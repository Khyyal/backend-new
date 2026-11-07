<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Notifications\Database\Factories\NotificationCampaignFactory;
use Modules\Notifications\Database\Factories\WhatsAppTemplateFactory;
use Modules\Notifications\Enums\CampaignStatus;
use Modules\Notifications\Jobs\DispatchCampaignJob;
use Modules\Notifications\Models\NotificationCampaign;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = SystemUserFactory::new()
        ->withPermissions([SystemPermission::ManageNotificationCampaigns, SystemPermission::ManageWhatsAppTemplates])
        ->create();
    $this->template = WhatsAppTemplateFactory::new()->create();

    $this->push = [
        'name' => 'Weekend offer',
        'channel' => 'push',
        'audience' => 'clients',
        'target_mode' => 'all',
        'title' => ['en' => 'Weekend offer', 'ar' => 'عرض نهاية الأسبوع'],
        'body' => ['en' => 'Book now', 'ar' => 'احجز الآن'],
    ];
    $this->whatsapp = [
        'name' => 'WhatsApp offer',
        'channel' => 'whatsapp',
        'audience' => 'clients',
        'target_mode' => 'all',
        'whatsapp_template_id' => $this->template->id,
        'template_parameters' => [
            ['source' => 'recipient', 'field' => 'first_name'],
            ['source' => 'fixed', 'value' => 'RIDE20'],
        ],
    ];
});

test('admin endpoints require a system user with the permission', function (): void {
    $this->getJson('/api/v1/admin/notification-campaigns')->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson('/api/v1/admin/notification-campaigns')->assertForbidden();
    $this->postJson('/api/v1/admin/whatsapp-templates', [])->assertForbidden();
});

test('admin registers a whatsapp template with ordered variables', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/whatsapp-templates', [
            'name' => 'Booking reminder',
            'provider_name' => 'booking_reminder',
            'language' => 'ar',
            'body' => 'مرحبا {{1}}، موعدك في {{2}}',
            'variables' => [
                ['label' => 'Client name', 'default_source' => 'recipient', 'default_field' => 'first_name'],
                ['label' => 'Center', 'default_source' => 'fixed'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.provider_name', 'booking_reminder')
        ->assertJsonCount(2, 'data.variables');

    $this->postJson('/api/v1/admin/whatsapp-templates', [
        'name' => 'Duplicate',
        'provider_name' => 'booking_reminder',
        'language' => 'ar',
        'variables' => [],
    ])->assertJsonValidationErrors('provider_name');
});

test('a template used by an unfinished campaign cannot be deleted', function (): void {
    NotificationCampaignFactory::new()->whatsapp($this->template)->create();

    $this->actingAs($this->admin, 'system_user')
        ->deleteJson("/api/v1/admin/whatsapp-templates/{$this->template->id}")
        ->assertUnprocessable();
});

test('admin creates push and whatsapp campaigns as drafts', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->postJson('/api/v1/admin/notification-campaigns', $this->push)
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title.ar', 'عرض نهاية الأسبوع');

    $this->postJson('/api/v1/admin/notification-campaigns', $this->whatsapp)
        ->assertCreated()
        ->assertJsonPath('data.channel', 'whatsapp')
        ->assertJsonPath('data.template.id', $this->template->id)
        ->assertJsonPath('data.template_parameters.1.value', 'RIDE20');
});

test('whatsapp parameters must match the template and the audience', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->postJson('/api/v1/admin/notification-campaigns', [
        ...$this->whatsapp,
        'template_parameters' => [['source' => 'fixed', 'value' => 'only one']],
    ])->assertJsonValidationErrors('template_parameters');

    $this->postJson('/api/v1/admin/notification-campaigns', [
        ...$this->whatsapp,
        'audience' => 'centers',
    ])->assertJsonValidationErrors('template_parameters.0.field');

    $this->postJson('/api/v1/admin/notification-campaigns', [
        ...$this->whatsapp,
        'whatsapp_template_id' => WhatsAppTemplateFactory::new()->inactive()->create()->id,
    ])->assertJsonValidationErrors('whatsapp_template_id');

    $this->postJson('/api/v1/admin/notification-campaigns', [...$this->whatsapp, 'title' => ['en' => 'x', 'ar' => 'y']])
        ->assertJsonValidationErrors('title');
});

test('selected targets must exist in the audience', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $center = CenterFactory::new()->create();

    $this->postJson('/api/v1/admin/notification-campaigns', [...$this->push, 'target_mode' => 'selected'])
        ->assertJsonValidationErrors('target_ids');

    $this->postJson('/api/v1/admin/notification-campaigns', [...$this->push, 'target_mode' => 'selected', 'target_ids' => [999999]])
        ->assertJsonValidationErrors('target_ids');

    $this->postJson('/api/v1/admin/notification-campaigns', [
        ...$this->push,
        'audience' => 'centers',
        'target_mode' => 'selected',
        'target_ids' => [$center->id],
    ])->assertCreated()
        ->assertJsonPath('data.target_ids', [$center->id])
        ->assertJsonPath('data.targets', [['id' => $center->id, 'name' => $center->name]]);
});

test('the show endpoint returns client targets with their names', function (): void {
    $this->actingAs($this->admin, 'system_user');
    $client = ClientFactory::new()->create(['first_name' => 'Aisha', 'last_name' => 'Noor']);

    $id = $this->postJson('/api/v1/admin/notification-campaigns', [
        ...$this->push,
        'audience' => 'clients',
        'target_mode' => 'selected',
        'target_ids' => [$client->id],
    ])->assertCreated()->json('data.id');

    $this->getJson("/api/v1/admin/notification-campaigns/{$id}")
        ->assertOk()
        ->assertJsonPath('data.targets', [['id' => $client->id, 'name' => 'Aisha Noor']]);
});

test('scheduling, sending, duplicating and cancelling follow the lifecycle', function (): void {
    Bus::fake([DispatchCampaignJob::class]);
    $this->actingAs($this->admin, 'system_user');
    $campaign = NotificationCampaignFactory::new()->create();

    $this->postJson("/api/v1/admin/notification-campaigns/{$campaign->id}/schedule", ['scheduled_at' => now()->addDay()->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    $this->postJson("/api/v1/admin/notification-campaigns/{$campaign->id}/send")
        ->assertAccepted()
        ->assertJsonPath('data.status', 'sending');
    Bus::assertDispatched(DispatchCampaignJob::class, 1);

    $this->postJson("/api/v1/admin/notification-campaigns/{$campaign->id}/send")->assertUnprocessable();
    $this->patchJson("/api/v1/admin/notification-campaigns/{$campaign->id}", ['name' => 'Renamed'])->assertUnprocessable();
    $this->postJson("/api/v1/admin/notification-campaigns/{$campaign->id}/cancel")->assertUnprocessable();

    $this->postJson("/api/v1/admin/notification-campaigns/{$campaign->id}/duplicate")
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

test('send_now on create claims and dispatches the campaign', function (): void {
    Bus::fake([DispatchCampaignJob::class]);
    ClientFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/notification-campaigns', [...$this->push, 'send_now' => true])
        ->assertCreated()
        ->assertJsonPath('data.status', 'sending');

    Bus::assertDispatched(DispatchCampaignJob::class);
});

test('switching a draft to whatsapp clears its push content', function (): void {
    $campaign = NotificationCampaignFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/notification-campaigns/{$campaign->id}", [
            'channel' => 'whatsapp',
            'whatsapp_template_id' => $this->template->id,
            'template_parameters' => $this->whatsapp['template_parameters'],
        ])
        ->assertOk()
        ->assertJsonPath('data.title', null);

    expect(NotificationCampaign::query()->find($campaign->id)->status)->toBe(CampaignStatus::Draft);
});
