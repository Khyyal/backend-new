<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Promotions\Database\Factories\CouponFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Jobs\GenerateCouponsJob;
use Modules\Promotions\Models\Coupon;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManageCoupons, SystemPermission::ViewPromotion])->create();
    $this->promotion = PromotionFactory::new()->active()->coupon()->create();
    $this->url = "/api/v1/admin/promotions/{$this->promotion->id}/coupons";
});

test('returns 403 without the coupon permission', function (): void {
    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->postJson($this->url, ['code' => 'WELCOME'])
        ->assertForbidden();
});

test('creates a coupon with an upper-cased code', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson($this->url, ['code' => ' welcome10 ', 'usage_limit' => 50])
        ->assertCreated()
        ->assertJsonPath('data.code', 'WELCOME10')
        ->assertJsonPath('data.usage_limit', 50);
});

test('rejects a duplicate code and a promotion that is not coupon-triggered', function (): void {
    CouponFactory::new()->withCode('TAKEN')->create();

    $this->actingAs($this->admin, 'system_user')
        ->postJson($this->url, ['code' => 'taken'])
        ->assertJsonValidationErrors('code');

    $automatic = PromotionFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/promotions/{$automatic->id}/coupons", ['code' => 'FRESH'])
        ->assertJsonValidationErrors(['promotion' => 'This promotion is not triggered by a coupon code.']);
});

test('generates small batches at once and queues large ones', function (): void {
    Queue::fake();

    $this->actingAs($this->admin, 'system_user')
        ->postJson("{$this->url}/generate", ['count' => 25, 'prefix' => 'eid', 'length' => 6])
        ->assertCreated()
        ->assertJsonPath('data.count', 25);

    expect(Coupon::query()->where('code', 'like', 'EID%')->count())->toBe(25);

    $this->actingAs($this->admin, 'system_user')
        ->postJson("{$this->url}/generate", ['count' => 500])
        ->assertStatus(202)
        ->assertJsonPath('data.queued', true);

    Queue::assertPushed(GenerateCouponsJob::class, fn (GenerateCouponsJob $job): bool => $job->count === 500);
});

test('a coupon code cannot be changed once issued', function (): void {
    $coupon = CouponFactory::new()->forPromotion($this->promotion)->create();

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/coupons/{$coupon->id}", ['code' => 'OTHER'])
        ->assertJsonValidationErrors('code');

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/coupons/{$coupon->id}", ['status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');
});

test('an assigned-only coupon can be used only by assigned clients', function (): void {
    $coupon = CouponFactory::new()->forPromotion($this->promotion)->assignedOnly()->create();
    $client = ClientFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->postJson("/api/v1/admin/coupons/{$coupon->id}/assignments", ['client_ids' => [$client->id]])
        ->assertOk()
        ->assertJsonPath('data.assigned_clients_count', 1);

    expect($coupon->assignedClients()->whereKey($client->id)->exists())->toBeTrue();
});
