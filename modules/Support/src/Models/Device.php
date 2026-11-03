<?php

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Support\Enums\DevicePlatform;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_identifier',
        'fcm_token',
        'platform',
        'locale',
        'last_seen_at',
        'deviceable_id',
        'deviceable_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function deviceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Devices registered on launch that are not linked to any user yet.
     */
    public function scopeUnowned(Builder $query): Builder
    {
        return $query->whereNull('deviceable_id')->whereNull('deviceable_type');
    }
}
