// Screenshots of the portfolio demo sites, used on the Work pages.
// Run after changing a demo site: node tools/work-shots.cjs [slug]  (needs Playwright with Chromium)
// Client sites ("client": true) are shot from their live URL; concepts from their demo folder.
const path = require('path');
let pw; try { pw = require('playwright'); } catch (e) { pw = require('/opt/node-tools/node_modules/playwright'); }
const root = path.join(__dirname, '..', 'marketing');
const only = process.argv[2];
const work = require(path.join(root, 'work.json')).projects.filter(p => !only || p.slug === only);
(async () => {
  const b = await pw.chromium.launch();
  for (const p of work) {
    const url = p.client ? p.url : 'file://' + path.join(root, 'work', p.slug, 'index.html');
    for (const [kind, vp, scale] of [['desktop', { width: 1440, height: 900 }, 1], ['mobile', { width: 390, height: 760 }, 2]]) {
      const page = await b.newPage({ viewport: vp, deviceScaleFactor: scale, isMobile: kind === 'mobile', hasTouch: kind === 'mobile' });
      await page.goto(url, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);
      await page.waitForTimeout(400);
      await page.screenshot({ path: path.join(root, 'assets', 'work', `${p.slug}-${kind}.jpg`), type: 'jpeg', quality: 80 });
      await page.close();
    }
    console.log('shot', p.slug);
  }
  await b.close();
})();
