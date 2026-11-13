---
paths:
  - 'modules/Clients/**'
---

# Clients

## Clients are created by OTP verify or by a center
Create clients only via ClientAuthService::findOrCreateByPhone after a successful OTP verify, or via CenterClientService::create (POST centers/{center}/clients: phone must be free, the client joins center_client); both fire ClientRegistered. A client needs onboarding while first_name or last_name is null.

## Centers only see their own clients
A center sees only clients in center_client or with a Confirmed/Completed purchase where it is the merchant. Never list clients across centers. Clients are soft-deleted; raw queries must filter deleted_at.
