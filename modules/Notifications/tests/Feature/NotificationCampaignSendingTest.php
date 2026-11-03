<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Notifications\Database\Factories\NotificationCampaignFactory;
use Modules\Notifications\Database\Factories\WhatsAppTemplateFactory;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Enums\CampaignTargetMode;
use Modules\Notifications\Enums\DeliveryStatus;
use Modules\Notifications\Jobs\SendCampaignChunkJob;
use Modules\Notifications\Notifications\CampaignNotification;
use Modules\Notifications\Services\CampaignService;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\WhatsAppSendException;
use Modules\Support\WhatsApp\WhatsAppSendResult;
use Modules\Support\WhatsApp\WhatsAppTemplateMessage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->gateway = new class implements WhatsAppGateway
    {
        /** @var list<WhatsAppTemplateMessage> */
        public array $sent = [];

        public ?WhatsAppSendException $failWith = null;

        public function sendTemplate(WhatsAppTemplateMessage $message): WhatsAppSendResult
        {
            if ($this->failWith !== null) {
                throw $this->failWith;
            }

            $this->sent[] = $message;

            return new WhatsAppSendResult('msg-'.count($this->sent));
        }

        public function sendText(string $toNumber, string $text): WhatsAppSendResult
        {
            return new WhatsAppSendResult(null);
        }
    };
    $this->app->instance(WhatsAppGateway::class, $this->gateway);

    $this->campaigns = app(CampaignService::class);
    $this->template = WhatsAppTemplateFactory::new()->create(['provider_name' => 'weekend_offer', 'language' => 'ar']);
});

test('a push campaign notifies every client and finishes as sent', function (): void {
    Notification::fake();
    $clients = ClientFactory::new()->count(3)->create();
    $campaign = NotificationCampaignFactory::new()->create();

    expect($this->campaigns->sendNow($campaign))->toBeTrue();

    Notification::assertSentTo($clients, CampaignNotification::class);
    expect($campaign->fresh())
        ->status->toBe(CampaignStatus::Sent)
        ->recipients_count->toBe(3)
        ->sent_at->not->toBeNull();
});

test('a center push campaign reaches only active staff of the selected centers', function (): void {
    Notification::fake();
    [$targeted, $other] = CenterFactory::new()->count(2)->create();
    $active = UserFactory::new()->create();
    $inactive = UserFactory::new()->create();
    $otherStaff = UserFactory::new()->create();
    $targeted->users()->attach($active, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()]);
    $targeted->users()->attach($inactive, ['status' => ActivationStatus::INACTIVE->value, 'joined_at' => now()->toDateString()]);
    $other->users()->attach($otherStaff, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()]);

    $campaign = NotificationCampaignFactory::new()->forCenters()->create(['target_mode' => CampaignTargetMode::Selected]);
    $campaign->targets()->create(['target_id' => $targeted->id]);

    $this->campaigns->sendNow($campaign);

    Notification::assertSentTo($active, CampaignNotification::class);
    Notification::assertNotSentTo([$inactive, $otherStaff], CampaignNotification::class);
});

test('campaign push copy comes from the campaign translations', function (): void {
    $campaign = NotificationCampaignFactory::new()->create();
    $notification = new CampaignNotification($campaign);

    expect($notification->toArray(ClientFactory::new()->create())['title'])->toBe(['en' => 'Weekend offer', 'ar' => 'عرض نهاية الأسبوع'])
        ->and($notification->toFcm(new stdClass, 'ar')->notification->title)->toBe('عرض نهاية الأسبوع')
        ->and($notification->toFcm(new stdClass, 'ar')->data['campaign_id'])->toBe((string) $campaign->id);
});

test('a whatsapp campaign resolves parameters per client and records deliveries', function (): void {
    $ali = ClientFactory::new()->create(['first_name' => 'Ali', 'phone_number' => '0501234567']);
    $sara = ClientFactory::new()->create(['first_name' => 'Sara', 'phone_number' => '+966 55 000 1111']);
    $invalid = ClientFactory::new()->create(['phone_number' => '12']);
    $campaign = NotificationCampaignFactory::new()->whatsapp($this->template)->create();

    $this->campaigns->sendNow($campaign);

    expect(collect($this->gateway->sent)->map(fn (WhatsAppTemplateMessage $m): array => [$m->toNumber, $m->templateName, $m->language, $m->parameters])->all())
        ->toEqualCanonicalizing([
            ['966501234567', 'weekend_offer', 'ar', ['Ali', 'RIDE20']],
            ['966550001111', 'weekend_offer', 'ar', ['Sara', 'RIDE20']],
        ]);

    expect($campaign->fresh())
        ->status->toBe(CampaignStatus::Sent)
        ->recipients_count->toBe(3)
        ->sent_count->toBe(2)
        ->failed_count->toBe(1);

    expect($campaign->deliveries()->where('recipient_id', $invalid->id)->sole()->status)->toBe(DeliveryStatus::Failed)
        ->and($campaign->deliveries()->where('recipient_id', $ali->id)->sole()->provider_message_id)->not->toBeNull()
        ->and($sara->id)->toBeInt();
});

test('a whatsapp campaign to centers fills staff and center fields', function (): void {
    $center = CenterFactory::new()->create(['name' => 'Desert Riders']);
    $staff = UserFactory::new()->create(['name' => 'Omar', 'phone' => '0559998888']);
    $center->users()->attach($staff, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()]);

    $template = WhatsAppTemplateFactory::new()->create();
    $campaign = NotificationCampaignFactory::new()->whatsapp($template)->forCenters()->create([
        'template_parameters' => [
            ['source' => 'recipient', 'field' => 'staff_name'],
            ['source' => 'recipient', 'field' => 'center_name'],
        ],
    ]);

    $this->campaigns->sendNow($campaign);

    expect($this->gateway->sent)->toHaveCount(1)
        ->and($this->gateway->sent[0]->toNumber)->toBe('966559998888')
        ->and($this->gateway->sent[0]->parameters)->toBe(['Omar', 'Desert Riders']);
});

test('a retried chunk does not message the same recipient twice', function (): void {
    $client = ClientFactory::new()->create(['phone_number' => '0501234567']);
    $campaign = NotificationCampaignFactory::new()->whatsapp($this->template)->status(CampaignStatus::Sending)->create();

    (new SendCampaignChunkJob($campaign->id, [$client->id], true))->handle($this->campaigns);
    (new SendCampaignChunkJob($campaign->id, [$client->id], true))->handle($this->campaigns);

    expect($this->gateway->sent)->toHaveCount(1)
        ->and($campaign->deliveries()->count())->toBe(1);
});

test('an authentication failure marks the whatsapp campaign failed', function (): void {
    ClientFactory::new()->create(['phone_number' => '0501234567']);
    $this->gateway->failWith = new WhatsAppSendException('Unauthorized', 401);
    $campaign = NotificationCampaignFactory::new()->whatsapp($this->template)->create();

    try {
        $this->campaigns->sendNow($campaign);
    } catch (WhatsAppSendException) {
        // The sync queue rethrows the failed chunk; the batch catch callback has already run.
    }

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Failed);
});

test('the scheduler sends only campaigns that are due', function (): void {
    Notification::fake();
    ClientFactory::new()->create();
    $due = NotificationCampaignFactory::new()->status(CampaignStatus::Scheduled)->create(['scheduled_at' => now()->subMinute()]);
    $later = NotificationCampaignFactory::new()->status(CampaignStatus::Scheduled)->create(['scheduled_at' => now()->addHour()]);

    $this->artisan('notifications:dispatch-due-campaigns')->expectsOutput('Dispatched 1 campaign(s).')->assertSuccessful();

    expect($due->fresh()->status)->toBe(CampaignStatus::Sent)
        ->and($later->fresh()->status)->toBe(CampaignStatus::Scheduled);
});
