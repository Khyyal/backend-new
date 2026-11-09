# Authentication

Each actor has its own token type and sign-in flow. Send tokens as `Authorization: Bearer <token>`.

| Actor | Sign in | Token |
|---|---|---|
| **Client** | Phone OTP: `POST clients/auth/login`, then `POST clients/auth/verify` | Client token (`auth:client`) |
| **Center user** | Phone OTP: `POST centers/auth/phone`, then `POST centers/auth/phone/verify`; then `POST centers/{center}/access` for a center | Center token with ability `center-access:{id}` (`auth:center_user`) |
| **System user** | `POST admin/auth/login` with email and password | System token (`auth:system_user`) |

## Notes

- Tokens are Laravel Sanctum bearer tokens. A client token cannot call center or admin routes, and the reverse.
- The center access step asks the **primary user** to re-enter their password. Issue center tokens only through `CenterAuthService::issueCenterAccessToken()`.
- Removing a center user revokes their center tokens.
- OTP limits: 5 sends per phone per 15 minutes and 5 failed verifies per 5 minutes. See [Support](../domain/support).
- Webhooks (`/webhooks/moyasar`, `/webhooks/tamara`) are public and verified by signature, not tokens.
- The endpoint reference uses a separate **session** login (`/docs/login`) for staff. It does not issue API tokens.
