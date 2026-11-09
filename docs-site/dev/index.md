# Developer guide

This guide is for people who work on the platform's backend: the Laravel application that serves the admin panel, the center dashboard and the client app.

## What the platform does

Clients browse riding and equestrian **centers**, look at the **services** each center offers, and book and pay for them. Centers manage their own services, schedules, team, promotions and payouts. The platform team (system users) manages centers, fees, plans, homepage content, notifications and analytics from the admin panel.

## How this guide is organised

| Section | What you find there |
|---|---|
| [Getting started](./getting-started/installation) | Installing, configuring and running the backend locally |
| [Architecture](./architecture/overview) | The modular monolith, the three API surfaces and how Octane affects the code |
| [Conventions](./conventions/coding) | How code is written here: money, permissions, tests, docs |
| [Domain flows](./domain/centers) | How each module works, with its business rules |
| [API reference](./api/overview) | Authentication, error format and a pointer to the endpoint reference |

The exact request and response of each endpoint is in the <a href="/docs" target="_self">endpoint reference</a>, generated from the code by Scribe.

## Stack at a glance

| Piece | Choice |
|---|---|
| Language | PHP 8.3+ (8.4 in CI) |
| Framework | Laravel 13, served by Laravel Octane |
| Auth | Laravel Sanctum bearer tokens, one guard per API surface |
| Roles and permissions | Spatie Laravel Permission |
| Lists | Spatie Laravel Query Builder |
| Settings | Spatie Laravel Settings |
| Media | Spatie Laravel Media Library |
| Translatable fields | Spatie Laravel Translatable (`en` and `ar`) |
| Audit trail | Spatie Laravel Activitylog |
| Payments | Moyasar and Tamara gateways |
| Push | Firebase Cloud Messaging |
| Tests | Pest |
| API docs | Scribe |
