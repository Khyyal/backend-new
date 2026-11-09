---
name: restaurant-feature-inspector
description: "Inspect how a feature is implemented in the sibling restaurant backend (/Users/mac/Documents/Work/restaurant/backend) so it can be referenced or ported to this project. Use when the user mentions the restaurant project/backend and asks how it does something (e.g. orders, delivery, promotions, reviews, notifications, support, identity), wants to compare it with khyyal, or wants to reuse its approach. Queries the restaurant project's graphify knowledge graph first, then reads source only where needed. Read-only: never edit the restaurant project."
---

# Restaurant Feature Inspector

Investigate features of the restaurant backend (Laravel, modular) using its graphify graph. Read-only on that project.

**Project path:** `/Users/mac/Documents/Work/restaurant/backend`
**Graph:** `/Users/mac/Documents/Work/restaurant/backend/graphify-out/graph.json` (report: `GRAPH_REPORT.md`)
**Modules** (`modules/`): Clients, Delivery, Discover, Homepage, Identity, Menu, Notifications, Order, Promotions, Restaurants, Reviews, Settings, Support, Taxonomy.
Extra context docs at the project root: `restaurant-domain-model.md`, `dlv.md`, `docs/`, `CLAUDE.md`.

## Process

1. **Query the graph first.** Always run graphify from the restaurant project directory:
   ```
   cd /Users/mac/Documents/Work/restaurant/backend
   graphify query "<how does feature X work?>"
   graphify explain "<ConceptOrClass>"
   graphify path "<A>" "<B>"
   ```
   Start broad (feature name), then narrow with `explain` on the class/concept names the query surfaces. Do not run `graphify update` there (read-only).
2. **Only if the graph is insufficient**, read `graphify-out/GRAPH_REPORT.md` (broad architecture) or `restaurant-domain-model.md`.
3. **Read source selectively.** Use the file paths the graph returns (typically `modules/<Module>/...`: actions, models, controllers, requests, resources, events, migrations, tests). Read only the files needed; the corpus is large (~1.5k files).
4. **Report** concisely:
   - What the feature does and which module owns it
   - Key files (`path:line`), models/tables, endpoints, events/jobs, validation and authorization
   - Notable business rules and edge cases
   - If relevant: how it maps to this project (khyyal) and what to adapt. Check `.ai/rules/index.md` here before suggesting code.

## Rules

- Never modify files in the restaurant project, and never run its migrations/seeders/artisan commands that write.
- The graph can be stale (check the date in `GRAPH_REPORT.md`); verify surprising claims against source.
- Don't copy code blindly: restaurant concepts (menus, orders, delivery) differ from khyyal's domain. Describe the pattern, then adapt.
