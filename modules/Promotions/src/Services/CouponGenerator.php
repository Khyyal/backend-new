<?php

namespace Modules\Promotions\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Promotions\Enums\CouponStatus;
use Modules\Promotions\Models\Promotion;

/**
 * Bulk coupon code generation. Codes avoid look-alike characters and are
 * inserted with insert-or-ignore, so a collision with an existing code simply
 * triggers another round.
 */
class CouponGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CHUNK = 500;

    private const MAX_ROUNDS = 20;

    /**
     * @param  array{usage_limit?: int|null, per_client_usage_limit?: int|null, starts_at?: string|null, ends_at?: string|null, is_assigned_only?: bool, status?: string}  $options
     * @return int Number of coupons created.
     */
    public function generate(Promotion $promotion, int $count, string $prefix, int $length, string $batchId, array $options = [], ?Model $creator = null): int
    {
        $created = 0;

        for ($round = 0; $round < self::MAX_ROUNDS && $created < $count; $round++) {
            foreach (array_chunk($this->randomCodes($count - $created, $prefix, $length), self::CHUNK) as $codes) {
                $now = now();

                $created += DB::table('coupons')->insertOrIgnore(array_map(fn (string $code): array => [
                    'promotion_id' => $promotion->getKey(),
                    'code' => $code,
                    'usage_limit' => $options['usage_limit'] ?? null,
                    'per_client_usage_limit' => $options['per_client_usage_limit'] ?? null,
                    'starts_at' => $options['starts_at'] ?? null,
                    'ends_at' => $options['ends_at'] ?? null,
                    'is_assigned_only' => $options['is_assigned_only'] ?? false,
                    'status' => $options['status'] ?? CouponStatus::Active->value,
                    'batch_id' => $batchId,
                    'creator_type' => $creator?->getMorphClass(),
                    'creator_id' => $creator?->getKey(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $codes));
            }
        }

        return $created;
    }

    public function newBatchId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @return list<string>
     */
    private function randomCodes(int $count, string $prefix, int $length): array
    {
        $codes = [];
        $max = strlen(self::ALPHABET) - 1;

        while (count($codes) < $count) {
            $suffix = '';

            for ($i = 0; $i < $length; $i++) {
                $suffix .= self::ALPHABET[random_int(0, $max)];
            }

            $codes[$prefix.$suffix] = true;
        }

        return array_keys($codes);
    }
}
