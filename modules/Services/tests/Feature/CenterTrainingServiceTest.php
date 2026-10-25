<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\Service;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->url = "/api/v1/centers/{$this->center->id}/training-services";
    $this->body = fn (array $extra = []) => [
        'name' => ['ar' => 'تدريب', 'en' => 'Training'],
        'description' => ['ar' => 'وصف', 'en' => 'Desc'],
        'price_options' => [['duration' => 30, 'price' => 100], ['duration' => 60, 'price' => 180]],
        ...$extra,
    ];
    $this->tempMedia = fn (string $name = 'a.png') => app(TemporaryMediaService::class)
        ->createTemporary(UploadedFile::fake()->image($name), $this->user)->uuid;
});

test('guests and unassigned users are rejected', function (): void {
    $this->postJson($this->url, ($this->body)())->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->postJson($this->url, ($this->body)())->assertForbidden();
});

test('creates a training service with trainers and media', function (): void {
    $trainers = TrainerFactory::new()->count(2)->create(['center_id' => $this->center->id]);
    $cover = ($this->tempMedia)();
    $images = [($this->tempMedia)('b.png'), ($this->tempMedia)('c.png')];

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)([
            'trainer_ids' => $trainers->pluck('id')->all(),
            'cover' => $cover,
            'images' => $images,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.name.ar', 'تدريب')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(2, 'data.price_options')
        ->assertJsonPath('data.price_options.0.duration', 30)
        ->assertJsonCount(2, 'data.trainers')
        ->assertJsonPath('data.trainers.0.id', $trainers[0]->id)
        ->assertJsonPath('data.trainers.0.name', $trainers[0]->name)
        ->assertJsonPath('data.cover.id', $cover)
        ->assertJsonPath('data.images.0.id', $images[0]);

    expect(TrainingService::count())->toBe(1)
        ->and(Service::first()->type)->toBe(ServiceType::Training);
});

test('validates the body', function (): void {
    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['name' => ['en' => 'x'], 'price_options' => [['duration' => 0, 'price' => -1]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name.ar', 'price_options.0.duration', 'price_options.0.price']);
});

test('rejects trainers of another center or deleted ones', function (): void {
    $foreignTrainer = TrainerFactory::new()->create();
    $deletedTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $deletedTrainer->delete();

    $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, ($this->body)(['trainer_ids' => [$foreignTrainer->id, $deletedTrainer->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trainer_ids.0', 'trainer_ids.1']);
});

test('lists, shows, updates and deletes', function (): void {
    $this->actingAs($this->user, 'center_user');
    [$trainerA, $trainerB] = TrainerFactory::new()->count(2)->create(['center_id' => $this->center->id]);
    $id = $this->postJson($this->url, ($this->body)(['trainer_ids' => [$trainerA->id]]))->json('data.id');

    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("{$this->url}/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    $this->putJson("{$this->url}/{$id}", ($this->body)([
        'name' => ['ar' => 'جديد'],
        'price_options' => [['duration' => 45, 'price' => 90]],
    ]))->assertOk()
        ->assertJsonPath('data.name.ar', 'جديد')
        ->assertJsonCount(1, 'data.price_options')
        ->assertJsonPath('data.trainers.0.id', $trainerA->id);

    $this->putJson("{$this->url}/{$id}", ($this->body)(['trainer_ids' => [$trainerB->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'data.trainers')
        ->assertJsonPath('data.trainers.0.id', $trainerB->id);

    $this->deleteJson("{$this->url}/{$id}")->assertNoContent();
    $this->getJson("{$this->url}/{$id}")->assertNotFound();

    expect(Trainer::count())->toBe(2)
        ->and($trainerB->fresh()->trainingServices)->toHaveCount(0);
});

test('update syncs cover and images, keeping them when omitted', function (): void {
    $this->actingAs($this->user, 'center_user');
    $cover = ($this->tempMedia)();
    $a = ($this->tempMedia)('a.png');
    $b = ($this->tempMedia)('b.png');
    $id = $this->postJson($this->url, ($this->body)(['cover' => $cover, 'images' => [$a, $b]]))->json('data.id');

    $this->putJson("{$this->url}/{$id}", ($this->body)())
        ->assertOk()->assertJsonPath('data.cover.id', $cover)->assertJsonCount(2, 'data.images');

    $this->putJson("{$this->url}/{$id}", ($this->body)(['cover' => null, 'images' => [$b]]))
        ->assertOk()->assertJsonPath('data.cover', null)
        ->assertJsonCount(1, 'data.images')->assertJsonPath('data.images.0.id', $b);
});

test('cannot access a training service of another center', function (): void {
    $this->actingAs($this->user, 'center_user');
    $id = $this->postJson($this->url, ($this->body)())->json('data.id');

    $other = CenterFactory::new()->create();
    $other->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);

    $this->getJson("/api/v1/centers/{$other->id}/training-services/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/centers/{$other->id}/training-services/{$id}")->assertNotFound();
});
