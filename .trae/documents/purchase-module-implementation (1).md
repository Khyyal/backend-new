# Purchase Module --- Implementation Specification

## 1. Objective

Implement a Laravel Purchase module that represents commercial purchases
independently from fulfillment.

The module must support:

1.  Center purchases a membership plan from the platform.
2.  Client purchases a service/product from a center.
3.  Center can create a purchase on behalf of a client.
4.  Payments can be online or paid when the client arrives.
5.  Online payment attempts can remain pending/processing and must be
    tracked.
6.  Multiple payment attempts can belong to one purchase.
7.  Payment providers are hidden behind interfaces.
8.  Payment provider webhooks are supported and idempotent.
9.  Purchase must not depend on Reservation or Subscription
    implementation.
10. Fulfillment is explicitly OUT OF SCOPE for this module.

------------------------------------------------------------------------

# 2. Architectural Principle

The core model is:

``` text
Purchase
├── PurchaseItems
├── Payments
└── Buyer / Merchant
```

Do NOT model:

``` text
Purchase -> Reservation
Purchase -> Subscription
```

inside this module.

Reservation and Subscription are downstream business concepts and will
consume purchase/payment events later.

The Purchase module must never decide whether a fulfillment record is
visible, active, confirmed, cancelled, etc.

------------------------------------------------------------------------

# 3. Domain Concepts

## 3.1 Purchase

A Purchase represents the commercial transaction/agreement.

Examples:

### Center buys membership

``` text
buyer    = Center
merchant = Platform
item     = Membership Plan
```

### Client buys service

``` text
buyer    = Client
merchant = Center
item     = Service
```

### Center creates purchase for client

``` text
buyer    = Client
merchant = Center
source   = center
```

The fact that the center created the purchase does NOT make the center
the buyer.

------------------------------------------------------------------------

# 4. Buyer

Create a `Buyer` contract and `Buyer` Laravel trait.

Example:

``` php
interface Buyer
{
    public function purchases(): MorphMany;
}
```

The exact interface can be adapted to the existing project's
conventions.

The trait should provide:

``` php
public function purchases(): MorphMany
{
    return $this->morphMany(Purchase::class, 'buyer');
}
```

Any model capable of buying must implement/use the buyer abstraction.

Do not hard-code `User` as the buyer.

Possible buyers include:

-   Client
-   Center
-   Future buyer types

------------------------------------------------------------------------

# 5. Purchasable

Create a `Purchasable` contract and Laravel trait.

The purchasable represents something that can be sold.

Examples:

-   Membership Plan
-   Service
-   Future products/packages/add-ons

The purchasable relationship belongs on `PurchaseItem`, not directly on
`Purchase`.

Example:

``` php
interface Purchasable
{
    public function purchases(): MorphMany;
}
```

The trait should provide the corresponding relationship.

The exact pricing API should follow the existing application's
money/currency conventions.

------------------------------------------------------------------------

# 6. Merchant

Purchase must explicitly identify the seller/merchant.

Do NOT assume that the merchant is always the platform.

Examples:

``` text
Center buys Membership Plan

buyer:
    Center

merchant:
    Platform
```

``` text
Client buys Service

buyer:
    Client

merchant:
    Center
```

Use a polymorphic relationship:

``` text
merchant_type
merchant_id
```

This makes the purchase model usable for both platform sales and center
sales.

------------------------------------------------------------------------

# 7. Purchase Source

A purchase may be initiated by different actors.

Add a source/origin field.

Suggested enum:

``` php
enum PurchaseSource: string
{
    case Client = 'client';
    case Center = 'center';
    case System = 'system';
}
```

Meaning:

-   `client`: client initiated the purchase.
-   `center`: center created the purchase for the client.
-   `system`: system-generated purchase.

Important:

`source` describes who initiated the purchase.

It does NOT describe the buyer.

Example:

``` text
source = center
buyer  = Client
merchant = Center
```

------------------------------------------------------------------------

# 8. Purchase Status

Purchase status must NOT mean payment status.

Do not use Purchase status as a proxy for whether payment succeeded.

Suggested enum:

``` php
enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
```

Definitions:

### pending

The purchase has been initialized but has not yet reached the
application's confirmation condition.

### confirmed

The purchase is commercially accepted/confirmed.

Confirmation is a Purchase business decision and must NOT be defined as
simply:

`text payment.status == succeeded`

For example, a cash-on-arrival purchase can be:

``` text
Purchase:
    confirmed

Payment:
    pending
    method = cash_on_arrival
```

### cancelled

The purchase has been explicitly cancelled.

### completed

The purchase's commercial lifecycle is complete.

Do not introduce fulfillment-specific states here.

------------------------------------------------------------------------

# 9. Important Payment Rule

Payment state is independent from Purchase state.

Valid example:

``` text
Purchase:
    confirmed

Payment:
    pending
    method = cash_on_arrival
```

This is a required use case.

Therefore:

``` text
Purchase confirmed
≠
Payment succeeded
```

and:

``` text
Payment failed
≠
Purchase failed
```

A failed payment means that a particular payment attempt failed.

It does NOT automatically mean that the purchase itself failed.

------------------------------------------------------------------------

# 10. PurchaseItem

Do not store a single `purchasable_type` / `purchasable_id` directly on
Purchase.

Use:

``` text
Purchase
    |
    +-- PurchaseItem
            |
            +-- Purchasable
```

This allows a purchase to contain multiple items in the future.

Suggested fields:

``` text
id
purchase_id

purchasable_type
purchasable_id

name
quantity

unit_price
subtotal
discount_amount
tax_amount
total_amount

metadata

created_at
updated_at
```

## Price snapshot

PurchaseItem must store the price at purchase time.

Never calculate historical purchase amounts from the current purchasable
price.

For example:

``` text
Service current price = 150
PurchaseItem unit_price = 100
```

The purchase remains 100 even after the service price changes.

------------------------------------------------------------------------

# 11. Money

Do NOT use a Money value object in this module.

Use flat numeric amount fields.

There is no currency field in the Purchase or Payment models because the application currently operates with a single currency.

Use the project's existing database convention for monetary precision:

- Prefer integer minor units if that is the existing application convention.
- Otherwise use an appropriate fixed-precision decimal column.
- Never use floating-point columns for financial amounts.

Examples:

```text
Purchase:
    subtotal = 10000
    discount_amount = 500
    tax_amount = 0
    total_amount = 9500

Payment:
    amount = 9500
```

Do not add:

```text
Money object
Money value object
```

unless the application later becomes multi-currency.

# 12. Payment

Payment is a payment attempt/record associated with a Purchase.

One Purchase can have many Payments.

Example:

```text
Purchase #100

Payment #1
    payment_company = moyasar
    payment_type = card
    payment_order_id = ORD-123
    status = failed

Payment #2
    payment_company = moyasar
    payment_type = card
    payment_order_id = ORD-124
    status = expired

Payment #3
    payment_company = moyasar
    payment_type = card
    payment_order_id = ORD-125
    status = succeeded
```

Never overwrite failed attempts.

This history is required for auditing, reconciliation, support, reporting, and retry handling.

Relationship:

```php
$purchase->payments()
```

is the complete payment history.

## Payment Provider Information

Store useful provider information directly on Payment.

At minimum:

```text
payment_company
payment_type
payment_order_id
```

Example:

```text
payment_company = moyasar
payment_type = card
payment_order_id = ORD-123
```

Semantics:

- `payment_company`: external payment provider/company, e.g. `moyasar`.
- `payment_type`: provider/payment instrument type, e.g. `card`, `apple_pay`, `stc_pay`, `paypal`.
- `payment_order_id`: provider order/transaction/checkout identifier used to reconcile the internal Payment with the provider.

These are first-class queryable columns. Do NOT hide these values only inside an opaque JSON field.

Provider-specific additional data may still be stored in:

```text
provider_data
```

but common/relevant information must remain queryable through dedicated columns.

# 13. Payment Method

Payment method is separate from payment status and payment provider type.

Suggested enum:

``` php
enum PaymentMethod: string
{
    case Online = 'online';
    case CashOnArrival = 'cash_on_arrival';
    case Manual = 'manual';
}
```

The exact set can be extended later.

Do NOT create a status such as:

``` text
pay_on_arrival
```

`pay_on_arrival` is a payment method, not a payment state.

------------------------------------------------------------------------

# 14. Payment Status

Suggested enum:

``` php
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
```

Definitions:

### pending

Payment has been created but processing has not started or payment is
intentionally waiting.

Example:

``` text
cash_on_arrival
```

### processing

An online provider is processing the payment.

### succeeded

Payment was successfully completed.

### failed

This payment attempt failed.

### cancelled

The payment attempt was explicitly cancelled.

### expired

The payment session/attempt expired.

------------------------------------------------------------------------

# 15. Cash on Arrival

Cash on arrival must NOT be treated as a failed or incomplete purchase.

Example:

``` text
Purchase
    status = confirmed

Payment
    method = cash_on_arrival
    status = pending
```

When cash is received:

``` text
Payment
    pending -> succeeded
```

The Purchase does not need to transition merely because payment
succeeded unless the business rule explicitly requires it.

The payment transition must be handled through the Payment
application/domain service, not by mutating the database directly from a
controller.

------------------------------------------------------------------------

# 16. Online Payment Flow

Example:

``` text
Create Purchase
        |
        v
Create Payment
        |
        v
PaymentGateway::initialize()
        |
        v
Provider checkout/session
        |
        +---- processing
        |
        +---- webhook
                  |
                  v
             Payment update
```

A payment initialization that does not complete must remain in the
database.

Example:

``` text
Purchase #100

Payment #1
    method = online
    gateway = stripe
    status = pending
    provider_reference = pi_xxx
```

If the customer abandons checkout, the Payment remains recorded.

It can later become:

``` text
expired
```

according to the provider/application expiration policy.

Do NOT delete abandoned payment records.

------------------------------------------------------------------------

# 17. Payment Gateway Contract

Define an interface independent of Stripe/PayPal/etc.

Suggested shape:

``` php
interface PaymentGateway
{
    public function initialize(
        Purchase $purchase,
        Payment $payment,
    ): PaymentInitialization;

    public function verify(
        Payment $payment,
    ): PaymentResult;

    public function capture(
        Payment $payment,
    ): PaymentResult;

    public function cancel(
        Payment $payment,
    ): PaymentResult;

    public function refund(
        Payment $payment,
        Money $amount,
    ): RefundResult;
}
```

Adapt method names/types to the project's conventions.

The Purchase domain must not contain provider-specific logic.

Avoid code such as:

``` php
if ($gateway === 'stripe') {
    ...
}
```

inside Purchase services.

------------------------------------------------------------------------

# 18. Gateway Resolution

Use Laravel's service container / gateway resolver.

Example conceptual API:

``` php
$gateway = $paymentGatewayManager->driver($payment->gateway);
```

Provider implementations:

``` text
PaymentGateway
├── StripeGateway
├── PaypalGateway
└── ...
```

Do not couple the Purchase model to a specific provider.

------------------------------------------------------------------------

# 19. Webhooks

Payment webhooks belong to the payment integration layer.

Routes should be provider-specific, for example:

``` text
POST /webhooks/payments/{gateway}
```

The webhook handler must:

1.  Verify provider signature.
2.  Parse provider payload.
3.  Identify the provider event ID.
4.  Store the webhook event.
5.  Guarantee idempotency.
6.  Resolve the internal Payment.
7.  Translate the provider event to an internal payment result/state.
8.  Update Payment.
9.  Dispatch the appropriate internal event.

------------------------------------------------------------------------

# 20. Webhook Event Storage

Create a `webhook_events` table.

Suggested fields:

``` text
id

gateway
external_event_id
event_type

payload

received_at
processed_at
failed_at

attempts

created_at
updated_at
```

Required unique constraint:

``` text
unique(gateway, external_event_id)
```

The same provider event must never be processed twice.

Webhook processing must be idempotent.

------------------------------------------------------------------------

# 21. Internal Payment Events

Provider-specific webhook names must not leak into the Purchase domain.

Translate them into internal events such as:

``` php
PaymentSucceeded
PaymentFailed
PaymentCancelled
PaymentExpired
PaymentRefunded
```

Example:

``` text
Stripe:
payment_intent.succeeded
        |
        v
PaymentSucceeded
```

The rest of the application should not care whether Stripe, PayPal, or
another provider generated the event.

------------------------------------------------------------------------

# 22. Payment Lifecycle

Conceptually:

``` text
                 ┌────────────┐
                 │   pending  │
                 └─────┬──────┘
                       │
                       v
                 ┌────────────┐
                 │ processing │
                 └─────┬──────┘
                       │
                 ┌─────┴──────┐
                 v            v
             succeeded      failed
                               |
                               v
                             retry
```

Other terminal transitions:

``` text
pending/processing -> cancelled
pending/processing -> expired
```

Do not allow arbitrary status changes from controllers.

Centralize payment transitions.

------------------------------------------------------------------------

# 23. Purchase Lifecycle

Purchase lifecycle must be handled independently.

Conceptually:

``` text
pending
   |
   v
confirmed
   |
   v
completed
```

and:

``` text
pending -> cancelled
confirmed -> cancelled
```

The exact allowed transitions should be enforced in one place.

Do not implement purchase state transitions through scattered controller
assignments such as:

``` php
$purchase->status = 'confirmed';
$purchase->save();
```

Use an application/domain action or state transition service.

------------------------------------------------------------------------

# 24. Payment Does Not Determine Purchase Failure

This rule is mandatory.

Incorrect:

``` php
if ($payment->failed()) {
    $purchase->status = PurchaseStatus::Cancelled;
}
```

A payment failure is only a failed payment attempt.

The customer may retry:

``` text
Purchase #100

Payment #1 -> failed
Payment #2 -> pending
Payment #2 -> succeeded
```

The Purchase remains the same Purchase throughout the retry process.

------------------------------------------------------------------------

# 25. No Global Purchase Confirmation Scope

Do NOT implement a global Eloquent scope on Reservation or any other
future fulfillment model that says:

``` text
purchase.status == confirmed
```

The Purchase module must not enforce fulfillment visibility.

Fulfillment modules will decide their own lifecycle/visibility rules.

This specification intentionally does not implement or define
Reservation or Subscription behavior.

------------------------------------------------------------------------

# 26. Suggested Eloquent Models

## Purchase

``` php
class Purchase extends Model
{
    public function buyer(): MorphTo
    {
        return $this->morphTo();
    }

    public function merchant(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
```

Use casts for enums and appropriate money/value objects.

------------------------------------------------------------------------

## PurchaseItem

``` php
class PurchaseItem extends Model
{
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

------------------------------------------------------------------------

## Payment

``` php
class Payment extends Model
{
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
```

Payment should contain the provider-specific reference and provider data
necessary for reconciliation.

------------------------------------------------------------------------

# 27. Database Constraints

At minimum:

### purchases

Indexes:

``` text
buyer_type + buyer_id
merchant_type + merchant_id
status
created_at
```

### purchase_items

Indexes:

``` text
purchase_id
purchasable_type + purchasable_id
```

### payments

Indexes:

``` text
purchase_id
status
provider_reference
```

If a provider reference is globally unique within the provider, enforce
the appropriate unique constraint.

### webhook_events

Mandatory:

``` text
unique(gateway, external_event_id)
```

------------------------------------------------------------------------

# 28. Purchase Creation

Create an application action such as:

``` php
CreatePurchase
```

Responsibilities:

1.  Validate buyer.
2.  Validate merchant.
3.  Validate purchasables.
4.  Calculate price.
5.  Snapshot item information.
6.  Create Purchase.
7.  Create PurchaseItems.
8.  Optionally create the initial Payment through a separate payment
    action.

Do not put all of this in an Eloquent model.

------------------------------------------------------------------------

# 29. Payment Creation

Create a separate action:

``` php
CreatePayment
```

Responsibilities:

1.  Validate Purchase.
2.  Validate amount.
3.  Validate currency.
4.  Validate payment method.
5.  Resolve gateway if online.
6.  Create Payment record.
7.  Initialize external payment when appropriate.
8.  Store provider reference/session data.
9.  Return payment initialization data to the caller.

The external gateway call should be designed carefully around
transaction boundaries.

Do not hold a database transaction open while waiting unnecessarily for
a remote provider.

------------------------------------------------------------------------

# 30. Retrying Payment

Retrying must create a new Payment record.

Correct:

``` text
Purchase
├── Payment #1 failed
└── Payment #2 processing
```

Incorrect:

``` text
Payment #1
status changed:
failed -> processing
```

The old attempt is valuable audit information and must remain immutable
except for controlled provider metadata/reconciliation fields.

------------------------------------------------------------------------

# 31. Reconciliation

The system should be able to query a provider later if webhook delivery
is delayed or lost.

The `PaymentGateway::verify()` operation exists for this purpose.

Example:

``` text
Payment:
    processing

Webhook never arrived

Background reconciliation:
    gateway->verify(payment)

Provider says:
    succeeded

Payment:
    succeeded
```

This is especially important for online payments.

------------------------------------------------------------------------

# 32. Transactions and Concurrency

Payment state transitions must be concurrency-safe.

A webhook and a user callback can arrive at nearly the same time.

Do not blindly perform:

``` php
$payment->status = ...
$payment->save();
```

without considering current state and idempotency.

Use:

-   database transactions where appropriate
-   row locking for critical transitions
-   state transition validation
-   idempotent webhook processing

Do not allow:

``` text
succeeded -> failed
```

because a late webhook says something contradictory unless a deliberate
reconciliation process handles it.

------------------------------------------------------------------------

# 33. Events to Expose

The Purchase module should expose domain/application events such as:

``` php
PurchaseCreated
PurchaseConfirmed
PurchaseCancelled
PurchaseCompleted

PaymentCreated
PaymentProcessing
PaymentSucceeded
PaymentFailed
PaymentCancelled
PaymentExpired
```

Other modules can listen to these events.

For example, a future Membership module can listen to:

``` text
PurchaseConfirmed
PaymentSucceeded
```

according to its own business rules.

The Purchase module must not directly instantiate:

``` text
Subscription
Reservation
```

------------------------------------------------------------------------

# 34. After Commit

Events that cause other modules to react to persisted Purchase/Payment
state should be dispatched after the database transaction commits where
appropriate.

This prevents listeners from seeing state that has not committed yet.

Use Laravel's transaction-aware event/listener facilities rather than
manually guessing transaction timing.

------------------------------------------------------------------------

# 35. Testing Requirements

Implement tests for at least:

## Purchase

-   Create purchase for Client.
-   Create purchase for Center.
-   Center creates purchase for Client.
-   Correct buyer is persisted.
-   Correct merchant is persisted.
-   Source is persisted.
-   Purchase items snapshot prices.
-   Multiple purchase items work.

## Payment

-   Create online payment.
-   Create cash-on-arrival payment.
-   Payment remains pending.
-   Payment can transition to processing.
-   Payment can succeed.
-   Payment can fail.
-   Payment can expire.
-   Multiple payment attempts are retained.

## Important scenarios

### Cash on arrival

``` text
Purchase = confirmed
Payment = pending
Method = cash_on_arrival
```

must be valid.

### Failed online attempt

``` text
Purchase exists
Payment #1 = failed
```

must not automatically cancel/fail the Purchase.

### Retry

``` text
Payment #1 = failed
Payment #2 = succeeded
```

must be valid.

### Abandoned payment

``` text
Payment = pending/processing
```

must remain persisted and traceable.

### Webhook idempotency

Sending the same webhook twice must result in one effective state
transition.

### Webhook race

Webhook and client callback arriving concurrently must not corrupt
Payment state.

------------------------------------------------------------------------

# 36. Explicit Non-Goals

Do NOT implement these in the Purchase module:

-   Reservation lifecycle
-   Subscription lifecycle
-   Reservation visibility
-   Subscription activation
-   No-show/attendance
-   Service scheduling
-   Appointment availability
-   Membership entitlement
-   Fulfillment visibility
-   Fulfillment-specific global scopes

The Purchase module only emits the events and exposes the data required
by those modules.

------------------------------------------------------------------------

# 37. Reference Package

The following repository may be used as inspiration, not as the
architecture to copy:

https://github.com/amzad78692/laravel-purchasable

Useful concepts to inspect:

-   Buyer trait
-   Purchasable trait
-   purchase/order lifecycle
-   transaction/payment history
-   payment gateway abstraction
-   pending transactions
-   refunds
-   webhook handling

Do not blindly reproduce its model because this application has an
explicit buyer/merchant relationship and supports multiple payment
attempts plus cash-on-arrival.

------------------------------------------------------------------------

# 38. Implementation Order

Implement in this order:

1.  Enums
2.  Buyer/Purchasable contracts and traits
3.  Purchase model + migration
4.  PurchaseItem model + migration
5.  Payment model + migration
6.  WebhookEvent model + migration
7.  Purchase creation action
8.  Payment creation action
9.  Payment state transition service
10. PaymentGateway contract
11. Gateway manager/resolver
12. Webhook contract and processing pipeline
13. Idempotency
14. Events
15. Tests
16. Integration with actual payment provider(s)

Do not implement Reservation or Subscription as part of this task.

------------------------------------------------------------------------

# 39. Final Invariants

The implementation must preserve these rules:

\`\`\`text 1. A Purchase has a buyer. 2. A Purchase has a merchant. 3. A
Purchase has one or more PurchaseItems. 4. A Purchase can have many
Payment attempts. 5. A Payment belongs to exactly one Purchase. 6.
Payment method and payment status are different concepts. 7.
Cash-on-arrival is a valid pending payment. 8. Failed payment does not
automatically mean failed Purchase. 9. Payment retries create new
Payment records. 10. Initialized/incomplete payments are retained. 11.
Webhooks are idempotent. 12. Provider-specific events do not leak into
the core domain. 13. Fulfillment is outside the Purchase module. 14.
Purchase status is not payment status. 15. No fulfillment visibility may
depend on a Purchase global scope.
