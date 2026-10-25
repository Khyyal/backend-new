<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServiceTypeTerm;
use Modules\Services\Services\ServiceTypeTermService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('listForCenter returns one entry per ServiceType case, unsaved when undefined', function () {
    $center = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $results = $svc->listForCenter($center);

    expect($results)->toHaveCount(count(ServiceType::cases()));
    foreach ($results as $index => $term) {
        expect($term)->toBeInstanceOf(ServiceTypeTerm::class);
        expect($term->exists)->toBeFalse();
        expect($term->type)->toBe(ServiceType::cases()[$index]);
    }
});

test('listForCenter mixes persisted and unsaved instances correctly', function () {
    $center = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $svc->upsert($center, ServiceType::Visit, ['terms' => ['ar' => 'شروط الزيارة']]);

    $results = $svc->listForCenter($center)->keyBy(fn (ServiceTypeTerm $t) => $t->type->value);

    expect($results->get(ServiceType::Visit->value)->exists)->toBeTrue();
    expect($results->get(ServiceType::Visit->value)->getTranslation('terms', 'ar'))->toBe('شروط الزيارة');

    foreach (ServiceType::cases() as $type) {
        if ($type === ServiceType::Visit) {
            continue;
        }
        expect($results->get($type->value)->exists)->toBeFalse();
    }
});

test('listForCenter only returns rows scoped to the given center', function () {
    $centerA = CenterFactory::new()->create();
    $centerB = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $svc->upsert($centerA, ServiceType::Event, ['terms' => ['ar' => 'شروط أ']]);

    $resultsB = $svc->listForCenter($centerB)->keyBy(fn (ServiceTypeTerm $t) => $t->type->value);
    expect($resultsB->get(ServiceType::Event->value)->exists)->toBeFalse();
});

test('upsert creates a row when none exists', function () {
    $center = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $term = $svc->upsert($center, ServiceType::Resort, ['terms' => ['ar' => 'شروط', 'en' => 'Terms']]);

    expect(ServiceTypeTerm::count())->toBe(1);
    expect($term->center_id)->toBe($center->id);
    expect($term->type)->toBe(ServiceType::Resort);
    expect($term->getTranslations('terms'))->toMatchArray(['ar' => 'شروط', 'en' => 'Terms']);
});

test('upsert updates the same row in place, no duplicates', function () {
    $center = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $first = $svc->upsert($center, ServiceType::Resort, ['terms' => ['ar' => 'قديم']]);
    $second = $svc->upsert($center, ServiceType::Resort, ['terms' => ['ar' => 'جديد', 'en' => 'New']]);

    expect(ServiceTypeTerm::count())->toBe(1);
    expect($second->id)->toBe($first->id);
    expect($second->getTranslations('terms'))->toMatchArray(['ar' => 'جديد', 'en' => 'New']);
});

test('upsert: ar-only terms do not include an en key', function () {
    $center = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $term = $svc->upsert($center, ServiceType::Visit, ['terms' => ['ar' => 'عربي فقط']]);

    expect($term->getTranslations('terms'))->toHaveKey('ar', 'عربي فقط');
    expect($term->getTranslations('terms'))->not->toHaveKey('en');
});

test('upsert: same type is independent across different centers', function () {
    $centerA = CenterFactory::new()->create();
    $centerB = CenterFactory::new()->create();
    $svc = app(ServiceTypeTermService::class);

    $svc->upsert($centerA, ServiceType::Visit, ['terms' => ['ar' => 'أ']]);
    $svc->upsert($centerB, ServiceType::Visit, ['terms' => ['ar' => 'ب']]);

    expect(ServiceTypeTerm::count())->toBe(2);
    expect(ServiceTypeTerm::where('center_id', $centerA->id)->first()->getTranslation('terms', 'ar'))->toBe('أ');
    expect(ServiceTypeTerm::where('center_id', $centerB->id)->first()->getTranslation('terms', 'ar'))->toBe('ب');
});
