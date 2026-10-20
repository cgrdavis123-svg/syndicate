# tools/video — TikTok video engine

Turns a short JSON brief into a 1080×1920 video with an original soundtrack, in one of three styles:

- **calm**: syndicatemarketing.co paper and ink, serif headlines, soft motion (local service businesses)
- **hype**: dark and lime, slams, glitch, fast cuts (general and gaming)
- **blocks**: original voxel art, pixel type, hotbar, block wipes (Minecraft servers and SMPs)

```
node tools/video/check.mjs  tools/video/briefs/example-calm-hvac.json        # house-rules check
node tools/video/render.mjs tools/video/briefs/example-calm-hvac.json --sheet # contact sheet only
node tools/video/render.mjs tools/video/briefs/example-calm-hvac.json         # out/<name>.mp4
```

Needs Node 18+, Playwright with Chromium, ffmpeg, and python3 with numpy and scipy. No npm dependencies are added to the site build.

| File | What it is |
|---|---|
| `PLAYBOOK.md` | What the scheduled task follows: research, rotation, brief format, hard rules, run steps |
| `engine.html` | The renderer. Builds the scenes from the brief; `render.mjs` steps it frame by frame |
| `render.mjs` | Checks the brief, serves the engine, renders 60 fps blended to 30, adds the soundtrack |
| `music.py` | Original music from the engine's cues (cuts, slams, taps); the seed changes key and melody |
| `check.mjs` | Rejects prices not in `crm/app/catalog.json`, banned claims, missing disclosures, bad timing |
| `shots/` | Screenshot library + `shots.json` (what each shows, focus spots, the label it gets) |
| `copperleaf/` | Source pages for the fictional Copperleaf SMP (website, wiki, Discord layout) |
| `vox.js`, `scene.js` | Original voxel textures and the Copperleaf island |
| `fonts/` | OFL fonts used by the engine |
| `briefs/` | Example briefs; dated briefs written by the scheduled task are not committed |

**Screenshots:** CRM shots come from a local CRM filled with the fictional portfolio clients (demo data, never real client data). Concept-site shots come from `marketing/work/`. To refresh them, rebuild the test harness in CLAUDE.md §21, seed the fictional clients and screenshot at 1280×860 @2x, then update `shots.json`.
