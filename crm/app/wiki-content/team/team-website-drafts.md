---
title: Website drafts and the builder
category: team-clients
order: 15
summary: How paid website projects get a first draft built for you, how you review it, and how the client’s approval and change requests move it on.
keywords: website drafts, builder, build agent, preview, draft link, rounds of changes, approval
---
## What it does

Every paid website order (and every won quote with a website package) gets a **website draft** in **CRM → Website drafts**. A scheduled task, the builder, picks it up, builds the site from what we know, and uploads it. You look at it, then send it to the client. Their **Approve** or **Request changes** in the portal moves it on by itself.

Nothing reaches the client until you press **Send to the client for approval**, and nothing goes live from here. Putting an approved site on its domain is still your job.

## The steps

1. **Order paid.** The draft starts as *Waiting for kickoff*.
2. **Kickoff.** The client fills in the **kickoff form** in their portal (linked in their order email, on their portal home and at the top of their project). Sending it starts the draft straight away and fills in **Contact details the client approved** from what they chose to show. If they ticked “I’d also like a kickoff call”, or booked a call instead, the builder starts 3 hours after the call ends; write what you learned in **Notes for the builder** before then. **Ready for the builder** starts it any time. Clients who haven't sent the form or booked a call after 3 days get one reminder email.
3. **The builder runs** a few times a day. It reads the checkout brief, the add-ons in the order, your notes, the client’s portal files and messages, and `templates/CLAUDE.md`, then uploads the site and a summary of what it assumed. If it can’t go on, it stops and asks (status *Needs you*); answer in your notes and press Ready again.
4. **You review.** You get an email and a task. Open the preview (check it on your phone), read the summary and the “to confirm” list. If it isn’t right, use **Send it back to the builder** with notes; that doesn’t use one of the client’s rounds.
5. **Send it.** The client gets an email and an approval box in their portal with the preview link.
6. **The client decides.**
   - **Request changes:** their note goes to the builder for the next draft. Each request uses one of the rounds their package includes (2 for One-page and Starter, 3 for Business and Online store). A request beyond that comes to you instead, to make anyway or to quote.
   - **Approve:** you get a task to put it live. Download the **Source .zip** from the draft page and deploy it as in [[team-onboarding-website]].

## The preview link

Each draft has its own private link, `crm.stusyn.com/draft/…/`. It isn’t in Google, carries a “Draft for review, not live yet” bar, and its forms can’t send anything. It runs in a sandbox, so nothing in a draft can touch the CRM. Older drafts keep working, so you can compare.

## Things to know

- **Hold** keeps the builder away from a draft (for example if you’d rather build it yourself). **Ready for the builder** lets it back in.
- **Switch the builder off** on the Website drafts page to stop it claiming anything new.
- A draft the builder starts but doesn’t finish in 8 hours goes back in the queue; after 3 tries it comes to you.
- An online store draft is the design, pages and product pages. Real checkout, Stripe and shipping are set up by you before launch.
- The builder signs in with the same agent key as outreach. If you make a new key in **Outreach**, update it in the builder’s scheduled task too.
- The weekly summary lists drafts waiting on you, ones still waiting for a kickoff after a week, and ones the client hasn’t answered for 5 days.
- Honesty rules apply as always: the builder never invents reviews, results, years in business or claims. Anything it couldn’t know is marked to confirm.
