---
paths:
  - 'modules/Notifications/**'
---

# Notifications

## Campaign lifecycle
draft|scheduled -> sending -> sent|failed; cancelled is terminal. Edit content and targets only while draft or scheduled; a sending campaign cannot be deleted. Start only via CampaignService::sendNow() (conditional update); only markFinished ends sending. Admin-only (notification_campaigns.manage).

## Campaign targeting and delivery
Target mode all = no target rows; selected = 1-5000 existing ids of the audience model; a Centers audience reaches Center::notifiableUsers(). Push needs en and ar title/body; WhatsApp parameters must equal the template variable count. WhatsApp writes one delivery row per recipient and skips existing ones so retries never resend.
