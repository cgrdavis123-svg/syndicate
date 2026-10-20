---
title: Prices and the product list
category: team-rules
order: 30
summary: One source of truth, so prices never drift.
---
- Live prices are edited in **CRM → Store → Products**. The store, checkout, quotes and the help center all read from there.
- `crm/app/catalog.json` builds the marketing site’s static pages and fallback. If you change a price, change it in both.
- In the help center, never type a price: use `{{price:sku}}`, `{{timeline:sku}}`, `{{features:sku}}` or `{{products:category}}`.
- Give clients at least 30 days’ notice before changing a monthly plan’s price.
