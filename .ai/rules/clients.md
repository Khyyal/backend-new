---
paths:
  - 'modules/Clients/**'
---

# Clients

## Clients are created only by OTP verify
Create clients only via ClientAuthService::findOrCreateByPhone after a successful OTP verify, firing ClientRegistered. A client needs onboarding while first_name or last_name is null.

## Centers only see their own clients
A center sees only clients in center_client or with a Confirmed/Completed purchase where it is the merchant. Never list clients across centers. Clients are soft-deleted; raw queries must filter deleted_at.
