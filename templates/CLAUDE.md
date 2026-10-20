# Syndicate site build rules

This file sets the rules for every website Syndicate Holdings Group builds with Claude Code: our own brands and client sites alike.
Copy it to the root of a new project as `CLAUDE.md`, fill in **Project brief**, and leave the rest as is.
When this file and a request disagree, the request wins, but say so. When the project brief and the defaults below disagree, the brief wins.

---

## Project brief (fill in for each project)

- **Client / brand:**
- **What they do, in one sentence:**
- **Who the site is for:**
- **The one action the site should drive** (call, book, buy, form, visit):
- **Package** (One-page · Starter · Business · Online store · custom):
- **Pages:**
- **Domain:**
- **Contact details to publish** (only what the client approved):
- **Brand assets** (logo, colors, fonts, photos; or "none, design from scratch"):
- **Sites they like / dislike:**
- **Site Stats key** (CRM → Site stats → Add a website):
- **Deadline:**
- **Anything off-limits:**

---

## 1. Ground rules

1. **Honesty first.** Never invent reviews, testimonials, client logos, results, awards, years in business, team members, certifications or "as seen in" claims. If the client hasn't given it to us, it doesn't go on the site.
   - Placeholder content that needs the client's input is marked visibly (e.g. a dashed pink highlight with `class="fill"`) and listed in the final summary. It never ships to a live site.
   - Sample numbers in dashboards or mockups are labeled "Sample" on the page.
   - Concept or portfolio work for fictional businesses says so on every page, uses `.example` domains, `555-01xx` phone numbers, illustrations instead of stock photos of "staff", and claims no results.
   - Don't name real third-party brands as partners, insurers, suppliers or clients unless the client confirms the relationship.
2. **Privacy.** Never publish personal phone numbers, personal email addresses or full home addresses. For Syndicate brands, publish `hello@stusyn.com` (or `careers@stusyn.com` for hiring) and "Spartanburg, SC". For clients, publish only the contact details listed in the brief.
3. **Prices and promises** (timelines, guarantees, refund terms, "reply within a day") come from the client or our catalog, never from guesses. When you write one, list it in the final summary so the owner can confirm it.
4. **Ask only when blocked.** Make sensible decisions and note them. Ask the owner only for things only they can answer: domain, prices, legal promises, brand direction, real content.
5. **Never do outward-facing actions without being asked:** pushing to production, sending email, creating Stripe products, deleting data.

---

## 2. Stack and hosting

We host on **cPanel** (Apache or LiteSpeed, PHP 7.4+, SQLite). Build for that.

- **Default:** plain HTML, CSS and vanilla JavaScript, with a tiny Node build script (`build.mjs`) that wraps pages in a shared layout. No framework, no bundler and no npm runtime dependencies unless the project truly needs them, for example a large interactive app.
- **Pages** live in `src/pages/*.html`, starting with a JSON front-matter comment:
  `<!-- { "title": "...", "description": "...", "nav": "...", "robots": "noindex" } -->`
  The build fills `{{placeholders}}` from one `site` config object and **fails on any placeholder left unfilled**.
- **Server code** (forms, checkout, admin) is plain PHP with PDO + SQLite, with config in `app/config.php`. Keep `app/` and `storage/` out of the web root, or block them with `.htaccess`.
- **Output:** `dist/`, packaged with `npm run package` into a zip that extracts straight into the domain's folder. Exclude databases, uploads, sessions, logs and keys from the zip.
- **Payments:** Stripe. Our store runs in the CRM (`crm.stusyn.com`): sites send the cart to the CRM's `shop-api.php`, which opens Stripe Checkout, and Stripe reports payments to `stripe-webhook.php`. A site never holds Stripe secret keys or prices it trusts. The server always re-prices the cart.
- **Leads:** contact forms post to the CRM so every lead becomes a contact, a deal and a task. If a site has its own PHP form handler, it saves to SQLite, emails `hello@`, and the CRM imports it.
- **Analytics:** Syndicate Site Stats only (see section 7). No Google Analytics, Meta pixel or third-party trackers unless the client asks, in which case consent rules apply.
- Any dependency loaded from a CDN is pinned to an exact version. Fonts are self-hosted as `.woff2` files where we can; otherwise Google Fonts with a full fallback stack.

---

## 3. Design

Every client gets its own visual identity. A site should never look like a template, or like the last site we built.

**Before writing any CSS**, decide and write at the top of the stylesheet:
- **Palette:** 4–7 named tokens (`--bg`, `--ink`, `--muted`, `--accent`, …) taken from the client's world: their materials, products, place and trade. A bakery gets flour and crust; an HVAC company gets navy and safety orange.
- **Type:** a characterful display face used with restraint, plus a readable body face, plus an optional mono or utility face. Pick families that fit this client. Don't reuse the same pair from site to site.
- **Layout idea:** one line describing the concept.

**Rules**
- Every color and font comes from a token. No one-off hex values in components.
- Neutrals are chosen on purpose, with a slight hue toward the palette. No default greys.
- Use one bold move per page (a big headline treatment, a signature illustration, a color block). Keep everything around it calm.
- The hero says what the business does and offers the main action within the first screen on a phone. Don't use a full-viewport hero that pushes content off screen.
- Avoid the generic AI-generated looks: purple-to-blue gradient heroes, beige with a serif and a terracotta accent, emoji as icons, identical rounded cards with drop shadows everywhere, everything centered, "01 / 02 / 03" numbering on things that aren't a sequence.
- When there are no real photos, use CSS and SVG illustration, typography and color, never fake "team" or "customer" stock photos. Leave clearly marked spots where real photos will go.
- Group spacing with `gap` in flex and grid. Give the type scale and spacing scale a handful of steps and stick to them.
- Lay out cards in a row with consistent padding, baselines and button positions.
- Motion is subtle: one entrance sequence and hover feedback. Respect `prefers-reduced-motion`. Content must be visible without JavaScript or scrolling. Never leave content at `opacity: 0` waiting for an observer.
- Headings use `text-wrap: balance`. Body text runs about 60–75 characters per line.
- Light theme by default. Add dark mode only if the brand calls for it, and if you do, build it fully at the token level.

---

## 4. Writing the copy

- Write for the customer, in plain words. Lead with what they get, not what we do.
- Use short sentences and active voice. Be specific: "Pick up warm bread at 7am" beats "Experience artisanal excellence."
- Buttons say exactly what happens: "Book a cleaning", "Get a quote", "Pay $350 securely", never just "Submit" or "Learn more" on its own.
- Errors say what went wrong and how to fix it: "Enter a valid email address."
- No filler or hype: "unlock", "elevate", "seamless", "cutting-edge", "in today's fast-paced world", "worth noting".
- Avoid mannered devices: em-dash asides, "not X but Y" lines, colon-then-reveal sentences, scare quotes.
- Use real details only: real prices, hours, service areas and offers from the client. Use the client's own terms of art, like "21-point tune-up" or "48-hour ferment."
- Every page has a unique `<title>` (under 60 characters, with brand last) and meta description (under 155 characters, including the main offer and place).

---

## 5. Accessibility (not optional)

- Use semantic HTML: one `h1` per page, headings in order, `<nav>`, `<main>`, `<footer>`, and buttons for actions and links for navigation.
- Color contrast meets WCAG AA (4.5:1 for body text, 3:1 for large text and UI).
- Every interactive element works by keyboard and has a visible `:focus-visible` state. Add a skip link to `#main`.
- Every input has a `<label>`. Errors are tied to their field and announced. Status messages use `role="status"`.
- Images have meaningful `alt` text (`alt=""` for decorative ones). SVG icons are `aria-hidden` unless they carry meaning.
- Tap targets are at least 44px. Mobile menus and drawers trap focus and close with Esc.

---

## 6. Performance and SEO

- **Budget per page:** under 200 KB of CSS and JS combined, under 1 MB total on first load, LCP under 2.5s on a mid-range phone, CLS under 0.1.
- Preload the main font. Use `font-display: swap`. Images get `width` and `height`, `loading="lazy"` below the fold, and are served as WebP or JPEG at sensible sizes.
- No render-blocking third-party scripts. JavaScript is `defer`.
- Every site has: canonical URLs, Open Graph tags, `sitemap.xml`, `robots.txt` (with thank-you and checkout pages `noindex`), a real `404.html`, and JSON-LD for the business type (`LocalBusiness` or a subtype, `ProfessionalService`, `Product` for packages).
- Clean URLs where the host supports them. Never break an existing URL without a 301 redirect.

---

## 7. Analytics: Syndicate Site Stats

- Add the install snippet from the CRM to the `<head>` of every page, through the layout:
  `<script defer src="https://crm.stusyn.com/a.js" data-site="SITE_KEY"></script>`
- Calls, emails, forms, downloads and outbound links are tracked automatically. Add `data-syn-event="..."` to the main buttons (Book, Order, Get a quote) and call `syn('Purchase', {value})` on order confirmation pages.
- Set the matching goals in the CRM (Site stats → the site → Goals).
- Site Stats is cookie-free, so no banner is needed for it. If the client adds any cookie-based tracker, add a consent banner that blocks it until the visitor agrees, and honor Global Privacy Control.
- Never send names, emails or phone numbers to analytics.

---

## 8. Forms, checkout and security

- **Every form:** labels, client-side checks for convenience, **server-side validation as the real check**, a honeypot field, rate limiting, CSRF protection on same-origin PHP forms, and a clear success state. If the submission fails, offer a fallback (`mailto:hello@...`).
- Email goes out from an address on the client's domain with `Reply-To` set to the person who filled in the form. Remind the owner to set up SPF and DKIM.
- **Checkout:** the cart can live in the browser, but the server prices it and creates the Stripe Checkout Session. Payment is confirmed by the webhook, with a check on the thank-you page as backup. Fulfillment must be safe to run twice (idempotent). Show the total and any recurring charge before payment, and require agreement to the terms.
- **`.htaccess` on every site:**
  - `Options -Indexes -MultiViews`
  - block `app/` and `storage/`
  - deny `*.sqlite`, `*.log`, `*.ini`, `*.md`
  - security headers: `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options`
  - a strict `Content-Security-Policy` that lists only the hosts the site uses
  - HTTPS redirect and HSTS lines, left commented with a note to turn them on once SSL is active
- Never commit secrets. API keys go in config files that aren't committed, or encrypted in the CRM.
- Escape all output (`htmlspecialchars` in PHP). Use prepared statements only. Uploads get checked type and size and stored outside the web root under random names.

---

## 9. Legal pages

Every site that collects anything gets a **privacy policy**. Sites that sell get **terms of sale**, covering what's included, payment, monthly billing and cancellation if any, refunds, ownership and limits on liability. Write them in plain English, list the real processors (Stripe, the host, the email provider), and put "Last updated" at the top. Don't copy another company's legal text. Flag any legal promise for the owner to confirm.

---

## 10. Testing before calling anything done

- `node build.mjs` passes with no unfilled placeholders. `php -l` passes on every PHP file.
- Run it locally (`php -S`) and drive it with **Playwright** (Chromium is preinstalled; launch with `executablePath: '/opt/pw-browsers/chromium'` if versions differ):
  - every page loads with **zero console errors**
  - **no horizontal scroll at 390px wide**, checked on every page
  - every form submits successfully and shows its errors correctly
  - the main action works end to end (lead reaches the CRM, order reaches Stripe test mode and comes back paid)
- Take screenshots at 1440px and 390px and **look at them**. Fix overlap, wrapping, cramped spacing and clipped text before reporting.
- Test checkout against Stripe **test mode** (card 4242 4242 4242 4242), or a mock of the Stripe API. Never use live keys for testing.
- Site Stats ignores headless browsers on purpose. In tests, mask `navigator.webdriver` and use a normal user agent if you need to see events.

---

## 11. Code that reads hand-built

The client, their next developer, or a curious visitor will open the source. It should read like a careful person wrote it for this one business, not like generated boilerplate. Sections 3 and 4 cover how the site looks and reads; this covers the code under it.

**Comments**
- Comment only the why the code can't show: a browser quirk, a business rule, a workaround and what it works around. One line is usually enough.
- Don't narrate the code (`// Initialize the menu`, `// Loop through the items`, `// This function handles the form`).
- No section banners or bookends: `<!-- Hero Section -->`, `<!-- End Header -->`, `/* ===== BUTTONS ===== */`, `// ---- Helpers ----`. Class names and headings already say what a block is.
- No docblocks on small, obvious functions. No chatty or instructional comments ("Note:", "Feel free to…", "You can customize this…", "Replace with your…", "Updated to fix…").
- No emoji in code, comments, console output, file names or commit messages.

**Nothing left over**
- No placeholders in a live site: lorem ipsum, "Your Company", "John Doe", `YOUR_API_KEY`, `href="#"`, "Coming soon" sections. Content still waiting on the client uses `class="fill"` (section 1) and never ships.
- No dead code: commented-out blocks, unused functions, CSS rules or tokens nothing uses, libraries nothing calls.
- No `console.log`, `alert()` or debug output in shipped files.
- No scaffold or version file names: `script.js`, `styles.css`, `index-new.html`, `final-v2.css`, `test.html`, `old/`. Name files for what they hold (`menu.js`, `quote-form.js`, `site.css`).
- No generator meta tags or "Built with…" credits, other than a footer credit the client has agreed to.

**How it's written**
- Write the smallest code that does the job. No `try/catch` that only logs `Error:`, no checks for things that can't happen, no config objects, wrappers or helpers for something used once.
- Name things after this business's content (`.menu-board`, `.service-area`, `.bake-schedule`), not a generic kit (`.hero-section`, `.feature-card`, `.cta-button` on every site we make).
- One naming style per project, used everywhere. Related CSS rules sit together; no empty rules or duplicate declarations.
- Messages a visitor sees are plain and specific ("That didn't send. Call us at …"), never "Oops! Something went wrong 😅".

**READMEs and hand-off notes**
- Plain headings and plain words: what the site is, how to edit it, where things live, how to deploy, who to contact. No emoji headings ("✨ Features", "🚀 Getting started") and no sales tone.

**Before delivery, search for tells.** Every hit is removed or is a deliberate choice you can explain:

```
grep -rniE "lorem|your company|john doe|replace with|your_api|todo|fixme|console\.log|alert\(|href=\"#\"|<!-- *(end|hero|section|header|footer)|[=-]{5,}|elevate|unlock|seamless|cutting-edge|delve|look no further" --include=*.{html,css,js,php,md} src
LC_ALL=C.UTF-8 grep -rnP "[\x{1F300}-\x{1FAFF}\x{2705}\x{2728}\x{26A0}]" --include=*.{html,css,js,php,md} src
```

---

## 12. Delivering

- Commit in small, clear steps on a feature branch. Commit messages say what changed and why. Don't push to `main` or open a PR unless asked.
- Package the zip and say exactly where to upload it (which cPanel folder) and what to set afterwards (domain, SSL, Stripe keys and webhook, cron job, Site Stats key, SPF/DKIM).
- **Final summary:**
  - what was built
  - what was tested and the result
  - anything not tested
  - every placeholder, price and promise the owner needs to confirm
  - next steps
- When the owner wants to see the site, publish a preview where the backend is simulated and clearly labeled as a preview.

---

## Definition of done (checklist)

- [ ] Brief filled in; design tokens written at the top of the CSS
- [ ] Unique visual identity; no template look; no invented content
- [ ] Copy is plain, specific and checked against section 4
- [ ] Mobile first; no sideways scroll at 390px; tap targets ≥ 44px
- [ ] Keyboard and screen-reader basics pass; contrast AA
- [ ] Titles, descriptions, canonical, OG, JSON-LD, sitemap, robots, 404
- [ ] Site Stats installed; key buttons tagged; goals set
- [ ] Forms validated on the server, rate-limited, honeypot, fallback
- [ ] `.htaccess` with security headers and CSP; private folders blocked
- [ ] Privacy policy (and terms of sale if selling)
- [ ] Zero console errors; screenshots reviewed at desktop and mobile
- [ ] Code reads hand-built: section 11 search is clean, no banners, leftovers or debug output
- [ ] Zip packaged; upload and setup steps written; items to confirm listed
