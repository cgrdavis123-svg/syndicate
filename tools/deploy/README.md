# deploy branch

This branch is written by GitHub Actions (`.github/workflows/deploy-build.yml` on `main`). Don't edit it by hand.

| Folder | Goes to |
|---|---|
| `stusyn/` | stusyn.com |
| `marketing/` | syndicatemarketing.co |
| `crm/` | crm.stusyn.com (includes `ads/` and `wiki/`) |

To put a build live: cPanel → **Git Version Control** → Manage → **Pull or Deploy** → **Update from Remote**, then **Deploy HEAD Commit**. `.cpanel.yml` runs `deploy.sh`, which copies each folder over the live one. It never copies `app/config.php` or anything in `storage/`, and it never deletes files. The log is in `~/.cpanel/logs/` (files named `vc_…_git_deploy.log`).

`BUILD.txt` says which commit on `main` this build came from.
