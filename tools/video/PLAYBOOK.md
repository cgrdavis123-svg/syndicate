# TikTok playbook — Syndicate Marketing

The scheduled task reads this file on every run. It writes one brief, checks it, renders it and sends the video plus caption to Grady, who posts it from his phone. Follow it exactly; when it conflicts with CLAUDE.md, CLAUDE.md wins.

## 1. What the research says (October 2026)

- **What TikTok ranks on:** watch time, completion, replays and shares count most; then comments, follows and saves; likes and hashtags count less. TikTok also reads on-screen text and spoken words for search. ([Hootsuite](https://blog.hootsuite.com/tiktok-algorithm/))
- **The first 3 seconds decide it.** Open with the promise or the question the video answers. Never open on a logo. ([Hootsuite](https://blog.hootsuite.com/tiktok-algorithm/))
- **Over 30% watch on mute:** every point must be readable on screen. ([Hootsuite](https://blog.hootsuite.com/tiktok-algorithm/))
- **Search matters:** put the words people search for ("HVAC website", "Minecraft server wiki", "Discord server setup") in the on-screen text and the first line of the caption. Mix one or two broad hashtags with niche ones. ([Hootsuite](https://blog.hootsuite.com/tiktok-algorithm/), [Sprout Social](https://sproutsocial.com/insights/small-business-tiktok/))
- **Frequency:** across 11.4M posts, median views stay around 460–510 whatever the frequency; posting more mainly raises the odds of an outlier (90th percentile 3.7K at 1/week vs 14.4K at 11+/week). Buffer recommends 2–5 posts a week, quality first. We post twice a day by Grady's choice, so **variety is mandatory**. ([Buffer](https://buffer.com/resources/how-often-should-you-post-on-tiktok/))
- **Repetition is penalised:** automated or repetitive content can be treated as spam and pulled from the For You feed. No two videos in a week may share the same audience + format + style, and never reuse a hook. ([ContentIQ summary of TikTok rules](https://contentiq.media/rules/tiktok))
- **People beat graphics** for small businesses: founder-led, behind-the-scenes and video replies to comments do best. So every Monday morning run also writes a short script for Grady to film himself. ([Sprout Social](https://sproutsocial.com/insights/small-business-tiktok/))
- **Timing:** engagement peaks 6–11 PM; afternoons are weakest; Sunday 9 AM and weekday mornings also do well. We post at about **7 AM and 7 PM Eastern**. ([Buffer](https://buffer.com/resources/best-time-to-post-on-tiktok))
- **Posting:** TikTok's API forces posts from unaudited apps to private, so Grady posts by hand from his phone. ([TikTok developers](https://developers.tiktok.com/docs/en/content-posting-api-reference-direct-post))

## 2. Rotation (so no two posts are alike)

Work out the slot from the run's date in America/New_York: `slot = (day_of_year × 2) + (0 for the morning run, 1 for the evening run)`.

**Audience** = `AUDIENCES[slot % 12]`:
1. HVAC and plumbing
2. Indie game studios
3. Landscaping and lawn care
4. Minecraft servers and SMPs
5. Dentists and dental offices
6. Streamers and content creators
7. Roofing and electrical
8. Salons and barbers
9. Esports teams
10. Bakeries, cafés and restaurants
11. Gaming businesses (stores, cafés, leagues)
12. Any small business (general)

**Format** = `FORMATS[(slot // 2 + day_of_year) % 10]`:
1. **What you get** — one package, its real features from `crm/app/catalog.json`, then the price.
2. **Customer journey** — someone needs help → finds the site on a phone → taps call/book → it shows up in Site Stats.
3. **Myth vs fact** — `list` scene with ✕/✓ items (`{"t":"...","no":true}` for myths). Only facts we can stand behind.
4. **Checklist** — "Does your site have…" with 4–5 checks that our packages cover.
5. **Price reveal** — `words` hook, then `prices` with the relevant packages.
6. **What you see every month** — Site Stats, the care report, the ads dashboard (demo data).
7. **Text thread** — a `chat` scene where a customer or player asks something; we answer it.
8. **Straight answer** — one common question answered plainly ("Do I own my site?" Yes, once it's paid in full).
9. **One feature, deep** — e.g. care checks every 15 minutes, backups, a monthly report.
10. **Compare** — one-page vs starter vs business (or Discord setup vs management).

**Style:** Minecraft servers → `blocks`. Gaming audiences (studios, creators, esports, gaming businesses) → `hype` on even slots, `blocks` on odd. Everyone else → `calm` in the morning, `hype` in the evening.

**Offers:** only feature `starter-500` if `catalog.json` shows it active and not past `ends_on`; set `"offer": "starter-500"` in the brief. Never invent scarcity.

## 3. Writing the brief

Copy the shape of the files in `briefs/` (`example-calm-hvac.json`, `example-hype-onepage.json`, `example-blocks-wiki.json`). Save as `briefs/YYYY-MM-DD-am.json` or `-pm.json`. Fields:

- `theme`, `bpm` (calm 88–100, hype 140–156, blocks 140–156), `seed` (use the slot number), `audience`, `format`.
- `hook`: the one-line promise of the first second.
- `scenes`: 4–7 scenes, 16–35 seconds total. First scene is `title`, `words`, `chat` or `world` and lasts under 4.5 s. Last scene is `end`.
- `caption`: first line = the hook in searchable words. Then 1–2 plain sentences. End with the disclosure when demo data, concept sites or Copperleaf appear (e.g. "(Sites and dashboards shown are concept work and demo data.)"). Under 300 characters is best.
- `hashtags`: 4–6, broad + niche + local where it fits (#spartanburgsc #greenvillesc #upstatesc for local trades).
- `firstComment`: a question that invites a reply (comments are a strong signal), or the link-in-bio pointer.

Scene types (text supports `*accent*`):

| type | fields | use |
|---|---|---|
| `title` | `eyebrow`, `lines[]`, `lines2[]`, `lines2At`, `sub` | the hook |
| `words` | `words[]` | rapid one-word-per-beat hook |
| `shot` | `shot`, `spots[]`, `lines[]`, `chips[]`, `sub`, `border` | a screen with the camera moving between spots |
| `phone` | `shot` (tall phone shot), `lines[]`, `scroll:[from,to]`, `tap:{at,x,y}`, `chips[]` | a phone scrolling a site |
| `browsers` | `shots[≤3]`, `labels[]`, `urls[]`, `lines[]`, `sub` | several websites at once |
| `scroll` | `shot` (tall doc), `lines[]`, `chips[]` | the care report scrolling |
| `prices` | `items:[[name,price]]`, `lines[]`, `sub` | prices from the catalog |
| `montage` | `shots[]`, `word` | quick cuts |
| `chat` | `messages:[[who,msg]]` (who = "You" for our side), `lines[]`, `sub` | text thread / player chat |
| `list` | `items[]` (string or `{t,no}`), `lines[]`, `sub` | checklists, myth vs fact |
| `world` | `lines[]`, `chat[]`, `tail` (blocks only) | voxel island opener |
| `end` | `tagline`, `sub`, `cta`, `disclaimer` | brand end card (always last) |

`beats` sets each scene's length. Screens are listed in `shots/shots.json` with what they show and named focus `spots`. Pick screens that prove the point; label tags are added automatically.

**Ridgeline phone tap:** `{"at":1.9,"x":0.42,"y":1235}` lands on its "Call now" button.

## 4. Hard rules (never break these)

- **Prices only from `crm/app/catalog.json`** (plus $150/hour consultations). `check.mjs` rejects anything else. Wikis and custom work say "quote", never a number.
- **No made-up results:** no percentages, no "X more leads", no client counts, no testimonials, no "#1", no guarantees. Dashboards are demo data; say so.
- **Never name a real business** as a client except Intake Breathing and Rap Snacks (Grady's TikTok Shop work), and only in Creator Ops content.
- **Concept sites are fictional:** Hearth & Grain, Ridgeline, Pine Street Dental, Loom & Lantern, Fernhollow, Lampwright, Copperleaf SMP.
- **Minecraft:** no Mojang characters, logos, fonts or textures (our voxel art is original). Always "Not affiliated with Mojang or Microsoft." Don't use the TikTok, Discord, Google or Meta logos either; their names are fine.
- **Voice (CLAUDE.md §20):** plain, warm, specific. Never "synergy", "revolutionary", "world-class", fake urgency or scarcity.
- **Public contact** is hello@stusyn.com and syndicatemarketing.co only. Never Grady's phone, email or address.
- **No secrets** in briefs, captions or commits.

## 5. Each run, step by step

1. Get the repo (`cgrdavis123-svg/syndicate`, branch `main`), then `cd tools/video`.
2. Make sure the tools work: `node -e "require('playwright')"`, `ffmpeg -version`, `python3 -c "import numpy, scipy"`. Install what's missing (`npm i playwright` in a temp folder, `pip install --break-system-packages numpy scipy`). Use the preinstalled Chromium; only run `npx playwright install chromium` if launching fails.
3. Work out today's slot, audience, format and style (section 2). If the Project doc `claude/tiktok/log.csv` is readable, check the last 14 rows and change anything that would repeat a hook, format+audience or headline.
4. Write the brief. Run `node check.mjs briefs/<file>.json` and fix every problem it lists.
5. `node render.mjs briefs/<file>.json --sheet` and look at the contact sheet. Fix clipped or overlapping text, empty frames, or anything off-brand, then render for real: `node render.mjs briefs/<file>.json`.
6. Send Grady the mp4 (`out/<file>.mp4`) as a proactive file, then one message with: when to post (7 AM or 7 PM ET), the caption, the hashtags on one line, the first comment to pin, and the audience/format/style used.
7. Monday morning only: add a 30–45 second script for Grady to film himself (hook line, three beats, closing line), on the week's most useful topic.
8. If you can, append a row to the Project doc `claude/tiktok/log.csv`: `date,slot,audience,format,theme,hook,caption_first_line`. Don't commit briefs or videos to git.
