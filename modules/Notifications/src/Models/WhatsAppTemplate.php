<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Notifications\Database\Factories\WhatsAppTemplateFactory;

/**
 * A template approved in Voxa / Meta, registered here so campaigns can fill its variables.
 *
 * @property array<int, array{label: string, default_source?: string|null, default_field?: string|null}> $variables
 */
class WhatsAppTemplate extends Model
{
    /** @use HasFactory<WhatsAppTemplateFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name',
        'provider_name',
        'language',
        'body',
        'variables',
        'is_active',
    ];

    protected static function newFactory(): WhatsAppTemplateFactory
    {
        return WhatsAppTemplateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(NotificationCampaign::class, 'whatsapp_template_id');
    }

    public function variablesCount(): int
    {
        return count($this->variables ?? []);
    }
}
