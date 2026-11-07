<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\TagFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Models\Center;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Models\City;
use Modules\Support\Services\Media\TemporaryMediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->center = CenterFactory::new()->create(['name' => 'Old name']);
    $this->primary = UserFactory::new()->create();
    $this->member = UserFactory::new()->create();

    foreach ([[$this->primary, true], [$this->member, false]] as [$user, $isPrimary]) {
        $this->center->users()->attach($user->id, [
            'status' => ActivationStatus::ACTIVE->value,
            'joined_at' => now()->toDateString(),
            'is_primary' => $isPrimary,
        ]);
    }

    $this->url = "/api/v1/centers/{$this->center->id}";
    $this->tempMedia = fn ($owner, string $name = 'a.png') => app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image($name), $owner)->uuid;
    $this->imageUuids = fn () => $this->center->fresh()->media()
        ->where('collection_name', 'images')->orderBy('order_column')->pluck('uuid')->all();
});

test('guests cannot update a center', function (): void {
    $this->patchJson($this->url, ['name' => 'X'])->assertUnauthorized();
});

test('only the primary user can update the center', function (): void {
    $this->actingAs($this->member, 'center_user')
        ->patchJson($this->url, ['name' => 'Hacked'])->assertForbidden();

    $outsider = UserFactory::new()->create();
    $this->actingAs($outsider, 'center_user')
        ->patchJson($this->url, ['name' => 'Hacked'])->assertForbidden();

    expect($this->center->fresh()->name)->toBe('Old name');
});

test('primary user updates attributes and tags', function (): void {
    $tags = TagFactory::new()->count(2)->create();

    $this->actingAs($this->primary, 'center_user')->patchJson($this->url, [
        'name' => 'New name',
        'description' => 'Desc',
        'lat' => 24.7136,
        'lng' => 46.6753,
        'address' => 'Somewhere 1',
        'contact_phone' => '+966500000000',
        'tags' => $tags->pluck('id')->all(),
    ])->assertOk()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonCount(2, 'data.tags');

    $center = $this->center->fresh();
    expect($center->name)->toBe('New name')
        ->and($center->address)->toBe('Somewhere 1')
        ->and($center->tags()->count())->toBe(2);
});

test('partial update leaves other fields untouched', function (): void {
    $address = $this->center->address;

    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['name' => 'Only name'])->assertOk();

    expect($this->center->fresh()->address)->toBe($address);
});

test('validation rejects bad coordinates and unknown tags', function (): void {
    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['lat' => 100, 'tags' => [999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lat', 'lng', 'tags.0']);
});

test('logo and cover are attached from temporary media and replaced', function (): void {
    $logo = ($this->tempMedia)($this->primary);

    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['logo' => $logo, 'cover' => ($this->tempMedia)($this->primary, 'c.png')])
        ->assertOk()
        ->assertJsonPath('data.logo.id', $logo)
        ->assertJsonPath('data.logo.status', 'attached');

    $newLogo = ($this->tempMedia)($this->primary, 'b.png');
    $this->patchJson($this->url, ['logo' => $newLogo])->assertOk();

    expect($this->center->fresh()->getMedia('logo')->pluck('uuid')->all())->toBe([$newLogo]);

    $this->patchJson($this->url, ['logo' => null])->assertOk();
    expect($this->center->fresh()->getMedia('logo'))->toHaveCount(0)
        ->and($this->center->fresh()->getMedia('cover'))->toHaveCount(1);
});

test('another user\'s temporary media cannot be attached and nothing is changed', function (): void {
    $foreign = ($this->tempMedia)($this->member);

    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['name' => 'Changed', 'logo' => $foreign])
        ->assertUnprocessable();

    expect($this->center->fresh()->name)->toBe('Old name')
        ->and(Media::where('uuid', $foreign)->first()->model_type)->not->toBe(Center::class);
});

test('images keep the order sent and are reordered, added and removed', function (): void {
    $this->actingAs($this->primary, 'center_user');
    [$a, $b, $c] = [($this->tempMedia)($this->primary, 'a.png'), ($this->tempMedia)($this->primary, 'b.png'), ($this->tempMedia)($this->primary, 'c.png')];

    $this->patchJson($this->url, ['images' => [$c, $a, $b]])->assertOk()
        ->assertJsonPath('data.images.0.id', $c);
    expect(($this->imageUuids)())->toBe([$c, $a, $b]);

    // reorder only
    $this->patchJson($this->url, ['images' => [$b, $c, $a]])->assertOk();
    expect(($this->imageUuids)())->toBe([$b, $c, $a]);

    // remove a, add d in the middle
    $d = ($this->tempMedia)($this->primary, 'd.png');
    $path = Media::where('uuid', $a)->first()->getPathRelativeToRoot();
    $this->patchJson($this->url, ['images' => [$b, $d, $c]])->assertOk();

    expect(($this->imageUuids)())->toBe([$b, $d, $c])
        ->and(Media::where('uuid', $a)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);

    // empty list clears
    $this->patchJson($this->url, ['images' => []])->assertOk();
    expect(($this->imageUuids)())->toBe([]);
});

test('images reject duplicates and unknown ids', function (): void {
    $id = ($this->tempMedia)($this->primary);

    $this->actingAs($this->primary, 'center_user')
        ->patchJson($this->url, ['images' => [$id, $id]])->assertUnprocessable();

    $this->patchJson($this->url, ['images' => [(string) Str::uuid()]])->assertUnprocessable();
});

test('primary user updates social links', function (): void {
    $this->actingAs($this->primary, 'center_user')->patchJson("{$this->url}/social-links", [
        'social_links' => ['instagram' => 'https://instagram.com/khyyal', 'whatsapp' => '+966500000000'],
    ])->assertOk()->assertJsonPath('data.social_links.instagram', 'https://instagram.com/khyyal');

    expect($this->center->fresh()->social_links)->toBe(['instagram' => 'https://instagram.com/khyyal', 'whatsapp' => '+966500000000']);

    $this->patchJson("{$this->url}/social-links", ['social_links' => null])->assertOk();
    expect($this->center->fresh()->social_links)->toBeNull();
});

test('primary user updates site theme', function (): void {
    $this->actingAs($this->primary, 'center_user')->putJson("{$this->url}/site-theme", [
        'site_theme' => ['primary' => '#0f766e', 'background' => '#fff'],
    ])->assertOk()->assertJsonPath('data.site_theme.primary', '#0f766e');

    expect($this->center->fresh()->site_theme)->toBe(['primary' => '#0f766e', 'background' => '#fff']);
});

test('social links and site theme reject unknown keys and invalid colors', function (): void {
    $this->actingAs($this->primary, 'center_user');

    $this->patchJson("{$this->url}/social-links", ['social_links' => ['myspace' => 'https://myspace.com/x']])
        ->assertJsonValidationErrors('social_links');
    $this->patchJson("{$this->url}/social-links", [])->assertJsonValidationErrors('social_links');
    $this->patchJson("{$this->url}/site-theme", ['site_theme' => ['border' => '#000000']])
        ->assertJsonValidationErrors('site_theme');
    $this->patchJson("{$this->url}/site-theme", ['site_theme' => ['primary' => 'red']])
        ->assertJsonValidationErrors('site_theme.primary');
});

test('only the primary user can change social links and site theme', function (): void {
    $this->actingAs($this->member, 'center_user')
        ->patchJson("{$this->url}/social-links", ['social_links' => ['instagram' => 'x']])->assertForbidden();
    $this->patchJson("{$this->url}/site-theme", ['site_theme' => ['primary' => '#000000']])->assertForbidden();
});

test('social links and site theme are no longer accepted on the main update', function (): void {
    $this->actingAs($this->primary, 'center_user')->patchJson($this->url, [
        'social_links' => ['instagram' => 'https://instagram.com/khyyal'],
        'site_theme' => ['primary' => '#0f766e'],
    ])->assertOk();

    $center = $this->center->fresh();
    expect($center->social_links)->toBeNull()->and($center->site_theme)->toBeNull();
});

test('primary user changes the center city', function (): void {
    $city = City::query()->first() ?? CityFactory::new()->create();

    $this->actingAs($this->primary, 'center_user')->patchJson($this->url, ['city_id' => $city->id])
        ->assertOk()->assertJsonPath('data.city.id', $city->id);

    expect($this->center->fresh()->city_id)->toBe($city->id);

    $this->patchJson($this->url, ['city_id' => 999999])->assertJsonValidationErrors('city_id');
});

test('primary user cannot change domain or custom site toggle', function (): void {
    $this->actingAs($this->primary, 'center_user')->patchJson($this->url, [
        'domain' => 'mine.example.com',
        'custom_site_enabled' => true,
    ])->assertOk();

    $center = $this->center->fresh();
    expect($center->domain)->toBeNull()
        ->and($center->custom_site_enabled)->toBeFalse();
});

test('a center user lists the available tags before any center is selected', function (): void {
    TagFactory::new()->count(3)->create();

    $this->getJson('/api/v1/centers/tags')->assertUnauthorized();

    $this->actingAs($this->primary, 'center_user')
        ->getJson('/api/v1/centers/tags')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name']]]);
});
