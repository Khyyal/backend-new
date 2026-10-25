<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('index requires authentication', function (): void {
    $response = $this->getJson('/api/v1/clients/favorites');

    $response->assertStatus(401);
});

test('store requires authentication', function (): void {
    $center = CenterFactory::new()->create();

    $response = $this->postJson('/api/v1/clients/favorites', [
        'center_id' => $center->id,
    ]);

    $response->assertStatus(401);
});

test('destroy requires authentication', function (): void {
    $center = CenterFactory::new()->create();

    $response = $this->deleteJson("/api/v1/clients/favorites/{$center->id}");

    $response->assertStatus(401);
});

test('store returns 422 when center_id is missing', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/favorites', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('center_id');
});

test('store returns 422 when center_id does not exist', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/favorites', [
            'center_id' => 999999,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('center_id');
});

test('store adds a center to the client favorites', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/favorites', [
            'center_id' => $center->id,
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'favorite' => ['center_id', 'client_id'],
    ]);

    expect($response->json('favorite.center_id'))->toBe($center->id);
    expect($response->json('favorite.client_id'))->toBe($client->id);

    $this->assertDatabaseHas('center_favorites', [
        'client_id' => $client->id,
        'center_id' => $center->id,
    ]);
});

test('store is idempotent when the center is already favorited', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $client->favoriteCenters()->attach($center->id);

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/favorites', [
            'center_id' => $center->id,
        ]);

    $response->assertStatus(200);

    $this->assertDatabaseCount('center_favorites', 1);
});

test('destroy removes a favorited center', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $client->favoriteCenters()->attach($center->id);

    $response = $this->actingAs($client, 'client')
        ->deleteJson("/api/v1/clients/favorites/{$center->id}");

    $response->assertStatus(200);

    $this->assertDatabaseMissing('center_favorites', [
        'client_id' => $client->id,
        'center_id' => $center->id,
    ]);
});

test('destroy returns 404 when the center is not favorited', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->deleteJson("/api/v1/clients/favorites/{$center->id}");

    $response->assertStatus(404);
});

test('index lists only the authenticated client favorite centers', function (): void {
    $client = ClientFactory::new()->create();
    $otherClient = ClientFactory::new()->create();

    $favoritedCenter = CenterFactory::new()->create();
    $otherCenter = CenterFactory::new()->create();
    $othersFavoriteCenter = CenterFactory::new()->create();

    $client->favoriteCenters()->attach($favoritedCenter->id);
    $otherClient->favoriteCenters()->attach($othersFavoriteCenter->id);

    $response = $this->actingAs($client, 'client')
        ->getJson('/api/v1/clients/favorites');

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($favoritedCenter->id);
});

test('favorite is accessible via favoriteCenters relationship on client', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/favorites', [
            'center_id' => $center->id,
        ])->assertStatus(201);

    $client->load('favoriteCenters');
    expect($client->favoriteCenters)->toHaveCount(1);
    expect($client->favoriteCenters->first()->id)->toBe($center->id);
});
