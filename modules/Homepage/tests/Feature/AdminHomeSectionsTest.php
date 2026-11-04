<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Enums\CenterStatus;
use Modules\Homepage\Database\Factories\HomeSectionFactory;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Models\HomeSection;
use Modules\Promotions\Database\Factories\PromotionFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManageHomepage])->create();
});

test('home section admin endpoints require a system user token', function (): void {
    $this->getJson('/api/v1/admin/home-sections')->assertUnauthorized();
});

test('returns 403 without the homepage permission', function (): void {
    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->postJson('/api/v1/admin/home-sections', ['key' => 'picks', 'type' => 'curated_centers'])
        ->assertForbidden();
});

test('creates a draft section with the default limit', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/home-sections', [
            'key' => 'weekend-picks',
            'type' => 'curated_centers',
            'title' => ['en' => 'Weekend picks', 'ar' => 'اختيارات'],
            'config' => ['radius_km' => 25],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.item_type', 'center')
        ->assertJsonPath('data.config.limit', 10)
        ->assertJsonPath('data.config.radius_km', 25)
        ->assertJsonPath('data.title.en', 'Weekend picks');

    expect(HomeSection::query()->sole()->created_by)->toBe($this->admin->id);
});

test('rejects config keys the section type does not read', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/home-sections', [
            'key' => 'offers',
            'type' => 'curated_promotions',
            'config' => ['radius_km' => 5],
        ])
        ->assertJsonValidationErrors('config');
});

test('the section type cannot change after creation', function (): void {
    $section = HomeSectionFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/home-sections/{$section->id}", ['type' => 'top_centers'])
        ->assertJsonValidationErrors('type');
});

test('update merges config and drops keys sent as null', function (): void {
    $section = HomeSectionFactory::new()->create(['config' => ['limit' => 5, 'radius_km' => 10]]);

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/home-sections/{$section->id}", ['config' => ['radius_km' => null]])
        ->assertOk()
        ->assertJsonPath('data.config', ['limit' => 5]);
});

test('reorders sections and keeps unlisted ones after them', function (): void {
    [$first, $second, $third] = HomeSectionFactory::new()->count(3)->sequence(
        ['sort_order' => 0], ['sort_order' => 1], ['sort_order' => 2],
    )->create();

    $this->actingAs($this->admin, 'system_user')
        ->putJson('/api/v1/admin/home-sections/reorder', ['order' => [$third->id, $first->id]])
        ->assertOk()
        ->assertJsonPath('data.order', [$third->id, $first->id, $second->id]);
});

test('syncs picked items in order and returns their summaries', function (): void {
    $section = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedCenters)->create();
    $visible = CenterFactory::new()->create(['status' => CenterStatus::VISIBLE, 'name' => 'Desert Riders']);
    $hidden = CenterFactory::new()->create(['status' => CenterStatus::INVISIBLE]);

    $this->actingAs($this->admin, 'system_user')
        ->putJson("/api/v1/admin/home-sections/{$section->id}/items", ['items' => [
            ['type' => 'center', 'id' => $hidden->id],
            ['type' => 'center', 'id' => $visible->id, 'ends_at' => now()->addWeek()->toDateTimeString()],
        ]])
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $hidden->id)
        ->assertJsonPath('data.items.0.is_available', false)
        ->assertJsonPath('data.items.1.id', $visible->id)
        ->assertJsonPath('data.items.1.label', 'Desert Riders')
        ->assertJsonPath('data.items.1.is_available', true);

    $this->actingAs($this->admin, 'system_user')
        ->putJson("/api/v1/admin/home-sections/{$section->id}/items", ['items' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

test('rejects invalid item lists', function (Closure $items, string $field): void {
    $section = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedServices)->create();

    $this->actingAs($this->admin, 'system_user')
        ->putJson("/api/v1/admin/home-sections/{$section->id}/items", ['items' => $items()])
        ->assertJsonValidationErrors($field);
})->with([
    'wrong item type' => [fn () => [['type' => 'center', 'id' => CenterFactory::new()->create()->id]], 'items.0.type'],
    'duplicate item' => [function () {
        $id = ServiceFactory::new()->create()->id;

        return [['type' => 'service', 'id' => $id], ['type' => 'service', 'id' => $id]];
    }, 'items.1.id'],
    'missing record' => [fn () => [['type' => 'service', 'id' => 999999]], 'items.0.id'],
    'window ends before it starts' => [fn () => [[
        'type' => 'service',
        'id' => ServiceFactory::new()->create()->id,
        'starts_at' => now()->addDay()->toDateTimeString(),
        'ends_at' => now()->toDateTimeString(),
    ]], 'items.0.ends_at'],
]);

test('sections that fill themselves take no items', function (): void {
    $section = HomeSectionFactory::new()->ofType(HomeSectionType::TopCenters)->create();

    $this->actingAs($this->admin, 'system_user')
        ->putJson("/api/v1/admin/home-sections/{$section->id}/items", ['items' => []])
        ->assertJsonValidationErrors('items');

    $this->actingAs($this->admin, 'system_user')
        ->getJson("/api/v1/admin/home-sections/{$section->id}/item-candidates")
        ->assertJsonValidationErrors('section');
});

test('searches candidates of the section item type by name', function (): void {
    $section = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedPromotions)->create();
    $match = PromotionFactory::new()->active()->create(['name' => ['en' => 'Summer gallop', 'ar' => 'صيف']]);
    PromotionFactory::new()->create(['name' => ['en' => 'Winter', 'ar' => 'شتاء']]);
    CenterFactory::new()->create(['name' => 'Summer center']);

    $this->actingAs($this->admin, 'system_user')
        ->getJson("/api/v1/admin/home-sections/{$section->id}/item-candidates?search=summer")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'promotion')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.label', 'Summer gallop')
        ->assertJsonPath('data.0.is_available', true)
        ->assertJsonPath('meta.total', 1);
});
