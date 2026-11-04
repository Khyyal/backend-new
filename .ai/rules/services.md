---
paths:
  - 'modules/Services/**'
---

# Services

## Services are scoped to the route center
Filter every center endpoint by center_id and validate every cross-reference (block service_id, membership visit_service_id and packages, training trainers) against the route center.

## Service authorization by type
Authorize with Gate::authorize('manageServices', [$center, ServiceType::X]); trainers fall under Training. Center-wide blocks and gender periods require manageAllServices.

## Service writes lock and keep identity
Create/update in DB::transaction with lockForUpdate on the Service. New services start ACTIVE. type, center_id and slug never change on update.

## Price options update in place
Update duration price options by id so sold packages survive; options missing from the payload are soft-deleted with their package. One ServicePackage per price option; revive a trashed package instead of duplicating.

## Service settings, deletion and visibility
Settings are boolean rows unique per (service_id, key), limited to the model SETTINGS_KEYS. Deleting a service soft-deletes it, its serviceable and price options; history and analytics read withTrashed(). Clients only see ACTIVE services of visible, active centers.
