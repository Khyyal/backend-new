<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Database\Factories\FeatureFactory;
use Modules\Billing\Database\Factories\LimitFactory;
use Modules\Billing\Database\Factories\PlanFactory;
use Modules\Billing\Models\Plan;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->plan = PlanFactory::new()->monthly()->active()->create();
    $this->feature = FeatureFactory::new()->create();
    $this->limit = LimitFactory::new()->create();
});

function asAdminWith(SystemPermission ...$permissions): void
{
    test()->actingAs(SystemUserFactory::new()->withPermissions($permissions)->create(), 'system_user');
}

test('guests and non system user tokens are unauthenticated', function (): void {
    $this->getJson('/api/admin/plans')->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user');
    $this->getJson('/api/admin/plans')->assertUnauthorized();
});

test('a system user without permissions is forbidden everywhere', function (): void {
    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $planUrl = "/api/admin/plans/{$this->plan->id}";

    $this->getJson('/api/admin/plans')->assertForbidden();
    $this->getJson($planUrl)->assertForbidden();
    $this->postJson('/api/admin/plans', [])->assertForbidden();
    $this->putJson($planUrl, [])->assertForbidden();
    $this->patchJson("{$planUrl}/status", [])->assertForbidden();
    $this->deleteJson($planUrl)->assertForbidden();
    $this->postJson("{$planUrl}/features", [])->assertForbidden();
    $this->deleteJson("{$planUrl}/features/{$this->feature->id}")->assertForbidden();
    $this->postJson("{$planUrl}/limits", [])->assertForbidden();
    $this->deleteJson("{$planUrl}/limits/{$this->limit->id}")->assertForbidden();
    $this->getJson('/api/admin/features')->assertForbidden();
    $this->postJson('/api/admin/features', [])->assertForbidden();
    $this->deleteJson("/api/admin/features/{$this->feature->id}")->assertForbidden();
    $this->getJson('/api/admin/limits')->assertForbidden();
    $this->putJson("/api/admin/limits/{$this->limit->id}", [])->assertForbidden();
});

test('plans.view_any only allows reading', function (): void {
    asAdminWith(SystemPermission::ViewAnyPlans);

    $this->getJson('/api/admin/plans')->assertOk();
    $this->getJson("/api/admin/plans/{$this->plan->id}")->assertOk();
    $this->getJson('/api/admin/features')->assertOk();
    $this->getJson('/api/admin/limits')->assertOk();

    $this->deleteJson("/api/admin/plans/{$this->plan->id}")->assertForbidden();
    $this->patchJson("/api/admin/plans/{$this->plan->id}/status", ['status' => 'in_active'])->assertForbidden();
    $this->deleteJson("/api/admin/features/{$this->feature->id}")->assertForbidden();
});

test('plans.update allows status changes and detaching, but not deleting', function (): void {
    asAdminWith(SystemPermission::UpdatePlan);

    $this->patchJson("/api/admin/plans/{$this->plan->id}/status", ['status' => 'in_active'])->assertOk();
    $this->deleteJson("/api/admin/plans/{$this->plan->id}/features/{$this->feature->id}")->assertNotFound();
    $this->deleteJson("/api/admin/plans/{$this->plan->id}")->assertForbidden();
    $this->getJson('/api/admin/plans')->assertForbidden();
});

test('plans.delete allows deleting a plan', function (): void {
    asAdminWith(SystemPermission::DeletePlan);

    $this->deleteJson("/api/admin/plans/{$this->plan->id}")->assertNoContent();
    expect(Plan::query()->find($this->plan->id))->toBeNull();
});

test('plans.manage_catalog allows managing features and limits only', function (): void {
    asAdminWith(SystemPermission::ManagePlanCatalog);

    $this->deleteJson("/api/admin/features/{$this->feature->id}")->assertNoContent();
    $this->deleteJson("/api/admin/limits/{$this->limit->id}")->assertNoContent();
    $this->deleteJson("/api/admin/plans/{$this->plan->id}")->assertForbidden();
});

test('inactive system users are denied', function (): void {
    $admin = SystemUserFactory::new()->superAdmin()->create();
    $admin->forceFill(['status' => 'inactive'])->save();

    $this->actingAs($admin, 'system_user');
    $this->getJson('/api/admin/plans')->assertForbidden();
});

test('super admin can use every plan route', function (): void {
    $this->actingAs(SystemUserFactory::new()->superAdmin()->create(), 'system_user');

    $this->getJson('/api/admin/plans')->assertOk();
    $this->deleteJson("/api/admin/plans/{$this->plan->id}")->assertNoContent();
});
