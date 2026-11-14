# Documentation template

Fill one block per endpoint. Every value comes from a completed trace ([api-analysis-workflow.md](api-analysis-workflow.md)). Write "None" for an empty section and "Not verified" for anything not read in source. Do not copy Scribe attribute text without checking it against the code.

For a module, open with an inventory table, then the endpoint blocks grouped by surface (client, center, admin). When a method is mounted on two surfaces, write one block and list both routes.

## Module inventory

| Method | URI | Route name | Controller@method | Guard | Other middleware |
|---|---|---|---|---|---|

## Endpoint block

````markdown
### <Endpoint name>

`<METHOD> /api/v1/<uri>`   (also `<METHOD> /api/v1/admin/...` when shared)

**Route name:** `<name>` · **Handler:** `<Controller>@<method>` (`path:line`)

<One or two sentences: what it does and for whom.>

#### Authentication

Guard `<guard>` (Sanctum bearer token) | Public. Extra token requirement, for example ability `center-access:{center}`.

#### Authorization

| Check | Where | Requirement |
|---|---|---|
| Center scope | `center.scope` middleware | Token ability; center Active |
| Policy | `path:line` | Center user: `<CenterUserPermission>`; system user: `<SystemPermission>` |
| Scoping | `path:line` | `<how the record is tied to the center or owner>` |

#### Headers

| Header | Required | Notes |
|---|---|---|
| `Idempotency-Key` | yes/no | |
| `Accept-Language` | no | `en`, `ar` |

#### Path parameters

| Name | Type | Constraint | Description |
|---|---|---|---|

#### Query parameters

| Name | Type | Required | Default | Rules / accepted values |
|---|---|---|---|---|

For lists: filters (`filter[x]`), sorts (`sort`, with default), `page`, page size.

#### Request body

| Field | Type | Required | Default | Validation rules | Notes |
|---|---|---|---|---|---|

Conditional rules:
- `<field>` is required when `<condition>` (`path:line`)

Enum values: `<Enum>`: `a`, `b`, `c` (`path:line`)

File uploads: none | temporary media ids from `POST /api/v1/media`.

#### Success response

**Status:** `<code>` · **Resource:** `<Resource>` (`path:line`)

| Field | Type | Nullable | Condition | Description |
|---|---|---|---|---|

Top-level keys besides `data`: `<additional keys>` · Pagination: `links`, `meta` (page size N) | none.

```json
{ "data": { } }
```

Use an example from a feature test or the Scribe attribute, and say which. Do not compose values that appear nowhere in the code.

#### Error responses

| Status | When | Body |
|---|---|---|
| 400 | Missing `Idempotency-Key` | `{message, code: "idempotency_key_required"}` |
| 401 | No or invalid token | `{message}` |
| 403 | `<each source>` | `{message}` |
| 404 | `<each lookup>` | `{message}` |
| 409 | Same key still running | `{message, code}` + `Retry-After` |
| 422 | Validation | `{message, errors}` |
| 422 | Domain rule, `reason` one of: `<keys>` | `{message, reason, errors}` |
| 429 | `<limiter>` | |

Delete the rows that cannot occur on this endpoint.

#### Business logic

1. `<step>` (`path:line`)
2. …

Amounts in halalas; percentages in basis points; times in UTC.

#### Dependencies and side effects

| Kind | What | Where |
|---|---|---|
| Action / service | | |
| Models written | | |
| Transaction / lock | | |
| Events → listeners | `<Event>` → `<Listener>` (module, queued or sync) | |
| Jobs / notifications | | |
| External services | | |
| Cache | | |

#### Findings

- Security concerns, likely bugs, and mismatches between code, Scribe attributes and `docs-site`.

#### Verification

- **Verified in source:** …
- **Inferred (graph or docs only):** …
- **Not checked:** …
````

## Where the output goes

Put the documentation in the reply unless the user names a destination. This project only allows new documentation files on explicit request, and its own docs (`docs-site/`, Scribe attributes) have a separate update process described in `docs-site/dev/conventions/documentation.md`.
