<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\Visit;
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
    $this->url = "/api/v1/centers/{$this->center->id}/visits";
    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'زيارة', 'en' => 'Visit'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'enter_type' => 'specific_time',
        'price' => 150,
        'days' => [0, 2],
        'hours' => ['09:00', '12:00', '14:00', '17:00'],
        ...$extra,
    ];
    $this->allDayBody = fn (array $extra = []) => [
        'name' => ['ar' => 'زيارة', 'en' => 'Visit'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'enter_type' => 'all_day',
        'price' => 150,
        'days' => [0, 2],
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

test('creates a specific_time service with media', function (): void {
    $cover = ($this->tempMedia)();
    $images = [($this->tempMedia)('b.png'), ($this->tempMedia)('c.png')];

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['cover' => $cover, 'images' => $images]))
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'زيارة')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.enter_type', 'specific_time')
        ->assertJsonPath('data.price', 150)
        ->assertJsonPath('data.days', [0, 2])
        ->assertJsonPath('data.hours', ['09:00', '12:00', '14:00', '17:00'])
        ->assertJsonPath('data.cover.id', $cover)
        ->assertJsonPath('data.images.0.id', $images[0]);

    expect(Visit::count())->toBe(1);
});

test('creates an all_day service with days only, no hours', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->allDayBody)())
        ->assertCreated()
        ->assertJsonPath('data.enter_type', 'all_day')
        ->assertJsonPath('data.price', 150)
        ->assertJsonPath('data.days', [0, 2])
        ->assertJsonPath('data.hours', []);

    expect(Visit::count())->toBe(1);
});

test('validates the body', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['hours' => ['09:00'], 'days' => [9]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours', 'days.0']);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['hours' => ['12:00', '09:00']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours.1']);

    // specific_time requires hours
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['hours' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours']);

    // all_day forbids hours
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->allDayBody)(['hours' => ['09:00', '12:00']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours']);

    // invalid enter_type
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['enter_type' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enter_type']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->allDayBody)([
        'name' => ['ar' => 'جديد'],
        'price' => 90,
        'days' => [5],
    ]))->assertOk()
        ->assertJsonPath('data.name.ar', 'جديد')
        ->assertJsonPath('data.enter_type', 'all_day')
        ->assertJsonPath('data.price', 90)
        ->assertJsonPath('data.days', [5])
        ->assertJsonPath('data.hours', []);

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

    $this->getJson("/api/v1/centers/{$other->id}/visits/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/visits/{$id}")->assertNotFound();
});
