<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Clients\Events\ClientRegistered;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\ClientAuthService;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    $this->url = "/api/v1/centers/{$this->center->id}/clients";
    $this->body = ['phone_number' => '+966500000003', 'first_name' => 'Noura', 'last_name' => 'Al-Qahtani'];
});

test('looks a client of any center up by exact phone', function (): void {
    $client = ClientFactory::new()->create(['phone_number' => '+966500000003', 'first_name' => 'Noura']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/lookup?phone_number=%2B966500000003")
        ->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.first_name', 'Noura')
        ->assertJsonPath('data.phone_number', '+966500000003');

    $this->getJson("{$this->url}/lookup?phone_number=966500000003")->assertNotFound();
    expect($this->center->clients()->exists())->toBeFalse();
});

test('lookup ignores deleted clients and validates the phone', function (): void {
    ClientFactory::new()->create(['phone_number' => '+966500000003'])->delete();

    $this->actingAs($this->user, 'center_user');
    $this->getJson("{$this->url}/lookup?phone_number=%2B966500000003")->assertNotFound();
    $this->getJson("{$this->url}/lookup?phone_number=abc")->assertUnprocessable()->assertJsonValidationErrors(['phone_number']);
    $this->getJson("{$this->url}/lookup")->assertUnprocessable()->assertJsonValidationErrors(['phone_number']);
});

test('lookup and create need reservation access', function (): void {
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageHorses]);

    $this->actingAs($this->user, 'center_user');
    $this->getJson("{$this->url}/lookup?phone_number=%2B966500000003")->assertForbidden();
    $this->postJson($this->url, $this->body)->assertForbidden();
});

test('creates a client, adds it to the center and announces the registration', function (): void {
    Event::fake([ClientRegistered::class]);

    $id = $this->actingAs($this->user, 'center_user')
        ->postJson($this->url, $this->body)
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Noura')
        ->assertJsonPath('data.last_name', 'Al-Qahtani')
        ->assertJsonPath('data.phone_number', '+966500000003')
        ->json('data.id');

    expect($this->center->clients()->pluck('clients.id')->all())->toBe([$id]);
    Event::assertDispatched(ClientRegistered::class, fn (ClientRegistered $event) => $event->client->id === $id);

    $signedIn = app(ClientAuthService::class)->findOrCreateByPhone('+966500000003');
    expect($signedIn->id)->toBe($id)
        ->and($signedIn->needs_onboarding)->toBeFalse()
        ->and(Client::count())->toBe(1);
});

test('rejects a taken phone and missing names', function (): void {
    ClientFactory::new()->create(['phone_number' => '+966500000003', 'first_name' => 'Old']);

    $this->actingAs($this->user, 'center_user');
    $this->postJson($this->url, $this->body)->assertUnprocessable()->assertJsonValidationErrors(['phone_number']);
    $this->postJson($this->url, ['phone_number' => '+966500000009'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name']);

    expect(Client::count())->toBe(1)
        ->and(Client::sole()->first_name)->toBe('Old');
});
