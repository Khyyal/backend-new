# Testing

Tests use **Pest** and live in each module's `tests/` folder (`Feature` and `Unit`).

## Running

```bash
php artisan test --compact                       # everything
php artisan test --compact modules/Reservations  # one module
php artisan test --compact --filter="display_price"
```

CI runs the suite on pull requests with SQLite.

## Conventions

- Feature tests use `uses(TestCase::class, RefreshDatabase::class)`.
- Build data with the module factories (`CenterFactory`, `ServiceFactory`, `PromotionFactory`, `ReservationFactory` ...). Prefer states such as `->active()` or `->forCenter($center)` over raw attributes.
- Test the behaviour and its important failure modes, not every branch.
- Authenticate with `actingAs($user, 'client' | 'center_user' | 'system_user')`. Docs endpoints use the `system_user_web` guard.
- Add a regression test with every behaviour change.

## Query counts

Some endpoints assert a **constant number of queries** regardless of data size (for example the service details availability tests). When you add a lookup that is cached, warm the cache before measuring, as the existing tests do.

## Time

Use `$this->travelTo('2026-10-19 08:00:00')` for anything that depends on the clock. Everything is UTC.

## Money

Assert amounts as integers in halalas.
