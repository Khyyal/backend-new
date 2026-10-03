<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Gateways\Tamara\TamaraClient;
use Modules\Purchase\Gateways\Tamara\TamaraGateway;
use Modules\Purchase\Gateways\Tamara\TamaraWebhookEventHandler;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\WebhookEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function tamaraConfig(): array
{
    return [
        'enabled' => true,
        'environment' => 'sandbox',
        'api_token' => 'tamara-test-token-xyz',
        'notification_token' => 'tamara-notif-secret',
        'country' => 'SA',
        'currency' => 'SAR',
        'locale' => 'en_US',
        'urls' => [
            'success' => 'https://example.com/ok',
            'failure' => 'https://example.com/fail',
            'cancel' => 'https://example.com/cancel',
            'notification' => 'https://example.com/webhook/tamara',
        ],
        'default_payment_type' => 'PAY_BY_INSTALMENTS',
    ];
}

test('T11: TamaraManager driver() resolves TamaraGateway', function (): void {
    config()->set('purchase.providers.tamara', tamaraConfig());

    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->driver('tamara');
    expect($driver)->toBeInstanceOf(TamaraGateway::class);
});

test('T11: Tamara initialize returns required fields and sets Payment provider metadata', function (): void {
    $purchase = PurchaseFactory::new()->create(['total_amount' => 300.00]);
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->create([
        'method' => PaymentMethod::Online,
        'amount' => 300.00,
        'status' => PaymentStatus::Pending,
    ]);

    $paymentId = (string) $payment->getKey();

    Http::fake([
        '*/checkout' => function (Request $req) use ($paymentId) {
            $body = (array) $req->data();
            expect($body['order_reference_id'])->toBe($paymentId);
            expect($body['currency'])->toBe('SAR');
            expect($body['country'])->toBe('SA');
            expect($body['payment_type'])->toBe('PAY_BY_INSTALMENTS');
            expect($body['total_amount']['amount'])->toBe(300.0);
            expect($body['merchant_url']['success'])->toBe('https://example.com/ok');

            // Auth header check
            expect($req->hasHeader('Authorization'))->toBeTrue();
            $authHeader = $req->header('Authorization');
            $auth = is_array($authHeader) ? ($authHeader[0] ?? '') : (string) $authHeader;
            expect(str_starts_with($auth, 'Bearer '))->toBeTrue();

            return Http::response([
                'order_id' => 'tamara-ord-999',
                'checkout_id' => 'tamara-chk-999',
                'checkout_url' => 'https://checkout.tamara.co/pay/tamara-ord-999',
                'status' => 'new',
            ], 201);
        },
    ]);

    $client = new TamaraClient(tamaraConfig());
    $gw = new TamaraGateway($client);
    $result = $gw->initialize($purchase, $payment);

    expect($result)->toHaveKey('checkout_url');
    expect($result['provider_reference'])->toBe('tamara-ord-999');
    expect($result['checkout_id'])->toBe('tamara-chk-999');
    expect($result['tamara_status'])->toBe('new');

    // Simulate caller persistence: verify we can assign the Tamara identity to Payment fields
    $payment->payment_company = 'tamara';
    $payment->payment_type = 'pay_by_instalments';
    $payment->payment_order_id = $result['provider_reference'];
    $providerData = (array) $payment->provider_data;
    $providerData['tamara_status'] = $result['tamara_status'];
    $providerData['tamara_checkout_id'] = $result['checkout_id'];
    $payment->provider_data = $providerData;
    $payment->save();
    $payment->refresh();

    expect($payment->payment_company)->toBe('tamara');
    expect($payment->payment_type)->toBe('pay_by_instalments');
    expect($payment->payment_order_id)->toBe('tamara-ord-999');
    expect($payment->provider_data['tamara_status'])->toBe('new');

    Http::assertSentCount(1);
});

test('T11: Tamara initialize payload contains NO PAN/CVC fields (no card data through backend)', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->create();

    $sentBody = null;
    Http::fake([
        '*/checkout' => function (Request $req) use (&$sentBody) {
            $sentBody = json_encode($req->data());

            return Http::response([
                'order_id' => 'ord-1',
                'checkout_id' => 'chk-1',
                'checkout_url' => 'https://x',
                'status' => 'new',
            ], 201);
        },
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    $gw->initialize($purchase, $payment);

    expect($sentBody)->toBeString();
    $lower = strtolower($sentBody);
    $banned = ['"pan"', '"cvc"', '"cvv"', 'cardnumber', '4111111111111111'];
    foreach ($banned as $needle) {
        expect(str_contains($lower, $needle))->toBeFalse("Sent body must NOT contain card-data marker: $needle");
    }
});

test('T11: Tamara sync (authorized) → Succeeded; raw tamara_status + order body saved in provider_data', function (): void {
    $payment = PaymentFactory::new()->create([
        'payment_order_id' => 'tamara-ord-sync-1',
        'payment_company' => 'tamara',
        'provider_data' => ['tamara_status' => 'new'],
    ]);

    Http::fake([
        '*/orders/tamara-ord-sync-1' => Http::response([
            'order_id' => 'tamara-ord-sync-1',
            'status' => 'authorised',
            'total_amount' => ['amount' => 99.99, 'currency' => 'SAR'],
            'payments' => [],
        ], 200),
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    $got = $gw->sync($payment);
    expect($got)->toBe(PaymentStatus::Succeeded);

    $payment->refresh();
    expect($payment->provider_data['tamara_status'])->toBe('authorised');
    expect($payment->provider_data['tamara_order']['order_id'])->toBe('tamara-ord-sync-1');
});

test('T11: Tamara verify wraps sync (declined → Failed)', function (): void {
    $payment = PaymentFactory::new()->create([
        'payment_order_id' => 'tamara-ord-dec',
        'payment_company' => 'tamara',
    ]);

    Http::fake([
        '*/orders/tamara-ord-dec' => Http::response(['order_id' => 'tamara-ord-dec', 'status' => 'declined'], 200),
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    expect($gw->verify($payment))->toBe(PaymentStatus::Failed);
});

test('T11: Tamara authorize endpoint (British spelling) maps to Succeeded', function (): void {
    $payment = PaymentFactory::new()->create([
        'payment_order_id' => 'tamara-ord-authz',
        'payment_company' => 'tamara',
    ]);

    $path = null;
    Http::fake([
        '*' => function (Request $req) use (&$path) {
            $path = parse_url($req->url(), PHP_URL_PATH);

            return Http::response(['order_id' => 'tamara-ord-authz', 'status' => 'authorised'], 200);
        },
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    $got = $gw->authorize($payment);
    expect($got)->toBe(PaymentStatus::Succeeded);
    expect($path !== null && str_ends_with($path, '/orders/tamara-ord-authz/authorise'))->toBeTrue();
});

test('T11: Tamara capture (fully_captured) → Succeeded; capture response saved', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 500.00,
        'payment_order_id' => 'tamara-ord-cap',
        'payment_company' => 'tamara',
    ]);

    Http::fake([
        '*/payments/capture' => Http::response([
            'order_id' => 'tamara-ord-cap',
            'status' => 'fully_captured',
            'capture_id' => 'cap-1',
        ], 200),
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    $got = $gw->capture($payment);
    expect($got)->toBe(PaymentStatus::Succeeded);

    $payment->refresh();
    expect($payment->provider_data['tamara_status'])->toBe('fully_captured');
    expect($payment->provider_data['tamara_capture_response']['capture_id'])->toBe('cap-1');
});

test('T11: Tamara cancel → Cancelled; provider_data updated', function (): void {
    $payment = PaymentFactory::new()->create([
        'payment_order_id' => 'tamara-ord-can',
        'payment_company' => 'tamara',
    ]);

    Http::fake([
        '*/orders/tamara-ord-can/cancel' => Http::response([
            'order_id' => 'tamara-ord-can',
            'status' => 'canceled',
        ], 200),
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    $got = $gw->cancel($payment);
    expect($got)->toBe(PaymentStatus::Cancelled);
    expect($payment->provider_data['tamara_status'])->toBe('canceled');
});

test('T11: Tamara refund returns true; sets tamara_refunded=true in provider_data (no auto status change)', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 250.00,
        'status' => PaymentStatus::Succeeded,
        'payment_order_id' => 'tamara-ord-ref',
        'payment_company' => 'tamara',
    ]);

    Http::fake([
        '*/orders/tamara-ord-ref/refunds' => Http::response([
            'refund_id' => 'ref-1',
            'order_id' => 'tamara-ord-ref',
        ], 200),
    ]);

    $gw = new TamaraGateway(new TamaraClient(tamaraConfig()));
    $ret = $gw->refund($payment, 250.00);
    expect($ret)->toBeTrue();

    $payment->refresh();
    expect($payment->provider_data['tamara_refunded'])->toBeTrue();
    expect((float) $payment->provider_data['tamara_refund_amount'])->toEqualWithDelta(250.0, 0.001);
    expect($payment->provider_data['tamara_status'])->toBe('refunded');
});

// ----- Webhook event handler matrix ------------------------------------------

test('T11: TamaraWebhookEventHandler maps order_approved → Processing; order_declined → Failed', function (): void {
    $handler = new TamaraWebhookEventHandler();

    $evApproved = new WebhookEvent();
    $evApproved->event_type = 'order_approved';
    $evApproved->payload = ['order_id' => 'ord-w-1'];
    $a1 = $handler->handle($evApproved);
    expect($a1)->not->toBeNull();
    expect($a1['payment_order_id'])->toBe('ord-w-1');
    expect($a1['payment_status'])->toBe(PaymentStatus::Processing);

    $evDeclined = new WebhookEvent();
    $evDeclined->event_type = 'order_declined';
    $evDeclined->payload = ['order_id' => 'ord-w-1'];
    $a2 = $handler->handle($evDeclined);
    expect($a2)->not->toBeNull();
    expect($a2['payment_status'])->toBe(PaymentStatus::Failed);
});

test('T11: TamaraWebhookEventHandler maps order_authorised→Succeeded; order_captured→Succeeded; order_expired→Expired', function (): void {
    $handler = new TamaraWebhookEventHandler();

    $cases = [
        ['order_authorised', PaymentStatus::Succeeded],
        ['order_captured', PaymentStatus::Succeeded],
        ['order_expired', PaymentStatus::Expired],
        ['order_canceled', PaymentStatus::Cancelled],
    ];

    foreach ($cases as [$type, $expected]) {
        $ev = new WebhookEvent();
        $ev->event_type = $type;
        $ev->payload = ['order_id' => 'ord-'.$type];
        $a = $handler->handle($ev);
        expect($a)->not->toBeNull("Handler for $type should return action");
        expect($a['payment_status'])->toBe($expected);
    }
});

test('T11: TamaraWebhookEventHandler order_refunded returns null (no state change; informational)', function (): void {
    $handler = new TamaraWebhookEventHandler();
    $ev = new WebhookEvent();
    $ev->event_type = 'order_refunded';
    $ev->payload = ['order_id' => 'ord-refx', 'refund_id' => 'r-1'];

    expect($handler->handle($ev))->toBeNull();
});
