<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\TagFactory;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\SystemPermission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->base = '/api/v1/admin/tags';
    $this->payload = ['name' => ['en' => 'Spa', 'ar' => 'سبا']];
});

test('guests are unauthenticated and users without permission are forbidden', function (): void {
    $this->getJson($this->base)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, $this->payload)->assertForbidden();
});

test('a permission only opens its own ability', function (): void {
    $viewer = SystemUserFactory::new()->withPermissions([SystemPermission::ViewAnyTags])->create();

    $this->actingAs($viewer, 'system_user')->getJson($this->base)->assertOk();
    $this->postJson($this->base, $this->payload)->assertForbidden();
});

test('admin creates, shows, lists, updates and deletes a tag', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $id = $this->postJson($this->base, $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'سبا')
        ->json('data.id');

    $this->postJson($this->base, ['name' => ['en' => 'Only english']])->assertUnprocessable();
    $this->getJson("{$this->base}/{$id}")->assertOk()->assertJsonPath('data.centers_count', 0);
    $this->getJson("{$this->base}?search=sp")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->base}?search=zzz")->assertOk()->assertJsonCount(0, 'data');

    $this->patchJson("{$this->base}/{$id}", ['name' => ['en' => 'Spa & Care', 'ar' => 'سبا']])
        ->assertOk()->assertJsonPath('data.name.en', 'Spa & Care');

    $this->deleteJson("{$this->base}/{$id}")->assertNoContent();
    $this->getJson("{$this->base}/{$id}")->assertNotFound();
});

test('deleting a tag detaches it from centers', function (): void {
    $tag = TagFactory::new()->create();
    $center = CenterFactory::new()->create();
    $center->tags()->attach($tag->id);

    $this->actingAs($this->admin, 'system_user');
    $this->getJson("{$this->base}/{$tag->id}")->assertJsonPath('data.centers_count', 1);

    $this->deleteJson("{$this->base}/{$tag->id}")->assertNoContent();
    expect($center->tags()->count())->toBe(0);
});

test('admin sets and replaces the tags of a center on update', function (): void {
    $tags = TagFactory::new()->count(2)->create();
    $center = CenterFactory::new()->create();

    $this->actingAs($this->admin, 'system_user');

    $this->patchJson("/api/v1/admin/centers/{$center->id}", ['tags' => $tags->pluck('id')->all()])
        ->assertOk()->assertJsonCount(2, 'data.tags');
    $this->patchJson("/api/v1/admin/centers/{$center->id}", ['tags' => [$tags[0]->id]])
        ->assertOk()->assertJsonCount(1, 'data.tags');
    $this->patchJson("/api/v1/admin/centers/{$center->id}", ['tags' => [99999]])->assertUnprocessable();
});
