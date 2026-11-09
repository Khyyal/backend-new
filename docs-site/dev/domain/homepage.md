# Homepage content

The **Homepage** module builds the client home screen from sections, banners and curated items.

## Sections

`HomeSection` types are **immutable after creation**:

| Type | Shows |
|---|---|
| `banner_carousel` | Banners (the only type that accepts banners) |
| `curated_centers` | Centers picked by an admin, in order |
| `curated_services` | Services picked by an admin, in order |
| `curated_promotions` | Running promotions picked by an admin |
| `curated_campaigns` | Running campaigns picked by an admin |
| `top_centers` | Centers by popularity, or nearest first when `lat` and `lng` are sent |

Items are stored as `HomeSectionItem` rows with optional `starts_at` and `ends_at`.

## What clients see

Sections and banners must be **published** and inside `[starts_at, ends_at)`. Within them:

- Centers are visible and active.
- Services are `ACTIVE` and belong to visible centers.
- Promotions are active and within their limits.
- Campaigns are running.

Sections with nothing to show are dropped. A section that is not visible answers 404.

## Display prices

Curated services carry `start_price` and `display_price` (see [Promotions](./promotions#display-prices)). The builder computes them for the visible page of items with one `ServicePriceDisplay::forServices()` call, and `HomeServiceResource::withPrices()` writes them out.

## Caching

Home payloads are cached under a **versioned key**. Register every model that home content displays in `flushContentOnChanges()` so its save, delete or restore bumps the version. The cache also expires at the next boundary (a section or item start or end time), so scheduled content appears on time.

## Analytics

Content analytics are **aggregate-only** per `(day, entity)` with no personal data. Events for unknown entities are dropped. Clients send events to `clients/home/events`; admins read them at `admin/analytics/home-content`.

## Endpoints

| Surface | Endpoints |
|---|---|
| Client | `clients/home`, `clients/home/sections/{key}` (paged), `clients/home/events` |
| Admin | `admin/home-sections` CRUD, `reorder`, `item-candidates`, `items`; `admin/banners` CRUD. Needs `homepage.manage`. |
