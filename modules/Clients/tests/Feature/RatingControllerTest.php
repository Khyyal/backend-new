<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);


test('rate endpoint requires authentication', function (): void {
    $center = CenterFactory::new()->create();

    $response = $this->postJson('/api/v1/clients/ratings', [
        'center_id' => $center->id,
        'stars' => 5,
    ]);

    $response->assertStatus(401);
});


test('rate returns 422 when center_id is missing', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'stars' => 5,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('center_id');
});


test('rate returns 422 when center_id is not an integer', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => 'not-an-id',
            'stars' => 5,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('center_id');
});


test('rate returns 422 when center_id does not exist', function (): void {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => 999999,
            'stars' => 5,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('center_id');
});


test('rate returns 422 when stars is missing', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('stars');
});


test('rate returns 422 when stars is not an integer', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 'five',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('stars');
});


test('rate returns 422 when stars is less than 1', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 0,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('stars');
});


test('rate returns 422 when stars is greater than 5', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 6,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('stars');
});


test('rate returns 422 when comment is not a string', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 5,
            'comment' => ['not' => 'a string'],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('comment');
});


test('rate returns 422 when comment exceeds 1000 characters', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 5,
            'comment' => str_repeat('x', 1001),
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('comment');
});


test('rate creates a rating with valid data and no comment', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 4,
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'rating' => [
            'id',
            'stars',
            'comment',
            'center_id',
            'rater_id',
        ],
    ]);

    expect($response->json('rating.stars'))->toBe(4);
    expect($response->json('rating.comment'))->toBeNull();
    expect($response->json('rating.center_id'))->toBe($center->id);
    expect($response->json('rating.rater_id'))->toBe($client->id);

    $this->assertDatabaseHas('ratings', [
        'rateable_id' => $center->id,
        'rateable_type' => $center->getMorphClass(),
        'rater_id' => $client->id,
        'rater_type' => $client->getMorphClass(),
        'stars' => 4,
        'comment' => null,
    ]);
});


test('rate creates a rating with a comment', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();
    $comment = 'Great service and friendly staff!';

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 5,
            'comment' => $comment,
        ]);

    $response->assertStatus(201);
    expect($response->json('rating.stars'))->toBe(5);
    expect($response->json('rating.comment'))->toBe($comment);

    $this->assertDatabaseHas('ratings', [
        'rateable_id' => $center->id,
        'rateable_type' => $center->getMorphClass(),
        'rater_id' => $client->id,
        'rater_type' => $client->getMorphClass(),
        'stars' => 5,
        'comment' => $comment,
    ]);
});


test('rate creates a 1-star rating successfully', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 1,
            'comment' => 'Very poor experience.',
        ]);

    $response->assertStatus(201);
    expect($response->json('rating.stars'))->toBe(1);

    $this->assertDatabaseHas('ratings', [
        'stars' => 1,
    ]);
});


test('rate creates a 3-star rating successfully', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 3,
        ]);

    $response->assertStatus(201);
    expect($response->json('rating.stars'))->toBe(3);
});


test('rate stores the correct polymorphic rater and rateable types', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 5,
        ])->assertStatus(201);

    $this->assertDatabaseHas('ratings', [
        'rater_id' => $client->id,
        'rater_type' => $client->getMorphClass(),
        'rateable_id' => $center->id,
        'rateable_type' => $center->getMorphClass(),
    ]);
});


test('rating is accessible via rateable relationship on center', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 4,
            'comment' => 'Good overall.',
        ])->assertStatus(201);

    $center->load('ratings');
    expect($center->ratings)->toHaveCount(1);
    expect($center->ratings->first()->stars)->toBe(4);
    expect($center->ratings->first()->comment)->toBe('Good overall.');
});


test('rating is accessible via ratingsGiven relationship on client', function (): void {
    $client = ClientFactory::new()->create();
    $center = CenterFactory::new()->create();

    $this->actingAs($client, 'client')
        ->postJson('/api/v1/clients/ratings', [
            'center_id' => $center->id,
            'stars' => 5,
        ])->assertStatus(201);

    $client->load('ratingsGiven');
    expect($client->ratingsGiven)->toHaveCount(1);
    expect($client->ratingsGiven->first()->stars)->toBe(5);
});
