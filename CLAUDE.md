# For_You — Claude Instructions

Read and follow `AGENTS.md` in the repository root. It contains the environment
setup, architecture, coding conventions, testing commands and review checklist
for this Laravel API.

Quick reference:

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan test
```

Do not run remote shell installers. Keep financial operations transactional and
locked, validate request input explicitly, and preserve Arabic user-facing
messages. Document API changes in `docs/API.md` and keep the Postman collection
up to date.
