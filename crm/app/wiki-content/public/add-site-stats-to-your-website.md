---
title: How do I add Site Stats to my website?
category: analytics
order: 30
summary: Copy one line of code into your site’s header. Steps for common platforms.
keywords: install, snippet, tracking code, wordpress, shopify, squarespace, wix, webflow, header
---
Websites we build already have it. For other sites, find your **tracking code** in {{portal}} under your website. It’s one line that looks like this:

```
<script defer src="https://crm.stusyn.com/a.js" data-site="YOUR-KEY"></script>
```

Paste it into the `<head>` of every page. The menus differ by platform:

| Platform | Where to paste it |
|---|---|
| **WordPress** | A header-code plugin (like WPCode), or your theme’s header settings |
| **Shopify** | Online Store → Themes → Edit code → `theme.liquid`, just before `</head>` |
| **Squarespace** | Settings → Advanced → Code Injection → Header (needs a paid plan that allows code) |
| **Wix** | Settings → Custom code → Add code → Head, all pages (needs a premium plan with a domain) |
| **Webflow** | Site settings → Custom code → Head code, then publish (needs a paid site plan) |

Visits appear in your dashboard within a minute. Rather we did it? That’s part of [[analytics-setup-service|Analytics setup]] and [[care-for-sites-we-didnt-build|website care]].

> **Tip:** Then [[exclude-your-own-visits|exclude your own visits]] so your numbers stay clean.
