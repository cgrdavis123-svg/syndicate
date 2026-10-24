// Builds the Syndicate Marketing website into dist-marketing/, ready to upload to its domain's folder on cPanel.
// The site is plain HTML, CSS and JavaScript. The cart lives in the browser; prices, checkout and orders live in the CRM.
// Usage: node build-marketing.mjs
import { readFileSync, writeFileSync, readdirSync, mkdirSync, rmSync, cpSync } from 'node:fs';
import { join } from 'node:path';
import { iconTags, reportMissing } from './tools/icons.mjs';
import { grid as workGrid, casePage } from './marketing/work-render.mjs';
import { loadPosts, indexPage as blogIndex, postPage, latest as latestPosts, feed as blogFeed, quote as clientQuote } from './marketing/blog-render.mjs';

const SRC = 'marketing', OUT = 'dist-marketing';
const site = {
  // The marketing site's own address, with no trailing slash. Change this to your domain.
  siteUrl: process.env.SM_SITE_URL || 'https://syndicatemarketing.co',
  // The CRM that holds products, Stripe and orders.
  crm: process.env.SM_CRM || 'https://crm.stusyn.com',
  email: 'hello@stusyn.com',
  // Online booking in the CRM
  bookDiscovery: (process.env.SM_CRM || 'https://crm.stusyn.com') + '/book.php?type=discovery-call',
  bookKickoff: (process.env.SM_CRM || 'https://crm.stusyn.com') + '/book.php?type=kickoff',
  // Site Stats for this website (CRM → Site stats → Add a website → install code). Leave empty to turn them off.
  statsSite: process.env.SM_STATS ?? 'UIOdnDz160w8',
  legalUpdated: 'October 23, 2026',
  // Referral program (CRM → Referrals). Keep these in step with the CRM settings ref_rate, ref_months, ref_hold_days and ref_min_cash.
  refRate: 5, refMonths: 12, refHoldDays: 30, refLinkDays: 60, refMinCash: 25,
  // Creator partners (creators.html). Keep in step with the CRM settings ref_creator_rate and ref_creator_discount.
  creatorRate: 10, creatorDiscount: 10,
  // Cloudflare Turnstile bot check on the contact form (dash.cloudflare.com → Turnstile → the widget's site key).
  // The site key is public. The secret key goes only in app/config.php on the server. Empty turns the check off.
  turnstileSite: process.env.TURNSTILE_SITE ?? '',
  arrow: '<svg class="arr" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  check: '<svg viewBox="0 0 18 18" aria-hidden="true"><path d="M9 1.5a7.5 7.5 0 1 1 0 15 7.5 7.5 0 0 1 0-15Z" fill="#c6ff3d"/><path d="m5.6 9.2 2.2 2.2 4.6-4.8" fill="none" stroke="#0d0f0c" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};
site.captcha = site.turnstileSite ? `<div class="cf-turnstile" data-sitekey="${site.turnstileSite}" data-action="contact" data-theme="light" data-size="flexible" data-appearance="interaction-only"></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>` : '';
site.domain = site.siteUrl.replace(/^https?:\/\//, '');

// The same catalog the CRM starts with. Live prices replace these in the browser as soon as the CRM answers.
const seed = JSON.parse(readFileSync('crm/app/catalog.json', 'utf8'));
const products = seed.products.map(p => ({
  sku: p.sku, category: p.category, name: p.name, blurb: p.blurb || '', features: p.features || [], price: p.price, interval: p.interval || '',
  unit: p.unit || '', max_qty: p.max_qty || 1, requires: p.requires || [], badge: p.badge || '', featured: !!p.featured, timeline_days: p.timeline_days || 0, free_with: p.free_with || [],
}));
const catalog = { ok: true, currency: 'usd', ready: true, promo: true, products,
  categories: { website: 'Websites', ads: 'Paid ads', care: 'Care plans', community: 'Community', service: 'Audits & services', addon: 'Add-ons' },
  platforms: { google: 'Google', meta: 'Meta (Facebook & Instagram)', tiktok: 'TikTok' } };
// Portfolio (marketing/work.json): real client sites ("client": true) and concept projects with demo sites in marketing/work/<slug>/.
// "listed": false still builds the case page but keeps it off the grids.
const work = JSON.parse(readFileSync(join(SRC, 'work.json'), 'utf8')).projects;
const listed = work.filter(p => p.listed !== false);
const win = {};
new Function('window', readFileSync(join(SRC, 'assets/js/cards.js'), 'utf8'))(win);
const Cards = win.SMCards;

const fill = (str, vars) => str.replace(/\{\{(\w+)\}\}/g, (m, k) => (k in vars ? vars[k] : m));
// {{products:website}} or {{products:sku:a,b:mini}} → product cards; {{price:sku}} → "$500/mo"
// Special offers (catalog.json → offers; the CRM's Store → Offers is the live copy, and the page refreshes from it).
// {{offer:starter-500:today}} → $500 · fields: today, monthly, value, save, trial (months), ends (date), spots,
// trialvalue (the free months at store price), list:sku, price:sku
const money = n => Cards.money(n);
const offerCalc = slug => {
  const of = (seed.offers || []).find(o => o.slug === slug); if (!of) throw new Error('Unknown offer ' + slug);
  const r = { today: 0, monthly: 0, value: 0, trial: 0, trialvalue: 0, items: {} };
  for (const it of of.items) {
    const p = products.find(x => x.sku === it.sku); if (!p) throw new Error(`Offer ${slug}: unknown product ${it.sku}`);
    const price = it.price ?? p.price, months = p.interval === 'month' ? (it.trial_months || 0) : 0;
    r.items[it.sku] = { price, list: p.price };
    if (p.interval === 'month') { r.monthly += price; r.trial = Math.max(r.trial, months); r.value += p.price * Math.max(1, months); r.trialvalue += p.price * months; if (!months) r.today += price; }
    else { r.today += price; r.value += p.price; }
  }
  r.save = r.value - r.today;
  r.ends = of.ends_on ? new Date(of.ends_on + 'T12:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
  r.spots = of.max_uses || '';
  return r;
};
const offerTag = (slug, field) => {
  const r = offerCalc(slug); const [f, sku] = field.split('.');
  if (f === 'list' || f === 'price') { const i = r.items[sku]; if (!i) throw new Error(`Offer ${slug}: ${sku} isn't in it`); return money(f === 'list' ? i.list : i.price); }
  if (['today', 'monthly', 'value', 'save', 'trialvalue'].includes(f)) return money(r[f]);
  if (f in r) return String(r[f]);
  throw new Error(`Offer ${slug}: unknown field ${field}`);
};
const productTags = html => html
  .replace(/\{\{offer:([a-z0-9-]+):([a-z]+(?:\.[a-z0-9-]+)?)\}\}/g, (m, slug, field) => offerTag(slug, field))
  .replace(/\{\{features:([a-z0-9-]+)\}\}/g, (m, sku) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku); return p.features.map(f => `<li>${site.check}<span>${f.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</span></li>`).join(''); })
  .replace(/\{\{products:([a-z0-9:,-]+?)(?::(pkg|mini))?\}\}/g, (m, spec, kind) => Cards.render(products, spec, kind || 'pkg'))
  .replace(/\{\{price:([a-z0-9-]+)\}\}/g, (m, sku) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku); return Cards.short(p); })
  // {{earn:sku}} → what a referrer earns on one purchase; {{earn:sku:12}} → on 12 months of a monthly plan
  .replace(/\{\{earn:([a-z0-9-]+)(?::(\d+))?\}\}/g, (m, sku, n) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku); return Cards.money(Math.round(p.price * (n ? +n : 1) * site.refRate) / 100); })
  // Creator partners: {{cprice:sku}} → what their audience pays with the code; {{cearn:sku}} / {{cearn:sku:12}} → what the creator earns.
  // The code takes money off one-time items only, so monthly plans stay full price.
  .replace(/\{\{cprice:([a-z0-9-]+)\}\}/g, (m, sku) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku); return p.interval === 'month' ? Cards.short(p) : Cards.money(Math.round(p.price * (100 - site.creatorDiscount)) / 100); })
  .replace(/\{\{cdisc:([a-z0-9-]+)\}\}/g, (m, sku) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku); return Cards.money(p.price - Math.round(p.price * (100 - site.creatorDiscount)) / 100); })
  .replace(/\{\{cearn:([a-z0-9-]+)(?::(\d+))?\}\}/g, (m, sku, n) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku);
    const paid = p.interval === 'month' ? p.price * (n ? +n : 1) : Math.round(p.price * (100 - site.creatorDiscount)) / 100;
    return Cards.money(Math.round(paid * site.creatorRate) / 100); })
  .replace(/\{\{amount:([a-z0-9-]+)\}\}/g, (m, sku) => { const p = products.find(x => x.sku === sku); if (!p) throw new Error('Unknown product ' + sku); return Cards.money(p.price); });

rmSync(OUT, { recursive: true, force: true });
mkdirSync(join(OUT, 'assets/fonts'), { recursive: true });
cpSync(join(SRC, 'assets'), join(OUT, 'assets'), { recursive: true });
cpSync('src/assets/fonts', join(OUT, 'assets/fonts'), { recursive: true });
cpSync('src/assets/css/fonts.css', join(OUT, 'assets/css/fonts.css'));
cpSync(join(SRC, 'work'), join(OUT, 'work'), { recursive: true });
writeFileSync(join(OUT, 'assets/catalog.json'), JSON.stringify(catalog));

const layout = readFileSync(join(SRC, 'layout.html'), 'utf8');
const stats = site.statsSite ? `<script defer src="${site.crm}/a.js" data-site="${site.statsSite}"></script>` : '';
const pages = [
  ...readdirSync(join(SRC, 'pages')).filter(f => f.endsWith('.html')).map(f => [join(SRC, 'pages', f), f]),
  ...readdirSync(join(SRC, 'legal')).filter(f => f.endsWith('.html')).map(f => [join(SRC, 'legal', f), f]),
];

function legalPage(meta, body) {
  const toc = [...body.matchAll(/<section id="([^"]+)">\s*<h2>([\s\S]*?)<\/h2>/g)].map(([, id, h]) => `<a href="#${id}">${h.replace(/^\d+\.\s*/, '')}</a>`).join('\n      ');
  return `
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs"><a href="./">Home</a><span>/</span><span>${meta.heading}</span></div>
    <h1>${meta.heading}</h1>
    <p class="lede">${meta.lede}</p>
    <p class="eyebrow" style="margin-top:22px"><span class="dot"></span>Last updated {{legalUpdated}}</p>
  </div>
</section>
<section class="section tight" style="padding-top:20px">
  <div class="wrap legal">
    <nav class="legal-toc" aria-label="Sections">
      ${toc}
    </nav>
    <article class="legal-doc">
${body}
    </article>
  </div>
</section>`;
}

const sitemap = [];
// One case-study page per concept project.
work.forEach(p => { const c = casePage(p, listed, products); pages.push([null, `work-${p.slug}.html`, c.front, c.body]); });
// The blog: blog.html, one blog-<slug>.html per post (marketing/blog/), and blog.xml.
const posts = loadPosts(join(SRC, 'blog'));
{ const b = blogIndex(posts); pages.push([null, 'blog.html', b.front, b.body]); }
posts.forEach(p => { const b = postPage(p, posts, site); pages.push([null, p.file, b.front, b.body]); });
// {{blog:latest:3}} → the newest posts as cards; {{quote:slug}} → that client's own words from work.json
const blogTags = html => html
  .replace(/\{\{blog:latest:(\d+)\}\}/g, (m, n) => latestPosts(posts, Number(n)))
  .replace(/\{\{quote:([a-z0-9-]+)(:nolink)?\}\}/g, (m, slug, nolink) => clientQuote(work.find(w => w.slug === slug), !nolink));

for (const [srcPath, file, virtMeta, virtBody] of pages) {
  let meta, body;
  if (srcPath) {
    const raw = readFileSync(srcPath, 'utf8');
    const m = raw.match(/^<!--\s*(\{[\s\S]*?\})\s*-->\s*/);
    if (!m) throw new Error(`${file}: missing <!-- {json} --> front matter`);
    meta = JSON.parse(m[1]); body = raw.slice(m[0].length);
  } else { meta = virtMeta; body = virtBody; }
  const name = file.replace(/\.html$/, '');
  const nav = meta.nav || name;
  body = blogTags(body);
  body = body.replace(/\{\{work:grid\}\}/g, () => workGrid(listed, products)).replace(/\{\{work:grid:(\d+)\}\}/g, (m, n) => workGrid(listed.slice(0, Number(n)), products));
  body = productTags(meta.heading ? legalPage(meta, body) : body);
  let html = fill(layout, {
    ...site,
    title: productTags(fill(meta.title, site)),
    description: productTags(fill(meta.description, site)),
    path: name === 'index' ? '' : file,
    ogType: meta.ogType || 'website',
    bodyClass: meta.bodyClass || '',
    stats,
    scripts: (meta.scripts || []).map(s => `<script src="assets/js/${s}" defer></script>`).join('\n'),
    styles: (meta.styles || []).map(s => `<link rel="stylesheet" href="assets/css/${s}">`).join('\n'),
    content: fill(body, site),
  });
  if (meta.robots) html = html.replace('<meta name="theme-color"', `<meta name="robots" content="${meta.robots}">\n<meta name="theme-color"`);
  else if (name !== '404') sitemap.push([name === 'index' ? '' : file, meta.lastmod]);
  if (name === '404') html = html.replace('<head>', '<head>\n<base href="/">');
  html = html.replaceAll(`data-nav="${nav}"`, `data-nav="${nav}" aria-current="page"`);
  html = iconTags(html);
  const left = html.match(/\{\{[\w:,-]+\}\}/);
  if (left) throw new Error(`${file}: unfilled placeholder ${left[0]}`);
  writeFileSync(join(OUT, file), html);
}

const today = new Date().toISOString().slice(0, 10);
writeFileSync(join(OUT, 'sitemap.xml'), `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${sitemap.map(([p, mod]) => `  <url><loc>${site.siteUrl}/${p}</loc><lastmod>${mod || today}</lastmod></url>`).join('\n')}\n</urlset>\n`);
writeFileSync(join(OUT, 'blog.xml'), blogFeed(posts, site));
writeFileSync(join(OUT, 'robots.txt'), `User-agent: *\nAllow: /\nDisallow: /checkout.html\nDisallow: /thank-you.html\n\nSitemap: ${site.siteUrl}/sitemap.xml\n`);
writeFileSync(join(OUT, '.htaccess'), fill(readFileSync(join(SRC, 'htaccess.txt'), 'utf8'), site));
console.log(`Built ${pages.length} pages → ${OUT}/ (${site.domain}), ${posts.filter(p => !p.draft).length} blog posts${posts.some(p => p.draft) ? ` + drafts: ${posts.filter(p => p.draft).map(p => p.file).join(', ')}` : ''}`);
reportMissing('syndicatemarketing.co');
