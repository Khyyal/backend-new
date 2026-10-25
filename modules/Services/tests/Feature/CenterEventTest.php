<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\Event;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->url = "/api/v1/centers/{$this->center->id}/events";
    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'فعالية', 'en' => 'Event'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'occurrence_type' => 'general',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-05',
        'open_date' => '2026-10-01',
        'close_date' => '2026-10-31',
        'max_tickets_per_day' => 100,
        'price_options' => [['name' => 'Adult', 'price' => 50], ['name' => 'Child', 'price' => 25]],
        ...$extra,
    ];
    $this->tempMedia = fn (string $name = 'a.png') => app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image($name), $this->user)->uuid;
});

test('guests and unassigned users are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();
});

test('creates a service with media', function (): void {
    $cover = ($this->tempMedia)();
    $images = [($this->tempMedia)('b.png'), ($this->tempMedia)('c.png')];

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['cover' => $cover, 'images' => $images]))
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'فعالية')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.occurrence_type', 'general')
        ->assertJsonPath('data.start_date', '2026-11-01')
        ->assertJsonPath('data.end_date', '2026-11-05')
        ->assertJsonPath('data.open_date', '2026-10-01')
        ->assertJsonPath('data.close_date', '2026-10-31')
        ->assertJsonPath('data.max_tickets_per_day', 100)
        ->assertJsonCount(2, 'data.price_options')
        ->assertJsonPath('data.price_options.0.name', 'Adult')
        ->assertJsonPath('data.price_options.0.price', 50)
        ->assertJsonPath('data.cover.id', $cover)
        ->assertJsonPath('data.images.0.id', $images[0]);

    expect(Event::count())->toBe(1);
});

test('creates a specific_day service', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'occurrence_type' => 'specific_day',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.occurrence_type', 'specific_day')
        ->assertJsonPath('data.start_date', '2026-11-01')
        ->assertJsonPath('data.end_date', '2026-11-01');
});

test('validates the body', function (): void {
    // end_date before start_date
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['end_date' => '2026-10-31']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);

    // missing required date fields
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['open_date' => null, 'close_date' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['open_date', 'close_date']);

    // invalid occurrence_type
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['occurrence_type' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occurrence_type']);

    // price_options shape
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['price_options' => [['name' => 'x']]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price_options.0.price']);

    // max_tickets_per_day must be >= 1
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['max_tickets_per_day' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['max_tickets_per_day']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'name' => ['ar' => 'جديد'],
        'max_tickets_per_day' => 50,
        'price_options' => [['name' => 'Single', 'price' => 75]],
    ]))->assertOk()
        ->assertJsonPath('data.name.ar', 'جديد')
        ->assertJsonPath('data.max_tickets_per_day', 50)
        ->assertJsonCount(1, 'data.price_options')
        ->assertJsonPath('data.price_options.0.name', 'Single');

    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();
});

test('update syncs cover and images, keeping them when omitted', function (): void {
    $this->actingAs($this->user, 'center_user');
    $cover = ($this->tempMedia)();
    $a = ($this->tempMedia)('a.png');
    $b = ($this->tempMedia)('b.png');
    $id = $this->postJson($this->url, ($this->body)(['cover' => $cover, 'images' => [$a, $b]]))->json('data.id');

    $this->putJson("{$this->url}/{$id}", ($this->body)())
        ->assertOk()->assertJsonPath('data.cover.id', $cover)->assertJsonCount(2, 'data.images');

    $this->putJson("{$this->url}/{$id}", ($this->body)(['cover' => null, 'images' => [$b]]))
        ->assertOk()->assertJsonPath('data.cover', null)
        ->assertJsonCount(1, 'data.images')->assertJsonPath('data.images.0.id', $b);
});

test('cannot access a service of another center', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    $this->getJson("/api/v1/centers/{$other->id}/events/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/events/{$id}")->assertNotFound();
});
