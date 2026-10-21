# Contributing to ChatDesk

Thanks for helping! ChatDesk open source is maintained by [Yoorie](https://yoorie.com).

## Ground rules

- Be kind. Keep discussions about the code.
- Open an issue before starting a large change, so we can agree on the approach.
- **Never** commit secrets: no `.env`, access tokens, app secrets, private keys or customer data.
- Security problems go to **support@yoorie.com**, not public issues (see [SECURITY.md](SECURITY.md)).

## Scope

This edition is a single-organisation, self-hosted WhatsApp Cloud API inbox. Multi-tenant SaaS,
billing/plans, payment gateways and the super-admin panel belong to ChatDesk paid plans / Zyptos
and will not be merged here.

## Local setup

```bash
cp .env.example .env
composer install && npm ci
php artisan key:generate && php artisan migrate --seed
npm run build
php artisan test
```

## Pull requests

1. Fork the repo and branch from `main` (`feat/…`, `fix/…`).
2. Add or update tests for your change. `php artisan test` must pass.
3. Format PHP with `vendor/bin/pint`; run `npm run typecheck` and `npm run lint` for the frontend.
4. Use clear commit messages and describe *why* in the PR.
5. By contributing, you agree your contribution is licensed under the AGPL-3.0 and that Yoorie may
   also offer it under its commercial licence.
