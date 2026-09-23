# Support Module API Layer Specification

## Problem

The Support module has `CityService` with two read methods for fetching cities but no API layer (controllers, routes, tests) to expose them over HTTP. Both client and center consumers need unauthenticated REST endpoints to list cities.

## Users

- **Client app consumers** — need only cities that have at least one VISIBLE center (for client-facing location pickers).
- **Center dashboard consumers** — need all cities (for admin-level location management).

## Goals

1. Expose `CityService::getWithCenters()` via a `GET` endpoint in `client.php` routes.
2. Expose `CityService::getAll()` via a `GET` endpoint in `center.php` routes.
3. Both endpoints are unauthenticated.
4. Follow the flow: Route → Controller → Service.
5. Document controllers using Dedoc/Scramble attributes and PHPDoc for OpenAPI generation.
6. Generate Pest tests covering success, edge cases, and response structure for both endpoints.

## Non-Goals

- No write endpoints (create/update/delete).
- No authentication/authorization middleware on these two endpoints.
- No pagination, filtering, or sorting — return flat collections as the service provides.
- No JSON Resource classes; return the raw `Collection` JSON for simplicity.
- No changes to the existing `CityService`, `City` model, or database schema.

## Functional Requirements

FR1. A `GET api/v1/...` endpoint reachable from `client.php` routes that returns cities which have at least one `VISIBLE` center, ordered by `name`.
FR2. A `GET api/v1/...` endpoint reachable from `center.php` routes that returns all cities, ordered by `name`.
FR3. The controller for the client endpoint injects `CityService` and calls `getWithCenters()`.
FR4. The controller for the center endpoint injects `CityService` and calls `getAll()`.
FR5. Both endpoints return HTTP 200 with a JSON array of city objects on success.
FR6. Both endpoints are reachable without any authentication token or session.
FR7. Each controller method is annotated with Scramble-compatible PHPDoc (summary/description) and, where applicable, `#[...]` Scramble attributes for response status/structure.
FR8. Routes are registered under the existing `api` middleware group and `api/v1` prefix via the Support module's service provider (already wired — no changes needed to service provider).

## Non-Functional Requirements

NFR1. Controllers are separated by audience in sub-directories:
  - Client-facing controllers → `Modules\Support\Http\Controllers\Client` namespace → `modules/Support/src/Http/Controllers/Client/`
  - Center-facing controllers → `Modules\Support\Http\Controllers\Center` namespace → `modules/Support/src/Http/Controllers/Center/`
  - (Admin-facing would go under `Modules\Support\Http\Controllers\Admin`, but is not in scope here.)
  All extend `App\Http\Controllers\Controller`.
NFR2. Controllers follow `StudlyCase` naming with a `Controller` suffix; action methods use a clear verb (e.g., `index`, `__invoke`).
NFR3. Route naming should be descriptive and consistent (e.g., `client.cities.index`, `center.cities.index`).
NFR4. Tests are placed in `modules/Support/tests/` and use the project's Pest setup, extending `Tests\TestCase`.
NFR5. Tests use the `RefreshDatabase` trait (or per-test DB cleanup) and factories (`CityFactory`, `CenterFactory`) to seed data.
NFR6. Tests must be runnable via the project's standard `php artisan test` (or `./vendor/bin/pest`) without extra setup beyond `.env.testing`.
NFR7. Code style follows the project's existing Laravel conventions (PSR-12, no extraneous docblocks unless required for Scramble).

## Constraints

- The project uses Laravel 13 with modular structure (modules under `/modules/*`).
- Routes are loaded via `SupportServiceProvider` under `Route::middleware(['api'])->prefix('api/v1')`.
- Scramble (`dedoc/scramble`) is installed in `composer.json` and is the required documentation tool.
- Pest is the test runner.
- The `City` model uses `spatie/laravel-translatable`; the `name` field is a translatable JSON column (`{ en: "...", ar: "..." }`).
- `Center` uses the `CenterStatus::VISIBLE` enum for filtering in `getWithCenters()`.

## Dependencies

- Existing [CityService.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Services/CityService.php) and its two read methods.
- Existing [City model](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Models/City.php), [CityFactory.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/database/factories/CityFactory.php).
- Existing [Center model](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Models/Center.php), [CenterFactory.php](file:///Users/mac/Herd/khyyal_backend/modules/Centers/database/factories/CenterFactory.php), [CenterStatus enum](file:///Users/mac/Herd/khyyal_backend/modules/Centers/src/Enums/CenterStatus.php).
- Scramble attributes/PHPDoc conventions per `https://scramble.dedoc.co/usage/`.

## Assumptions

- No existing `Http/Controllers` directory or classes under `modules/Support/src/Http/` — will be created.
- No existing `tests/` directory under `modules/Support/` — will be created.
- `composer.json` already PSR-4 autoloads `Modules\Support\` from `modules/Support/src/`; the new `Http\Controllers` sub-namespace is covered automatically.
- The `tests` namespace for modules: tests live under `modules/Support/tests/` and Pest's `pest()->extend(TestCase::class)->in('Feature')` covers the root `tests/Feature` only. Module tests must either use a dedicated `pest()` binding or the standard `Tests\TestCase` base class directly. We will add a `modules/Support/tests/Pest.php` to bind `Tests\TestCase` and `RefreshDatabase` for this directory.

## Open Questions

None at this time.

---

## Acceptance Criteria

### rule
AC1. `modules/Support/routes/client.php` defines a `GET` route pointing to a controller action that calls `CityService::getWithCenters()`.
### rule
AC2. `modules/Support/routes/center.php` defines a `GET` route pointing to a controller action that calls `CityService::getAll()`.
### rule
AC3. Both endpoints return HTTP 200 and a JSON array without any authentication (guest request succeeds).
### rule
AC4. The client endpoint returns *only* cities that have at least one center with `status = CenterStatus::VISIBLE`; cities without visible centers are excluded.
### rule
AC5. The center endpoint returns *all* cities regardless of related centers.
### rule
AC6. Results from both endpoints are ordered by city `name` (alphabetical, ascending).
### rule
AC7. Client controller lives in namespace `Modules\Support\Http\Controllers\Client` and file `modules/Support/src/Http/Controllers/Client/CityController.php`; Center controller lives in namespace `Modules\Support\Http\Controllers\Center` and file `modules/Support/src/Http/Controllers/Center/CityController.php`; both extend `App\Http\Controllers\Controller`.
### rule
AC8. Each controller method has PHPDoc summary/description and Scramble-compatible annotations so the endpoint appears in `/docs/api` with correct method/path/summary/response.
### rule
AC9. Tests exist under `modules/Support/tests/` and pass via `./vendor/bin/pest modules/Support/tests` (or equivalent). Coverage includes:
- client endpoint returns correct subset of cities.
- center endpoint returns all cities.
- ordering by name.
- response structure (200, JSON array, city fields present).
- no authentication required.
### rubric
AC10. **Structure & Conventions (0-2)**. Pass threshold ≥ 1.
  - 2: Controllers, routes, and tests exactly mirror Laravel/module conventions already in the repo; naming is consistent; no dead code.
  - 1: Functional but one or two conventions deviate (e.g., slightly inconsistent namespace depth, or route prefix that still works).
  - 0: Unusable structure, missing files, or incorrect wiring.
### rubric
AC11. **Scramble Documentation Quality (0-2)**. Pass threshold ≥ 1.
  - 2: Endpoints appear in Scramble UI with meaningful summary, description, correct 200 response shape, and no missing/incorrect info.
  - 1: Endpoints appear with summary and correct response; minor missing detail.
  - 0: Endpoints do not appear in docs or have wrong method/path.
