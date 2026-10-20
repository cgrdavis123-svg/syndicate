---
title: The cron job
category: team-systems
order: 40
summary: One job every 15 minutes runs everything automatic.
---
cPanel → Cron Jobs → every 15 minutes:

```
php /home/USERNAME/crm.stusyn.com/cron.php
```

Each run: website lead import, recurring invoices, email sync, Site Stats cleanup and reports, store sync (plus yearly plan reminders and checkout reminders), quote reminders, booking holds and reminders, care checks and reports, ads reports and approval nudges, outreach (replies, opt-outs, bounces, then sending what’s due) and the Monday summary email.

If `php` isn’t found, use `/usr/local/bin/php`. If care sites say “Checked” long ago, the cron job has stopped.
