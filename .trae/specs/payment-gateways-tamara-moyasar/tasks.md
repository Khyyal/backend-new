# Payment Gateways (Tamara + Moyasar) — Implementation Tasks Plan

Parent Specification: [spec.md](file:///Users/mac/Herd/khyyal_backend/.trae/specs/payment-gateways-tamara-moyasar/spec.md)
Repository Root: `/Users/mac/Herd/khyyal_backend`

Order is strictly sequential. Each task contains:
- Test Requirements (TRs): typed `rule` or `rubric`
- Priority: `high`, `medium`, or `low`
- Maps to parent Acceptance Criteria (AC-xx in spec.md)

---

## Task 1: Skeleton — Extend PaymentGateway Contract & Add Exception Hierarchy

### Scope
- Modify `modules/Purchase/src/Contracts/PaymentGateway.php` to ADD `sync(Payment):PaymentStatus` + `authorize(Payment):PaymentStatus`.
- Update `FakeGateway` to implement the 2 new methods (trivial: sync returns stored `nextStatus`; authorize returns `PaymentStatus::Processing`).
- Create `modules/Purchase/src/Exceptions/` namespace with 6 exception classes extending base `PaymentGatewayException extends RuntimeException`.
- Register autoload for new namespaces if needed (Exceptions, Gateways subfolders).

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T1-TR1 | rule | `PaymentGateway::class` interface has 7 public methods: initialize, verify, capture, cancel, refund, sync, authorize. Reflection check passes. |
| T1-TR2 | rule | FakeGateway implements 7/7 methods and `class_implements(FakeGateway::class)` contains PaymentGateway. |
| T1-TR3 | rule | 6 exception classes: PaymentGatewayException (base), PaymentInitializationException, PaymentProviderUnavailableException, PaymentProviderValidationException, PaymentVerificationException, PaymentStateConflictException, PaymentAuthorizationException — total of 7 classes total (6 subtypes). All classes exist; `new Xxx('msg') instanceof PaymentGatewayException` = true for each subtype. |

**Priority**: high
**Maps ACs**: AC-19 (interface methods)
**Depends**: nothing (first task)
**Status**: pending

---

## Task 2: Tamara/Moyasar Namespace Folders + Shared HTTP Client Helper

### Scope
Create folder skeleton under `modules/Purchase/src/` (if not already):
```
Gateways/
├── FakeGateway.php (existing)
├── FakeWebhookEventHandler.php (existing)
├── Tamara/
│   ├── TamaraGateway.php          → implements PaymentGateway
│   ├── TamaraClient.php           → HTTP client wrapper
│   ├── TamaraStatusMapper.php     → invokable/static class
│   └── TamaraWebhookEventHandler.php → implements WebhookEventHandler
└── Moyasar/
    ├── MoyasarGateway.php         → implements PaymentGateway
    ├── MoyasarClient.php          → HTTP client wrapper
    ├── MoyasarStatusMapper.php    → invokable/static class
    └── MoyasarWebhookEventHandler.php → implements WebhookEventHandler
Support/
└── Http/
    └── PaymentGatewayClientHelpers.php → trait with timeouts, retry, redaction
Exceptions/ (from Task 1)
```
Create `PaymentGatewayClientHelpers` trait with methods: `protected function defaultHttpOptions(): array` (connect_timeout, timeout, JSON headers), `protected function withRedactedLogging(Closure $requestCall)`, `protected function convertRequestException(Throwable $e): PaymentGatewayException`.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T2-TR1 | rule | Files created: 8 gateway + handler + client files + support trait files have correct FQCN `class_exists` checks pass for each. |
| T2-TR2 | rule | Gateways `implements PaymentGateway`; Handlers `implements WebhookEventHandler`. |
| T2-TR3 | rule | `TamaraStatusMapper` and `MoyasarStatusMapper` have `__invoke(array $providerResponse): PaymentStatus` public method (or `::map` static). Signature check via reflection. |

**Priority**: high
**Maps ACs**: AC-01 (implements), AC-05/AC-06 (mappers), AC-26 (helpers redaction scaffold)
**Depends**: Task 1
**Status**: pending

---

## Task 3: TamaraClient Implementation

### Scope
Implement `TamaraClient` under Gateways/Tamara/. Methods:
- `__construct(array $config)` — reads enabled, environment, api_token, notification_token, country, currency, locale, merchant_urls from config/purchase.php providers.tamara.
- `baseUrl(): string` — sandbox vs production URL switch.
- `setRequestToken(?string $token = null): self` — helper (used in tests).
- `withAuthHeader(Http\PendingRequest $r): Http\PendingRequest` — adds `Authorization: Bearer $this->apiToken`.
- `createCheckout(array $payload): array` — POST /checkout with body $payload + auth. Returns decoded JSON as array; on non-2xx → convert exception via helpers.
- `getOrder(string $tamaraOrderId): array` — GET /orders/{id}
- `authorizeOrder(string $tamaraOrderId): array` — POST /orders/{id}/authorise (note British spelling in endpoint)
- `capturePayment(array $payload): array` — POST /payments/capture
- `cancelOrder(string $tamaraOrderId): array` — POST /orders/{id}/cancel
- `refundPayment(array $payload): array` — POST /orders/{id}/refunds (or endpoint matching docs; tests will mock the actual path)
- Optionally `checkNotificationToken(string $token): bool` — for webhook auth.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T3-TR1 | rule | `Http::fake` sequence → assert correct URL for each operation (sandbox vs prod) and correct Authorization header. |
| T3-TR2 | rule | 4xx/5xx responses from fakes → throw correct subtype of PaymentGatewayException (422 = Validation; 500 = Unavailable). No raw RequestException propagated. |
| T3-TR3 | rule | Sandbox env: `'environment' => 'sandbox'` → calls go to `api-sandbox.tamara.co`. Production env → `api.tamara.co`. |
| T3-TR4 | rule | No credentials/tokens appear in exception messages. |

**Priority**: high
**Maps ACs**: AC-08 (auth headers & URLs), AC-17 (exception normalization, redaction), AC-18 (no PAN/CVC)
**Depends**: Task 2
**Status**: pending

---

## Task 4: TamaraStatusMapper & TamaraGateway

### Scope
Implement TamaraStatusMapper (9 status strings per spec AC-05 map). Implement TamaraGateway methods:
- `initialize(Purchase, Payment): array` — builds checkout payload (order_reference_id=Payment.id, order_number=Purchase.id, items, total_amount, tax_amount, merchant_urls, locale, currency, country, payment_type). Calls TamaraClient::createCheckout. Returns `[checkout_url, provider_reference = order_id, checkout_id, tamara_status]`.
- `sync(Payment): PaymentStatus` — `getOrder(payment_order_id)` → map status + update provider_data in-memory; returns mapped status.
- `verify(Payment): PaymentStatus` — wraps sync.
- `authorize(Payment): PaymentStatus` — `authorizeOrder(payment_order_id)` → maps returned status to Succeeded (after authorised).
- `capture(Payment, ?int $amount=null): PaymentStatus` → capturePayment with total amount or partial.
- `cancel(Payment): PaymentStatus` → cancelOrder.
- `refund(Payment, $amount): bool` → refundPayment; returns true on success.

NOTE: Gateways do NOT call PaymentStateService. Caller persists returned status.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T4-TR1 | rule | 9 status mapping scenarios: new→Processing, approved→Processing, authorised→Succeeded, fully_captured→Succeeded, partially_captured→Succeeded, declined→Failed, canceled→Cancelled, expired→Expired. Provider status preserved in $payment->provider_data['tamara_status'] after gateway processes response. |
| T4-TR2 | rule | initialize builds payload with `order_reference_id = (string) $payment->id` (stable idempotency). HTTP fake assert sees exact `given_id` equivalent (order_reference_id) in request body. |
| T4-TR3 | rule | initialize returns `['checkout_url'=>..., 'provider_reference'=>..., ...]` shape; caller-code-style test simulates persist of Payment.payment_order_id = order_id, payment_company='tamara', payment_type = tamara payment type string, and provider_data['checkout_id'] present and not empty. |
| T4-TR4 | rule | Idempotent retry: second initialize on same Payment ID passes same order_reference_id (no new UUID). |
| T4-TR5 | rule | Capture, cancel, authorize, sync, verify → each correct HTTP verb and path sent. |

**Priority**: high
**Maps ACs**: AC-03 (initialize persistence), AC-05 (status map), AC-12 (sync called from callback), AC-14 (authorize+capture), AC-24 (AC-24 rubric uses payment_company)
**Depends**: Task 3
**Status**: pending

---

## Task 5: MoyasarClient Implementation

### Scope
Implement MoyasarClient:
- `__construct(array $config)` — enabled, publishable_key, secret_key, webhook_secret, currency.
- HTTP Basic auth: username = secret_key, password empty.
- `createPayment(array $payload): array` → POST /v1/payments. Note: accepts `given_id` stable key.
- `fetchPayment(string $moyasarPaymentId): array` → GET /v1/payments/{id}.
- `capturePayment(string $id, int $amountHalalas, string $currency): array` → POST /v1/payments/{id}/capture body: {amount, currency}.
- `voidPayment(string $id): array` → POST /v1/payments/{id}/void.
- `refundPayment(string $id, int $amountHalalas, ?string $reason = null): array` → POST /v1/payments/{id}/refund body {amount, reason?}.
- Helper: `toHalalas(float|int|string $decimalAmount): int` — `(int) round($amount * 100)`. Test: 123.45 → 12345.
- Webhook: `verifyWebhookSignature(string $signature, string $rawBody): bool`.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T5-TR1 | rule | `Http::fake` asserts Basic auth header present and encoded correctly (secret key as username). No publishable_key used in any server-side privileged endpoint (only in initialize return payload for frontend). |
| T5-TR2 | rule | createPayment uses `given_id = stable Payment ID`. Retry sends same given_id. |
| T5-TR3 | rule | `toHalalas(123.45) === 12345`. |
| T5-TR4 | rule | 422 → PaymentProviderValidationException; 500 → PaymentProviderUnavailableException. |
| T5-TR5 | rule | Amount in capture/refund correctly converted to halalas: capture(Payment amount=100.00) → payload amount = 10000. |

**Priority**: high
**Maps ACs**: AC-04 (given_id), AC-08 (Moyasar auth), AC-17 (normalization)
**Depends**: Task 2
**Status**: pending

---

## Task 6: MoyasarStatusMapper + MoyasarSourceNormalizer + MoyasarGateway

### Scope
Write MoyasarStatusMapper (8 states per AC-06). Write MoyasarSourceNormalizer static callable:
```
creditcard   → card
Apple Pay    → apple_pay
Samsung Pay  → samsung_pay
STC Pay      → stc_pay
```
Implement MoyasarGateway:
- initialize → returns shape for frontend init: `['type'=>'moyasar', 'publishable_key'=>..., 'amount_halalas'=>X, 'currency'=>..., 'given_id'=>$payment->id]`.
- sync → fetchPayment → update provider_data, returns mapped internal PaymentStatus.
- verify → sync + amount check. Throws PaymentVerificationException on mismatch (provider amount in halalas vs internal amount * 100).
- authorize → createPayment with manual=true.
- capture → capturePayment (convert domain amount decimal → halalas internally).
- cancel → if authorized, void; throw otherwise.
- refund → refundPayment; returns bool.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T6-TR1 | rule | 8 states mapped per spec; raw moyasar_status always preserved in provider_data. |
| T6-TR2 | rule | 4 source normalizations applied; `payment_type` column set; raw provider source stored in provider_data['source_type']. |
| T6-TR3 | rule | initialize returns publishable_key for frontend use; secret key NOT returned. |
| T6-TR4 | rule | verify: on provider amount mismatch (provider paid 500 halalas vs internal SAR 10 → 1000 halalas) → throws PaymentVerificationException; on exact match → returns internal Succeeded. |
| T6-TR5 | rule | capture amount SAR 50.00 → client->capturePayment received amount=5000 halalas. |
| T6-TR6 | rule | void → internal Cancelled; provider_data['moyasar_status']='voided'. |

**Priority**: high
**Maps ACs**: AC-04, AC-06, AC-07, AC-13, AC-15, AC-16
**Depends**: Task 5
**Status**: pending

---

## Task 7: TamaraWebhookEventHandler & MoyasarWebhookEventHandler

### Scope
Implement both handlers:

**TamaraWebhookEventHandler.handle(WebhookEvent $event)**:
- Payload body expected to contain Tamara's order webhook format (includes order_id, order_status, event_type e.g. order_approved).
- Extract provider order_id → payment_order_id.
- Translate event_type via TamaraStatusMapper.
- Return shape `[payment_order_id, payment_status]`.
- For order_refunded: return null (no direct state transition per current contract; preserve raw event in provider_data manually elsewhere).

**MoyasarWebhookEventHandler.handle(WebhookEvent $event)**:
- Payload contains Moyasar payment_paid / payment_faild / payment_refunded / payment_voided / payment_authorized / payment_captured / payment_verified.
- Match provider typo `payment_faild` (not failed).
- Extract `payment_id` or `id` field (per Moyasar docs) → payment_order_id.
- Map via MoyasarStatusMapper.
- payment_refunded → returns null.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T7-TR1 | rule | 7 Tamara event types (approved, declined, authorised, canceled, captured, expired, refunded) produce correct status OR null. |
| T7-TR2 | rule | 7 Moyasar events including typo `payment_faild` produce correct status. |
| T7-TR3 | rule | payment_order_id returned matches Tamara order_id / Moyasar payment id. |
| T7-TR4 | rule | refund events for both providers return null (since no refund state column yet; we preserve raw details in provider data only — outside handler scope). |

**Priority**: high
**Maps ACs**: AC-09 (registered), AC-10 (payment_faild typo), AC-05/06 (status map correctness via handler)
**Depends**: Task 4, Task 6
**Status**: pending

---

## Task 8: Manager Drivers & WebhookProcessor Handler Map

### Scope
Modify `PaymentGatewayManager`:
- `createTamaraDriver(): PaymentGateway` → instantiates TamaraClient with `config('purchase.providers.tamara')` → injects into TamaraGateway.
- `createMoyasarDriver(): PaymentGateway` → same for Moyasar.

Modify `WebhookProcessorService::__construct` handlers default map:
```php
[
    'fake' => FakeWebhookEventHandler::class,
    'tamara' => TamaraWebhookEventHandler::class,
    'moyasar' => MoyasarWebhookEventHandler::class,
]
```

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T8-TR1 | rule | `PaymentGatewayManager::driver('tamara') instanceof TamaraGateway` → true; same for moyasar. |
| T8-TR2 | rule | `Manager::driver('fake')` still works (no regression). |
| T8-TR3 | rule | `WebhookProcessorService->process('tamara', $extId, $type, $payload)` successfully resolves handler; does NOT throw "No webhook handler registered". |
| T8-TR4 | rule | Same for moyasar. |
| T8-TR5 | rule | Unknown gateway 'xxx' still throws InvalidArgumentException (existing behaviour preserved). |

**Priority**: high
**Maps ACs**: AC-02 (Manager drivers), AC-09 (handlers registered), AC-21 (payment_company → driver), AC-24 (driver resolution rubric)
**Depends**: Task 7
**Status**: pending

---

## Task 9: Extended Config purchase.php

### Scope
Modify `modules/Purchase/config/purchase.php`:
- Keep `default_gateway` key.
- Add `providers` key with tamara + moyasar sub-arrays per spec FR-12.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T9-TR1 | rule | `config('purchase.providers.tamara.currency')` default === `'SAR'` in test environment. |
| T9-TR2 | rule | `config('purchase.providers.moyasar.currency')` default === `'SAR'`. |
| T9-TR3 | rule | Env variables override config values correctly when set (use `putenv`/`Config::set` in test). |

**Priority**: medium
**Maps ACs**: AC-20 (config)
**Depends**: Task 8
**Status**: pending

---

## Task 10: Shared Infrastructure Tests — PaymentGatewayExceptions & StatusMapping Edge Cases

### Scope
Write tests under `modules/Purchase/tests/Unit/Gateways/`:
- ExceptionsTest: all 7 exception classes hierarchically correct; messages can accept string without leaking token.
- ProviderExceptionsNormalizationTest (shared) — both clients re-throw domain exceptions from 422/500/401 provider responses.
- IdempotencyTest: Tamara order_reference_id, Moyasar given_id stable on retry (initialize called twice, same stable ID both calls).
- AmountConversionTest: `toHalalas(...)` 5 decimal values including 0, 123.4, 123.45, 123.005 (rounds).
- PaymentGatewayInterfaceTest: 7 methods exist via reflection.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T10-TR1 | rule | 4xx/5xx provider fake → correct subtype exceptions. |
| T10-TR2 | rule | Idempotency checks: both providers reuse stable IDs. |
| T10-TR3 | rule | All 5 amount conversions correct. |
| T10-TR4 | rule | (rubric AC-27) Tamara tests ≥ 25 total, Moyasar ≥ 25 total (count in final). |
| T10-TR5 | rule | Rubric AC-25 (thin gateway): ensure gateways do not call PaymentStateService (assert no class reference inside TamaraGateway/MoyasarGateway via grep). |
| T10-TR6 | rule | Rubric AC-26 (redaction + logging): `Log::fake` → call gateway initialize → assert logs contain INFO entry with '[REDACTED]' for Authorization; actual token not in log entry context. |

**Priority**: high
**Maps ACs**: AC-17 (exceptions), AC-25 (thinness rubric), AC-26 (logging rubric), AC-27 (count rubric)
**Depends**: Task 4, Task 6
**Status**: pending

---

## Task 11: Tamara Integration Scenario Tests

### Scope
Tests (all with Http::fake sequences):
1. Checkout initialize → TamaraClient POST /checkout → response with order_id/checkout_id → persist Payment fields (caller-style test) → verify shape.
2. Tamara order approved webhook (type=order_approved) → handler → PaymentStatus::Processing.
3. Tamara order_authorised → handler → Succeeded.
4. Tamara callback URL hit → controller-equivalent service call of `gateway->sync` + PaymentStateService apply → status transition works and DB row updates.
5. Tamara order_declined → handler → Failed.
6. Tamara order_canceled → Cancelled.
7. Tamara order_expired → Expired.
8. Tamara capture full, partial.
9. Tamara refund full, partial.
10. Tamara cancel order.
11. Idempotent duplicate webhook events (2× same gateway+extId): second call does NOT double-transition (use existing WebhookIdempotency pattern).
12. Webhook auth: Tamara token verification fails → not processed (service rejects).

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T11-TR1 | rule | 12 scenario tests all pass; no real HTTP calls. |
| T11-TR2 | rule | AC-03 checkout persistence: after initialize scenario asserts `$payment->payment_order_id === 'tamara-order-123'`; provider_data['checkout_id'] present and non-empty. |
| T11-TR3 | rule | AC-11 idempotency: 2 identical webhook calls → attempts counter=1 not 2. |
| T11-TR4 | rule | AC-12 callback: callback flow calls `TamaraClient::getOrder` and state depends on response not URL query params. |
| T11-TR5 | rule | AC-18 no PAN/CVC in provider_data after handling. |

**Priority**: high
**Maps ACs**: AC-03, AC-05, AC-11, AC-12, AC-14, AC-16, AC-22, AC-23
**Depends**: Task 10
**Status**: pending

---

## Task 12: Moyasar Integration Scenario Tests

### Scope
1. Moyasar initialize returns publishable_key; does not call backend createPayment (frontend-only step); simulate callback flow: callback receives payment_id → backend `MoyasarGateway::sync` → provider returns paid → PaymentStatus::Succeeded after amount match.
2. Moyasar payment_paid webhook → Succeeded.
3. Moyasar `payment_faild` (typo) webhook → Failed.
4. Moyasar payment_voided → Cancelled.
5. Moyasar amount mismatch case: provider paid 500 halalas vs Payment.amount SAR 10 (1000 halalas) → verify() throws PaymentVerificationException.
6. Payment create with given_id → assert exact string used = Payment.id.
7. Moyasar capture full.
8. Moyasar capture partial (1/2 amount) → correct halalas passed to capture endpoint.
9. Moyasar refund partial & full.
10. Moyasar void authorized payment → Cancelled.
11. Duplicate webhook: idempotency test (counter + no double transition).
12. Moyasar source type = 'creditcard' → payment_type = 'card'; Apple Pay → apple_pay. Source company 'mada' in provider_data.source_company after sync.
13. Status mapping edge: 'verified' → by default returns status = no change (Pending) / does not auto-succeed.
14. Moyasar authorized webhook → Processing (not Succeeded).
15. Webhook auth: Moyasar webhook secret verification fails → not processed.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T12-TR1 | rule | 15 scenario tests pass; no real HTTP. |
| T12-TR2 | rule | AC-04 stable given_id. |
| T12-TR3 | rule | AC-07 normalization: creditcard → 'card' (payment_type column). |
| T12-TR4 | rule | AC-10 typo payment_faild → handler returns Failed. |
| T12-TR5 | rule | AC-13 callback amount mismatch rejection. |
| T12-TR6 | rule | AC-15 void → Cancelled internal status. |
| T12-TR7 | rule | AC-16 partial refund correct amount in halalas. |

**Priority**: high
**Maps ACs**: AC-04, AC-06, AC-07, AC-10, AC-11, AC-13, AC-15, AC-16, AC-22, AC-23
**Depends**: Task 10
**Status**: pending

---

## Task 13: Cross-Module Regression & Final Gate

### Scope
1. Run: `./vendor/bin/pest modules/Purchase/tests` — existing 72 tests + new tests all pass.
2. Run: `./vendor/bin/pest` — full project 197 tests baseline (existing) + new gateway tests pass; no regressions.
3. AC-22 grep Reservation/Subscription in new files → 0.
4. No controllers created (task excluded them).
5. AC-27 rubric: count Tamara test cases and Moyasar test cases. Document score in Completion Evidence.

### Test Requirements (TR)
| TR | Type | Condition |
|----|------|-----------|
| T13-TR1 | rule | Full project `pest` exits 0. |
| T13-TR2 | rule | Purchase-subset exits 0. |
| T13-TR3 | rule | grep Reservation/Subscription → 0. |
| T13-TR4 | rule | Composer dump-autoload generates classes correctly (exit 0) and package:discover succeeds. |
| T13-TR5 | rubric | (AC-27) Count test cases per provider; apply rubric 0-2. Score and rationale logged. |
| T13-TR6 | rubric | (AC-24,25,26) Score rubrics and document evidence in review.md. |

**Priority**: high
**Maps ACs**: All AC final gate + rubrics final scoring
**Depends**: Task 11, Task 12
**Status**: pending

---

## Cancelled Tasks
None (no user-approved de-scoping yet).

## Reviewer Notes
All tasks contain explicit rule-type TRs for objectively-verifiable conditions and rubric-type TRs where numeric score is produced and stored per task completion. After Task 13 drains queue, enter Phase 5 Review with independent review pass.
