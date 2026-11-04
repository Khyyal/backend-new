---
paths:
  - 'modules/Billing/**'
---

# Billing

## Subscription lifecycle
Subscribe only to an active plan, with no active/trialing subscription to the same plan, holding a lock on the plan. Status is trialing when trial_days > 0 else active; ends_at = starts_at + one interval (addMonthsNoOverflow). Only the owner cancels active/trialing subscriptions; renew creates a new row from the previous ends_at with no trial. Billing has no VAT, invoices or proration.
