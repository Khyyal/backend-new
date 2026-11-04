<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Homepage\Database\Factories\BannerFactory;
use Modules\Homepage\Database\Factories\HomeSectionFactory;
use Modules\Homepage\Enums\HomeSectionType;
use Modules\Homepage\Models\Banner;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManageHomepage])->create();
    $this->carousel = HomeSectionFactory::new()->ofType(HomeSectionType::BannerCarousel)->create();
    $this->upload = fn (): string => app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image('banner.png'), $this->admin)->uuid;
});

test('returns 403 without the homepage permission', function (): void {
    $this->actingAs(SystemUserFactory::new()->create(), 'system_user')
        ->getJson('/api/v1/admin/banners')
        ->assertForbidden();
});

test('creates a banner with per-locale images', function (): void {
    $center = CenterFactory::new()->create();

    $response = $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/banners', [
            'home_section_id' => $this->carousel->id,
            'title' => ['en' => 'Ride at dawn'],
            'action_type' => 'center',
            'action_target_id' => $center->id,
            'images' => ['en' => ($this->upload)()],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.action_type', 'center')
        ->assertJsonPath('data.images.ar', null);

    expect($response->json('data.images.en.status'))->toBe('attached')
        ->and(Banner::query()->sole()->getFirstMedia('image_en'))->not->toBeNull();
});

test('removes a locale image when sent as null', function (): void {
    $banner = BannerFactory::new()->create(['home_section_id' => $this->carousel->id]);

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/banners/{$banner->id}", ['images' => ['ar' => ($this->upload)()]])
        ->assertOk();

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/banners/{$banner->id}", ['images' => ['ar' => null]])
        ->assertOk()
        ->assertJsonPath('data.images.ar', null);
});

test('rejects invalid banner actions and sections', function (array $body, string $field): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/banners', ['title' => ['en' => 'Promo'], ...$body])
        ->assertJsonValidationErrors($field);
})->with([
    'url without a url' => [['action_type' => 'url'], 'action_url'],
    'target without an id' => [['action_type' => 'service'], 'action_target_id'],
    'missing target' => [['action_type' => 'campaign', 'action_target_id' => 999999], 'action_target_id'],
]);

test('a banner can only belong to a carousel section', function (): void {
    $section = HomeSectionFactory::new()->ofType(HomeSectionType::CuratedCenters)->create();

    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/banners', ['title' => ['en' => 'Promo'], 'home_section_id' => $section->id])
        ->assertJsonValidationErrors('home_section_id');
});

test('switching an existing banner to a target action requires the target', function (): void {
    $banner = BannerFactory::new()->create();

    $this->actingAs($this->admin, 'system_user')
        ->patchJson("/api/v1/admin/banners/{$banner->id}", ['action_type' => 'promotion'])
        ->assertJsonValidationErrors('action_target_id');
});
