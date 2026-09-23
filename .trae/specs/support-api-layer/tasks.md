# Tasks — Support API Layer Implementation

Implementation queue for the API layer described in `spec.md`.

---

## Task 1: Create Controller Classes with Scramble Annotations

- **Status:** pending
- **Priority:** high
- **Maps to AC:** AC7, AC8, AC10, AC11

### Scope
Create two controllers separated by audience in sub-directories under `modules/Support/src/Http/Controllers/`:

1. **Client-side controller** under `Client/` sub-dir — injects `CityService`, calls `getWithCenters()`, returns JSON collection.
2. **Center-side controller** under `Center/` sub-dir — injects `CityService`, calls `getAll()`, returns JSON collection.

Both extend `App\Http\Controllers\Controller`.

### Scramble Documentation
Each public action method must include:
- PHPDoc block with `summary` (first line) and optional `description` (subsequent lines).
- At minimum: the return expression is analyzable by Scramble so the `200 OK` array-of-City schema is generated.
- Optional: `#[ResponseBody(status: 200, description: '...')]` or equivalent where useful.

### Files to Create
- `modules/Support/src/Http/Controllers/Client/CityController.php`
  - Namespace: `Modules\Support\Http\Controllers\Client`
  - Method: `index(CityService $cityService)` — returns `$cityService->getWithCenters()`
- `modules/Support/src/Http/Controllers/Center/CityController.php`
  - Namespace: `Modules\Support\Http\Controllers\Center`
  - Method: `index(CityService $cityService)` — returns `$cityService->getAll()`

Each audience (`Client`, `Center`, `Admin`) gets its own sub-namespace so controllers for different consumers never collide and are easy to find.

### Task-local Test Requirements (TR)
#### rule
TR1. `Modules\Support\Http\Controllers\Client\CityController` class exists at correct file path, extends `App\Http\Controllers\Controller`, and has an `index` method type-hinting `Modules\Support\Services\CityService`.
#### rule
TR2. `Modules\Support\Http\Controllers\Center\CityController` class exists at correct file path, extends `App\Http\Controllers\Controller`, and has an `index` method type-hinting `Modules\Support\Services\CityService`.
#### rule
TR3. `Client\CityController@index` returns the result of calling `$cityService->getWithCenters()`.
#### rule
TR4. `Center\CityController@index` returns the result of calling `$cityService->getAll()`.
#### rule
TR5. Each controller action has a PHPDoc summary and, where applicable, Scramble attributes so Scramble can generate a 200 response documentation entry for `City[]` schema.
#### rubric
TR6. **Naming & Style (0-2, pass ≥ 1)**
  - 2: Class/method names, imports, sub-namespace structure, and PHPDoc style are idiomatic and follow the audience-separation convention exactly.
  - 1: Works but minor style inconsistency.
  - 0: Wrong namespace, missing sub-directories, missing imports, or code that does not resolve.

### Completion Evidence
- File existence + content inspection.
- `php artisan route:list` (or equivalent) after routes are wired, showing the two routes reach controller actions.

---

## Task 2: Wire Client & Center Route Files

- **Status:** pending
- **Priority:** high
- **Maps to AC:** AC1, AC2, AC6, AC10

### Scope
Update the (currently empty) route files:

1. `modules/Support/routes/client.php`
   - Register `GET /cities` → `Modules\Support\Http\Controllers\Client\CityController::class, 'index'`
   - Route name: `client.cities.index`
2. `modules/Support/routes/center.php`
   - Register `GET /cities` → `Modules\Support\Http\Controllers\Center\CityController::class, 'index'`
   - Route name: `center.cities.index`

Both files are `require`d by `routes/api.php`, which itself is loaded by `SupportServiceProvider` under:
```
Route::middleware(['api'])->prefix('api/v1')->group(...)
```
So the final URLs are:
- `GET /api/v1/cities` (client) — but wait, both route files define the same path! Need to disambiguate.

**Important:** Because `api.php` requires both `client.php` and `center.php` in the same prefix, declaring identical URIs in both would collide. We must scope each sub-route file.

Option A (preferred): Add a group prefix/name prefix in each route file:
- `client.php`: wrap inside `Route::prefix('client')->name('client.')->group(...)` → final path `GET /api/v1/client/cities`, name `client.cities.index`.
- `center.php`: wrap inside `Route::prefix('center')->name('center.')->group(...)` → final path `GET /api/v1/center/cities`, name `center.cities.index`.

Option B: Use distinct paths without prefix (`/cities` vs `/all-cities`) — less clean.

We choose **Option A**.

### Task-local Test Requirements (TR)
#### rule
TR1. `client.php` registers `GET client/cities` under name `client.cities.index` pointing to `Modules\Support\Http\Controllers\Client\CityController@index`.
#### rule
TR2. `center.php` registers `GET center/cities` under name `center.cities.index` pointing to `Modules\Support\Http\Controllers\Center\CityController@index`.
#### rule
TR3. No `auth` or `auth:sanctum` middleware is attached to either route (guest access preserved).
#### rule
TR4. `php artisan route:list --path=api/v1` shows both routes with the correct method, URI, name, and controller action (incl. `Client\CityController` and `Center\CityController` in the FQCNs).

### Completion Evidence
- `route:list` output showing both endpoints.
- Sending a bare `curl` / test request returns 200 without auth headers (proven by tests in Task 3).

---

## Task 3: Create Module Test Suite (Pest)

- **Status:** pending
- **Priority:** high
- **Maps to AC:** AC3, AC4, AC5, AC6, AC9

### Scope
Create `modules/Support/tests/` directory with:

1. `Pest.php` — module-local Pest bootstrap that binds `Tests\TestCase` and `RefreshDatabase` trait to all tests in this directory.
2. `Feature/ClientCitiesIndexTest.php` — covers client endpoint.
3. `Feature/CenterCitiesIndexTest.php` — covers center endpoint.

### Test scenarios

#### ClientCitiesIndexTest (GET /api/v1/client/cities)
1. **Empty DB** → returns 200 with `[]`.
2. **Cities exist, some with VISIBLE centers, some with INVISIBLE, some with none** → only cities with at least one VISIBLE center are returned.
3. **Ordering** → returned cities are ordered by `name` ascending (test by creating cities in non-alphabetical order).
4. **Unauthenticated access** → guest request (no token) returns 200.
5. **Response structure** → each city object contains `id`, `name` (translatable: object with `en`/`ar`), `lat`, `lng`, `radius`, `created_at`, `updated_at` (or the columns present on the `cities` table per migration).

#### CenterCitiesIndexTest (GET /api/v1/center/cities)
1. **Empty DB** → returns 200 with `[]`.
2. **Mixed cities** (with/without centers, any status) → all cities are returned.
3. **Ordering** → ordered by `name` ascending.
4. **Unauthenticated access** → guest request returns 200.
5. **Response structure** matches City table columns.

### Task-local Test Requirements (TR)
#### rule
TR1. `modules/Support/tests/Pest.php` exists and extends `Tests\TestCase` with `RefreshDatabase` for tests in this directory.
#### rule
TR2. Running `./vendor/bin/pest modules/Support/tests` completes with exit code 0 and all tests passing.
#### rule
TR3. Client endpoint test: a city with only an `INVISIBLE` center is NOT in the result; a city with a `VISIBLE` center IS in the result.
#### rule
TR4. Center endpoint test: all seeded cities appear regardless of centers.
#### rule
TR5. Ordering test: in both endpoints, `assertSame` on plucked names equals `$seededNames->sort()->values()`.
#### rule
TR6. Both endpoint tests assert guest (unauthenticated) request → 200 (i.e., `actingAs` is never called and request still succeeds).
#### rubric
TR7. **Test quality (0-2, pass ≥ 1)**
  - 2: Each scenario above has a dedicated test case; factories are used; assertions are precise (structure + ordering + filtering).
  - 1: Core filtering + ordering tests exist; some edge cases covered.
  - 0: Tests do not cover the filtering behavior or do not pass.

### Completion Evidence
- Passing test run output (copy exit code and test summary).
- All individual `it(...)` or `test(...)` blocks have descriptive names.
