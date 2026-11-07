<?php

namespace Modules\Purchase\Models\Merchant;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $table = null;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [];

    /**
     * `purchases.merchant_id` is an unsigned integer, so the platform is stored as 0;
     * `merchant_type = 'platform'` is what tells it apart from a center.
     */
    public function getKey(): int
    {
        return 0;
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getAttribute($key)
    {
        if ($key === 'id') {
            return 0;
        }

        return null;
    }

    public function exists(): bool
    {
        return true;
    }

    public function getMorphClass(): string
    {
        return 'platform';
    }
}
