<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Purchase\Contracts\PaymentGateway;
use Modules\Purchase\Database\Factories\PaymentFactory;
use Modules\Purchase\Database\Factories\PurchaseFactory;
use Modules\Purchase\Enums\PaymentStatus;
use Modules\Purchase\Exceptions\PaymentGatewayException;
use Modules\Purchase\Exceptions\PaymentProviderUnavailableException;
use Modules\Purchase\Exceptions\PaymentProviderValidationException;
use Modules\Purchase\Gateways\Moyasar\MoyasarClient;
use Modules\Purchase\Gateways\Moyasar\MoyasarGateway;
use Modules\Purchase\Gateways\Moyasar\MoyasarSourceNormalizer;
use Modules\Purchase\Gateways\Moyasar\MoyasarStatusMapper;
use Modules\Purchase\Gateways\Tamara\TamaraClient;
use Modules\Purchase\Gateways\Tamara\TamaraGateway;
use Modules\Purchase\Gateways\Tamara\TamaraStatusMapper;
use Modules\Purchase\Support\Http\PaymentGatewayClientHelpers;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---- Interface reflection ---------------------------------------------------

test('T10: TamaraGateway implements PaymentGateway with all 7 methods', function (): void {
    $reflection = new ReflectionClass(TamaraGateway::class);
    $implements = $reflection->getInterfaceNames();
    expect($implements)->toContain(PaymentGateway::class);

    $methodNames = array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    foreach (['initialize', 'sync', 'authorize', 'verify', 'capture', 'cancel', 'refund'] as $name) {
        if (! in_array($name, $methodNames, true)) {
            throw new ExpectationFailedException("TamaraGateway missing public method $name");
        }
    }
    expect(true)->toBeTrue();
});

test('T10: MoyasarGateway implements PaymentGateway with all 7 methods', function (): void {
    $reflection = new ReflectionClass(MoyasarGateway::class);
    $implements = $reflection->getInterfaceNames();
    expect($implements)->toContain(PaymentGateway::class);

    $methodNames = array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    foreach (['initialize', 'sync', 'authorize', 'verify', 'capture', 'cancel', 'refund'] as $name) {
        if (! in_array($name, $methodNames, true)) {
            throw new ExpectationFailedException("MoyasarGateway missing public method $name");
        }
    }
    expect(true)->toBeTrue();
});

// ---- Amount conversion (Moyasar halalas) ------------------------------------

test('T10: MoyasarClient::toHalalas multiplies by 100 and rounds', function (): void {
    $client = new MoyasarClient();
    expect($client->toHalalas(100.00))->toBe(10000);
    expect($client->toHalalas(99.99))->toBe(9999);
    expect($client->toHalalas(50.5))->toBe(5050);
    expect($client->toHalalas(49.995))->toBe(5000); // banker's round: 49.995 → 5000
    expect($client->toHalalas('12.34'))->toBe(1234);
    expect($client->toHalalas(0))->toBe(0);
});

// ---- Log redaction helper trait ---------------------------------------------

test('T10: PaymentGatewayClientHelpers::redact masks sensitive keys', function (): void {
    $subject = new class {
        use PaymentGatewayClientHelpers;

        public function doRedact(array $data): array
        {
            return $this->redact($data);
        }
    };

    $in = [
        'Authorization' => 'Bearer sk_live_abcdef',
        'body' => [
            'card' => ['number' => '4111111111111111', 'cvc' => '123'],
            'api_token' => 'tok-secret',
            'normal' => 'visible',
        ],
    ];

    $out = $subject->doRedact($in);
    expect($out['Authorization'])->toBe('[REDACTED]');
    expect($out['body']['card'])->toBe('[REDACTED]');
    expect($out['body']['api_token'])->toBe('[REDACTED]');
    expect($out['body']['normal'])->toBe('visible');
});

// ---- Exception normalization via trait (422 / 500) --------------------------

test('T10: convertRequestException maps 422 → PaymentProviderValidationException', function (): void {
    Http::fake([
        '*/orders/*' => Http::response(['errors' => ['oops']], 422),
    ]);

    $client = new TamaraClient(['api_token' => 'tkn', 'environment' => 'sandbox']);

    try {
        $client->getOrder('ord-123');
        throw new ExpectationFailedException('Should have thrown');
    } catch (\Modules\Purchase\Exceptions\PaymentProviderValidationException $e) {
        expect($e)->toBeInstanceOf(PaymentGatewayException::class);
    }

    Http::assertSentCount(1);
});

test('T10: convertRequestException maps 5xx → PaymentProviderUnavailableException', function (): void {
    Http::fake([
        '*/orders/*' => Http::response(['msg' => 'down'], 502),
    ]);

    $client = new TamaraClient(['api_token' => 'tkn', 'environment' => 'sandbox']);

    try {
        $client->getOrder('ord-456');
        throw new ExpectationFailedException('Should have thrown');
    } catch (\Modules\Purchase\Exceptions\PaymentProviderUnavailableException $e) {
        expect($e)->toBeInstanceOf(PaymentGatewayException::class);
    }

    Http::assertSentCount(1);
});

// ---- Idempotency stable IDs -------------------------------------------------

test('T10: Tamara initialize uses order_reference_id = Payment.id (stable across retries)', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->online()->create([
        'amount' => 200.00,
    ]);
    $pid = (string) $payment->getKey();

    $calls = [];
    Http::fake([
        '*/checkout' => function (Request $req) use (&$calls) {
            $body = (array) $req->data();
            $calls[] = $body['order_reference_id'] ?? null;

            return Http::response([
                'order_id' => 'tam-ord-'.count($calls),
                'checkout_id' => 'tam-chk-'.count($calls),
                'checkout_url' => 'https://checkout.example/t'.count($calls),
                'status' => 'new',
            ], 201);
        },
    ]);

    $client = new TamaraClient([
        'api_token' => 'tkn',
        'country' => 'SA', 'currency' => 'SAR', 'locale' => 'en_US',
        'urls' => ['success' => 's', 'failure' => 'f', 'cancel' => 'c', 'notification' => 'n'],
    ]);
    $gw = new TamaraGateway($client);

    $gw->initialize($purchase, $payment);
    $gw->initialize($purchase, $payment);

    expect(count($calls))->toBe(2);
    expect($calls[0])->toBe($pid);
    expect($calls[1])->toBe($pid);
    Http::assertSentCount(2);
});

test('T10: Moyasar initialize returns given_id = Payment.id (stable across retries)', function (): void {
    $purchase = PurchaseFactory::new()->create();
    $payment = PaymentFactory::new()->for($purchase, 'purchase')->online()->create([
        'amount' => 150.25,
    ]);
    $pid = (string) $payment->getKey();

    $client = new MoyasarClient(['publishable_key' => 'pk_test_xx', 'currency' => 'SAR']);
    $gw = new MoyasarGateway($client);

    $r1 = $gw->initialize($purchase, $payment);
    expect($r1['given_id'])->toBe($pid);
    expect($r1['amount_halalas'])->toBe(15025);
    expect($r1['currency'])->toBe('SAR');
    expect($r1['publishable_key'])->toBe('pk_test_xx');
    expect($r1['type'])->toBe('moyasar');

    $r2 = $gw->initialize($purchase, $payment);
    expect($r2['given_id'])->toBe($pid);
});

// ---- Status mappers ---------------------------------------------------------

test('T10: TamaraStatusMapper maps all 9 rule cases correctly', function (): void {
    $mapper = new TamaraStatusMapper();

    $expectations = [
        'new' => PaymentStatus::Processing,
        'approved' => PaymentStatus::Processing,
        'authorised' => PaymentStatus::Succeeded,
        'fully_captured' => PaymentStatus::Succeeded,
        'partially_captured' => PaymentStatus::Succeeded,
        'declined' => PaymentStatus::Failed,
        'canceled' => PaymentStatus::Cancelled,
        'expired' => PaymentStatus::Expired,
    ];

    foreach ($expectations as $raw => $expected) {
        $got = $mapper->map($raw);
        if ($got !== $expected) {
            $gotStr = $got === null ? 'null' : $got->value;
            throw new ExpectationFailedException(sprintf(
                "Tamara map '%s' expected %s, got %s",
                $raw,
                $expected->value,
                $gotStr,
            ));
        }
    }

    expect($mapper->map('refunded'))->toBeNull();
    expect($mapper->isRefunded('refunded'))->toBeTrue();
});

test('T10: MoyasarStatusMapper maps 8 states correctly; refunded/verified null', function (): void {
    $mapper = new MoyasarStatusMapper();
    $expectations = [
        'initiated' => PaymentStatus::Processing,
        'paid' => PaymentStatus::Succeeded,
        'authorized' => PaymentStatus::Processing,
        'captured' => PaymentStatus::Succeeded,
        'failed' => PaymentStatus::Failed,
        'voided' => PaymentStatus::Cancelled,
    ];

    foreach ($expectations as $raw => $expected) {
        $got = $mapper->map($raw);
        if ($got !== $expected) {
            $gotStr = $got === null ? 'null' : $got->value;
            throw new ExpectationFailedException(sprintf(
                "Moyasar map '%s' expected %s, got %s",
                $raw,
                $expected->value,
                $gotStr,
            ));
        }
    }

    expect($mapper->map('refunded'))->toBeNull();
    expect($mapper->map('verified'))->toBeNull();
    expect($mapper->isRefunded('refunded'))->toBeTrue();
    expect($mapper->isVerified('verified'))->toBeTrue();
});

test('T10: MoyasarSourceNormalizer maps 4 source types correctly; preserves raw', function (): void {
    $norm = new MoyasarSourceNormalizer();

    $cases = [
        ['creditcard', 'card'],
        ['Apple Pay', 'apple_pay'],
        ['Samsung Pay', 'samsung_pay'],
        ['STC Pay', 'stc_pay'],
    ];

    foreach ($cases as [$raw, $expected]) {
        $res = $norm->fromSource(['type' => $raw, 'company' => 'mada']);
        expect($res['payment_type'])->toBe($expected);
        expect($res['raw_source_type'])->toBe($raw);
        expect($res['source_company'])->toBe('mada');
    }
});

// ---- Thin gateway check (AC-25): No PaymentStateService, no $payment->status = writes

test('T10 (AC-25 rubric): TamaraGateway source never references PaymentStateService or status-assign', function (): void {
    $src = file_get_contents(__DIR__.'/../../../src/Gateways/Tamara/TamaraGateway.php');
    expect($src !== false)->toBeTrue();

    $usesPss = preg_match('/^\s*use\s+.*PaymentStateService\s*;/m', $src)
        || preg_match('/\bPaymentStateService::/m', $src)
        || preg_match('/\bnew\s+PaymentStateService\b/m', $src)
        || preg_match('/\binstanceof\s+PaymentStateService\b/m', $src)
        || preg_match('/->paymentStateService\b/m', $src);

    $writesStatus = preg_match('/\$payment\s*->\s*status\s*=/m', $src);

    expect((int) $usesPss)->toBe(0, 'TamaraGateway must NOT reference PaymentStateService in code');
    expect((int) $writesStatus)->toBe(0, 'TamaraGateway must NOT write $payment->status = ... directly');
});

test('T10 (AC-25 rubric): MoyasarGateway source never references PaymentStateService or status-assign', function (): void {
    $src = file_get_contents(__DIR__.'/../../../src/Gateways/Moyasar/MoyasarGateway.php');
    expect($src !== false)->toBeTrue();

    $usesPss = preg_match('/^\s*use\s+.*PaymentStateService\s*;/m', $src)
        || preg_match('/\bPaymentStateService::/m', $src)
        || preg_match('/\bnew\s+PaymentStateService\b/m', $src)
        || preg_match('/\binstanceof\s+PaymentStateService\b/m', $src)
        || preg_match('/->paymentStateService\b/m', $src);

    $writesStatus = preg_match('/\$payment\s*->\s*status\s*=/m', $src);

    expect((int) $usesPss)->toBe(0, 'MoyasarGateway must NOT reference PaymentStateService in code');
    expect((int) $writesStatus)->toBe(0, 'MoyasarGateway must NOT write $payment->status = ... directly');
});
