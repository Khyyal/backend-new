# Implementation Tasks — Purchase Module

Parent spec: [spec.md](./spec.md)

## Task 1: Module skeleton, autoload registration, and composer.json

**Status**: pending
**Priority**: high (all tasks depend on PSR-4 being registered)
**Depends on**: (none)

### Scope
1. Create directory tree:
   - `modules/Purchase/src/`
   - `modules/Purchase/database/migrations/`
   - `modules/Purchase/database/factories/`
   - `modules/Purchase/tests/Feature/`
   - `modules/Purchase/tests/Unit/`
   - `modules/Purchase/tests/Pest.php`
2. Create `modules/Purchase/src/PurchaseServiceProvider.php` (minimal — will be expanded in later tasks).
3. Create `modules/Purchase/tests/Pest.php` mirroring the existing module pattern:
   ```php
   <?php
   use Illuminate\Foundation\Testing\RefreshDatabase;
   use Tests\TestCase;
   uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/Feature');
   uses(TestCase::class, RefreshDatabase::class)->in(__DIR__.'/Unit');
   ```
4. Update root `composer.json` `autoload.psr-4` section to add:
   - `"Modules\\Purchase\\": "modules/Purchase/src/",`
   - `"Modules\\Purchase\\Database\\Factories\\": "modules/Purchase/database/factories/",`
   - `"Modules\\Purchase\\Database\\Seeders\\": "modules/Purchase/database/seeders/",`
5. Run `composer dump-autoload` and confirm exit code 0.
6. Register `PurchaseServiceProvider::class` in `bootstrap/providers.php` so Laravel boots it.

### Test Requirements (local)
- **TR-1.1 (rule)**: `composer dump-autoload` exits 0.
- **TR-1.2 (rule)**: `class_exists(Modules\Purchase\PurchaseServiceProvider::class)` returns true in a tinker-style test.
- **TR-1.3 (rule)**: `bootstrap/providers.php` includes `PurchaseServiceProvider::class` in its return array.

### References
- [composer.json autoload](file:///Users/mac/Herd/khyyal_backend/composer.json#L34-L51)
- [CentersServiceProvider](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/CentersServiceProvider.php)

---

## Task 2: Enums — PurchaseSource, PurchaseStatus, PaymentMethod, PaymentStatus

**Status**: pending
**Priority**: high
**Depends on**: Task 1

### Scope
Create under `modules/Purchase/src/Enums/`:

1. `PurchaseSource.php` — string enum: `case Client = 'client'`, `case Center = 'center'`, `case System = 'system'`
2. `PurchaseStatus.php` — string enum: `case Pending = 'pending'`, `case Confirmed = 'confirmed'`, `case Cancelled = 'cancelled'`, `case Completed = 'completed'`
3. `PaymentMethod.php` — string enum: `case Online = 'online'`, `case CashOnArrival = 'cash_on_arrival'`, `case Manual = 'manual'`
4. `PaymentStatus.php` — string enum: `case Pending = 'pending'`, `case Processing = 'processing'`, `case Succeeded = 'succeeded'`, `case Failed = 'failed'`, `case Cancelled = 'cancelled'`, `case Expired = 'expired'`

### Test Requirements
- **TR-2.1 (rule)**: All 4 enums are `BackedEnum` instances and each `->value` matches the string per spec.
- **TR-2.2 (rule)**: Each enum has no extra cases beyond the spec (exact case sets).

### References
- [CenterStatus enum pattern](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Enums/CenterStatus.php)

---

## Task 3: Contracts & Traits — Buyer, Purchasable, IsBuyer, IsPurchasable

**Status**: pending
**Priority**: high
**Depends on**: Task 1, Task 2

### Scope
Create:
1. `modules/Purchase/src/Contracts/Buyer.php` interface:
   ```php
   interface Buyer { public function purchases(): MorphMany; }
   ```
2. `modules/Purchase/src/Traits/IsBuyer.php`:
   ```php
   public function purchases(): MorphMany { return $this->morphMany(Purchase::class, 'buyer'); }
   ```
3. `modules/Purchase/src/Contracts/Purchasable.php`:
   ```php
   interface Purchasable { public function purchaseItems(): MorphMany; }
   ```
4. `modules/Purchase/src/Traits/IsPurchasable.php`:
   ```php
   public function purchaseItems(): MorphMany { return $this->morphMany(PurchaseItem::class, 'purchasable'); }
   ```
5. Apply to existing models:
   - Edit `modules/Centers/src/Models/Center.php`: add `implements Buyer` and `use IsBuyer`; add the proper `use` imports.
   - Edit `modules/Clients/src/Models/Client.php`: same.

### Test Requirements
- **TR-3.1 (rule)**: `Center::class` implements `Buyer` interface (via `instanceof` check).
- **TR-3.2 (rule)**: `Client::class` implements `Buyer` interface.
- **TR-3.3 (rule)**: `$center->purchases()` returns a `MorphMany` with `morphTo` relation name `buyer` (check `$center->purchases()->getForeignKeyName()` etc.).
- **TR-3.4 (rule)**: A dummy `IsPurchasable` model correctly exposes `purchaseItems(): MorphMany` with relation `purchasable`.

### References
- [Actionable trait pattern](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Concerns/Actionable.php)

---

## Task 4: Platform merchant morph class & PurchaseServiceProvider boot

**Status**: pending
**Priority**: high
**Depends on**: Task 1, Task 2, Task 3

### Scope
1. Create `modules/Purchase/src/Models/Merchant/Platform.php`:
   - A plain class (or `Model` without DB table, using a `protected $table = null` override) that can be used as polymorphic merchant morph target.
   - Use `Illuminate\Database\Eloquent\Model` with `public $incrementing = false; protected $keyType = 'string';` and hardcode `getKey()` to return `'platform'` so `merchant_type='platform', merchant_id=1` or similar works consistently.
2. Update `PurchaseServiceProvider::boot()`:
   - `$this->loadMigrationsFrom(__DIR__.'/../database/migrations');`
   - Register morph map: `Relation::morphMap(['platform' => Platform::class, 'center' => Center::class, 'client' => Client::class]);`
   - Bind `PaymentGatewayManager::class` as singleton (stub now — Task 13 will flesh it out).

### Test Requirements
- **TR-4.1 (rule)**: `Relation::getMorphedModel('platform')` returns `Platform::class` after provider boot.
- **TR-4.2 (rule)**: A `Purchase` with `merchant_type = 'platform'` can resolve its merchant via `$purchase->merchant` returning a `Platform` instance without errors.

---

## Task 5: Database migrations — purchases, purchase_items, payments, webhook_events

**Status**: pending
**Priority**: high
**Depends on**: Task 1, Task 2

### Scope
Create 4 migrations in `modules/Purchase/database/migrations/` with filenames following the pattern `YYYY_MM_DD_HHMMSS_create_<name>_table.php`. Use timestamps after existing migrations (e.g., `2026_09_26_000001_`).

**Migration 1 — purchases**:
Columns: `id`, `buyer_type` (string), `buyer_id` (unsignedBigInteger), `merchant_type` (string), `merchant_id` (unsignedBigInteger), `source` (enum from PurchaseSource values), `status` (enum from PurchaseStatus values, default pending), `subtotal` (decimal(14,2)), `discount_amount` (decimal(14,2) default 0), `tax_amount` (decimal(14,2) default 0), `total_amount` (decimal(14,2)), `metadata` (json nullable), `timestamps`, `softDeletes`.
Indexes: `(buyer_type, buyer_id)`, `(merchant_type, merchant_id)`, `status`, `created_at`.

**Migration 2 — purchase_items**:
Columns: `id`, `purchase_id` (foreignId → purchases restrictOnDelete), `purchasable_type` (string), `purchasable_id` (unsignedBigInteger), `name` (string), `quantity` (unsignedInteger default 1), `unit_price` (decimal(14,2)), `subtotal` (decimal(14,2)), `discount_amount` (decimal(14,2) default 0), `tax_amount` (decimal(14,2) default 0), `total_amount` (decimal(14,2)), `metadata` (json nullable), `timestamps`.
Indexes: `purchase_id`, `(purchasable_type, purchasable_id)`.

**Migration 3 — payments**:
Columns: `id`, `purchase_id` (foreignId → purchases restrictOnDelete), `amount` (decimal(14,2)), `method` (enum from PaymentMethod), `status` (enum from PaymentStatus, default pending), `payment_company` (string nullable), `payment_type` (string nullable), `payment_order_id` (string nullable), `provider_data` (json nullable), `metadata` (json nullable), `paid_at` (timestamp nullable), `timestamps`.
Indexes: `purchase_id`, `status`, `payment_order_id` (non-unique index).

**Migration 4 — webhook_events**:
Columns: `id`, `gateway` (string), `external_event_id` (string), `event_type` (string), `payload` (json), `received_at` (timestamp default current), `processed_at` (timestamp nullable), `failed_at` (timestamp nullable), `attempts` (unsignedInteger default 0), `timestamps`.
Unique constraint: `unique(gateway, external_event_id)`.
Indexes: `processed_at`, `failed_at`.

### Test Requirements
- **TR-5.1 (rule)**: `php artisan migrate --database=sqlite_testing` (or test DB) exits 0.
- **TR-5.2 (rule)**: All 4 tables exist with correct columns and column types (inspect via `Schema::getColumnListing()`).
- **TR-5.3 (rule)**: Inserting 2 rows with same `(gateway, external_event_id)` into `webhook_events` throws unique constraint exception on the second insert.
- **TR-5.4 (rule)**: `migrate:rollback` drops all 4 tables in reverse order without errors.

### References
- [Centers migration pattern](file:///Users/mac/Herd/khyyal_backend/modules/Centers/database/migrations/2026_09_22_114915_create_centers_table.php)

---

## Task 6: Eloquent Models — Purchase, PurchaseItem, Payment, WebhookEvent

**Status**: pending
**Priority**: high
**Depends on**: Task 2, Task 3, Task 4, Task 5

### Scope
Create in `modules/Purchase/src/Models/`:

1. **`Purchase.php`**:
   - `use HasFactory;`, `use SoftDeletes;`
   - `fillable`: `buyer_type, buyer_id, merchant_type, merchant_id, source, status, subtotal, discount_amount, tax_amount, total_amount, metadata`
   - `casts()`: `source` → PurchaseSource, `status` → PurchaseStatus, `metadata` → `array`, `subtotal/discount/tax/total_amount` → `decimal:2`
   - Relations: `buyer()` morphTo, `merchant()` morphTo, `items()` hasMany PurchaseItem, `payments()` hasMany Payment.

2. **`PurchaseItem.php`**:
   - `use HasFactory;`
   - `fillable`: `purchase_id, purchasable_type, purchasable_id, name, quantity, unit_price, subtotal, discount_amount, tax_amount, total_amount, metadata`
   - `casts()`: `metadata` → `array`, money fields → `decimal:2`, `quantity` → `integer`
   - Relations: `purchase()` belongsTo Purchase, `purchasable()` morphTo.

3. **`Payment.php`**:
   - `use HasFactory;`
   - `fillable`: `purchase_id, amount, method, status, payment_company, payment_type, payment_order_id, provider_data, metadata, paid_at`
   - `casts()`: `method` → PaymentMethod, `status` → PaymentStatus, `provider_data` → `array`, `metadata` → `array`, `paid_at` → `datetime`, `amount` → `decimal:2`
   - Relations: `purchase()` belongsTo Purchase.

4. **`WebhookEvent.php`**:
   - `use HasFactory;`
   - `fillable`: `gateway, external_event_id, event_type, payload, received_at, processed_at, failed_at, attempts`
   - `casts()`: `payload` → `array`, timestamps → `datetime`, `attempts` → `integer`

### Test Requirements
- **TR-6.1 (rule)**: `Purchase::create([...])` with valid buyer/merchant morphs saves correctly and `$p->buyer` resolves the morph.
- **TR-6.2 (rule)**: `$purchase->items()->create([...])` and `$purchase->payments()->create([...])` work; `$purchase->items->count()` and `$purchase->payments->count()` reflect it.
- **TR-6.3 (rule)**: Setting `$purchase->status = PurchaseStatus::Confirmed` and saving round-trips the enum (`find()` returns the enum object).
- **TR-6.4 (rule)**: `PurchaseItem::purchasable` MorphTo resolves a test Purchasable model correctly when persisted with `purchasable_type`/`purchasable_id`.

---

## Task 7: Factories — PurchaseFactory, PurchaseItemFactory, PaymentFactory, WebhookEventFactory

**Status**: pending
**Priority**: medium
**Depends on**: Task 6

### Scope
Create in `modules/Purchase/database/factories/` using the Faker pattern, extending `Illuminate\Database\Eloquent\Factories\Factory`, each with `$model` property and `definition()` method.

1. **`PurchaseFactory`**: Needs a buyer (default: `ClientFactory::new()`) and merchant (default: morph to Platform). Generates random source/status/totals.
2. **`PurchaseItemFactory`**: Requires a purchase_id; generates a test purchasable; name from faker words; quantity 1-5; unit_price random decimal; computes subtotal/discount/tax/total.
3. **`PaymentFactory`**: Requires a purchase_id; random amount; random method; status = Pending by default; fills payment_company/payment_type if method=Online.
4. **`WebhookEventFactory`**: Random gateway string; unique external_event_id per call; event_type random; payload JSON with random data.

Also, configure the factories in each Model via `protected static function newFactory()` (Laravel 11+ pattern).

### Test Requirements
- **TR-7.1 (rule)**: `PurchaseFactory::new()->hasItems(2)->hasPayments(1)->create()` successfully persists a Purchase with 2 items and 1 payment.
- **TR-7.2 (rule)**: Each factory's `definition()` generates valid data that passes `create()` without throwing.

---

## Task 8: Domain Events (10 event classes)

**Status**: pending
**Priority**: medium
**Depends on**: Task 6

### Scope
Create in `modules/Purchase/src/Events/`:
1. `PurchaseCreated`
2. `PurchaseConfirmed`
3. `PurchaseCancelled`
4. `PurchaseCompleted`
5. `PaymentCreated`
6. `PaymentProcessing`
7. `PaymentSucceeded`
8. `PaymentFailed`
9. `PaymentCancelled`
10. `PaymentExpired`

Each event uses `Dispatchable`, `SerializesModels`; has a single public property `$purchase` (or `$payment`) typed to its model; constructor accepts the model and assigns it.

### Test Requirements
- **TR-8.1 (rule)**: All 10 event classes exist and `class_exists(...)` = true.
- **TR-8.2 (rule)**: `event(new PurchaseCreated($purchase))` fires correctly with `Event::fake()` + `assertDispatched(PurchaseCreated::class)`.

---

## Task 9: PurchaseStateService and PaymentStateService

**Status**: pending
**Priority**: high
**Depends on**: Task 2, Task 6, Task 8

### Scope
Create under `modules/Purchase/src/Services/`:

1. **`PurchaseStateService.php`**:
   - Inject `DB` facade (or use `DB::transaction()`).
   - `confirm(Purchase $purchase): Purchase` — transaction + `lockForUpdate()`; checks `$purchase->status === PurchaseStatus::Pending`; throws on invalid; updates status to Confirmed; dispatches `PurchaseConfirmed` via `DB::afterCommit()`.
   - `cancel(Purchase $purchase): Purchase` — allows `Pending|Confirmed → Cancelled`.
   - `complete(Purchase $purchase): Purchase` — allows `Confirmed → Completed`.

2. **`PaymentStateService.php`**:
   - `markProcessing(Payment $payment): Payment` — `Pending → Processing` + dispatch `PaymentProcessing`.
   - `markSucceeded(Payment $payment): Payment` — `Pending|Processing → Succeeded` + set `paid_at = now()` + dispatch `PaymentSucceeded`.
   - `markFailed(Payment $payment): Payment` — `Pending|Processing → Failed` + dispatch `PaymentFailed`.
   - `cancel(Payment $payment): Payment` — `Pending|Processing → Cancelled` + dispatch `PaymentCancelled`.
   - `expire(Payment $payment): Payment` — `Pending|Processing → Expired` + dispatch `PaymentExpired`.
   - Invariant: any from-state of `Succeeded` on any transition throws `\InvalidArgumentException` (no regressions).

Both services load the row fresh inside the transaction using `$this->purchaseRepository` or inline `Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail()`.

### Test Requirements
- **TR-9.1 (rule)**: `confirm()` on pending Purchase succeeds; calling again throws; PurchaseConfirmed event dispatched.
- **TR-9.2 (rule)**: `markSucceeded()` on a Succeeded Payment throws (no regression) and DB status unchanged.
- **TR-9.3 (rule)**: After `markSucceeded()`, `$payment->paid_at` is non-null and a Carbon instance.
- **TR-9.4 (rule)**: `cancel()` on already-completed Purchase throws; purchase status stays `completed` in DB.
- **TR-9.5 (rule)**: Every valid transition dispatches exactly 1 matching event (use `Event::fake()`).

---

## Task 10: CreatePurchase and CreatePayment application actions

**Status**: pending
**Priority**: high
**Depends on**: Task 2, Task 3, Task 6, Task 8, Task 9 (soft dep on 9 for type-safety but not run-time)

### Scope
Create under `modules/Purchase/src/Actions/`:

1. **`CreatePurchase.php`**:
   - Constructor-inject no external deps (pure action + facades).
   - `execute(Buyer $buyer, Model $merchant, PurchaseSource $source, array $items, array $metadata = []): Purchase`
   - Validation:
     - Each `$item` must contain `purchasable` (instance of `Purchasable`), `unit_price` (>0), `quantity` (>0 int).
     - `name` default from `$purchasable->name` (fallback: $item['name']).
     - Compute per-item: `subtotal = unit_price * quantity`, `total_amount = subtotal - discount_amount + tax_amount`.
     - Sum across items into Purchase totals.
   - Wrap in `DB::transaction()`.
   - Return with `->load('items')`.
   - Dispatch `PurchaseCreated` via `DB::afterCommit()`.

2. **`CreatePayment.php`**:
   - `execute(Purchase $purchase, PaymentMethod $method, $amount, ?string $paymentCompany = null, ?string $paymentType = null, array $metadata = []): Payment`
   - Validation: `$amount > 0`; if method=Online, paymentCompany is recommended not required (make optional).
   - Create Payment row with `status = PaymentStatus::Pending`.
   - Dispatch `PaymentCreated` via `DB::afterCommit()`.
   - Return with `->load('purchase')`.

### Test Requirements
- **TR-10.1 (rule)**: `CreatePurchase` with Client buyer + Center merchant + 2 items persists Purchase with correct totals.
- **TR-10.2 (rule)**: Item totals: `total_amount = unit_price * qty - discount + tax` (precise to 2 decimals).
- **TR-10.3 (rule)**: Two calls to `CreatePayment` on the same Purchase produce 2 Payment rows.
- **TR-10.4 (rule)**: PurchaseCreated and PaymentCreated are dispatched (use `Event::fake()`); asserting their payload references the correct model.

---

## Task 11: PaymentGateway interface and FakeGateway

**Status**: pending
**Priority**: medium
**Depends on**: Task 2, Task 6

### Scope
1. Create `modules/Purchase/src/Contracts/PaymentGateway.php`:
   ```php
   interface PaymentGateway {
       public function initialize(Purchase $purchase, Payment $payment): array;
       public function verify(Payment $payment): PaymentStatus;
       public function capture(Payment $payment): PaymentStatus;
       public function cancel(Payment $payment): PaymentStatus;
       public function refund(Payment $payment, $amount): bool;
   }
   ```
2. Create `modules/Purchase/src/Gateways/FakeGateway.php` implementing `PaymentGateway`:
   - `initialize()` returns `['checkout_url' => 'https://fake.example/checkout/'.Str::random(16)]`.
   - Internal `static array $nextStatus = []` + static setter `setNextStatus(string $paymentId, PaymentStatus $s)`.
   - `verify()` returns the next status for the payment or `PaymentStatus::Pending` if none set.
   - `capture()` = same pattern returning Succeeded by default.
   - `cancel()` = Cancelled.
   - `refund()` = true.

### Test Requirements
- **TR-11.1 (rule)**: `FakeGateway::initialize()` returns non-empty array with `checkout_url` key.
- **TR-11.2 (rule)**: After `FakeGateway::setNextStatus($pId, PaymentStatus::Succeeded)`, `verify()` returns Succeeded.

---

## Task 12: PaymentGatewayManager (Laravel Manager pattern)

**Status**: pending
**Priority**: medium
**Depends on**: Task 11, Task 4

### Scope
Create `modules/Purchase/src/Managers/PaymentGatewayManager.php`:
- Extends `Illuminate\Support\Manager`.
- Method `getDefaultDriver(): string` returns `config('purchase.default_gateway', 'fake')`.
- Method `createFakeDriver(): PaymentGateway` returns a `new FakeGateway()`.
- Bind as singleton in `PurchaseServiceProvider::register()`: `$this->app->singleton(PaymentGatewayManager::class, fn() => new PaymentGatewayManager($this->app))`.

Add a default config file `modules/Purchase/config/purchase.php` with `['default_gateway' => env('PURCHASE_GATEWAY', 'fake')]`, and register the config in the provider with `$this->mergeConfigFrom(...)`.

### Test Requirements
- **TR-12.1 (rule)**: `app(PaymentGatewayManager::class)->driver('fake')` returns a `PaymentGateway` instance.
- **TR-12.2 (rule)**: `getDefaultDriver()` returns `'fake'` in testing environment.

---

## Task 13: WebhookEvent model + WebhookProcessorService

**Status**: pending
**Priority**: medium
**Depends on**: Task 6, Task 9, Task 12

### Scope
Create:
1. A resolver contract and handler stub:
   - `modules/Purchase/src/Contracts/WebhookEventHandler.php` interface: `handle(WebhookEvent $event): ?array` returns `['payment_order_id' => string, 'payment_status' => PaymentStatus]` or null if no payment action.
   - `modules/Purchase/src/Gateways/FakeWebhookEventHandler.php`: returns the payment_status from `$event->payload['status']` keyed as 'payment_status' and `$event->payload['payment_order_id']` as 'payment_order_id'.
2. `modules/Purchase/src/Services/WebhookProcessorService.php`:
   - Constructor: takes a callable/array map `$handlers = ['fake' => FakeWebhookEventHandler::class]`.
   - `process(string $gateway, string $externalEventId, string $eventType, array $payload): void`:
     Step 1: `firstOrCreate` the WebhookEvent by `(gateway, external_event_id)` — DB transaction with `lockForUpdate()`.
     Step 2: If `$event->processed_at !== null`: return (no-op idempotency).
     Step 3: Increment `attempts`. Save.
     Step 4: Resolve handler for gateway. If not found, mark `failed_at` and return.
     Step 5: Call handler. If result has `payment_order_id` + `payment_status`: find Payment by `payment_order_id`, call `PaymentStateService` method matching the status (map Succeeded → markSucceeded etc.).
     Step 6: Set `processed_at = now()` OR `failed_at` on exception. Save.

### Test Requirements
- **TR-13.1 (rule)**: Two sequential `process()` calls with same event ID result in exactly one Payment status transition (Event::fake on PaymentSucceeded → count === 1).
- **TR-13.2 (rule)**: WebhookEvent row has `processed_at` set after first success; second call doesn't change `processed_at` timestamp.
- **TR-13.3 (rule)**: Handler returning payment_order_id that does not exist still sets `failed_at` and increments attempts.

---

## Task 14: Full Feature Test Suite (Pest)

**Status**: pending
**Priority**: high (verifies all rule-type ACs)
**Depends on**: Tasks 2-13

### Scope
Create test files covering every rule-type AC from spec.md:

A. `tests/Unit/CreatePurchaseTest.php` — AC-11 through AC-17.
B. `tests/Unit/CreatePaymentTest.php` — AC-18 through AC-21.
C. `tests/Unit/PurchaseStateServiceTest.php` — AC-22 through AC-26.
D. `tests/Unit/PaymentStateServiceTest.php` — AC-27 through AC-31 + AC-34 (retry) + AC-33 (failed not cancel purchase).
E. `tests/Unit/PaymentGatewayTest.php` — AC-36 through AC-38.
F. `tests/Feature/WebhookIdempotencyTest.php` — AC-39 through AC-41.
G. `tests/Feature/PurchaseScenariosTest.php` — AC-32 (COA confirmed+pending), AC-33, AC-34 (retry), AC-35 (abandoned payment).
H. `tests/Unit/ConcurrencyTest.php` — AC-42 (use `DB::pretend()` or two sequential lock-for-update calls to verify no regression).
I. `tests/Unit/EnumTest.php` — enum round-trip + correct case sets (AC-7).
J. `tests/Unit/ModelStructureTest.php` — migration verification (AC-1).

Each test should:
- Use `uses(TestCase::class, RefreshDatabase::class)`.
- Use `Event::fake()` for event assertions.
- Use existing `ClientFactory` and `CenterFactory` + `CityFactory` setup (mirror existing module test `beforeEach` for city creation).

### Test Requirements
- **TR-14.1 (rule)**: Running `./vendor/bin/pest modules/Purchase/tests` exits with 0 and green tests.
- **TR-14.2 (rubric 0-2, ≥2 pass)**: Every rule-typed AC in spec.md has at least one corresponding test file (by name/purpose) that asserts its pass condition.

### References
- [Existing Unit test pattern](file:///Users/mac/Herd/khyyal_backend/modules/Centers/tests/Unit/CenterRegisterServiceTest.php)

---

## Task 15: Integration check, final polish, and cross-module consistency

**Status**: pending
**Priority**: medium
**Depends on**: Tasks 1-14

### Scope
1. Run `php artisan migrate:fresh` on test DB, ensure all migrations run.
2. Grep `modules/Purchase/src` for references to Reservation/Subscription — should be zero.
3. Confirm no Purchase/Payment controllers exist under the module (spec excludes HTTP layer).
4. Run `php artisan test` for the entire project to ensure no existing tests regress (especially Centers 123 tests passing).
5. Ensure `PurchaseServiceProvider` boots and morph map is correct (dump `Relation::morphMap()` via `dd()` in a test).
6. Run `composer dump-autoload` one more time to confirm no stale PSR-4 entries.

### Test Requirements
- **TR-15.1 (rule)**: Full project test suite `php artisan test` passes (no regressions in Centers, Clients, Support tests).
- **TR-15.2 (rule)**: `grep -r 'Reservation\|Subscription' modules/Purchase/src` returns no matches.
- **TR-15.3 (rule)**: No file matching `*Purchase*Controller*` exists under modules/Purchase/src/.

---

## Task dependencies summary (partial order)

```
Task 1 (bootstrap)
 ├─→ Task 2 (enums)
 ├─→ Task 5 (migrations)
 │     └─→ Task 6 (models)
 │           ├─→ Task 7 (factories)
 │           ├─→ Task 8 (events)
 │           ├─→ Task 9 (state services)
 │           ├─→ Task 10 (actions)
 │           ├─→ Task 13 (webhook service)
 │           └─→ Task 14 (full test suite)
 ├─→ Task 3 (contracts/traits + apply to Center/Client)
 ├─→ Task 4 (Platform merchant + provider boot → gates for 12)
 ├─→ Task 11 (gateway interface + FakeGateway)
 └─→ Task 12 (GatewayManager)

All must complete → Task 15 (final polish + integration)
```
