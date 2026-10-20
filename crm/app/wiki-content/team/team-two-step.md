---
title: Two-step sign-in
category: team-systems
order: 60
summary: Passkeys (Face ID, Touch ID) or an authenticator app, the recovery codes, and what to do if you’re locked out.
keywords: passkey, face id, touch id, iphone, 2fa, authenticator
---
- **CRM**: Settings → Two-step sign-in. **Hiring HQ**: Account (authenticator app only for now).
- **Passkey (easiest on iPhone):** name it, tap **Add a passkey**, confirm with Face ID or Touch ID. It syncs through iCloud Keychain to your other Apple devices. At sign-in, tap **Use a passkey**. On a computer without one, the browser can show a QR code to use your iPhone instead. One passkey works on the CRM, ads and wiki (they all belong to stusyn.com).
- **Authenticator app:** scan the QR code with any authenticator app. You can have both; either works at sign-in.
- Save the 10 recovery codes in your password manager. Each works once.
- Lost your phone **and** codes? On the server, run `php app/cli.php 2fa-off you@example.com` (removes the app and all passkeys) from the CRM folder (or the main site’s folder for Hiring HQ).
- Forgot your password? `setup.php?reset=1` with the setup key and your account email sends a one-time link.
