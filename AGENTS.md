# For_You — Agent Guidelines

This repository is a Laravel 13 REST API for an e-commerce store with an
influencer affiliate program (promotional links, tracked clicks, commissions,
wallets and withdrawals).

## Environment

- PHP 8.4.1+ with the `pdo_mysql` (or `pdo_sqlite` for tests) extension.
- Composer 2.x.
- Node.js only if you need to build the Vite assets (`npm install && npm run build`).
- `gd` or `imagick` is required only when uploading/converting images. The API
  boots without it; upload endpoints return a clear runtime error instead.

Install dependencies with the committed lock file:

```sh
composer install
```

Do **not** run arbitrary remote install scripts. If PHP/Composer is missing,
install it with your operating system's package manager or the official
installer from <https://www.php.net/downloads> / <https://getcomposer.org/download/>.

## Local setup

```sh
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Tests use an in-memory SQLite database configured in `phpunit.xml`, so no local
database is needed:

```sh
php artisan test
```

Run a single suite/file while iterating:

```sh
php artisan test tests/Feature/OrderTransitionTest.php
php artisan test --filter=CommissionTest
```

## Project layout

- `app/Http/Controllers/Api` — customer, auth, webhook and influencer endpoints.
- `app/Http/Controllers/Admin` — admin/employee endpoints guarded by permissions.
- `app/Services` — business logic (`OrderService`, `PaymentService`,
  `WalletService`, `ShippingService`, `PricingService`, …). Keep financial
  rules here, not in controllers.
- `app/Contracts` + `app/Services/*Gateways` — pluggable payment/shipping
  gateway interfaces and the manual/EasyKash skeletons.
- `config/store.php`, `config/payments.php`, `config/services.php` — runtime
  configuration and feature flags.
- `routes/api.php` — every API route (auto-prefixed with `/api`).
- `database/migrations`, `database/factories`, `database/seeders` — schema and
  fixtures. `RolesAndPermissionsSeeder` defines permissions and roles.

## Conventions

- Keep the public API contract stable unless a change is required and documented.
- Never use `$request->all()` in create/update actions; validate explicitly.
- Never interpolate user input into raw SQL. Use query builder bindings.
- Financial state changes (payments, commissions, wallets, withdrawals) must run
  inside `DB::transaction(...)` and use `lockForUpdate()` for the rows involved.
- Preserve the existing Arabic user-facing messages. Validation/HTTP messages
  are translated by `TranslateMessages` based on `Accept-Language`.
- Use Form Requests or inline `$request->validate([...])`; do not trust mass
  assignment beyond each model's `$fillable`.
- Runtime files (`storage/`, `bootstrap/cache/`) are ignored. Never commit them.
- Add a feature test for every behavioural change in a financial or sensitive
  flow (`tests/Feature`).

## Review checklist

1. `php -l` every edited PHP file.
2. `php artisan test` passes.
3. Routes/middleware/config changes are consistent (`php artisan route:list`).
4. Migrations are reversible (`down()` implemented).
5. No secrets, `.env`, logs, caches, sessions or uploaded files are committed.

## Documentation

- `docs/API.md` — endpoint reference, auth, roles, order/commission/withdrawal
  flows, idempotency, webhooks and environment variables.
- `postman/For_You_API.postman_collection.json` — importable Postman
  collection (Collection v2.1).
- `README.md` — project overview and quick start.
