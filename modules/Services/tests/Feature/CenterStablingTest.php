<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\Service;
use Modules\Services\Models\Stabling;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageStablingServices]);
    $this->url = "/api/v1/centers/{$this->center->id}/stablings";
    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'إسطبل', 'en' => 'Stabling'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'monthly_prices' => [['months' => 3, 'price' => 420000], ['months' => 1, 'price' => 150000]],
        'daily_price' => ['price' => 6000],
        'extra_options' => [['name' => 'Grooming', 'price' => 30000]],
        ...$extra,
    ];
    $this->tempMedia = fn (string $name = 'a.png') => app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image($name), $this->user)->uuid;
});

test('guests and users without the stabling permission are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageHorseCareServices]);

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();
});

test('creates a stabling service with every price kind and media', function (): void {
    $cover = ($this->tempMedia)();
    $images = [($this->tempMedia)('b.png')];

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['cover' => $cover, 'images' => $images]))
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'إسطبل')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(2, 'data.monthly_prices')
        ->assertJsonPath('data.monthly_prices.0.months', 1)
        ->assertJsonPath('data.monthly_prices.0.price', 150000)
        ->assertJsonPath('data.monthly_prices.1.months', 3)
        ->assertJsonPath('data.daily_price.price', 6000)
        ->assertJsonPath('data.extra_options.0.name', 'Grooming')
        ->assertJsonPath('data.extra_options.0.price', 30000)
        ->assertJsonPath('data.cover.id', $cover)
        ->assertJsonPath('data.images.0.id', $images[0]);

    expect(Stabling::count())->toBe(1)
        ->and(PriceOption::query()->pluck('unit')->map->value->sort()->values()->all())
        ->toBe(['day', 'month', 'month', 'option']);
});

test('daily price and extras are optional', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['daily_price' => null, 'extra_options' => []]))
        ->assertCreated()
        ->assertJsonPath('data.daily_price', null)
        ->assertJsonCount(0, 'data.extra_options')
        ->assertJsonCount(2, 'data.monthly_prices');
});

test('validates the body', function (array $extra, string $error): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)($extra))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$error]);
})->with([
    'no monthly price' => [['monthly_prices' => []], 'monthly_prices'],
    'duplicate months' => [['monthly_prices' => [['months' => 1, 'price' => 1], ['months' => 1, 'price' => 2]]], 'monthly_prices.0.months'],
    'months out of range' => [['monthly_prices' => [['months' => 0, 'price' => 1]]], 'monthly_prices.0.months'],
    'daily price without price' => [['daily_price' => ['id' => 1]], 'daily_price.price'],
    'extra without name' => [['extra_options' => [['price' => 1]]], 'extra_options.0.name'],
    'negative price' => [['monthly_prices' => [['months' => 1, 'price' => -1]]], 'monthly_prices.0.price'],
]);

test('update keeps option ids sent back and deletes the rest', function (): void {
    $this->actingAs($this->user, 'center_user');
    $created = $this->postJson($this->url, ($this->body)())->json('data');
    $oneMonth = $created['monthly_prices'][0];
    $threeMonths = $created['monthly_prices'][1];
    $daily = $created['daily_price'];

    $this->putJson("{$this->url}/{$created['id']}", ($this->body)([
        'name' => ['ar' => 'جديد'],
        'monthly_prices' => [['id' => $oneMonth['id'], 'months' => 1, 'price' => 160000], ['months' => 6, 'price' => 800000]],
        'daily_price' => null,
        'extra_options' => [['id' => $created['extra_options'][0]['id'], 'name' => 'Full grooming', 'price' => 35000]],
    ]))->assertOk()
        ->assertJsonPath('data.name.ar', 'جديد')
        ->assertJsonPath('data.monthly_prices.0.id', $oneMonth['id'])
        ->assertJsonPath('data.monthly_prices.0.price', 160000)
        ->assertJsonPath('data.monthly_prices.1.months', 6)
        ->assertJsonPath('data.daily_price', null)
        ->assertJsonPath('data.extra_options.0.id', $created['extra_options'][0]['id'])
        ->assertJsonPath('data.extra_options.0.name', 'Full grooming');

    expect(PriceOption::withTrashed()->find($threeMonths['id'])->trashed())->toBeTrue()
        ->and(PriceOption::withTrashed()->find($daily['id'])->trashed())->toBeTrue();
});

test('update rejects a price option id of another service', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');
    $foreign = $this->postJson($this->url, ($this->body)())->json('data.monthly_prices.0.id');

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'monthly_prices' => [['id' => $foreign, 'months' => 1, 'price' => 1]],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['monthly_prices.0.id']);

    expect(PriceOption::find($foreign)->price)->toBe(150000);
});

test('lists, shows and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();
    expect(PriceOption::count())->toBe(0)
        ->and(Service::withTrashed()->sole()->trashed())->toBeTrue();
});

test('start price ignores extras', function (): void {
    $this->actingAs($this->user, 'center_user');
    $this->postJson($this->url, ($this->body)([
        'daily_price' => null,
        'extra_options' => [['name' => 'Cheap extra', 'price' => 100]],
    ]))->assertCreated();

    $stabling = Stabling::sole();

    expect(Service::query()->withStartPrice()->sole()->start_price)->toBe(150000)
        ->and($stabling->start_price)->toBe(150000);
});

test('cannot access a stabling service of another center', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($other, [CenterUserPermission::ManageStablingServices]);

    $this->getJson("/api/v1/centers/{$other->id}/stablings/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/stablings/{$id}")->assertNotFound();
});
