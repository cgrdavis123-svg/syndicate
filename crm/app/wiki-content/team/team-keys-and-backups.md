---
title: Keys, config and backups
category: team-systems
order: 20
summary: What lives only on the server, and what to back up.
---
Never put secrets in docs, commits, chat or client pages.

## Only on the server

- `app/config.php` (CRM and main site): setup key, Turnstile secret, paths.
- `storage/mail.key`: decrypts mailbox passwords, Stripe keys and the PageSpeed key.
- `storage/totp.key`: decrypts two-step sign-in secrets.

## Back up regularly

- CRM `storage/`: `crm.sqlite`, `analytics.sqlite`, `mail.key`, `totp.key`, `portal/`, `ads/` (ad creatives).
- Main site `storage/`: database and resumes.

Copy SQLite files while the site is idle, or include the `-wal` files. Without `mail.key` and `totp.key`, saved passwords, Stripe keys and two-step sign-in can’t be recovered.
