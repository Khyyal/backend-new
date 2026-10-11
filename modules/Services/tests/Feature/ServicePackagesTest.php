<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\PriceOption;
use Modules\Services\Models\ServicePackage;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\SystemUser;
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

    $base = [
        'name' => ['ar' => 'اسم', 'en' => 'Name'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'price_options' => [['duration' => 30, 'price' => 100], ['duration' => 60, 'price' => 180]],
    ];

    $this->bodies = [
        'recreation-ridings' => [...$base, 'days' => [0, 2], 'hours' => ['09:00', '12:00']],
        'training-services' => $base,
    ];
    $this->post = fn (string $uri, array $extra = []) => $this->actingAs($this->user, 'center_user')
        ->postJson("/api/v1/centers/{$this->center->id}/{$uri}", [...$this->bodies[$uri], ...$extra]);
    $this->put = fn (string $uri, int $id, array $extra = []) => $this->actingAs($this->user, 'center_user')
        ->putJson("/api/v1/centers/{$this->center->id}/{$uri}/{$id}", [...$this->bodies[$uri], ...$extra]);
});

$types = ['recreation-ridings', 'training-services'];

it('creates a service with packages on its price options', function (string $uri): void {
    $response = ($this->post)($uri, ['packages' => [
        ['price_option' => 1, 'count' => 8, 'price' => 1200, 'duration' => 3],
    ]])->assertCreated();

    expect($response->json('data.price_options.0.package'))->toBeNull()
        ->and($response->json('data.price_options.1.package'))->toMatchArray(['count' => 8, 'price' => 1200, 'duration' => 3]);

    expect(ServicePackage::count())->toBe(1)
        ->and(ServicePackage::first()->service_id)->toBe($response->json('data.service_id'));
})->with($types);

it('returns a null package when none is defined', function (string $uri): void {
    ($this->post)($uri)->assertCreated()
        ->assertJsonPath('data.price_options.0.package', null);
})->with($types);

it('rejects two packages for the same price option', function (string $uri): void {
    ($this->post)($uri, ['packages' => [
        ['price_option' => 0, 'count' => 4, 'price' => 100, 'duration' => 1],
        ['price_option' => 0, 'count' => 8, 'price' => 150, 'duration' => 2],
    ]])->assertUnprocessable()->assertJsonValidationErrors('packages.0.price_option');

    expect(ServicePackage::count())->toBe(0);
})->with($types);

it('rejects invalid package fields and unknown option indexes', function (string $uri): void {
    ($this->post)($uri, ['packages' => [['price_option' => 5, 'count' => 0, 'price' => -1, 'duration' => 0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['packages.0.price_option', 'packages.0.count', 'packages.0.price', 'packages.0.duration']);

    ($this->post)($uri, ['packages' => [['price_option' => 2, 'count' => 4, 'price' => 100, 'duration' => 1]]])
        ->assertUnprocessable()->assertJsonValidationErrors('packages.0.price_option');
})->with($types);

it('keeps an option and its package when the option is sent back with its id', function (string $uri): void {
    $created = ($this->post)($uri, ['packages' => [['price_option' => 0, 'count' => 4, 'price' => 300, 'duration' => 1]]])
        ->assertCreated();
    $id = $created->json('data.id');
    $optionId = $created->json('data.price_options.0.id');
    $packageId = $created->json('data.price_options.0.package.id');

    // Option edited in place (30 -> 45 minutes), package omitted from the request body => untouched.
    $response = ($this->put)($uri, $id, ['price_options' => [['id' => $optionId, 'duration' => 45, 'price' => 120]]])
        ->assertOk();

    expect($response->json('data.price_options.0.id'))->toBe($optionId)
        ->and($response->json('data.price_options.0.duration'))->toBe(45)
        ->and($response->json('data.price_options.0.package.id'))->toBe($packageId);

    // The removed second option is gone.
    expect(PriceOption::count())->toBe(1);
})->with($types);

it('replaces packages when sent on update', function (string $uri): void {
    $created = ($this->post)($uri, ['packages' => [['price_option' => 0, 'count' => 4, 'price' => 300, 'duration' => 1]]])
        ->assertCreated();
    $id = $created->json('data.id');
    $first = $created->json('data.price_options.0.id');
    $second = $created->json('data.price_options.1.id');

    $response = ($this->put)($uri, $id, [
        'price_options' => [
            ['id' => $first, 'duration' => 30, 'price' => 100],
            ['id' => $second, 'duration' => 60, 'price' => 180],
        ],
        'packages' => [['price_option' => 1, 'count' => 12, 'price' => 2000, 'duration' => 6]],
    ])->assertOk();

    expect($response->json('data.price_options.0.package'))->toBeNull()
        ->and($response->json('data.price_options.1.package'))->toMatchArray(['count' => 12, 'duration' => 6]);

    expect(ServicePackage::count())->toBe(1);

    ($this->put)($uri, $id, [
        'price_options' => [['id' => $first, 'duration' => 30, 'price' => 100], ['id' => $second, 'duration' => 60, 'price' => 180]],
        'packages' => [],
    ])->assertOk();

    expect(ServicePackage::count())->toBe(0);
})->with($types);

it('soft deletes a removed package and revives it when defined again', function (string $uri): void {
    $package = ['price_option' => 0, 'count' => 4, 'price' => 300, 'duration' => 1];
    $created = ($this->post)($uri, ['packages' => [$package]])->assertCreated();
    $options = collect($created->json('data.price_options'))
        ->map(fn ($o) => ['id' => $o['id'], 'duration' => $o['duration'], 'price' => $o['price']])->all();

    ($this->put)($uri, $created->json('data.id'), ['price_options' => $options, 'packages' => []])->assertOk();

    expect(ServicePackage::count())->toBe(0)
        ->and(ServicePackage::onlyTrashed()->count())->toBe(1);

    ($this->put)($uri, $created->json('data.id'), ['price_options' => $options, 'packages' => [[...$package, 'count' => 6]]])
        ->assertOk()->assertJsonPath('data.price_options.0.package.count', 6);

    expect(ServicePackage::count())->toBe(1)
        ->and(ServicePackage::onlyTrashed()->count())->toBe(0);
})->with($types);

it('deletes the package when its option is deleted', function (string $uri): void {
    $created = ($this->post)($uri, ['packages' => [['price_option' => 1, 'count' => 4, 'price' => 300, 'duration' => 1]]])
        ->assertCreated();
    $first = $created->json('data.price_options.0.id');

    ($this->put)($uri, $created->json('data.id'), [
        'price_options' => [['id' => $first, 'duration' => 30, 'price' => 100]],
    ])->assertOk();

    expect(ServicePackage::count())->toBe(0)
        ->and(PriceOption::count())->toBe(1);
})->with($types);

it('without ids, options are recreated and old packages are dropped', function (string $uri): void {
    $created = ($this->post)($uri, ['packages' => [['price_option' => 0, 'count' => 4, 'price' => 300, 'duration' => 1]]])
        ->assertCreated();

    ($this->put)($uri, $created->json('data.id'))->assertOk()
        ->assertJsonPath('data.price_options.0.package', null);

    expect(ServicePackage::count())->toBe(0);
})->with($types);

it('rejects a price option id from another service', function (string $uri): void {
    $other = ($this->post)($uri)->assertCreated();
    $mine = ($this->post)($uri)->assertCreated();

    ($this->put)($uri, $mine->json('data.id'), [
        'price_options' => [['id' => $other->json('data.price_options.0.id'), 'duration' => 30, 'price' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors('price_options.0.id');

    expect($other->json('data.price_options'))->toHaveCount(2);
})->with($types);

it('enforces one package per price option in the database', function (): void {
    $optionId = ($this->post)('training-services')->assertCreated()->json('data.price_options.0.id');
    ServicePackage::factory()->create(['price_option_id' => $optionId]);

    expect(fn () => ServicePackage::factory()->create(['price_option_id' => $optionId]))
        ->toThrow(QueryException::class);
});

it('lets a system user manage packages through the admin routes', function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $admin = SystemUser::factory()->superAdmin()->create();

    $this->actingAs($admin, 'system_user')
        ->postJson("/api/v1/admin/centers/{$this->center->id}/training-services", [
            ...$this->bodies['training-services'],
            'packages' => [['price_option' => 0, 'count' => 4, 'price' => 300, 'duration' => 1]],
        ])->assertCreated()->assertJsonPath('data.price_options.0.package.count', 4);
});
