<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Promotions\Models\Promotion;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManagePromotions]);
    $this->url = "/api/v1/centers/{$this->center->id}/promotions";

    $this->body = fn (array $overrides = []) => [
        'name' => ['en' => 'Morning rides'],
        'trigger' => 'coupon',
        'scope' => 'item',
        'discount_type' => 'fixed_amount',
        'discount_value' => 2500,
        'starts_at' => now()->toDateTimeString(),
        ...$overrides,
    ];
});

test('guests, users without the permission and system users are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ($this->body)())
        ->assertForbidden();

    $this->actingAs(SystemUser::factory()->create(), 'system_user')
        ->postJson($this->url, ($this->body)())
        ->assertForbidden();
});

test('creates a promotion owned and funded by the center', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)())
        ->assertCreated()
        ->assertJsonPath('data.center_id', $this->center->id)
        ->assertJsonPath('data.funded_by', 'center')
        ->assertJsonPath('data.trigger', 'coupon');
});

test('cannot set ownership or funding', function (string $field, mixed $value): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([$field => $value]))
        ->assertJsonValidationErrors($field);
})->with([
    ['center_id', 1],
    ['funded_by', 'platform'],
    ['center_share_bps', 0],
]);

test('only sees its own promotions', function (): void {
    $own = PromotionFactory::new()->forCenter($this->center)->create();
    $other = PromotionFactory::new()->forCenter(CenterFactory::new()->create())->create();
    PromotionFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);

    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$other->id}")->assertNotFound();
    $this->actingAs($this->user, 'center_user')->deleteJson("{$this->url}/{$other->id}")->assertNotFound();

    expect(Promotion::query()->find($other->id))->not->toBeNull();
});

test('can only target its own services', function (): void {
    $promotion = PromotionFactory::new()->forCenter($this->center)->create();
    $own = ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $foreign = ServiceFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/{$promotion->id}/targets", ['targets' => [['type' => 'service', 'id' => $foreign->id]]])
        ->assertJsonValidationErrors('targets.0.id');

    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/{$promotion->id}/targets", ['targets' => [['type' => 'center', 'id' => $this->center->id]]])
        ->assertJsonValidationErrors('targets.0.type');

    $this->actingAs($this->user, 'center_user')
        ->putJson("{$this->url}/{$promotion->id}/targets", ['targets' => [['type' => 'service', 'id' => $own->id]]])
        ->assertOk()
        ->assertJsonPath('data.targets.0.id', $own->id);
});
