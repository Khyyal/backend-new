<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Models\ServiceTypeTerm;
use Modules\Support\Enums\ActivationStatus;
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
    $this->url = "/api/v1/centers/{$this->center->id}/service-types";
});

test('guests and unassigned users are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();
    $this->putJson("{$this->url}/visit/terms", ['terms' => ['ar' => 'شروط']])->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)->assertForbidden();
    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->putJson("{$this->url}/visit/terms", ['terms' => ['ar' => 'شروط']])->assertForbidden();
});

test('index lists all 4 service types with null terms initially', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.type', 'recreation_riding')
        ->assertJsonPath('data.0.terms', null)
        ->assertJsonPath('data.1.type', 'visit')
        ->assertJsonPath('data.2.type', 'event')
        ->assertJsonPath('data.3.type', 'resort');
});

test('sets terms for a type and reflects them in index', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/visit/terms", ['terms' => ['ar' => 'شروط الزيارة', 'en' => 'Visit terms']])
        ->assertCreated()
        ->assertJsonPath('data.type', 'visit')
        ->assertJsonPath('data.terms.ar', 'شروط الزيارة')
        ->assertJsonPath('data.terms.en', 'Visit terms');

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.1.type', 'visit')
        ->assertJsonPath('data.1.terms.ar', 'شروط الزيارة');

    expect(ServiceTypeTerm::count())->toBe(1);
});

test('re-putting the same type replaces terms in place, no duplicate row', function (): void {
    $this->actingAs($this->user, 'center_user');

    $this->putJson("{$this->url}/event/terms", ['terms' => ['ar' => 'قديم']])->assertCreated();
    $this->putJson("{$this->url}/event/terms", ['terms' => ['ar' => 'جديد']])
        ->assertOk()
        ->assertJsonPath('data.terms.ar', 'جديد');

    expect(ServiceTypeTerm::count())->toBe(1);
    expect(ServiceTypeTerm::first()->getTranslation('terms', 'ar'))->toBe('جديد');
});

test('validates the body', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/visit/terms", ['terms' => ['en' => 'only english']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['terms.ar']);

    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/visit/terms", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['terms']);
});

test('invalid service type segment is not found', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/not-a-type/terms", ['terms' => ['ar' => 'شروط']])
        ->assertNotFound();
});

test('terms are isolated per center', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/resort/terms", ['terms' => ['ar' => 'شروط المنتجع الأول']])
        ->assertCreated();

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("/api/v1/centers/{$other->id}/service-types")
        ->assertOk()
        ->assertJsonPath('data.3.type', 'resort')
        ->assertJsonPath('data.3.terms', null);

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.3.terms.ar', 'شروط المنتجع الأول');
});
