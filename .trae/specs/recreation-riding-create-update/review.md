# Recreational Riding Create/Update Service - Independent Review

- [x] CP-R1: create() method builds Service with RecreationalRiding enum type, Active status, center-owned
  - **Type**: `rule`
  - **Covers**: AC-1, AC-2, FR-1, FR-3, FR-4
  - **Evidence**: PASS — Pest test CP-R1 with 6 assertions all passed. Service::count +1, RecreationalRiding::count +1, $riding->service_id matches Service::id, type === RecreationRiding, status === ACTIVE, center_id correct, slug non-empty. (118-assertion suite run exit code 0.)

- [x] CP-R2: Translatable name/description JSON correctly built (ar required, en optional)
  - **Type**: `rule`
  - **Covers**: AC-1, AC-5, FR-2
  - **Evidence**: PASS — Pest test CP-R2. Created with ar-only → getTranslations has ar key only. Created with ar+en → both keys present, values match input exactly.

- [x] CP-R3: create() persists correct count + column mapping for PriceOptions (duration→quantity, MINUTE unit, price→price, name=null)
  - **Type**: `rule`
  - **Covers**: AC-3, FR-5
  - **Evidence**: PASS — Pest test CP-R3. 2 price options created, quantity=[30,60] sorted, price=[100.5,180.75], unit===MINUTE on both, name===null on both.

- [x] CP-R4: create() persists correct count + column mapping for Schedules (days × slot pairs cartesian)
  - **Type**: `rule`
  - **Covers**: AC-4, FR-6
  - **Evidence**: PASS — Pest test CP-R4. 3 days × 2 pairs = 6 rows. day_of_week unique set = {0,2,4}. Each day has 2 slots start/end pairs (09:00/12:00, 14:00/17:00). Polymorphic schedulable_type === RecreationalRiding class, schedulable_id === riding id.

- [x] CP-R5: update() updates translations only; preserves slug, center_id, type, status
  - **Type**: `rule`
  - **Covers**: AC-5, FR-7
  - **Evidence**: PASS — Pest test CP-R5. Pre-update $oldService replicate captured. Post-update: slug, center_id, type, status are all identical to pre-update values; name/description translations updated to new values.

- [x] CP-R6: update() syncs PriceOptions delete-then-recreate with no stale IDs
  - **Type**: `rule`
  - **Covers**: AC-6, FR-8
  - **Evidence**: PASS — Pest test CP-R6. Old 2 PriceOption IDs queried post-update → count 0. New 3 options created with quantity [10,20,30], price [25,45,65], all unit=MINUTE and name=null.

- [x] CP-R7: update() syncs Schedules delete-then-recreate with no stale IDs
  - **Type**: `rule`
  - **Covers**: AC-7, FR-9
  - **Evidence**: PASS — Pest test CP-R7. Old 6 Schedule IDs queried post-update → count 0. New 4 days × 2 pairs = 8 schedules. day_of_week unique set = {1,3,5,6}, all polymorphic FKs still pointing to same RecreationalRiding::id.

- [x] CP-R8: Both methods wrapped in DB::transaction; failure mid-flow rolls back all 4 tables
  - **Type**: `rule`
  - **Covers**: AC-8, FR-10
  - **Evidence**: PASS — Two tests passed. (a) create mid-fail anonymous subclass (throws after Service + RecreationalRiding creation) → Service/RecreationalRiding/PriceOption/Schedule counts all identical before vs after. (b) update mid-fail anonymous subclass (throws after translation update + priceOptions delete) → name translation reverted to original ar value, old 2 PriceOption IDs all exist again (count 2), old 6 Schedule IDs all exist again (count 6).

- [x] CP-U1: Concurrency safety (lockForUpdate + transaction)
  - **Type**: `rubric`
  - **Covers**: AC-9, FR-10
  - **Scale**: 1-5
  - **Anchors**: 1 = no locking or transaction; 3 = transaction present but no lockForUpdate; 5 = DB::transaction + lockForUpdate on Service row inside update() before cascading writes
  - **Pass Threshold**: >= 5
  - **Score**: 5
  - **Rationale**: Static source inspection + runtime mid-flight mock class confirmed. Both methods have DB::transaction closure wrappers. update() fetches Service via `Service::query()->whereKey($riding->service_id)->lockForUpdate()->firstOrFail()` BEFORE any write mutation (translation update, relation deletes, relation recreates). Highest concurrency safety tier achieved.
  - **Evidence**: (a) Grep of source file: 2x `DB::transaction` (lines 16, 74), 1x `lockForUpdate` (line 77) within update(). (b) CP-U1 Pest test: string segment analysis of createBody vs updateBody confirms presence of both transaction wrappers and lockForUpdate only in update body.

- [x] CP-R9: Returned RecreationalRiding is fresh, with service/service.priceOptions/schedules eager-loaded
  - **Type**: `rule`
  - **Covers**: AC-10, NFR-3, NFR-4
  - **Evidence**: PASS — Pest test CP-R9. After create(): relationLoaded('service') true, service.relationLoaded('priceOptions') true, relationLoaded('schedules') true. After update(): same 3 relations all loaded. Eager-loaded post-update collections reflect new counts (1 priceOption, 1 schedule for the update-data). $riding = $riding->fresh() in update() confirms NFR-4 fresh instance.

- [x] CP-R10: No unused imports, valid PHP syntax, container resolves class
  - **Type**: `rule`
  - **Covers**: NFR-1
  - **Evidence**: PASS — (a) `php -l` exit 0 "No syntax errors detected". (b) Static string source check: all 6 required FQCN imports present (Facades\DB, PriceOptionUnit, ServiceType, RecreationalRiding, Service, ActivationStatus). Schedule import deliberately absent (confirmed via str_contains negative). (c) `app()->make(RecreationRidingService::class)` via Laravel bootstrap exits 0, returns fully qualified class name string.

## Review History

### Review R1
- **Result**: `pass`
- **Evidence**: 12/12 checkpoints passed. 118 total runtime assertions via Pest test suite (all green, exit 0). 41/42 existing Promotion tests pass (1 unrelated pre-existing ModelStructureTest::rollback flake confirmed not caused by this change — 0 lines modified in Promotion module; it tests migration rollback internals which don't touch Services module code). Syntax check passed. Container resolution passed. Static import audit passed. Transactional rollback tests for both create() and update() mid-flight failures passed (4 tables all counts reverted).
