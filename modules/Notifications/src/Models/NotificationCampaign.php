<?php

namespace Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Notifications\Database\Factories\NotificationCampaignFactory;
use Modules\Notifications\Enums\CampaignActionType;
use Modules\Notifications\Enums\CampaignAudience;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Enums\CampaignTargetMode;
use Modules\Support\Models\SystemUser;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * @property array<int, array{source: string, value?: string|null, field?: string|null}>|null $template_parameters
 */
#[Translatable('title', 'body')]
class NotificationCampaign extends Model
{
    /** @use HasFactory<NotificationCampaignFactory> */
    use HasFactory, HasTranslations, SoftDeletes;

    protected $fillable = [
        'name',
        'channel',
        'audience',
        'target_mode',
        'status',
        'title',
        'body',
        'action_type',
        'action_target_id',
        'action_url',
        'whatsapp_template_id',
        'template_parameters',
        'scheduled_at',
        'sent_at',
        'recipients_count',
        'sent_count',
        'failed_count',
        'created_by',
    ];

    protected static function newFactory(): NotificationCampaignFactory
    {
        return NotificationCampaignFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => CampaignChannel::class,
            'audience' => CampaignAudience::class,
            'target_mode' => CampaignTargetMode::class,
            'status' => CampaignStatus::class,
            'action_type' => CampaignActionType::class,
            'template_parameters' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SystemUser::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NotificationCampaignTarget::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationCampaignDelivery::class);
    }

    /**
     * @return list<int>
     */
    public function targetIds(): array
    {
        return $this->targets()->pluck('target_id')->map(fn ($id): int => (int) $id)->all();
    }
}
