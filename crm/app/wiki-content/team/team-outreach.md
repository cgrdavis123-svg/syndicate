---
title: The outreach agent
category: team-daily
order: 45
summary: How cold outreach runs on its own, what it will and won’t do, and when it needs you.
keywords: outreach, cold email, prospects, unsubscribe, agent, leads
---
## What it does

The outreach agent finds local businesses that could use us, checks their websites, writes a short personal email and two follow-ups, and answers replies. It sends from **outreach@syndicatemarketing.co**, never from hello@, so a bad week can’t hurt order emails and invoices.

The work is split so each part is done by whatever does it reliably:

- **The agent** (a scheduled task) researches prospects, writes the emails, and reads replies. It talks to the CRM through `outreach-api.php` with the key from CRM → Outreach.
- **The CRM** does everything with fixed rules: sends on weekdays 9 AM–5 PM, spaces sends through the day, follows up 4 and 10 business days after the first email, stops the moment someone replies, records bounces and opt-outs, and keeps the do-not-contact list.

## The free checkup in emails

The agent can run the [free website checkup](https://syndicatemarketing.co/checkup.html) on a prospect’s site first (`outreach-api.php?a=checkup` with `url` and `business`). It gets back the report link, the score and each finding, so the first email can mention one or two real things on their site and link the report instead of a generic pitch. The report is the same one anyone can run, so it never claims more than the page shows.

When someone opens a report the agent sent, the CRM adds a task (“… opened the website checkup we sent”), since that’s a good moment to reply. Every report is listed in CRM → Checkups with its views.

## Homepage previews

For a business with no website, or one that needs rebuilding, the agent can make a **free homepage preview** (`outreach-api.php?a=preview`): a draft home page in one of six styles, built only from what they publish themselves (name, kind of business, town, phone, services, areas, hours). The first email links it as a concept we made for them. Every preview has a banner saying it’s a free concept and not their real site, isn’t in Google, expires after 30 days, and has a **Remove this preview** link. If they remove it and ask not to be contacted, they go on the do-not-contact list and their emails stop.

When a real person opens it (not a link scanner, and not you while signed in), the CRM adds a task. See them all in **Outreach → Homepage previews**, where you can also extend one, take it down, or make one by hand before a call.

Never add logos, photos, reviews, prices, awards or anything they haven’t said about themselves.

## The rules it can’t break

- Every email has your name, the business postal address, a “reply no thanks” line and an unsubscribe link, plus the unsubscribe header mail apps use for their one-click button.
- “No thanks”, “not interested”, “unsubscribe” and similar replies are handled by the CRM on its own, straight away. That address, and the whole business domain, go on the do-not-contact list for good.
- It never emails anyone already in the CRM as a contact, anyone on the do-not-contact list, or a second person at a business it already emailed.
- Links can only point to our sites or the prospect’s own website.
- Sending starts at 8 emails a day and goes up by 4 each week, to 30. If more than 8% of emails bounce over two weeks, outreach switches itself off and emails you.

## What happens when someone replies

- **Interested:** the agent answers (usually with the free discovery call link), and the CRM creates a contact, a “Cold outreach” deal and a task for you, and emails you.
- **A question it can answer from our published prices and promises:** it answers.
- **Anything else** (custom pricing, phone calls, complaints, anything unclear): it hands it to you with a suggested reply. You get an email and a task, and the prospect shows under **Outreach → Needs you**.
- **Auto-replies** (“out of office”) don’t stop the follow-ups.

If you answer a prospect yourself, from the CRM inbox or your mail app, the agent leaves that conversation to you. **I’ll answer this** and **Let the agent answer** on the prospect page switch it by hand.

## Your part

- Skim **Outreach** a couple of times a week, and the Monday summary email.
- Handle anything under **Needs you** within one business day.
- To stop emailing someone, open them and choose **Stop and never email again**, or add the address or domain under **Do not contact**.
- If outreach pauses itself, look at the bounced prospects (usually bad addresses from a directory), then switch it back on.

## Setup (once)

1. In cPanel, create the mailbox outreach@syndicatemarketing.co. Under Email Deliverability, make sure SPF and DKIM are valid for syndicatemarketing.co, and add a DMARC record.
2. In the CRM, connect it under Inbox → Mailboxes.
3. In CRM → Outreach → Settings, choose that mailbox and enter the postal address.
4. Make an agent key and add it as a network secret for `crm.stusyn.com` on the cloud environment the scheduled tasks use (header `Authorization`, prefix `Bearer`). Set that environment’s network access to include crm.stusyn.com.
5. Switch outreach on.
