<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Support\Database\Factories\BlockFactory;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'blocker_id',
        'blocker_type',
        'blockable_id',
        'blockable_type',
        'reason',
    ];

    protected static function newFactory(): BlockFactory
    {
        return BlockFactory::new();
    }

    public function blocker(): MorphTo
    {
        return $this->morphTo();
    }

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }
}
