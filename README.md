# Syndicate Holdings Group — stusyn.com

> This repo also contains **Syndicate CRM** (`crm/`, deployed to crm.stusyn.com). See [`crm/README.md`](crm/README.md). Build its upload zip with `npm run package:crm`.

The website for Syndicate Holdings Group LLC, plus a careers hub with job listings, online applications and a hiring dashboard. It's built to upload to cPanel hosting.

## Deploy to cPanel

You need PHP 7.4 or newer (8.x recommended) with the `pdo_sqlite` extension on. Both are standard on cPanel. No MySQL database is needed.

1. **Get the zip.** Use `syndicate-stusyn-cpanel.zip`, or build it yourself with `npm run package` (requires Node 18+).
2. **Upload it.** In cPanel, open **File Manager → public_html → Upload**, upload the zip, then right-click it and choose **Extract**. Delete the zip afterwards. In File Manager **Settings**, turn on **Show Hidden Files** so you can see `.htaccess`.
3. **Create the email inbox.** In cPanel **Email Accounts**, create `careers@stusyn.com`. Make sure `hello@stusyn.com` exists too.
4. **Create your admin account.** Visit `https://stusyn.com/admin/` and enter the setup key from `app/config.php`. Do this right after uploading.
5. **Turn on HTTPS** once SSL is active (cPanel **SSL/TLS Status**, usually automatic). Open `.htaccess` and remove the `#` from the two "force HTTPS" lines.

The database and resumes live in `storage/` and are created automatically. That folder is blocked from the web.

### Updating the site later

Run `npm run package` and upload or extract the new zip over the old files. Your database and resumes in `storage/` aren't in the zip, so they're never overwritten.

## What's included

| Path | What it is |
| --- | --- |
| `/` `about.html` `portfolio.html` … | Marketing pages (static HTML) |
| `/careers.php` | Careers hub: live job list with filters and search |
| `/job.php?slug={role}` | Job page with an application form and resume upload, plus Google Jobs data |
| `/job.php?slug=open-application` | General application not tied to a role |

Careers links use the real PHP files, so they work on any host whether or not URL rewriting is on. Older `/careers` and `/careers/{role}` links redirect to them. Resumes over the upload limit get a clear message; if applicants with normal-size files hit it, raise `upload_max_filesize` to 10M and `post_max_size` to 12M in cPanel → **MultiPHP INI Editor**.
| `/admin/` | Hiring HQ: dashboard, applicants pipeline, jobs, messages, account |
| `contact.php` | Contact form handler: saves each message and emails hello@stusyn.com |
| `privacy.html` `cookies.html` `terms.html` `applicant-privacy.html` `accessibility.html` | Legal pages (source in `src/legal/`) |
| `/sitemap.xml` | Generated, and includes every published job |

### Hiring HQ (`/admin/`)

- **Dashboard:** new applicants, applications over the last 30 days, pipeline by stage, and applicants per role.
- **Applicants:** filter by stage or role, search, bulk-move stages, and export to CSV.
- **Applicant page:** contact details, links, answers, resume preview and download, stage, a 1–5 star rating, and private notes.
- **Jobs:** post, edit, publish, close or delete roles. Drafts are only visible to signed-in admins.
- **Messages:** contact-form inbox.
- **Account:** change your password, and add or remove admins.
- **Forgot password:** use `/admin/setup.php?reset=1` with the setup key.

### Emails

- **New application:** emailed to `careers@stusyn.com` with a link to the applicant, and a confirmation goes to the applicant.
- **Contact message:** emailed to `hello@stusyn.com`, with reply-to set to the sender.

Addresses are set in `app/config.php`. If emails land in spam, set up SPF and DKIM in cPanel under **Email Deliverability**.

### Privacy and compliance

- **Legal pages:** Privacy Policy, Cookie Policy, Terms of Use (including how submitted ideas are handled), Applicant Privacy Notice and Accessibility Statement. The "last updated" date is set in `build.mjs`. Have a lawyer review these before launch.
- **Cookie banner** with settings. Only strictly necessary cookies are used. Optional categories default to off, Global Privacy Control is honored, and any future analytics script should run only when `SyndicateConsent.get('analytics')` is true.
- **No third-party requests:** fonts are self-hosted, and there are no trackers or ad pixels.
- **Automatic deletion:** applications, resumes and contact messages are deleted after `retention_days` (730 by default, in `app/config.php`). If you change it, update the policies to match.
- **Careers:** equal opportunity and accommodation statements on every job, and a consent checkbox linked to the Applicant Privacy Notice.
- **Business address:** the policies list Spartanburg, South Carolina and email. If you get a business mailing address (a PO box or registered agent works), add it to the Contact sections of `src/legal/privacy.html` and `src/legal/terms.html`.

### Security

- A Content Security Policy only allows scripts, styles and fonts from your own domain.
- Every form has spam protection: CSRF tokens, a hidden honeypot field, a minimum fill time and rate limits.
- Admin sign-in is rate-limited, and passwords are hashed.
- Resumes are stored privately, given random file names and checked for real PDF or Word content. Only signed-in admins can download them.

## Editing

```
src/
  layout.html          shared header, nav and footer for every page (including careers)
  pages/*.html         marketing page content
  assets/              CSS, JS, favicon
  php/                 careers hub, admin, contact handler, .htaccess
    app/config.php     emails, setup key, upload limit
    app/seed-jobs.php  sample jobs loaded on first run
build.mjs              builds everything into dist/
```

Run `npm run build` to output `dist/`, or `npm run package` to also create the upload zip.

To mark text that still needs confirming, wrap it in `<span class="fill">…</span>`. It shows highlighted in pink until you replace it.

## Syndicate Marketing website (`marketing/`)

A separate, static website for Syndicate Marketing with its own cart and checkout. Products, prices, Stripe and orders live in the CRM (see `crm/README.md` → Online store).

- **Build:** `npm run package:marketing` → `syndicate-marketing-cpanel.zip`. Upload and extract it into the domain's folder in cPanel File Manager (for an addon domain, usually `/home/USERNAME/yourdomain.com`).
- **Domain:** set `siteUrl` at the top of `build-marketing.mjs` to the real domain before building, and enter the same address in the CRM under Store → Settings.
- **Site Stats:** add the domain in the CRM (Site stats → Add a website), put its key in `statsSite` in `build-marketing.mjs`, and rebuild.
- **Pages:** `marketing/pages/*.html` (JSON front matter, like the main site). `{{products:website}}`, `{{products:sku:a,b:mini}}` and `{{price:sku}}` insert product cards and prices from `crm/app/catalog.json`; the browser refreshes them from the CRM's live catalog.
- Checkout flow: cart (saved in the browser) → your details → project brief (questions change with what's in the cart) → review → Stripe Checkout → thank-you page, which confirms the payment with the CRM.

