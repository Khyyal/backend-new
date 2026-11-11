# Search

The **Search** module serves the admin panel's global search. One request looks up centers, clients, reservations, purchases, client packages, client memberships and services, and returns the hits grouped by type.

## Endpoint

`GET admin/search?q=…&limit=…&locale=…` (`auth:system_user`, 60 requests a minute).

| Param | Rules |
|---|---|
| `q` | Required, 2–100 characters |
| `limit` | Hits per group, 1–10, default 5 |
| `locale` | `ar` or `en`, the language of translated titles (service names). If it is missing, Accept-Language is used, then `ar` |

The response is `data.<group>[]`. Each hit has the same shape: `id`, `title`, `subtitle`, `code`, `center_id`, `kind` and `meta`. Within a group, hits are newest first. The panel needs `center_id` and `kind` to build nested detail routes.

## Groups

Each group is a `GlobalSearchProvider` in `modules/Search/src/Providers`. They are registered, in response order, as a tagged list in `SearchServiceProvider`. `GlobalSearch` returns nothing for inactive users. It also drops a group the user lacks the permission for, removing its key from the response rather than returning it empty.

| Group | Permission | Matches |
|---|---|---|
| `centers` | `centers.view_any` | Name, slug, contact phone or email |
| `clients` | `clients.view_any` | Every word of `q` must be in the first name, last name or phone (`Client::scopeSearch`) |
| `reservations` | `centers.manage_reservations` | The client (as above), or the exact public `code` |
| `purchases` | `purchases.view_any` | Client purchases at centers only (as in `AdminPurchaseQuery`): the buyer, or the exact `code` |
| `client_packages` | `centers.manage_reservations` | The client, or the service name (en/ar). Pending packages are excluded; `center_id` comes from the service |
| `client_memberships` | `centers.manage_reservations` | Same as packages |
| `services` | `centers.manage_services` | Name (en/ar) or slug. `kind` is the admin route segment (e.g. `training-services`), and `meta.serviceable_id` is the id under it |

When `q` looks like a public code (6 characters from the `HasUniqueCode` alphabet, any case), the exact reservation or purchase is listed first. LIKE wildcards in `q` are escaped.
