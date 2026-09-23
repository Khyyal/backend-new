# Khyyal Backend

Laravel backend application built with a modular architecture. The project is organized around a shared `modules/` directory, where each module contains its own models, migrations, seeders, and service provider registration.

## Overview

This application uses Laravel 13 with modular domain separation for:

- Support services and shared data models
- Centers management and center-user permissions
- Client model and client-specific logic

The modular structure keeps the codebase easier to extend and maintain as each domain is isolated under `modules/{ModuleName}`.

## Tech Stack

- PHP 8.3+
- Laravel 13
- MySQL/PostgreSQL-compatible database via Laravel Eloquent
- Sanctum for API authentication patterns
- Spatie packages for media, permissions, settings, translatable data, and query building
- Pest for testing
- Vite for frontend asset building

## Project Structure

```text
khyyal_backend/
├── app/
│   ├── Http/
│   ├── Models/
│   └── Providers/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── modules/
│   ├── Support/
│   │   ├── database/
│   │   ├── routes/
│   │   ├── src/
│   │   └── SupportServiceProvider.php
│   ├── Centers/
│   │   ├── database/
│   │   ├── src/
│   │   └── CentersServiceProvider.php
│   └── Clients/
│       ├── database/
│       ├── src/
│       └── ClientsServiceProvider.php
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── .env.example
├── artisan
├── composer.json
├── package.json
├── phpunit.xml
├── README.md
├── vite.config.js
└── vendor/
```

## Module Architecture

The application uses Composer PSR-4 autoload mappings to register module namespaces from the `modules/` directory.

### 1. Support Module
Location: `modules/Support/`

This module contains shared support domain features such as:

- Cities
- Devices
- Ratings
- Action logs
- Shared traits and enums

Key files:

- `modules/Support/src/Models/City.php`
- `modules/Support/src/Models/Device.php`
- `modules/Support/src/Models/Rating.php`
- `modules/Support/src/Models/ActionLog.php`
- `modules/Support/src/SupportServiceProvider.php`

The provider loads its migrations and registers API routes for the support domain.

### 2. Centers Module
Location: `modules/Centers/`

This module manages center-related domain logic including:

- Centers
- Tags
- Users assigned to centers
- Center permissions and roles
- Center status and role enums

Key files:

- `modules/Centers/src/Models/Center.php`
- `modules/Centers/src/Models/User.php`
- `modules/Centers/src/Models/CenterUser.php`
- `modules/Centers/src/Models/Tag.php`
- `modules/Centers/src/CentersServiceProvider.php`

This module is responsible for center-level authorization, assignment logic, and seeding of center-specific data.

### 3. Clients Module
Location: `modules/Clients/`

This module contains client-related models and is set up to load its own migrations and API routes.

Key files:

- `modules/Clients/src/Models/Client.php`
- `modules/Clients/src/ClientsServiceProvider.php`

## Routes and API Structure

Module providers include API route registration under the `api/v1` prefix, for example:

- `modules/Support/routes/api.php`
- `modules/Support/routes/client.php`
- `modules/Support/routes/admin.php`
- `modules/Support/routes/center.php`

This allows each domain to expose its APIs independently while sharing the same Laravel application skeleton.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

To run the application in development mode:

```bash
composer run dev
```

## Testing

```bash
php artisan test
```

## Notes

- Module autoloading is configured in `composer.json`.
- Each module contains its own database migrations and factory/seed data.
- Service providers are the entry points for registering module-level migrations and routes.

This project follows a modular Laravel pattern suitable for scaling domain-specific features without mixing unrelated logic into a single monolithic app structure.
