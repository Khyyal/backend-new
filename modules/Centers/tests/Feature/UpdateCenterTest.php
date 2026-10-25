<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\TagFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Models\Center;
use Modules\Support\Enums\ActivationStatus;
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
