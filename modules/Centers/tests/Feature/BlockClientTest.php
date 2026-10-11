<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\BlockService;
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
    $this->url = "/api/v1/centers/{$this->center->id}/blocked-clients";
});

test('guests and unassigned users are rejected', function (): void {
    $this->postJson($this->url)->assertUnauthorized();

    $client = ClientFactory::new()->create();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ['client_id' => $client->id, 'reason' => 'x'])
        ->assertForbidden();
});

test('center blocks a client with a reason', function (): void {
    $client = ClientFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ['client_id' => $client->id, 'reason' => 'No-shows'])
        ->assertCreated()
        ->assertJsonPath('data.client_id', $client->id)
        ->assertJsonPath('data.reason', 'No-shows');

    expect(app(BlockService::class)->isBlocked($this->center, $client))->toBeTrue();
    expect($client->blocksReceived)->toHaveCount(1);
    expect($this->center->blocksGiven)->toHaveCount(1);
});

test('blocking again updates the reason without duplicating', function (): void {
    $client = ClientFactory::new()->create();

    foreach (['First', 'Second'] as $reason) {
        $this->actingAs($this->user, 'center_user')
            ->postJson($this->url, ['client_id' => $client->id, 'reason' => $reason])
            ->assertSuccessful();
    }

    expect($client->blocksReceived()->count())->toBe(1);
    expect($client->blocksReceived()->first()->reason)->toBe('Second');
});

test('client_id and reason are validated', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ['client_id' => 9999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id', 'reason']);
});

test('unblock removes the block', function (): void {
    $client = ClientFactory::new()->create();
    $service = app(BlockService::class);
    $service->block($this->center, $client, 'x');

    expect($service->unblock($this->center, $client))->toBeTrue();
    expect($service->isBlocked($this->center, $client))->toBeFalse();
});

test('lists only the center\'s blocked clients, newest first', function (): void {
    $service = app(BlockService::class);
    $first = ClientFactory::new()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $second = ClientFactory::new()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);
    $service->block($this->center, $first, 'No-shows');
    $this->travel(1)->minutes();
    $service->block($this->center, $second, 'Rude');
    $service->block(CenterFactory::new()->create(), ClientFactory::new()->create(), 'Other center');

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.client', 'Jane Smith')
        ->assertJsonPath('data.0.reason', 'Rude')
        ->assertJsonPath('data.1.client_id', $first->id);
});

test('listing blocked clients requires center access', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)->assertForbidden();
});
