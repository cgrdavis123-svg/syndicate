# The blog — how posts are written and published

Posts live here, one `.html` file per post. The build (`build-marketing.mjs` with `marketing/blog-render.mjs`) turns each into `blog-<file>.html`, lists it on `blog.html`, the home page strip and “More from the blog”, and adds it to `blog.xml` and `sitemap.xml`. Only `.html` files here are read; this README isn’t.

## Front matter

```html
<!-- {
  "title": "What a contractor’s website needs to get estimate requests",
  "seoTitle": "Optional: the browser-tab and Google title, if different (the site name is added)",
  "description": "One or two sentences, ~150–160 characters. Shown under the title, in Google and in the feed.",
  "excerpt": "Optional shorter teaser for the cards (defaults to description).",
  "date": "2026-10-23",
  "updated": "Optional, when a published post is meaningfully revised",
  "rank": 1,
  "category": "Websites",
  "related": ["web-one-page", "web-starter"],
  "draft": true
} -->
```

- **category** must be one of: `Websites`, `Local search`, `Ads`, `Website care`, `Gaming & Discord` (the build refuses anything else; add new ones to `CATS` in `blog-render.mjs`).
- **related** are 1–3 catalog SKUs (`crm/app/catalog.json`), shown as product cards under the post.
- **rank** only orders posts published on the same day (lower first).
- **draft: true** builds the post at its address with a “Draft” bar, `noindex`, and keeps it off the blog index, the home strip, the feed and the sitemap. Publishing = remove `"draft": true` and set `date` to the publish day.
- The file name is the address: `contractor-website-that-gets-estimate-requests.html` → `/blog-contractor-website-that-gets-estimate-requests`. Lowercase words and hyphens, built around what people search for. Never rename a published post.

## Body

Plain HTML, no wrapper: `<p>`, `<h2>` (sections; they get ids and form the “On this page” list), `<h3>`, `<ul>`, `<ol>`, `<ul class="checklist">`, `<p class="note"><b>…</b> …</p>`, `<table>` with `<thead>`. All the site’s template tags work: `{{price:sku}}` for every price (never type a price), `{{quote:kirbys:nolink}}` for Kirby’s real words, `{{bookDiscovery}}`. Internal links are relative (`care.html`, `blog-google-business-profile-checklist.html`); external links to official sources get `target="_blank" rel="noopener"`.

## What makes a good post here

- **For owners, not marketers.** A question a small-business owner actually asks (“how much…”, “do I need…”, “why isn’t…”), answered plainly and usefully even if they never buy from us. 900–1,500 words.
- **Voice** — CLAUDE.md §20: plain, direct, warm, honest, short sentences, “we” for the business. Curly quotes and apostrophes.
- **Honesty** — CLAUDE.md §2 applies in full: no invented stats, studies, percentages, clients, results, reviews or quotes. The only client we may name is Kirby’s Home Improvement (with its real quote). Facts about Google, Meta, TikTok or Discord come from their own help pages, checked on the day and linked. If you can’t check it, leave it out. No guarantees, no “#1”, no fake urgency.
- **One clear next step** at the end: the free checkup, a related page, or a package, with prices from the tags.
- **Topics that fit what we sell**: websites and what they cost, local search and Google Business Profile, ads on Google, Meta and TikTok, website care and fixing problems, analytics, Discord and websites for game studios, creators and gaming businesses. Upstate SC angles are welcome where they’re genuine; no near-copy posts for different towns or trades.
- **Don’t repeat** a topic already covered; link to it instead.

## Who publishes

Grady approves every post. The weekly blog task writes one post as a draft, pushes it, and sends Grady the preview link. He publishes it by asking Claude in the Syndicate project to “publish the blog draft” (or to change it first).
