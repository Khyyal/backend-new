<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Enums\PeriodGender;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\ServicePeriod;
use Modules\Support\Enums\ActivationStatus;
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

    $this->makeService = fn ($center) => Resort::query()->create()->service()->create([
        'name' => ['ar' => 'منتجع'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $center->id,
        'status' => ActivationStatus::ACTIVE,
        'type' => ServiceType::Resort,
    ]);

    $this->service = ($this->makeService)($this->center);
    $this->url = "/api/v1/centers/{$this->center->id}/services/{$this->service->id}/periods";
});

test('guests and unassigned users are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->putJson($this->url, [])->assertForbidden();
});

test('a service without periods returns null for both genders', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.female', null)
        ->assertJsonPath('data.male', null);
});

test('defines only a female period', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson($this->url, ['female' => ['days' => [1, 3], 'hours' => ['09:00', '12:00', '16:00', '18:00']]])
        ->assertOk()
        ->assertJsonPath('data.female.days', [1, 3])
        ->assertJsonPath('data.female.hours', ['09:00', '12:00', '16:00', '18:00'])
        ->assertJsonPath('data.male', null);

    expect(ServicePeriod::count())->toBe(4);
});

test('defines both genders and reads them back', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson($this->url, [
            'female' => ['days' => [0], 'hours' => ['08:00', '10:00']],
            'male' => ['days' => [5, 6], 'hours' => ['18:00', '20:00']],
        ])->assertOk();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertJsonPath('data.female.days', [0])
        ->assertJsonPath('data.male.days', [5, 6])
        ->assertJsonPath('data.male.hours', ['18:00', '20:00']);
});

test('update replaces periods and null or omitted clears a gender', function (): void {
    ServicePeriod::factory()->for($this->service)->forGender(PeriodGender::Male)->create();
    ServicePeriod::factory()->for($this->service)->forGender(PeriodGender::Female)->create();

    $this->actingAs($this->user, 'center_user')
        ->putJson($this->url, ['female' => ['days' => [2], 'hours' => ['10:00', '11:00']], 'male' => null])
        ->assertOk()
        ->assertJsonPath('data.female.days', [2])
        ->assertJsonPath('data.male', null);

    $this->actingAs($this->user, 'center_user')->putJson($this->url, [])->assertOk();

    expect(ServicePeriod::count())->toBe(0);
});

test('rejects invalid periods', function (array $body, string $field): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson($this->url, $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'day out of range' => [['female' => ['days' => [7], 'hours' => ['09:00', '10:00']]], 'female.days.0'],
    'duplicate day' => [['male' => ['days' => [1, 1], 'hours' => ['09:00', '10:00']]], 'male.days.0'],
    'missing days' => [['female' => ['hours' => ['09:00', '10:00']]], 'female.days'],
    'missing hours' => [['male' => ['days' => [1]]], 'male.hours'],
    'bad time format' => [['female' => ['days' => [1], 'hours' => ['9am', '10:00']]], 'female.hours.0'],
    'odd hours count' => [['female' => ['days' => [1], 'hours' => ['09:00', '10:00', '11:00']]], 'female.hours'],
    'end before start' => [['male' => ['days' => [1], 'hours' => ['10:00', '09:00']]], 'male.hours.1'],
]);

test('a service of another center is not found', function (): void {
    $other = ($this->makeService)(CenterFactory::new()->create());

    $this->actingAs($this->user, 'center_user')
        ->getJson("/api/v1/centers/{$this->center->id}/services/{$other->id}/periods")
        ->assertNotFound();
});
