<?php

namespace Modules\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Gives the model a public, human-friendly `code` (6 characters from CODE_ALPHABET) generated on insert.
 * The alphabet leaves out I, L, O, 0 and 1 so codes are easy to read aloud. The table's unique index on
 * `code` is the final guard against a collision.
 */
trait HasUniqueCode
{
    public const CODE_LENGTH = 6;

    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Sets the code right before the insert, like Laravel's HasUuids, so it is filled even when
     * model events are faked or muted.
     */
    protected function performInsert(Builder $query): bool
    {
        $this->code ??= static::generateUniqueCode();

        return parent::performInsert($query);
    }

    /**
     * A random code not used by any row, soft-deleted ones included.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = static::randomCode();
        } while (static::codeQuery()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Rows with this code; the lookup ignores surrounding whitespace and case.
     */
    public function scopeWhereCode(Builder $query, string $code): Builder
    {
        return $query->where($this->qualifyColumn('code'), strtoupper(trim($code)));
    }

    private static function randomCode(): string
    {
        $maxIndex = strlen(self::CODE_ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $maxIndex)];
        }

        return $code;
    }

    private static function codeQuery(): Builder
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? static::withTrashed()
            : static::query();
    }
}
