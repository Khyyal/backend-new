<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Support\Database\Factories\TemporaryUploadFactory;
use Modules\Support\Exceptions\InvalidTemporaryMediaException;
use Modules\Support\Jobs\CleanupExpiredMedia;
use Modules\Support\Models\TemporaryUpload;
use Modules\Support\Services\Media\MediaService;
use Modules\Support\Services\Media\TemporaryMediaService;
use Modules\Support\Tests\Support\MediaTestModel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

require_once __DIR__.'/../Support/MediaTestModel.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Schema::create('media_test_models', function ($table) {
        $table->id();
        $table->timestamps();
    });
    $this->service = app(TemporaryMediaService::class);
    $this->owner = ClientFactory::new()->create();
    $this->target = MediaTestModel::create();
});

function tempMedia($owner, string $name = 'a.png'): Media
{
    return app(TemporaryMediaService::class)->createTemporary(UploadedFile::fake()->image($name), $owner);
}

test('temporary media can be promoted and becomes permanent', function () {
    $media = tempMedia($this->owner);
    $path = $media->getPathRelativeToRoot();

    $promoted = $this->service->promote($media->uuid, $this->owner, $this->target, 'gallery');

    expect($promoted->uuid)->toBe($media->uuid)
        ->and($promoted->fresh()->model_id)->toBe($this->target->id)
        ->and($this->service->statusOf($promoted->fresh())->value)->toBe('attached')
        ->and(TemporaryUpload::count())->toBe(0);
    Storage::disk('public')->assertExists($path);
    expect($this->target->getMedia('gallery'))->toHaveCount(1);
});

test('user cannot promote another user\'s temporary media', function () {
    $media = tempMedia(ClientFactory::new()->create());

    expect(fn () => $this->service->promote($media->uuid, $this->owner, $this->target, 'gallery'))
        ->toThrow(InvalidTemporaryMediaException::class);

    expect(TemporaryUpload::count())->toBe(1);
});

test('expired media cannot be promoted', function () {
    $media = tempMedia($this->owner);
    TemporaryUpload::query()->update(['expires_at' => now()->subMinute()]);

    expect(fn () => $this->service->promote($media->uuid, $this->owner, $this->target, 'gallery'))
        ->toThrow(InvalidTemporaryMediaException::class);
});

test('same media cannot be promoted twice', function () {
    $media = tempMedia($this->owner);
    $this->service->promote($media->uuid, $this->owner, $this->target, 'gallery');

    expect(fn () => $this->service->promote($media->uuid, $this->owner, MediaTestModel::create(), 'gallery'))
        ->toThrow(InvalidTemporaryMediaException::class);
});

test('unknown media id is rejected', function () {
    expect(fn () => $this->service->promote('missing', $this->owner, $this->target, 'gallery'))
        ->toThrow(InvalidTemporaryMediaException::class);
});

test('promoteMany is all or nothing', function () {
    $good = tempMedia($this->owner);
    $foreign = tempMedia(ClientFactory::new()->create());

    expect(fn () => $this->service->promoteMany([$good->uuid, $foreign->uuid], $this->owner, $this->target, 'gallery'))
        ->toThrow(InvalidTemporaryMediaException::class);

    expect(TemporaryUpload::count())->toBe(2)
        ->and($this->target->media()->count())->toBe(0);
});

test('promoteMany preserves input order', function () {
    $a = tempMedia($this->owner, 'a.png');
    $b = tempMedia($this->owner, 'b.png');

    $result = $this->service->promoteMany([$b->uuid, $a->uuid], $this->owner, $this->target, 'gallery');

    expect($result->pluck('uuid')->all())->toBe([$b->uuid, $a->uuid]);
});

test('a failing attachment rolls back the promotion claim', function () {
    $png = tempMedia($this->owner);

    // collection only accepts png; a pdf is rejected after the claim happened
    $pdf = $this->service->createTemporary(UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), $this->owner);

    expect(fn () => $this->service->promoteMany([$png->uuid, $pdf->uuid], $this->owner, $this->target, 'images'))
        ->toThrow(InvalidArgumentException::class);

    expect(TemporaryUpload::count())->toBe(2)
        ->and($png->fresh()->model_type)->toBe((new TemporaryUpload)->getMorphClass());
});

test('single file collection replaces previous media after promotion', function () {
    $first = tempMedia($this->owner);
    $second = tempMedia($this->owner);
    $this->service->promote($first->uuid, $this->owner, $this->target, 'single');
    $this->service->promote($second->uuid, $this->owner, $this->target, 'single');

    expect($this->target->fresh()->getMedia('single')->pluck('uuid')->all())->toBe([$second->uuid]);
});

test('owner can discard temporary media but not attached media', function () {
    $media = tempMedia($this->owner);
    $path = $media->getPathRelativeToRoot();
    $this->service->discard($media, $this->owner);

    expect(Media::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);

    $attached = $this->service->promote(tempMedia($this->owner)->uuid, $this->owner, $this->target, 'gallery');
    expect(fn () => $this->service->discard($attached, $this->owner))
        ->toThrow(InvalidTemporaryMediaException::class);
});

test('cleanup removes expired uploads with media and files and keeps live ones', function () {
    $expired = tempMedia($this->owner);
    $expiredPath = $expired->getPathRelativeToRoot();
    $live = tempMedia($this->owner);
    TemporaryUpload::whereKey($expired->model_id)->update(['expires_at' => now()->subMinute()]);

    expect($this->service->cleanupExpired())->toBe(1);

    expect(Media::where('uuid', $expired->uuid)->exists())->toBeFalse()
        ->and(Media::where('uuid', $live->uuid)->exists())->toBeTrue()
        ->and(TemporaryUpload::count())->toBe(1);
    Storage::disk('public')->assertMissing($expiredPath);
    Storage::disk('public')->assertExists($live->getPathRelativeToRoot());
});

test('cleanup is safe to run repeatedly and does not touch promoted media', function () {
    $media = tempMedia($this->owner);
    $this->service->promote($media->uuid, $this->owner, $this->target, 'gallery');
    TemporaryUploadFactory::new()->forUploader($this->owner)->expired()->create();

    (new CleanupExpiredMedia)->handle($this->service);
    (new CleanupExpiredMedia)->handle($this->service);

    expect(TemporaryUpload::count())->toBe(0)
        ->and(Media::where('uuid', $media->uuid)->exists())->toBeTrue();
});

test('cleanup processes more rows than one chunk', function () {
    config(['support-media.cleanup_chunk_size' => 2]);
    TemporaryUploadFactory::new()->forUploader($this->owner)->expired()->count(5)->create();

    expect($this->service->cleanupExpired())->toBe(5);
});

test('detach and delete remove permanent media', function () {
    $media = app(MediaService::class)->upload($this->target, UploadedFile::fake()->image('a.png'), 'gallery');
    $path = $media->getPathRelativeToRoot();

    expect(fn () => app(MediaService::class)->detach(MediaTestModel::create(), $media))
        ->toThrow(InvalidArgumentException::class);

    app(MediaService::class)->detach($this->target, $media);

    expect(Media::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});
