<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Database\Factories\PurchaseItemFactory;
use Modules\Purchase\Models\Purchase;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Models\Reservation;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Database\Factories\TrainerFactory;
use Modules\Services\Enums\ServiceType;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->center = CenterFactory::new()->create();
    $this->ownTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $this->otherTrainer = TrainerFactory::new()->create(['center_id' => $this->center->id]);
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
        'trainer_id' => $this->ownTrainer->id,
    ]);
    $this->url = "/api/v1/centers/{$this->center->id}/purchases";

    $this->training = ServiceFactory::new()->create(['center_id' => $this->center->id, 'type' => ServiceType::Training]);
    $this->visit = ServiceFactory::new()->create(['center_id' => $this->center->id, 'type' => ServiceType::Visit]);

    $this->purchaseFor = function ($service, ?int $trainerId): Purchase {
        $reservation = ReservationFactory::new()->create(['service_id' => $service->id, 'trainer_id' => $trainerId]);
        $purchase = PurchaseFactory::new()->create([
            'merchant_type' => $this->center->getMorphClass(),
            'merchant_id' => $this->center->id,
        ]);
        PurchaseItemFactory::new()->create([
            'purchase_id' => $purchase->id,
            'purchasable_type' => (new Reservation)->getMorphClass(),
            'purchasable_id' => $reservation->id,
        ]);

        return $purchase;
    };

    $this->visibleIds = fn (): array => collect($this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->json('data'))->pluck('id')->sort()->values()->all();
});

test('purchases need a purchases view permission', function (): void {
    $this->actingAs($this->user, 'center_user')->getJson($this->url)->assertForbidden();
});

test('only purchases of viewable service types are listed', function (): void {
    $training = ($this->purchaseFor)($this->training, $this->ownTrainer->id);
    $visit = ($this->purchaseFor)($this->visit, $this->ownTrainer->id);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases]);

    expect(($this->visibleIds)())->toBe([$training->id]);

    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$visit->id}")->assertNotFound();
    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$training->id}")->assertOk();
});

test('trainer rules narrow the listed purchases', function (): void {
    $own = ($this->purchaseFor)($this->training, $this->ownTrainer->id);
    $other = ($this->purchaseFor)($this->training, $this->otherTrainer->id);
    $unassigned = ($this->purchaseFor)($this->training, null);

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases]);
    expect(($this->visibleIds)())->toBe([$own->id]);

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases, CenterUserPermission::ViewOtherTrainersPurchases]);
    expect(($this->visibleIds)())->toBe([$own->id, $other->id]);

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases, CenterUserPermission::ViewUnassignedPurchases]);
    expect(($this->visibleIds)())->toBe([$own->id, $unassigned->id]);
});

test('prices are hidden without the view prices permission', function (): void {
    $purchase = ($this->purchaseFor)($this->training, $this->ownTrainer->id);
    PaymentFactory::new()->cashOnArrival()->create(['purchase_id' => $purchase->id]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.total_amount')
        ->assertJsonMissingPath('data.items.0.unit_price')
        ->assertJsonMissingPath('data.payments')
        ->assertJsonPath('data.payment_method', 'cash_on_arrival')
        ->assertJsonPath('data.payment_status', 'pending');

    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ViewTrainingPurchases, CenterUserPermission::ViewPurchasePrices]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.total_amount', (float) $purchase->total_amount)
        ->assertJsonPath('data.items.0.unit_price', (float) $purchase->items()->first()->unit_price);
});
