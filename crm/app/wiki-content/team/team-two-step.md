---
title: Two-step sign-in
category: team-systems
order: 60
summary: Turn it on, keep the recovery codes, and what to do if you’re locked out.
---
- **CRM**: Settings → Two-step sign-in. **Hiring HQ**: Account. Scan the QR code with any authenticator app.
- Save the 10 recovery codes in your password manager. Each works once.
- Lost your phone **and** codes? On the server, run `php app/cli.php 2fa-off you@example.com` from the CRM folder (or the main site’s folder for Hiring HQ).
- Forgot your password? `setup.php?reset=1` with the setup key and your account email sends a one-time link.
