---
paths:
  - 'modules/Homepage/**'
---

# Homepage

## Clients only see live home content
Sections and banners must be published and within [starts_at, ends_at); centers visible and active; services ACTIVE at visible centers; promotions active and within limits; campaigns running. Admin endpoints need homepage.manage.

## Home cache must be invalidated
Home payloads are cached under a versioned key. Register every model that home content displays in flushContentOnChanges() so its save/delete/restore bumps the version.

## Home structure and analytics
Banners attach only to banner_carousel sections; a section type is immutable after creation. Content analytics are aggregate-only per (day, entity) with no personal data; drop events for unknown entities.
