---
title: Stripe setup and going live
category: team-systems
order: 30
summary: Keys, webhook, receipts and the customer portal.
---
1. Stripe → Developers → API keys: copy the secret key (test first).
2. Developers → Webhooks: endpoint `https://crm.stusyn.com/stripe-webhook.php` with checkout, invoice, subscription and charge.refunded events. Copy the signing secret.
3. CRM → Store → Settings: paste both (stored encrypted) and set the shop site URL.
4. Test with card 4242 4242 4242 4242.
5. **Go live**: live secret key **and** a separate live webhook with its own signing secret.
6. Settings → Emails: turn on payment receipts. Settings → Billing → Customer portal: turn on (we send clients that link to update cards).
7. Deactivate the old $500 Payment Link.

If a webhook is missed, the cron job checks pending orders against Stripe directly.
