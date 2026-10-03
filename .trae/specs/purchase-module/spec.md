# Purchase Module — Specification

## Problem

The khyyal_backend application currently has no commercial transaction layer. All downstream business concepts (center membership plans, client service bookings, etc.) would need to individually model purchases and payments if no common module existed. This creates duplication, inconsistent payment lifecycle handling, no audit trail for retries, no webhook idempotency guarantees, and no clear separation between commercial agreement (Purchase) and fulfillment (Reservation/Subscription).

This module implements a reusable Purchase domain that:
1. Supports both platform sales (Center → Platform for membership) and center sales (Client → Center for services).
2. Decouples Purchase state from Payment state (a confirmed purchase can have pending cash-on-arrival payment; a failed payment does not cancel the purchase).
3. Supports multiple payment attempts per Purchase for retry scenarios.
4. Abstracts payment providers behind an interface with pluggable gateways.
5. Provides idempotent webhook processing for provider callbacks.
6. Emits typed events that downstream fulfillment modules can consume.

## Users

- **Clients**: Purchase services/products from centers via the client API.
- **Center users (owners/staff)**: Create purchases on behalf of clients via the center dashboard API.
- **Platform**: Sell membership plans to centers via the platform/center registration flow.
- **Future modules** (Reservation, Subscription, Membership): Consume `PurchaseConfirmed`, `PaymentSucceeded`, and related events to activate their own fulfillment records.

## Goals

1. Provide a polymorphic `Purchase` model with `buyer` (morphTo), `merchant` (morphTo), `items` (hasMany), and `payments` (hasMany).
2. Provide `Buyer` contract + trait and `Purchasable` contract + trait for models that can participate.
3. Implement `PurchaseStatus` (pending/confirmed/cancelled/completed) and `PurchaseSource` (client/center/system) as string enums.
4. Implement `PurchaseItem` with price snapshot fields (unit_price, subtotal, discount_amount, tax_amount, total_amount) + quantity + metadata JSON.
5. Implement `Payment` model with `PaymentMethod` enum (online/cash_on_arrival/manual) and `PaymentStatus` enum (pending/processing/succeeded/failed/cancelled/expired).
6. Support multiple `Payment` records per `Purchase` (retry = new Payment, never mutate failed → processing).
7. Implement `PaymentGateway` interface + `PaymentGatewayManager` resolver (Laravel Manager-style) with initialize/verify/capture/cancel/refund operations.
8. Implement `WebhookEvent` model with a `unique(gateway, external_event_id)` constraint for idempotency.
9. Implement domain/application actions: `CreatePurchase`, `CreatePayment`, payment state-transition service, purchase state-transition service.
10. Dispatch internal events: `PurchaseCreated`, `PurchaseConfirmed`, `PurchaseCancelled`, `PurchaseCompleted`, `PaymentCreated`, `PaymentProcessing`, `PaymentSucceeded`, `PaymentFailed`, `PaymentCancelled`, `PaymentExpired`.
11. Write comprehensive Pest tests covering all acceptance criteria.
12. Update `composer.json` autoload PSR-4 entries for the new `Modules\Purchase` namespace.
13. Update `Center` and `Client` models to implement/use the `Buyer` contract/trait.

## Non-Goals

1. **NO Reservation/Subscription logic.** The Purchase module only emits events; it does not create, activate, cancel, or enforce visibility of Reservation or Subscription records.
2. **NO fulfillment global scopes.** No Eloquent `addGlobalScope` on any model that filters by `purchase.status == confirmed`.
3. **NO Money value object.** Use flat numeric amount fields (decimal or integer minor units, matching project convention) — single currency application per the spec.
4. **NO specific payment provider integration** (Stripe, Moyasar, PayPal concrete classes are OUT OF SCOPE for this task). Only the contract, manager, and a test/fake gateway are in scope.
5. **NO API controllers or HTTP endpoints.** This is a domain-module-only implementation. Future tasks will wire up controllers/routes.
6. **NO UI.** This is a backend-only module.

---

## Confirmed Open Questions / Assumptions

| # | Question / Assumption | Decision |
|---|---|---|
| 1 | Money column precision | Use `decimal(14, 2)` for all amount columns (subtotal, discount, tax, total, payment amount) — same pattern as other financial-friendly projects; aligns with `decimal:2` cast. |
| 2 | Metadata field type | Use `json` column + `array` cast on Purchase, PurchaseItem, Payment, WebhookEvent payload. |
| 3 | Module namespace | `Modules\Purchase` — follows existing module structure. |
| 4 | Apply `Buyer` trait to existing models | Yes: `Modules\Centers\Models\Center` and `Modules\Clients\Models\Client` will use the `Buyer` trait in this task. |
| 5 | `Merchant` contract | `merchant` is a morphTo on Purchase. For "Platform" merchant, use a `Platform` marker class (singleton model or empty morph class) — create `Modules\Purchase\Models\Merchant\Platform` as a simple morph-mappable class with no DB table. |
| 6 | Testing framework | Pest (existing project convention: `tests/Pest.php`, `uses(TestCase::class, RefreshDatabase::class)`). |
| 7 | Provider column naming on Payment | Use `payment_company` (string), `payment_type` (string), `payment_order_id` (string nullable) as first-class queryable columns per the spec. |
| 8 | Payment retries = new Payment record | Yes, enforced by the `CreatePayment` action; no mutation of existing Payment's status from failed → processing. |
| 9 | Concurrency for state transitions | Use DB transactions + `Model::lockForUpdate()` row locks on Payment/Purchase during state transitions in service classes. |
| 10 | Post-commit events | Use `afterCommit: true` on all state-changing event dispatches via `DB::afterCommit()` or Laravel's `ShouldDispatchAfterCommit` equivalent for event listeners. |

---

## Functional Requirements

### FR-1 — Module skeleton & autoload registration

Create a `modules/Purchase/` directory tree following the existing modular pattern:
- `src/` (PSR-4 namespace `Modules\Purchase`)
- `database/migrations/`
- `database/factories/` (PSR-4 namespace `Modules\Purchase\Database\Factories`)
- `tests/` (Feature + Unit + Pest.php)
- `src/PurchaseServiceProvider.php` boot/register methods

Update root `composer.json` `autoload.psr-4` with entries for `Modules\Purchase\` and the factories/seeder namespaces. Run `composer dump-autoload` to confirm.

### FR-2 — Enums

Create the following string-backed enums under `Modules\Purchase\Enums\`:

**FR-2.1 `PurchaseSource`**: `client`, `center`, `system`
**FR-2.2 `PurchaseStatus`**: `pending`, `confirmed`, `cancelled`, `completed`
**FR-2.3 `PaymentMethod`**: `online`, `cash_on_arrival`, `manual`
**FR-2.4 `PaymentStatus`**: `pending`, `processing`, `succeeded`, `failed`, `cancelled`, `expired`

Each enum MUST cast correctly via Eloquent `casts()` on its model.

### FR-3 — Contracts & Traits

Create under `Modules\Purchase\Contracts\`:

**FR-3.1 `Buyer` interface**
- Method: `purchases(): MorphMany`

Create under `Modules\Purchase\Traits\`:

**FR-3.2 `IsBuyer` trait**
- Implements `purchases(): MorphMany` → `$this->morphMany(Purchase::class, 'buyer')`

**FR-3.3 `Purchasable` interface**
- Method: `purchaseItems(): MorphMany` (note: relationship is on PurchaseItem, not Purchase, per spec)

**FR-3.4 `IsPurchasable` trait**
- Implements `purchaseItems(): MorphMany` → `$this->morphMany(PurchaseItem::class, 'purchasable')`

Apply to existing models:
- `Modules\Centers\Models\Center` → use `IsBuyer` trait + implement `Buyer` contract
- `Modules\Clients\Models\Client` → use `IsBuyer` trait + implement `Buyer` contract

### FR-4 — Platform merchant morph class

Create `Modules\Purchase\Models\Merchant\Platform` as a simple non-Eloquent or empty-Eloquent class with no DB table that serves as the polymorphic merchant "type" for platform-level sales (Center buys membership from Platform). Register it in the service provider via `Relation::enforceMorphMap()` or `Relation::morphMap()` with key `platform`.

### FR-5 — Eloquent Models

Create models under `Modules\Purchase\Models\`:

**FR-5.1 `Purchase`**
- Fields (via migration): `id`, `buyer_type` (string), `buyer_id` (unsignedBigInteger), `merchant_type` (string), `merchant_id` (unsignedBigInteger), `source` (enum PurchaseSource), `status` (enum PurchaseStatus), `subtotal` (decimal(14,2)), `discount_amount` (decimal(14,2) default 0), `tax_amount` (decimal(14,2) default 0), `total_amount` (decimal(14,2)), `metadata` (json nullable), `created_at`, `updated_at`, `deleted_at` (softDeletes).
- Relations:
  - `buyer(): MorphTo`
  - `merchant(): MorphTo`
  - `items(): HasMany` (→ PurchaseItem)
  - `payments(): HasMany` (→ Payment)
- Casts: `source` → PurchaseSource, `status` → PurchaseStatus, `metadata` → array, money fields → decimal:2.
- Indexes: `(buyer_type, buyer_id)`, `(merchant_type, merchant_id)`, `status`, `created_at`.

**FR-5.2 `PurchaseItem`**
- Fields: `id`, `purchase_id` (foreignId → purchases.id restrictOnDelete), `purchasable_type` (string), `purchasable_id` (unsignedBigInteger), `name` (string — snapshot name from purchasable), `quantity` (unsignedInteger default 1), `unit_price` (decimal(14,2)), `subtotal` (decimal(14,2)), `discount_amount` (decimal(14,2) default 0), `tax_amount` (decimal(14,2) default 0), `total_amount` (decimal(14,2)), `metadata` (json nullable), `created_at`, `updated_at`.
- Relations:
  - `purchase(): BelongsTo`
  - `purchasable(): MorphTo`
- Casts: `metadata` → array, money fields → decimal:2.
- Indexes: `purchase_id`, `(purchasable_type, purchasable_id)`.
- Invariant: `total_amount = (unit_price * quantity) - discount_amount + tax_amount` — enforced by `CreatePurchase` action (not by DB check).

**FR-5.3 `Payment`**
- Fields: `id`, `purchase_id` (foreignId → purchases.id restrictOnDelete), `amount` (decimal(14,2)), `method` (enum PaymentMethod), `status` (enum PaymentStatus), `payment_company` (string nullable — e.g. "moyasar", "stripe"), `payment_type` (string nullable — e.g. "card", "apple_pay"), `payment_order_id` (string nullable — provider order/checkout ID), `provider_data` (json nullable), `metadata` (json nullable), `paid_at` (timestamp nullable), `created_at`, `updated_at`.
- Relations:
  - `purchase(): BelongsTo`
- Casts: `method` → PaymentMethod, `status` → PaymentStatus, `provider_data` → array, `metadata` → array, `paid_at` → datetime, `amount` → decimal:2.
- Indexes: `purchase_id`, `status`, `payment_order_id` (unique if provider guarantees global uniqueness, otherwise plain index — use plain index for flexibility).

**FR-5.4 `WebhookEvent`**
- Fields: `id`, `gateway` (string), `external_event_id` (string), `event_type` (string), `payload` (json), `received_at` (timestamp default current), `processed_at` (timestamp nullable), `failed_at` (timestamp nullable), `attempts` (unsignedInteger default 0), `created_at`, `updated_at`.
- Constraints: `unique(gateway, external_event_id)` for idempotency.
- Casts: `payload` → array, timestamps → datetime.
- Indexes: `(gateway, external_event_id)` (unique constraint covers the query), `processed_at`, `failed_at`.

### FR-6 — PurchaseServiceProvider

Implement `Modules\Purchase\PurchaseServiceProvider`:
- Load migrations from `../database/migrations`
- Register the `IsBuyer` / `IsPurchasable` morph map for existing models
- Register the `Platform` merchant morph
- Bind `PaymentGatewayManager` as a singleton (see FR-9)
- Boot event listeners if needed (for FR-10 post-commit dispatch hooks)

### FR-7 — Purchase & Payment State-Transition Services

Create under `Modules\Purchase\Services\`:

**FR-7.1 `PurchaseStateService`**
- Methods:
  - `confirm(Purchase $purchase): Purchase` — validates transition: `pending → confirmed`. Uses `lockForUpdate()` + transaction. Dispatches `PurchaseConfirmed` afterCommit.
  - `cancel(Purchase $purchase): Purchase` — validates: `pending|confirmed → cancelled`. Dispatches `PurchaseCancelled`.
  - `complete(Purchase $purchase): Purchase` — validates: `confirmed → completed`. Dispatches `PurchaseCompleted`.
- Rejects invalid transitions with a `\InvalidArgumentException` or domain exception.

**FR-7.2 `PaymentStateService`**
- Methods:
  - `markProcessing(Payment $payment): Payment` — validates: `pending → processing`. Dispatches `PaymentProcessing`.
  - `markSucceeded(Payment $payment): Payment` — validates: `pending|processing → succeeded`. Sets `paid_at = now()`. Dispatches `PaymentSucceeded`.
  - `markFailed(Payment $payment): Payment` — validates: `pending|processing → failed`. Dispatches `PaymentFailed`.
  - `cancel(Payment $payment): Payment` — validates: `pending|processing → cancelled`. Dispatches `PaymentCancelled`.
  - `expire(Payment $payment): Payment` — validates: `pending|processing → expired`. Dispatches `PaymentExpired`.
- All transitions use DB transaction + `lockForUpdate()` on the Payment row for concurrency safety.
- Invariant: `succeeded → *` never allowed (no regression from success).

### FR-8 — Application Actions

Create under `Modules\Purchase\Actions\`:

**FR-8.1 `CreatePurchase`**
- Signature: `execute(Buyer $buyer, Model $merchant, PurchaseSource $source, array $items, array $metadata = []): Purchase`
- Each item in `$items` is an associative array with keys: `purchasable` (Purchasable model), `name` (string override, optional — default from purchasable's display name), `quantity` (int, default 1), `unit_price` (decimal/numeric), `discount_amount` (numeric default 0), `tax_amount` (numeric default 0), `metadata` (array default []).
- Responsibilities:
  1. Validate buyer and merchant exist.
  2. Validate each item has a Purchasable, positive unit_price, positive quantity.
  3. Compute each item's `subtotal = unit_price * quantity`, `total_amount = subtotal - discount_amount + tax_amount`.
  4. Compute purchase totals by summing across items (subtotal, discount_amount, tax_amount, total_amount).
  5. Wrap in DB transaction: create Purchase → create each PurchaseItem.
  6. Dispatch `PurchaseCreated` afterCommit.
  7. Return Purchase with `items` loaded.

**FR-8.2 `CreatePayment`**
- Signature: `execute(Purchase $purchase, PaymentMethod $method, float|int $amount, string $paymentCompany = null, string $paymentType = null, array $metadata = []): Payment`
- Responsibilities:
  1. Validate Purchase exists (fresh check).
  2. Validate amount > 0 and `amount <= purchase->total_amount - sum of all succeeded payment amounts` (partial payments allowed IF not exceeding total — simplified for this spec: allow any positive amount, test can cover full payment).
  3. Create Payment with `status = PaymentStatus::Pending`.
  4. Dispatch `PaymentCreated` afterCommit.
  5. Return Payment with `purchase` loaded.
- Invariant: Always creates a NEW payment row. Retrying is a matter of calling `CreatePayment` again — never mutate a failed Payment's status to processing.

### FR-9 — Payment Gateway Abstraction

Create under `Modules\Purchase\Contracts\`:

**FR-9.1 `PaymentGateway` interface**
Methods:
- `initialize(Purchase $purchase, Payment $payment): array` — returns array of provider data to store (e.g. checkout URL, provider session ID). May throw on network errors.
- `verify(Payment $payment): PaymentStatus` — queries the provider and returns the resolved status.
- `capture(Payment $payment): PaymentStatus` — captures an authorized payment.
- `cancel(Payment $payment): PaymentStatus` — cancels the payment session at the provider.
- `refund(Payment $payment, $amount): bool` — issues a partial/full refund.

Create under `Modules\Purchase\Gateways\`:

**FR-9.2 `FakeGateway` (testing implementation)**
- Stores state in memory (or driver-internal array).
- `initialize()` returns `['checkout_url' => 'https://fake.example/checkout/xxx']` and sets internal state.
- `verify()` returns the current internal status.
- Supports manual state injection for tests (`FakeGateway::setNextStatus(PaymentStatus $status)`).

Create under `Modules\Purchase\Managers\`:

**FR-9.3 `PaymentGatewayManager` extends `Illuminate\Support\Manager`**
- `getDefaultDriver()`: returns config `purchase.default_gateway` (default `"fake"` for local/testing).
- `createFakeDriver()`: returns `FakeGateway` instance.
- Bind as singleton in the provider (interface-less binding of the manager itself; caller resolves via `app(PaymentGatewayManager::class)->driver('fake')`).

### FR-10 — Domain Events

Create under `Modules\Purchase\Events\`:
- `PurchaseCreated` (public Purchase $purchase)
- `PurchaseConfirmed` (public Purchase $purchase)
- `PurchaseCancelled` (public Purchase $purchase)
- `PurchaseCompleted` (public Purchase $purchase)
- `PaymentCreated` (public Payment $payment)
- `PaymentProcessing` (public Payment $payment)
- `PaymentSucceeded` (public Payment $payment)
- `PaymentFailed` (public Payment $payment)
- `PaymentCancelled` (public Payment $payment)
- `PaymentExpired` (public Payment $payment)

All events are `Illuminate\Foundation\Events\Dispatchable` + `SerializesModels` and use the service-level `DB::afterCommit(fn() => event(new Xxx(...)))` pattern to avoid listeners seeing uncommitted state.

### FR-11 — Webhook Processing Pipeline

Create under `Modules\Purchase\Services\`:

**FR-11.1 `WebhookProcessorService`**
- Method: `process(string $gateway, string $externalEventId, string $eventType, array $payload): void`
- Steps:
  1. Try to find existing `WebhookEvent` by `(gateway, external_event_id)`. If found and `processed_at IS NOT NULL`: return early (idempotency — do nothing). If found and failed → increment attempts; else create new row.
  2. Within a DB transaction with `lockForUpdate()` on the row:
     - Mark `attempts += 1`.
     - Call a `WebhookEventHandler` resolver interface (in scope: a `FakeWebhookEventHandler` that returns `PaymentStatus::Succeeded` for test event types).
     - Resolve the Payment via `payment_order_id` if provided in payload.
     - If Payment resolved: call `PaymentStateService::markSucceeded/markFailed/markCancelled/markExpire` as appropriate.
     - Set `processed_at = now()` OR `failed_at = now()` based on result.
  3. If same event is processed twice, the 2nd call is a no-op.

### FR-12 — Concurrency and Transaction Safety

All state-transition services must:
1. Open a `DB::transaction()` closure.
2. `lockForUpdate()` the target row (Payment/Purchase) before any status read-check-set.
3. Verify current status matches the allowed transition from-state.
4. Update and save.
5. Dispatch event via `DB::afterCommit()` to fire after the outermost transaction commits.

This prevents double-webhook races where `markSucceeded()` runs twice from concurrent webhook + callback requests.

---

## Non-Functional Requirements

### NFR-1 — Architecture consistency
- Follows existing modular layout: `modules/Purchase/{src,database/factories,database/migrations,tests}`.
- Uses FormRequest pattern (for future endpoints — none required now) → no inline `validate()`.
- Uses Resource classes (for future endpoints — none required now) → no raw array responses in controllers.
- Logic lives in Service classes (`PurchaseStateService`, `PaymentStateService`, `WebhookProcessorService`) and Action classes (`CreatePurchase`, `CreatePayment`) — thin/no controller.

### NFR-2 — Test coverage
- All core behaviors have passing Pest tests.
- Tests use Pest `uses(TestCase::class, RefreshDatabase::class)` pattern.

### NFR-3 — No external network calls during tests
- All payment gateway calls use `FakeGateway`.
- Webhook processor uses `FakeWebhookEventHandler`.

### NFR-4 — Scramble compatibility
- Any future controller method will need `#[Group]` and `#[Response]` attributes — documented in NFR for follow-up tasks.

---

## Acceptance Criteria

All ACs below are typed as `rule` (objective pass/fail) or `rubric` (evaluative with threshold).

### Purchase Model & Structure

- **AC-1 (rule):** Running `php artisan migrate` creates tables `purchases`, `purchase_items`, `payments`, `webhook_events` with all specified columns, indexes, and the unique constraint on `webhook_events(gateway, external_event_id)`.
- **AC-2 (rule):** `Purchase::buyer()` returns a `MorphTo` and correctly resolves a `Center` or `Client` buyer via `buyer_type`/`buyer_id`.
- **AC-3 (rule):** `Purchase::merchant()` returns a `MorphTo` and correctly resolves both a `Center` merchant and the `Platform` merchant morph.
- **AC-4 (rule):** `Purchase::items()` returns `HasMany` of `PurchaseItem`; `PurchaseItem::purchase()` returns `BelongsTo`.
- **AC-5 (rule):** `Purchase::payments()` returns `HasMany` of `Payment`; `Payment::purchase()` returns `BelongsTo`.
- **AC-6 (rule):** `PurchaseItem::purchasable()` returns a `MorphTo`; `Purchasable` models using `IsPurchasable` trait expose `purchaseItems(): MorphMany`.
- **AC-7 (rule):** All enum fields (`purchase.source`, `purchase.status`, `payment.method`, `payment.status`) correctly round-trip via Eloquent `create/find/value` using native enum objects.
- **AC-8 (rubric 0-2, ≥2 pass):** Autoload PSR-4 entries correctly registered in root `composer.json` and `composer dump-autoload` exits 0.

### Buyer trait applied to existing models

- **AC-9 (rule):** `Modules\Centers\Models\Center` implements `Modules\Purchase\Contracts\Buyer` and uses `IsBuyer` trait; `$center->purchases()` returns a `MorphMany` that creates/retrieves purchases where `buyer_type = Center::morphClass()`.
- **AC-10 (rule):** `Modules\Clients\Models\Client` implements `Modules\Purchase\Contracts\Buyer` and uses `IsBuyer` trait; same behavior as AC-9 for Client.

### CreatePurchase action

- **AC-11 (rule):** `CreatePurchase::execute(Center $buyer, Platform $merchant, PurchaseSource::Center, $items)` persists a Purchase with `buyer_type='center'` and `merchant_type='platform'`.
- **AC-12 (rule):** `CreatePurchase::execute(Client $buyer, Center $merchant, PurchaseSource::Client, $items)` persists a Purchase with correct Client/Center morphs.
- **AC-13 (rule):** Purchase totals (subtotal, discount_amount, tax_amount, total_amount) equal the sum of the corresponding item fields.
- **AC-14 (rule):** Each PurchaseItem's `total_amount` equals `(unit_price * quantity) - discount_amount + tax_amount`.
- **AC-15 (rule):** A Purchase with 3 items creates exactly 3 `purchase_items` rows, all linked via `purchase_id`.
- **AC-16 (rule):** Purchase `metadata` JSON field is cast correctly as an array (set via create, read as array).
- **AC-17 (rule):** After successful `CreatePurchase`, the `PurchaseCreated` event is dispatched exactly once and after DB commit (verifiable via `Event::fake()` + assertDispatched + checking DB state in `shouldDispatch` callback).

### CreatePayment action

- **AC-18 (rule):** `CreatePayment::execute` creates a new `Payment` row with `status = Pending` for the given Purchase.
- **AC-19 (rule):** Calling `CreatePayment` three times on the same Purchase produces 3 distinct Payment records (never overwrites).
- **AC-20 (rule):** The `PaymentCreated` event is dispatched once per successful create, afterCommit.
- **AC-21 (rule):** CreatePayment with `PaymentMethod::CashOnArrival` creates a Payment with `method = cash_on_arrival`, `status = pending`, `payment_company = null` (or nullable string as per migration).

### Purchase state transitions

- **AC-22 (rule):** `pending → confirmed` via `PurchaseStateService::confirm()` succeeds and sets status.
- **AC-23 (rule):** `confirmed → completed` via `complete()` succeeds.
- **AC-24 (rule):** `pending → cancelled` and `confirmed → cancelled` via `cancel()` succeed.
- **AC-25 (rule):** `cancel()` on an already-`completed` Purchase throws an exception (invalid transition) and does NOT mutate DB state.
- **AC-26 (rule):** Each transition dispatches its corresponding event (Confirmed/Cancelled/Completed) exactly once afterCommit.

### Payment state transitions & invariants

- **AC-27 (rule):** `pending → processing → succeeded` transitions all succeed via `PaymentStateService`.
- **AC-28 (rule):** `succeeded → failed` is rejected (throws exception) — payment cannot regress from success.
- **AC-29 (rule):** `pending → expired`, `pending → cancelled`, `pending → failed`, `processing → expired`, `processing → cancelled`, `processing → failed` all succeed.
- **AC-30 (rule):** A `succeeded` Payment has non-null `paid_at` timestamp.
- **AC-31 (rule):** Each transition dispatches its event (Processing/Succeeded/Failed/Cancelled/Expired) exactly once afterCommit.

### Critical cross-cutting scenarios

- **AC-32 (rule):** Cash-on-arrival scenario: `Purchase.status = confirmed`, single `Payment.method = cash_on_arrival`, `Payment.status = pending` — this is a valid persisted state and no service throws when loaded.
- **AC-33 (rule):** Failed payment does NOT automatically cancel Purchase: Payment #1 failed, Purchase stays in original status (pending/confirmed).
- **AC-34 (rule):** Retry scenario: Payment #1 `failed`, then create Payment #2 → Payment #2 can transit `pending → processing → succeeded`. Payment #1 row retains `failed` status unchanged.
- **AC-35 (rule):** Abandoned payment retention: A Payment created with `pending` status and never transitioned is still queryable via `Payment::find()` (no deletion, no GC).

### Gateway contract & manager

- **AC-36 (rule):** `PaymentGatewayManager::driver('fake')` returns an object implementing `PaymentGateway` (the `FakeGateway`).
- **AC-37 (rule):** `FakeGateway::initialize()` returns a non-empty array with provider data (e.g. `checkout_url`).
- **AC-38 (rule):** `PaymentGatewayManager::getDefaultDriver()` returns the configured value (default `fake`).

### Webhook idempotency

- **AC-39 (rule):** Inserting two `WebhookEvent` with identical `(gateway, external_event_id)` fails the second insert with a unique constraint violation.
- **AC-40 (rule):** `WebhookProcessorService::process()` called twice with the same `(gateway, externalEventId, eventType, payload)` performs exactly ONE payment state transition (first call succeeds; second is a no-op — verified via 2nd call not dispatching any Payment* event).
- **AC-41 (rule):** `processed_at` is set on the `WebhookEvent` row after the first successful process; second call leaves `processed_at` unchanged.

### Concurrency

- **AC-42 (rule):** When two processes race to `markSucceeded()` the same Payment concurrently, exactly ONE succeeds and the second either throws (transition validation) or is a no-op; DB never has corrupted status (no succeeded → failed regression possible in a race).

### Events

- **AC-43 (rule):** All 10 events exist under `Modules\Purchase\Events\` with `Illuminate\Foundation\Events\Dispatchable` trait and accept their target model (Purchase or Payment) via the constructor.

### Tests

- **AC-44 (rule):** Running `./vendor/bin/pest modules/Purchase/tests` exits 0 with all tests passing (or equivalently `php artisan test --filter Purchase`).
- **AC-45 (rubric 0-2, ≥2 pass):** Test coverage is comprehensive: at least one test exists for every rule-type AC numbered above (i.e. no rule AC exists without a corresponding passing test case).

### Architectural

- **AC-46 (rule):** No Purchase or Payment controller exists (the spec does not require HTTP endpoints yet); logic is via Services/Actions only.
- **AC-47 (rule):** No `Purchase` field, relationship, or global scope references `Reservation` or `Subscription` anywhere in the Purchase module files (grep for these terms in modules/Purchase/ — must return 0 matches).
- **AC-48 (rubric 0-2, ≥1 pass):** Code style matches the existing project conventions: Pest tests, `casts()` array for enums/arrays, Service constructor-injected dependencies, no `validate()` inline, no raw `$model->status = ...` assignment from outside the state services (i.e. only state services perform status write mutations).
