<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
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
    $this->url = "/api/v1/centers/{$this->center->id}/service-types/counts";
});

function attachServiceOfType(int $centerId, ServiceType $type): void
{
    Resort::query()->create()->service()->create([
        'name' => ['ar' => 'اسم'],
        'description' => ['ar' => 'وصف'],
        'center_id' => $centerId,
        'status' => ActivationStatus::ACTIVE,
        'type' => $type,
    ]);
}

test('guests and unassigned users are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)->assertForbidden();
});

test('returns all service types with zero counts when the center has none', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(count(ServiceType::cases()), 'data')
        ->assertJsonPath('data.0.service_type', 'recreation_riding')
        ->assertJsonPath('data.0.count', 0)
        ->assertJsonPath('data.3.service_type', 'resort')
        ->assertJsonPath('data.3.count', 0);
});

test('counts services per type, zero for types with none', function (): void {
    attachServiceOfType($this->center->id, ServiceType::Resort);
    attachServiceOfType($this->center->id, ServiceType::Resort);
    attachServiceOfType($this->center->id, ServiceType::Event);

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.1.service_type', 'visit')
        ->assertJsonPath('data.1.count', 0)
        ->assertJsonPath('data.2.service_type', 'event')
        ->assertJsonPath('data.2.count', 1)
        ->assertJsonPath('data.3.service_type', 'resort')
        ->assertJsonPath('data.3.count', 2);
});

test('counts are isolated per center', function (): void {
    attachServiceOfType($this->center->id, ServiceType::Resort);

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    attachServiceOfType($other->id, ServiceType::Resort);
    attachServiceOfType($other->id, ServiceType::Resort);

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.3.count', 1);

    $this->actingAs($this->user, 'center_user')
        ->getJson("/api/v1/centers/{$other->id}/service-types/counts")
        ->assertOk()
        ->assertJsonPath('data.3.count', 2);
});

test('soft-deleted services are not counted', function (): void {
    attachServiceOfType($this->center->id, ServiceType::Resort);
    attachServiceOfType($this->center->id, ServiceType::Resort);

    Service::query()->where('center_id', $this->center->id)->first()->delete();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.3.count', 1);
});
