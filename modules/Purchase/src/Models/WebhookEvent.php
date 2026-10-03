<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Purchase\Database\Factories\WebhookEventFactory;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'external_event_id',
        'event_type',
        'payload',
        'received_at',
        'processed_at',
        'failed_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function newFactory(): WebhookEventFactory
    {
        return WebhookEventFactory::new();
    }
}
