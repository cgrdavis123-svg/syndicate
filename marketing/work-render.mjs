// Renders the portfolio from marketing/work.json (real client sites and concept work): the grid of projects and one case-study page each,
// including sample ads drawn in HTML the way they appear on Meta, Google and TikTok.
import { quote } from './blog-render.mjs';
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const nl = s => esc(s).replace(/\n/g, '<br>');
const SERVICE = { website: 'Website', ads: 'Paid ads', analytics: 'Site Stats', community: 'Community' };
const PLATFORM = { meta: 'Meta · Facebook & Instagram feed', google: 'Google Search', tiktok: 'TikTok · In-feed video' };
const money = n => '$' + Number(n).toLocaleString('en-US');
const initials = s => s.replace(/&/g, '').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();

// Real client work links to the live site; concepts link to their demo copy.
const site = p => p.client ? p.url : `work/${p.slug}/index.html`;
const tag = p => p.client ? '<span class="concept client">Client site · Live</span>' : '<span class="concept">Concept</span>';

function shot(p, kind) { return `assets/work/${p.slug}-${kind}.jpg`; }
function devices(p, big = false) {
  return `<div class="dev${big ? ' big' : ''}">
    <div class="dev-browser"><div class="dev-bar"><i></i><i></i><i></i><span>${esc(p.domain || p.slug.replace(/-/g, '') + '.example')}</span></div><img src="${shot(p, 'desktop')}" alt="${esc(p.name)} ${p.client ? 'website' : 'concept website'} on desktop" loading="lazy" width="1440" height="900"></div>
    <div class="dev-phone"><img src="${shot(p, 'mobile')}" alt="${esc(p.name)} ${p.client ? 'website' : 'concept website'} on a phone" loading="lazy" width="390" height="844"></div>
  </div>`;
}

export function grid(projects, products) {
  return projects.map(p => {
    const pkg = products.find(x => x.sku === p.package);
    return `<article class="work-card reveal" style="--wbg:${p.colors.bg};--wink:${p.colors.ink};--wacc:${p.colors.accent}">
      <a class="work-media" href="work-${p.slug}.html" aria-label="${esc(p.name)} case study">${devices(p)}</a>
      <div class="work-body">
        <div class="work-tags">${tag(p)}${p.services.map(s => `<span>${SERVICE[s]}</span>`).join('')}</div>
        <h3><a href="work-${p.slug}.html">${esc(p.name)}</a></h3>
        <p class="work-kind">${esc(p.kind)}${pkg ? ` · ${esc(pkg.name)}` : ''}</p>
        <p>${esc(p.summary)}</p>
        <div class="work-links"><a class="btn btn-ink btn-sm" href="work-${p.slug}.html">See the case study {{arrow}}</a><a class="btn btn-ghost btn-sm" href="${esc(site(p))}" target="_blank" rel="noopener">${p.client ? 'Visit the live site' : 'Open demo site'} ↗</a></div>
      </div>
    </article>`;
  }).join('\n');
}

export function ad(a, p) {
  if (a.platform === 'google') return `<figure class="ad ad-google">
    <div class="g-top"><span class="g-ico">${esc(initials(p.name))}</span><span><b>${esc(p.name)}</b><small>${esc(a.domain)} › ${esc(a.path)}</small></span><span class="g-sp">Sponsored</span></div>
    <h4>${esc(a.headline)} | ${esc(a.headline2)}</h4>
    <p>${esc(a.desc)}</p>
    <div class="g-links">${a.sitelinks.map(s => `<span>${esc(s)}</span>`).join('')}</div>
    ${a.call ? `<div class="g-call"><svg viewBox="0 0 20 20" fill="none"><path d="M5.5 2.5h2.5l1.5 4-2 1.5a9 9 0 0 0 4.5 4.5l1.5-2 4 1.5v2.5a2 2 0 0 1-2 2A13.5 13.5 0 0 1 3.5 4.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>Call ${esc(a.call)}</div>` : ''}
    <figcaption>${PLATFORM.google}</figcaption></figure>`;
  if (a.platform === 'tiktok') return `<figure class="ad ad-tiktok" style="--abg:${a.bg};--afg:${a.fg};--aacc:${a.accent}">
    <div class="tt-screen">
      <div class="tt-art"><span class="tt-glow"></span><b>${nl(a.overlay)}</b><span>${esc(a.sub)}</span></div>
      <div class="tt-side"><span class="tt-av">${esc(initials(p.name))}</span><span>♥<small>Like</small></span><span>{{icon:chat}}<small>Chat</small></span><span>↗<small>Share</small></span></div>
      <div class="tt-cap"><b>@${esc(a.handle)}</b> <span class="tt-sp">Sponsored</span><p>${esc(a.caption)}</p><small>♫ ${esc(a.sound)}</small><span class="tt-cta">${esc(a.cta)} ›</span></div>
    </div>
    <figcaption>${PLATFORM.tiktok}</figcaption></figure>`;
  return `<figure class="ad ad-meta">
    <div class="m-top"><span class="m-av" style="background:${a.bg};color:${a.fg}">${esc(initials(p.name))}</span><span><b>${esc(a.page)}</b><small>Sponsored · {{icon:globe}}</small></span><span class="m-dots">···</span></div>
    <p class="m-primary">${esc(a.primary)}</p>
    <div class="m-art" style="--abg:${a.bg};--afg:${a.fg};--aacc:${a.accent || a.fg}"><b>${nl(a.image_title)}</b><span>${esc(a.image_sub)}</span><i></i></div>
    <div class="m-link"><span><small>${esc(a.domain.toUpperCase())}</small><b>${esc(a.headline)}</b></span><span class="m-cta">${esc(a.cta)}</span></div>
    <div class="m-react"><span>{{icon:like}} Like</span><span>{{icon:chat}} Comment</span><span>↗ Share</span></div>
    <figcaption>${PLATFORM.meta}</figcaption></figure>`;
}

export function casePage(p, projects, products) {
  const pkg = products.find(x => x.sku === p.package);
  const i = projects.indexOf(p);
  const next = projects[(i + 1) % projects.length];
  const rows = (p.plan?.rows || []).map(([c, plat, aud, obj, pct]) => `<tr><td><b>${esc(c)}</b></td><td>${esc(plat)}</td><td>${esc(aud)}</td><td>${esc(obj)}</td><td class="r"><b>${money(Math.round(p.plan.budget * pct / 100))}</b><small>${pct}%</small></td></tr>`).join('');
  const front = p.client
    ? { title: `${p.name} — client website — Syndicate Marketing`, description: `Client work: ${p.summary}`, nav: 'work' }
    : { title: `${p.name} — concept ${p.services.includes('ads') ? 'website and ad campaign' : 'website'} — Syndicate Marketing`, description: `A concept project: ${p.summary}`, nav: 'work' };
  const body = `
<section class="page-hero case-hero" style="--wbg:${p.colors.bg};--wink:${p.colors.ink};--wacc:${p.colors.accent}">
  <div class="wrap">
    <div class="crumbs reveal"><a href="./">Home</a><span>/</span><a href="work.html">Work</a><span>/</span><span>${esc(p.name)}</span></div>
    <div class="case-top">
      <div>
        <div class="work-tags reveal d1">${p.client ? tag(p) : '<span class="concept">Concept project</span>'}${p.services.map(s => `<span>${SERVICE[s]}</span>`).join('')}</div>
        <h1 class="reveal d1">${esc(p.name)}</h1>
        <p class="lede reveal d2">${esc(p.summary)}</p>
        <div class="cta-row reveal d3"><a class="btn btn-ink" href="${esc(site(p))}" target="_blank" rel="noopener">${p.client ? `Visit ${esc(p.domain)}` : 'Open the demo site'} ↗</a>${p.ads ? '<a class="btn btn-ghost" href="#ads">See the ads</a>' : ''}</div>
      </div>
      <dl class="case-facts reveal d2">
        <div><dt>Business</dt><dd>${esc(p.kind)}</dd></div>
        ${pkg ? `<div><dt>${p.client ? 'Comparable package' : 'Package'}</dt><dd>${esc(pkg.name)} · <span data-price="${pkg.sku}">{{price:${pkg.sku}}}</span></dd></div>` : ''}
        ${p.plan ? `<div><dt>Ad budget in the plan</dt><dd>${money(p.plan.budget)}/mo</dd></div>` : ''}
        <div><dt>Status</dt><dd>${p.client ? `Real client, live at <a class="link" href="${esc(p.url)}" target="_blank" rel="noopener">${esc(p.domain)}</a>` : 'Fictional business, made for our portfolio'}</dd></div>
      </dl>
    </div>
    <div class="case-shot reveal d2">${devices(p, true)}</div>
  </div>
</section>

<section class="section tight">
  <div class="wrap split case-split">
    <div><span class="eyebrow"><span class="dot"></span>The brief</span><h2>The problem to solve</h2><p class="lede">${esc(p.challenge)}</p></div>
    <div><span class="eyebrow"><span class="dot"></span>Our approach</span><ul class="checks">${p.approach.map(a => `<li>{{check}}<div><span style="color:var(--ink-2)">${esc(a)}</span></div></li>`).join('')}</ul></div>
  </div>
</section>

<section class="section tight" style="padding-top:0">
  <div class="wrap">
    <div class="built">
      <div><span class="eyebrow"><span class="dot"></span>What we built</span><h2>The website</h2></div>
      <ul>${p.built.map(b => `<li>${esc(b)}</li>`).join('')}</ul>
      <div class="built-goals"><span class="req">Site Stats goals</span><div>${p.goals.map(g => `<span class="goal-chip"><i></i>${esc(g)}</span>`).join('')}</div></div>
    </div>
  </div>
</section>

${p.quote ? `<section class="section tight" style="padding-top:0">
  <div class="wrap" style="max-width:900px">
    <span class="eyebrow reveal" style="margin-bottom:16px"><span class="dot"></span>In their words</span>
    ${quote(p, false)}
  </div>
</section>` : ''}

${p.ads ? `<section class="night band section" id="ads">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow"><span class="dot"></span>Sample campaign</span><h2>The ads</h2><p class="lede">Written and designed for this concept, shown as they’d appear in the feed and on Google.</p></div></div>
    <div class="ads-row">${p.ads.map(a => ad(a, p)).join('')}</div>
    <div class="plan">
      <div class="plan-h"><h3>Campaign plan</h3><span>Sample plan for a ${money(p.plan.budget)}/month ad budget, paid to the platforms</span></div>
      <div class="cmp-wrap"><table class="cmp plan-tbl"><thead><tr><th>Campaign</th><th>Platform</th><th>Who sees it</th><th>Optimized for</th><th class="r">Budget</th></tr></thead><tbody>${rows}</tbody></table></div>
      <p class="plan-note">This is a plan, not a result. No ads ran for this fictional business. On a real campaign, these numbers come from your ad accounts and Site Stats every month.</p>
    </div>
  </div>
</section>` : ''}

<section class="wrap" style="padding:80px 24px 40px">
  <div class="cta-band reveal"><div><h2>Want this for your business?</h2><p>${pkg ? `This ${p.client ? 'site' : 'concept'} is our ${esc(pkg.name)} package, <span data-price="${pkg.sku}">{{price:${pkg.sku}}}</span>.` : ''} Add it to your cart and we’ll start with a kickoff call.</p></div>
    <div class="cta-row">${pkg ? `<button type="button" class="btn btn-ink" data-add="${pkg.sku}">Add ${esc(pkg.name.toLowerCase())}</button>` : ''}<a href="work-${next.slug}.html" class="btn btn-ghost">Next: ${esc(next.name)} {{arrow}}</a></div></div>
</section>`;
  return { front, body };
}
