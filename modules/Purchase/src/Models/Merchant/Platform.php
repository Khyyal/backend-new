<?php

namespace Modules\Purchase\Models\Merchant;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $table = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [];

    public function getKey(): string
    {
        return 'platform';
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getAttribute($key)
    {
        if ($key === 'id') {
            return 'platform';
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
