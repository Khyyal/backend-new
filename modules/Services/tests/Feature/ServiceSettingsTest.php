<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\Event;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServiceSetting;
use Modules\Services\Models\TrainingService;
use Modules\Services\Models\Visit;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    $base = [
        'name' => ['ar' => 'اسم', 'en' => 'Name'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
    ];

    $this->types = [
        'recreation-ridings' => [RecreationalRiding::class, [...$base,
            'price_options' => [['duration' => 30, 'price' => 100]],
            'days' => [0, 2],
            'hours' => ['09:00', '12:00'],
        ]],
        'training-services' => [TrainingService::class, [...$base,
            'price_options' => [['duration' => 30, 'price' => 100]],
        ]],
        'events' => [Event::class, [...$base,
            'occurrence_type' => 'general',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'open_date' => '2026-10-01',
            'close_date' => '2026-10-31',
            'max_tickets_per_day' => 100,
            'price_options' => [['name' => 'Adult', 'price' => 50]],
        ]],
        'resorts' => [Resort::class, [...$base,
            'days' => [['day' => 0, 'price' => 150]],
            'price_options' => [['name' => 'Adult', 'price' => 50]],
        ]],
        'visits' => [Visit::class, [...$base,
            'enter_type' => 'specific_time',
            'price' => 150,
            'days' => [0, 2],
            'hours' => ['09:00', '12:00'],
        ]],
    ];
});

it('declares the expected settings keys per service type', function (): void {
    expect(RecreationalRiding::settingKeys())->toBe(['enable_cash_on_arrival', 'enable_single_session_booking', 'enable_package_booking'])
        ->and(TrainingService::settingKeys())->toContain('enable_single_session_booking')
        ->and(Event::settingKeys())->toBe(['enable_cash_on_arrival'])
        ->and(Visit::settingKeys())->toBe(['enable_cash_on_arrival'])
        ->and(Resort::settingKeys())->toContain('close_all_resort_bookings');
});

it('returns every key as false when no settings were sent', function (string $uri): void {
    [$model, $body] = $this->types[$uri];

    $response = $this->actingAs($this->user, 'center_user')
        ->postJson("/api/v1/centers/{$this->center->id}/{$uri}", $body)
        ->assertCreated();

    expect($response->json('data.settings'))
        ->toHaveKeys($model::settingKeys())
        ->each->toBeFalse();

    expect(ServiceSetting::count())->toBe(0);
})->with(['recreation-ridings', 'training-services', 'events', 'resorts', 'visits']);

it('stores settings on create and replaces them on update', function (string $uri): void {
    [$model, $body] = $this->types[$uri];
    $keys = $model::settingKeys();
    $url = "/api/v1/centers/{$this->center->id}/{$uri}";

    $id = $this->actingAs($this->user, 'center_user')
        ->postJson($url, [...$body, 'settings' => [$keys[0] => true]])
        ->assertCreated()
        ->assertJsonPath("data.settings.{$keys[0]}", true)
        ->json('data.id');

    $this->actingAs($this->user, 'center_user')->getJson("{$url}/{$id}")
        ->assertJsonPath("data.settings.{$keys[0]}", true);

    // Omitting `settings` leaves stored values untouched.
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$url}/{$id}", $body)
        ->assertOk()
        ->assertJsonPath("data.settings.{$keys[0]}", true);

    // Sending `settings` replaces: omitted keys reset to false.
    $last = end($keys);
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$url}/{$id}", [...$body, 'settings' => [$last => true]])
        ->assertOk()
        ->assertJsonPath("data.settings.{$last}", true)
        ->assertJsonPath("data.settings.{$keys[0]}", $keys[0] === $last);
})->with(['recreation-ridings', 'training-services', 'events', 'resorts', 'visits']);

it('rejects unknown and non-boolean settings', function (string $uri): void {
    [$model, $body] = $this->types[$uri];
    $url = "/api/v1/centers/{$this->center->id}/{$uri}";

    $this->actingAs($this->user, 'center_user')
        ->postJson($url, [...$body, 'settings' => ['nope' => true]])
        ->assertUnprocessable()->assertJsonValidationErrors('settings');

    $this->actingAs($this->user, 'center_user')
        ->postJson($url, [...$body, 'settings' => [$model::settingKeys()[0] => 'maybe']])
        ->assertUnprocessable();
})->with(['recreation-ridings', 'training-services', 'events', 'resorts', 'visits']);

it('lets a system user manage settings through the admin routes', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();
    [, $body] = $this->types['events'];

    $this->actingAs($admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/events", [...$body, 'settings' => ['enable_cash_on_arrival' => true]])
        ->assertCreated()
        ->assertJsonPath('data.settings.enable_cash_on_arrival', true);
});
