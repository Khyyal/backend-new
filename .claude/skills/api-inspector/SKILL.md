---
name: api-inspector
description: "Inspect, trace, explain and document this backend's HTTP APIs (Laravel, modular, under modules/). Use when asked to inspect or explain an API endpoint, list or inventory endpoints (e.g. 'list all client APIs', 'what admin routes exist'), document an endpoint or module's APIs (e.g. 'document the reservation APIs', 'generate API documentation for this module'), analyze request validation or response shape, analyze the authentication or authorization flow, or assess impact ('what could break if I modify this action/resource/request/model?'). Starts from the graphify knowledge graph and `php artisan route:list`, then verifies against source. Read-only: never edits application code."
---

# API Inspector

Inspect and document the HTTP APIs of this backend. Read-only on application code.

Every claim in the output must come from the route table, the graph, or source you opened. Mark anything else as an assumption.

## Tools, in order

1. **Route table** — `php artisan route:list --json --except-vendor`. The only reliable source for URI, method, route name, controller action and the resolved middleware stack. The graph does not link routes to controllers (route files are nested `require`s).
2. **Graphify** — `graphify query | explain | path | affected`, run from the project root. Use it to move from a controller method to its requests, actions, services, models, resources and docs, and to find reverse dependencies. See [references/graphify-usage.md](references/graphify-usage.md).
3. **Source** — open only the files the first two steps point to, and verify there. A graph edge shows a static reference, not runtime behaviour.
4. **Existing docs** — `docs-site/dev/**` (hand-written rules per module), `.ai/rules/*.md` (standing constraints), Scribe attributes on controllers, requests and resources. Treat them as leads; code wins when they disagree, and the disagreement is a finding.

Do not scan the whole repository. If the graph has no answer, search inside the one module directory the route table names.

## Pick the task

| Request | Do |
|---|---|
| List / inventory endpoints | Discovery, below |
| Explain or inspect one endpoint; request or response shape | Trace: [references/api-analysis-workflow.md](references/api-analysis-workflow.md) |
| Authentication or authorization flow | Trace steps 2–4, plus the auth section of [references/architecture.md](references/architecture.md) |
| Document an endpoint or module | Trace each endpoint, then fill [references/documentation-template.md](references/documentation-template.md) |
| "What breaks if I change X?" | Impact analysis in [references/api-analysis-workflow.md](references/api-analysis-workflow.md) |

Read [references/architecture.md](references/architecture.md) once per session before a trace. It records how this project deviates from stock Laravel. It is a snapshot; re-verify anything a conclusion depends on.

## Discovery

```
php artisan route:list --json --except-vendor
```

Filter with `--path=api/v1/clients`, `--name=centers.reservations`, `--method=POST`. Group the result by:

- **Surface**, from the URI prefix and guard: client (`clients/*`), center (`centers/{center}/*`), admin (`admin/*`), shared (`media`, `webhooks/*`), and the unversioned Billing routes (`api/admin/*`, `api/center/*`).
- **Module**, from the action namespace `Modules\<Module>\…`.
- **Resource**, from the route name prefix.

Report as a table: method, URI, route name, controller@method, guard, other middleware (`center.scope`, `idempotent`, `throttle:*`, `can:*`). Path parameters come from the URI; query parameters need the controller (step 5 of the trace).

## Output standards

- Cite `path:line` for every rule, field, check and side effect.
- Keep **Verified** (read in source) apart from **Inferred** (graph edge, doc, or naming only) and **Not checked**.
- Tables for endpoint inventories and for request and response fields.
- Never invent a field, rule, status code or permission. If it cannot be found, say so.
- Separate authentication (who the caller is: guard, token) from authorization (what they may do: policy, permission, ownership, center scoping).
- Call out security concerns and likely bugs in their own section: a center route missing `center.scope`, a controller method with no policy check, a query not scoped by `center_id` or owner, a write route without `idempotent`, Scribe attributes that disagree with the code.
- Skip explanations of standard Laravel behaviour.
- Money is integer halalas, percentages are basis points, dates are UTC. State units on every amount.

## Scope and safety

- Do not edit application code, routes, migrations, config, tests or `docs-site/`. Only files under `.claude/skills/api-inspector/` may be changed, and only when asked to update this skill.
- Allowed commands: `graphify query|explain|path|affected|god-nodes`, `php artisan route:list`, `php artisan config:show <key>`, `git log|diff|show`. `graphify update .` only when the graph is stale (it writes only to the ignored `graphify-out/`).
- Do not run commands that write or have side effects: migrations, seeders, `tinker` that mutates, `scribe:generate`, `docs:build`, queue workers, real HTTP calls to gateways.
- Never read or print `.env`, tokens, keys or gateway secrets. `php artisan config:show` output can contain secrets: request the narrowest key and redact values.
- Write generated documentation into the reply unless the user names a file. This project only allows new documentation files on explicit request.
