# Installation

## Requirements

- PHP 8.3 or newer (CI runs 8.4) with the usual Laravel extensions (`mbstring`, `dom`, `fileinfo`, `pdo_sqlite` for tests)
- Composer
- Node.js and npm (for the front-end build and for this documentation site)
- A database supported by Laravel. Tests use SQLite.
- [Laravel Herd](https://herd.laravel.com) is the usual local environment, but any PHP setup works.

## Setup

```bash
composer setup
```

`composer setup` installs PHP dependencies, copies `.env.example` to `.env` if it is missing, generates the app key, runs the migrations and builds the front-end assets.

Then create the first admin. The seeders read `SUPER_ADMIN_NAME`, `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` from `.env` (see [Configuration](./configuration)).

## Module layout

Application code lives in `modules/<Module>/`, not in `app/`. Each module has its own `src/`, `routes/`, `database/`, `lang/` and `tests/`, and a service provider that registers them. See [Modules](../architecture/modules).
