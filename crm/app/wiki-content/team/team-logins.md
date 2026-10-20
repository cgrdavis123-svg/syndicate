---
title: Staff logins and the team
category: team-systems
order: 55
summary: Invite someone, owners vs members, removing a login, and making two-step sign-in a must.
keywords: team, users, invite, staff, login, owner, member, add user
---
Everyone who works in the CRM, ads or wiki has their own login. One login covers all three.

## Invite someone
1. CRM → **Team** → Invite someone. Enter their name and email and pick a role.
2. They get an email from hello@ with a link to choose their password. It works once and expires in 7 days.
3. After they join, they land on Settings to set up two-step sign-in (a passkey takes about a minute). You get an email when they join.

To resend, invite the same email again; the old link stops working. Cancel an invite under **Waiting to join**.

## Owners and members
- **Owners** manage the team: invite, change roles, remove people, clear someone’s two-step sign-in, and require two-step for everyone.
- **Members** can use everything else in the CRM, ads and wiki, including clients, invoices and the store.
- The team always keeps at least one owner. Nobody can remove themselves.

## When someone leaves
Team → their name → Manage → **Remove from team** (with your password). They’re signed out everywhere at once. Their past notes and messages keep their name.

## Lost phone
Team → their name → Manage → **Clear their two-step** (with your password). They sign in with their password and set it up again. If the only owner is locked out, use `php app/cli.php 2fa-off you@example.com` on the server (see [[team-two-step]]).

## Require two-step sign-in
Team → **Require it for everyone**. Anyone without a passkey or authenticator app is sent to Settings until they add one. Turn on your own first.
