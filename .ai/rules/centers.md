---
paths:
  - 'modules/Centers/**'
---

# Centers

## Center state vs status
state (pending/active/blocked) is admin-controlled and gates center-scoped endpoints; status (visible/invisible) is the center's own listing visibility. Changing state, status, custom site or domain is admin-only.

## Primary user and center user management
The primary user (is_primary, owner role) implicitly holds every center permission, can never be modified, and must re-enter their password on /access. Non-primary managers cannot modify themselves and can only grant permissions they hold. Removing a user revokes their center tokens; a user with no centers left is soft-deleted.

## Purchase visibility for center staff
Non-primary users see purchases through CenterPurchaseVisibility: purchases.{type}.view, own linked trainer always, other trainers need purchases.view_other_trainers, trainer-less items need purchases.view_unassigned. Primary and system users see all.

## Revenue counts confirmed and completed only
Center revenue and analytics count only Confirmed and Completed purchases where the center is the merchant, compared to the previous same-length period. Analytics are center-wide, not narrowed by purchase visibility.
