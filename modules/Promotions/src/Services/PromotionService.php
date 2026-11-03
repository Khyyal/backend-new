<?php

namespace Modules\Promotions\Services;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Promotions\Enums\PromotionStatus;
use Modules\Promotions\Models\Promotion;

/**
 * Promotion lifecycle, shared by the admin and center APIs.
 */
class PromotionService
{
    /** Fields that change what a promotion is worth; frozen once a purchase has used it. */
    private const FINANCIAL_FIELDS = ['scope', 'discount_type', 'discount_value', 'max_discount_amount'];

    public function __construct(
        private readonly ActivePromotionRepository $repository,
        private readonly CoverMedia $cover,
    ) {}

    /**
     * New promotions always start as drafts.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Model $actor): Promotion
    {
        return DB::transaction(function () use ($data, $actor): Promotion {
            $promotion = Promotion::create(array_merge($this->attributes($data), [
                'status' => PromotionStatus::Draft->value,
                'creator_type' => $actor->getMorphClass(),
                'creator_id' => $actor->getKey(),
            ]));

            $this->cover->sync($promotion, $actor, $data);

            return $promotion->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Promotion $promotion, array $data, Model $actor): Promotion
    {
        $this->assertNotArchived($promotion);

        if ($this->hasBeenUsed($promotion)) {
            foreach (self::FINANCIAL_FIELDS as $field) {
                if (array_key_exists($field, $data) && $this->changes($promotion, $field, $data[$field])) {
                    throw ValidationException::withMessages([$field => __('promotions::messages.locked_after_use')]);
                }
            }
        }

        return DB::transaction(function () use ($promotion, $data, $actor): Promotion {
            $promotion->update($this->attributes($data));
            $this->cover->sync($promotion, $actor, $data);

            return $promotion->refresh();
        });
    }

    public function transition(Promotion $promotion, PromotionStatus $to): Promotion
    {
        $allowed = match ($to) {
            PromotionStatus::Active => [PromotionStatus::Draft, PromotionStatus::Paused],
            PromotionStatus::Paused => [PromotionStatus::Active],
            PromotionStatus::Archived => [PromotionStatus::Draft, PromotionStatus::Active, PromotionStatus::Paused],
            PromotionStatus::Draft => [],
        };

        if (! in_array($promotion->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('promotions::messages.invalid_transition', ['from' => $promotion->status->value, 'to' => $to->value]),
            ]);
        }

        $promotion->update(['status' => $to->value]);

        return $promotion->refresh();
    }

    /**
     * @param  list<array{type: string, id: int, mode?: string|null}>  $targets
     */
    public function syncTargets(Promotion $promotion, array $targets): Promotion
    {
        $this->assertNotArchived($promotion);

        if ($this->hasBeenUsed($promotion)) {
            throw ValidationException::withMessages(['targets' => __('promotions::messages.locked_after_use')]);
        }

        DB::transaction(function () use ($promotion, $targets): void {
            $promotion->targets()->delete();

            foreach ($targets as $target) {
                $promotion->targets()->create([
                    'target_type' => $target['type'],
                    'target_id' => $target['id'],
                    'mode' => $target['mode'] ?? 'include',
                ]);
            }
        });

        $this->repository->flush();

        return $promotion->load('targets');
    }

    /**
     * @param  list<array{day_of_week: int, start_time: string, end_time: string}>  $schedules
     */
    public function syncSchedules(Promotion $promotion, array $schedules): Promotion
    {
        $this->assertNotArchived($promotion);

        DB::transaction(function () use ($promotion, $schedules): void {
            $promotion->schedules()->delete();

            foreach ($schedules as $schedule) {
                $promotion->schedules()->create($schedule);
            }
        });

        $this->repository->flush();

        return $promotion->load('schedules');
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    /**
     * Whether any purchase has already been priced with this promotion.
     */
    public function hasBeenUsed(Promotion $promotion): bool
    {
        return DB::table('purchase_adjustments')->where('promotion_id', $promotion->getKey())->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        unset($data['cover']);

        return $data;
    }

    private function assertNotArchived(Promotion $promotion): void
    {
        if ($promotion->status === PromotionStatus::Archived) {
            throw ValidationException::withMessages(['status' => __('promotions::messages.archived')]);
        }
    }

    private function changes(Promotion $promotion, string $field, mixed $value): bool
    {
        $current = $promotion->getAttribute($field);
        $current = $current instanceof BackedEnum ? $current->value : $current;

        return (string) $current !== (string) $value;
    }
}
