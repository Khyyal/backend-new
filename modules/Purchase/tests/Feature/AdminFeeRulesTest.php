<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Purchase\Database\Factories\FeeRuleFactory;
use Modules\Purchase\Enums\PurchaseChannel;
use Modules\Purchase\Models\FeeRule;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManageFees])->create();
    $this->center = CenterFactory::new()->create();
});

test('fee rule endpoints require a system user with the fees permission', function (): void {
    $this->getJson('/api/v1/admin/fee-rules')->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->getJson('/api/v1/admin/fee-rules')
        ->assertForbidden();
});

test('creates a center override for a channel', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/fee-rules', [
            'center_id' => $this->center->id,
            'channel' => 'khyyal_app',
            'percentage_bps' => 750,
            'fixed_amount' => 100,
            'min_amount' => 200,
            'max_amount' => 5000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.center_id', $this->center->id)
        ->assertJsonPath('data.channel', 'khyyal_app')
        ->assertJsonPath('data.percentage_bps', 750)
        ->assertJsonPath('data.is_active', true);
});

test('allows one rule per center and channel, the platform fallback included', function (): void {
    FeeRuleFactory::new()->create();
    FeeRuleFactory::new()->forCenter($this->center)->forChannel(PurchaseChannel::KhyyalWeb)->create();

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/fee-rules', ['center_id' => null, 'channel' => null, 'percentage_bps' => 100])
        ->assertJsonValidationErrors('channel');

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/fee-rules', ['center_id' => $this->center->id, 'channel' => 'khyyal_web'])
        ->assertJsonValidationErrors('channel');

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/fee-rules', ['center_id' => $this->center->id, 'channel' => null])
        ->assertCreated();
});

test('rejects invalid amounts', function (array $body, string $field): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/fee-rules', ['center_id' => null, 'channel' => null, ...$body])
        ->assertJsonValidationErrors($field);
})->with([
    'percentage above 100%' => [['percentage_bps' => 10001], 'percentage_bps'],
    'maximum below minimum' => [['min_amount' => 500, 'max_amount' => 100], 'max_amount'],
    'unknown channel' => [['channel' => 'tv_app'], 'channel'],
]);

test('updates a rule without tripping over itself', function (): void {
    $rule = FeeRuleFactory::new()->forChannel(PurchaseChannel::KhyyalApp)->create(['min_amount' => 100]);

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/fee-rules/{$rule->id}", ['percentage_bps' => 300, 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.percentage_bps', 300)
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/fee-rules/{$rule->id}", ['max_amount' => 50])
        ->assertJsonValidationErrors('max_amount');
});

test('filters platform defaults and deletes a rule', function (): void {
    $platform = FeeRuleFactory::new()->create();
    $override = FeeRuleFactory::new()->forCenter($this->center)->create();

    $this->actingAs($this->admin, 'system_user')
        ->getJson('/api/v1/admin/fee-rules?center_id=platform')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $platform->id);

    $this->actingAs($this->admin, 'system_user')
        ->deleteJson("/api/v1/admin/fee-rules/{$override->id}")
        ->assertNoContent();

    expect(FeeRule::query()->count())->toBe(1);
});
