<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Clients\Database\Factories\ClientFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('unauthenticated user cannot upload', function () {
    $this->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('a.png')])
        ->assertUnauthorized();
});

test('authenticated user can upload and receives a stable media id', function () {
    $client = ClientFactory::new()->create();

    $response = $this->actingAs($client, 'client')
        ->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('photo.png')])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'status', 'url', 'filename', 'mime_type', 'size', 'expires_at']])
        ->assertJsonPath('data.status', 'temporary');

    $media = Media::firstWhere('uuid', $response->json('data.id'));
    expect($media)->not->toBeNull();
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
});

test('invalid mime type is rejected even with an allowed extension', function () {
    $client = ClientFactory::new()->create();
    $path = tempnam(sys_get_temp_dir(), 'media');
    file_put_contents($path, '<?php echo 1;');
    $file = new UploadedFile($path, 'fake.png', null, null, true);

    $this->actingAs($client, 'client')
        ->postJson('/api/v1/media', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('oversized file is rejected', function () {
    $client = ClientFactory::new()->create();
    config(['support-media.max_size_kb' => 10]);

    $this->actingAs($client, 'client')
        ->postJson('/api/v1/media', ['file' => UploadedFile::fake()->create('big.pdf', 50, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('user can delete their own temporary media', function () {
    $client = ClientFactory::new()->create();
    $id = $this->actingAs($client, 'client')
        ->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('a.png')])
        ->json('data.id');
    $path = Media::firstWhere('uuid', $id)->getPathRelativeToRoot();

    $this->deleteJson("/api/v1/media/{$id}")->assertNoContent();

    expect(Media::where('uuid', $id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

test('user cannot delete another user\'s media', function () {
    [$owner, $other] = [ClientFactory::new()->create(), ClientFactory::new()->create()];
    $id = $this->actingAs($owner, 'client')
        ->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('a.png')])
        ->json('data.id');

    $this->actingAs($other, 'client')->deleteJson("/api/v1/media/{$id}")->assertForbidden();

    expect(Media::where('uuid', $id)->exists())->toBeTrue();
});
