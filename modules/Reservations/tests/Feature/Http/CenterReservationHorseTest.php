<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\HorseFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Tests\Support\BookingFixtures;
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
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations, CenterUserPermission::EditReservations]);
    $this->reservation = ReservationFactory::new()->forService(BookingFixtures::riding($this->center))->create();
    $this->url = "/api/v1/centers/{$this->center->id}/reservations/{$this->reservation->id}/horse";
});

test('attaches, replaces and detaches a horse of the center', function (): void {
    $najm = HorseFactory::new()->for($this->center)->create(['name' => 'Najm', 'room' => 3]);
    $barq = HorseFactory::new()->for($this->center)->forClient()->create();

    $this->actingAs($this->user, 'center_user')
        ->putJson($this->url, ['horse_id' => $najm->id])
        ->assertOk()
        ->assertJsonPath('data.horse_id', $najm->id)
        ->assertJsonPath('data.horse', ['id' => $najm->id, 'name' => 'Najm', 'room' => 3])
        ->assertJsonPath('data.status', ReservationStatus::Confirmed->value);

    $this->putJson($this->url, ['horse_id' => $barq->id])->assertOk()->assertJsonPath('data.horse.id', $barq->id);

    $this->putJson($this->url, ['horse_id' => null])
        ->assertOk()
        ->assertJsonPath('data.horse_id', null)
        ->assertJsonPath('data.horse', null);

    expect($this->reservation->fresh()->horse_id)->toBeNull();
});

test('rejects a horse of another center, a deleted horse or a missing horse_id', function (): void {
    $foreign = HorseFactory::new()->create();
    $deleted = HorseFactory::new()->for($this->center)->create();
    $deleted->delete();

    $this->actingAs($this->user, 'center_user');
    $this->putJson($this->url, ['horse_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors(['horse_id']);
    $this->putJson($this->url, ['horse_id' => $deleted->id])->assertUnprocessable()->assertJsonValidationErrors(['horse_id']);
    $this->putJson($this->url, [])->assertUnprocessable()->assertJsonValidationErrors(['horse_id']);
});

test('a reservation of another center is not found', function (): void {
    $other = ReservationFactory::new()->create();
    $horse = HorseFactory::new()->for($this->center)->create();

    $this->actingAs($this->user, 'center_user')
        ->putJson("/api/v1/centers/{$this->center->id}/reservations/{$other->id}/horse", ['horse_id' => $horse->id])
        ->assertNotFound();
});

test('needs reservations.edit', function (): void {
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    $horse = HorseFactory::new()->for($this->center)->create();

    $this->actingAs($this->user, 'center_user')
        ->putJson($this->url, ['horse_id' => $horse->id])
        ->assertForbidden();
});
