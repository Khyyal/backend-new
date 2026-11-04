---
paths:
  - 'modules/Support/**'
---

# Support

## OTP goes through OtpVerificationService
Codes are hashed, expire after 300s, a new send invalidates older unused codes, a successful verify consumes the code. Limits: 5 sends per phone per 15 min, 5 failed verifies per 5 min. Use the same phone string for send, verify and account lookup.

## OTP dev bypass is never production
The dev bypass works only when otp.dev.enabled, the environment is local/testing/staging, and the phone is in OTP_DEV_PHONES. Never widen it.

## Devices and push tokens
Register devices via DeviceService::launch() and link them on login with attachTo(); a device is unique per (owner, identifier, platform). Invalid FCM tokens are nulled, not deleted.
