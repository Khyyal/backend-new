<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Models\Promotion;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = SystemUserFactory::new()->withPermissions([
        SystemPermission::ViewAnyPromotions,
        SystemPermission::ViewPromotion,
        SystemPermission::CreatePromotion,
        SystemPermission::UpdatePromotion,
        SystemPermission::DeletePromotion,
        SystemPermission::ActivatePromotion,
    ])->create();

    $this->body = fn (array $overrides = []) => [
        'name' => ['en' => 'National day', 'ar' => 'اليوم الوطني'],
        'scope' => 'item',
        'discount_type' => 'percentage',
        'discount_value' => 1500,
        'starts_at' => now()->toDateTimeString(),
        ...$overrides,
    ];
});

test('promotion admin endpoints require a system user token', function (): void {
    $this->getJson('/api/v1/admin/promotions')->assertUnauthorized();
});

test('returns 403 without the promotion permission', function (): void {
    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->postJson('/api/v1/admin/promotions', ($this->body)())
        ->assertForbidden();
});

test('creates a platform-wide promotion as a draft', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/promotions', ($this->body)(['funded_by' => 'shared', 'center_share_bps' => 3000]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.center_id', null)
        ->assertJsonPath('data.funded_by', 'shared')
        ->assertJsonPath('data.center_share_bps', 3000);

    expect(Promotion::query()->sole()->creator->is($this->admin))->toBeTrue();
});

test('rejects invalid discount combinations', function (array $overrides, string $field): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/promotions', ($this->body)($overrides))
        ->assertJsonValidationErrors($field);
})->with([
    'percentage above 100%' => [['discount_value' => 10001], 'discount_value'],
    'fixed price on the whole purchase' => [['scope' => 'order', 'discount_type' => 'fixed_price'], 'discount_type'],
    'shared funding without a share' => [['funded_by' => 'shared'], 'center_share_bps'],
    'end before start' => [['ends_at' => now()->subDay()->toDateTimeString()], 'ends_at'],
]);

test('filters platform-wide promotions', function (): void {
    $platform = PromotionFactory::new()->create();
    PromotionFactory::new()->forCenter(CenterFactory::new()->create())->create();

    $this->actingAs($this->admin, 'system_user')
        ->getJson('/api/v1/admin/promotions?center_id=platform')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $platform->id);
});

test('moves through its lifecycle and refuses invalid transitions', function (): void {
    $promotion = PromotionFactory::new()->create();
    $url = "/api/v1/admin/promotions/{$promotion->id}";

    $this->actingAs($this->admin, 'system_user')->postJson("{$url}/pause")->assertUnprocessable();
    $this->actingAs($this->admin, 'system_user')->postJson("{$url}/activate")->assertOk()->assertJsonPath('data.is_live', true);
    $this->actingAs($this->admin, 'system_user')->postJson("{$url}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
    $this->actingAs($this->admin, 'system_user')->putJson($url, ['priority' => 3])->assertJsonValidationErrors('status');
});

test('locks the value and targets once a purchase used the promotion', function (): void {
    $promotion = PromotionFactory::new()->active()->create();
    DB::table('purchase_adjustments')->insert([
        'purchase_id' => PurchaseFactory::new()->create()->id,
        'type' => 'promotion', 'promotion_id' => $promotion->id, 'label' => '{}', 'discount_type' => 'percentage',
        'discount_value' => 1000, 'amount' => 100, 'funded_by' => 'platform', 'created_at' => now(),
    ]);
    $url = "/api/v1/admin/promotions/{$promotion->id}";

    $this->actingAs($this->admin, 'system_user')->putJson($url, ['discount_value' => 2000])->assertJsonValidationErrors('discount_value');
    $this->actingAs($this->admin, 'system_user')->putJson($url, ['priority' => 5])->assertOk();
    $this->actingAs($this->admin, 'system_user')->putJson("{$url}/targets", ['targets' => []])->assertJsonValidationErrors('targets');
});

test('replaces targets and checks that they exist', function (): void {
    $promotion = PromotionFactory::new()->create();
    $service = ServiceFactory::new()->create();
    $url = "/api/v1/admin/promotions/{$promotion->id}/targets";

    $this->actingAs($this->admin, 'system_user')
        ->putJson($url, ['targets' => [['type' => 'service', 'id' => 999999]]])
        ->assertJsonValidationErrors('targets.0.id');

    $this->actingAs($this->admin, 'system_user')
        ->putJson($url, ['targets' => [['type' => 'service', 'id' => $service->id], ['type' => 'center', 'id' => $service->center_id, 'mode' => 'exclude']]])
        ->assertOk()
        ->assertJsonCount(2, 'data.targets')
        ->assertJsonFragment(['type' => 'center', 'id' => $service->center_id, 'mode' => 'exclude']);
});

test('replaces the weekly schedule', function (): void {
    $promotion = PromotionFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->putJson("/api/v1/admin/promotions/{$promotion->id}/schedules", ['schedules' => [['day_of_week' => 5, 'start_time' => '16:00', 'end_time' => '20:00']]])
        ->assertOk()
        ->assertJsonPath('data.schedules.0', ['day_of_week' => 5, 'start_time' => '16:00', 'end_time' => '20:00']);
});
