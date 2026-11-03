<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Services\Database\Factories\ServiceFactory;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageTrainingServices]);
    $this->url = "/api/v1/centers/{$this->center->id}/services/by-type";
});

test('guests and unassigned users are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();
});

test('services are grouped by type with only id and name', function (): void {
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id, 'name' => ['en' => 'Jumping', 'ar' => 'قفز']]);
    ServiceFactory::new()->create();

    $response = $this->actingAs($this->user, 'center_user')->getJson($this->url)->assertOk();

    $response->assertJsonCount(7, 'data');
    $training = collect($response->json('data'))->firstWhere('service_type', 'training');

    expect($training['services'])->toHaveCount(1)
        ->and(array_keys($training['services'][0]))->toBe(['id', 'name'])
        ->and($training['services'][0]['id'])->toBe($service->id);

    $others = collect($response->json('data'))->where('service_type', '!=', 'training');
    expect($others->every(fn (array $group) => $group['services'] === []))->toBeTrue();
});
