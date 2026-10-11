<?php

namespace Modules\Services\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Services\Database\Factories\ServiceSettingFactory;

class ServiceSetting extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'service_id',
        'key',
        'value',
    ];

    protected static function newFactory(): ServiceSettingFactory
    {
        return ServiceSettingFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Boolean view of the stored value (settings are booleans in most cases).
     */
    public function isEnabled(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }
}
