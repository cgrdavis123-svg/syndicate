---
title: A client’s website is down
category: team-fixes
order: 10
summary: What to check, in order.
---
1. Open the site yourself, on your phone too. Is it really down, or just slow?
2. Check **Care → the site**: the last error (timeout, 500, SSL, DNS).
3. **SSL error**: run AutoSSL in cPanel, or check the certificate on the host.
4. **DNS / domain**: has the domain expired? Check the renewal date on the Care page and the registrar.
5. **500 errors**: check the host’s error log; a recent plugin or theme update is the usual cause. Restore from the last backup if needed.
6. **Host outage**: check the host’s status page; open a ticket.
7. Log what happened and the fix as a shared **Fix** entry. The client gets the recovery email automatically.
