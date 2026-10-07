<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\Resort;
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
    $this->url = "/api/v1/centers/{$this->center->id}/resorts";
    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'منتجع', 'en' => 'Resort'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'days' => [['day' => 0, 'price' => 150], ['day' => 5, 'price' => 220]],
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
        ->assertJsonPath('data.name.ar', 'منتجع')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(2, 'data.days')
        ->assertJsonPath('data.days.0.day', 0)
        ->assertJsonPath('data.days.0.price', 150)
        ->assertJsonPath('data.days.1.day', 5)
        ->assertJsonPath('data.days.1.price', 220)
        ->assertJsonCount(2, 'data.price_options')
        ->assertJsonPath('data.price_options.0.name', 'Adult')
        ->assertJsonPath('data.price_options.0.price', 50)
        ->assertJsonPath('data.cover.id', $cover)
        ->assertJsonPath('data.images.0.id', $images[0]);

    expect(Resort::count())->toBe(1);
});

test('accepts partial weekday coverage', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['days' => [['day' => 2, 'price' => 99]]]))
        ->assertCreated()
        ->assertJsonCount(1, 'data.days')
        ->assertJsonPath('data.days.0.day', 2);
});

test('validates the body', function (): void {
    // duplicate weekday rejected
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['days' => [['day' => 0, 'price' => 100], ['day' => 0, 'price' => 200]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days.0.day', 'days.1.day']);

    // invalid day number
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['days' => [['day' => 9, 'price' => 100]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days.0.day']);

    // price_options shape
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['price_options' => [['name' => 'x']]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price_options.0.price']);

    // missing days entirely
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['days' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'name' => ['ar' => 'جديد'],
        'days' => [['day' => 3, 'price' => 300]],
        'price_options' => [['name' => 'Single', 'price' => 75]],
    ]))->assertOk()
        ->assertJsonPath('data.name.ar', 'جديد')
        ->assertJsonCount(1, 'data.days')
        ->assertJsonPath('data.days.0.day', 3)
        ->assertJsonPath('data.days.0.price', 300)
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

    $this->getJson("/api/v1/centers/{$other->id}/resorts/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/resorts/{$id}")->assertNotFound();
});
