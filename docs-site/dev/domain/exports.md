# Exports

The **Exports** module builds spreadsheets in the background so large lists never time out.

## Flow

1. The requester calls an export endpoint (for example `POST centers/{center}/purchases/export`).
2. `ExportService::start()` records an `Export` row (`pending`), queues the build and returns immediately.
3. When the file is ready (`completed`) or fails (`failed`), the requester gets a notification.
4. The requester downloads it from `…/exports/{export}/download`.

Rules:

- Status only moves `pending → completed` or `pending → failed`, through conditional updates, so concurrent chunk failures notify once.
- The same export (requester, type, center) cannot be started again while one is still being built; the API answers **429**.
- A download after the file expired answers **410**.
- `exports:prune` runs daily at 03:00 and deletes expired files.

## Types

| Type | Who |
|---|---|
| `admin_purchases`, `admin_centers`, `admin_center_purchases`, `admin_clients`, `admin_center_ledger` | System users (`purchases.export`, `centers.export`, `clients.export`, `payouts.export`) |
| `center_purchases`, `center_clients` | Center users (`purchases.export`, `clients.export`) |

## Endpoints

`admin/exports`, `admin/exports/{export}`, `admin/exports/{export}/download` and `centers/{center}/exports` (same shape).
