<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Clients\Database\Factories\ClientFactory;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Enums\PurchaseStatus;
use Modules\Purchase\Models\WebhookEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'purchase.providers.moyasar' => ['enabled' => true, 'publishable_key' => 'pk', 'secret_key' => 'sk', 'webhook_secret' => 'whsec', 'currency' => 'SAR'],
        'purchase.providers.tamara' => ['enabled' => true, 'environment' => 'sandbox', 'api_token' => 'tok', 'notification_token' => 'notif-key', 'country' => 'SA', 'currency' => 'SAR', 'locale' => 'en_US', 'urls' => []],
    ]);

    $this->client = ClientFactory::new()->create();
    $this->purchase = PurchaseFactory::new()->create(['status' => PurchaseStatus::Pending, 'subtotal' => 5000, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 5000, 'buyer_type' => $this->client->getMorphClass(), 'buyer_id' => $this->client->id]);
    $this->makePayment = fn (string $company, array $attributes = []) => PaymentFactory::new()->for($this->purchase, 'purchase')->create([
        'amount' => 5000,
        'method' => PaymentMethod::Online,
        'status' => PaymentStatus::Pending,
        'payment_company' => $company,
        'payment_order_id' => null,
        ...$attributes,
    ]);
    $this->tamaraJwt = function (string $key = 'notif-key', int $exp = 0): string {
        $b64 = fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $b64(json_encode(['iss' => 'Tamara', 'exp' => $exp ?: time() + 600]));

        return "{$head}.{$body}.".$b64(hash_hmac('sha256', "{$head}.{$body}", $key, true));
    };
});

test('moyasar webhook rejects a wrong secret', function (): void {
    $this->postJson('/api/v1/webhooks/moyasar', ['id' => 'e1', 'type' => 'payment_paid', 'secret_token' => 'nope', 'data' => []])
        ->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0);
});

test('moyasar paid webhook finds the payment by given_id, links the provider id and verifies with moyasar', function (): void {
    $payment = ($this->makePayment)('moyasar');
    Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'mo_1', 'status' => 'paid', 'amount' => 5000, 'given_id' => (string) $payment->id])]);

    $body = ['id' => 'evt_1', 'type' => 'payment_paid', 'secret_token' => 'whsec', 'data' => ['id' => 'mo_1', 'given_id' => (string) $payment->id, 'status' => 'paid']];

    $this->postJson('/api/v1/webhooks/moyasar', $body)->assertOk();
    $this->postJson('/api/v1/webhooks/moyasar', $body)->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->payment_order_id)->toBe('mo_1')
        ->and(WebhookEvent::query()->count())->toBe(1);
});

test('a paid webhook is not trusted when moyasar says otherwise', function (): void {
    $payment = ($this->makePayment)('moyasar');
    Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'mo_1', 'status' => 'initiated', 'amount' => 5000, 'given_id' => (string) $payment->id])]);

    $this->postJson('/api/v1/webhooks/moyasar', ['id' => 'evt_2', 'type' => 'payment_paid', 'secret_token' => 'whsec', 'data' => ['id' => 'mo_1', 'given_id' => (string) $payment->id]])
        ->assertOk();

    expect($payment->refresh()->status)->not->toBe(PaymentStatus::Succeeded);
});

test('tamara webhook needs a valid notification token', function (): void {
    $this->postJson('/api/v1/webhooks/tamara', ['order_id' => 'o1', 'event_type' => 'order_approved'])->assertUnauthorized();
    $this->postJson('/api/v1/webhooks/tamara', ['order_id' => 'o1', 'event_type' => 'order_approved'], ['Authorization' => 'Bearer '.($this->tamaraJwt)('wrong-key')])->assertUnauthorized();
    $this->postJson('/api/v1/webhooks/tamara', ['order_id' => 'o1', 'event_type' => 'order_approved'], ['Authorization' => 'Bearer '.($this->tamaraJwt)('notif-key', time() - 10)])->assertUnauthorized();
});

test('tamara approved webhook authorises the order and marks the payment paid', function (): void {
    $payment = ($this->makePayment)('tamara', ['payment_order_id' => 'tam_1']);
    Http::fake(['*/orders/tam_1/authorise' => Http::response(['status' => 'authorised'])]);

    $this->postJson(
        '/api/v1/webhooks/tamara',
        ['order_id' => 'tam_1', 'order_reference_id' => (string) $payment->id, 'event_type' => 'order_approved'],
        ['Authorization' => 'Bearer '.($this->tamaraJwt)()],
    )->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded);
});

test('client verifies a moyasar payment and links the provider payment', function (): void {
    $payment = ($this->makePayment)('moyasar');
    Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'mo_9', 'status' => 'paid', 'amount' => 5000, 'given_id' => (string) $payment->id])]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/payments/{$payment->id}/verify", ['provider_payment_id' => 'mo_9'])
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.purchase_status', 'confirmed');

    expect($payment->refresh()->payment_order_id)->toBe('mo_9');
});

test('a moyasar payment of another checkout cannot be linked', function (): void {
    $payment = ($this->makePayment)('moyasar');
    Http::fake(['api.moyasar.com/*' => Http::response(['id' => 'mo_x', 'status' => 'paid', 'amount' => 5000, 'given_id' => '99999'])]);

    $this->actingAs($this->client, 'client')
        ->postJson("/api/v1/clients/payments/{$payment->id}/verify", ['provider_payment_id' => 'mo_x'])
        ->assertUnprocessable();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)->and($payment->payment_order_id)->toBeNull();
});

test('a client cannot verify someone else\'s payment', function (): void {
    $payment = ($this->makePayment)('moyasar');

    $this->actingAs(ClientFactory::new()->create(), 'client')
        ->postJson("/api/v1/clients/payments/{$payment->id}/verify")
        ->assertNotFound();

    $this->postJson("/api/v1/clients/payments/{$payment->id}/verify")->assertNotFound();
});
