# Backend architecture, as it affects API inspection

Snapshot verified against the code at commit `2a33048` (2026-11-13). Counts and lists drift; re-derive anything a conclusion depends on. The hand-written equivalents are `docs-site/dev/architecture/*.md`, `docs-site/dev/api/*.md`, `docs-site/dev/conventions/permissions.md` and `.ai/rules/modules.md`.

## Stack

Laravel 13 on PHP 8.4, served by Octane (RoadRunner). Sanctum for tokens. Spatie: query-builder (filters and sorts), permission, medialibrary, translatable, activitylog, settings. Scribe for the endpoint reference. Pest for tests. Payment gateways: Moyasar and Tamara.

## Layout

`app/` holds almost nothing: `AppServiceProvider`, a base controller and legacy-migration console commands. `routes/web.php` has only the welcome route. All API code is in `modules/<Module>/`:

```
modules/<Module>/
  routes/            admin.php, center.php, client.php, api.php, partials/ (varies per module)
  src/
    <Module>ServiceProvider.php   routes, policies, events, middleware aliases, rate limiters, schedule
    Http/Controllers/{Admin,Center,Client}/
    Http/Requests/   Http/Resources/   Http/Middleware/
    Actions/         single use case, public execute()
    Services/        the main home of business logic
    Models/  Enums/  Policies/  Events/  Listeners/  Jobs/  Exceptions/  Notifications/
  database/  lang/{en,ar}/  config/  tests/{Feature,Unit}/
```

Modules: Analytics, Billing, Centers, Clients, Exports, Homepage, Notifications, Promotions, Purchase, Reservations, Search, Services, Settlements, Support. Providers are listed in `bootstrap/providers.php`.

Not every module has every layer. `Actions/` exists in only a few modules (Reservations, Purchase, Settlements, Analytics); most logic sits in `Services/` (about 120 classes against about 30 actions). Do not assume a Route → Controller → Action shape: read the controller method to see what it calls.

## Route registration

Each service provider mounts its own route files, usually:

```php
Route::middleware(['api'])->prefix('api/v1')->group(__DIR__.'/../routes/api.php');
```

- A module's `routes/api.php` often just `require`s `center.php`, `admin.php`, `client.php`, which in turn `require` files in `routes/partials/`.
- **Shared partials**: the same partial is mounted twice, under `centers/{center}/…` (`auth:center_user`, `center.scope`) and under `admin/centers/{center}/…` (`auth:system_user`). One controller method therefore serves two routes with different guards. Any change to it affects both surfaces.
- **Billing is the exception**: `routes/admin.php` and `routes/center.php` are required directly and land on `api/admin/*` and `api/center/*` without `v1`; only `admin_subscriptions.php` is under `api/v1`. Billing also uses route-level `can:` middleware, which other modules do not.
- Versioning is the `api/v1` prefix only. There is no version negotiation and no second version.

On 2026-11-13 the table had 504 API routes: about 258 admin, 163 center, 49 client, plus media, webhooks and the Billing routes. Get current numbers from `route:list`.

## Authentication

Guards (`config/auth.php`), all Sanctum bearer tokens:

| Guard | Model | Surface |
|---|---|---|
| `client` | `Modules\Clients\Models\Client` | `clients/*` |
| `center_user` | `Modules\Centers\Models\User` | `centers/*` |
| `system_user` | `Modules\Support\Models\SystemUser` | `admin/*` |
| `system_user_web` (session) | `SystemUser` | `/docs`, `/guide` only |

Sign-in: clients and center users by phone OTP; system users by email and password. A center user then calls `POST centers/{center}/access` to get a token carrying the ability `center-access:{id}`, issued by `CenterAuthService::issueCenterAccessToken()`. Some client browse routes are public. Webhooks are public and verified by signature. `POST media` accepts several guards.

## Middleware to recognise

| Alias | Class | Effect |
|---|---|---|
| `center.scope` | `Modules\Centers\Http\Middleware\EnsureCenterAccessScope` | Token must have `center-access:{id}`; center must be Active (403 otherwise, except route `centers.show`). Does **not** check membership or permissions. |
| `idempotent` | `Modules\Support\Http\Middleware\EnsureIdempotentRequest` | Requires `Idempotency-Key` header. 400 `idempotency_key_required`, 422 `idempotency_key_reused`, 409 `idempotency_request_in_progress` with `Retry-After`. A stored 2xx is replayed with `Idempotent-Replayed: true`. Keys are scoped to the authenticated actor. |
| `set.locale.from.accept.language` | `Modules\Clients\Http\Middleware\SetLocaleFromAcceptLanguage` | Locale from `Accept-Language` (en, ar) on client routes. |
| `track.client.session` | `Modules\Clients\Http\Middleware\TrackClientSession` | On authenticated client routes. |
| `throttle:<name>` | Named limiters `coupon`, `home-events`, `engagement-events`, `voxa`, defined with `RateLimiter::for` in module providers | 429 |

Aliases are registered in `CentersServiceProvider`, `ClientsServiceProvider` and `SupportServiceProvider`.

## Authorization

Tenant isolation is by center, enforced in three separate places. Check each one for every center endpoint:

1. `center.scope` middleware (token ability and center state), on center routes only. The admin mirror routes do not have it.
2. A policy check: `Gate::forUser($request->user())->authorize('<ability>', $center)` in a private controller helper, or `Gate::…->allows(…)` in the FormRequest's `authorize()`. Some methods rely on the request alone, some on the controller helper, some on both. A method with neither is a finding.
3. Query scoping: lookups are written as `Model::query()->where('center_id', $center->getKey())->findOrFail($id)`, often in a private `find()` helper. Child ids are taken as plain `int` route parameters and resolved through the parent (`$reservation->invoices()->findOrFail($invoice)`), not by route-model binding. An unscoped `findOrFail` on a center route is a finding.

There is no tenancy package and no global scope: isolation depends on each query being written correctly.

Policies (about 23, bound with `Gate::policy` in providers) accept `User|SystemUser`:

- Center users: active assignment to the center plus a `CenterUserPermission`. The primary user holds all of them.
- System users: a `SystemPermission` (`resource.action`). Every admin policy denies inactive system users in `before()`.
- `CenterPolicy` (`modules/Centers/src/Policies/CenterPolicy.php`) holds most center abilities and maps each to both permission sets.
- Service endpoints authorize per service type: `Gate::authorize('manageServices', [$center, ServiceType::X])`.
- Purchase lists are further narrowed by `CenterPurchaseVisibility`.

Client endpoints authorize by ownership: queries go through the authenticated client's relations or filter on the client id.

## Requests

- FormRequests in `Http/Requests/{Admin,Center,Client}/`, with `authorize()` and `rules()`. Request-body docs are `#[BodyParam]` attributes on the class.
- Some controller methods validate inline with `$request->validate([...])` instead (common for query parameters). Check both.
- Shared rule sets live in `Http/Requests/Concerns/` traits.
- Enums are validated with `Rule::enum(X::class)`; accepted values are the enum's cases.
- Client file uploads never arrive on the endpoint itself: the client uploads to `POST media` first and sends temporary media ids, which are promoted by `TemporaryMediaService::promote()` / `promoteMany()`. Every media failure is one 422 `InvalidTemporaryMediaException`.

## Lists: filtering, sorting, pagination

`Spatie\QueryBuilder\QueryBuilder::for($query, $request)` in the controller method:

- `->allowedFilters(...)` defines `filter[name]`; `AllowedFilter::exact`, `::callback`, `::scope`.
- `->allowedSorts(...)` and `->defaultSort(...)` define `sort` (`-field` for descending).
- `->paginate(N)->withQueryString()`; the page size is a literal in the method.
- An unknown filter or sort is rejected by the package (400).

The accepted query parameters are exactly what these calls list, plus `page`. `#[QueryParam]` attributes describe them for Scribe and can lag behind.

## Responses

- `JsonResource` classes return under `data`; paginated collections add `links` and `meta`.
- Extra top-level keys come from `->additional([...])` (for example `checkout` beside `data`).
- Status codes other than 200 are set explicitly: `->response()->setStatusCode(201)`.
- Some endpoints return a hand-built `JsonResponse` instead of a resource. Read the return statement.
- Fields depend on eager loads. Controllers keep the relation list in a `RELATIONS` constant; a relation missing there yields `null` or an extra query, not an error.
- Translatable fields: client resources usually return the current locale's string, center and admin resources the full `{en, ar}` object via `getTranslations()`. Check per resource.
- `#[ResponseField]` attributes on `toArray()` document each field. Scribe examples are built from these, not from models, so they can drift from the array actually returned.

## Errors

`bootstrap/app.php` renders JSON for `api/*` and for requests that expect JSON. There is no custom global envelope.

| Status | Source | Body |
|---|---|---|
| 400 | `idempotent` middleware; invalid filter or sort | `{message, code}` for idempotency |
| 401 | guard | `{message}` |
| 403 | `center.scope`, policy, FormRequest `authorize()` | `{message}` |
| 404 | `findOrFail`, route binding | `{message}` |
| 409 | idempotency in progress; a few domain rules | |
| 422 | validation | `{message, errors}` |
| 422 | domain exceptions with their own `render()` | `{message, reason, errors}` with a stable `reason` key |
| 429 | throttle | |

Domain exceptions that render themselves: `ReservationNotAllowedException` and `PriceChangedException` (Reservations), `CouponRejectedException` and `PromotionUnavailableException` (Promotions), `CenterDeletionNotAllowedException` (Centers). Purchase has a family of `Payment*Exception` classes and Billing `SubscriptionIneligibleException`; check how each is caught or rendered where it is thrown. The `reason` values are translation keys under the module's `lang/` files; list them by following the `::because('<reason>')` calls reachable from the endpoint.

Canonical example bodies for docs are in `Modules\Support\Documentation\ApiExamples`.

## Side effects

- State changes go through `*StateService` classes inside `DB::transaction`, often with `lockForUpdate()`.
- Domain events fire in `DB::afterCommit`. Listeners are wired by hand in the listening module's service provider, so the effects of an event are spread across modules (for example `PurchaseConfirmed` is handled by Reservations and Billing). Money and notification listeners are queued.
- Notifications extend `AppNotification` and go through `NotificationServiceContract` (database plus FCM; WhatsApp for some).
- Scheduled commands are registered in service providers and change state outside any request (releasing unpaid bookings, expiring packages, reminders).
- Caching is limited: home content (`HomeContentCache`), active promotions (`ActivePromotionRepository`), center search and a few `Center` model lookups. Elsewhere responses are computed per request.
- Octane keeps the process alive between requests: static or singleton state that holds request data is a bug worth reporting.

## Existing API documentation

| Source | Location | Notes |
|---|---|---|
| Scribe attributes | On controllers (`#[Group]`, `#[Endpoint]`, `#[UrlParam]`, `#[QueryParam]`, `#[Header]`, `#[Response]`, `#[ResponseFromApiResource]`), requests (`#[BodyParam]`), resources (`#[ResponseField]`) | Closest to the code, still hand-written |
| Scribe intermediate files | `.scribe/endpoints/*.yaml` | Committed; as fresh as the last `scribe:generate` |
| OpenAPI and Postman output | `storage/app/private/scribe/openapi.yaml`, `collection.json` | Git-ignored; may be absent or stale |
| `api.json` at the project root | OpenAPI 3.1, 156 paths, last committed 2026-11-03 | Stale against the route table; do not rely on it |
| Developer guides | `docs-site/dev/domain/<module>.md`, `docs-site/dev/api/*.md` | Business rules per module; the project requires them to be updated with every behaviour change |
| Feature tests | `modules/<Module>/tests/Feature/` | Executable evidence of status codes and payloads |

The served reference is `/docs` (Scribe) and `/guide` (VitePress build), both behind the `system_user_web` session and the `api_docs.view` permission.
