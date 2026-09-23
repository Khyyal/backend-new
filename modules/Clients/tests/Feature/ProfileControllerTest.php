<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\CityFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);


test('me endpoint requires authentication', function (): void {
    $response = $this->getJson('/api/v1/clients/me');

    $response->assertStatus(401);
});


test('update profile endpoint requires authentication', function (): void {
    $response = $this->putJson('/api/v1/clients/profile', [
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $response->assertStatus(401);
});


test('me returns current client profile with resource structure', function (): void {
    $client = ClientFactory::new()->create([
        'first_name' => 'Fatima',
        'last_name' => 'Al-Saud',
    ]);

    $response = $this->actingAs($client, 'client')
        ->getJson('/api/v1/clients/me');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'first_name',
                'last_name',
                'phone_number',
                'city_id',
                'needs_onboarding',
            ],
        ]);

    expect($response->json('data.first_name'))->toBe('Fatima');
    expect($response->json('data.last_name'))->toBe('Al-Saud');
    expect($response->json('data.phone_number'))->toBe($client->phone_number);
    expect($response->json('data.id'))->toBe($client->id);
});


test('me loads city relationship when city is set', function (): void {
    $city = CityFactory::new()->create([
        'name' => ['en' => 'Riyadh', 'ar' => 'الرياض'],
        'radius' => 50,
    ]);

    $client = ClientFactory::new()->create([
        'city_id' => $city->id,
    ]);

    $response = $this->actingAs($client, 'client')
        ->getJson('/api/v1/clients/me');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'city' => [
                'id',
                'name',
                'radius',
            ],
        ],
    ]);

    expect($response->json('data.city.id'))->toBe($city->id);
    expect($response->json('data.city.radius'))->toBe(50);
});


test('update profile returns 422 when first_name is missing', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->putJson('/api/v1/clients/profile', [
            'last_name' => 'Doe',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('first_name');
});


test('update profile returns 422 when last_name is missing', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->putJson('/api/v1/clients/profile', [
            'first_name' => 'John',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('last_name');
});


test('update profile returns 422 when names are too short', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->putJson('/api/v1/clients/profile', [
            'first_name' => 'A',
            'last_name' => 'B',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['first_name', 'last_name']);
});


test('update profile persists names and returns client', function (): void {
    $client = ClientFactory::new()->create([
        'first_name' => null,
        'last_name' => null,
    ]);

    $response = $this->actingAs($client, 'client')
        ->putJson('/api/v1/clients/profile', [
            'first_name' => 'Noor',
            'last_name' => 'Khalid',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'needs_onboarding',
            'message',
            'client' => [
                'id',
                'first_name',
                'last_name',
            ],
        ]);

    expect($response->json('needs_onboarding'))->toBeFalse();
    expect($response->json('client.first_name'))->toBe('Noor');
    expect($response->json('client.last_name'))->toBe('Khalid');

    $client->refresh();
    expect($client->first_name)->toBe('Noor');
    expect($client->last_name)->toBe('Khalid');
});


test('update profile replaces existing names', function (): void {
    $client = ClientFactory::new()->create([
        'first_name' => 'OldFirst',
        'last_name' => 'OldLast',
    ]);

    $response = $this->actingAs($client, 'client')
        ->patchJson('/api/v1/clients/profile', [
            'first_name' => 'NewFirst',
            'last_name' => 'NewLast',
        ]);

    $response->assertStatus(200);
    expect($response->json('client.first_name'))->toBe('NewFirst');
    expect($response->json('client.last_name'))->toBe('NewLast');

    $client->refresh();
    expect($client->first_name)->toBe('NewFirst');
    expect($client->last_name)->toBe('NewLast');
});


test('needs_onboarding is true when names are null and false after update', function (): void {
    $client = ClientFactory::new()->create([
        'first_name' => null,
        'last_name' => null,
    ]);

    $meResponse = $this->actingAs($client, 'client')
        ->getJson('/api/v1/clients/me');
    $meResponse->assertStatus(200);
    expect($meResponse->json('data.needs_onboarding'))->toBeTrue();

    $updateResponse = $this->actingAs($client, 'client')
        ->putJson('/api/v1/clients/profile', [
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
        ]);

    $updateResponse->assertStatus(200);
    expect($updateResponse->json('needs_onboarding'))->toBeFalse();

    $this->assertTrue($client->fresh()->needs_onboarding === false);
});


test('update profile returns 422 when names are not strings', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->putJson('/api/v1/clients/profile', [
            'first_name' => 12345,
            'last_name' => true,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['first_name', 'last_name']);
});
