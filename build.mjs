// Builds the site into dist/, ready to upload to public_html on cPanel:
//  - wraps each page in src/pages with src/layout.html (static .html pages)
//  - copies the PHP careers hub, admin and API from src/php
//  - turns the same layout into PHP header/footer templates for the careers pages
// Usage: node build.mjs
import { readFileSync, writeFileSync, readdirSync, mkdirSync, rmSync, cpSync } from 'node:fs';
import { join } from 'node:path';

const SRC = 'src', OUT = 'dist';
const site = {
  email: 'hello@stusyn.com',
  careers: 'careers@stusyn.com',
  legalUpdated: 'October 17, 2026',
  // Cookie-free site stats from the CRM (Site stats → stusyn.com → Settings & install code). Leave statsSite empty to turn them off.
  statsSrc: 'https://crm.stusyn.com/a.js',
  statsSite: 't4xJf60nIgv4',
  // One-page website: sold through the Syndicate Marketing store (price set in the CRM under Store → Products).
  onePagePrice: '$350',
  onePageAmount: '350',
  smSite: 'https://syndicatemarketing.co',
  // Online booking in the CRM
  bookConsult: 'https://crm.stusyn.com/book.php?type=tiktok-shop-consultation',
  smCheckout: 'https://syndicatemarketing.co/checkout.html?add=web-one-page&amp;utm_source=stusyn.com&amp;utm_campaign=one-page',
  // Cloudflare Turnstile bot check on the contact form (dash.cloudflare.com → Turnstile → the widget's site key).
  // The site key is public. The secret key goes only in app/config.php on the server. Empty turns the check off.
  turnstileSite: process.env.TURNSTILE_SITE ?? '',
  arrow: '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};
site.captcha = site.turnstileSite ? `<div class="cf-turnstile" data-sitekey="${site.turnstileSite}" data-action="contact" data-theme="dark" data-size="flexible" data-appearance="interaction-only"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>` : '';
const PORTFOLIO = ['portfolio', 'creator-ops-media', 'syndicate-marketing'];

const fill = (str, vars) => str.replace(/\{\{(\w+)\}\}/g, (m, k) => (k in vars ? vars[k] : m));

rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });
cpSync(join(SRC, 'assets'), join(OUT, 'assets'), { recursive: true });
cpSync(join(SRC, 'static'), OUT, { recursive: true });
cpSync(join(SRC, 'php'), OUT, { recursive: true });

const layout = readFileSync(join(SRC, 'layout.html'), 'utf8');
const pages = [
  ...readdirSync(join(SRC, 'pages')).filter(f => f.endsWith('.html')).map(f => [join(SRC, 'pages', f), f]),
  ...readdirSync(join(SRC, 'legal')).filter(f => f.endsWith('.html')).map(f => [join(SRC, 'legal', f), f]),
];

// Legal pages: shared hero, table of contents built from each <section id> and its <h2>.
function legalPage(meta, body) {
  const toc = [...body.matchAll(/<section id="([^"]+)">\s*<h2>([\s\S]*?)<\/h2>/g)]
    .map(([, id, h]) => `<a href="#${id}">${h.replace(/^\d+\.\s*/, '')}</a>`).join('\n        ');
  return `
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs reveal"><a href="index.html">Home</a><i>/</i><span>Legal</span><i>/</i><span>${meta.heading}</span></div>
    <h1 class="reveal d1" style="font-size:clamp(40px,5.4vw,76px)">${meta.heading}</h1>
    <p class="lede reveal d2">${meta.lede}</p>
    <div class="legal-meta reveal d3"><span class="tag">LAST UPDATED · {{legalUpdated}}</span><span class="tag">SYNDICATE HOLDINGS GROUP LLC</span></div>
  </div>
</section>
<section class="section" style="padding-top:80px">
  <div class="wrap legal">
    <aside class="legal-toc">
      <span class="eyebrow"><span class="dot"></span>On this page</span>
      <nav aria-label="Sections">
        ${toc}
      </nav>
    </aside>
    <article class="legal-doc">
${body}
    </article>
  </div>
</section>
<div class="wrap" style="padding-bottom:40px">
  <div class="legal-meta" style="justify-content:center;margin:0">
    <a class="tag" href="privacy.html">PRIVACY</a><a class="tag" href="cookies.html">COOKIES</a><a class="tag" href="terms.html">TERMS</a><a class="tag" href="applicant-privacy.html">APPLICANT PRIVACY</a><a class="tag" href="accessibility.html">ACCESSIBILITY</a>
  </div>
</div>`;
}

for (const [srcPath, file] of pages) {
  const raw = readFileSync(srcPath, 'utf8');
  const m = raw.match(/^<!--\s*(\{[\s\S]*?\})\s*-->\s*/);
  if (!m) throw new Error(`${file}: missing <!-- {json} --> front matter`);
  const meta = JSON.parse(m[1]);
  const name = file.replace(/\.html$/, '');
  const nav = meta.nav || name;

  let html = fill(layout, {
    ...site,
    title: fill(meta.title, site),
    description: fill(meta.description, site),
    path: name === 'index' ? '' : file,
    bodyClass: meta.bodyClass || '',
    ddCurrent: PORTFOLIO.includes(nav) ? ' current' : '',
    content: fill(meta.heading ? legalPage(meta, raw.slice(m[0].length)) : raw.slice(m[0].length), site),
  });
  if (meta.robots) html = html.replace('<meta name="theme-color"', `<meta name="robots" content="${meta.robots}">\n<meta name="theme-color"`);
  // 404 can be served from any path, so resolve its relative links from the root.
  if (name === '404') html = html.replace('<head>', '<head>\n<base href="/">');
  html = html.replaceAll(`data-nav="${nav}"`, `data-nav="${nav}" aria-current="page"`);

  const left = html.match(/\{\{\w+\}\}/);
  if (left) throw new Error(`${file}: unfilled placeholder ${left[0]}`);
  writeFileSync(join(OUT, file), html);
}

// PHP layout for the careers pages: same header and footer, with PHP-filled title and description.
const SPLIT = '<!--CONTENT-->';
let phpLayout = fill(layout, {
  ...site,
  title: "<?= e($page['title']) ?>",
  description: "<?= e($page['description']) ?>",
  path: "<?= e($page['path']) ?>",
  bodyClass: '',
  ddCurrent: '',
  content: SPLIT,
});
phpLayout = phpLayout
  .replace('<head>', '<head>\n<base href="<?= e(BASE) ?>">')
  .replace('<meta name="theme-color"', '<?php if (!empty($page[\'robots\'])): ?><meta name="robots" content="<?= e($page[\'robots\']) ?>">\n<?php endif; ?><meta name="theme-color"')
  .replaceAll('data-nav="careers"', 'data-nav="careers" aria-current="page"');
const [top, bottom] = phpLayout.split(SPLIT);
const header = '<?php /* Generated by build.mjs from src/layout.html. Edit the layout, not this file. */ ?>\n';
writeFileSync(join(OUT, 'app/views/site-top.php'), header + top);
writeFileSync(join(OUT, 'app/views/site-bottom.php'), header + bottom);

console.log(`Built ${pages.length} pages + careers hub → ${OUT}/`);
