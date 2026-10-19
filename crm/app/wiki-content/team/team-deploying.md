---
title: Deploying the sites
category: team-systems
order: 10
summary: Build, upload, extract.
---
| Site | Command | Zip |
|---|---|---|
| stusyn.com | `npm run package` | syndicate-stusyn-cpanel.zip |
| syndicatemarketing.co | `npm run package:marketing` | syndicate-marketing-cpanel.zip |
| crm.stusyn.com (+ ads and wiki) | `npm run package:crm` | syndicate-crm-cpanel.zip |

1. Run the command locally.
2. cPanel → File Manager → the site’s folder → Upload the zip → Extract, overwriting.
3. Never delete `storage/` or `app/config.php` on the server; the zips don’t include them.
4. The CRM database updates itself on the next page load.

## Subdomains

- **ads.stusyn.com** → document root: the CRM folder’s `ads` subfolder.
- **wiki.stusyn.com** → document root: the CRM folder’s `wiki` subfolder.

Run AutoSSL after creating each subdomain.
