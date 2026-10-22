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

## Nightly backups (automatic)

Every night after 2 AM, cron copies `crm.sqlite`, `analytics.sqlite`, `mail.key`, `totp.key`, the stusyn.com database and its `totp.key` into `storage/backups/YYYY-MM-DD/`. Each database copy is a consistent snapshot that’s checked before it counts. The last 14 days are kept.

- **Status and controls:** Settings → Nightly backups shows the last backup, has **Back up now**, and lets an owner **Download latest backup** (a zip; needs your password).
- **If one fails:** hello@ gets one email and a task a day until it works, and the dashboard warns once the last good backup is over 2 days old. On the server, `php app/cli.php backup` runs it by hand and prints the reason.
- **Restore:** stop cron, replace `storage/crm.sqlite` (and the keys, if lost) with the copies from a backup folder, delete any `crm.sqlite-wal` / `-shm` next to it, then start cron again.

## Off the server, regularly

These copies sit on the same server, so they don’t help if the hosting account is lost. Every week or two, download the latest backup and keep it somewhere private, plus:

- CRM `storage/`: `crm.sqlite`, `analytics.sqlite`, `mail.key`, `totp.key`, `portal/`, `ads/` (ad creatives).
- Main site `storage/`: database and resumes.

The nightly backup doesn’t include `portal/`, `ads/` or resumes (they can be large); take those from cPanel → Backup. Copy SQLite files by hand only while the site is idle, or include the `-wal` files. Without `mail.key` and `totp.key`, saved passwords, Stripe keys and two-step sign-in can’t be recovered.
