# Review — Support API Layer

Review Cycle 1 (initial) — 2026-09-23

Reviewer: independent pass (same session, read-only inspection against evidence only).

Implementation artifacts reviewed:
- [CityController.php (Client)](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Http/Controllers/Client/CityController.php)
- [CityController.php (Center)](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Http/Controllers/Center/CityController.php)
- [client.php routes](file:///Users/mac/Herd/khyyal_backend/modules/Support/routes/client.php)
- [center.php routes](file:///Users/mac/Herd/khyyal_backend/modules/Support/routes/center.php)
- [Pest.php (module)](file:///Users/mac/Herd/khyyal_backend/modules/Support/tests/Pest.php)
- [ClientCitiesIndexTest.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/tests/Feature/ClientCitiesIndexTest.php)
- [CenterCitiesIndexTest.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/tests/Feature/CenterCitiesIndexTest.php)
- [AppServiceProvider.php](file:///Users/mac/Herd/khyyal_backend/app/Providers/AppServiceProvider.php) (factory name-resolver fix)
- [SupportServiceProvider.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/SupportServiceProvider.php) (unchanged, verified)
- [CityService.php](file:///Users/mac/Herd/khyyal_backend/modules/Support/src/Services/CityService.php) (unchanged, verified)

Evidence gathered:
1. `php artisan route:list --path=api/v1 -v` — shows both routes under `api/v1/client/cities` and `api/v1/center/cities`, middleware `api` only, FQCNs include audience sub-namespaces.
2. `./vendor/bin/pest modules/Support/tests` — 10/10 passed, 51 assertions, ~1.2 s.
3. `./vendor/bin/pest` (root suite) — 2/2 passed, no regressions.
4. `php artisan scramble:export --stdout` — produced valid OpenAPI; both endpoints listed under correct groups:
   - `GET /v1/center/cities` [Center / Cities] "List all cities"
   - `GET /v1/client/cities` [Client / Cities] "List cities with visible centers"
5. `php artisan scramble:analyze` — no warnings or errors emitted.

---

## AC Checklist

| #  | Type   | Criterion | Verdict | Evidence |
|----|--------|-----------|---------|----------|
| AC1 | rule | `client.php` routes → controller calls `CityService::getWithCenters()` | ✅ PASS | client.php registers `client/cities` → `Client\CityController@index`; controller body: `return $cityService->getWithCenters()`. |
| AC2 | rule | `center.php` routes → controller calls `CityService::getAll()` | ✅ PASS | center.php registers `center/cities` → `Center\CityController@index`; controller body: `return $cityService->getAll()`. |
| AC3 | rule | Both endpoints return HTTP 200 JSON as guest | ✅ PASS | Both test suites include "accessible without authentication" tests and the empty-DB tests (guest requests return 200/`[]`). 10/10 tests pass. |
| AC4 | rule | Client endpoint returns only cities with ≥1 VISIBLE center | ✅ PASS | `includes_only_cities_with_at_least_one_visible_center` test seeds 4 cities (visible / invisible-only / mixed / none). Asserts: visible∈result ∧ mixed∈result ∧ invisible∉result ∧ none∉result. |
| AC5 | rule | Center endpoint returns all cities | ✅ PASS | `returns_all_cities_regardless_of_centers` test seeds 3 cities (visible-center / invisible-center / no-center); asserts `JsonCount(3)` and each id present. |
| AC6 | rule | Both ordered by name ascending | ✅ PASS | Each test file has `is_ordered_by_name_ascending` test: creates [Zulu, Alpha, Mike, Beta], compares returned sorted English names to PHP-sorted expected. Both pass. |
| AC7 | rule | Client controller in `Modules\Support\Http\Controllers\Client\CityController` + file path; Center controller same pattern under `Center\`; both extend `App\Http\Controllers\Controller` | ✅ PASS | File paths verified. Namespace declarations match exactly. Both `extends Controller` with `use App\Http\Controllers\Controller;`. |
| AC8 | rule | Each controller method has PHPDoc summary/description and Scramble-compatible annotations → endpoint appears in docs correctly | ✅ PASS | Scramble export shows: correct method (GET), paths `/v1/client/cities` & `/v1/center/cities`, correct group tags `Client / Cities` & `Center / Cities`, correct summaries from PHPDoc first lines. Both methods carry `#[Response(status: 200, description: '...')]` and PHPDoc `@return Collection<int, City>` with FQCN. |
| AC9 | rule | Tests under `modules/Support/tests/` pass; cover filtering / all-cities / ordering / structure / no-auth | ✅ PASS | 10 tests (5 per endpoint) covering: empty→[] (×2), filtering (client only), inclusiveness (center), ordering (×2), unauthenticated (×2), response structure with translatable `name` shape and scalar fields (×2). All pass with 51 assertions. |
| AC10 | rubric | Structure & Conventions (0-2, pass≥1). Score=2. Rationale: Controllers placed in audience sub-namespaces exactly per user correction; extend base Controller; routes use `prefix`/`name` groups consistently; FQCNs resolve via existing PSR-4; factories used via explicit FQCN (resilient); Pest per-file bindings avoid module-Pest bootstrap ambiguity. Zero dead code, no deviations. | ✅ PASS (2/2) | File structure, namespaces, route patterns, test organization all idiomatic and convention-correct. |
| AC11 | rubric | Scramble Documentation Quality (0-2, pass≥1). Score=2. Rationale: Endpoints appear in OpenAPI export with distinct audience groups, meaningful summaries, PHPDoc descriptions are captured, `#[Group]` and `#[Response]` attributes applied, return type annotation helps schema inference (Collection<City>). No warnings in `scramble:analyze`. | ✅ PASS (2/2) | Verified in scramble export output; paths, tags, summaries, statuses all correct and consistent. |

---

## Summary

Result: **pass**

- 9/9 rule ACs verified passing with concrete evidence.
- 2/2 rubric ACs scored at threshold or above (both scored 2/2).
- No actionable findings, no unknowns, no blocked checks.
- No regressions in root test suite (2/2 passing).
- Implementation satisfies every requirement in `spec.md` including the user-requested audience-separated controller sub-directories.

## Review History

| Cycle | Date | Result | Notes |
|-------|------|--------|-------|
| 1 | 2026-09-23 | pass | Initial review against spec.md. All criteria pass with evidence. No findings. |
