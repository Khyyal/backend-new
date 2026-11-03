<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PaymentFailed;
use Modules\Purchase\Events\PaymentRefunded;
use Modules\Purchase\Events\PurchaseCancelled;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Notifications\PurchaseNotification;
use Modules\Support\Enums\ActivationStatus;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $this->client = ClientFactory::new()->create();
    $this->purchase = PurchaseFactory::new()->create([
        'buyer_type' => $this->client->getMorphClass(),
        'buyer_id' => $this->client->id,
        'status' => PurchaseStatus::Confirmed,
        'total_amount' => 150,
    ]);

    $this->assertSent = function (object $notifiable, string $type): void {
        Notification::assertSentTo($notifiable, PurchaseNotification::class, fn (PurchaseNotification $notification): bool => $notification->type() === $type);
    };
});

test('purchase status changes notify the client buyer', function (string $eventClass, string $type): void {
    event(new $eventClass($this->purchase));

    ($this->assertSent)($this->client, $type);
})->with([
    [PurchaseConfirmed::class, 'purchase_confirmed'],
    [PurchaseCancelled::class, 'purchase_cancelled'],
]);

test('payment failures and refunds notify the buyer with the amount', function (): void {
    $payment = PaymentFactory::new()->create(['purchase_id' => $this->purchase->id, 'amount' => 15000]);

    event(new PaymentFailed($payment));
    event(new PaymentRefunded($payment, 4000));

    ($this->assertSent)($this->client, 'purchase_payment_failed');
    Notification::assertSentTo(
        $this->client,
        PurchaseNotification::class,
        fn (PurchaseNotification $notification): bool => $notification->type() === 'purchase_payment_refunded'
            && str_contains($notification->body('en'), '40.00 SAR'),
    );
});

test('a center buyer is notified through its active staff', function (): void {
    $center = CenterFactory::new()->create();
    $staff = UserFactory::new()->create();
    $center->users()->attach($staff, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString()]);
    $purchase = PurchaseFactory::new()->create([
        'buyer_type' => $center->getMorphClass(),
        'buyer_id' => $center->id,
    ]);

    event(new PurchaseConfirmed($purchase));

    ($this->assertSent)($staff, 'purchase_confirmed');
});
