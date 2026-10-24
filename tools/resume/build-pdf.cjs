// Renders the two resumes to src/static: resume.html → grady-davis-resume.pdf (linked from the site, for clients)
// and resume-jobs.html → grady-davis-cv.pdf (for job applications, linked from the noindex grady-davis.html).
// Usage: npm run resume:pdf   (needs Playwright with Chromium)
const path = require('path');
let pw; try { pw = require('playwright'); } catch { pw = require('/opt/node-tools/node_modules/playwright'); }
(async () => {
  const b = await pw.chromium.launch();
  for (const [src, pdf] of [['resume.html', 'grady-davis-resume.pdf'], ['resume-jobs.html', 'grady-davis-cv.pdf']]) {
    const p = await b.newPage();
    await p.goto('file://' + path.join(__dirname, src)); await p.evaluate(() => document.fonts.ready);
    const out = path.join(__dirname, '../../src/static/' + pdf);
    await p.pdf({ path: out, format: 'Letter', printBackground: true, preferCSSPageSize: true });
    const over = await p.evaluate(() => { const pg = document.querySelector('.page'); return pg.scrollHeight - pg.clientHeight; });
    console.log('Wrote', out, over > 0 ? `(WARNING: content overflows the page by ${over}px)` : '(fits on one page)');
    await p.close();
  }
  await b.close();
})();
