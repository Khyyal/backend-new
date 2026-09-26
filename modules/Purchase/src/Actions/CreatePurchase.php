<?php

namespace Modules\Purchase\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Purchase\Contracts\Buyer;
use Modules\Purchase\Contracts\Purchasable;
use Modules\Purchase\Enums\PurchaseSource;
use Modules\Purchase\Events\PurchaseCreated;
use Modules\Purchase\Models\Purchase;
use Modules\Purchase\Models\PurchaseItem;

class CreatePurchase
{
    /**
     * @param  Buyer  $buyer  Model that implements Buyer (Center, Client, etc.)
     * @param  Model  $merchant  Merchant-side model (Center for service sales, Platform for membership sales)
     * @param  PurchaseSource  $source  Who initiated the purchase (client/center/system)
     * @param  array<int, array>  $items  Each item: purchasable (Purchasable), name (?string), quantity (int default 1), unit_price (numeric), discount_amount (numeric default 0), tax_amount (numeric default 0), metadata (array default [])
     * @param  array<string, mixed>  $metadata  Optional purchase-level metadata
     */
    public function execute(
        Buyer $buyer,
        Model $merchant,
        PurchaseSource $source,
        array $items,
        array $metadata = [],
    ): Purchase {
        if (count($items) === 0) {
            throw new InvalidArgumentException('A purchase must contain at least one item.');
        }

        $normalized = [];
        $totals = [
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
        ];

        foreach ($items as $idx => $raw) {
            if (! isset($raw['purchasable']) || ! $raw['purchasable'] instanceof Purchasable) {
                throw new InvalidArgumentException(
                    sprintf('Item #%d is missing a valid "purchasable" instance of %s.', $idx + 1, Purchasable::class)
                );
            }

            $purchasable = $raw['purchasable'];

            if (! isset($raw['unit_price'])) {
                throw new InvalidArgumentException(sprintf('Item #%d is missing "unit_price".', $idx + 1));
            }

            $unitPrice = (float) $raw['unit_price'];
            if ($unitPrice <= 0) {
                throw new InvalidArgumentException(sprintf('Item #%d unit_price must be greater than 0.', $idx + 1));
            }

            $quantity = isset($raw['quantity']) ? (int) $raw['quantity'] : 1;
            if ($quantity <= 0) {
                throw new InvalidArgumentException(sprintf('Item #%d quantity must be greater than 0.', $idx + 1));
            }

            $name = $raw['name'] ?? $this->resolvePurchasableName($purchasable);
            if ($name === '' || $name === null) {
                throw new InvalidArgumentException(sprintf('Item #%d name cannot be empty.', $idx + 1));
            }

            $discount = (float) ($raw['discount_amount'] ?? 0);
            $tax = (float) ($raw['tax_amount'] ?? 0);
            $itemMeta = (array) ($raw['metadata'] ?? []);

            $subtotal = $this->roundMoney($unitPrice * $quantity);
            $itemTotal = $this->roundMoney($subtotal - $discount + $tax);

            $totals['subtotal'] += $subtotal;
            $totals['discount_amount'] += $discount;
            $totals['tax_amount'] += $tax;
            $totals['total_amount'] += $itemTotal;

            $normalized[] = [
                'purchasable' => $purchasable,
                'name' => (string) $name,
                'quantity' => $quantity,
                'unit_price' => $this->roundMoney($unitPrice),
                'subtotal' => $subtotal,
                'discount_amount' => $this->roundMoney($discount),
                'tax_amount' => $this->roundMoney($tax),
                'total_amount' => $itemTotal,
                'metadata' => $itemMeta,
            ];
        }

        $totals = array_map(fn ($v): float => $this->roundMoney($v), $totals);

        $purchase = DB::transaction(function () use (
            $buyer,
            $merchant,
            $source,
            $metadata,
            $normalized,
            $totals,
        ): Purchase {
            $purchase = Purchase::create([
                'buyer_type' => $buyer->getMorphClass(),
                'buyer_id' => $buyer->getKey(),
                'merchant_type' => $merchant->getMorphClass(),
                'merchant_id' => $merchant->getKey(),
                'source' => $source,
                'status' => \Modules\Purchase\Enums\PurchaseStatus::Pending,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            foreach ($normalized as $ni) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->getKey(),
                    'purchasable_type' => $ni['purchasable']->getMorphClass(),
                    'purchasable_id' => $ni['purchasable']->getKey(),
                    'name' => $ni['name'],
                    'quantity' => $ni['quantity'],
                    'unit_price' => $ni['unit_price'],
                    'subtotal' => $ni['subtotal'],
                    'discount_amount' => $ni['discount_amount'],
                    'tax_amount' => $ni['tax_amount'],
                    'total_amount' => $ni['total_amount'],
                    'metadata' => $ni['metadata'] === [] ? null : $ni['metadata'],
                ]);
            }

            DB::afterCommit(static function () use ($purchase): void {
                event(new PurchaseCreated($purchase));
            });

            return $purchase;
        });

        $purchase->load('items');

        return $purchase;
    }

    private function resolvePurchasableName(Purchasable $purchasable): ?string
    {
        if (method_exists($purchasable, 'getAttribute')) {
            $name = $purchasable->getAttribute('name');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        if ($purchasable instanceof Model && isset($purchasable->title)) {
            $title = $purchasable->title;
            if (is_string($title) && $title !== '') {
                return $title;
            }
        }

        return null;
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}
