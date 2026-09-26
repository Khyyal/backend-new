<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentMethod;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Exceptions\PaymentVerificationException;
use Modules\Purchase\Gateways\Moyasar\MoyasarClient;
use Modules\Purchase\Gateways\Moyasar\MoyasarGateway;
use Modules\Purchase\Gateways\Moyasar\MoyasarWebhookEventHandler;
use Modules\Purchase\Managers\PaymentGatewayManager;
use Modules\Purchase\Models\WebhookEvent;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function moyasarConfig(): array
{
    return [
        'enabled' => true,
        'publishable_key' => 'pk_test_mo_abc',
        'secret_key' => 'sk_test_mo_secret',
        'webhook_secret' => 'whsec_moyy',
        'currency' => 'SAR',
    ];
}

test('T12: MoyasarManager driver() resolves MoyasarGateway', function (): void {
    config()->set('purchase.providers.moyasar', moyasarConfig());

    $manager = app(PaymentGatewayManager::class);
    $driver = $manager->driver('moyasar');
    expect($driver)->toBeInstanceOf(MoyasarGateway::class);
});

test('T12: Moyasar initialize returns frontend config; NO backend HTTP call; never leaks secret key', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->create([
        'amount' => 149.99,
    ]);

    $client = new MoyasarClient(moyasarConfig());
    $gw = new MoyasarGateway($client);

    $result = $gw->initialize($purchase, $payment);

    expect($result['type'])->toBe('moyasar');
    expect($result['publishable_key'])->toBe('pk_test_mo_abc');
    expect($result['amount_halalas'])->toBe(14999);
    expect($result['currency'])->toBe('SAR');
    expect($result['given_id'])->toBe((string) $payment->getKey());

    // Initialize MUST NOT contain the secret key
    expect(implode(',', $result))->not->toContain('sk_test_mo_secret');

    // And NO HTTP call was made (frontend-only config)
    Http::assertSentCount(0);
});

test('T12: Moyasar given_id is stable across repeated initialize calls', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->create();
    $id = (string) $payment->getKey();

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $r1 = $gw->initialize($purchase, $payment);
    $r2 = $gw->initialize($purchase, $payment);
    $r3 = $gw->initialize($purchase, $payment);

    expect($r1['given_id'])->toBe($id);
    expect($r2['given_id'])->toBe($id);
    expect($r3['given_id'])->toBe($id);
});

test('T12: Moyasar sync status=paid returns Succeeded; source.type creditcard→card normalized with company', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 100.00,
        'payment_order_id' => 'pay-moy-1',
        'payment_company' => 'moyasar',
        'payment_type' => 'online',
    ]);

    Http::fake([
        '*/v1/payments/pay-moy-1' => Http::response([
            'id' => 'pay-moy-1',
            'status' => 'paid',
            'amount' => 10000,
            'currency' => 'SAR',
            'source' => [
                'type' => 'creditcard',
                'company' => 'mada',
                'name' => 'Test',
                'scheme' => 'mada',
            ],
            'transaction_url' => 'https://moyasar.example/tx/pay-moy-1',
        ], 200),
    ]);

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $got = $gw->sync($payment);
    expect($got)->toBe(PaymentStatus::Succeeded);

    $payment->refresh();
    expect($payment->provider_data['moyasar_status'])->toBe('paid');
    expect($payment->provider_data['source_type'])->toBe('creditcard');
    expect($payment->provider_data['source_company'])->toBe('mada');
    expect($payment->provider_data['payment_type_normalized'])->toBe('card');
    expect($payment->provider_data['provider_amount_halalas'])->toBe(10000);
    expect($payment->provider_data['transaction_url'])->toBe('https://moyasar.example/tx/pay-moy-1');
    expect($payment->payment_type)->toBe('card');
});

test('T12: Moyasar sync source normalization - Apple Pay / Samsung Pay / STC Pay', function (): void {
    $cases = [
        ['Apple Pay', 'apple_pay', 'visa'],
        ['Samsung Pay', 'samsung_pay', 'mastercard'],
        ['STC Pay', 'stc_pay', 'stcpay'],
    ];

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));

    foreach ($cases as [$rawType, $expectedNorm, $company]) {
        $payment = PaymentFactory::new()->create([
            'amount' => 50.00,
            'payment_order_id' => 'pay-moy-src-'.md5($rawType),
            'payment_company' => 'moyasar',
            'payment_type' => 'online',
        ]);
        $pid = $payment->payment_order_id;

        Http::fake([
            "*/v1/payments/$pid" => Http::response([
                'id' => $pid,
                'status' => 'paid',
                'amount' => 5000,
                'source' => ['type' => $rawType, 'company' => $company],
            ], 200),
        ]);

        $gw->sync($payment);
        $payment->refresh();

        expect($payment->provider_data['source_type'])->toBe($rawType);
        expect($payment->provider_data['source_company'])->toBe($company);
        expect($payment->provider_data['payment_type_normalized'])->toBe($expectedNorm);
        expect($payment->payment_type)->toBe($expectedNorm);
    }
});

test('T12: Moyasar verify with halalas match → Succeeded; mismatch → throws PaymentVerificationException', function (): void {
    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));

    // --- Match case ---
    $paymentOk = PaymentFactory::new()->create([
        'amount' => 75.50,
        'payment_order_id' => 'pay-match',
        'payment_company' => 'moyasar',
    ]);

    Http::fake([
        '*/v1/payments/pay-match' => Http::response([
            'id' => 'pay-match',
            'status' => 'paid',
            'amount' => 7550, // 75.50 * 100 → matches
        ], 200),
    ]);

    $status = $gw->verify($paymentOk);
    expect($status)->toBe(PaymentStatus::Succeeded);

    // --- Mismatch case ---
    $paymentBad = PaymentFactory::new()->create([
        'amount' => 75.50,
        'payment_order_id' => 'pay-mismatch',
        'payment_company' => 'moyasar',
    ]);

    Http::fake([
        '*/v1/payments/pay-mismatch' => Http::response([
            'id' => 'pay-mismatch',
            'status' => 'paid',
            'amount' => 9999, // mismatch
        ], 200),
    ]);

    try {
        $gw->verify($paymentBad);
        throw new \PHPUnit\Framework\ExpectationFailedException('Should have thrown VerificationException');
    } catch (PaymentVerificationException $e) {
        expect($e->getMessage())->toContain('Amount mismatch');
    }
});

test('T12: Moyasar authorize calls backend createPayment with manual=true; HTTP Basic auth sent', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 200.00,
        'payment_type' => 'card',
        'payment_order_id' => null, // Force empty so gateway assigns from backend response
    ]);

    $pid = (string) $payment->getKey();
    $captured = [];

    Http::fake([
        '*/v1/payments' => function (Request $req) use ($pid, &$captured) {
            $captured['body'] = (array) $req->data();
            $captured['auth'] = $req->header('Authorization');

            return Http::response([
                'id' => 'pay-moy-auth',
                'status' => 'authorized',
                'amount' => 20000,
                'source' => ['type' => 'creditcard', 'company' => 'visa'],
            ], 201);
        },
    ]);

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $got = $gw->authorize($payment);
    expect($got)->toBe(PaymentStatus::Processing);

    // Payload: given_id = stable Payment.id, manual=true
    expect($captured['body']['given_id'])->toBe($pid);
    expect($captured['body']['manual'])->toBeTrue();
    expect($captured['body']['amount'])->toBe(20000);

    // Basic auth: username = secret key, password empty → base64 of "sk_test_mo_secret:"
    $authHeader = $captured['auth'] ?? null;
    $authStr = is_array($authHeader) ? ($authHeader[0] ?? '') : (string) $authHeader;
    expect(str_starts_with($authStr, 'Basic '))->toBeTrue();
    $b64 = substr($authStr, 6);
    $decoded = base64_decode($b64, true);
    expect($decoded !== false)->toBeTrue();
    // Format "username:password"; password is empty
    $parts = explode(':', $decoded, 2);
    expect($parts[0])->toBe('sk_test_mo_secret');
    expect($parts[1] ?? '')->toBe('');

    // payment_order_id is set from backend response
    $payment->refresh();
    expect($payment->payment_order_id)->toBe('pay-moy-auth');
    expect($payment->provider_data['moyasar_status'])->toBe('authorized');
});

test('T12: Moyasar capture captures payment with halalas → Succeeded', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 300.00,
        'payment_order_id' => 'pay-cap-1',
        'payment_company' => 'moyasar',
    ]);

    $reqBody = null;
    Http::fake([
        '*/v1/payments/pay-cap-1/capture' => function (Request $req) use (&$reqBody) {
            $reqBody = (array) $req->data();

            return Http::response([
                'id' => 'pay-cap-1',
                'status' => 'captured',
            ], 200);
        },
    ]);

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $got = $gw->capture($payment);
    expect($got)->toBe(PaymentStatus::Succeeded);

    expect($reqBody['amount'])->toBe(30000);
    expect($reqBody['currency'])->toBe('SAR');

    $payment->refresh();
    expect($payment->provider_data['moyasar_status'])->toBe('captured');
});

test('T12: Moyasar cancel = void; voided → Cancelled', function (): void {
    $payment = PaymentFactory::new()->create([
        'payment_order_id' => 'pay-void-1',
        'payment_company' => 'moyasar',
    ]);

    Http::fake([
        '*/v1/payments/pay-void-1/void' => Http::response([
            'id' => 'pay-void-1',
            'status' => 'voided',
        ], 200),
    ]);

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $got = $gw->cancel($payment);
    expect($got)->toBe(PaymentStatus::Cancelled);
    expect($payment->provider_data['moyasar_status'])->toBe('voided');
});

test('T12: Moyasar refund = true; sets moyasar_refunded, refund_amount_halalas; status refunded', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 400.00,
        'status' => PaymentStatus::Succeeded,
        'payment_order_id' => 'pay-ref-1',
        'payment_company' => 'moyasar',
    ]);

    Http::fake([
        '*/v1/payments/pay-ref-1/refund' => Http::response([
            'id' => 'pay-ref-1',
            'status' => 'refunded',
            'refund_id' => 'r-1',
        ], 200),
    ]);

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $ret = $gw->refund($payment, 150.25);
    expect($ret)->toBeTrue();

    $payment->refresh();
    expect($payment->provider_data['moyasar_refunded'])->toBeTrue();
    expect((int) $payment->provider_data['moyasar_refund_amount_halalas'])->toBe(15025);
    expect((float) $payment->provider_data['moyasar_refund_amount'])->toEqualWithDelta(150.25, 0.001);
    expect($payment->provider_data['moyasar_status'])->toBe('refunded');
});

// ----- Webhook event handler matrix -----------------------------------------

test('T12: MoyasarWebhookEventHandler payment_paid→Succeeded; payment_faild→Failed (typo matched exactly)', function (): void {
    $handler = new MoyasarWebhookEventHandler();

    $evPaid = new WebhookEvent();
    $evPaid->event_type = 'payment_paid';
    $evPaid->payload = ['id' => 'pay-wh-paid'];
    $a = $handler->handle($evPaid);
    expect($a)->not->toBeNull();
    expect($a['payment_order_id'])->toBe('pay-wh-paid');
    expect($a['payment_status'])->toBe(PaymentStatus::Succeeded);

    // IMPORTANT: Moyasar emits the typo "payment_faild" (without an 'e'). We MUST match this exactly.
    $evFail = new WebhookEvent();
    $evFail->event_type = 'payment_faild';
    $evFail->payload = ['id' => 'pay-wh-fail'];
    $b = $handler->handle($evFail);
    expect($b)->not->toBeNull();
    expect($b['payment_status'])->toBe(PaymentStatus::Failed);

    // And the CORRECT spelling "payment_failed" is NOT matched → no action
    $evWrongSpelling = new WebhookEvent();
    $evWrongSpelling->event_type = 'payment_failed';
    $evWrongSpelling->payload = ['id' => 'pay-wh-wrong'];
    expect($handler->handle($evWrongSpelling))->toBeNull();
});

test('T12: MoyasarWebhookEventHandler payment_authorized→Processing; payment_captured→Succeeded; payment_voided→Cancelled', function (): void {
    $handler = new MoyasarWebhookEventHandler();
    $cases = [
        ['payment_authorized', PaymentStatus::Processing],
        ['payment_captured', PaymentStatus::Succeeded],
        ['payment_voided', PaymentStatus::Cancelled],
    ];

    foreach ($cases as [$type, $expected]) {
        $ev = new WebhookEvent();
        $ev->event_type = $type;
        $ev->payload = ['id' => 'pay-'.$type];
        $a = $handler->handle($ev);
        expect($a)->not->toBeNull("Handler for $type should return action");
        expect($a['payment_status'])->toBe($expected);
        expect($a['payment_order_id'])->toBe('pay-'.$type);
    }
});

test('T12: MoyasarWebhookEventHandler payment_refunded & payment_verified return null (no state change)', function (): void {
    $handler = new MoyasarWebhookEventHandler();

    $evR = new WebhookEvent();
    $evR->event_type = 'payment_refunded';
    $evR->payload = ['id' => 'pay-wh-r'];
    expect($handler->handle($evR))->toBeNull();

    $evV = new WebhookEvent();
    $evV->event_type = 'payment_verified';
    $evV->payload = ['id' => 'pay-wh-v'];
    expect($handler->handle($evV))->toBeNull();
});

test('T12: Moyasar sync verified status returns Pending (no auto-state-change for verified)', function (): void {
    $payment = PaymentFactory::new()->create([
        'amount' => 55.00,
        'payment_order_id' => 'pay-ver-1',
        'payment_company' => 'moyasar',
    ]);

    Http::fake([
        '*/v1/payments/pay-ver-1' => Http::response([
            'id' => 'pay-ver-1',
            'status' => 'verified',
            'amount' => 5500,
        ], 200),
    ]);

    $gw = new MoyasarGateway(new MoyasarClient(moyasarConfig()));
    $got = $gw->sync($payment);
    // verified = null state map → fallback Pending returned
    expect($got)->toBe(PaymentStatus::Pending);
    expect($payment->provider_data['moyasar_status'])->toBe('verified');
});

test('T12: Moyasar fetch uses HTTP Basic auth; never publishable key in privileged fetch', function (): void {
    $payment = PaymentFactory::new()->create([
        'payment_order_id' => 'pay-auth-fetch',
        'payment_company' => 'moyasar',
    ]);

    $capturedAuth = null;
    Http::fake([
        '*/v1/payments/pay-auth-fetch' => function (Request $req) use (&$capturedAuth) {
            $h = $req->header('Authorization');
            $capturedAuth = is_array($h) ? ($h[0] ?? '') : (string) $h;

            return Http::response([
                'id' => 'pay-auth-fetch',
                'status' => 'paid',
                'amount' => 5000,
            ], 200);
        },
    ]);

    $client = new MoyasarClient(moyasarConfig());
    $client->fetchPayment('pay-auth-fetch');

    // Must be Basic auth, username = secret_key
    expect(str_starts_with($capturedAuth, 'Basic '))->toBeTrue();
    $decoded = base64_decode(substr($capturedAuth, 6), true);
    expect($decoded !== false)->toBeTrue();
    expect(explode(':', $decoded, 2)[0])->toBe('sk_test_mo_secret');
    // And never publishable key
    expect(explode(':', $decoded, 2)[0])->not->toBe('pk_test_mo_abc');
});
