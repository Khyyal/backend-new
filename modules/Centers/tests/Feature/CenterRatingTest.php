<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\RatingFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\RatingService;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewRatings]);
    $this->url = "/api/v1/centers/{$this->center->id}/ratings";
});

test('guests and unassigned users are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)->assertForbidden();
});

test('returns a zeroed summary and empty data when there are no ratings', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('summary.average', null)
        ->assertJsonPath('summary.count', 0)
        ->assertJsonPath('data', []);
});

test('returns summary and data for existing ratings', function (): void {
    $client = ClientFactory::new()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $other = ClientFactory::new()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $ratingService = app(RatingService::class);
    $ratingService->create($client, $this->center, 5, 'Great place');
    $ratingService->create($other, $this->center, 3, null);

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('summary.average', 4)
        ->assertJsonPath('summary.count', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.client', 'Jane Smith')
        ->assertJsonPath('data.0.stars', 3)
        ->assertJsonPath('data.0.comment', null)
        ->assertJsonPath('data.1.client', 'John Doe')
        ->assertJsonPath('data.1.stars', 5)
        ->assertJsonPath('data.1.comment', 'Great place');
});

test('sort=highest lists the most stars first, then the newest', function (): void {
    $rate = fn (int $stars, int $daysAgo) => RatingFactory::new()
        ->forRateable($this->center)
        ->byRater(ClientFactory::new()->create())
        ->create(['stars' => $stars, 'created_at' => now()->subDays($daysAgo)]);

    $olderFive = $rate(5, 3);
    $one = $rate(1, 0);
    $newerFive = $rate(5, 2);
    $three = $rate(3, 1);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?sort=highest")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$newerFive->id, $olderFive->id, $three->id, $one->id]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?sort=newest")
        ->assertOk()
        ->assertJsonPath('data.*.id', [$one->id, $three->id, $newerFive->id, $olderFive->id]);
});

test('an unknown sort is rejected', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?sort=lowest")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
});

test('ratings are isolated per center', function (): void {
    $client = ClientFactory::new()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    app(RatingService::class)->create($client, $this->center, 5, null);

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($other, [CenterUserPermission::ViewRatings]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("/api/v1/centers/{$other->id}/ratings")
        ->assertOk()
        ->assertJsonPath('summary.count', 0)
        ->assertJsonPath('data', []);
});
