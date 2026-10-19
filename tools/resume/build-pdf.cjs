// Renders tools/resume/resume.html to src/static/grady-davis-resume.pdf (served at /grady-davis-resume.pdf).
// Usage: npm run resume:pdf   (needs Playwright with Chromium)
const path = require('path');
let pw; try { pw = require('playwright'); } catch { pw = require('/opt/node-tools/node_modules/playwright'); }
(async () => {
  const b = await pw.chromium.launch(); const p = await b.newPage();
  await p.goto('file://' + path.join(__dirname, 'resume.html')); await p.evaluate(() => document.fonts.ready);
  const out = path.join(__dirname, '../../src/static/grady-davis-resume.pdf');
  await p.pdf({ path: out, format: 'Letter', printBackground: true, preferCSSPageSize: true });
  const over = await p.evaluate(() => { const pg = document.querySelector('.page'); return pg.scrollHeight - pg.clientHeight; });
  console.log('Wrote', out, over > 0 ? `(WARNING: content overflows the page by ${over}px)` : '(fits on one page)');
  await b.close();
})();
