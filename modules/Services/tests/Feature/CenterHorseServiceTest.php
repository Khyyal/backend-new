<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\HorseService;
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
    $this->url = "/api/v1/centers/{$this->center->id}/horse-services";
    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'خدمة الخيول', 'en' => 'Horse Service'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'price_options' => [['name' => 'Basic', 'price' => 100], ['name' => 'Premium', 'price' => 200]],
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
        ->assertJsonPath('data.name.ar', 'خدمة الخيول')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(2, 'data.price_options')
        ->assertJsonPath('data.price_options.0.name', 'Basic')
        ->assertJsonPath('data.price_options.0.price', 100)
        ->assertJsonPath('data.cover.id', $cover)
        ->assertJsonPath('data.images.0.id', $images[0]);

    expect(HorseService::count())->toBe(1);
});

test('accepts a single price option', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['price_options' => [['name' => 'Only', 'price' => 50]]]))
        ->assertCreated()
        ->assertJsonCount(1, 'data.price_options');
});

test('validates the body', function (): void {
    // at least one price option required
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['price_options' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price_options']);

    // price_options shape
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['price_options' => [['name' => 'x']]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['price_options.0.price']);

    // missing required name
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['name' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'name' => ['ar' => 'جديد'],
        'price_options' => [['name' => 'Single', 'price' => 75]],
    ]))->assertOk()
        ->assertJsonPath('data.name.ar', 'جديد')
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

    $this->getJson("/api/v1/centers/{$other->id}/horse-services/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/horse-services/{$id}")->assertNotFound();
});
