# API analysis workflow

Two procedures: tracing one endpoint, and impact analysis. Both start from the route table and the graph and end in source.

The flow in this project is:

```
Route (module route file, often a shared partial)
  → Middleware (guard, center.scope, idempotent, throttle, locale)
  → Controller method (Modules\<M>\Http\Controllers\{Admin,Center,Client})
  → FormRequest (authorize + rules)  or inline $request->validate()
  → Policy check (Gate, in the request or a controller helper)
  → Scoped lookup (where center_id / owner)
  → Action::execute() or Service method  (transaction, state service, locks)
  → Models, events (after commit) → listeners in other modules, jobs, notifications
  → JsonResource (+ additional keys, explicit status) → JSON
```

Steps can be missing or merged. Record what the code does, not what this diagram expects.

## Tracing an endpoint

### 1. Resolve the route

```
php artisan route:list --json --except-vendor --path=<uri fragment>
```

or `--name=<route name>`. Record method, URI, name, `Controller@method` and the full middleware list. If the user gave a controller method or a class instead of a URL, find its routes by filtering the JSON on `action`.

Check for a twin: if the route file is a partial, the same method is also mounted on the other surface (`centers/{center}/…` and `admin/centers/{center}/…`). List both.

### 2. Middleware and authentication

From the middleware list, state:

- the guard (`Authenticate:<guard>`), or that the route is public;
- `center.scope` present or absent on a `centers/{center}` route (absent is a finding);
- `idempotent`, and therefore the required `Idempotency-Key` header and its 400, 409 and 422 responses;
- throttles, with the limiter definition if it is a named one (`RateLimiter::for` in the module provider);
- `can:` middleware (Billing).

### 3. Controller method

```
graphify explain "<Surface>/<Name>Controller.php"
```

then open the method. Note: its typed parameters (route parameters as `int` or bound models, the FormRequest class, injected actions), constructor-injected services, the Scribe attributes above it, and the return type and statement.

### 4. Authorization

Find every check that applies and cite each:

- FormRequest `authorize()`;
- controller helper calling `Gate::forUser(...)->authorize('<ability>', ...)`;
- the policy method behind the ability, and the permissions it maps to for center users and for system users;
- query scoping of each lookup (`where('center_id', …)`, the client's own relations, a parent relation for child ids);
- any further narrowing (`CenterPurchaseVisibility`, type-specific service abilities).

Report missing layers explicitly. Do not assume the middleware covers them.

### 5. Request

Open the FormRequest, any `Concerns` trait it uses, and inline `validate()` calls. For each field: name, type, required or optional, rules verbatim, conditional rules (`required_if`, `required_unless`, `Rule::when`, `sometimes`), enum values (open the enum), defaults (look for `$request->input('x', default)`, `boolean()`, `prepareForValidation()`), and whether the action reads fields beyond the validated set.

For list endpoints, take query parameters from the `QueryBuilder` calls: each `AllowedFilter`, each allowed sort, the default sort, and the page size.

Media: look for temporary media ids and `TemporaryMediaService::promote*`.

### 6. Business logic

```
graphify explain "<ActionOrService>"
```

and repeat on each class it references, until reaching models or another module's public service. Then read the code. Record:

- transaction boundaries and locks;
- each rule that can reject the request, with its exception and `reason` key;
- state transitions (which `*StateService` method);
- writes: models created, updated, deleted;
- calls into other modules and external services (payment gateways, FCM, WhatsApp, storage);
- cache reads and invalidations.

Stop following a branch when it leaves the endpoint's concern, and say where you stopped.

### 7. Side effects

For every event dispatched on the path, find its listeners by opening the service providers (`Event::listen`, `Event::subscribe`); `graphify affected "<EventClass>"` narrows which modules mention it. For each listener: queued or synchronous, and what it changes. Add jobs dispatched, notifications sent, and scheduled commands that later act on the same records.

### 8. Response

Open the resource's `toArray()` and list the keys actually returned, with type, nullability and the condition under which each is null or absent (`when`, `whenLoaded`, ternaries on relations). Include nested resources, `additional()` keys, pagination `meta` and `links` for collections, and the status code.

Then list error responses by walking back through the steps: 400 and 409 (idempotency), 401, 403 (each source), 404 (each `findOrFail`), 422 (validation, each domain `reason`), 429.

Compare with the Scribe attributes. Report every mismatch between attributes and code as a documentation defect, and describe the code's behaviour as the truth.

### 9. Cross-check

- Feature tests in `modules/<Module>/tests/Feature/` for the endpoint: they confirm payloads and codes, and show intended edge cases.
- `docs-site/dev/domain/<module>.md` and `.ai/rules/<module>.md` for the stated rule. A difference between rule and code is a finding.

### 10. Report

Use the structure in [documentation-template.md](documentation-template.md) when documentation was requested. For an explanation, give: summary, the flow as a numbered list with `path:line`, request table, response table, authorization, side effects, findings, and what was not checked.

## Impact analysis

For "what could break if I change X", where X is an action, service, model, resource, request, enum, event or policy.

### 1. Reverse dependencies

```
graphify affected "<X>" --depth 2
```

Raise `--depth` when X is a low-level model or service. Separate the results into production code, tests and docs. For widely used classes, check `graphify god-nodes` to see whether X is a hub.

### 2. Map to endpoints

For each controller method in the result, find its routes in `route:list --json` (filter on `action`). Remember that a shared partial gives each method two routes. The outcome is a table: endpoint, surface, how it depends on X (direct call, through which class).

### 3. Follow what the graph cannot see

- Events: if X dispatches or is an event, read the providers for listeners in other modules.
- Policies: a renamed ability or permission breaks string-based `Gate` calls that the graph does not link.
- `resolveRelationUsing` relations and morph maps in providers.
- Scheduled commands and queued jobs that use X.
- Lang keys: `reason` values and notification keys need `en` and `ar` entries.
- Exports and notifications that render the same model.

### 4. Classify the change

| Change | Typical breakage |
|---|---|
| Resource: field removed, renamed, type or nullability changed | Every endpoint returning that resource or a resource that nests it; all three frontends for shared resources; Scribe attributes; feature tests |
| Request: new required field, stricter rule, enum case removed | Existing clients get 422; idempotent replays of old bodies change fingerprint |
| Action or service: signature or behaviour | All callers from step 1; listeners that expect the same events; state transitions |
| Model: column, cast, relation | Resources reading it, `RELATIONS` eager-load lists, factories, exports, the legacy migrator commands in `app/Console/Commands/Migrator` |
| Enum: case added or removed | Validation, resource output, lang files, frontend switch statements |
| Event payload | Listeners in other modules, queued jobs already serialized on the queue |
| Policy or permission | Both actor types on shared endpoints; protected system roles |
| Status code or error `reason` | Clients that branch on them |

State which surfaces are affected: the client app, the center dashboard and app, the admin panel. A change to a shared partial's controller or resource reaches center and admin together.

### 5. Regression risks and report

List the tests that cover the affected paths and the paths with no test. Name the docs that the project rules require to change with the code (`docs-site/dev/domain/<module>.md`, `docs-site/user/**`, Scribe attributes). Give the result as: dependency table, affected endpoints table, breaking changes, non-breaking but risky changes, unverified areas.
