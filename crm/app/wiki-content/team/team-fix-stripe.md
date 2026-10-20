---
title: An order paid but nothing happened
category: team-fixes
order: 30
summary: When the webhook didn’t fire or failed.
---
1. Stripe → Developers → Webhooks: look for failed deliveries to `stripe-webhook.php` and resend.
2. Check the signing secret in CRM → Store → Settings matches the endpoint (test and live secrets differ).
3. The cron job also checks pending orders against Stripe every run, so a missed webhook is usually fixed within 15 minutes if cron is running.
4. Events are de-duplicated, so resending is always safe.
