<?php

use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Http\Controllers\Admin\PlanController;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Support\Database\Factories\CityFactory;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    CityFactory::new()->create();
});

function callSlugResolve(string $baseNameEn, array $overrides = []): string
{
    $ctl = app(PlanController::class);
    $ref = new ReflectionClass($ctl);
    $m = $ref->getMethod('dedupeSlug');
    $m->setAccessible(true);

    return $m->invoke($ctl, $baseNameEn, ...[]);
}

function callStoreResolve(array $validated): string
{
    $ctl = app(PlanController::class);
    $ref = new ReflectionClass($ctl);
    $m = $ref->getMethod('resolveSlug');
    $m->setAccessible(true);

    return $m->invoke($ctl, $validated);
}

test('two plans with identical English names resolve to slug with -1 suffix on second', function (): void {
    PlanFactory::new()->create([
        'name' => ['en' => 'My Plan', 'ar' => 'خطتي'],
        'slug' => 'my-plan',
    ]);

    $slug2 = callStoreResolve([
        'name' => ['en' => 'My Plan', 'ar' => 'خطتي'],
    ]);

    expect($slug2)->toBe('my-plan-1');
});

test('dedupe handles three collisions with incrementing suffix', function (): void {
    PlanFactory::new()->create(['slug' => 'alpha']);
    PlanFactory::new()->create(['slug' => 'alpha-1']);
    PlanFactory::new()->create(['slug' => 'alpha-2']);

    $next = callSlugResolve('alpha');
    expect($next)->toBe('alpha-3');
});

test('when explicit slug provided and unique it is preserved', function (): void {
    $slug = callStoreResolve([
        'name' => ['en' => 'Some Plan'],
        'slug' => 'custom-slug-999',
    ]);
    expect($slug)->toBe('custom-slug-999');
});

test('when explicit slug collides automatic dedupe suffix applied', function (): void {
    PlanFactory::new()->create(['slug' => 'existent']);
    $slug = callStoreResolve([
        'name' => ['en' => 'Plan Collide'],
        'slug' => 'existent',
    ]);
    expect($slug)->toBe('existent-1');
});
