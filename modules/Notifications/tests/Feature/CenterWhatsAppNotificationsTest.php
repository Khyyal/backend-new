<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Centers\Database\Factories\CenterFactory;
use Modules\Centers\Database\Factories\UserFactory;
use Modules\Centers\Events\CenterCreated;
use Modules\Centers\Models\Center;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Events\PurchaseConfirmed;
use Modules\Purchase\Models\Purchase;
use Modules\Support\Enums\ActivationStatus;
use Modules\Support\WhatsApp\Contracts\WhatsAppGateway;
use Modules\Support\WhatsApp\WhatsAppSendException;
use Modules\Support\WhatsApp\WhatsAppSendResult;
use Modules\Support\WhatsApp\WhatsAppTemplateMessage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-09 10:00:00');
    config([
        'services.voxa.client_id' => 'client',
        'services.voxa.channel_id' => 'channel',
        'notifications.whatsapp.language' => 'ar',
    ]);

    $this->gateway = new class implements WhatsAppGateway
    {
        /** @var list<WhatsAppTemplateMessage> */
        public array $sent = [];

        public ?WhatsAppSendException $failWith = null;

        public function sendTemplate(WhatsAppTemplateMessage $message): WhatsAppSendResult
        {
            if ($this->failWith !== null) {
                throw $this->failWith;
            }

            $this->sent[] = $message;

            return new WhatsAppSendResult('msg-1');
        }

        public function sendText(string $toNumber, string $text): WhatsAppSendResult
        {
            return new WhatsAppSendResult('msg-1');
        }
    };
    $this->app->instance(WhatsAppGateway::class, $this->gateway);

    $this->owner = UserFactory::new()->create(['phone' => '0501112233']);
    $this->center = CenterFactory::new()->create(['name' => 'Phoenix Stable']);
    $this->center->users()->attach($this->owner, ['status' => ActivationStatus::ACTIVE->value, 'joined_at' => now()->toDateString(), 'is_primary' => true]);

    $this->purchase = fn (PurchaseStatus $status = PurchaseStatus::Confirmed, ?Center $merchant = null): Purchase => PurchaseFactory::new()->create([
        'merchant_type' => ($merchant ?? $this->center)->getMorphClass(),
        'merchant_id' => ($merchant ?? $this->center)->getKey(),
        'status' => $status,
    ]);

    $this->sentTemplates = fn (): array => array_map(fn (WhatsAppTemplateMessage $message): string => $message->templateName, $this->gateway->sent);
});

test('a created center gets the welcome template on its owner phone', function (): void {
    event(new CenterCreated($this->center, $this->owner));

    expect($this->gateway->sent)->toHaveCount(1)
        ->and($this->gateway->sent[0])->toEqual(new WhatsAppTemplateMessage('966501112233', 'welcom', 'ar', ['Phoenix Stable']));
});

test('nothing is sent while voxa is not configured', function (): void {
    config(['services.voxa.client_id' => null]);

    event(new CenterCreated($this->center, $this->owner));

    expect($this->gateway->sent)->toBeEmpty();
});

test('a center without a valid owner phone or with a rejected message does not fail', function (): void {
    $this->owner->update(['phone' => '']);
    event(new CenterCreated($this->center, $this->owner));

    $this->owner->update(['phone' => '0501112233']);
    $this->gateway->failWith = new WhatsAppSendException('rejected', 400, '{"error":"template"}');
    event(new CenterCreated($this->center->fresh(), $this->owner));

    expect($this->gateway->sent)->toBeEmpty();
});

test('a fatal gateway error is rethrown so the job retries', function (): void {
    $this->gateway->failWith = new WhatsAppSendException('unauthorized', 401);

    event(new CenterCreated($this->center, $this->owner));
})->throws(WhatsAppSendException::class);

test('the first purchase of a center sends the purchase template', function (): void {
    event(new PurchaseConfirmed(($this->purchase)()));

    expect(($this->sentTemplates)())->toBe(['new_purchase'])
        ->and($this->gateway->sent[0]->parameters)->toBe(['Phoenix Stable']);
});

test('a purchase after 21 quiet days sends, one within them does not', function (): void {
    $this->travelTo('2026-10-18 10:00:00');
    ($this->purchase)(PurchaseStatus::Completed);

    $this->travelTo('2026-11-09 10:00:00');
    event(new PurchaseConfirmed($quietGap = ($this->purchase)()));

    $this->travelTo('2026-11-12 10:00:00');
    event(new PurchaseConfirmed(($this->purchase)()));

    expect(($this->sentTemplates)())->toBe(['new_purchase'])
        ->and($quietGap->exists)->toBeTrue();
});

test('pending and cancelled purchases do not count as recent', function (): void {
    ($this->purchase)(PurchaseStatus::Pending);
    ($this->purchase)(PurchaseStatus::Cancelled);

    event(new PurchaseConfirmed(($this->purchase)()));

    expect(($this->sentTemplates)())->toBe(['new_purchase']);
});

test('purchases from other merchants send nothing', function (): void {
    event(new PurchaseConfirmed(PurchaseFactory::new()->create(['status' => PurchaseStatus::Confirmed])));

    expect($this->gateway->sent)->toBeEmpty();
});
