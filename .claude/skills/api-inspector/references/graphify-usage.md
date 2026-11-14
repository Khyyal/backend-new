# Graphify usage for API inspection

The graph lives in `graphify-out/` (git-ignored). Run every command from the project root. The commands below are the ones this project's `CLAUDE.md` and `graphify --help` define; do not use others without checking `graphify --help`.

## Freshness check, once per session

`graphify-out/GRAPH_REPORT.md` has a line `Built from commit: <sha>`. Compare with `git rev-parse --short HEAD`.

- Same commit: use the graph as is.
- Different: run `graphify update .`. It re-extracts code by AST, needs no API key, and writes only to `graphify-out/`. Do not run a full `/graphify` rebuild for an inspection task.
- Uncommitted changes in the working tree are not in the graph. Read those files directly.

## Commands

| Command | Use for |
|---|---|
| `graphify query "<question>"` | Broad orientation on a feature. BFS from the nodes matching the question's words. |
| `graphify query "<question>" --dfs` | Follow one chain deep instead of wide. |
| `graphify query "..." --budget N` | Cap output at about N tokens (default 2000). A `TRUNCATED` notice means the answer may be cut: narrow the question or raise the budget. |
| `graphify query "..." --context call` | Keep only edges of one context, for example `call` or `import`. Repeatable. |
| `graphify explain "<Node>"` | One node with all its edges, each with `file:line`. The main tool for a trace. |
| `graphify path "<A>" "<B>"` | Shortest path between two nodes, to confirm that and how two things connect. |
| `graphify affected "<Node>" --depth N` | Reverse traversal: what references, calls, imports or extends the node. The main tool for impact analysis. Default depth 2. |
| `graphify god-nodes --top N` | Most connected nodes; changing these has the widest reach. |

## Node names

- Classes are named by short class name: `CancelStayInvoice`, `StayInvoiceResource`, `CenterPolicy`.
- Methods are `.methodName()`: `.cancelInvoice()`. Many classes share method names, so start from the class with `explain` and read the method edges there.
- Files are named by a path suffix: `Center/ReservationController.php`, `Reservations/routes/center.php`. Several modules have a controller with the same class name (for example `Center/ReservationController`, `Client/ReservationController`); use the file-suffix form to pick one.
- Doc sections from `docs-site/` and `.ai/rules/` are nodes too. An `INFERRED` edge from a doc section to a class points at the paragraph describing that class.

## Reading the output

Each edge carries a relation (`imports`, `references`, `calls`, `method`, `contains`, `inherits`), a confidence tag and a `file:line`.

- `EXTRACTED`: found in the code by the parser. Reliable as a static fact.
- `INFERRED`: a model's reading, mostly of docs. A lead only.
- `AMBIGUOUS`: treat as unknown.

Open the cited line before stating anything as verified.

## What the graph does not show in this project

Checked against the code; plan around these gaps.

| Gap | Where to look instead |
|---|---|
| Route → controller method. Route files are chains of `require` (`routes/api.php` → `center.php` → `partials/*.php`), and the graph only records the imports of each file. | `php artisan route:list --json` |
| Middleware resolved onto a route, including aliases and group middleware | `route:list --json` `middleware` field; aliases are registered in module service providers |
| Event → listener wiring. Registered with `Event::listen` / `Event::subscribe` inside each `modules/*/src/*ServiceProvider.php`, not by discovery. | The service provider of every module that could listen; `graphify affected "<Event>"` finds the files that mention the event class |
| Policy ↔ model binding (`Gate::policy` in service providers) and string abilities such as `Gate::authorize('editReservations', $center)` | The service provider and the policy class |
| Dynamic relations added with `Model::resolveRelationUsing` in a provider | The service provider |
| Scheduled commands (`callAfterResolving(Schedule::class, …)` in providers) | The service provider |
| Validation rules, response fields, query filters | Only as text inside the file. Read the request, resource and controller. |
| Runtime facts: which branch runs, config values, database contents | Source, config, tests |

## Typical sequences

Trace forward from a controller method:

```
graphify explain "Center/ReservationController.php"
graphify explain "CancelStayInvoice"
graphify explain "RefundPurchase"
```

Impact of changing a class:

```
graphify affected "StayInvoiceResource" --depth 2
graphify path "StayInvoiceResource" "HorseStayController"
```

Orientation on an unfamiliar feature:

```
graphify query "how does a client book from a package" --budget 1500
```

## When the graph has nothing

Fall back to source, but stay narrow: the module directory named by the route's action namespace, then the classes that file imports. Say in the report that the finding came from source because the graph had no node or edge for it.
