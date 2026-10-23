# Syndicate CRM — crm.stusyn.com

A private CRM for Syndicate Holdings Group: website stats for client sites, email inbox, client portal and projects, deal pipeline, companies and contacts, consultations and hours, invoices, and tasks. New stusyn.com contact form messages and consultation requests are imported automatically as leads.

## Deploy to cPanel

Requirements: PHP 7.4+ (8.x recommended) with `pdo_sqlite`. This is the same as the main site, and no MySQL is needed.

1. **Create the subdomain.** In cPanel, go to **Domains** (or **Subdomains**) and create `crm.stusyn.com`. Note its document root. It's usually `/home/USERNAME/crm.stusyn.com`, or `/home/USERNAME/public_html/crm` on older setups.
2. **Upload.** In **File Manager**, open that document root, upload `syndicate-crm-cpanel.zip`, and choose **Extract**. Delete the zip afterwards, and turn on **Show Hidden Files** so you can see `.htaccess`.
3. **Add SSL.** Run **SSL/TLS Status → AutoSSL** for the subdomain. Then open `.htaccess` and remove the `#` from the two "force HTTPS" lines.
4. **Create your account.** Visit `https://crm.stusyn.com` and enter the setup key from `app/config.php`.
5. **Connect the website.** Open **Settings** in the CRM. If Website sync says *Connected*, you're done. If not, set `site_db` in `app/config.php` to the full path of the main site's database, e.g. `/home/USERNAME/public_html/storage/syndicate.sqlite`.

## How the website import works

- Every time you open the dashboard (at most every 5 minutes), or click **Sync now**, the CRM reads new messages from the stusyn.com database.
- Each message becomes a contact (matched by email), a company (if they gave one), and a **New lead** deal. The deal is tagged with a brand based on the topic: consultations and TikTok Shop go to Creator Ops Media, and marketing to Syndicate Marketing.
- Each lead also gets a "Reply to…" task due today.
- Careers messages are skipped, because they belong in the hiring dashboard.
- The website's own copy is left untouched, and the CRM only reads it.

## Site stats (analytics for client websites)

Your own privacy-friendly analytics, like Plausible or Fathom. Add one line of code to a client's website and see their traffic in the CRM. Clients can see it too, in their portal.

**Add a website**
1. Go to **Site stats → + Add a website**, or use **+ Add site** on a company. Enter the domain, e.g. `upstatecoffee.com`. Subdomains are included automatically.
2. Copy the install code and paste it into the site's `<head>`. Setup steps for WordPress, Shopify, Squarespace, Wix, Webflow, Google Tag Manager and custom sites are on the same page.
3. Open the client's site. The settings page shows "Receiving data" within a few seconds.

**What you see**
- **Overview:** visitors, visits, pageviews, pages per visit, bounce rate, engaged time and conversions, each compared with the previous period. Also a visitors and pageviews chart and a live "online now" count.
- **Pages:** top pages with views, engaged time and scroll depth, plus landing pages with bounce rate.
- **Sources:**
  - **Channels:** organic search, paid search, paid social, organic social, local listing (Google Business Profile), AI assistants, email, SMS, QR code, referral and direct, each with its conversion rate.
  - **Paid clicks:** Google Ads (`gclid`), Microsoft Ads, TikTok ads and LinkedIn ads clicks are detected even without UTM tags.
  - **UTM campaigns** are listed with their source and medium.
- **Location:** country, state and city (see "Location data" below).
- **Devices:** device type, browser and operating system, including in-app browsers (Instagram, Facebook, TikTok), which show social traffic.
- **Actions,** tracked automatically: phone taps (`tel:`), email links, form sends (never the fields people typed), file downloads and outbound links. Custom events come from `data-syn-event="Book now"` on any element, or `syn('Order placed', {total: '42.50'})` in JavaScript.
- **Goals:** any action or page visit counts as a conversion, e.g. `/thank-you` or `/order/*/thanks`. Each new site starts with Form sent, Phone call and Email click.
- **Site speed:** Core Web Vitals from real visitors at the 75th percentile, Google's method, overall and for phones. Covers largest paint, responsiveness (INP), layout stability, first paint and server response.
- **When people visit:** a day-by-hour heatmap.
- **Click to filter:** click any page, source, campaign, country, device or goal to filter the whole report by it.
- **Export CSV:** daily totals for the selected period.

**Clients**
- **Portal:** with "Show these stats in the client's portal" ticked, the client sees the same report under **Your website**, read-only.
- **Monthly report:** with "Email a monthly report" ticked, each portal user gets a summary email in the first days of the month, as long as the cron job is running.

**Privacy**
- No cookies, no local storage and no fingerprinting. No cookie banner is needed under US law, but add a line to the client's privacy policy (suggested wording is on the settings page).
- IP addresses are never stored. Visitors are counted with a one-way code built from a random daily salt, which is deleted after a day. This is also why "visitors" means unique visitors per day.
- Data is kept 760 days, then deleted automatically by the cron job.

**Accuracy and filtering**
- **Ignored traffic:** bots, crawlers, uptime monitors, link previews and automated browsers.
- **Domains:** only the domains you list can send data.
- **Excluding visits:** open the client's site with `?syn_ignore=1` once on each of your browsers to leave out your own visits, and the client's team can do the same. `?syn_ignore=0` turns counting back on.
- **Ad blockers:** for sites on Netlify, Vercel or Cloudflare you can serve the script from the client's own domain to get past ad blockers. Instructions are on the settings page.

**Location data (optional, recommended)**
- **Default:** country comes from the visitor's time zone.
- **City and state:** download the free **DB-IP "IP to City Lite"** database (`.mmdb` format) from db-ip.com/db/download/ip-to-city-lite. Upload it to `storage/` in File Manager with any file name ending in `.mmdb`, and update it monthly. MaxMind GeoLite2 City works too. DB-IP's free license requires a credit link to db-ip.com, so add "IP geolocation by DB-IP" to the client portal footer or your privacy page.
- **Cloudflare:** if the CRM is behind Cloudflare, its country header is used automatically.

**Speed and scale**
- **Storage:** visits are stored in `storage/analytics.sqlite`, separate from the CRM.
- **Daily summaries:** each finished day is summarised, so reports stay fast. A year of a site with 600 visits a day loads in about a quarter of a second, and summing the daily numbers is exact. Filtered views read the raw data and take a few seconds on very busy sites.
- **The script** is about 3 KB compressed, loads after the page, and works with single-page apps (React, Next.js and Webflow route changes are counted). Add `data-hash="true"` for sites that use `#/` routes.

## Client portal and projects

Each client gets a private portal at `crm.stusyn.com/portal-login.php`. They can follow their projects, review and approve work, swap files and messages with you, and see and pay their invoices.

**Set up a project**
1. Go to **Projects → + New project**, or use **+ Project** on a deal or **+ New** on a company. Pick a starter checklist (website build, paid ads launch, social media management or TikTok Shop launch), or write your own steps.
2. On the project, under **Portal access**, click **Give access** next to each person who should see it, then **Email invite**. They get a personal link that signs them straight in.
3. Later visits: clients enter their email on the sign-in page and get a one-time link that expires in 30 minutes. There are no passwords to manage.

**What you do on a project**
- **Post an update** with optional files. With "Email the client" ticked, everyone with access gets an email with a link.
- **Needs approval** posts get an **Approve** / **Request changes** button for the client. Their answer and note show on the post, and you get an email and a task.
- **Tick off steps.** The progress bar follows the checklist, and you can also set it by hand. Changing the status posts a short note in the activity feed.
- **Files** you upload, and files the client sends (logos, photos, documents), are kept on the project. Both of you can download them.

**What the client sees**
- **Their home page:** a **Needs you** list (approvals and unpaid invoices), their projects with progress and the next step, the latest updates, and their invoices with **Pay** buttons.
- **Scope:** only projects shared with their company (or with them, if they have no company). Untick "Show in the client portal" on a project to keep it private while you set it up.
- **Notifications to you:** when they message, upload or approve, you get an email at your business email, a **Respond to…** task, and a note on their contact timeline. Projects with new client replies are marked "Client replied" and counted in the sidebar.

**Access and security**
- **Remove access** or **Reset link** (on the project or the contact) takes effect immediately and signs them out everywhere.
- **Client sessions are separate:** they use their own cookie and can't open any CRM page.
- **Uploads:** clients can only upload common document, image, design, video and archive files, up to your PHP upload limit. Files are stored in `storage/portal/`, which the web can't reach, and they always download rather than open in the browser.
- **Rate limits:** sign-in links and invite links are rate-limited, and the sign-in page never says whether an email belongs to a client.

## Online store (Syndicate Marketing website)

The Syndicate Marketing website sells packages online. The website only holds the shopper's cart; products, prices, Stripe and orders all live here, under **Store**.

**Set up (once):**
1. **Store → Settings:** enter the store website's address (e.g. `https://syndicatemarketing.co`) and your Stripe **secret key** (Stripe → Developers → API keys). Use the `sk_test_…` key first to place a test order with card `4242 4242 4242 4242`, then switch to the `sk_live_…` key.
2. **Stripe → Developers → Webhooks → Add endpoint:** `https://crm.stusyn.com/stripe-webhook.php`, with the events listed on the settings page. Paste its signing secret (`whsec_…`) into Store → Settings. Monthly renewals, failed cards, cancellations and refunds come in through the webhook.
3. Keep the 15-minute cron job running. It catches any payment the webhook missed and closes checkouts that were never finished.
4. Optional: Stripe → Settings → Customer emails → turn on receipts; Billing → Customer portal so monthly customers can update their card; Products → Coupons for promo codes.

**When someone pays, the CRM automatically:**
- creates or updates the contact and company (brand: Syndicate Marketing)
- creates a **won deal** and a **paid invoice** that matches what Stripe charged (discounts and tax included)
- creates a **project** in the client portal for each package or service, using the product's steps (add-on steps join the package, right after kickoff)
- turns on **client portal** access and emails the customer their portal link and next steps
- sets up **Site Stats** for their domain (website and ads plans)
- adds tasks for you: schedule the kickoff call, get ad account access
- emails you the order, with the customer's project brief

Monthly plans renew through Stripe; each renewal becomes a paid invoice here. If someone starts checkout and doesn't pay, a follow-up deal and task are created after a day, and that deal is marked won if they come back and buy.

**Products:** Store → Products. Change prices, descriptions, what's included, project steps and timelines. The website shows changes within a minute; no rebuild needed. Hide a product to stop selling it.

## Free website checkup

`syndicatemarketing.co/checkup.html` lets anyone check a website's home page: security, Google basics, phone speed (Google PageSpeed), broken links, visitor tracking and the domain renewal date, in plain English, with the products that fix each problem. It only fetches public addresses (private and internal networks are refused, DNS is pinned, redirects are re-checked) and never probes for private files. Anyone who asks for the report by email becomes a deal with source “Marketing site — checkup”. All reports, their scores and views are in **Checkups**, where you can also run one by hand. For a report someone asked to have emailed, **Draft quote** builds a quote from what it found (the most useful fix included, the rest optional) and opens it for you to check before sending. The outreach agent can run one too (`outreach-api.php?a=checkup`) and link it in a first email; you get a task when the prospect opens it. Code: `app/checkup.php`.

## Homepage previews

For outreach, the agent (or you, in Outreach → Homepage previews) can make a free draft home page for a prospect from their public details. It always shows a banner saying it’s a concept and not their real site, isn’t in Google, expires after 30 days, and the owner can remove it (and stop all emails) in one click. You get a task when a real person opens it. Code: `app/previews.php`, page `preview.php`.

## Review requests

A few days after a project is marked Complete, the client gets one email asking for an honest Google review (Settings → Review requests: paste your Google review link, choose the delay). Everyone gets the same email, nothing is offered in return, and each business is asked once a year at most. Nobody is asked while an invoice is overdue, after a refund, or if they’re on the do-not-contact list. Each project page shows whether its request is waiting, sent or skipped, with Send now and Don’t ask. Code: `app/reviews.php`.

## Quotes and proposals

For work that doesn't fit a store package. **Quotes → + New quote** (or **Create quote** on a deal).

1. Pick the contact and the deal, add items from your products (their project steps and timeline fill in automatically) or custom lines. Mark items **Monthly** for retainers, or **Optional** for extras the client can tick.
2. Choose how they pay: in full, a 50% or 25% deposit, or nothing up front.
3. **Send to client.** They get a link to a proposal page where they choose extras, type their name to accept, and pay through Stripe (card, Apple Pay, Google Pay). You're emailed when they open it, accept or decline.

When it's won, the CRM marks the deal won, records the payment as a paid invoice, **drafts the balance invoice** for you to send on delivery, creates the project with its steps in the client portal, turns on portal access, sets up Site Stats if a website or ads product is included, and adds a kickoff task. Monthly items become a Stripe subscription; each renewal becomes a paid invoice.

Quotes expire on their "valid until" date, with one automatic reminder three days before (needs the cron job). A quote agreed by phone can be **marked won** without online payment. Default terms, numbering and how long quotes stay valid are in **Settings → Quotes**.

## Online booking

People book time with you at **crm.stusyn.com/book.php**. Manage it under **Bookings**.

- **Meeting types** (Bookings → Hours & meeting types): starts with a free 20-minute **Discovery call** (creates a lead deal), a private **Project kickoff call** link for new clients, and a paid **TikTok Shop consultation** ($150, paid through Stripe when booked). Add, change or hide types any time; each has its own link to share.
- **Your hours**: weekly hours, minimum notice, how far ahead people can book, a break between meetings, and **time off**. Anything on your calendar as a scheduled consultation blocks those times too.
- **Video link**: add your Zoom or Google Meet room and it goes out with every video booking; otherwise send links yourself.
- Visitors see open times in **their own time zone**. Two people can't take the same time, and a paid booking holds its time for 35 minutes while they pay.

Each booking adds the contact (and a deal for new leads), puts the meeting on your **CRM calendar feed** as a consultation (so it syncs to your phone), records paid sessions as a paid invoice, and emails the guest a confirmation with a calendar file and a link to **reschedule or cancel**. Reminders go out the day before (needs the cron job). Booking a kickoff call marks the "Schedule kickoff call" task done. Order and proposal emails now include the kickoff booking link.

## Inbox (email inside the CRM)

Read and answer your @stusyn.com email in the CRM, next to each client's history.

**Connect a mailbox**
1. In cPanel, open **Email Accounts** and click **Connect Devices** next to the mailbox, e.g. hello@stusyn.com. Note the password and the server, usually `mail.stusyn.com`, with IMAP port 993 and SMTP port 465.
2. In the CRM, open **Inbox** (or **Settings → Email inbox**), enter the address and password, and click **Connect**. The CRM signs in to check the details before saving.
3. Repeat for careers@stusyn.com or any other mailbox. Each conversation shows which mailbox it came to, and you choose which one to send from.

**What it does**
- **Copies mail in:** the first time, it copies the last 60 days (up to 300 messages). After that it checks every couple of minutes while the CRM is open, and whenever you click **Check for mail**. To keep it current when you're not logged in, run the cron job below every 15 minutes.
- **Matches emails to people:** email from or to anyone in your contacts appears on their contact page and their company's page. Someone new gets an **Add as contact** button, and you can turn any email into a deal with **Create a deal from this email**.
- **Replying:** you can reply, reply all, forward (with the original attachments) or write a new email, with attachments and your signature. Mail goes out through the mailbox's own login, the same way your phone sends it, and a copy is saved to its Sent folder. Emails you send are also noted on the contact's timeline.
- **Stays in step with your phone:** mail is never deleted from the server. Read and unread status syncs back. Archiving only hides a conversation in the CRM.
- **Safe to open:** HTML emails are shown in a locked-down frame with no scripts or forms. Pictures from the internet stay hidden until you click **Show pictures**, because senders use them to see when you open mail. When you do show them, the CRM fetches them, so the sender doesn't see your own address or device.
- **Search:** the top search bar and the inbox search look through subjects, senders and message text.

**Cron job for email:** in cPanel → **Cron Jobs**, choose **Once Per Fifteen Minutes** (`*/15 * * * *`) with `php /home/USERNAME/crm.stusyn.com/cron.php`. The same job also sends recurring invoices and imports website leads.

**Notes**
- Mailbox passwords are stored encrypted. The key is `storage/mail.key`, and copies of emails are in `storage/mail/`. Neither can be reached from the web, and updates never overwrite them. The **Download full backup** includes the emails' text, but not `mail.key`. If the key is lost, just re-enter the mailbox passwords.
- If **Connect** says the certificate doesn't match, check the server name. As a last resort, untick **Check the server certificate** under **Advanced**.
- Changed a mailbox password in cPanel? Update it under **Inbox → Accounts → Edit**.
- Messages larger than 25 MB show their details only. Open them in webmail to get the attachments.
- Attachments you send are limited by PHP's upload size. To raise it, in cPanel → **MultiPHP INI Editor** set `upload_max_filesize` to `20M` and `post_max_size` to `25M`.

## Sending invoices and getting paid

- **Send to client** on an invoice emails it from your server, with the amount, due date and a private link. Sending marks the invoice as sent. Replies go to the business email in Settings.
- **The client link** (`/i.php?t=…`) shows the invoice to your client, who can print it or save it as a PDF. It doesn't need a sign-in, and it doesn't give access to anything else. Drafts stay hidden until you send them.
- **Viewed tracking:** the first time the client opens the link, the invoice shows "Viewed" and an entry is added to their timeline.
- **Payment link (optional):** paste a Stripe, Square or PayPal payment link on an invoice, or set a default in **Settings** for all new invoices. The client page and the email then show a **Pay online** button.
- **Send reminder** appears once an invoice has been sent. It emails a friendly nudge with the same link.
- **If email doesn't arrive:** cPanel's `mail()` usually works out of the box. If messages land in spam, turn on SPF and DKIM for stusyn.com under cPanel → **Email Deliverability**. You can always copy the client link and send it yourself.

## Recurring invoices

- On any invoice, set **Repeat** to every week, month, 3 months or year, and pick the date of the next one. This works well for retainers like monthly social media management.
- When the date arrives, the CRM creates a new invoice that copies the line items, tax, notes and payment link, with the same number of days to pay.
- **Email automatically** sends it to the client. If it's off, the invoice is saved as a draft and you get a task to review and send it.
- Write `{month}` in a line item, e.g. "Social media management — {month}", and each invoice shows its own month ("November 2026").
- The Invoices page shows your **recurring revenue per month** and has a **Recurring** filter. Use **Stop repeating** on the invoice to end a schedule.
- **On-time sending:** new invoices are created whenever you open the dashboard or Invoices. To make sure they go out on the right day even when you don't log in, add a cPanel cron job with this command: `php /home/USERNAME/crm.stusyn.com/cron.php`. Once a day is enough for invoices. Every 15 minutes also keeps the inbox current. It imports new website leads too.
- If invoices were missed (no cron and you didn't log in), the missed ones are created as drafts so the client never gets a pile of back-dated invoices at once. Only the newest one is emailed.

## Calendar

- Every consultation has an **Add to calendar (.ics)** button.
- **Settings → Calendar feed** gives you a private link you can subscribe to in Google Calendar, Apple Calendar or Outlook. Upcoming and recent consultations then show up automatically.
- Use **Reset link** if the feed link ever gets shared by mistake.

## Your data

- Everything lives in `storage/crm.sqlite`, which is blocked from the web.
- **Nightly backups** (cron, after 2 AM): the CRM and Site Stats databases, the stusyn.com database, `mail.key` and `totp.key` go into `storage/backups/YYYY-MM-DD/` (checked copies, last 14 days kept). A failure emails you and adds a task; the dashboard warns after 2 days without one. **Settings → Nightly backups** shows the status, runs one now, and lets an owner download the latest as a zip. `php app/cli.php backup` runs one from the server.
- These stay on the same server, so download one regularly and keep it somewhere safe: it contains client details and the keys.
- **Settings → Download full backup** still gives a one-off copy of just the database.
- CSV exports of contacts, companies, deals, consultations and invoices are under **Settings**.
- Invoices print cleanly to PDF from your browser with **Print / Save PDF**.

## Updating later

From the repo, run `npm run package:crm` and extract the new zip over the old files. Your data in `storage/` isn't in the zip, so it's never overwritten.

## Security

- **Private:** every page needs a sign-in, apart from client invoice links and the calendar feed, which use long random tokens. Search engines are told not to index anything, and bad tokens are rate-limited.
- **Sign-in and forms:** sign-in is rate-limited, passwords are hashed, and every form and action is CSRF-protected.
- **Strict content policy:** only scripts, styles and fonts from the CRM itself can load.
- **Forgot your password?** Use `/setup.php?reset=1` with the setup key.
