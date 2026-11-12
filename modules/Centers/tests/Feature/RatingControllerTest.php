<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\RatingFactory;
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

describe('my ratings', function (): void {
    beforeEach(function (): void {
        $this->client = ClientFactory::new()->create();
        $this->center = CenterFactory::new()->create();

        $this->rating = fn (int $stars, $client = null, array $attributes = [], $center = null) => RatingFactory::new()
            ->forRateable($center ?? $this->center)
            ->byRater($client ?? $this->client)
            ->create(['stars' => $stars, ...$attributes]);
    });

    test('listing and editing require a client token', function (): void {
        $rating = ($this->rating)(3);

        $this->getJson('/api/v1/clients/ratings')->assertUnauthorized();
        $this->patchJson("/api/v1/clients/ratings/{$rating->id}", ['stars' => 4])->assertUnauthorized();
    });

    test('lists only my ratings, newest first by default', function (): void {
        $older = ($this->rating)(5, attributes: ['created_at' => now()->subDay()]);
        $otherCenter = CenterFactory::new()->create();
        $newer = ($this->rating)(2, attributes: ['comment' => 'Too crowded.'], center: $otherCenter);
        ($this->rating)(4, ClientFactory::new()->create());

        $this->actingAs($this->client, 'client')
            ->getJson('/api/v1/clients/ratings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.stars', 2)
            ->assertJsonPath('data.0.comment', 'Too crowded.')
            ->assertJsonPath('data.0.center.id', $otherCenter->id)
            ->assertJsonPath('data.1.id', $older->id);
    });

    test('sort=highest lists the most stars first', function (): void {
        $five = ($this->rating)(5, attributes: ['created_at' => now()->subDays(2)]);
        $one = ($this->rating)(1, center: CenterFactory::new()->create());
        $three = ($this->rating)(3, attributes: ['created_at' => now()->subDay()], center: CenterFactory::new()->create());

        $this->actingAs($this->client, 'client')
            ->getJson('/api/v1/clients/ratings?sort=highest')
            ->assertOk()
            ->assertJsonPath('data.0.id', $five->id)
            ->assertJsonPath('data.1.id', $three->id)
            ->assertJsonPath('data.2.id', $one->id);
    });

    test('an unknown sort is rejected', function (): void {
        $this->actingAs($this->client, 'client')
            ->getJson('/api/v1/clients/ratings?sort=lowest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    });

    test('edits the stars and comment of my rating', function (): void {
        $rating = ($this->rating)(2, attributes: ['comment' => 'Too crowded.']);

        $this->actingAs($this->client, 'client')
            ->patchJson("/api/v1/clients/ratings/{$rating->id}", ['stars' => 4, 'comment' => 'Better this time.'])
            ->assertOk()
            ->assertJsonPath('rating.id', $rating->id)
            ->assertJsonPath('rating.stars', 4)
            ->assertJsonPath('rating.comment', 'Better this time.')
            ->assertJsonPath('rating.center.id', $this->center->id);

        expect($rating->refresh())
            ->stars->toBe(4)
            ->comment->toBe('Better this time.');
        expect($this->center->averageRating())->toBe(4.0);
    });

    test('a field left out of an edit keeps its value', function (): void {
        $rating = ($this->rating)(2, attributes: ['comment' => 'Too crowded.']);

        $this->actingAs($this->client, 'client')
            ->patchJson("/api/v1/clients/ratings/{$rating->id}", ['stars' => 5])
            ->assertOk();

        expect($rating->refresh())->stars->toBe(5)->comment->toBe('Too crowded.');

        $this->actingAs($this->client, 'client')
            ->patchJson("/api/v1/clients/ratings/{$rating->id}", ['comment' => null])
            ->assertOk();

        expect($rating->refresh())->stars->toBe(5)->comment->toBeNull();
    });

    test('another client\'s rating cannot be edited', function (): void {
        $rating = ($this->rating)(2, ClientFactory::new()->create());

        $this->actingAs($this->client, 'client')
            ->patchJson("/api/v1/clients/ratings/{$rating->id}", ['stars' => 5])
            ->assertNotFound();

        expect($rating->refresh()->stars)->toBe(2);
    });

    test('an edit with stars out of range is rejected', function (int $stars): void {
        $rating = ($this->rating)(3);

        $this->actingAs($this->client, 'client')
            ->patchJson("/api/v1/clients/ratings/{$rating->id}", ['stars' => $stars])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stars');

        expect($rating->refresh()->stars)->toBe(3);
    })->with([0, 6]);
});
