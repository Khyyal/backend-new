<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\TagFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Support\Database\Factories\CityFactory;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');

    $this->center = CenterFactory::new()->create(['city_id' => null, 'description' => null, 'address' => null, 'lat' => null, 'lng' => null]);
    $this->primary = UserFactory::new()->create();
    $this->member = UserFactory::new()->create();

    foreach ([[$this->primary, true], [$this->member, false]] as [$user, $isPrimary]) {
        $this->center->users()->attach($user->id, [
            'status' => ActivationStatus::ACTIVE->value,
            'joined_at' => now()->toDateString(),
            'is_primary' => $isPrimary,
        ]);
    }

    $this->url = "/api/v1/centers/{$this->center->id}/profile-completion";
});

test('guests cannot read the profile completion', function (): void {
    $this->getJson($this->url)->assertUnauthorized();
});

test('a bare center is incomplete and lists every step to do', function (): void {
    $this->actingAs($this->member, 'center_user')->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.is_complete', false)
        ->assertJsonPath('data.completed_steps', 0)
        ->assertJsonPath('data.total_steps', 10)
        ->assertJsonPath('data.percentage', 0)
        ->assertJsonPath('data.missing', ['city', 'description', 'address', 'location', 'logo', 'cover', 'images', 'tags', 'service', 'email'])
        ->assertJsonPath('data.steps.0.action', ['method' => 'PATCH', 'path' => "centers/{$this->center->id}"])
        ->assertJsonPath('data.steps.3.fields', ['lat', 'lng']);
});

test('steps flip as the profile is filled in until it is complete', function (): void {
    $this->actingAs($this->primary, 'center_user');
    $media = fn (string $name) => app(TemporaryMediaService::class)->createTemporary(UploadedFile::fake()->image($name), $this->primary)->uuid;

    $this->patchJson("/api/v1/centers/{$this->center->id}", [
        'city_id' => CityFactory::new()->create()->id,
        'description' => 'A riding club',
        'address' => 'Riyadh',
        'lat' => 24.7,
        'lng' => 46.6,
    ])->assertOk();

    $this->getJson($this->url)->assertJsonPath('data.missing', ['logo', 'cover', 'images', 'tags', 'service', 'email'])
        ->assertJsonPath('data.percentage', 40);

    $this->patchJson("/api/v1/centers/{$this->center->id}", [
        'logo' => $media('logo.png'),
        'cover' => $media('cover.png'),
        'images' => [$media('a.png')],
        'tags' => [TagFactory::new()->create()->id],
    ])->assertOk();

    ServiceFactory::new()->inactive()->create(['center_id' => $this->center->id]);
    $this->getJson($this->url)->assertJsonPath('data.missing', ['service', 'email']);

    ServiceFactory::new()->create(['center_id' => $this->center->id]);
    $this->center->forceFill(['email' => 'info@club.test', 'email_verified_at' => now()])->save();
    $this->getJson($this->url)
        ->assertJsonPath('data.is_complete', true)
        ->assertJsonPath('data.percentage', 100)
        ->assertJsonPath('data.missing', []);
});

test('a user not assigned to the center is forbidden', function (): void {
    $this->actingAs(UserFactory::new()->create(), 'center_user')->getJson($this->url)->assertForbidden();
});

test('the email step needs a verified email and points to the next action', function (): void {
    $this->actingAs($this->member, 'center_user');
    $this->center->forceFill(['email' => null, 'email_verified_at' => null])->save();
    $emailStep = fn () => collect($this->getJson($this->url)->json('data.steps'))->firstWhere('key', 'email');

    expect($emailStep())->done->toBeFalse()->action->toBe(['method' => 'PATCH', 'path' => "centers/{$this->center->id}"]);

    $this->center->forceFill(['email' => 'info@club.test'])->save();
    expect($emailStep())->done->toBeFalse()->action->toBe(['method' => 'POST', 'path' => "centers/{$this->center->id}/email/send"]);

    $this->center->forceFill(['email_verified_at' => now()])->save();
    expect($emailStep())->done->toBeTrue();
});
