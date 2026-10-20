# CLAUDE.md — Syndicate Holdings Group: the complete reference

This is the master reference for this repository (`syndicate`: stusyn.com, syndicatemarketing.co and the CRM with ads and wiki) and the business behind it. It covers who we are, every site we run, every system, every price, how things are built and deployed, and the rules we work by. Read it before you change anything. If something here conflicts with the code, the code is what's live, so fix this file in the same commit.

> **Building a site for a client?** Use [`templates/CLAUDE.md`](templates/CLAUDE.md). It holds the build rules for client sites (stack, design, copy, accessibility, performance, Site Stats, forms, legal, testing, delivery). This file is about *our own* sites and systems.

---

## Contents

1. [The business](#1-the-business)
2. [Non-negotiable rules](#2-non-negotiable-rules)
3. [Domains, hosting and email](#3-domains-hosting-and-email)
4. [Repository layout](#4-repository-layout)
5. [Build, package and deploy](#5-build-package-and-deploy)
6. [Site 1 — stusyn.com (main site + careers)](#6-site-1--stusyncom-main-site--careers)
7. [Site 2 — syndicatemarketing.co (agency site + store)](#7-site-2--syndicatemarketingco-agency-site--store)
8. [Site 3 — crm.stusyn.com (the CRM)](#8-site-3--crmstusyncom-the-crm)
9. [Prices — everything we sell](#9-prices--everything-we-sell)
10. [Store and Stripe](#10-store-and-stripe)
11. [Quotes and proposals](#11-quotes-and-proposals)
12. [Online booking](#12-online-booking)
13. [Site Stats (our own analytics)](#13-site-stats-our-own-analytics)
14. [Projects and the client portal](#14-projects-and-the-client-portal)
15. [Invoices, consultations, inbox, calendar](#15-invoices-consultations-inbox-calendar)
16. [Cron (the scheduled job)](#16-cron-the-scheduled-job)
17. [Databases and migrations](#17-databases-and-migrations)
18. [Security, headers and .htaccess](#18-security-headers-and-htaccess)
19. [Design systems](#19-design-systems)
20. [Copy and voice](#20-copy-and-voice)
21. [Testing](#21-testing)
22. [Git and commit conventions](#22-git-and-commit-conventions)
23. [Promises we make to customers](#23-promises-we-make-to-customers)
24. [Open items and to-dos](#24-open-items-and-to-dos)
25. [Glossary and quick lookups](#25-glossary-and-quick-lookups)

---

## 1. The business

### Syndicate Holdings Group LLC
- **What it is:** a holding company for the brands below. It is **not** an incubator, accelerator or fund, so never call it one.
- **Where:** Spartanburg, South Carolina. Fully remote; we work with clients anywhere.
- **Founded:** 2019.
- **Founder:** Grady Davis (owner and operator; does the work himself, with hires as the brands grow).
- **Public contact:** hello@stusyn.com. Public location: "Spartanburg, SC" or "Spartanburg, South Carolina", nothing more specific.

### The brands

| Brand | What it does | Where it lives | How it sells |
|---|---|---|---|
| **Syndicate Marketing** | Websites, paid ads (Google, Meta, TikTok), analytics, audits, care plans for small businesses; Discord community setup and management for game studios, creators, esports and gaming businesses | syndicatemarketing.co (own site + store); summary page on stusyn.com | Fixed-price packages through the online store, quotes for custom work, free discovery calls |
| **Creator Ops Media** | 1:1 TikTok Shop consulting | stusyn.com/creator-ops-media.html | **$150/hour** paid consultation, booked online. Moved to a consulting-only model in late 2026 |
| **Syndicate Holdings Group** | The parent; also runs Careers/hiring | stusyn.com | n/a |

`BRANDS` in the CRM is exactly: `Syndicate Marketing`, `Creator Ops Media`, `Syndicate Holdings Group`. Deals, invoices and quotes are tagged with one.

### Grady Davis (as told on the site)
Sources: `src/pages/about.html`, `src/pages/grady.html`, and the resume (`tools/resume/`, output `src/static/grady-davis-resume.pdf`).
- Retail and operations: Dollar General shift lead (2016); Circle K, ran a team of 25; security operations, about 50 officers.
- American Credit Acceptance: grew the dealer network by about 10%.
- General manager in Spartanburg (2023).
- BBA, Limestone University.
- TikTok Shop: **$10M+ GMV** driven for brands including Intake Breathing and Rap Snacks; worked with TikTok/ByteDance in 2024–25.
- **Still unconfirmed** and kept off the site until Grady confirms: his 7-Eleven end date, the start years for brand work, his Limestone graduation year, and a headshot. Don't invent these.

---

## 2. Non-negotiable rules

These apply to every change, every page and every email.

### Privacy
- **Never publish** Grady's phone number, personal email, home address or full ZIP code. Public contact is **hello@stusyn.com** only. Location is "Spartanburg, SC".
- Careers mail uses **careers@stusyn.com**. Nothing else is public.
- **Cold outreach emails** must carry a postal address (CAN-SPAM). They use the LLC's registered business address, kept only in the Project doc `claude/outreach-settings.md`. It goes in outreach email footers and nowhere else: not on the sites, in page copy or in this repo.
- Applicant data is covered by `src/legal/applicant-privacy.html`. Resumes are kept for the retention period (730 days) and served only to logged-in admins.

### Secrets
- **Never** put setup keys, passwords, API keys, Stripe keys, webhook secrets or mailbox passwords in docs, commits, chat or client-facing pages.
- They live in:
  - `src/php/app/config.php` (main site: `setup_key`, `turnstile_secret`, mail addresses, paths)
  - `crm/app/config.php` (CRM: `setup_key`, `turnstile_secret`, `site_db`, `stripe_api`)
  - Both `config.php` files are **not in git and not in the deploy zips**. They exist only on the server (and your local machine). Git holds `config.sample.php` with placeholders. The setup key must be 20+ characters and not the sample value, or setup and reset refuse it.
  - the CRM settings table (Stripe secret key and webhook secret, **encrypted** with `mail_encrypt()` using `storage/mail.key`)
  - mailbox passwords (encrypted the same way)
- `storage/` directories are blocked from the web by `.htaccess` and excluded from zips (databases, sessions, logs, keys, uploads).

### Honesty
- **No fabricated reviews, testimonials, clients, logos, case studies, stats or results.** Ever.
- Portfolio concept work is **labelled as fictional concepts**, uses `.example` domains and **555-01xx** phone numbers, and is `noindex`.
- Don't name real companies as partners or clients unless they really are. (Insurer names were removed from the dental demo for this reason.) Intake Breathing and Rap Snacks are real past work and may be named.
- Don't promise what we can't do: no guaranteed rankings, no guaranteed ROAS, no "#1".
- Prices on the sites come from `crm/app/catalog.json` through the build, so they can't drift. Never hard-code a price in page copy; use the template tags.

### Model identifiers
- Never put an AI model name or ID in commits, PRs, code comments or pushed files.

---

## 3. Domains, hosting and email

| Domain | What | Document root | Deployed from |
|---|---|---|---|
| **stusyn.com** | Main site, careers, Hiring HQ admin | cPanel `public_html` (or the stusyn.com root) | `syndicate-stusyn-cpanel.zip` |
| **crm.stusyn.com** | CRM, store API, Stripe webhook, Site Stats, client portal, booking, proposals | cPanel subdomain folder, e.g. `/home/USERNAME/crm.stusyn.com` | `syndicate-crm-cpanel.zip` |
| **syndicatemarketing.co** | Syndicate Marketing site, cart and checkout | its own cPanel addon-domain folder | `syndicate-marketing-cpanel.zip` |
| **wiki.stusyn.com** | Help center (public) + Team docs (staff) | the CRM folder's `wiki/` subfolder, e.g. `/home/USERNAME/crm.stusyn.com/wiki` | `syndicate-crm-cpanel.zip` (same zip as the CRM) |
| **ads.stusyn.com** | Ads management: staff admin + client ads portal | the CRM folder's `ads/` subfolder, e.g. `/home/USERNAME/crm.stusyn.com/ads` | `syndicate-crm-cpanel.zip` (same zip as the CRM) |

- **Hosting:** cPanel shared hosting (Apache or LiteSpeed), **PHP 7.4+** with PDO SQLite, OpenSSL and cURL or `allow_url_fopen`. No Node on the server; Node is only used locally to build.
- **All sites are live** as of October 2026.
- **HTTPS:** enforced by `.htaccess` (HTTP → HTTPS, `www.` → apex), with `/.well-known/` excluded so AutoSSL renewals keep working. HSTS `max-age=31536000` (plain; no `includeSubDomains`/`preload`, and no `expr=` because LiteSpeed doesn't support it).

### Email addresses

| Address | Used for |
|---|---|
| **hello@stusyn.com** | Everything customer-facing: contact forms, store order emails, quotes, booking emails, invoices, CRM `mail_from`, store `shop_notify` |
| **careers@stusyn.com** | Careers only: application receipts and applicant messages (`careers_mail_from`, name "Syndicate Careers") |

- Main-site config: `mail_from` = hello@ and `careers_mail_from` = careers@. (An earlier bug sent purchase emails from careers@; that's fixed, so keep them separate.)
- Mail is sent with PHP `mail()` (sendmail) on the server, or by SMTP for inbox replies in the CRM.
- **To do:** SPF and DKIM for stusyn.com in cPanel → Email Deliverability.

---

## 4. Repository layout

```
/
├── CLAUDE.md                  ← this file
├── README.md                  ← deploy guide for the main site
├── package.json               ← build/package scripts (no dependencies)
├── build.mjs                  ← builds stusyn.com → dist/
├── build-marketing.mjs        ← builds syndicatemarketing.co → dist-marketing/
├── src/                       ← stusyn.com source
│   ├── layout.html            ← shared shell (head, nav, footer, consent, ⌘K)
│   ├── pages/*.html           ← pages (JSON front matter in a leading comment)
│   ├── legal/*.html           ← legal pages
│   ├── assets/                ← css, js, fonts (self-hosted), images
│   ├── static/                ← copied as-is (robots.txt, resume PDF)
│   └── php/                   ← careers + contact + Hiring HQ (PHP, SQLite)
│       ├── .htaccess          ← main-site .htaccess (headers, redirects, CSP)
│       ├── careers.php, job.php, contact.php, sitemap.php
│       ├── admin/             ← Hiring HQ
│       ├── app/               ← bootstrap.php, config.php, seed-jobs.php, views/
│       └── storage/           ← SQLite DB, resumes, sessions (web-blocked)
├── marketing/                 ← syndicatemarketing.co source
│   ├── layout.html, pages/, legal/
│   ├── assets/css/sm.css, assets/js/{cards,sm,checkout}.js, assets/work/*.jpg
│   ├── work/                  ← 6 fictional demo sites (+ .htaccess: noindex, Google Fonts allowed)
│   ├── work.json              ← portfolio data (concepts, ad campaigns)
│   ├── work-render.mjs        ← renders the case-study pages
│   └── htaccess.txt           ← becomes dist-marketing/.htaccess
├── crm/                       ← crm.stusyn.com (PHP, SQLite, deployed as-is)
│   ├── README.md              ← CRM feature + setup docs
│   ├── *.php                  ← pages and endpoints (see §8)
│   ├── a.js, t.php            ← Site Stats tracker + collector
│   ├── ads/                   ← ads.stusyn.com (document root of that subdomain; see “Ads management”)
│   ├── wiki/                  ← wiki.stusyn.com (document root; see “Help center & wiki”)
│   ├── app/                   ← bootstrap.php (DB + migrations + helpers), catalog.json,
│   │                            shop.php, quotes.php, booking.php, portal.php, analytics*.php,
│   │                            imap.php, smtp.php, mail.php, mime.php, ics.php, mmdb.php,
│   │                            invoice-doc.php, recurring.php, partials.php, views/
│   └── storage/               ← crm.sqlite, analytics.sqlite, mail, portal uploads, keys (web-blocked)
├── templates/CLAUDE.md        ← build rules for CLIENT sites
└── tools/
    ├── resume/build-pdf.cjs   ← builds Grady's resume PDF
    ├── work-shots.cjs         ← screenshots the demo sites for the portfolio
    └── video/                 ← TikTok video engine + PLAYBOOK.md (see "TikTok videos" in §24)
```

Ignored by git: `dist/`, `dist-crm/`, `dist-marketing/`, `*.zip`, CRM databases, sessions, backups and logs, the root resume PDF, and `marketing/assets/favicon.svg` (generated).

---

## 5. Build, package and deploy

No frameworks, no npm dependencies. Node 18+ locally.

| Command | Does |
|---|---|
| `npm run build` | Builds stusyn.com into `dist/` |
| `npm run package` | Build + `syndicate-stusyn-cpanel.zip` (skips storage DBs, resumes, sessions, logs) |
| `npm run build:marketing` | Builds syndicatemarketing.co into `dist-marketing/` |
| `npm run package:marketing` | Build + `syndicate-marketing-cpanel.zip` (includes `.htaccess`) |
| `npm run package:crm` | Copies `crm/` to `dist-crm/`, adds fonts, zips `syndicate-crm-cpanel.zip` (skips DBs, sessions, backups, logs, mail, mail.key, portal uploads, `.mmdb`) |
| `npm run resume:pdf` | Rebuilds the resume PDF (uses Playwright/Chromium) |
| `npm run demos` | Regenerates the portfolio demo sites in `marketing/work/` from `marketing/demos/*.mjs` (then run `node tools/work-shots.cjs`) |

### Automatic deploys (GitHub Actions → FTPS) — the usual way
- **Every push to `main`** runs `.github/workflows/deploy-build.yml` (“Deploy to cPanel”): it builds all three sites (Turnstile site key from the repo variable `TURNSTILE_SITE`; empty = no widget, which matches what's live) and uploads them with `tools/deploy/ftp-deploy.sh` (lftp over FTPS). It can also be run by hand from the Actions tab, with a “check only” box that finds the folders and uploads nothing.
- **What the upload does:** finds each live folder by looking for the site's own files (CRM: `app/config.php` + `portal.php`; stusyn.com: `app/config.php` + `careers.php`; marketing: `checkout.html`) in the usual cPanel folder names, or uses the repo variables `CRM_DIR`, `STUSYN_DIR`, `MARKETING_DIR` (paths from the FTP account's home). It stops before uploading anything if a folder can't be found or two sites point at one folder, and lists the folders it can see. Then it uploads like extracting the zips: adds and replaces files, **deletes nothing**, never uploads `app/config.php` or `storage/` data.
- **Secrets** (GitHub → repo Settings → Secrets and variables → Actions): `FTP_SERVER` (the hosting server's own hostname, ending in `.web-hosting.com`, from cPanel → Server Information; the FTP certificate is issued to `*.web-hosting.com`, so `stusyn.com` or `ftp.stusyn.com` fail the certificate check), `FTP_USERNAME`, `FTP_PASSWORD`. Use a dedicated cPanel FTP account whose directory is the home folder. Without them the workflow only builds. Set the variable `FTP_VERIFY_CERT=no` only if the host's certificate doesn't match the server name.
- cPanel's Git Version Control isn't used: this host doesn't allow SSH, so it can't clone a private repo.
- The zips below still work as a fallback.

### Deploying with zips (cPanel)
1. Run the package command.
2. cPanel → File Manager → the domain's folder → Upload the zip → Extract, overwriting files.
3. Storage, databases and `app/config.php` survive because the zips don't include them. **Don't delete `storage/` or `config.php` on the server.**
4. First install only: copy `app/config.sample.php` to `app/config.php` on the server and fill it in, then visit `/admin/setup.php` (main site) or `/setup.php` (CRM) with the setup key to create the first login.
5. **Forgot a password:** `setup.php?reset=1` (CRM) or `/admin/setup.php?reset=1` (Hiring HQ). The setup key plus the account email sends a one-time link (30 minutes) to that inbox; the key alone can't change a password. A reset or password change signs out every other session.
6. The CRM's database migrates itself on the next page load (`PRAGMA user_version`).

### `build.mjs` (stusyn.com)
- The `site` config object holds:
  - `email` hello@stusyn.com, `careers` careers@stusyn.com
  - `legalUpdated` 'October 17, 2026'
  - `turnstileSite` (Turnstile site key, or `TURNSTILE_SITE` env; empty = no widget)
  - `statsSrc` `https://crm.stusyn.com/a.js`, `statsSite` `t4xJf60nIgv4`
  - `onePagePrice` '$350', `onePageAmount` '350'
  - `smSite` `https://syndicatemarketing.co`
  - `bookConsult` `https://crm.stusyn.com/book.php?type=tiktok-shop-consultation`
  - `smCheckout` `https://syndicatemarketing.co/checkout.html?add=web-one-page&utm_source=stusyn.com&utm_campaign=one-page`
  - `arrow` (the SVG arrow)
- Each page has JSON front matter (`title`, `description`, `robots`, etc.) in a leading `<!-- { … } -->`, gets wrapped in `src/layout.html`, and has `{{key}}` placeholders filled from `site`.
- The PHP careers views are generated from the same layout so careers pages match the static site.
- Copies `src/php/` (with its `.htaccess`), assets, fonts and `src/static/`.

### `build-marketing.mjs` (syndicatemarketing.co)
- Config:
  - `siteUrl` (`SM_SITE_URL` env, default `https://syndicatemarketing.co`)
  - `crm` (`SM_CRM`, default `https://crm.stusyn.com`)
  - `email` hello@stusyn.com
  - `bookDiscovery` / `bookKickoff` (CRM `book.php?type=discovery-call` / `kickoff`)
  - `statsSite` (`SM_STATS`, default `UIOdnDz160w8`)
  - `legalUpdated` ('October 18, 2026'), `arrow`, `check`
- Reads `crm/app/catalog.json` (prices and products) and `marketing/work.json` (portfolio), and renders product cards with `marketing/assets/js/cards.js` (the same code the browser uses, loaded through `new Function`), so the build and the live cart always agree.
- **Template tags:**

| Tag | Output |
|---|---|
| `{{products:website}}` | All product cards in a category (`website`, `ads`, `care`, `addon`, `service`) |
| `{{products:sku:a,b:mini}}` | Specific SKUs, optional `mini` style |
| `{{price:sku}}` | Formatted price, e.g. `$850` or `$300/mo` |
| `{{amount:sku}}` | Raw number |
| `{{work:grid}}` / `{{work:grid:2}}` | Portfolio grid (all, or the first N) |
| `{{features:sku}}` | The product's feature list as `<li>` items with checkmarks |
| `{{offer:slug:field}}` | From `catalog.json` → `offers`: `today`, `monthly`, `value`, `save`, `trialvalue` (money), `trial` (free months), `ends` (date), `spots`, `list.sku` / `price.sku` |

  Tags also work inside page titles and descriptions.
- Front matter can add `"styles": ["x.css"]` (loaded in the head) and `"scripts": ["x.js"]`; `"robots"` makes a page `noindex` and keeps it out of the sitemap.
- Generates `work-<slug>.html` case pages, copies the `marketing/work/` demos, and writes `sitemap.xml`, `robots.txt` and `.htaccess`.
- **The build fails if any `{{…}}` placeholder is left unfilled**, so a typo can't ship.

---

## 6. Site 1 — stusyn.com (main site + careers)

**Purpose:** the parent company site. It introduces Grady and the brands, sells the $350 one-page website (through the Syndicate Marketing checkout), books Creator Ops consultations, and runs careers/hiring.

### Pages (`src/pages/`)

| Page | What's on it |
|---|---|
| `index.html` | Home: holding company intro, brands, proof ($10M+ GMV), CTAs |
| `about.html` | Company story, founded 2019, Spartanburg, remote, not an incubator |
| `grady.html` | Grady's profile/career, resume download |
| `portfolio.html` | Our work and brand results |
| `creator-ops-media.html` | TikTok Shop consulting, **$150/hr**, "Book a consultation" → CRM booking (`tiktok-shop-consultation`) |
| `syndicate-marketing.html` | Summary of the agency, links to syndicatemarketing.co |
| `one-page-website.html` | **$350** one-page website offer; "Order" button → syndicatemarketing.co checkout with `?add=web-one-page` |
| `ideas.html` | Ideas / insights page |
| `contact.html` | Contact form (posts to `contact.php`) |
| `order-thanks.html` | Legacy thank-you page (kept for old links) |
| `404.html` | Not found |

Legal (`src/legal/`): `privacy.html`, `cookies.html`, `terms.html` (section 7 covers one-page website orders placed on syndicatemarketing.co), `applicant-privacy.html`, `accessibility.html`. Last updated **October 17, 2026** (Turnstile disclosure).

### PHP (`src/php/`)
- `careers.php`: careers hub with open roles. `job.php`: role page plus application form (resume upload up to **8 MB**; PDF/DOC/DOCX).
- Seeded jobs (`app/seed-jobs.php`): **Social Media Manager**, **Paid Social Specialist**, **Open application**.
- `contact.php`: contact form handler (rate-limited by `attempts`, honeypot, saves to `messages`, emails hello@). The CRM imports these messages as leads (see §8, "Website sync").
- `sitemap.php`: dynamic sitemap including open jobs.
- **Removed:** `order.php` (the old $500 Stripe payment link flow). `.htaccess` 301-redirects it to `/one-page-website.html#order`.
- Database tables (`storage/*.sqlite`): `admins`, `jobs`, `applications`, `messages`, `attempts`.

### Hiring HQ (`/admin/`)
- `index.php` dashboard
- `applications.php`, `application.php` (review, status, notes, resume)
- `jobs.php`, `job-edit.php`
- `messages.php` (contact form inbox)
- `account.php`, `export.php` (CSV), `resume.php` (secure resume download)
- `login.php`, `logout.php`, `setup.php`
- Applicants get a receipt from careers@. Retention: **730 days**, then data is purged.

### Main-site config keys (`src/php/app/config.php`, values not listed here)
`site_url`, `base_path`, `careers_to`, `contact_to`, `mail_from`, `mail_from_name`, `careers_mail_from`, `careers_mail_from_name`, `storage_dir`, `setup_key`, `retention_days` (730), `max_upload_mb` (8), `pretty_urls`, `timezone`.

### Site features
- **Cookie consent v2:** analytics on by default with a clear notice and opt-out; **Global Privacy Control (GPC) is honored**, so the GPC signal turns analytics off.
- **⌘K / Ctrl+K quick nav** across pages.
- **Site Stats** loaded through a `syn-stats` meta tag (site key `t4xJf60nIgv4`), respecting consent.
- The CSP allows `https://crm.stusyn.com` for scripts and connections (Site Stats, booking links).

---

## 7. Site 2 — syndicatemarketing.co (agency site + store)

**Purpose:** Syndicate Marketing's own site, where people can buy fixed-price packages outright with an advanced cart and Stripe checkout, see the portfolio, contact us or book a free discovery call. Everything flows into the CRM automatically.

### Pages (`marketing/pages/`)

| Page | What's on it |
|---|---|
| `index.html` | Home: hero, services, packages, portfolio preview, process, FAQ |
| `care.html` | Website care: sample report, Care vs Care Plus table, both plan cards, “Didn’t build it with us?” onboarding steps, FAQ |
| `community.html` | Community management (in the menu): example Discord server mock (labelled as an example), the four audiences, both monthly plans, setup and audit, what we do each week, server setup detail, studio website + press kit, “How we work” rules, FAQ. Styles `assets/css/community.css` |
| `gaming-communities.html` | **Gaming campaign landing page**, `noindex`, not in the menu or sitemap, header shows only logo and cart. The email link picks the pitch: `?t=studio`, `creator`, `esports` or `business` changes the headline and lede (studios also see the website + press kit cards); `b=` and `n=` greet them by business and first name (plain text). Uses `offer.css` + `community.css` and `assets/js/gaming.js` |
| `websites.html` | The 4 website packages, add-ons, care plan |
| `paid-ads.html` | Ads plans (1, 2 or 3 platforms), ad account setup, ads audit |
| `analytics.html` | Site Stats, analytics setup, site audit |
| `pricing.html` | Every product and price, comparison table |
| `work.html` + `work-<slug>.html` | Portfolio grid + 6 case pages (fictional concepts, clearly labelled) |
| `about.html` | Who we are (part of Syndicate Holdings Group, Grady) |
| `contact.html` | Lead form → CRM `shop-api.php?a=lead` (creates contact + deal + task) |
| `checkout.html` | 4-step checkout (see below) |
| `thank-you.html` | Polls the order API until Stripe confirms, then shows next steps and fires the `Purchase` goal once |
| `starter-website-offer.html` | **Email-campaign landing page** for the `starter-500` offer (see “Special offers” in §10). `noindex`, not in the menu or sitemap; the header shows only the logo and cart. Personalize the link with `?b=Business+Name&n=FirstName` (shown as plain text). Prices, spots left and the end date refresh live from the CRM; when the offer ends or fills, the page says so and the buttons go to the regular packages. Styles `assets/css/offer.css`, script `assets/js/offer.js` (front matter `styles` / `scripts`) |
| `404.html` | Not found |

Legal (`marketing/legal/`): `terms.html` (terms of sale) and `privacy.html`.

### Cart and checkout (`assets/js/sm.js`, `checkout.js`, `cards.js`)
- Cart lives in `localStorage` key **`sm_cart`**. Any link with `?add=<sku>` starts or adds to the cart (this is how stusyn.com's one-page button works).
- **Rules:** one website package per order, one ads plan per order, one care plan per order. Add-ons with `requires` need their parent (e.g. `add-page` needs a website, `add-ads-setup` needs an ads plan). Per-page add-ons have a quantity.
- Visit source and UTM data are kept in `sessionStorage` **`sm_src`** and sent with the order (the CRM records the deal source, e.g. `Marketing site — checkout`).
- Checkout steps: **1 Cart → 2 Your details → 3 Project details → 4 Review & pay**. Pay sends the customer to Stripe Checkout (hosted). The in-progress form is kept in `sessionStorage` `sm_checkout`; the order reference is kept in `sm_order`.
- After paying, Stripe returns them to `thank-you.html?ref=…&k=…&session_id=…`, which polls `shop-api.php?a=order` and shows paid, processing (bank payments) or still waiting.
- Prices and products load live from `shop-api.php?a=catalog`, with the built-in catalog as a fallback.

### Portfolio (`marketing/work.json`, `marketing/work/`)
All six are **fictional concepts**, labelled as such, with `.example` domains and 555-01xx numbers, and `noindex`.

| Concept | Business | Shows off | Demo folder |
|---|---|---|---|
| Hearth & Grain | Bakery | One-page website (single hand-written page) | `work/hearth-and-grain/` |
| Ridgeline Heating & Air | HVAC | Starter website (5 pages) + Google/Meta ads | `work/ridgeline-air/` |
| Pine Street Dental | Dental practice | Business website (10 pages + blog) + ads | `work/pine-street-dental/` |
| Loom & Lantern | Candle shop | Online store (collections, products with sizes and stock, bundles, cart, demo checkout that stops before payment) + TikTok/Meta ads | `work/loom-and-lantern/` |
| Fernhollow Interactive | Indie game studio | Business website (9 pages: games, team, news + posts, careers with pay ranges, press, contact) + Meta/Google ads; links to its game's site | `work/fernhollow/` |
| Lampwright | Fernhollow's upcoming co-op game | Starter website (5 pages: home, the game, media gallery, community with Discord/playtest/roadmap) + press kit add-on + TikTok/Meta ads | `work/lampwright/` |

The two gaming concepts (Fernhollow, Lampwright) back the gaming campaign and `community.html`: they're unreleased or small, so they show no reviews, press quotes, awards, wishlist or sales numbers, and the Lampwright art (key art, “screenshots”) is SVG drawn in `marketing/demos/lampwright.mjs` and labelled as illustration. Each has a live demo site, desktop and mobile screenshots (`assets/work/*-desktop.jpg`, `*-mobile.jpg`, made with `node tools/work-shots.cjs`), and mock ad campaigns (ad creatives, targeting, an illustrative results panel labelled as example figures).

### Site Stats
- Tracker: `<script defer src="https://crm.stusyn.com/a.js" data-site="UIOdnDz160w8"></script>`
- Goals the site fires: **Purchase** (with value and ref), **Checkout started**, **Add to cart**, **Contact form sent**. Add these as goals in CRM → Site stats → syndicatemarketing.co.

---

## 8. Site 3 — crm.stusyn.com (the CRM)

**Purpose:** the private back office for all the brands, plus the public endpoints that power the store, booking, proposals, invoices, client portal and Site Stats. Plain PHP + SQLite. Login required for everything except the public endpoints. Full docs: `crm/README.md`.

### Modules (admin pages)

| Module | Files | What it does |
|---|---|---|
| Care | `care.php`, `care-site.php` | Website care plans and monitoring (see “Website care”) |
| Dashboard | `index.php` | Today: tasks due, upcoming consultations/bookings, open deals, unpaid invoices, store orders, stats; “Email me this week’s summary” (see §16) |
| Inbox | `inbox.php`, `email.php`, `compose.php`, `mailboxes.php`, `email-file.php`, `email-img.php` | IMAP/SMTP email inside the CRM, linked to contacts and deals; passwords encrypted with `storage/mail.key` |
| Pipeline | `deals.php`, `deal.php`, `deal-edit.php` | Deals by stage: **New lead → Contacted → Qualified → Proposal → Won / Lost** |
| Companies | `companies.php`, `company.php`, `company-edit.php` | Organizations |
| Contacts | `contacts.php`, `contact.php`, `contact-edit.php` | People, with a timeline of activities (note, call, email, meeting) |
| Tasks | `tasks.php` | To-dos with due dates and priority, linked to records |
| Projects | `projects.php`, `project.php`, `project-edit.php`, `project-file.php` | Client projects with steps, updates and files, shared with the client portal |
| Outreach | `outreach.php` | Prospects the outreach agent found and emailed, replies waiting on you, settings (mailbox, postal address, warm-up), agent key, do-not-contact list; see “Outreach (cold email)” |
| Store | `store.php`, `store-order.php`, `store-products.php`, `store-product.php`, `store-offers.php`, `store-settings.php` | Orders from syndicatemarketing.co, product/price editor, special offers, Stripe keys, promo/tax/notify settings |
| Quotes | `quotes.php`, `quote-edit.php`, `quote.php` | Proposals with optional items and deposits, paid online |
| Bookings | `bookings.php`, `booking.php`, `booking-setup.php`, `booking-type.php` | Online scheduling: meeting types, hours, blocks, bookings |
| Consultations | `consultations.php`, `consultation-edit.php` | Creator Ops consults: date, length, rate ($150 default), status, billable, invoice link |
| Invoices | `invoices.php`, `invoice.php`, `invoice-edit.php` | Invoices (`SYN-1001`…), recurring schedules, payment links, PDF-style print, email |
| Site stats | `analytics.php`, `site.php`, `site-edit.php`, `site-export.php` | Privacy-first analytics for all our and our clients' sites |
| Calendar | `calendar.php` | Private ICS feed (token-protected) of consultations, bookings and tasks |
| Search | `search.php` | Search across everything |
| Settings | `settings.php` | Business details, invoice numbering, rates, mail, booking link, calendar token |
| Export | `export.php` | CSV exports |
| Wiki | `wiki/*` on wiki.stusyn.com (menu item “Wiki ↗”) | Help center editor, Team docs, client questions; see “Help center & wiki” |
| Ads | `ads/*` on ads.stusyn.com (menu item “Ads ↗”) | Ads management for every ads client; see “Ads management (ads.stusyn.com)” |
| Auth | `login.php`, `login-2fa.php`, `logout.php`, `setup.php` | Login (rate-limited), two-step sign-in, first-user setup and emailed password reset |

### Public endpoints (no login)

| Endpoint | Used by |
|---|---|
| `a.js`, `t.php` | Site Stats tracker script and collector |
| `shop-api.php?a=catalog` / `offer` / `checkout` / `order` / `lead` | syndicatemarketing.co cart, offers, checkout, thank-you page and contact form (CORS limited to the shop site; `catalog` and `offer` are public) |
| `stripe-webhook.php` | Stripe events (store orders, quotes, paid bookings, renewals, refunds) |
| `i.php` | Public invoice view/pay link (`?k=` token) |
| `q.php` | Public proposal: view, choose options, accept, pay deposit, decline |
| `book.php` | Public booking page (`?type=discovery-call`, `kickoff`, `tiktok-shop-consultation`) |
| `care-report.php` | Monthly care report web page (signed link) |
| `outreach-api.php` | Outreach agent API (key-protected: `Authorization: Bearer <key>` or `X-Api-Key`): `status`, `check`, `add`, `pending`, `respond` |
| `u.php` | Unsubscribe from outreach emails (`?t=` token; one-click POST from mail apps) |
| `portal-ads.php` | One-click hop from the client portal into the ads portal (2-minute one-time link) |
| `portal.php`, `portal-care.php`, `portal-login.php`, `portal-logout.php`, `portal-project.php`, `portal-file.php`, `portal-stats.php`, `portal-plans.php` | Client portal (magic-link login); `portal-login.php?to=plans` opens Plans & billing |
| `portal-demo.php` (and `ads/portal-demo.php`) | Demo client portal with sample data for prospects (see “Demo client portal” in §14) |
| `api.php` | Internal JSON helpers |
| `cron.php` | CLI only (404 on the web) |

### CRM config keys (`crm/app/config.php`, values not listed here)
`app_url`, `ads_url` (https://ads.stusyn.com), `wiki_url` (https://wiki.stusyn.com), `base_path`, `storage_dir`, `setup_key`, `psi_api` (testing only), `site_db` (path to stusyn.com's SQLite DB, for website lead sync), `timezone`, `stripe_api` (Stripe API base URL; only changed for testing).

### CRM settings (Settings table; edit in the app)
- **Business:** `business_name` (Syndicate Holdings Group LLC), `business_email` (hello@stusyn.com), `business_location` (Spartanburg, South Carolina), `business_website` (stusyn.com), `default_rate` (150)
- **Invoices:** `invoice_prefix` (SYN-), `invoice_next` (1001), `invoice_terms_days` (14), `invoice_footer`, `payment_instructions`, `payment_link_default`
- **Mail:** `mail_from` (hello@stusyn.com)
- **Calendar:** `calendar_token`
- **Website sync:** `site_last_message_id`, `site_last_sync`
- **Store:** `shop_site_url`, `shop_promo` (1 = promo codes allowed at Stripe), `shop_tax` (0 = off; 1 = Stripe automatic tax), `shop_notify` (hello@stusyn.com), `stripe_secret` (encrypted), `stripe_webhook_secret` (encrypted)
- **Quotes:** `quote_prefix` (Q-), `quote_next` (1001), `quote_valid_days` (30), `quote_terms`
- **Booking:** `booking_hours` (JSON; Mon–Thu 09:00–17:00, Fri 09:00–15:00), `booking_notice_hours` (12), `booking_window_days` (30), `booking_buffer` (15 min), `booking_step` (30 min), `booking_meeting_url` (your video link)
- **Analytics:** `analytics_retention_days` (760)
- **Care:** `psi_key` (Google PageSpeed API key, encrypted), `care_report_<siteId>` (last month emailed)
- **Ads:** `ads_access_email`, `ads_google_manager`, `ads_meta_business`, `ads_tiktok_bc` (shown to clients on their Setup page; edit at ads.stusyn.com → Access details)

### Lists and statuses (constants in `crm/app/bootstrap.php`)
- **Deal stages:** new (New lead), contacted, qualified, proposal, won, lost
- **Deal sources:** Website · Website — consultation · Website — order · Marketing site · Marketing site — order · Marketing site — checkout · Referral · TikTok · Instagram · LinkedIn · Email · Event · Cold outreach · Existing client · Other
- **Activity kinds:** note, call, email, meeting
- **Consultation statuses:** scheduled, completed, cancelled, no_show
- **Booking statuses:** confirmed, pending (awaiting payment), cancelled, expired (not paid)
- **Quote statuses:** draft, sent, viewed, accepted (awaiting payment), won, declined, expired
- **Invoice statuses:** draft, sent, paid, void
- **Recurring repeats:** week, month, quarter, year
- **Project statuses:** planning, active (in progress), review, paused (on hold), done

### Website sync
The CRM reads stusyn.com's contact-form `messages` table (path in `site_db`) and turns new messages into contacts, deals (source "Website") and tasks. It runs from the dashboard button and from cron.

---

## 9. Prices — everything we sell

**Pricing model: freelancer-level.** Fixed, published, honest prices. One-off prices are paid upfront at checkout; monthly plans bill every month through Stripe subscriptions until cancelled (30 days' notice for ads). **Ad spend is always paid by the client directly to the ad platforms, never through us.**

Source of truth: **`crm/app/catalog.json`**. It seeds the CRM `products` table (migration v7) and builds the marketing site. Once the site is live, prices are edited in **CRM → Store → Products**, and the live cart reads from the CRM. If you change a price, change `catalog.json` too so the static pages and fallback match.

### Websites (one-off)

| SKU | Product | Price | Timeline | What's included |
|---|---|---|---|---|
| `web-one-page` | One-page website | **$350** | ~2 weeks | One long-scroll page, mobile-first; contact/quote form to your inbox; basic SEO (titles, descriptions, sitemap); Site Stats with live dashboard; 2 rounds of changes |
| `web-starter` | Starter website | **$850** | ~3 weeks | Up to 5 pages (Home, About, Services, Contact + 1); copy polish; lead forms, click-to-call, map; on-page SEO + Google Business Profile link; Site Stats with goals; 2 rounds of changes |
| `web-business` | Business website | **$1,600** | ~4–5 weeks | Up to 10 pages + blog; edit text/posts yourself; ads landing page; full on-page SEO + speed tuning; Site Stats with goals + campaigns; 3 rounds of changes |
| `web-store` | Online store | **$2,500** | ~6 weeks | Store design + up to 6 content pages; first 25 products loaded; checkout with card, Apple Pay, Google Pay; shipping, tax, order emails; Site Stats with sales/checkout tracking; 3 rounds of changes |

### Paid ads management (monthly)

| SKU | Product | Price | What's included |
|---|---|---|---|
| `ads-one` | One platform (Google, Meta or TikTok) | **$300/mo** | Setup, targeting, budgets; new creative monthly; conversion tracking; results in Site Stats; monthly report + check-in call |
| `ads-two` | Two platforms | **$550/mo** | All of the above + retargeting between platforms |
| `ads-all` | Google, Meta & TikTok | **$800/mo** | All three; creative includes short video; cross-platform retargeting; budget shifted to what's working |

All ads plans: cancel any time with **30 days' notice**. First ads live in about 2–3 weeks.

### Care (monthly)

| SKU | Product | Price | What's included |
|---|---|---|---|
| `care-plan` | Website care plan | **$49/mo** | Uptime checks every 15 min with down/recovery alerts (us + client); SSL and domain renewal watch; weekly security and broken-link scan; updates and weekly backups logged in the portal; 1 hour of edits a month requested and tracked in the portal; monthly care report (uptime, speed, security, visitors, work done); cancel online any time. See “Website care” in §14. Works for sites we didn’t build. |
| `care-plus` | Care Plus | **$99/mo** | Everything in the care plan; 2 hours of edits a month; requests started within one business day (care plan: two); a speed and search tune-up every quarter guided by PageSpeed; a yearly 30-minute review call; cancel online any time |

### Community (Discord) — prices confirmed by Grady October 19, 2026

| SKU | Product | Price | What's included |
|---|---|---|---|
| `com-discord-setup` | Discord server setup | **$300** | ~1 week. Channels and categories, safe roles and permissions, welcome screen, onboarding and verification, rules + AutoMod + mod log, bots (moderation, welcome, self-roles, tickets, stream/video alerts), icon and welcome graphics, staff guide, 1 round of changes |
| `com-audit` | Community audit | **$125** | 7 days. Permissions and security (raids, spam), onboarding, channel layout, bots and AutoMod, engagement; video walkthrough + written plan |
| `com-manage` | Community management | **$350/mo** | Moderation check-ins every weekday (AutoMod and bots between), reports handled within one business day, announcements, one event a month, upkeep, monthly report; cancel online any time |
| `com-manage-plus` | Community management Plus | **$650/mo** | Everything in Community management, check-ins every day incl. weekends, two events a month, we recruit/train/coordinate volunteer mods, monthly round-up of what members ask for, monthly 30-minute strategy call |

Rules: one community management plan per order (setup and audit can be bought with it). Community plans end at the end of the paid month (no 30-day notice; that's ads only). We never buy members or fake activity, don't promise member numbers, don't claim 24/7 coverage, and the client always stays the server owner (we use a staff role on our own account and remove it when the plan ends). Checkout asks who the community is for, its name, the Discord link and size (`com_kind`, `com_name`, `com_link`, `com_size` in the brief). A paid order creates a project per community product plus a “Get Discord access” task; `community_report_tasks()` (cron, inside `shop_cleanup()`, days 1–5) adds one task per live plan per month to write the client's community report and post it in their portal.

### Add-ons (one-off)

| SKU | Product | Price | Needs |
|---|---|---|---|
| `add-ads-setup` | Ad account setup (accounts, Meta pixel, Google tag, TikTok pixel, conversions) | **$150** | an ads plan |
| `add-page` | Extra page | **$75 per page** | a website |
| `add-copy` | Copywriting | **$50 per page** | a website |
| `add-logo` | Logo design | **$200** | — |
| `add-gbp` | Google Business Profile setup | **$100** | — |
| `add-rush` | Rush delivery | **$150** | a website |
| `add-presskit` | Press kit page (fact sheet, description, screenshots, trailer, logos, press contact) | **$150** | a website |

### Services (one-off)

| SKU | Product | Price | Delivery | What's included |
|---|---|---|---|---|
| `svc-ads-audit` | Paid ads audit | **$150** | 7 days | Up to 2 ad accounts; recorded video walkthrough; written action plan ranked by impact |
| `svc-site-audit` | Website & SEO audit | **$150** | 7 days | Speed + Core Web Vitals; SEO/visibility; design + conversion review; video + written plan |
| `svc-analytics` | Analytics setup | **$125** | 5 days | Site Stats on any platform (WordPress, Shopify, Squarespace, Wix…); goals; live portal dashboard; monthly report; 30-day review call |
| `svc-strategy` | Marketing strategy session | **$75** | 7 days | One-hour video call; written notes + next steps; **price credited if they buy a package within 30 days** |

### Creator Ops Media

| Service | Price | How |
|---|---|---|
| 1:1 TikTok Shop consultation | **$150/hour** | Book and pay online: `crm.stusyn.com/book.php?type=tiktok-shop-consultation` (60 min, paid via Stripe at booking) |

### Free

| Service | Price | How |
|---|---|---|
| Discovery call (Syndicate Marketing) | Free, 20 min | `book.php?type=discovery-call` |
| Kickoff call (for paying clients) | Free, 30 min, private link | `book.php?type=kickoff` (link sent in order/quote emails) |

### Price history
- The old one-page offer was **$500** through a Stripe Payment Link (`order.php`). It is now **$350** through the store. **Deactivate the old $500 Payment Link in Stripe.**
- Migration v8 moved the store to freelancer pricing, updating only prices that hadn't been edited by hand.

### Product data shape (`catalog.json`)
Each product has `sku`, `category` (`website`/`ads`/`care`/`community`/`addon`/`service`), `name`, `blurb`, `price`, `interval` (`month` or empty), `unit` (e.g. "per page"), `features[]`, `steps[]` (become project steps), `timeline_days`, `site` (1 = creates a Site Stats site), `requires` (`website`/`ads`), plus display fields.

---

## 10. Store and Stripe

### How a purchase flows
1. Customer builds a cart on syndicatemarketing.co → `checkout.html`.
2. `shop-api.php?a=checkout` validates the cart against the **CRM's** products and prices (never trusts browser prices), creates an `orders` row (status pending), and creates a **Stripe Checkout Session** with inline `price_data`:
   - mode **`subscription`** if any item is monthly (one-off items go on the first invoice),
   - otherwise mode **`payment`** with `invoice_creation` on (so Stripe sends a proper invoice/receipt),
   - promo codes allowed if `shop_promo`, automatic tax if `shop_tax`,
   - metadata carries the order ref and kind.
3. Customer pays on Stripe's hosted page and returns to `thank-you.html`.
4. **`stripe-webhook.php`** gets `checkout.session.completed` and marks the order paid. Then it **automates everything**:
   - contact + company created/updated,
   - deal created as **Won** (source "Marketing site — checkout"),
   - **paid invoice** recorded in the CRM,
   - **project** created with steps from each product's `steps`,
   - **client portal** access + magic link emailed,
   - **Site Stats site** created if any product has `site: 1` (tracking snippet included in the portal),
   - **tasks** created (including "Schedule kickoff call"),
   - order confirmation email to the customer from hello@ (with the kickoff booking link via `shop_kickoff_line()`) and an alert to `shop_notify`.
5. Monthly plans renew through Stripe; each `invoice.paid` records a paid renewal invoice in the CRM.

### Webhook (`stripe-webhook.php`)
- Verifies the `Stripe-Signature` HMAC-SHA256 with a **300-second** tolerance.
- **Dedupes** by event ID in `stripe_events`, so a resend never double-processes.
- Metadata `kind` routes the event: store order (default), **`quote`** (proposal deposits), **`booking`** (paid consultations).
- Handles:
  - `checkout.session.completed`, `checkout.session.async_payment_succeeded` (bank payments clearing), `checkout.session.async_payment_failed`, `checkout.session.expired`
  - `invoice.paid` (subscription renewals → CRM invoice)
  - `invoice.payment_failed` (flags the order, notifies)
  - `customer.subscription.updated` / `customer.subscription.deleted` (plan status, cancellations)
  - `charge.refunded` (marks refunded)
- `shop_plan_by()` finds the order or quote that owns a subscription.
- No Stripe SDK; a small `stripe()` helper makes HTTPS calls (base URL from `stripe_api` in config, normally `https://api.stripe.com`).

### Fallback sync
`shop_cleanup()` (in cron) checks pending orders against Stripe directly, so an order still completes if a webhook is missed, and expires abandoned ones.

### Unfinished checkout reminder
`shop_remind_abandoned()` (in `shop_cleanup()`) emails **one** reminder 1–48 hours after a checkout that reached Stripe but wasn't paid (`orders.reminded_at`). Never twice per order, at most once a week per email, and not if that email has paid since. The link `checkout.html?resume=REF&k=KEY` calls `shop-api.php?a=resume`, which rebuilds the cart and fills in their details (radio answers like “has a domain” are asked again), then opens step 3. A paid order redirects to the thank-you page. Activity is logged on the deal or contact.

### Special offers (`offers` table, CRM → Store → Offers)
A set price and/or free months on products **bought together**, with an end date (`ends_on`, last day to buy, end of day in the CRM time zone) and a limit (`max_uses` paid orders; 0 = none). Seeded from `catalog.json` → `offers` (migration v17); after that, edit in **Store → Offers** (name, end date, spots, products with offer price and free months, on/off). Functions in `app/shop.php`: `offer_row()`, `offer_items()`, `offer_state()` (live / off / ended / full), `offer_used()`, `offer_public()`.
- **Current offer `starter-500`:** Starter website for **$500** (regularly $850) + **Care Plus free for 3 months**, then $99/month. 10 spots, ends **November 30, 2026**. Landing page `syndicatemarketing.co/starter-website-offer.html`; checkout link `checkout.html?offer=starter-500`.
- **Cart:** `?offer=slug` (or the page's buttons) loads `shop-api.php?a=offer`, replaces any website/care package in the cart with the offer's products, and stores `sm_cart.offer`. Lines show the regular price struck through and “Free for 3 months, then $99/mo”; the renewal notice and the “I agree” line name the first charge date. Removing an offer product drops the offer (regular prices apply). Add-ons stay at regular prices.
- **Checkout** (`shop_price_cart($items, $brief, $offer)`) re-checks everything: offer live, all its products present, no other monthly plan beside free months, no promo codes. Order rows get `orders.offer`, `orders.trial_end`, `order_items.list_price`, `order_items.trial_months`. Stripe gets the offer price as `unit_amount`, and free months as `subscription_data[trial_end]` (plus `trial_settings.end_behavior.missing_payment_method=cancel`): Stripe charges the one-off items today and starts monthly billing when the trial ends. The consent record names the first charge date.
- **After payment:** order `sub_status = trialing`; the CRM invoice shows the website at the offer price (“regularly $850”) and Care Plus at $0 (“first 3 months free, then $99/month”); deal notes and the staff email name the offer; the customer email gives the first charge date and how to cancel. When the trial ends, Stripe's `customer.subscription.updated` moves it to `active` and `invoice.paid` records each renewal as usual.
- **Free-months reminder:** `plans_trial_reminders()` (cron, inside `shop_cleanup()`) emails once, 7 days before `trial_end`, with the amount, date and cancel link (card-network rules and California's auto-renewal law). Cancelling during the trial (portal Plans & billing) ends it at the trial end with no charge; the portal shows “Free until …”.

### Stripe setup (what Grady does in the Stripe dashboard)
1. Developers → API keys → copy the **secret key** (start with the **test** key `sk_test_…`).
2. Developers → Webhooks → Add endpoint `https://crm.stusyn.com/stripe-webhook.php` with the events listed above → copy the **signing secret** (`whsec_…`).
3. CRM → Store → Settings: paste both, set the shop site URL `https://syndicatemarketing.co`. (Stored encrypted.)
4. Test a purchase with card `4242 4242 4242 4242`.
5. **Go live:** swap in the live secret key **and** create a separate live-mode webhook endpoint with its **own** signing secret (test and live secrets are different).
6. Settings → Emails: turn on **successful payment receipts**. Settings → Billing → **Customer portal**: turn on so subscribers can update cards and cancel.
7. Deactivate the old **$500** Payment Link.

---

## 11. Quotes and proposals

For custom work that doesn't fit the store. Files: `crm/app/quotes.php`, `quotes.php`, `quote-edit.php`, `quote.php`, public `q.php`.

- Numbering: **`Q-1001`** onward (`quote_prefix`, `quote_next`). Valid for **30 days** by default (`quote_valid_days`).
- Line items can come from the product catalog or be custom; one-off or monthly; items can be **optional**, so the client ticks what they want.
- **Payment options:** deposit **100%**, **50%**, **25%** or **0%** (accept without paying now).
- Statuses: draft → sent → viewed → accepted (awaiting payment) → **won**, or declined / expired.
- The client opens `q.php?k=…`, picks options, sees totals update, accepts with their name, and pays the deposit through Stripe Checkout (metadata `kind=quote`). Monthly items start a subscription.
- **On win** (deposit paid, or accepted at 0%):
  - deal → Won,
  - **paid invoice** for the deposit,
  - **draft balance invoice** for the rest (sent on delivery, due in 14 days),
  - **project** created from the chosen items' steps (skipping steps for optional items they didn't pick),
  - client **portal** access,
  - **Site Stats** site if needed,
  - tasks, including the kickoff call,
  - emails to client and to hello@ with the kickoff booking link.
- **One automatic reminder** goes out **3 days before** the quote expires (via cron `quotes_cleanup()`), which also marks expired quotes.
- Default terms (`quote_terms`): USD; deposit due on acceptance and work starts once it's paid; balance invoiced on delivery, due within 14 days; monthly services renew until cancelled with 30 days' notice; ad spend paid to the platforms; the work is the client's once paid in full; the deposit is fully refundable before the kickoff call, after that refunds are based on work completed.

---

## 12. Online booking

Our own scheduler (replaces Calendly). Files: `crm/app/booking.php`, public `book.php`, admin `bookings.php`, `booking.php`, `booking-setup.php`, `booking-type.php`.

### Meeting types (seeded)

| Slug | Name | Brand | Length | Price | Visibility | Kind |
|---|---|---|---|---|---|---|
| `discovery-call` | Discovery call | Syndicate Marketing | 20 min | Free | Public | `lead` (creates contact + deal + task) |
| `kickoff` | Kickoff call | Syndicate Marketing | 30 min | Free | Private (link only) | `kickoff` (completes the "Schedule kickoff call" task for that client) |
| `tiktok-shop-consultation` | TikTok Shop consultation | Creator Ops Media | 60 min | **$150** | Public | `consult` (paid at booking through Stripe) |

Each type can set location (video by default), an intake question, a max per day, active/public flags and order.

### Rules
- Availability: `booking_hours` (default Mon–Thu 9–5, Fri 9–3, weekends closed), minus existing bookings, consultations and **blocks** (`booking_blocks`, for time off).
- Minimum notice **12 hours**; bookable up to **30 days** ahead; **15-minute buffer** between meetings; start times every **30 minutes**.
- **Paid bookings hold their slot for 35 minutes** while the customer pays (status pending). If not paid in time they become "Not paid" (expired) and the slot frees up (`bookings_cleanup()`).
- Every booking creates a **consultation** record (so it appears in the calendar feed) and, if paid, a **paid invoice**.
- Confirmation emails include an `.ics` invite and a self-serve **cancel/reschedule** link. The meeting link is `booking_meeting_url`.
- **Reminder** email 18–26 hours before the meeting (sent by cron).
- `shop_kickoff_line()` puts the kickoff booking link into store and quote confirmation emails; the marketing thank-you page links to it too.

---

## 13. Site Stats (our own analytics)

Privacy-first, cookieless analytics built into the CRM; it replaces Google Analytics on our sites and is included for clients.

- **Embed:** `<script defer src="https://crm.stusyn.com/a.js" data-site="SITEKEY"></script>`
  - stusyn.com: `t4xJf60nIgv4` (loaded through the `syn-stats` meta tag and the consent banner)
  - syndicatemarketing.co: `UIOdnDz160w8`
  - Client sites: a key per site, created automatically on purchase or by hand in CRM → Site stats.
- **Collector:** `t.php` writes to a separate database, `storage/analytics.sqlite`.
- **No cookies, no personal data:** visitors are counted with a daily-rotating salted hash (salts are deleted after a day); IPs are never stored. Bots and crawlers are filtered out. On stusyn.com the consent banner and GPC decide whether the tracker loads.
- **Tracks:** pageviews, unique visitors, referrers, UTM campaigns, pages, devices, browsers, countries (city-level if the optional DB-IP Lite `.mmdb` file is placed in `crm/storage/`), outbound clicks, and **goals**.
- **Goals:** custom events fired with `window.syn('Goal name', {props})`, or URL-based goals. Tables `sites`, `site_goals`.
- **Exclude yourself:** visit any tracked site once with **`?syn_ignore=1`** in each of your browsers.
- **Client access:** dashboards show in the client portal (`portal-stats.php`); **monthly email reports** go out automatically (cron `an_monthly_reports()`); CSV export (`site-export.php`).
- **Retention:** events and daily summaries older than `analytics_retention_days` (760 by default, minimum 30) are deleted by cron `an_cleanup()`.

---

## 14. Projects and the client portal

- A **project** has a status (planning, in progress, in review, on hold, complete), **steps** (from catalog `steps`, or custom), **posts/updates**, and **files** (`projects`, `project_steps`, `project_posts`, `project_files`).
- Store orders and won quotes create projects automatically; you can also create one by hand.
- **Client portal** (`portal*.php`, `crm/app/portal.php`): clients log in with an emailed **magic link** (`portal_links`; no passwords). They see:
  - their projects, the step checklist and progress,
  - updates and drafts,
  - a place to upload files (logo, photos, text) and download ours,
  - their Site Stats dashboard and tracking snippet,
  - their invoices.
- Portal uploads are stored in `crm/storage/portal/` (web-blocked, served through `portal-file.php` / `project-file.php` after an access check).

### Demo client portal (for showing prospects)
- **Links:** `crm.stusyn.com/portal-demo.php` (client portal) and `ads.stusyn.com/portal-demo.php` (ads portal). Anyone who opens one is signed in as **Dana Reyes at Ridgeline Heating & Air**, the fictional HVAC concept from the portfolio (`ridgelineair.example`, (864) 555-0142). The portal login page also links to it (“Look around a demo account”).
- **What it shows:** every portal page filled in: three projects (finished website with approved designs and files, finished ads setup, and a new page **waiting for approval**), an unpaid invoice and paid renewals, Plans & billing, Care Plus (90-day uptime with one outage, scan, PageSpeed, edit minutes, requests, work log, monthly care reports), Site Stats (120 days of visits, goals, campaigns), and the ads portal (four campaigns, daily results, two ads to approve, setup checklist, work log, messages, monthly reports).
- **Separate data:** `app/demo.php` builds it in `crm/storage/demo/` (its own `crm.sqlite`, `analytics.sqlite`, portal and ad files from `app/demo-files/`), rebuilt on the first visit each day so dates stay current. `DEMO` (top of `bootstrap.php`) is on only for `portal-demo.php`, or for client-facing pages (`DEMO_PAGES`) when the `syn_demo` cookie is set; then `DATA` points at the demo folder. Staff pages, cron, the store and webhooks always use the real data. The demo uses its own portal session cookie.
- **Look-only:** every form on it (approve, request changes, messages, care requests, ad approvals, cancel plan) saves and sends nothing and says what would happen for a real client (`demo_read_only()`); `send_mail()` is a no-op. A lime bar on every page says it’s a demo with “Book a free discovery call” and “Exit demo”; invoices and reports opened from it carry a demo ribbon. A real sign-in link, invoice or report link opened with the demo cookie still set drops the cookie and opens the real thing (`demo_miss()`).
- **Honesty:** all sample data is labelled as a made-up business on every page. Don’t add real names, reviews or results to it. Change the sample data in `app/demo.php` (bump `DEMO_VERSION` to rebuild right away).

---

### Website care (the care plan, §9)
Built into the CRM (`app/care.php`), so nothing outside is needed.
- **Tiers** (`CARE_TIERS` in `bootstrap.php`): `care-plan` (60 min, requests started within two business days) and `care-plus` (120 min, one business day, plus a quarterly tune-up task and a yearly review-call task from `care_plus_tasks()`). Switching plans in `care-site.php` moves the minutes unless they were changed by hand.
- **Sites we didn’t build:** a care plan can be bought on its own. Checkout then asks for the site address (required), platform and whether they have admin logins (`care_url`, `care_platform`, `care_access` in the brief); the Site Stats site is created from that address. `care_built = 0` adds four “Care onboarding” tasks (access, first backup or export, Site Stats snippet, review the first scan) and a “Getting set up” box in the portal. On hosted platforms (`CARE_HOSTED`: Shopify, Squarespace, Wix, Webflow) backups are monthly content exports (`backup_days` 30).
- **Turning on:** buying `care-plan` or `care-plus` (store or a won quote) sets `sites.care = 1, monitor = 1, care_order_id` (store order id, or minus the quote id) on the client's Site Stats site; a cancelled subscription turns both off (`care_disable_order`). Any site can also be monitored without a plan (e.g. our own three) from **CRM → Care**.
- **Every cron run (15 min):** `care_run_checks()` fetches each monitored site (optional "text that must be on the page"). Two failures in a row = **down**: opens an incident, emails `shop_notify`, adds a task, and emails the client if `notify_client`. Recovery closes the incident and emails both with the downtime. Checks are kept 400 days (`site_checks`, `site_incidents`).
- **Daily / weekly (inside `care_cron()`):** SSL expiry (alerts at 14 and 3 days); domain expiry via RDAP (rdap.org, weekly; alerts at 30 and 7 days); **security & health scan** weekly: HTTPS + http→https redirect, 5 security headers, exposed files (`/.env`, `/.git/HEAD`, `/wp-config.php.bak`, `/phpinfo.php`, `/.DS_Store`), mixed content, software/generator, title/description/viewport, and **broken links** (home page links + sitemap.xml, up to 60, same site). Exposed files alert by email + task; broken links create a task. Overdue backups (no `backup` log within `backup_days` + 1) create a task.
- **Work log** (`care_log`: update, security, backup, edit, fix, speed, note; minutes; shared or private) and **edit requests** (`care_requests`, sent by clients from the portal; statuses Received → In progress → Done / Not included; minutes logged on completion count toward `care_minutes`, default 60/month; the client is emailed on status changes).
- **Google PageSpeed** (`care_psi_due()`, weekly, up to 3 sites per cron run): phone test of the home page, four scores, lab metrics, the top 5 fixes, and last week’s scores for the ▲▼ change. A speed score under 50 on a care site creates a task. Free API key: Google Cloud → enable PageSpeed Insights API → create key → CRM → Care → “Google PageSpeed API key” (stored encrypted). Works without a key but Google rate-limits it. `psi_api` in config only for testing.
- **Monthly care report** (`care_monthly_reports()`, days 1–5): the email is a plain summary with a link to the **web report** `care-report.php?id=&m=YYYY-MM&k=` (HMAC of site + month with `sites.report_key`; each month has its own link). The web report is a printable white document with uptime bars, response-time line, PageSpeed rings and top fixes, security findings, real-visitor vitals, visitors chart and goals, and the work log with the edit-minute meter. Reports are listed on `care-site.php` and `portal-care.php` (this month shows as “so far”).
- **Monthly care report contents**: uptime %, outages and downtime, response time, security findings in plain words, SSL/domain dates, Core Web Vitals p75 from Site Stats, visitors and goals, work done, backups and edit minutes. Care sites get this **instead of** the plain Site Stats report.
- **Pages:** staff `care.php` (all sites) and `care-site.php` (one site: uptime bar, incidents, renewals, speed, scan, requests, log work, log backup, settings, "Check everything now"); client `portal-care.php` (status, 90-day uptime bar, security & health, speed, edit-minute meter, request a change, work log), linked from the portal home.
- **Backups themselves** are taken by us (cPanel backup or the host's tool); the CRM logs them and nags when one is due.

### Ads management (ads.stusyn.com)
The admin side and the client ads portal for every ads package. It lives in the CRM's `ads/` folder and uses the CRM's database, staff accounts (with two-step sign-in), client magic links and Site Stats; `app/ads-boot.php` defines `ADS_APP`, which switches the shared shell (`views/top.php`, `portal_top()`) to the ads menu and branding. Engine: `app/ads.php`; report page: `app/ads-doc.php`. Setup: cPanel → Domains → create `ads.stusyn.com` with its document root set to the CRM folder's `ads` subfolder. `ads/.htaccess` forces HTTPS, sends `crm.stusyn.com/ads/…` to ads.stusyn.com and sets the same security headers. `npm run package:crm` copies the CRM's `assets/` into `ads/assets/`.
- **How a client starts:** buying `ads-one`/`ads-two`/`ads-all` (store) or winning a quote with one creates `ad_clients` (status Getting set up) with the plan, platforms, ad budget (top of the range they picked) and area from the checkout brief, an `ad_accounts` row per platform, the Site Stats site link, and three onboarding tasks. A cancelled subscription ends it (`ads_end_order()`) with a task to pause campaigns and remove access. Staff can also add a client by hand on the overview.
- **Staff pages:** `index.php` (month-to-date spend, leads, cost per lead, live campaigns; “Needs attention”; every client with a budget pacing bar), `client.php` (KPIs and charts for this month / last month / 30 / 90 days, campaigns with site leads by UTM campaign, setup checklist, ad accounts with access/pixel/conversions, budget & goals, monthly report notes with “Save & email report”, work log, messages), `campaigns.php`, `campaign.php` (details, UTM link builder, results by day, type a day in by hand), `import.php` (CSV upload + undo), `creatives.php` / `creative.php` (image/video/text ads, preview, send for approval, mark live/retire), `file.php` (serves ad files; staff, or the owning client with `&p=1`), `settings.php` (our access details).
- **Client pages:** `portal.php` (spend vs budget, leads, cost per lead, clicks, spend chart, campaigns with “who sees it”, what we did, monthly reports, messages), `portal-creatives.php` (approve or ask for changes with a note; nothing runs until approved), `portal-setup.php` (checklist and per-platform steps to give us access), `portal-login.php` (email link). The CRM client portal shows a “Your ads” card that hops over without signing in again.
- **Results:** no live API connection yet (Google, Meta and TikTok all require app review/approval). Results come from each platform's daily CSV export (`ads_parse_csv()` maps column names, handles Google's UTF-16 files and title rows, skips totals; re-importing a day replaces it; every import can be undone) or are typed in per day. **Leads** = Site Stats goal conversions from paid channels (Paid search/social/display/other) when the client's site has Site Stats, otherwise the platform-reported conversions; the page always says which. Cost per lead = spend ÷ leads.
- **Alerts** (`ads_alerts()`): spend over 115% or under 75% of budget pace (after day 3), a live campaign with no results for 4+ days, live past its end date, ads waiting on the client 3+ days, changes asked for, unread client messages, cost per lead 30%+ over target. Approvals, change requests and client messages also email `shop_notify` and create tasks.
- **Monthly ads report** (`ads_cron()`, days 1–5): a task on the 1st to write the month's notes (“How the month went” / “Next month”); the report emails once the notes are saved, or on day 5 regardless (or send it any time from the client page). Web report `ads/report.php?c=&m=&k=` (HMAC with `ad_clients.report_key`), printable, with KPIs vs last month, daily spend and conversions, by platform, by campaign, and what we did. Ads waiting for approval get one reminder email after 2 days.

### Help center & wiki (wiki.stusyn.com)
A public help center for clients and staff-only Team docs, in the CRM's `wiki/` folder on the same database and staff accounts (`app/wiki-boot.php` defines `WIKI_APP`; engine `app/wiki.php`). Setup: cPanel → Domains → create `wiki.stusyn.com` with its document root at the CRM folder's `wiki` subfolder, then run AutoSSL.
- **Articles** live in `wiki_articles` and are edited in the browser (wiki → All articles / + New article). They start from Markdown files in `crm/app/wiki-content/public/*.md` and `team/*.md` (front matter: title, category, order, summary, keywords; the file name is the address). `wiki_seed()` adds new files and updates changed ones **unless the article was edited in the browser** (`edited = 1`), so live edits are never overwritten. `tools/wiki/w.py` is the helper used to write the seed files.
- **Never type prices.** Tags fill from CRM → Store → Products: `{{price:sku}}`, `{{amount:sku}}`, `{{name:sku}}`, `{{timeline:sku}}` (from the “Live in about …” feature), `{{features:sku}}`, `{{products:category}}`, plus `{{email}}`, `{{book:discovery-call}}`, `{{portal}}`, `{{plans}}`, `{{adsportal}}`, `{{store}}`, `{{pricing}}`, `{{terms}}`. Links between articles: `[[slug]]` or `[[slug|text]]` (missing ones show dashed red to staff).
- **Markdown subset**: `##`/`###` headings (h2s make the “On this page” list), paragraphs, `-`/`1.` lists, tables, `> **Tip:**` / `> **Heads up:**` callouts, code fences, `---`, bold, italic, code, links. Everything is escaped; only http(s), mailto, tel, relative and `#` links are allowed.
- **Public pages**: home (search, topics, start-here, most read), `c/<topic>`, `/<slug>` articles (table of contents, related, “Was this helpful?” with optional comment), `search.php` (searches with no results are saved), `ask.php` (Turnstile, honeypot, rate limit; creates a task, emails `shop_notify`, logs on the contact), `sitemap.xml`, `robots.txt`. Public pages set no cookies and are indexable; team pages, drafts, search and forms are `noindex`.
- **Staff pages**: `team.php` (Team docs by topic, recent changes), `articles.php` (views and helpful %), `edit.php` (editor with preview, live tags help, every save keeps the previous version with one-click restore, reader comments), `insights.php` (questions to answer, searches with no answer, unhelpful articles, most read, top searches).
- **Topics** (`WIKI_CATEGORIES` / `WIKI_TEAM_CATEGORIES`): Getting started, Websites, Paid ads, Community management, Website care, Site Stats, Client portal, Billing & plans, Audits & consulting, Policies & privacy, Glossary; Team: Running the business, Client work, Systems & setup, Rules & voice, When something breaks.
- **Linked from**: the marketing site footer, the client portal and ads portal footers, and the CRM menu (“Wiki ↗”).
- **Keep it true:** when a promise, process or feature changes, update the matching help article (search the wiki) in the same change. 70 public and 25 team articles shipped on October 18, 2026; `offers-and-free-months` added the same day, then a **Community management** topic with `community-plans-compared`, `discord-server-setup`, `discord-access` and `community-moderation` (75 public). Team article `team-outreach` added October 19, 2026 (26 team).

### Outreach (cold email)
The outreach agent finds prospects and the CRM emails them. Engine `app/outreach.php`, admin `outreach.php`, agent API `outreach-api.php`, unsubscribe page `u.php`, Team article `team-outreach`.
- **Split of work:** a scheduled task (the agent) researches businesses, writes a first email and two follow-ups, and answers replies through the API. The CRM does everything rule-based: sending, timing, follow-ups, stopping on reply, opt-outs, bounces, the do-not-contact list.
- **Sending:** from the mailbox chosen in CRM → Outreach (`outreach_mailbox`; **outreach@syndicatemarketing.co**, never hello@), sender name `outreach_from_name` (Grady Davis). Weekdays 9 AM–5 PM, spread across the day in 15-minute cron runs. Warm-up limit: `outreach_start` (8) a day, +4 each week since the first send, up to `outreach_cap` (30); agent replies count toward it. Follow-up 1 four business days after the first email, follow-up 2 ten business days after it, both as replies in the same thread (`Re:`, `In-Reply-To`). The CRM adds the signature, the postal address (`outreach_address`), a “reply no thanks” line and the unsubscribe link to every email, plus `List-Unsubscribe` (mailto + URL) and `List-Unsubscribe-Post` headers. Off until switched on (`outreach_on`), and refuses to start without a mailbox and an address.
- **Guards:** `out_blocked()` skips addresses on the do-not-contact list (email, or the whole business domain; free-mail domains are never blocked as a whole), anyone already a prospect at the same business domain, and anyone already a CRM contact. `out_links_error()` allows links only to our sites (`OUT_LINK_HOSTS`) and the prospect's own website, and refuses leftover placeholders. More than 8% bounces over 14 days (at 20+ sends) switches outreach off and emails `shop_notify` (`outreach_paused`).
- **Replies** (`out_process_mail()`, each cron run after `mail_sync()`, reading only the outreach mailbox from `outreach_last_email`): bounces (mailer-daemon, or an RCPT refusal at send) → `bounced` + do-not-contact. Auto-replies (headers or subject) → logged, sequence continues. “No thanks / not interested / unsubscribe / remove me” → `declined` or `unsubscribed` + whole domain on do-not-contact, no further mail. Any other reply → `replied`, sequence stops, `shop_notify` is emailed, and it waits for the agent. If Grady answers from his own mail or the CRM inbox, the prospect moves to `talking` and the agent leaves it alone.
- **Agent answers** (`out_respond()`): `reply` (send, → `talking`), `interested` (optional reply; creates the contact, a `qualified` deal with source “Cold outreach” valued at the `sku` price, a task, and emails Grady), `decline` (do-not-contact), `handoff` (→ `needs_you`, task + email with the suggested reply), `resume` (not a real reply). Only conversations in `replied` can be answered, once.
- **Campaigns** (scheduled tasks, all queue through the same API and share the daily limit): **Syndicate prospecting** (Mon + Thu 7:54 AM, local trades in Upstate SC, rotating niche, mostly the starter-500 offer); **Gaming outreach prospecting** (Tue 7:47 AM, rotating weekly through indie studios, streamers and creators, esports teams, and gaming businesses; links to `gaming-communities.html?t=…`; skips anyone who appears under 18 or anything aimed at kids; business emails only, never Discord or social DMs; batch and niche `gaming-<audience>`); **Outreach replies** (weekdays 10:52 AM and 3:52 PM). All run in the **Syndicate CRM** cloud environment, which holds the agent key as a network secret for crm.stusyn.com.
- **Statuses:** queued, active (in sequence), replied (agent to answer), talking, interested, needs_you, finished (no reply), declined, unsubscribed, bounced, paused, cancelled. The nav badge counts `needs_you`. The Monday summary lists “Outreach: your turn” and the week's numbers.

### Plans & billing (online cancellation)
Clients see their monthly plans (store orders and won quotes with a live Stripe subscription) at `portal-plans.php` and cancel in two steps. `plan_cancel()` in `app/shop.php` sets `cancel_at` (ads, with notice, `proration_behavior=none`) or `cancel_at_period_end` in Stripe, stores `cancel_at`, logs it and sends both emails. The webhook also records `cancel_at` when a plan is cancelled from the Stripe dashboard.
- **Consent record:** checkout stores what the buyer agreed to (time, hashed IP, terms URL, the monthly amount) in `orders.consent`, shown on the order page. Orders are never deleted, which covers California's 3-year rule. Quotes keep `accepted_at`, `accepted_name`, `accepted_ip`.
- **Yearly reminder:** `plans_yearly_reminders()` (run by cron inside `shop_cleanup()`) emails each live plan once a year with the price, billing frequency and how to cancel.

## 15. Invoices, consultations, inbox, calendar

### Invoices
- Numbers **`SYN-1001`** onward; terms **14 days**; footer "Thank you for your business."
- Line items, tax rate, notes, statuses (draft, sent, paid, void), paid date and method.
- Public link `i.php?k=…` lets the client view and print; a payment link (Stripe or other) can be attached (`payment_link_default`).
- **Recurring invoices** (weekly, monthly, quarterly, yearly) are generated and emailed by cron (`run_recurring()`).
- Consultations can be turned into invoice lines (`consultation_id` on `invoice_items`).

### Consultations (Creator Ops Media)
- Date, start time, minutes (60 default), **rate $150** default, topic, notes, status, billable flag, linked invoice.
- Paid online bookings create these automatically.

### Inbox
- Connect mailboxes (IMAP to read, SMTP to send) in `mailboxes.php`. Passwords are encrypted with `crm/storage/mail.key`. **Back up `mail.key`**: without it saved passwords and Stripe keys can't be decrypted.
- Email is synced every cron run (and on demand), threaded, matched to contacts/deals, with attachments and inline images (`email-file.php`, `email-img.php`), and replies sent from the CRM.

### Calendar feed
- `calendar.php?token=…` is a private **ICS** feed (consultations, bookings, tasks with due dates). Subscribe to it in Google Calendar or Apple Calendar. Rotate `calendar_token` in Settings if it leaks.

---

## 16. Cron (the scheduled job)

One job runs everything. cPanel → **Cron Jobs** → every 15 minutes (`*/15 * * * *`):

```
php /home/USERNAME/crm.stusyn.com/cron.php
```

(Replace `USERNAME` and the folder with the real cPanel path. If `php` isn't found, use `/usr/local/bin/php`.) `cron.php` returns a 404 on the web; it only runs from the command line.

Each run:
1. `sync_website()`: imports new stusyn.com contact messages as leads
2. `run_recurring()`: creates and emails recurring invoices that are due
3. `mail_sync()`: pulls new email into the inbox
4. `an_cleanup()`: Site Stats retention cleanup
5. `an_monthly_reports()`: monthly Site Stats emails to clients
6. `shop_cleanup()`: Stripe fallback sync for pending orders, expires abandoned checkouts, sends yearly plan reminders, unfinished-checkout reminders and free-months-ending reminders (7 days before)
7. `quotes_cleanup()`: 3-day expiry reminders, marks expired quotes
8. `bookings_cleanup()`: releases unpaid holds after 35 minutes, sends the 18–26 hour reminders
9. `care_cron()`: uptime checks every run; SSL daily; domain, security scan and PageSpeed weekly; backup-due, Care Plus tune-up and review-call tasks; monthly care reports on days 1–5
10. `ads_cron()`: monthly ads report notes tasks and emails (days 1–5), one approval reminder after 2 days
11. `outreach_cron()` (`app/outreach.php`): reads replies, bounces and opt-outs in the outreach mailbox, then sends what's due (weekdays 9–5, within the warm-up limit)
12. `pipeline_digest_send()` (`app/digest.php`): the **weekly summary**, emailed to `shop_notify` once a week on Monday from 7 AM (setting `digest_week` stops repeats). It lists tasks due or overdue, open deals with no activity in 14+ days, quotes waiting on the client (flags those expiring within 7 days), overdue invoices, unpaid checkouts from the last 7 days, calls booked this week, and the last 7 days in numbers (new leads, deals won, money in, visitors to the two campaign pages). Also includes outreach (conversations waiting on Grady, and the week's sends, replies and bounces). Sent on demand from the dashboard (“Email me this week’s summary”) or `php app/cli.php digest [send]` (without `send` it just prints).

It prints a one-line summary (cPanel can email it to you; set the cron email to blank to silence it).

---

## 17. Databases and migrations

### CRM: `crm/storage/crm.sqlite`
Migrations run automatically in `crm/app/bootstrap.php` using `PRAGMA user_version`. **Never edit an old migration. Add a new version.** Next free version: **v20**.

| Version | Adds |
|---|---|
| v1 | `users`, `companies`, `contacts`, `deals`, `activities`, `tasks`, `consultations`, `invoices`, `invoice_items`, `settings`, `attempts` + default settings |
| v2 | Invoice payment links; `mail_from` = hello@ |
| v3 | Recurring invoices |
| v4 | Inbox: `mailboxes`, `mail_folders`, `emails` |
| v5 | Projects + portal: `projects`, `project_steps`, `project_posts`, `project_files`, `portal_links` |
| v6 | Site Stats: `sites`, `site_goals` |
| v7 | Store: `products`, `orders`, `order_items`, `stripe_events`, `invoices.stripe_invoice`; seeds products from `catalog.json`; store settings |
| v8 | Freelance price update (only prices not edited by hand) |
| v9 | Quotes: `quotes`, `quote_items`; quote settings and terms |
| v10 | Booking: `meeting_types`, `bookings`, `booking_blocks`; booking settings; seeds the 3 meeting types |
| v11 | Auto-renewal rules: `cancel_at` and `renewal_reminded_at` on `orders` and `quotes`, `orders.consent` (what the buyer agreed to, JSON); setting `plan_cancel_notice_days` (30) |
| v12 | Website care (`sites` care/monitor/status/SSL/domain/scan columns, `site_checks`, `site_incidents`, `care_log`, `care_requests`; care plan copy refreshed if unedited) and two-step sign-in (`users.totp_secret`, `totp_last`, `recovery_codes`) |
| v13 | Care Plus and care for outside sites (`sites.care_sku`, `care_platform`, `care_built`, `care_since`), web care reports (`sites.report_key`), Google PageSpeed (`sites.psi`, `psi_checked`; setting `psi_key`); inserts the `care-plus` product |
| v14 | `orders.reminded_at` (one reminder for unpaid checkouts) |
| v15 | Ads management: `ad_clients`, `ad_accounts`, `ad_campaigns`, `ad_stats` (one row per campaign per day), `ad_imports`, `ad_creatives`, `ad_log`, `ad_messages`, `ad_months` (report notes) |
| v16 | Wiki: `wiki_articles`, `wiki_revisions`, `wiki_searches` (no-result searches), `wiki_feedback`, `wiki_questions` |
| v17 | Special offers: `offers` (seeded from `catalog.json` → `offers`); `orders.offer`, `orders.trial_end`, `orders.trial_reminded_at`; `order_items.list_price`, `order_items.trial_months` |
| v19 | Outreach: `outreach_prospects`, `outreach_log`, `outreach_optout`; settings `outreach_on`, `outreach_mailbox`, `outreach_from_name`, `outreach_address`, `outreach_start`, `outreach_cap`, `outreach_api_hash`, `outreach_last_email`, `outreach_paused` |
| v18 | Community products (`com-discord-setup`, `com-audit`, `com-manage`, `com-manage-plus`, after the care plans) and the `add-presskit` add-on, from `catalog.json` |

### Site Stats: `crm/storage/analytics.sqlite`
Separate file so heavy analytics writes never slow down the CRM.

### Main site: `src/php/storage/` (on the server, `public_html/storage/`)
`admins`, `jobs`, `applications`, `messages`, `attempts`. Resumes in `storage/resumes/`. Schema version v2 (two-step sign-in columns on `admins`).

### Backups
- Download `crm/storage/` (especially `crm.sqlite`, `analytics.sqlite`, `mail.key`, `portal/`) and the main site's `storage/` regularly (cPanel → Backup, or File Manager → Compress).
- Use the SQLite connection with WAL-safe copies (copy the `-wal` file too, or copy while idle).

---

## 18. Security, headers and .htaccess

### Application security (all PHP)
- PDO prepared statements everywhere; no string-built SQL with user input.
- CSRF tokens on every POST form; `SameSite` secure session cookies; session regeneration on login.
- Passwords with `password_hash()` (bcrypt); login and form **rate limiting** via `attempts` (hashed IPs).
- Output escaped with `e()`; uploads checked by type and size, stored outside the web, served through access-checked scripts.
- Public links use long random tokens (`k=`) compared with `hash_equals()`.
- Secrets encrypted at rest (`mail_encrypt()` with `storage/mail.key`).
- Stripe webhook signature verified; events deduped.
- Honeypots on public forms.
- **Two-step sign-in (TOTP)** for CRM users (Settings → Two-step sign-in) and Hiring HQ admins (Account): any authenticator app, QR via `assets/js/qrcode.js` (qrcode-generator, MIT), ±30 s drift, codes can't be reused, 10 one-time recovery codes (stored hashed). Secrets are encrypted with `storage/totp.key` (back it up with `mail.key`; excluded from zips and git). `app/totp.php` is identical in both apps. Lost phone and codes: on the server run `php app/cli.php 2fa-off you@example.com` (CLI only). The CRM dashboard nudges until it's on.
- **Bot check (Cloudflare Turnstile)** on the public forms: stusyn.com contact (`contact.php`) and job applications (`job.php`), syndicatemarketing.co contact (`shop-api.php?a=lead`), and CRM bookings (`book.php`). Free, no Cloudflare DNS needed, one widget for all three domains. Keys: the public site key goes in `turnstile_site` (both `config.php` files) and in the builds (`TURNSTILE_SITE` env or `turnstileSite` in `build.mjs` / `build-marketing.mjs`); the secret goes only in `turnstile_secret` in both `config.php` files. Empty = off. Helpers in both bootstraps: `captcha_widget()`, `captcha_script()`, `captcha_ok()`. Missing or bad token = refused; Cloudflare unreachable = let through and logged. **Turn it on in this order:** rebuild and deploy the static sites with the site key first, then add the secrets, or the static contact forms will be refused. Test keys: site `1x00000000000000000000AA`, secret `1x0000000000000000000000000000000AA` (always pass).
- The marketing contact auto-reply contains none of the visitor's words and goes to each address at most once a day, so the form can't relay spam.

### .htaccess
Sources: `src/php/.htaccess` (stusyn.com), `marketing/htaccess.txt` (syndicatemarketing.co), `crm/.htaccess` (CRM), plus `storage/.htaccess` and `app/.htaccess` files that **deny all**, and `marketing/work/.htaccess` (demos: `noindex`, Google Fonts allowed in CSP).

All three sites set:
- HTTP → HTTPS and `www` → apex 301s, skipping `/.well-known/`
- `Strict-Transport-Security: max-age=31536000`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()`
- a strict **Content-Security-Policy** (self-hosted fonts, no third-party scripts except `https://crm.stusyn.com` for Site Stats and `https://challenges.cloudflare.com` (script + frame) for Turnstile; syndicatemarketing.co also allows `connect-src` to the CRM for the cart API; Stripe is a redirect, so no Stripe script is needed)
- caching for static assets, compression, custom 404, no directory listings, blocked access to dotfiles, `app/` and `storage/`
- stusyn.com: `order.php` → `/one-page-website.html#order` (301)

---

## 19. Design systems

### stusyn.com — dark, editorial
- Background `#07080a`; text near-white; accent **lime `#c6ff3d`**; secondary accent **pink `#ff5c8a`**.
- Fonts (self-hosted in `src/assets/fonts/`, declared in `fonts.css`): **Inter Tight** (UI/body), **Instrument Serif** (italic display accents), **JetBrains Mono** (labels, numbers, eyebrows).
- Style: big type, serif italic highlights, mono eyebrows, thin borders, generous spacing.

### syndicatemarketing.co — light, paper and ink
- Paper `#f5f4ef`; ink `#0d0f0c`; accent **lime `#c6ff3d`**; dark panels `#0b0d10`.
- Same three font families (self-hosted).
- Components: product cards (`cards.js`), plan tables, chips, checkout steps, review lines, slide-out cart drawer, `.ty` thank-you card, `.serif.hl` highlight.
- Global rule: `[hidden]{display:none!important}` (stops layout classes from un-hiding things).

### CRM — dark app
- Dark UI with the **lime** accent, Inter Tight + JetBrains Mono.
- Public documents (invoices, proposals, booking pages, portal) are **white documents on a dark background**, printable.

### Demo sites
Each concept has its own brand (fonts may come from Google Fonts, allowed only in `marketing/work/`).

---

## 20. Copy and voice

- **Plain, direct, warm, honest.** Write like a capable person talking to a small-business owner. Short sentences. No jargon unless we explain it.
- Say what's included, what it costs and how long it takes. Lead with outcomes, not buzzwords.
- **Never:** "synergy", "revolutionary", "world-class", guaranteed results, made-up stats, fake urgency or countdowns, fake scarcity.
- Use "we" for the brands and "Grady" in the founder story.
- Prices always come from the catalog tags; write "$300/month", "$150/hour".
- Curly quotes and apostrophes (’ “ ”) and en dashes for ranges (4–5 weeks).
- CTAs: specific verbs: "Book a free discovery call", "Add to cart", "Book your kickoff call", "Book a consultation".
- Every customer email comes from hello@ (careers from careers@), signed by the brand, with a clear next step.
- Accessibility: real headings in order, alt text, labels on every field, visible focus, AA contrast, `aria-live` for dynamic status (as on the thank-you page).

---

## 21. Testing

Test end to end before shipping any change to money, booking or forms. The test harness is not in the repo; rebuild it in a scratch directory when needed:

- **PHP built-in server** for each app:
  - test CRM on port **8820** with a copy of `crm/` and a fresh `storage/`
  - built marketing site on **8821** (`SM_CRM=http://127.0.0.1:8820 SM_SITE_URL=http://127.0.0.1:8821 npm run build:marketing`)
- **Mock Stripe** (a small Python server on **8830**): implements the Checkout Session, subscription, invoice and refund endpoints the CRM uses. `/pay/<id>` shows a page with **Pay** (`#pay`) and **Cancel** (`#cancel`) links that send signed webhooks back. Point the CRM's `stripe_api` config at it.
- **Fake sendmail** (`sendmail_path` → a script that appends to a `mail.log`) to check every email.
- **Playwright** (Chromium is preinstalled; don't run `playwright install`) for browser flows.

Suites we ran (all passing at last run):

| Suite | Checks |
|---|---|
| Store e2e | 65 (cart rules, `?add=`, checkout steps, Stripe session, webhook signature/dedupe, CRM automation, renewals, failures, refunds, expiry, emails, thank-you page) |
| Quotes e2e | 28 (create, send, view, options, deposits 100/50/25/0, win automation, balance invoice, decline, reminder, expiry) |
| Booking e2e | 29 (slots, notice, buffer, blocks, free and paid bookings, 35-min hold, expiry, reschedule/cancel, ICS, reminders, kickoff task) |

Testing tips learned the hard way:
- Use a **fresh browser context** each run (the catalog is cached).
- When backdating records, backdate relative to `created_at` (timezones differ between PHP and the shell).
- Don't put complex PHP in `php -r` from the shell; write a temp `.php` file.
- Email subjects are MIME-encoded, so check the body text.
- Font 404s in the test environment are expected (fonts are only copied in at packaging).
- Check layouts at **375px, 768px and 1280px**, plus keyboard-only and `prefers-reduced-motion`.
- Run `php -l` on every changed PHP file and the relevant `npm run build` (the marketing build fails on unfilled placeholders).

---

## 22. Git and commit conventions

- Repo: **`cgrdavis123-svg/syndicate`** (private). It holds all three sites and nothing else. It started fresh on October 19, 2026 from the `landing-page` branch of the old `syndicate-crm` repo, which still has the earlier history and other projects (ShiftProof, the macOS CRM).
- Working branch: **`main`**. Commit there and push with `git push -u origin main`.
- Clear commit messages in the imperative ("Add booking reminders"), with a short body explaining why.
- Every commit ends with the session attribution lines provided by the environment.
- **No model identifiers** in commits, PRs, comments or files.
- **No secrets**, no databases, no zips, no `dist*` folders in git.
- Don't open a PR unless asked.
- Match the surrounding code: small helper functions, terse comments only where they help, same naming (`snake_case` PHP functions, `q()`/`all()`/`val()` DB helpers, `e()` escaping, `setting()` for settings).
- New CRM schema = new migration version in `bootstrap.php`. New settings = `INSERT OR IGNORE` in that migration.
- New product = add to `catalog.json` **and** (if the store is live) add it in CRM → Store → Products, or ship a migration that inserts it.

---

## 23. Promises we make to customers

These appear on the sites, in `catalog.json`, the terms of sale and quote terms. **Grady still needs to confirm them**; if any change, update the catalog, the terms pages and quote terms together.

- **Rounds of changes:** 2 (one-page, starter), 3 (business, store).
- **Timelines:** one-page ~2 wks, starter ~3 wks, business ~4–5 wks, store ~6 wks; audits 7 days; analytics setup 5 days; first ads live in ~2–3 wks. Rush delivery (+$150) shortens the website timeline.
- **We reply within one business day** to contact forms and portal messages.
- **Refunds:** deposits and one-off payments fully refundable before the kickoff call; after that, based on work completed.
- **Cancellation:** online any time in the client portal (**Plans & billing**, `portal-plans.php`), or by email. Ads management ends at the first billing date at least **30 days** after cancelling (`plan_cancel_notice_days`); care and other monthly plans end when the paid month runs out. The exact end date shows before confirming, and a confirmation email goes to the client and an alert to `shop_notify`. Order and quote emails link to `portal-login.php?to=plans`; the marketing footer has “Manage or cancel a plan”.
- **Care requests** are started within two business days (care plan) or one business day (Care Plus). Edit time doesn’t roll over. For sites we didn’t build, fixing problems that were there before can use edit time or be quoted first (terms of sale §4).
- **Strategy session credit:** the $75 is credited toward any package bought within 30 days.
- **Ownership:** once paid in full, the website, copy and creative are the client's.
- **Ad spend** is paid by the client directly to the platforms.
- **Balance invoices** (quotes) are due within 14 days of delivery.

---

## 24. Open items and to-dos

All setup items below were done by October 18, 2026, except the ones still open.

**Stripe**
- [x] Test a full purchase in test mode
- [x] Go live: live secret key + **separate** live webhook signing secret
- [x] Deactivate the old $500 Payment Link
- [x] Turn on payment receipts and the customer portal

**CRM setup**
- [x] Cron job running every 15 minutes (§16)
- [x] Site Stats goals on syndicatemarketing.co: Purchase, Checkout started, Add to cart, Contact form sent
- [x] Booking: confirm hours, set `booking_meeting_url` (video link), add any time-off blocks
- [x] Review the default quote terms
- [x] Visit each site once with `?syn_ignore=1` in each browser
- [x] Optional: DB-IP Lite City `.mmdb` in `crm/storage/` for city-level locations
- [x] Back up `crm/storage/mail.key` somewhere safe

**Security**
- [x] Change both setup keys in the server `config.php` files (the old ones were in git history and the shared zips)
- [x] Create the Turnstile widget (hostnames: stusyn.com, syndicatemarketing.co, crm.stusyn.com), rebuild/deploy with the site key, then add the secret to both `config.php` files
- [x] Turn on two-step sign-in for the CRM (Settings) and Hiring HQ (Account), and save the recovery codes
- [x] Back up `crm/storage/totp.key` and `public_html/storage/totp.key` with `mail.key`
- [x] CRM → Care: add our own three sites to monitoring
- [x] Get a free Google PageSpeed API key and paste it in CRM → Care
- [x] cPanel: create the ads.stusyn.com subdomain with its document root at the CRM folder's `ads` subfolder (AutoSSL covers it), then deploy the CRM zip
- [ ] ads.stusyn.com → Access details: fill in the access email and, if you have them, the Google manager ID, Meta Business ID and TikTok Business Center ID; check the client Setup steps read right for how you work
- [x] cPanel: create wiki.stusyn.com with its document root at the CRM folder's `wiki` subfolder, run AutoSSL
- [ ] Google Search Console: add Domain properties for stusyn.com and syndicatemarketing.co (DNS TXT verification), submit the three sitemaps (stusyn.com, syndicatemarketing.co, wiki.stusyn.com), request indexing of the home pages, and link Search Console in Google Business Profile if you have one. The CRM, ads and portal subdomains stay out of Google on purpose (`X-Robots-Tag: noindex`); the wiki drops that header in `wiki/.htaccess` because it sits inside the CRM folder
- [x] Read through the help center (especially Billing & plans and Policies) and the Team → Promises article; edit anything that isn't how you work
- [ ] Confirm the `starter-500` offer limits before emailing it: 10 spots, ends November 30, 2026 (change both in CRM → Store → Offers; the page and checkout follow). Read Grady's note on the page and edit it if it doesn't sound like you
- [x] Confirm the community prices ($300 setup, $125 audit, $350/mo, $650/mo, $150 press kit; confirmed October 19, 2026). Still open: the promises (weekday vs daily check-ins, reports within one business day, events per month, volunteer mods on Plus), and read Grady's note on `gaming-communities.html`. Change prices in CRM → Store → Products and `catalog.json`
- [ ] Set up a Discord account for Syndicate Marketing (with two-factor on) to use for client servers, and write the staff guide template the setup promises
- [x] Outreach setup (done October 19, 2026): create outreach@syndicatemarketing.co in cPanel (SPF, DKIM and DMARC valid for syndicatemarketing.co), connect it in Inbox → Mailboxes, choose it and enter the postal address in CRM → Outreach, make an agent key and add it as a network secret for crm.stusyn.com on the scheduled tasks' cloud environment (network access must include crm.stusyn.com), then switch outreach on. Steps in the Team article `team-outreach`
- [ ] Later: apply for API access (Google Ads developer token, Meta Marketing API, TikTok Marketing API) to replace CSV imports
- [x] Confirm the Care Plus price ($99) and promises (2 hours, one business day, quarterly tune-up, yearly call)

**Email**
- [x] SPF, DKIM and DMARC for stusyn.com (cPanel → Email Deliverability)

**Compliance** (see the project doc `claude/compliance-review-2026-10-17.md`)
- [x] Add pay ranges to job posts before hiring anyone in or reporting to CO, NY, CA, WA or IL
- [x] Ask an accountant whether SC sales tax applies to the care plan (SC taxes some software/SaaS) and turn on `shop_tax` if so
- [x] Keep records backing the $10M+ GMV claim

**Content**
- [x] Confirm the promises in §23
- [x] Confirm resume items: 7-Eleven end date, brand start years, Limestone grad year; add a headshot

**TikTok videos** (set up October 20, 2026)
- A scheduled task makes two videos a day (about 6:45 AM and 5:45 PM ET) with `tools/video/` and sends each to Grady's phone with its caption, hashtags and first comment; Grady posts them at 7 AM and 7 PM. It follows `tools/video/PLAYBOOK.md` (rotation, research, hard rules). TikTok's API posts only privately until an app passes TikTok's audit, so posting stays manual.
- [ ] Optional: convert the TikTok account to a Business account and decide whether to apply for the Content Posting API later

**Ideas we've discussed but not built** (don't build without a go-ahead)
- Two-way Google Calendar sync for booking, and automatic CRM backups (started, then cancelled)
- Review requests after project completion (only real reviews, never seeded)
- Abandoned-cart follow-up email
- Monthly ads report automation in the portal

---

## 25. Glossary and quick lookups

| Thing | Value |
|---|---|
| Legal name | Syndicate Holdings Group LLC |
| Location (public) | Spartanburg, SC |
| Founded | 2019 |
| Founder | Grady Davis |
| Main email | hello@stusyn.com |
| Careers email | careers@stusyn.com |
| Main site | https://stusyn.com |
| Agency site | https://syndicatemarketing.co |
| CRM | https://crm.stusyn.com |
| Site Stats key, stusyn.com | `t4xJf60nIgv4` |
| Site Stats key, syndicatemarketing.co | `UIOdnDz160w8` |
| One-page order link | `https://syndicatemarketing.co/checkout.html?add=web-one-page` |
| Consultation booking | `https://crm.stusyn.com/book.php?type=tiktok-shop-consultation` |
| Discovery call booking | `https://crm.stusyn.com/book.php?type=discovery-call` |
| Kickoff booking | `https://crm.stusyn.com/book.php?type=kickoff` |
| Stripe webhook URL | `https://crm.stusyn.com/stripe-webhook.php` |
| Cron | `php /home/USERNAME/crm.stusyn.com/cron.php` every 15 min |
| Invoice numbers | SYN-1001… |
| Quote numbers | Q-1001… |
| Consulting rate | $150/hour |
| Exclude own visits | `?syn_ignore=1` |
| Cart storage | `localStorage.sm_cart`, `sessionStorage.sm_src`, `sm_checkout`, `sm_order` |
| CRM DB version | v19 (next: v20) |
| Gaming campaign | `syndicatemarketing.co/gaming-communities.html?t=studio\|creator\|esports\|business&b=Name&n=First` |
| Outreach | CRM → Outreach; sends from outreach@syndicatemarketing.co; agent API `crm.stusyn.com/outreach-api.php` |
| Current offer | `starter-500`: $500 Starter website + 3 months Care Plus free · `syndicatemarketing.co/starter-website-offer.html?b=Business+Name&n=First` |
| Demo client portal | https://crm.stusyn.com/portal-demo.php (ads: https://ads.stusyn.com/portal-demo.php) |
| Help center | https://wiki.stusyn.com (CRM's `wiki/` folder); Team docs at /team.php |
| Ads app | https://ads.stusyn.com (CRM's `ads/` folder) |
| Client site rules | `templates/CLAUDE.md` |
| GMV claim | $10M+ TikTok Shop GMV |
