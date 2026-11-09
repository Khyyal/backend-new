---
paths:
  - 'docs-site/**'
---

# Docs site

## Layout and registration
Developer pages live in docs-site/dev (getting-started, architecture, conventions, domain one page per module, api); user pages in docs-site/user/{admin,center,client}. Register every new page in docs-site/.vitepress/config.mts (devGroups or userGroups) or it has no sidebar entry.

## Describe what exists today
Document current behaviour only; never planned features. Developer pages name the rules and classes (amounts in halalas); user pages describe what a person sees and does, in SAR, without class names. Derive facts from code, routes and .ai/rules, and keep both in step.

## Build output is committed
npm run docs:build writes storage/app/docs, which is committed and served at /guide behind the system-user login (api_docs.view). Scribe stays at /docs. The site is English only; adding Arabic means mirroring the tree under docs-site/ar and extending the sync rule in modules.md to both languages.
