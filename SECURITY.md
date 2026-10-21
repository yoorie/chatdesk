# Security policy

## Reporting a vulnerability

Please email **support@yoorie.com** with:

- a description of the issue and its impact,
- steps to reproduce (or a proof of concept),
- the ChatDesk version / commit you tested.

Do **not** open a public GitHub issue for security problems. We aim to reply within 3 business
days and will keep you updated until a fix is released. We are happy to credit you.

## Supported versions

Security fixes go to the latest release on `main`.

## Hardening checklist for your install

- Serve ChatDesk over HTTPS only and keep `APP_DEBUG=false` in production.
- Set `META_APP_SECRET` so every webhook signature is verified (the default is strict).
- Use a System User token with only the WhatsApp permissions ChatDesk needs.
- Keep PHP, your database and dependencies up to date (`composer update`, `npm update`).
- Back up the database and `storage/` regularly.
