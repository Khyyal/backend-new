<?php

namespace Modules\Promotions\Services;

use Illuminate\Support\Collection;
use Modules\Promotions\Data\DiscountableLine;
use Modules\Promotions\Data\DiscountResult;
use Modules\Promotions\Data\PriceAdjustment;
use Modules\Promotions\Enums\DiscountType;
use Modules\Promotions\Enums\FundedBy;
use Modules\Promotions\Enums\PromotionScope;
use Modules\Promotions\Enums\PromotionStacking;
use Modules\Promotions\Models\Promotion;

/**
 * Pure discount maths, in minor units. Receives promotions already known to
 * be live (status, date window, schedule) and decides what each is worth.
 *
 * Rules:
 *  - each line takes at most one item-scope offer: the largest discount, then highest priority, then lowest id;
 *  - at most one order-scope offer applies, on top of the item discounts;
 *  - an exclusive item offer blocks order offers; an exclusive order offer only wins when it beats
 *    the item discounts, in which case it replaces them;
 *  - a discount never exceeds the amount it applies to, and a fixed price never raises a price.
 */
class DiscountEngine
{
    /**
     * Whether a promotion can do anything for these lines: it needs at least
     * one matching line and, if it has a minimum, enough spend on those lines.
     *
     * @param  list<DiscountableLine>  $lines
     * @return 'ok'|'not_applicable'|'min_subtotal'
     */
    public function eligibility(Promotion $promotion, array $lines): string
    {
        $matching = array_filter($lines, fn (DiscountableLine $line): bool => $promotion->appliesToLine($line));

        if ($matching === []) {
            return 'not_applicable';
        }

        return $this->meetsMinimum($promotion, $matching) ? 'ok' : 'min_subtotal';
    }

    /**
     * @param  list<DiscountableLine>  $lines
     * @param  Collection<int, Promotion>  $promotions
     */
    public function apply(array $lines, Collection $promotions): DiscountResult
    {
        $linesByIndex = [];

        foreach ($lines as $line) {
            $linesByIndex[$line->index] = $line;
        }

        if ($lines === [] || $promotions->isEmpty()) {
            return new DiscountResult(array_fill_keys(array_keys($linesByIndex), 0), []);
        }

        [$itemDiscounts, $itemWinners] = $this->applyItemOffers(
            $lines,
            $promotions->filter(fn (Promotion $promotion): bool => $promotion->scope === PromotionScope::Item),
        );

        $orderOutcome = $this->chooseOrderOffer(
            $linesByIndex,
            $promotions->filter(fn (Promotion $promotion): bool => $promotion->scope === PromotionScope::Order),
            $itemDiscounts,
            $itemWinners,
        );

        if ($orderOutcome['replacesItemOffers']) {
            $itemDiscounts = array_fill_keys(array_keys($linesByIndex), 0);
            $itemWinners = [];
        }

        $lineDiscounts = $itemDiscounts;
        $adjustments = [];

        foreach ($itemWinners as $index => $winner) {
            if ($itemDiscounts[$index] > 0) {
                $adjustments[] = $this->adjustment($winner['promotion'], $itemDiscounts[$index], $index);
            }
        }

        if ($orderOutcome['promotion'] !== null) {
            foreach ($orderOutcome['allocation'] as $index => $amount) {
                $lineDiscounts[$index] += $amount;
            }

            $adjustments[] = $this->adjustment($orderOutcome['promotion'], array_sum($orderOutcome['allocation']), null);
        }

        foreach ($linesByIndex as $index => $line) {
            $lineDiscounts[$index] = min($lineDiscounts[$index], $line->subtotal());
        }

        return new DiscountResult($lineDiscounts, $adjustments);
    }

    /**
     * @param  list<DiscountableLine>  $lines
     * @param  Collection<int, Promotion>  $promotions
     * @return array{0: array<int, int>, 1: array<int, array{promotion: Promotion, amount: int}>}
     */
    private function applyItemOffers(array $lines, Collection $promotions): array
    {
        $discounts = [];
        $winners = [];

        foreach ($lines as $line) {
            $discounts[$line->index] = 0;
        }

        $eligible = $promotions->filter(fn (Promotion $promotion): bool => $this->eligibility($promotion, $lines) === 'ok');

        foreach ($lines as $line) {
            $best = null;

            foreach ($eligible as $promotion) {
                if (! $promotion->appliesToLine($line)) {
                    continue;
                }

                $amount = $this->itemDiscount($promotion, $line);

                if ($amount > 0 && ($best === null || $this->beats($promotion, $amount, $best['promotion'], $best['amount']))) {
                    $best = ['promotion' => $promotion, 'amount' => $amount];
                }
            }

            if ($best !== null) {
                $winners[$line->index] = $best;
                $discounts[$line->index] = $best['amount'];
            }
        }

        $winners = $this->enforceCaps($winners, $discounts);

        return [$discounts, $winners];
    }

    /**
     * Trim each promotion's discounts so their combined total stays within max_discount_amount.
     *
     * @param  array<int, array{promotion: Promotion, amount: int}>  $winners
     * @param  array<int, int>  $discounts
     * @return array<int, array{promotion: Promotion, amount: int}>
     */
    private function enforceCaps(array $winners, array &$discounts): array
    {
        $spent = [];

        foreach ($winners as $index => $winner) {
            $promotion = $winner['promotion'];

            if ($promotion->max_discount_amount === null) {
                continue;
            }

            $remaining = max(0, $promotion->max_discount_amount - ($spent[$promotion->id] ?? 0));
            $amount = min($discounts[$index], $remaining);
            $discounts[$index] = $amount;
            $winners[$index]['amount'] = $amount;
            $spent[$promotion->id] = ($spent[$promotion->id] ?? 0) + $amount;
        }

        return $winners;
    }

    private function itemDiscount(Promotion $promotion, DiscountableLine $line): int
    {
        $base = $line->subtotal();

        $amount = match ($promotion->discount_type) {
            DiscountType::Percentage => intdiv($base * $promotion->discount_value, 10000),
            DiscountType::FixedAmount => $promotion->discount_value * $line->quantity,
            DiscountType::FixedPrice => max(0, $line->unitPrice - $promotion->discount_value) * $line->quantity,
        };

        return min($amount, $base);
    }

    /**
     * @param  array<int, DiscountableLine>  $linesByIndex
     * @param  Collection<int, Promotion>  $promotions
     * @param  array<int, int>  $itemDiscounts
     * @param  array<int, array{promotion: Promotion, amount: int}>  $itemWinners
     * @return array{promotion: Promotion|null, allocation: array<int, int>, replacesItemOffers: bool}
     */
    private function chooseOrderOffer(array $linesByIndex, Collection $promotions, array $itemDiscounts, array $itemWinners): array
    {
        $none = ['promotion' => null, 'allocation' => [], 'replacesItemOffers' => false];

        if ($promotions->isEmpty()) {
            return $none;
        }

        $itemOffersAreExclusive = collect($itemWinners)->contains(
            fn (array $winner): bool => $winner['promotion']->stacking === PromotionStacking::Exclusive
        );

        $itemTotal = array_sum($itemDiscounts);
        $best = null;

        foreach ($promotions as $promotion) {
            $exclusive = $promotion->stacking === PromotionStacking::Exclusive;

            if ($itemOffersAreExclusive && ! $exclusive) {
                continue;
            }

            $matching = array_filter($linesByIndex, fn (DiscountableLine $line): bool => $promotion->appliesToLine($line));

            if ($matching === [] || ! $this->meetsMinimum($promotion, $matching)) {
                continue;
            }

            $bases = [];

            foreach ($matching as $index => $line) {
                $bases[$index] = $exclusive ? $line->subtotal() : max(0, $line->subtotal() - $itemDiscounts[$index]);
            }

            $amount = $this->orderDiscount($promotion, array_sum($bases));

            if ($amount <= 0 || ($exclusive && $amount <= $itemTotal)) {
                continue;
            }

            if ($best === null || $this->beats($promotion, $amount, $best['promotion'], $best['amount'])) {
                $best = [
                    'promotion' => $promotion,
                    'amount' => $amount,
                    'allocation' => $this->allocate($amount, $bases),
                    'exclusive' => $exclusive,
                ];
            }
        }

        if ($best === null) {
            return $none;
        }

        return [
            'promotion' => $best['promotion'],
            'allocation' => $best['allocation'],
            'replacesItemOffers' => $best['exclusive'],
        ];
    }

    private function orderDiscount(Promotion $promotion, int $base): int
    {
        $amount = match ($promotion->discount_type) {
            DiscountType::Percentage => intdiv($base * $promotion->discount_value, 10000),
            DiscountType::FixedAmount => $promotion->discount_value,
            DiscountType::FixedPrice => 0,
        };

        if ($promotion->max_discount_amount !== null) {
            $amount = min($amount, $promotion->max_discount_amount);
        }

        return min($amount, $base);
    }

    /**
     * Largest-remainder allocation so the per-line amounts add up exactly to the total.
     *
     * @param  array<int, int>  $bases
     * @return array<int, int>
     */
    private function allocate(int $total, array $bases): array
    {
        $baseTotal = array_sum($bases);
        $allocation = array_fill_keys(array_keys($bases), 0);

        if ($baseTotal <= 0 || $total <= 0) {
            return $allocation;
        }

        $remainders = [];

        foreach ($bases as $index => $base) {
            $allocation[$index] = intdiv($total * $base, $baseTotal);
            $remainders[$index] = ($total * $base) % $baseTotal;
        }

        $leftover = $total - array_sum($allocation);
        arsort($remainders);

        foreach (array_keys($remainders) as $index) {
            if ($leftover <= 0) {
                break;
            }

            if ($allocation[$index] < $bases[$index]) {
                $allocation[$index]++;
                $leftover--;
            }
        }

        return $allocation;
    }

    /**
     * @param  array<int, DiscountableLine>  $matching
     */
    private function meetsMinimum(Promotion $promotion, array $matching): bool
    {
        if ($promotion->min_subtotal_amount === null) {
            return true;
        }

        return array_sum(array_map(fn (DiscountableLine $line): int => $line->subtotal(), $matching)) >= $promotion->min_subtotal_amount;
    }

    private function beats(Promotion $candidate, int $amount, Promotion $current, int $currentAmount): bool
    {
        if ($amount !== $currentAmount) {
            return $amount > $currentAmount;
        }

        if ($candidate->priority !== $current->priority) {
            return $candidate->priority > $current->priority;
        }

        return $candidate->id < $current->id;
    }

    private function adjustment(Promotion $promotion, int $amount, ?int $lineIndex): PriceAdjustment
    {
        $label = array_filter($promotion->getTranslations('badge_label')) ?: $promotion->getTranslations('name');

        $centerShare = match ($promotion->funded_by) {
            FundedBy::Platform => 0,
            FundedBy::Center => $amount,
            FundedBy::Shared => intdiv($amount * $promotion->center_share_bps, 10000),
        };

        return new PriceAdjustment(
            promotionId: $promotion->id,
            label: $label,
            discountType: $promotion->discount_type,
            discountValue: $promotion->discount_value,
            amount: $amount,
            fundedBy: $promotion->funded_by,
            centerShareAmount: $centerShare,
            lineIndex: $lineIndex,
        );
    }
}
