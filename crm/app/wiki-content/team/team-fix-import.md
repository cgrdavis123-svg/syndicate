---
title: An ads import won’t read
category: team-fixes
order: 40
summary: Fixing CSV imports on ads.stusyn.com.
---
- The export must have **a day (or date) column, a campaign column and a cost column**. In the platform, break the report down **by day**.
- Google Ads files in UTF-16 and with title rows are fine; totals rows are skipped.
- Pick the right **platform** when uploading; campaigns are matched by name within that platform.
- Imported the wrong file? **Undo** it on the Import page; only the rows that import wrote are removed.
