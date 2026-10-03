# Payment Gateways (Tamara + Moyasar) Implementation Specification

Date: 2026-09-26
Scope: Integration of two online payment providers (Tamara, Moyasar) into the existing Purchase module domain layer using the existing PaymentGateway contract.
Repository Root: `/Users/mac/Herd/khyyal_backend`

## Problem

The Purchase module currently ships only with a `FakeGateway` for testing. There are no production-ready payment providers. The application is expected to run in a Saudi-market (KSA / GCC) context and needs:
- **Tamara**: for BNPL (Buy Now Pay Later) - `PAY_BY_INSTALMENTS`, `PAY_NEXT_MONTH` flows.
- **Moyasar**: for card payments (mada/Visa/Mastercard), Apple Pay, Samsung Pay, STC Pay.

Both providers must integrate into the existing Payment state machine, WebhookEvent idempotency, PaymentGateway Manager pattern, and domain events without leaking provider-specific concepts into the Purchase domain layer or adding Reservation/Subscription logic.

## Users

- **Application Developers**: Will call `PaymentGatewayManager::driver('tamara')->initialize($purchase, $payment)` via domain actions.
- **End customers**: Will be redirected to Tamara checkout URL or will see Moyasar payment form (frontend integration scope, not this module).
- **Admin ops**: Will use `sync()` and status reconciliation for failed/webhook-delayed payments.
- **Future Controllers/HTTP layer** (not this task): Will use thin controllers to call Tamara/Moyasar callback verification and webhook routing into the existing `WebhookProcessorService`.

## Goals

1. **Extend PaymentGateway contract** (only if truly needed) to support a `sync()` method for server-side provider-state reconciliation after browser callback and an `authorize()` method for Tamara's explicit authorize flow (after `approved` → `authorised`) and Moyasar's `manual=true` authorization flow. Keep existing `initialize/verify/capture/cancel/refund` signature.
2. **TamaraGateway + TamaraClient** under `Modules\Purchase\Gateways\Tamara`. Uses Laravel HTTP client, base URLs configurable via env, Bearer token auth.
3. **MoyasarGateway + MoyasarClient** under `Modules\Purchase\Gateways\Moyasar`. Uses Laravel HTTP client with HTTP Basic auth using secret key.
4. **Provider Clients**: TamaraClient encapsulates: `POST /checkout`, `GET /orders/{id}`, `POST /orders/{id}/authorise`, `POST /payments/capture`, `POST /orders/{id}/cancel`, refund endpoint. MoyasarClient encapsulates: `POST /v1/payments`, `GET /v1/payments/{id}`, `POST /v1/payments/{id}/capture`, `POST /v1/payments/{id}/void`, `POST /v1/payments/{id}/refund`.
5. **Idempotency**:
   - Tamara: uses `order_reference_id = (string) Payment->id` (stable). Persists Tamara `order_id` in `payment_order_id` first-class column and `checkout_id` in provider_data.
   - Moyasar: uses `given_id = (string) Payment->id` (stable). Persists Moyasar `payment.id` in `payment_order_id` first-class column.
6. **Provider state translation**: Tamara webhook events `order_*` map to internal statuses; Moyasar events `payment_*` (including provider typo `payment_faild`) map to internal statuses. Raw provider status always preserved in `provider_data.tamara_status` or `provider_data.moyasar_status`.
7. **Webhook handlers**: TamaraWebhookEventHandler, MoyasarWebhookEventHandler implement existing `WebhookEventHandler` contract. Both registered in `WebhookProcessorService` constructor handler map alongside `'fake' => FakeWebhookEventHandler::class`.
8. **Configuration**: `config/purchase.php` (module config) extended with `'providers' => [ 'tamara' => [...], 'moyasar' => [...] ]` with env variables:
   - Tamara: `TAMARA_ENABLED`, `TAMARA_ENVIRONMENT` (sandbox|production), `TAMARA_API_TOKEN`, `TAMARA_NOTIFICATION_TOKEN`, `TAMARA_COUNTRY`, `TAMARA_CURRENCY`, `TAMARA_LOCALE`.
   - Moyasar: `MOYASAR_ENABLED`, `MOYASAR_PUBLISHABLE_KEY`, `MOYASAR_SECRET_KEY`, `MOYASAR_WEBHOOK_SECRET`.
9. **Manager drivers**: `createTamaraDriver()`, `createMoyasarDriver()` in `PaymentGatewayManager`. Factory method reads config.
10. **Pest tests**: Comprehensive test coverage with mocked HTTP responses (never call real provider APIs). Test initialize → field persistence → status mapping → webhook idempotency → sync → authorize → capture → void → cancel → refund → exception normalization.
11. **Gateway lookup by `payment_company`**: When Payment has `payment_company = 'tamara'`, `PaymentGatewayManager::driver('tamara')` returns TamaraGateway.
12. **Callback safety**: Tamara browser callback and Moyasar browser callback both call provider `sync()` or fetch server-side. **NEVER** mark payment succeeded solely from browser URL.
13. **Provider-specific HTTP exceptions**: Normalized. Never expose raw response JSON to end-clients.

## Non-Goals

1. **NO Controllers / HTTP endpoints** in this task. Spec prompt mentions endpoints like `POST /webhooks/payments/tamara` — they will be implemented in a SEPARATE task (frontend-controllers layer). This task only implements the domain+client+gateway+config+handler+test pieces.
2. **NO Reservation / Subscription logic**. Gateways must only operate on Payment/Purchase concerns.
3. **NO raw PAN / CVC card data anywhere in the Laravel backend** for Moyasar. Only `publishable_key`-based Moyasar Form frontend creates tokens. If we need "create payment" for Moyasar, we only use the tokenized sources. This task does not implement Moyasar frontend.
4. **NO real credentials in code / configs**. Only env placeholders.
5. **NO external SDK packages** (aghfatehi/laravel-tamara, iabduul7/laravel-moyasar). Implement via Laravel HTTP client directly.
6. **NO Money VO / currency fields in Purchase / Payment domain models**. Provider adapters translate flat decimals to provider-required format at the API boundary.
7. **NO modification to Center, Client, or unrelated modules**. Only modify within Purchase module (except global config merge in PurchaseServiceProvider which is already in place).

## Confirmed Assumptions

1. The existing `PaymentGateway` interface's `initialize(Purchase $purchase, Payment $payment): array` signature is acceptable and can be extended. Per prompt suggestion, we will ADD `authorize()` method to PaymentGateway (already has, for Tamara we need a no-op in FakeGateway for backward compat, plus MoyasarGateway). Also add `sync()`. See "PaymentGateway Contract Extension" section.
2. Payment `amount` is `decimal(14,2)` flat. Tamara expects amount as number (e.g., 300.00). Moyasar expects integer in smallest currency unit (halalas for SAR). Conversion: MoyasarClient `amount_to_halalas(Payment $payment): int = (int) round($payment->amount * 100)`.
3. `payment_company` values for Tamara = `'tamara'` (string), Moyasar = `'moyasar'`.
4. `payment_type` normalization: Tamara returns `pay_by_instalments`, `pay_next_month` (keep provider snake_case). Moyasar: `creditcard → card`, `Apple Pay → apple_pay`, `Samsung Pay → samsung_pay`, `STC Pay → stc_pay`. Normalized value stored in first-class `payment_type` column; raw provider value kept in `provider_data.source_type`.
5. TamaraNotificationToken (webhook token): Tamara sends it in `tamaraToken` query param OR Bearer auth; also it's HS256 JWT. For simplicity: check both and verify. Implementation will validate both presence + HS256 signature.
6. Moyasar Webhook auth: provider docs mention "Webhook Secret Token". Use hash_hmac SHA-256 signature comparison on request body (standard approach for both providers).
7. No controllers implemented. Spec prompt's "Webhook Processing (sec 36)" pipeline is implemented as service-class logic only; controllers will later call these services.

## Functional Requirements (FRs)

### Domain Contract Extension
FR-1: **PaymentGateway interface** gets two additional optional methods:
```php
// Added
public function sync(Payment $payment): PaymentStatus;
public function authorize(Payment $payment): PaymentStatus;
```
- Backward compat: FakeGateway will have trivial implementations.

### Tamara Client & Gateway
FR-2: TamaraClient with:
- `createCheckout(array $params): array` → `POST /checkout` with Bearer TAMARA_API_TOKEN
- `getOrder(string $tamaraOrderId): array` → `GET /orders/{id}`
- `authorizeOrder(string $tamaraOrderId): array` → `POST /orders/{id}/authorise`
- `capturePayment(array $params): array` → `POST /payments/capture`
- `cancelOrder(string $tamaraOrderId): array` → `POST /orders/{id}/cancel`
- `refundPayment(array $params): array` → Tamara's current refund endpoint (POST /payments/refund or /orders/{id}/refund as per current docs).
- Sandbox base URL `https://api-sandbox.tamara.co`, production `https://api.tamara.co`.
- Timeouts: connect 5s, request 15s.
- All errors throw subclass of `PaymentGatewayException` (not `HttpException` or raw).

FR-3: TamaraGateway implements `PaymentGateway` interface + extended methods:
- `initialize($purchase, $payment)` → builds checkout request from Purchase items, amounts, merchant URLs (success/failure/cancel as configurable), locale = `purchase config providers.tamara.locale`, currency = config currency, country = config country, `order_reference_id = (string) $payment->id`, `order_number = (string) $purchase->id`, items = PurchaseItems, consumer (optional from Buyer metadata if available). Returns `['checkout_url' => ..., 'provider_reference' => tamara order_id, ...]`.
- On initialize success, the caller (not the gateway itself) persists `payment_order_id = tamara_order_id`, `payment_company = 'tamara'`, `payment_type = requested tamara payment_type`, `provider_data` with `checkout_id`, `checkout_url`, `tamara_status = 'new'`.
- `sync(Payment $payment)` → calls `TamaraClient::getOrder(payment_order_id)`. Translates provider status → internal via TamaraStatusMapper. Updates `provider_data.tamara_status` and returns internal PaymentStatus. **Does NOT call PaymentStateService directly** (gateways are read-only with respect to internal state transitions; caller applies them).
- `authorize(Payment $payment)` → `authoriseOrder`.
- `verify(Payment $payment)` → equivalent to sync + returns internal status.
- `capture(Payment $payment, ?int $amount = null)` → `capturePayment`.
- `cancel(Payment $payment)` → `cancelOrder`.
- `refund(Payment $payment, $amount)` → `refundPayment`; returns bool on success; throws on failure.

FR-4: **TamaraStatusMapper** helper (static method or invokable):
```
new → Processing
approved → Processing
authorised → Succeeded
fully_captured → Succeeded
partially_captured → Succeeded  (preserve capture details in provider_data)
declined → Failed
canceled → Cancelled
expired → Expired
refunded → (gateway only stores in provider_data; caller applies refund representation per existing convention)
```
Mapper preserves raw tamara_status in provider_data ALWAYS (never discards).

### Moyasar Client & Gateway
FR-5: MoyasarClient with:
- `createPayment(array $params): array` → `POST /v1/payments` (HTTP Basic: secret key). Accepts `given_id = stable Payment id`.
- `fetchPayment(string $moyasarPaymentId): array` → `GET /v1/payments/{id}` (Basic auth).
- `capturePayment(string $moyasarPaymentId, int $amountHalalas, string $currency): array` → `POST /v1/payments/{id}/capture`.
- `voidPayment(string $moyasarPaymentId): array` → `POST /v1/payments/{id}/void`.
- `refundPayment(string $moyasarPaymentId, int $amountHalalas, ?string $reason = null): array` → `POST /v1/payments/{id}/refund`.
- Base URL `https://api.moyasar.com/v1`.
- HTTP Basic auth: username = secret key, password empty (per Moyasar docs).
- Same timeouts; all errors throw PaymentGatewayException subtypes.

FR-6: MoyasarGateway implements PaymentGateway + extended:
- `initialize($purchase, $payment)` → for Moyasar, this is NOT a provider create call (Moyasar requires frontend tokenization first). Instead MoyasarGateway::initialize returns: `['type' => 'moyasar', 'publishable_key' => publishable, 'amount_halalas' => X, 'currency' => SAR_from_config, 'given_id' => $payment->id]`. Caller persists `payment_company = 'moyasar'`, maybe no payment_order_id yet (stored after backend fetch on callback). **If** the initialize call receives a Moyasar token/source in a future flow, it can call createPayment; for now we return frontend-init data.
- `sync(Payment $payment)` → calls `MoyasarClient::fetchPayment(payment_order_id)`, normalizes status & source, updates provider_data, returns internal PaymentStatus.
- `verify(Payment $payment)` → sync with amount check (provider amount must equal Payment amount). Throws PaymentVerificationException on amount mismatch.
- `authorize(Payment $payment)` → creates a payment with `manual = true`.
- `capture(Payment $payment, ?int $amount = null)` → capturePayment with amount (in domain decimal; convert to halalas internally in client).
- `cancel(Payment $payment)` → voidPayment if authorized/capturable state.
- `refund(Payment $payment, $amount)` → refundPayment.

FR-7: **MoyasarStatusMapper**:
```
initiated → Processing
paid → Succeeded
authorized → Processing (unless caller defines authorized = sufficient for capture-only flow; default Processing)
captured → Succeeded
failed → Failed
voided → Cancelled
refunded → (store in provider_data, caller applies refund convention)
verified → (do not auto-succeed unless future explicit policy set, default: no state change)
```
Raw moyasar_status preserved in provider_data ALWAYS. Additionally source details: `source_type` (provider creditcard/Apple Pay/etc.), `source_company` (mada/visa/mastercard), `transaction_url`, `reference_number`, `authorization_code` → all into provider_data.

FR-8: **Moyasar Source normalization** → `payment_type`:
```
creditcard → card
Apple Pay → apple_pay
Samsung Pay → samsung_pay
STC Pay → stc_pay
```
Raw value in `provider_data.source_type`.

### Webhook Event Handlers
FR-9: `TamaraWebhookEventHandler implements WebhookEventHandler` + `MoyasarWebhookEventHandler implements WebhookEventHandler`:
- Each handler, given a WebhookEvent, returns `?array{payment_order_id: string, payment_status: PaymentStatus}`.
- Tamara: provider events `order_approved, order_declined, order_authorised, order_canceled, order_captured, order_refunded, order_expired`. Extract `order_id` from webhook payload body → `payment_order_id`. Use TamaraStatusMapper to translate.
- Moyasar: provider events `payment_paid, payment_faild, payment_refunded, payment_voided, payment_authorized, payment_captured, payment_verified`. Extract `payment.id` → `payment_order_id`. Use MoyasarStatusMapper to translate. **Match exact provider event name strings including the typo 'faild'.**
- For refund events (Tamara `order_refunded`, Moyasar `payment_refunded`): if the internal state already Succeeded, returning null is acceptable (per existing handler contract returning null = no payment-action, and the existing applyPaymentAction doesn't have a Refund state — future enhancement can extend; for now we preserve `provider_data.refunded=true` and return no action).

### Manager + Provider Registration
FR-10: **PaymentGatewayManager**:
- Add `createTamaraDriver()`: new TamaraGateway(new TamaraClient(...config...)).
- Add `createMoyasarDriver()`: new MoyasarGateway(new MoyasarClient(...config...)).
- Keep `createFakeDriver()` unchanged.
- Default driver: remains `purchase.default_gateway` env.

FR-11: **WebhookProcessorService constructor handlers map** extended:
```php
[
    'fake'    => FakeWebhookEventHandler::class,
    'tamara'  => TamaraWebhookEventHandler::class,
    'moyasar' => MoyasarWebhookEventHandler::class,
]
```
- WebhookProcessorService MUST remain backwards compatible with existing `fake` handler.

### Configuration
FR-12: `modules/Purchase/config/purchase.php` extended with:
```php
'default_gateway' => env('PURCHASE_GATEWAY', 'fake'),

'providers' => [
    'tamara' => [
        'enabled' => env('TAMARA_ENABLED', false),
        'environment' => env('TAMARA_ENVIRONMENT', 'sandbox'),
        'api_token' => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'country' => env('TAMARA_COUNTRY', 'SA'),
        'currency' => env('TAMARA_CURRENCY', 'SAR'),
        'locale' => env('TAMARA_LOCALE', 'en_US'),
        'payment_type' => env('TAMARA_DEFAULT_PAYMENT_TYPE', 'PAY_BY_INSTALMENTS'),
        'merchant_urls' => [
            'success' => env('TAMARA_URL_SUCCESS', '/payment/tamara/success'),
            'failure' => env('TAMARA_URL_FAILURE', '/payment/tamara/failure'),
            'cancel'  => env('TAMARA_URL_CANCEL',  '/payment/tamara/cancel'),
            'notification' => env('TAMARA_URL_NOTIFICATION', '/webhooks/payments/tamara'),
        ],
    ],
    'moyasar' => [
        'enabled' => env('MOYASAR_ENABLED', false),
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
        'currency' => env('MOYASAR_CURRENCY', 'SAR'),
    ],
],
```

### Exception Hierarchy
FR-13: Under `Modules\Purchase\Exceptions\` (new namespace), add (extends RuntimeException or \Exception or appropriate):
```
PaymentGatewayException (base)
├── PaymentInitializationException
├── PaymentProviderUnavailableException
├── PaymentProviderValidationException
├── PaymentVerificationException
├── PaymentStateConflictException
└── PaymentAuthorizationException
```
Gateways and Clients must NEVER re-throw raw `Illuminate\Http\Client\RequestException`. Always catch, redact sensitive info (token, card data), log (use Laravel Log with context), and re-throw the corresponding domain exception.

### HTTP Client Shared Infra
FR-14: Optionally add a `BaseHttpClient` or helper trait under `Modules\Purchase\Support\Http\` to centralize: connect_timeout 5, timeout 15, retry strategy with idempotency-aware decision, `accept: application/json` header, redacted logging of request/response. TamaraClient and MoyasarClient use it. Retry `initialize()` only once with stable given_id/reference_id; NEVER retry `capture`/`refund` unless idempotency key is supported (capture is idempotent if provider supports capture-id key). For simplicity: only retry initialize (1 retry); never retry capture/refund/void.

---

## Constraints & Dependencies

### Constraints
- All code lives under `modules/Purchase/src/...` (Gateways/..., Exceptions/..., Support/..., Services/...).
- Laravel HTTP client only. No external SDK packages installed (no composer require of tamara/moyasar SDKs).
- Existing `Modules\Purchase\Models\Payment` columns unchanged. Only use `payment_company`, `payment_type`, `payment_order_id`, `provider_data`, `metadata`.
- Existing `PaymentStateService` central transition methods are the ONLY way to change `Payment.status`. Gateways MUST NOT write status directly.
- Gateways are "read-only" with respect to Payment state (they return PaymentStatus; callers apply via PaymentStateService).
- Provider status always preserved in `provider_data` — never discarded.
- Amounts: `Payment.amount` is always decimal(14,2) in SAR. TamaraClient accepts decimal (it matches API). MoyasarClient converts to halalas (×100 integer).
- Webhook events: unique constraint `(gateway, external_event_id)` in existing WebhookEvent already ensures idempotency at DB level. Webhook handlers only translate; they don't attempt to re-check idempotency because processor does it.
- **No PAN/CVC storage ever**: test assertions ensure payment model never has these fields stored in provider_data or elsewhere.
- **Secret keys never appear in logs**: HTTP middleware redacts Authorization header.

### Dependencies
- Existing Purchase module (fully implemented and tests green): [PurchaseServiceProvider](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/src/PurchaseServiceProvider.php), [PaymentGateway contract](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/src/Contracts/PaymentGateway.php), [WebhookEventHandler](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/src/Contracts/WebhookEventHandler.php), [PaymentGatewayManager](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/src/Managers/PaymentGatewayManager.php), [WebhookProcessorService](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/src/Services/WebhookProcessorService.php), [Payment model](file:///Users/mac/Herd/khyyal_backend/modules/Purchase/src/Models/Payment.php).
- Laravel 11 HTTP client (`Illuminate\Support\Facades\Http`).
- Root PHP 8.x (enum returns, readonly promoted properties, first-class callable syntax OK).

---

## Acceptance Criteria

Type: `rule` = objectively verifiable boolean. `rubric` = evaluative quality dimension (score with threshold).

| AC # | Type | Requirement | Evidence Source |
|------|------|-------------|-----------------|
| AC-01 | rule | TamaraGateway and MoyasarGateway implement `Modules\Purchase\Contracts\PaymentGateway` interface (including extended sync and authorize methods). | `class_exists(TamaraGateway::class)`, `implements` check; Pest "it implements PaymentGateway" tests green. |
| AC-02 | rule | PaymentGatewayManager has `createTamaraDriver()`, `createMoyasarDriver()` methods and they return correct instances. | Manager tests: `$manager->driver('tamara') instanceof TamaraGateway` → true; same for moyasar. |
| AC-03 | rule | Tamara initialize persists Tamara `order_id` in Payment.payment_order_id; checkout_id and checkout_url retained in provider_data. | Pest test: mock HTTP createCheckout → call initialize → assert payment fields. |
| AC-04 | rule | Moyasar `given_id` in provider createPayment call = `(string) Payment->id`; stable across retries. | Pest test: spy on HTTP payload → `given_id === (string) $paymentId`; retry assertion. |
| AC-05 | rule | Tamara status translation: 8 Tamara states (new/approved/authorised/fully_captured/partially_captured/declined/canceled/expired) mapped to internal PaymentStatus. Provider raw status stored in provider_data.tamara_status. | Unit test with 9 scenarios; after status map → `$event->provider_data['tamara_status']` contains raw string. |
| AC-06 | rule | Moyasar status translation: 8 states (initiated/paid/authorized/captured/failed/voided/refunded/verified) mapped per rules. Raw moyasar_status preserved. | Unit test 8 scenarios + provider_data assert. |
| AC-07 | rule | Moyasar `source_type` normalization applied to `payment_type` column: creditcard→card, Apple Pay→apple_pay, Samsung Pay→samsung_pay, STC Pay→stc_pay. Raw value preserved in provider_data. | Test with 4 source fixtures → `Payment::payment_type === 'card'/'apple_pay'/...`. |
| AC-08 | rule | TamaraClient and MoyasarClient use correct auth headers and base URLs (sandbox vs prod for Tamara, Basic auth for Moyasar). | HTTP `fake` sequence tests: assertSent; checks Authorization header and URL string. |
| AC-09 | rule | TamaraWebhookEventHandler + MoyasarWebhookEventHandler both implement WebhookEventHandler; registered in WebhookProcessorService constructor handlers under keys 'tamara' and 'moyasar'. | Constructor handler-map inspection test; processor->process('tamara', ...) resolves Tamara handler without throwing "no handler". |
| AC-10 | rule | Moyasar webhook event name `payment_faild` (provider typo) is handled correctly. | Event type 'payment_faild' payload → handler returns `[..., PaymentStatus::Failed]`. |
| AC-11 | rule | Webhook idempotency: same (gateway, external_event_id) replayed → attempts count not incremented, no double-transition. | Use existing WebhookIdempotencyTest pattern; write 2 replayed scenarios for tamara and moyasar. |
| AC-12 | rule | Tamara callback (via sync()) does not mark payment succeeded based on URL parameters alone; sync() calls TamaraClient::getOrder() server-side. | Test callback flow → HTTP fake assertSent GET /orders/{id}; state transition to Succeeded AFTER server response; transition DOES NOT happen if HTTP mock returns "declined". |
| AC-13 | rule | Moyasar callback verify() performs: fetchPayment server-side + amount match (throws PaymentVerificationException on amount mismatch). | Amount mismatch scenario → exception; amount match scenario → returns Succeeded/Paid. |
| AC-14 | rule | Tamara authorize operation (authorizeOrder) and capture (capturePayment) both work, return correct PaymentStatus or throw. | HTTP fakes: assert POST /orders/{id}/authorise then POST /payments/capture correct payloads. |
| AC-15 | rule | Moyasar void (voidPayment) maps provider 'voided' to internal Cancelled. | Test: after void() call status mapping + provider_data preserved. |
| AC-16 | rule | Tamara refund and Moyasar refund both call correct provider endpoints; partial refund accepted in both cases (amount param respected). | Test mock HTTP endpoints: partial 5000 halalas / 50.00 decimals are converted correctly per provider. |
| AC-17 | rule | Provider exception normalization: HTTP 422 / 500 from provider → throws `PaymentProviderValidationException` / `PaymentProviderUnavailableException` respectively (not raw RequestException). No raw provider response leaks via exception message (redacted). | Test with 422/500 fake responses → `assertThrows` correct exception type; message does not contain API token. |
| AC-18 | rule | No PAN/CVC string "424242...4242" / "123" / etc. ever appears in logged provider_data or Payment columns. We test that normalizing code strips them; also test initialization / response parsing never writes these fields. | Redaction test: HTTP fake response contains sensitive data → assert after save/handler processing, Payment provider_data does not contain them. |
| AC-19 | rule | PaymentGateway contract has (1) initialize, (2) verify, (3) capture, (4) cancel, (5) refund, (6) sync, (7) authorize. Total of 7 methods with exact signatures specified. | Interface file diff check: PaymentGateway has exactly these 7 public method names. |
| AC-20 | rule | Configuration: mergeConfigFrom('purchase') contains providers.tamara + providers.moyasar arrays; env keys correctly referenced. | `config('purchase.providers.tamara.currency')` returns `'SAR'` default in tests; env var override works. |
| AC-21 | rule | `payment_company` strings persisted in Payment correctly: Tamara → 'tamara', Moyasar → 'moyasar'. Manager driver resolution uses same string. | Payment::create → then `PaymentGatewayManager::driver($payment->payment_company)` returns correct gateway. |
| AC-22 | rule | NO Reservation/Subscription class references anywhere in Tamara/Moyasar source files, clients, gateways, handlers. | grep: `grep -rniE 'Reservation|Subscription' modules/Purchase/src/Gateways/{Tamara,Moyasar}` → 0 matches. |
| AC-23 | rule | Failed attempts not overwritten: createPayment with `given_id=X` fails once → Payment row exists with status Failed → second retry using same internal Payment ID (different external HTTP attempt) does NOT UPDATE row #1; instead creates a NEW Payment row per existing "retry = new Payment" invariant. | Same pattern as AC-27 payment retry existing test; apply same 2-Payment-rows-created test for Tamara/Moyasar. |
| AC-24 | rubric | Gateway resolution by payment_company: Dimension: 0-2. 0: unresolved. 1: resolved but requires manual mapping. 2: direct Payment->payment_company string is the driver key used by Manager::driver(). **Threshold ≥ 1**. | Code review + test: Manager::driver() call directly uses $payment->payment_company. |
| AC-25 | rubric | Thinness of gateway logic. Dimension 0-2. 0: contains Purchase business logic (apply state transitions directly inside gateway). 1: mostly clean but occasional direct model writes. 2: Gateways are "pure" adapters; they call HTTP clients, normalize responses, return internal status; never call PaymentStateService inside themselves; callers persist provider data. **Threshold ≥ 1**. | Code review of TamaraGateway/MoyasarGateway: no call to PaymentStateService inside Gateway; no `$payment->status =` assignments inside gateway. |
| AC-26 | rubric | Logging + redaction quality. Dimension 0-2. 0: no logs / log raw token. 1: logs but redaction is patchy. 2: consistent request/response logging (INFO for success; ERROR for failure), Authorization header + any card/token fields in JSON redacted. **Threshold ≥ 1**. | Review logs in tests (use `Log::fake()`) → log entries exist; authorization value is `[REDACTED]`. |
| AC-27 | rubric | Test coverage for spec's 40 shared/41 tamara/42 moyasar test topics. Dimension 0-2. 0: < 10 tests per provider. 1: 15-24 tests per provider, most happy-path + key failure paths. 2: ≥ 25 tests per provider covering all ACs, idempotency tests, authorization/capture/void/refund/cancel/sync for each provider, 3+ exception paths, 2+ status mapping edge cases. **Threshold ≥ 1** (at least medium coverage). | Count Tamara test cases and Moyasar test cases. |

## Open Questions

- **Q1**: Does the existing system use integer halala minor units elsewhere? We currently have Payment.amount as `decimal(14,2)`. **Assumption: keep decimal; only convert at MoyasarClient boundary.** Confirmed by prompt section 2 line 90-92. Keep.
- **Q2**: Does Tamara's refund endpoint go via `POST /orders/{orderId}/refunds` or `POST /payments/refunds`? Docs historically moved. **Assumption: implement one that matches the current Tamara reference docs link at prompt line 20 (docs.tamara.co), and unit test payload shape independent of exact URL.**
- **Q3**: Are callbacks/controllers in scope? Prompt mentions but per section 36 it says "Keep HTTP layer thin"; this spec explicitly marks them as OUT OF SCOPE (Non-Goal 1) for this task.

## Provider Docs Reference (from prompt)

- Tamara API reference: https://docs.tamara.co/reference/tamara-api-reference-documentation
- Moyasar API reference: https://docs.moyasar.com/api/api-introduction
