<?php

namespace Modules\Notifications\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notifications\Enums\CampaignActionType;
use Modules\Notifications\Enums\CampaignAudience;
use Modules\Notifications\Enums\CampaignChannel;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Enums\CampaignTargetMode;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * @extends Factory<NotificationCampaign>
 */
class NotificationCampaignFactory extends Factory
{
    protected $model = NotificationCampaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'channel' => CampaignChannel::Push,
            'audience' => CampaignAudience::Clients,
            'target_mode' => CampaignTargetMode::All,
            'status' => CampaignStatus::Draft,
            'title' => ['en' => 'Weekend offer', 'ar' => 'عرض نهاية الأسبوع'],
            'body' => ['en' => 'Book a ride this weekend.', 'ar' => 'احجز جولة هذا الأسبوع.'],
            'action_type' => CampaignActionType::None,
        ];
    }

    public function whatsapp(?WhatsAppTemplate $template = null): static
    {
        return $this->state(fn (): array => [
            'channel' => CampaignChannel::WhatsApp,
            'title' => null,
            'body' => null,
            'whatsapp_template_id' => $template ?? WhatsAppTemplateFactory::new(),
            'template_parameters' => [
                ['source' => 'recipient', 'field' => 'first_name'],
                ['source' => 'fixed', 'value' => 'RIDE20'],
            ],
        ]);
    }

    public function forCenters(): static
    {
        return $this->state(['audience' => CampaignAudience::Centers]);
    }

    public function status(CampaignStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
