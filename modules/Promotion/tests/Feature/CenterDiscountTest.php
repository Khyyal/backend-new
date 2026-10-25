<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Promotion\Database\Factories\CouponFactory;
use Modules\Promotion\Database\Factories\DiscountFactory;
use Modules\Promotion\Models\Discount;
use Modules\Services\Database\Factories\ServiceFactory;
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
    $this->url = "/api/v1/centers/{$this->center->id}/discounts";

    $this->body = fn (array $extra = []) => [
        'name' => ['en' => 'Summer', 'ar' => 'الصيف'],
        'type' => 'percentage',
        'value' => 10,
        'scope' => 'all',
        'application_method' => 'coupon',
        'starts_at' => now()->toDateTimeString(),
        ...$extra,
    ];
});

test('guests, unassigned center users and system users are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();

    $this->actingAs(SystemUser::factory()->create(), 'system_user')
        ->getJson($this->url)->assertForbidden();
});

test('creates a discount owned by the route center', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['center_id' => CenterFactory::new()->create()->id]))
        ->assertCreated()
        ->assertJsonPath('data.center_id', $this->center->id)
        ->assertJsonPath('data.service_ids', []);

    expect(Discount::query()->sole()->center_id)->toBe($this->center->id);
});

test('lists only the center discounts', function (): void {
    $own = DiscountFactory::new()->for($this->center)->create();
    DiscountFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});

test('a discount of another center is not found', function (): void {
    $foreign = DiscountFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$foreign->id}")
        ->assertNotFound();

    $this->actingAs($this->user, 'center_user')
        ->deleteJson("{$this->url}/{$foreign->id}")
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

test('creates coupons under a center discount', function (): void {
    $discount = DiscountFactory::new()->for($this->center)->coupon()->create();

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$discount->id}/coupons", [
            'coupons' => [['code' => ' save10 ', 'starts_at' => now()->toDateTimeString()]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.code', 'SAVE10');
});

test('a coupon of another discount is not found', function (): void {
    $discount = DiscountFactory::new()->for($this->center)->create();
    $otherCoupon = CouponFactory::new()->for(DiscountFactory::new()->for($this->center))->create();

    $this->actingAs($this->user, 'center_user')
        ->deleteJson("{$this->url}/{$discount->id}/coupons/{$otherCoupon->id}")
        ->assertNotFound();
});

test('attaches and detaches services of the center', function (): void {
    $discount = DiscountFactory::new()->for($this->center)->specificItems()->create();
    $services = ServiceFactory::new()->count(2)->create(['center_id' => $this->center->id]);

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$discount->id}/services", ['services' => $services->modelKeys()])
        ->assertOk()
        ->assertJsonPath('data.services_count', 2);

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$discount->id}/services", ['services' => [$services->first()->id]])
        ->assertOk()
        ->assertJsonPath('data.services_count', 2);

    $this->actingAs($this->user, 'center_user')
        ->deleteJson("{$this->url}/{$discount->id}/services/{$services->first()->id}")
        ->assertNoContent();

    $this->actingAs($this->user, 'center_user')
        ->deleteJson("{$this->url}/{$discount->id}/services/{$services->first()->id}")
        ->assertNotFound();

    expect($discount->services()->pluck('services.id')->all())->toBe([$services->last()->id]);
});

test('rejects services of another center and unknown ids', function (): void {
    $discount = DiscountFactory::new()->for($this->center)->specificItems()->create();
    $foreignService = ServiceFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$discount->id}/services", ['services' => [$foreignService->id, 999999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0', 'services.1']);

    expect($discount->services()->count())->toBe(0);
});

test('rejects soft-deleted services', function (): void {
    $discount = DiscountFactory::new()->for($this->center)->specificItems()->create();
    $service = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $service->delete();

    $this->actingAs($this->user, 'center_user')
        ->postJson("{$this->url}/{$discount->id}/services", ['services' => [$service->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['services.0']);
});
