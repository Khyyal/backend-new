# Octane and long-running workers

The application runs on Laravel Octane. It boots once and then serves many requests in the same process, so state can leak between requests.

## Rules

- **Never store request-specific state** in singletons or static properties.
- Prefer **scoped bindings** (`$this->app->scoped()`) over singletons for per-request services. Example: `ServicePriceDisplay` is bound with `scoped()` in `PromotionsServiceProvider`.
- Do not inject the request or the authenticated user into a singleton's constructor.
- Cached values must be keyed by what they depend on (for example the home payload is cached under a versioned key).

## Detecting the driver

Use `config('octane.server')` if you must write driver-specific code (`swoole`, `roadrunner` or `frankenphp`).

## Why it matters here

- Home content and promotions are cached. Writes bump a cache version so the next request sees fresh data. See [Homepage](../domain/homepage) and [Promotions](../domain/promotions).
- Queue workers are also long-running: restart them after a deployment so they load new code.
