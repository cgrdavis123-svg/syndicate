---
title: Referrals and paying referrers
category: team-daily
order: 50
summary: How referral links are tracked, when earnings appear, and how to pay them out each month. Sales reps use the same system at 10%.
keywords: referral, referrer, commission, payout, w-9, 1099, sales rep, credit, affiliate
---
## How it works

- People join at syndicatemarketing.co/referrals.html (or you add them in **CRM → Referrals → Add someone by hand**). Each gets a code and a link like `syndicatemarketing.co/?ref=code`.
- The website remembers the code in the visitor’s browser for 60 days and sends it with an order, a contact form message or a discovery-call booking. The CRM ties that contact to the referrer, but only if they’re new: not already a paying client, not already talking to us, and not the referrer’s own business.
- Every cron run, `ref_sweep()` looks at paid invoices for referred clients and records an earning: the rate (5% by default, per person) of the invoice minus sales tax. Only payments in the 12 months from the client’s first payment count. Ad spend never reaches us, so it never counts.
- Each earning is **held 30 days**, then becomes **ready**. A refund in Stripe shrinks or cancels it; if it was already paid out, the difference comes off the next payout. A voided invoice cancels its earning.
- The referrer gets an email when someone they sent becomes a client, and can see everything at `crm.stusyn.com/refer.php` (emailed sign-in link, no password).

## Someone named a referrer when they got in touch

Open the referrer in **CRM → Referrals**, pick the contact under “People they referred”, and click **Tie to …**. Payments from that day on count.

## Paying out (days 1–5 of each month)

A task “Pay referral earnings” appears when anything is ready. For each person with a **Ready to pay** amount:

1. **Account credit:** click **Record payout**. If they have a live monthly plan, the CRM adds the credit to their Stripe customer balance and it comes off their next bill by itself. If not, add a “Referral credit” line (negative amount) to their next invoice or quote by hand.
2. **Cash:** you need a **W-9 on file** first (ask for it by email, keep it with your tax records, never in the CRM, then tick the box). Send the money (PayPal, Venmo or check, from their payout details), then click **Record payout** with the reference. Cash starts at $25.
3. They’re emailed a receipt either way.

> **Heads up:** anyone paid $600 or more in cash commissions in a year needs a Form 1099-NEC. Ask the accountant each January; the Referrals page totals what each person was paid.

## Sales reps

Commissioned sales reps use the same system: add them by hand as **Commissioned sales rep** (10%, cash). Their earnings, holds and payouts work the same way.

## When something isn’t right

- **Don’t count:** on the referrer’s page, use “Don’t count” on a referral (self-referrals, spam, someone who was already talking to us). Unpaid earnings for it are cancelled. The reason stays private.
- **Pause or close** a referrer in their Details. Paused: new people stop being tied to them, existing clients keep earning. Closed: nothing new earns and they can’t sign in.
- Rules referrers agree to (honest claims, disclose the commission, no spam, no paid search on our name) are on the referrals page. Close accounts that break them.
