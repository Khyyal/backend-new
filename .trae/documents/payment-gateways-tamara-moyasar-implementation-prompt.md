# AI Agent Implementation Prompt — Tamara + Moyasar Payment Gateways

Implement two online payment gateway adapters for the existing Laravel Purchase/Payment module:

1. Tamara
2. Moyasar

Do NOT redesign the existing Purchase module. Integrate both providers into its existing contracts, models, payment state transitions, webhook events, and idempotency mechanisms.

## References

### Tamara

- https://docs.tamara.co/docs/introduction-to-tamara
- https://docs.tamara.co/reference/createcheckoutsession
- https://docs.tamara.co/reference/authoriseorder
- https://docs.tamara.co/reference/captureorder
- https://docs.tamara.co/docs/direct-online-checkout
- https://docs.tamara.co/reference/tamara-api-reference-documentation
- https://github.com/aghfatehi/laravel-tamara

### Moyasar

- https://docs.moyasar.com/
- https://docs.moyasar.com/api/api-introduction
- https://docs.moyasar.com/api/authentication
- https://docs.moyasar.com/api/payments/01-create-payment
- https://docs.moyasar.com/guides/payment-operations
- https://docs.moyasar.com/api/other/webhooks/create-webhook
- https://docs.moyasar.com/api/other/webhooks/webhook-reference
- https://docs.moyasar.com/guides/card-payments/basic-integration
- https://github.com/iabduul7/laravel-moyasar

The provider details in this prompt were verified against current provider documentation on September 26, 2026.

---

# 1. Existing Internal Model

Assume the existing module already has:

```text
Purchase
PurchaseItem
Payment
WebhookEvent
```

and internal payment concepts similar to:

```php
enum PaymentMethod: string
{
    case Online = 'online';
    case CashOnArrival = 'cash_on_arrival';
    case Manual = 'manual';
}

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

Adapt names to the actual project implementation.

The existing Payment model contains useful first-class provider fields:

```text
payment_company
payment_type
payment_order_id
```

and may have:

```text
provider_data
```

for additional provider-specific information.

Do not add Money objects/value objects.

Do not add currency to the internal Purchase/Payment models.

Provider adapters may construct provider-specific currency objects/fields at the API boundary when required by the provider.

---

# 2. Provider Abstraction

Use the project's existing `PaymentGateway` contract.

If necessary, extend it to support:

```php
interface PaymentGateway
{
    public function initialize(Payment $payment): PaymentInitializationResult;

    public function sync(Payment $payment): PaymentSyncResult;

    public function authorize(Payment $payment): AuthorizationResult;

    public function capture(Payment $payment, ?int $amount = null): CaptureResult;

    public function cancel(Payment $payment): CancelResult;

    public function refund(Payment $payment, int $amount): RefundResult;
}
```

For providers that use provider-specific terminology, translate it inside the adapter.

Provider implementations should follow:

```text
PaymentGateway
├── TamaraGateway
│   └── TamaraClient
└── MoyasarGateway
    └── MoyasarClient
```

Do not call provider SDKs/APIs directly from controllers or Purchase domain logic.

---

# 3. Provider Identification

Persist:

```text
payment_company
payment_type
payment_order_id
```

Examples:

```text
Tamara:
payment_company = tamara
payment_type = pay_by_instalments
payment_order_id = <Tamara order_id>
```

```text
Moyasar card:
payment_company = moyasar
payment_type = card
payment_order_id = <Moyasar payment id>
```

```text
Moyasar Apple Pay:
payment_company = moyasar
payment_type = apple_pay
payment_order_id = <Moyasar payment id>
```

For Moyasar, provider response data may also contain the payment source/company such as `mada`, `visa`, `master`, etc. Keep useful provider-specific details in `provider_data`.

Do not hide the primary provider ID only in JSON.

---

# 4. Idempotency

The same internal Payment attempt must not accidentally create multiple provider transactions.

Use the Payment UUID/ID as the stable application correlation key.

## Moyasar

Moyasar Create Payment supports `given_id`, which is intended for merchant-generated idempotency. Use one stable value for the same Payment attempt.

If the API request is retried due to network failure, reuse the same `given_id`.

Do not generate a new idempotency value for each retry of the same Payment attempt.

## Tamara

Tamara checkout creation uses `order_reference_id`, and the response returns a durable `order_id` and `checkout_id`.

Use a stable internal reference for the Payment/Purchase and persist all returned Tamara IDs.

---

# 5. Tamara Gateway

## Authentication

Tamara API authentication:

```http
Authorization: Bearer {API_TOKEN}
```

Base URLs:

```text
Sandbox:    https://api-sandbox.tamara.co
Production: https://api.tamara.co
```

Keep the API token server-side.

Tamara uses a separate Notification Token for webhook authentication. Do not reuse the API token for webhook verification.

---

# 6. Tamara Checkout Initialization

Use:

```http
POST /checkout
```

Tamara returns at least:

```text
order_id
checkout_id
status
checkout_url
```

Persist:

```text
Payment.payment_company = tamara
Payment.payment_type = selected provider payment type
Payment.payment_order_id = Tamara order_id
```

Store useful additional values in `provider_data`:

```json
{
  "checkout_id": "...",
  "checkout_url": "...",
  "tamara_status": "new"
}
```

Do not put `order_id` only in JSON.

---

# 7. Tamara Request Mapping

Build the Tamara checkout request from:

```text
Purchase
PurchaseItems
Buyer/customer information
Payment
```

Map internal flat numeric amounts to Tamara's required API amount objects.

Example provider-side structure:

```json
{
  "amount": 300,
  "currency": "SAR"
}
```

The internal domain remains currency-free.

The adapter owns the conversion to provider format.

Map appropriate fields such as:

```text
total_amount
shipping_amount
tax_amount
order_reference_id
order_number
items
consumer
country_code
description
merchant_url
payment_type
instalments
billing_address
shipping_address
locale
is_mobile
```

Send only fields required by the actual integration.

---

# 8. Tamara Correlation

Use stable internal references.

Recommended:

```text
order_reference_id = internal Payment UUID/reference
order_number = internal Purchase number
```

Persist:

```text
Tamara order_id
Tamara checkout_id
internal Payment UUID/reference
Purchase number
```

---

# 9. Tamara Payment Types

Tamara supports provider payment types such as:

```text
PAY_BY_INSTALMENTS
PAY_NEXT_MONTH
```

and eligibility may depend on account/country/order data.

Do not blindly hard-code one payment type if the application needs to expose provider choices.

If payment type discovery is required, add a provider-specific method/service that calls Tamara's Payment Types API and translates its response to a provider-neutral result.

---

# 10. Tamara Callback

Treat browser callbacks as navigation/user-experience events, not the final payment authority.

When the user returns:

```text
callback
    -> resolve internal Payment
    -> sync Payment with Tamara
```

Do not mark the Payment succeeded solely because the browser hit a success URL.

Tamara provides a Get Order API for server-side synchronization.

---

# 11. Tamara Webhook Authentication

Verify Tamara webhook authentication before processing.

Tamara documents a Notification Token which is:

- sent in the `tamaraToken` query parameter
- also supplied as a Bearer authorization token
- an HS256 JWT that can be validated using the merchant Notification Token

Reject invalid notifications.

Only verified webhook requests may create/update internal payment state.

---

# 12. Tamara Webhooks

Support at least:

```text
order_approved
order_declined
order_authorised
order_canceled
order_captured
order_refunded
order_expired
```

Translate provider states to internal Payment states.

Recommended mapping:

```text
Tamara new
    -> processing

Tamara approved
    -> processing

Tamara authorised
    -> succeeded

Tamara fully_captured
    -> succeeded

Tamara partially_captured
    -> succeeded
    + preserve capture details

Tamara declined
    -> failed

Tamara canceled
    -> cancelled

Tamara expired
    -> expired
```

For every event, preserve the exact provider status:

```text
provider_data.tamara_status
```

Do not discard it.

---

# 13. Tamara Authorize

Implement:

```php
TamaraGateway::authorize(Payment $payment)
```

using:

```http
POST /orders/{order_id}/authorise
```

The provider documents this operation after an `approved` webhook unless auto-authorisation is enabled.

If auto-authorisation is enabled, an `authorised` event may arrive without your application explicitly calling authorize.

Avoid duplicate authorization operations.

---

# 14. Tamara Capture

Implement:

```php
TamaraGateway::capture(...)
```

using:

```http
POST /payments/capture
```

Support full/partial capture as required by the existing Payment contract.

Preserve provider capture details in the Payment/provider data.

Tamara states that an order not captured within 21 days after authorization may be auto-captured, so reconciliation must not assume the application always performs capture itself.

---

# 15. Tamara Cancel

Implement:

```http
POST /orders/{order_id}/cancel
```

Use:

```text Payment.payment_order_id
```

as the Tamara order ID.

Only apply the internal cancellation transition when provider state supports it.

---

# 16. Tamara Refund

Implement full and partial refunds using Tamara's current refund endpoint/API.

The internal refund amount is a flat numeric amount.

Do not assume Tamara and Moyasar use identical refund request/response formats.

---

# 17. Tamara Sync

Implement:

```php
TamaraGateway::sync(Payment $payment)
```

using:

```http
GET /orders/{order_id}
```

Use sync for:

- browser callback verification
- delayed webhook recovery
- reconciliation
- manual/admin verification

The sync method must translate provider state into the internal state machine safely.

---

# 18. Moyasar Gateway

## Authentication

Moyasar API base URL:

```text
https://api.moyasar.com/v1
```

Moyasar uses API keys with HTTP Basic Authentication.

Two important keys:

### Publishable key

Used client-side for payment creation/tokenization.

### Secret key

Used server-side for privileged operations such as fetch, capture, void, refund, and related operations.

Never expose the secret key to browsers/mobile clients.

---

# 19. Moyasar Frontend Security Requirement

Do NOT create a Laravel endpoint that receives raw card PAN/CVC and forwards it to Moyasar.

Moyasar explicitly requires cardholder payment initiation/tokenization through the client-side flow using the publishable key.

Use the official Moyasar Payment Form or another provider-approved frontend integration.

Expected architecture:

```text
Frontend
    |
    | Moyasar Form / publishable key
    v
Moyasar
    |
    | payment id
    v
Application callback
    |
    v
Backend fetches payment
    |
    v
Verify provider state + amount
```

Moyasar's documentation explicitly says cardholder data must not be sent to the merchant backend.

---

# 20. Moyasar Payment Creation

Moyasar uses:

```http
POST /v1/payments
```

Supported payment sources include, depending on merchant configuration:

```text
creditcard
Apple Pay
Samsung Pay
STC Pay
card token
```

The internal Payment amount is a flat integer amount in the application's existing convention.

Moyasar expects an integer in the smallest currency unit.

Provider-specific currency information belongs only at the API boundary.

---

# 21. Moyasar Idempotency

For a single internal Payment attempt:

```text
given_id = stable Payment UUID/reference
```

Reuse it if the request is retried.

Do not create a new Moyasar payment merely because the original HTTP request timed out and the application is retrying.

---

# 22. Moyasar Persistence

When created:

```text
Payment.payment_company = moyasar
Payment.payment_order_id = Moyasar payment.id
```

Normalize payment type:

```text
creditcard -> card
Apple Pay  -> apple_pay
Samsung Pay -> samsung_pay
STC Pay    -> stc_pay
```

Store useful response details in provider data:

```json
{
  "moyasar_status": "initiated",
  "source_type": "creditcard",
  "source_company": "mada",
  "transaction_url": "...",
  "reference_number": "...",
  "authorization_code": "..."
}
```

Never store raw PAN/CVC.

---

# 23. Moyasar Status Mapping

Moyasar documents statuses including:

```text
initiated
paid
authorized
failed
refunded
captured
voided
verified
```

Recommended normalization:

```text
initiated
    -> processing

paid
    -> succeeded

authorized
    -> processing
    unless the application deliberately defines authorization as sufficient

captured
    -> succeeded

failed
    -> failed

voided
    -> cancelled

refunded
    -> refund handling

verified
    -> do not automatically mark as succeeded unless the actual payment flow requires it
```

Always preserve:

```text
provider_data.moyasar_status
```

---

# 24. Moyasar Callback Verification

The callback receives the Moyasar Payment ID.

The callback handler must:

1. Read the provider Payment ID.
2. Resolve the internal Payment using `payment_order_id`.
3. Fetch the payment from Moyasar server-side using the secret key.
4. Verify provider amount matches the internal Payment amount.
5. Verify the provider status.
6. Apply the internal state transition.
7. Return a user-appropriate result.

Never trust the callback URL alone.

Moyasar's official guidance explicitly recommends backend verification of status and amount before considering an order paid.

---

# 25. Moyasar Webhooks

Support the documented payment webhook events:

```text
payment_paid
payment_faild
payment_refunded
payment_voided
payment_authorized
payment_captured
payment_verified
```

Note: `payment_faild` is the provider's currently documented event spelling. Match the actual provider event exactly.

Translate these events into internal Payment events/states.

---

# 26. Moyasar Webhook Authentication

Moyasar webhooks are configured with:

```text
Endpoint
Secret Token
HTTP Method
Events
```

Validate the configured webhook secret before processing.

Reject unauthorized webhook requests.

Store and deduplicate the event using the existing WebhookEvent mechanism.

---

# 27. Moyasar Fetch / Sync

Implement:

```php
MoyasarGateway::sync(Payment $payment)
```

using:

```http
GET /v1/payments/{payment_id}
```

Use it for:

- callback verification
- delayed webhook recovery
- reconciliation
- manual/admin verification

Verify:

```text
provider payment exists
provider amount == internal amount
provider status maps to current internal state
```

---

# 28. Moyasar Authorization

Moyasar supports authorization-only payment creation using:

```text
manual = true
```

This results in:

```text
authorized
```

instead of an immediate `paid` status.

Implement authorization only if the application needs that flow.

Do not make every Moyasar card payment manual by default.

---

# 29. Moyasar Capture

Implement:

```http
POST /v1/payments/{id}/capture
```

Support full and partial capture.

Use:

```text
payment_order_id
```

as the provider payment ID.

Use the secret key.

Persist returned provider status and captured amount.

---

# 30. Moyasar Void

Implement:

```http
POST /v1/payments/{id}/void
```

for authorized payments where appropriate.

Provider `voided` must be mapped to the correct internal cancellation/void transition.

Do not replace a valid void operation with a refund automatically.

---

# 31. Moyasar Refund

Implement:

```http
POST /v1/payments/{id}/refund
```

Support:

- full refund
- partial refund

Do not refund more than the captured/charged amount.

Persist provider refund information.

---

# 32. Provider Data

Do not flatten away provider details.

Example:

```text
payment_company = tamara
payment_type = pay_by_instalments
payment_order_id = <Tamara order ID>
payment.status = succeeded

provider_data.tamara_status = authorised
provider_data.checkout_id = ...
```

And:

```text
payment_company = moyasar
payment_type = card
payment_order_id = <Moyasar payment ID>
payment.status = succeeded

provider_data.moyasar_status = paid
provider_data.source_company = mada
```

Common/reporting data belongs in first-class columns.

Provider-specific data belongs in provider data.

---

# 33. Refund Representation

Follow the existing application's refund implementation.

Do not overload:

```text Payment.status
```

to contain every refund detail.

If the existing module has Refund records, create them.

If it only stores aggregate payment refund information, update it consistently.

Provider-specific refund IDs/details should be retained for reconciliation.

---

# 34. Error Handling

Normalize provider failures into application-level exceptions/results.

Suggested categories:

```text
PaymentGatewayException
PaymentInitializationException
PaymentProviderUnavailableException
PaymentProviderValidationException
PaymentVerificationException
PaymentStateConflictException
```

Never expose raw provider API responses directly to clients.

Log provider diagnostics server-side with credentials and sensitive card information redacted.

---

# 35. HTTP Client

Prefer Laravel's HTTP client unless the application already has an established provider HTTP client.

Configure:

- connect timeout
- request timeout
- safe retry strategy
- structured request/response logging
- JSON handling
- provider-specific authentication
- exception normalization

Never blindly retry payment creation.

Retry provider operations only with an idempotency/correlation strategy appropriate to that operation.

---

# 36. Webhook Processing

Use provider endpoints such as:

```text
POST /webhooks/payments/tamara
POST /webhooks/payments/moyasar
```

Pipeline:

```text
HTTP request
    ↓
Authenticate provider request
    ↓
Extract provider event ID
    ↓
Store WebhookEvent
    ↓
Deduplicate
    ↓
Resolve Payment
    ↓
Translate provider event/state
    ↓
Apply internal Payment transition
    ↓
Store provider details
    ↓
Dispatch internal Payment event
```

Keep the HTTP layer thin.

If the application uses queues, persist the webhook event before queueing downstream processing.

---

# 37. Webhook Idempotency

Never assume one webhook is delivered only once.

For every provider:

```text
(gateway, external_event_id)
```

must be unique in WebhookEvent storage.

Repeated delivery must not duplicate a payment transition or trigger downstream side effects twice.

---

# 38. State Transition Safety

Payment transitions must be centralized and validated.

Do not allow arbitrary controller assignments.

Examples of invalid transitions that should be rejected:

```text
succeeded -> failed
succeeded -> processing
cancelled -> succeeded
expired -> processing
```

unless an explicit reconciliation policy supports the transition.

Provider webhooks and callbacks may arrive out of order.

The internal state machine must remain consistent.

---

# 39. Fulfillment Isolation

Do NOT implement Reservation or Subscription inside either gateway.

The gateways must only operate on Payment/Purchase concerns and emit/trigger internal payment events.

Do not call:

```text
Reservation::create()
Subscription::create()
```

from:

```text
TamaraGateway
MoyasarGateway
```

Fulfillment modules can later listen to Purchase/Payment events.

---

# 40. Tests — Shared

Test:

- gateway resolution by `payment_company`
- successful initialization
- provider ID persistence
- provider metadata persistence
- failed attempt persistence
- multiple Payment attempts for one Purchase
- safe retry behavior
- invalid state transitions
- provider exception normalization
- webhook idempotency
- provider status preservation

---

# 41. Tests — Tamara

Test:

- checkout initialization
- order ID persistence
- checkout ID persistence
- checkout URL persistence/return
- stable order reference
- approval webhook
- declined webhook
- authorized webhook
- capture webhook
- cancelled webhook
- expired webhook
- refund webhook
- webhook authentication
- duplicate webhook
- callback + sync
- get-order synchronization
- authorize
- capture
- cancel
- refund
- partial refund if supported
- provider status stored in provider data

---

# 42. Tests — Moyasar

Test:

- payment creation
- stable `given_id`
- initiated state
- paid state
- failed state
- authorized state
- captured state
- voided state
- refund
- partial refund
- callback verification
- amount mismatch rejection
- webhook authentication
- duplicate webhook
- payment fetch/sync
- authorize/capture flow
- void flow
- provider source type normalization
- provider source/company data persistence

---

# 43. Security

Mandatory:

- Never store PAN.
- Never store CVC/CVV.
- Never log secrets.
- Never expose secret provider keys client-side.
- Never trust frontend payment status.
- Verify provider payment server-side.
- Verify webhook authentication.
- Make webhook processing idempotent.
- Redact sensitive request/response fields in logs.
- Follow provider-specific client/server responsibilities.

Moyasar specifically prohibits sending cardholder data to the merchant backend.

---

# 44. Configuration

Use the application's existing configuration conventions.

A conceptual configuration:

```php
'payments' => [
    'tamara' => [
        'enabled' => env('TAMARA_ENABLED', false),
        'environment' => env('TAMARA_ENVIRONMENT', 'sandbox'),
        'api_token' => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'country' => env('TAMARA_COUNTRY', 'SA'),
        'currency' => env('TAMARA_CURRENCY', 'SAR'),
        'locale' => env('TAMARA_LOCALE', 'en_US'),
    ],

    'moyasar' => [
        'enabled' => env('MOYASAR_ENABLED', false),
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
    ],
],
```

Adapt to the project's existing configuration structure.

---

# 45. Provider Differences Must Remain Explicit

Do NOT force both APIs into a false identical lifecycle.

## Tamara

```text
checkout session
    ↓
Tamara order
    ↓
approved
    ↓
authorised
    ↓
captured
```

Tamara operates around an order/checkout identifier and has explicit authorize/capture operations.

## Moyasar

Default purchase flow:

```text
payment
    ↓
initiated
    ↓
paid
```

Optional authorization flow:

```text
payment
    ↓
authorized
    ↓
captured
```

Moyasar's frontend card flow also imposes stricter client-side handling of card data.

The shared interface should normalize capabilities, but provider-specific implementation remains inside each adapter.

---

# 46. Reference Repositories

Use these only as implementation references:

```text
https://github.com/aghfatehi/laravel-tamara
https://github.com/iabduul7/laravel-moyasar
```

Do not copy their public APIs directly into domain code.

The repository packages can help understand Laravel integration, but the application must retain its own `PaymentGateway` abstraction.

---

# 47. Implementation Order

1. Inspect the actual existing PaymentGateway contract.
2. Inspect existing Payment status transitions.
3. Inspect existing WebhookEvent/idempotency implementation.
4. Inspect existing Payment fields and casts.
5. Inspect current frontend/payment initiation architecture.
6. Implement common provider HTTP/client infrastructure if needed.
7. Implement `TamaraClient`.
8. Implement `TamaraGateway`.
9. Implement Tamara webhook endpoint/handler.
10. Implement Tamara callback/sync.
11. Implement Tamara authorize/capture/cancel/refund.
12. Implement `MoyasarClient`.
13. Implement `MoyasarGateway`.
14. Implement Moyasar frontend integration compatible with the application's frontend.
15. Implement Moyasar callback/sync.
16. Implement Moyasar webhook endpoint/handler.
17. Implement Moyasar authorize/capture/void/refund.
18. Add configuration.
19. Add tests.
20. Run static analysis and full test suite.

Do not modify unrelated modules.

---

# 48. Acceptance Criteria

The implementation is accepted only when:

- `payment_company` selects the gateway implementation.
- Tamara checkout creates a Payment and persists Tamara `order_id` in `payment_order_id`.
- Tamara `checkout_id` and checkout URL are retained.
- Tamara callback does not blindly mark payment as successful.
- Tamara webhooks are authenticated and idempotent.
- Tamara order states are safely normalized.
- Tamara authorize/capture/cancel/refund are implemented where applicable.
- Moyasar payment ID is persisted in `payment_order_id`.
- Moyasar card integration never sends raw card data through Laravel.
- Moyasar callback performs backend verification.
- Moyasar payment amount is verified against the internal amount.
- Moyasar webhook processing is authenticated and idempotent.
- Moyasar `given_id` is stable per internal Payment attempt.
- Moyasar capture/void/refund operations are implemented where applicable.
- Provider-specific state is preserved.
- Failed Payment attempts remain in the database.
- Retrying an attempt does not overwrite historical failed attempts.
- Provider secrets are never exposed or logged.
- No gateway-specific logic leaks into Purchase business logic.
- No Reservation or Subscription code is added to the gateway implementation.
