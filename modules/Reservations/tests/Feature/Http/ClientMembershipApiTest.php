<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Models\Payment;
use Modules\Purchase\Models\PurchaseItem;
use Modules\Purchase\Services\PaymentStateService;
use Modules\Purchase\Services\PurchaseStateService;
use Modules\Reservations\Database\Factories\ClientMembershipFactory;
use Modules\Reservations\Database\Factories\ClientPackageFactory;
use Modules\Reservations\Enums\ClientMembershipStatus;
use Modules\Reservations\Enums\ClientPackageStatus;
use Modules\Reservations\Models\ClientMembership;
use Modules\Reservations\Models\ClientPackage;
use Modules\Reservations\Models\Reservation;
use Modules\Reservations\Tests\Support\BookingFixtures;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Membership;
use Modules\Services\Models\ServicePackage;
use Modules\Services\Models\Trainer;
use Modules\Services\Models\TrainingService;
use Modules\Services\Models\Visit;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 08:00:00');

    $this->center = CenterFactory::new()->create();
    $this->client = ClientFactory::new()->create();

    $this->riding = BookingFixtures::riding($this->center);
    $this->ridingPackage = ServicePackage::query()->create([
        'service_id' => $this->riding->id,
        'price_option_id' => $this->riding->priceOptions()->first()->id,
        'count' => 4,
        'price' => 350,
        'duration' => 2,
    ]);

    $training = TrainingService::query()->create();
    $this->training = BookingFixtures::service($this->center, $training, ServiceType::Training);
    $trainingOption = $this->training->priceOptions()->create(['price' => 200, 'quantity' => 60, 'unit' => PriceOptionUnit::MINUTE]);
    $this->trainingPackage = ServicePackage::query()->create([
        'service_id' => $this->training->id,
        'price_option_id' => $trainingOption->id,
        'count' => 8,
        'price' => 1500,
        'duration' => 1,
    ]);
    $this->trainer = Trainer::query()->create(['center_id' => $this->center->id, 'name' => 'Coach', 'max_tickets_per_hour' => 2]);
    $training->trainers()->attach($this->trainer);

    $visit = Visit::query()->create(['enter_type' => VisitEnterType::AllDay, 'max_tickets_per_day' => 10]);
    $this->visit = BookingFixtures::service($this->center, $visit, ServiceType::Visit);
    $this->visit->priceOptions()->create(['name' => null, 'price' => 25]);
    BookingFixtures::schedule($visit, 1, null, null);

    $this->membership = Membership::query()->create([
        'visit_service_id' => $this->visit->id,
        'max_visits_per_day' => 2,
        'duration' => 6,
        'color' => '#112233',
    ]);
    $service = BookingFixtures::service($this->center, $this->membership, ServiceType::Membership);
    $service->priceOptions()->create(['name' => null, 'price' => 1000]);
    $this->membership->packages()->createMany([
        ['service_package_id' => $this->ridingPackage->id, 'price_option_id' => $this->ridingPackage->price_option_id],
        ['service_package_id' => $this->trainingPackage->id, 'price_option_id' => $this->trainingPackage->price_option_id, 'trainer_id' => $this->trainer->id],
    ]);
});

test('buying a membership sells all its packages in one purchase item', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/memberships', ['membership_id' => $this->membership->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.price', 1000)
        ->assertJsonPath('data.max_visits_per_day', 2)
        ->assertJsonCount(2, 'data.packages')
        ->assertJsonPath('data.purchase.total_amount', 1000)
        ->assertJsonPath('data.purchase.center.id', $this->center->id)
        ->assertJsonPath('checkout.purchase.total_amount', 1000);

    $clientMembership = ClientMembership::sole();

    expect(PurchaseItem::sole()->purchasable->is($clientMembership))->toBeTrue()
        ->and($clientMembership->packages)->toHaveCount(2)
        ->and($clientMembership->packages->pluck('status')->unique()->all())->toBe([ClientPackageStatus::Pending])
        ->and($clientMembership->packages->pluck('price')->all())->toBe([0, 0])
        ->and($clientMembership->packages->firstWhere('service_package_id', $this->trainingPackage->id)->trainer_id)->toBe($this->trainer->id);
});

test('paying activates the membership and each package with its own validity', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/memberships', ['membership_id' => $this->membership->id])
        ->assertCreated();

    app(PaymentStateService::class)->markSucceeded(Payment::query()->sole());

    $clientMembership = ClientMembership::sole();
    $riding = ClientPackage::query()->where('service_package_id', $this->ridingPackage->id)->sole();
    $training = ClientPackage::query()->where('service_package_id', $this->trainingPackage->id)->sole();

    expect($clientMembership->status)->toBe(ClientMembershipStatus::Active)
        ->and($clientMembership->expires_at->toDateTimeString())->toBe('2027-04-20 08:00:00')
        ->and($riding->status)->toBe(ClientPackageStatus::Active)
        ->and($riding->expires_at->toDateTimeString())->toBe('2026-12-20 08:00:00')
        ->and($training->expires_at->toDateTimeString())->toBe('2026-11-20 08:00:00');

    $this->actingAs($this->client, 'client')
        ->getJson('/api/v1/clients/memberships')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.visits_left_today', 2)
        ->assertJsonPath('data.0.service.color', '#112233');
});

test('a cancelled purchase cancels the membership and its packages', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/memberships', ['membership_id' => $this->membership->id])
        ->assertCreated();

    app(PurchaseStateService::class)->cancel(PurchaseItem::sole()->purchase);

    expect(ClientMembership::sole()->status)->toBe(ClientMembershipStatus::Cancelled)
        ->and(ClientPackage::query()->pluck('status')->unique()->all())->toBe([ClientPackageStatus::Cancelled]);
});

test('a membership with a deleted package cannot be bought', function (): void {
    $this->ridingPackage->delete();

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/memberships', ['membership_id' => $this->membership->id])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'membership_package_unavailable');

    expect(ClientMembership::count())->toBe(0);
});

test('a membership with an inactive package cannot be bought', function (): void {
    $this->ridingPackage->update(['status' => ActivationStatus::INACTIVE]);

    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/memberships', ['membership_id' => $this->membership->id])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'membership_package_unavailable');
});

test('quotes a membership', function (): void {
    $this->actingAs($this->client, 'client')
        ->postJson('/api/v1/clients/memberships/quote', ['membership_id' => $this->membership->id])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 1000);
});

test('a trainer-bound package is booked only with its trainer', function (): void {
    $otherTrainer = Trainer::query()->create(['center_id' => $this->center->id, 'name' => 'Other', 'max_tickets_per_hour' => 2]);
    $package = ClientPackageFactory::new()
        ->forServicePackage($this->trainingPackage)
        ->create(['client_id' => $this->client->id, 'trainer_id' => $this->trainer->id]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/packages/{$package->id}/reservations", [
            'date' => '2026-10-26', 'start_time' => '09:00', 'trainer_id' => $otherTrainer->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'trainer_not_allowed');
});

describe('free visits', function (): void {
    beforeEach(function (): void {
        $this->clientMembership = ClientMembershipFactory::new()
            ->withVisits($this->visit, 2)
            ->create([
                'client_id' => $this->client->id,
                'service_id' => $this->membership->service->id,
                'membership_id' => $this->membership->id,
            ]);
        $this->url = "/api/v1/clients/memberships/{$this->clientMembership->id}/visits";
    });

    test('books a free confirmed visit', function (): void {
        $this->actingAs($this->client, 'client')
            ->postJson($this->url, ['date' => '2026-10-26', 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.service.id', $this->visit->id)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.total_amount', 0)
            ->assertJsonPath('data.client_membership_id', $this->clientMembership->id);
    });

    test('limits the free visits per day', function (): void {
        $this->actingAs($this->client, 'client');

        $this->postJson($this->url, ['date' => '2026-10-26'])->assertCreated();
        $this->postJson($this->url, ['date' => '2026-10-26', 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'membership_daily_visits_exceeded');
        $this->postJson($this->url, ['date' => '2026-10-26'])->assertCreated();

        expect(Reservation::query()->where('client_membership_id', $this->clientMembership->id)->sum('quantity'))->toBe(2);
    });

    test('still respects the visit capacity and schedule', function (): void {
        $this->visit->serviceable->update(['max_tickets_per_day' => 1]);

        $this->actingAs($this->client, 'client');

        $this->postJson($this->url, ['date' => '2026-10-26', 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'capacity_exceeded');
        $this->postJson($this->url, ['date' => '2026-10-27'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'outside_schedule');
    });

    test('refuses an expired or pending membership', function (): void {
        $this->clientMembership->update(['expires_at' => now()->subDay()]);

        $this->actingAs($this->client, 'client')
            ->postJson($this->url, ['date' => '2026-10-26'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'membership_not_usable');

        $this->clientMembership->update(['expires_at' => now()->addMonth(), 'status' => ClientMembershipStatus::Pending]);

        $this->actingAs($this->client, 'client')
            ->postJson($this->url, ['date' => '2026-10-26'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'membership_not_usable');
    });

    test('refuses a membership without visits', function (): void {
        $this->clientMembership->update(['visit_service_id' => null, 'max_visits_per_day' => null]);

        $this->actingAs($this->client, 'client')
            ->postJson($this->url, ['date' => '2026-10-26'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'membership_has_no_visits');
    });

    test('another client\'s membership is not found', function (): void {
        $this->actingAs(ClientFactory::new()->create(), 'client')
            ->postJson($this->url, ['date' => '2026-10-26'])
            ->assertNotFound();
    });
});
