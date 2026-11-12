<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Enums\CenterUserPermission;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\PurchaseItem;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Database\Factories\ReservationFactory;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Enums\ReservationStatus;
use Modules\Reservations\Notifications\ClientPackageNotification;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Models\ServicePackage;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->user = UserFactory::new()->create();
    $this->center->users()->attach($this->user->id, [
        'status' => ActivationStatus::ACTIVE->value,
        'joined_at' => now()->toDateString(),
        'is_primary' => false,
    ]);
    $this->user->syncCenterPermissions($this->center, [CenterUserPermission::ManageReservations]);
    $this->service = BookingFixtures::riding($this->center);
    $this->url = "/api/v1/centers/{$this->center->id}/packages";

    $this->servicePackage = ServicePackage::query()->create([
        'service_id' => $this->service->id,
        'price_option_id' => $this->service->priceOptions()->first()->id,
        'count' => 4,
        'price' => 350,
        'duration' => 2,
    ]);

    $this->package = fn (array $attributes = [], array $client = []) => ClientPackageFactory::new()
        ->forServicePackage($this->servicePackage)
        ->create([
            'client_id' => ClientFactory::new()->create($client)->id,
            ...$attributes,
        ]);
});

test('guests and users of other centers are rejected', function (): void {
    $this->getJson($this->url)->assertUnauthorized();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson($this->url)
        ->assertForbidden();
});

test('lists only the center packages, never pending ones', function (): void {
    $kept = ($this->package)();
    ($this->package)(['status' => ClientPackageStatus::Pending]);
    ClientPackageFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $kept->id)
        ->assertJsonPath('data.0.client.id', $kept->client_id);
});

test('filters by active and not_active', function (): void {
    $active = ($this->package)();
    $expired = ($this->package)(['status' => ClientPackageStatus::Expired]);
    $exhausted = ($this->package)(['status' => ClientPackageStatus::Exhausted]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?active=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?not_active=1")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $expired->id])
        ->assertJsonFragment(['id' => $exhausted->id]);
});

test('searches by client name or phone number', function (): void {
    $sara = ($this->package)([], ['first_name' => 'Sara', 'last_name' => 'Belhadj', 'phone_number' => '0550111222']);
    ($this->package)([], ['first_name' => 'Omar', 'last_name' => 'Kaci', 'phone_number' => '0661333444']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?q=Sara Bel")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sara->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?q=0550111")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sara->id);
});

test('filters by purchase date range and service', function (): void {
    $old = ($this->package)(['created_at' => '2026-09-01 10:00:00']);
    $recent = ($this->package)(['created_at' => '2026-10-15 10:00:00']);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-01&to=2026-10-20")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recent->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?service_id={$this->service->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?service_id=999999")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}?from=2026-10-20&to=2026-10-01")
        ->assertUnprocessable();
});

test('counts active and not active packages, ignoring pending ones', function (): void {
    ($this->package)();
    ($this->package)();
    ($this->package)(['status' => ClientPackageStatus::Expired]);
    ($this->package)(['status' => ClientPackageStatus::Cancelled]);
    ($this->package)(['status' => ClientPackageStatus::Exhausted]);
    ($this->package)(['status' => ClientPackageStatus::Pending]);
    ClientPackageFactory::new()->create();

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/counts")
        ->assertOk()
        ->assertExactJson(['data' => ['active' => 2, 'not_active' => 3]]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/counts?service_id=999999")
        ->assertOk()
        ->assertExactJson(['data' => ['active' => 0, 'not_active' => 0]]);

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/counts")
        ->assertForbidden();
});

test('shows a package with its client and booked sessions', function (): void {
    $package = ($this->package)();
    ReservationFactory::new()->forService($this->service)
        ->at('2026-10-26', '10:00:00', '11:00:00')
        ->create([
            'client_id' => $package->client_id,
            'client_package_id' => $package->id,
            'price_option_id' => $package->price_option_id,
        ]);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$package->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $package->id)
        ->assertJsonPath('data.client.id', $package->client_id)
        ->assertJsonCount(1, 'data.reservations')
        ->assertJsonPath('data.reservations.0.date', '2026-10-26')
        ->assertJsonPath('data.reservations.0.start_time', '10:00');
});

test('a pending, foreign or forbidden package cannot be shown', function (): void {
    $pending = ($this->package)(['status' => ClientPackageStatus::Pending]);
    $foreign = ClientPackageFactory::new()->create();
    $own = ($this->package)();

    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$pending->id}")->assertNotFound();
    $this->actingAs($this->user, 'center_user')->getJson("{$this->url}/{$foreign->id}")->assertNotFound();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->getJson("{$this->url}/{$own->id}")
        ->assertForbidden();
});

test('center staff mark a package as controlled', function (): void {
    $package = ($this->package)(['knight_name' => 'Thunder']);

    $this->actingAs($this->user, 'center_user')
        ->patchJson("{$this->url}/{$package->id}", ['controlled' => true, 'knight_name' => 'Hacked'])
        ->assertOk()
        ->assertJsonPath('data.controlled', true)
        ->assertJsonPath('data.knight_name', 'Thunder');

    expect($package->fresh()->controlled)->toBeTrue();
});

test('updating a package needs the permission, a paid package of this center and a boolean', function (): void {
    $package = ($this->package)();
    $pending = ($this->package)(['status' => ClientPackageStatus::Pending]);
    $foreign = ClientPackageFactory::new()->create();

    $this->actingAs(UserFactory::new()->create(), 'center_user')
        ->patchJson("{$this->url}/{$package->id}", ['controlled' => true])
        ->assertForbidden();

    $this->actingAs($this->user, 'center_user')
        ->patchJson("{$this->url}/{$pending->id}", ['controlled' => true])
        ->assertNotFound();

    $this->actingAs($this->user, 'center_user')
        ->patchJson("{$this->url}/{$foreign->id}", ['controlled' => true])
        ->assertNotFound();

    $this->actingAs($this->user, 'center_user')
        ->patchJson("{$this->url}/{$package->id}", ['controlled' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('controlled');
});

test('center package resources carry the purchase of the package', function (): void {
    $package = ($this->package)();
    $purchase = PurchaseFactory::new()->create(['buyer_type' => $package->client->getMorphClass(), 'buyer_id' => $package->client_id, 'merchant_type' => $this->center->getMorphClass(), 'merchant_id' => $this->center->id]);
    PurchaseItem::query()->create(['purchase_id' => $purchase->id, 'purchasable_type' => $package->getMorphClass(), 'purchasable_id' => $package->id, 'name' => 'Package', 'quantity' => 1, 'unit_price' => 350, 'subtotal' => 350, 'total_amount' => 350]);

    $this->actingAs($this->user, 'center_user')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.purchase.code', $purchase->code)
        ->assertJsonPath('data.0.purchase.client.id', $package->client_id)
        ->assertJsonPath('data.0.purchase.center.id', $this->center->id);

    $this->actingAs($this->user, 'center_user')
        ->getJson("{$this->url}/{$package->id}")
        ->assertOk()
        ->assertJsonPath('data.purchase.id', $purchase->id);
});

describe('managing a package', function (): void {
    beforeEach(function (): void {
        $this->training = BookingFixtures::service($this->center, $trainingService = TrainingService::query()->create(), ServiceType::Training);
        $option = $this->training->priceOptions()->create(['price' => 200, 'quantity' => 60, 'unit' => PriceOptionUnit::MINUTE]);
        $this->trainingPackage = ServicePackage::query()->create([
            'service_id' => $this->training->id, 'price_option_id' => $option->id, 'count' => 8, 'price' => 1500, 'duration' => 1,
        ]);
        $this->trainer = Trainer::query()->create(['center_id' => $this->center->id, 'name' => 'Coach', 'max_tickets_per_hour' => 2]);
        $trainingService->trainers()->attach($this->trainer);
        $this->trainingPackageFor = fn (array $attributes = []) => ClientPackageFactory::new()
            ->forServicePackage($this->trainingPackage)
            ->create(['client_id' => ClientFactory::new()->create()->id, ...$attributes]);
    });

    test('books a session for the client from their package', function (): void {
        $package = ($this->package)(['remaining_sessions' => 1]);

        $this->actingAs($this->user, 'center_user')
            ->postJson("{$this->url}/{$package->id}/reservations", ['date' => '2026-10-26', 'start_time' => '09:00'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.source', 'center')
            ->assertJsonPath('data.total_amount', 0)
            ->assertJsonPath('data.client_package_id', $package->id);

        expect($package->fresh())->remaining_sessions->toBe(0)->status->toBe(ClientPackageStatus::Exhausted);
    });

    test('booking needs the permission, and a paid package of this center', function (): void {
        $package = ($this->package)();
        $pending = ($this->package)(['status' => ClientPackageStatus::Pending]);
        $body = ['date' => '2026-10-26', 'start_time' => '09:00'];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];

        $this->actingAs(UserFactory::new()->create(), 'center_user')
            ->postJson("{$this->url}/{$package->id}/reservations", $body, $headers)->assertForbidden();
        $this->actingAs($this->user, 'center_user')
            ->postJson("{$this->url}/{$pending->id}/reservations", $body, $headers)->assertNotFound();
    });

    test('adds sessions, reviving an exhausted package but not an expired or cancelled one', function (): void {
        $exhausted = ($this->package)(['status' => ClientPackageStatus::Exhausted, 'remaining_sessions' => 0]);
        $expired = ($this->package)(['status' => ClientPackageStatus::Expired, 'remaining_sessions' => 0]);
        $cancelled = ($this->package)(['status' => ClientPackageStatus::Cancelled]);

        $this->actingAs($this->user, 'center_user')
            ->postJson("{$this->url}/{$exhausted->id}/sessions", ['sessions' => 3])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.remaining_sessions', 3)
            ->assertJsonPath('data.total_sessions', $exhausted->total_sessions + 3);

        $this->actingAs($this->user, 'center_user')
            ->postJson("{$this->url}/{$expired->id}/sessions", ['sessions' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');

        $this->actingAs($this->user, 'center_user')
            ->postJson("{$this->url}/{$cancelled->id}/sessions", ['sessions' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'package_not_adjustable');

        foreach ([0, 101, 'x'] as $invalid) {
            $this->actingAs($this->user, 'center_user')
                ->postJson("{$this->url}/{$exhausted->id}/sessions", ['sessions' => $invalid])
                ->assertJsonValidationErrors('sessions');
        }
    });

    test('binds, replaces and frees the trainer of a training package', function (): void {
        $package = ($this->trainingPackageFor)();
        $foreign = Trainer::query()->create(['center_id' => CenterFactory::new()->create()->id, 'name' => 'Away', 'max_tickets_per_hour' => 2]);
        $idle = Trainer::query()->create(['center_id' => $this->center->id, 'name' => 'Idle', 'max_tickets_per_hour' => 2]);
        $url = "{$this->url}/{$package->id}";

        $this->actingAs($this->user, 'center_user')
            ->patchJson($url, ['trainer_id' => $this->trainer->id])->assertOk();
        expect($package->fresh()->trainer_id)->toBe($this->trainer->id);

        foreach ([$foreign->id, $idle->id] as $trainerId) {
            $this->actingAs($this->user, 'center_user')
                ->patchJson($url, ['trainer_id' => $trainerId])
                ->assertUnprocessable()
                ->assertJsonPath('reason', 'invalid_trainer');
        }

        $this->actingAs($this->user, 'center_user')->patchJson($url, ['trainer_id' => null])->assertOk();
        expect($package->fresh()->trainer_id)->toBeNull();
    });

    test('a riding package cannot have a trainer', function (): void {
        $this->actingAs($this->user, 'center_user')
            ->patchJson("{$this->url}/".($this->package)()->id, ['trainer_id' => $this->trainer->id])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'package_not_trainer_bound');
    });

    test('extends the validity and revives an expired package with sessions left', function (): void {
        $package = ($this->package)(['status' => ClientPackageStatus::Expired, 'expires_at' => now()->subDay(), 'expiry_reminder_sent_at' => now()->subDays(3)]);
        $url = "{$this->url}/{$package->id}";

        $this->actingAs($this->user, 'center_user')
            ->patchJson($url, ['expires_at' => now()->addDays(30)->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_usable', true);

        expect($package->fresh()->expiry_reminder_sent_at)->toBeNull();

        $this->actingAs($this->user, 'center_user')
            ->patchJson($url, ['expires_at' => now()->addDays(10)->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'invalid_expiry');

        $this->actingAs($this->user, 'center_user')
            ->patchJson($url, ['expires_at' => now()->subDay()->toIso8601String()])
            ->assertJsonValidationErrors('expires_at');

        $this->actingAs($this->user, 'center_user')->patchJson($url, [])->assertJsonValidationErrors('controlled');
    });
});

describe('cancelling a package', function (): void {
    beforeEach(function (): void {
        $this->cancel = fn ($package, $user = null) => $this->actingAs($user ?? $this->user, 'center_user')
            ->postJson("{$this->url}/{$package->id}/cancel", [], ['Idempotency-Key' => (string) Str::uuid()]);

        $this->purchaseOf = function ($package, array $payment) {
            $purchase = PurchaseFactory::new()->create([
                'buyer_type' => $package->client->getMorphClass(),
                'buyer_id' => $package->client_id,
                'merchant_type' => $this->center->getMorphClass(),
                'merchant_id' => $this->center->id,
                'status' => PurchaseStatus::Confirmed,
            ]);
            PurchaseItem::query()->create(['purchase_id' => $purchase->id, 'purchasable_type' => $package->getMorphClass(), 'purchasable_id' => $package->id, 'name' => 'Package', 'quantity' => 1, 'unit_price' => 350, 'subtotal' => 350, 'total_amount' => 350]);

            return [$purchase, PaymentFactory::new()->create(['purchase_id' => $purchase->id, 'amount' => 350, ...$payment])];
        };
    });

    test('cancels an unpaid package with its upcoming sessions and tells the client', function (): void {
        Notification::fake();
        $package = ($this->package)(['remaining_sessions' => 2]);
        $session = fn (string $date) => ReservationFactory::new()->forService($this->service)->at($date, '09:00:00', '10:00:00')->create([
            'client_id' => $package->client_id,
            'client_package_id' => $package->id,
            'status' => ReservationStatus::Confirmed,
        ]);
        $past = $session('2026-10-19');
        $upcoming = $session('2026-10-26');

        ($this->cancel)($package)
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        expect($package->fresh()->status)->toBe(ClientPackageStatus::Cancelled)
            ->and($upcoming->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($upcoming->fresh()->cancellation_reason)->toBe('package_cancelled')
            ->and($past->fresh()->status)->toBe(ReservationStatus::Confirmed);

        Notification::assertSentTo($package->client, ClientPackageNotification::class, fn (ClientPackageNotification $notification): bool => $notification->type() === 'client_package_cancelled');
    });

    test('cancels the purchase that only sold the package, with its open payment', function (): void {
        $package = ($this->package)();
        [$purchase, $payment] = ($this->purchaseOf)($package, ['method' => 'cash_on_arrival', 'status' => PaymentStatus::Pending]);

        ($this->cancel)($package)->assertOk();

        expect($purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled)
            ->and($payment->fresh()->status)->toBe(PaymentStatus::Cancelled);
    });

    test('a paid package is refused until its payment is fully refunded', function (): void {
        $package = ($this->package)();
        [, $payment] = ($this->purchaseOf)($package, ['method' => 'manual', 'status' => PaymentStatus::Succeeded]);

        ($this->cancel)($package)
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'package_has_payment');
        expect($package->fresh()->status)->toBe(ClientPackageStatus::Active);

        $payment->update(['status' => PaymentStatus::Refunded, 'refunded_amount' => 350]);

        ($this->cancel)($package)->assertOk();
        expect($package->fresh()->status)->toBe(ClientPackageStatus::Cancelled);
    });

    test('a membership package or an already cancelled one is refused', function (): void {
        $included = ($this->package)(['client_membership_id' => ClientMembershipFactory::new()->create()->id]);
        $cancelled = ($this->package)(['status' => ClientPackageStatus::Cancelled]);

        ($this->cancel)($included)->assertUnprocessable()->assertJsonPath('reason', 'package_membership_bound');
        ($this->cancel)($cancelled)->assertUnprocessable()->assertJsonPath('reason', 'package_not_cancellable');
    });

    test('cancelling needs the permission and a paid package of this center', function (): void {
        $package = ($this->package)();
        $pending = ($this->package)(['status' => ClientPackageStatus::Pending]);
        $foreign = ClientPackageFactory::new()->create();

        ($this->cancel)($package, UserFactory::new()->create())->assertForbidden();
        ($this->cancel)($pending)->assertNotFound();
        ($this->cancel)($foreign)->assertNotFound();

        expect($package->fresh()->status)->toBe(ClientPackageStatus::Active);
    });
});
