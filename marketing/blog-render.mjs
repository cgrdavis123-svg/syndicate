// The blog (marketing/blog/*.html): one file per post with JSON front matter, rendered into blog-<slug>.html pages,
// the blog.html index, the blog.xml feed and the latest-posts strip used on other pages.
// Front matter: title, description, date (YYYY-MM-DD), category, optional seoTitle, updated, excerpt, related (SKUs), draft.
// A draft builds at its address for review, marked noindex, and stays off the index, the feed, the sitemap and the strips.
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const day = d => new Date(d + 'T12:00:00Z').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
const slugify = s => s.toLowerCase().replace(/<[^>]+>/g, '').replace(/&[a-z#0-9]+;/g, '').replace(/[’']/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const CATS = ['Websites', 'Local search', 'Ads', 'Website care', 'Gaming & Discord'];

export function loadPosts(dir) {
  if (!existsSync(dir)) return [];
  const posts = readdirSync(dir).filter(f => f.endsWith('.html')).map(f => {
    const raw = readFileSync(join(dir, f), 'utf8');
    const m = raw.match(/^<!--\s*(\{[\s\S]*?\})\s*-->\s*/);
    if (!m) throw new Error(`blog/${f}: missing <!-- {json} --> front matter`);
    const meta = JSON.parse(m[1]);
    for (const k of ['title', 'description', 'date', 'category']) if (!meta[k]) throw new Error(`blog/${f}: front matter needs "${k}"`);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(meta.date) || (meta.updated && !/^\d{4}-\d{2}-\d{2}$/.test(meta.updated))) throw new Error(`blog/${f}: dates are YYYY-MM-DD`);
    if (!CATS.includes(meta.category)) throw new Error(`blog/${f}: category must be one of ${CATS.join(', ')}`);
    // Give every h2 an id so the contents list can link to it.
    const ids = new Set();
    const body = raw.slice(m[0].length).replace(/<h2>([\s\S]*?)<\/h2>/g, (x, h) => { let id = slugify(h), n = 2; while (ids.has(id)) id = slugify(h) + '-' + n++; ids.add(id); return `<h2 id="${id}">${h}</h2>`; });
    const words = body.replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;
    return { ...meta, slug: f.replace(/\.html$/, ''), file: `blog-${f}`, body, minutes: Math.max(2, Math.round(words / 230)), toc: [...body.matchAll(/<h2 id="([^"]+)">([\s\S]*?)<\/h2>/g)].map(([, id, h]) => ({ id, h })) };
  });
  // Newest first; posts on the same day go by "rank" (lower first), then title.
  return posts.sort((a, b) => b.date.localeCompare(a.date) || (a.rank ?? 99) - (b.rank ?? 99) || a.title.localeCompare(b.title));
}

export const published = posts => posts.filter(p => !p.draft);

export function card(p) {
  return `<article class="post-card reveal"><a href="${p.file}">
      <span class="pc-cat">${esc(p.category)}</span>
      <h3>${p.title}</h3>
      <p>${esc(p.excerpt || p.description)}</p>
      <span class="pc-meta"><time datetime="${p.date}">${day(p.date)}</time> · ${p.minutes} min read</span>
    </a></article>`;
}

export const latest = (posts, n, skip) => published(posts).filter(p => p.slug !== skip).slice(0, n).map(card).join('\n');

export function indexPage(posts) {
  const live = published(posts);
  const cats = CATS.filter(c => live.some(p => p.category === c));
  const front = { title: 'Blog — plain advice on websites, Google and ads — Syndicate Marketing', description: 'Straight answers for small-business owners on websites, Google Business Profile, ads, website care and Discord communities, from a Spartanburg web studio.', styles: ['blog.css'], scripts: ['blog.js'], nav: 'blog' };
  const body = `
<section class="page-hero">
  <div class="wrap">
    <div class="crumbs reveal"><a href="./">Home</a><span>/</span><span>Blog</span></div>
    <h1 class="reveal d1">Plain advice for <span class="serif hl">small businesses.</span></h1>
    <p class="lede reveal d2">What a website should cost, how to show up on Google Maps, which ads fit a local business, and what keeps a site healthy. Written for owners, not marketers, by a small web studio in Spartanburg, SC.</p>
    <p class="blog-feed reveal d3"><a class="link" href="blog.xml">Follow by RSS</a></p>
  </div>
</section>
<section class="section tight" style="padding-top:0">
  <div class="wrap">
    ${cats.length > 1 ? `<div class="blog-filter" role="group" aria-label="Filter posts by topic" hidden><button type="button" class="chip on" data-cat="" aria-pressed="true">All</button>${cats.map(c => `<button type="button" class="chip" data-cat="${esc(c)}" aria-pressed="false">${esc(c)}</button>`).join('')}</div>` : ''}
    <div class="post-grid" id="posts">
${live.map(p => card(p).replace('<article class="post-card reveal">', `<article class="post-card reveal" data-cat="${esc(p.category)}">`)).join('\n')}
    </div>
    ${live.length ? '' : '<p class="lede">The first posts are on their way.</p>'}
  </div>
</section>
<section class="wrap" style="padding:40px 24px">
  <div class="cta-band reveal"><div><h2>Rather we just look at it?</h2><p>Run the free website checkup for a plain-English report on your site, or talk it through on a 20-minute call.</p></div><div class="cta-row"><a class="btn btn-ink" href="checkup.html">Check my site free {{arrow}}</a><a class="btn btn-ghost" href="{{bookDiscovery}}">Book a free call</a></div></div>
</section>`;
  return { front, body };
}

export function postPage(p, posts, site) {
  const url = `${site.siteUrl}/${p.file}`;
  const ld = { '@context': 'https://schema.org', '@type': 'BlogPosting', headline: p.title.replace(/<[^>]+>/g, ''), description: p.description, datePublished: p.date, dateModified: p.updated || p.date,
    mainEntityOfPage: url, url, articleSection: p.category, inLanguage: 'en-US',
    author: { '@type': 'Organization', name: 'Syndicate Marketing', url: site.siteUrl + '/' },
    publisher: { '@type': 'Organization', name: 'Syndicate Marketing', url: site.siteUrl + '/', parentOrganization: { '@type': 'Organization', name: 'Syndicate Holdings Group LLC' } } };
  const more = latest(posts, 3, p.slug);
  const front = { title: (p.seoTitle || p.title.replace(/<[^>]+>/g, '')) + ' — Syndicate Marketing', description: p.description, styles: ['blog.css'], nav: 'blog', ogType: 'article', lastmod: p.updated || p.date, ...(p.draft ? { robots: 'noindex, nofollow' } : {}) };
  const body = `
${p.draft ? '<div class="draft-bar" role="note"><div class="wrap"><b>Draft.</b> Not published yet: only people with this link can see it, and Google is told to skip it.</div></div>' : ''}
<article class="post-page">
<header class="page-hero post-hero">
  <div class="wrap">
    <div class="crumbs reveal"><a href="./">Home</a><span>/</span><a href="blog.html">Blog</a><span>/</span><span>${esc(p.category)}</span></div>
    <h1 class="reveal d1">${p.title}</h1>
    <p class="lede reveal d2">${esc(p.description)}</p>
    <p class="post-meta reveal d3"><span>By Syndicate Marketing</span><time datetime="${p.date}">${day(p.date)}</time>${p.updated && p.updated !== p.date ? `<span>Updated <time datetime="${p.updated}">${day(p.updated)}</time></span>` : ''}<span>${p.minutes} min read</span></p>
  </div>
</header>
<div class="wrap post-wrap">
  <div class="post">
${p.body}
  </div>
  <aside class="post-side" aria-label="On this page">
    ${p.toc.length > 2 ? `<nav class="post-toc"><b>On this page</b>${p.toc.map(t => `<a href="#${t.id}">${t.h.replace(/<[^>]+>/g, '')}</a>`).join('')}</nav>` : ''}
    <div class="post-help"><b>Want a second opinion?</b><span>The free checkup reads your home page and tells you what to fix, in about a minute.</span><a class="btn btn-ink btn-sm" href="checkup.html">Check my site {{arrow}}</a></div>
  </aside>
</div>
</article>
${p.related?.length ? `<section class="section tight">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow reveal"><span class="dot"></span>If you’d rather we did it</span><h2 class="reveal d1">Related <span class="serif">services.</span></h2></div></div>
    <div class="mini-grid c3" data-products="sku:${p.related.join(',')}" data-kind="mini">{{products:sku:${p.related.join(',')}:mini}}</div>
  </div>
</section>` : ''}
${more ? `<section class="section tight">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow reveal"><span class="dot"></span>Keep reading</span><h2 class="reveal d1">More from the <span class="serif">blog.</span></h2></div><a href="blog.html" class="btn btn-ghost reveal">All posts {{arrow}}</a></div>
    <div class="post-grid">${more}</div>
  </div>
</section>` : ''}
<section class="wrap" style="padding:40px 24px">
  <div class="cta-band reveal"><div><h2>Questions about your own site?</h2><p>Book a free 20-minute call. We’ll look at what you have and tell you honestly what’s worth doing.</p></div><div class="cta-row"><a class="btn btn-ink" href="{{bookDiscovery}}">Book a free call {{arrow}}</a><a class="btn btn-ghost" href="pricing.html">See pricing</a></div></div>
</section>
<script type="application/ld+json">${JSON.stringify(ld).replace(/</g, '\\u003c')}</script>`;
  return { front, body };
}

export function feed(posts, site) {
  const live = published(posts);
  const x = s => String(s).replace(/&(?![a-z#0-9]+;)/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const plain = s => s.replace(/<[^>]+>/g, '').replace(/&amp;/g, '&');
  const rfc = d => new Date(d + 'T12:00:00Z').toUTCString();
  return `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>Syndicate Marketing blog</title>
  <link>${site.siteUrl}/blog.html</link>
  <atom:link href="${site.siteUrl}/blog.xml" rel="self" type="application/rss+xml"/>
  <description>Plain advice for small businesses on websites, Google, ads and website care.</description>
  <language>en-us</language>
${live.length ? `  <lastBuildDate>${rfc(live.map(p => p.updated || p.date).sort().pop())}</lastBuildDate>\n` : ''}${live.map(p => `  <item>
    <title>${x(plain(p.title))}</title>
    <link>${site.siteUrl}/${p.file}</link>
    <guid isPermaLink="true">${site.siteUrl}/${p.file}</guid>
    <pubDate>${rfc(p.date)}</pubDate>
    <category>${x(p.category)}</category>
    <description>${x(p.description)}</description>
  </item>`).join('\n')}
</channel>
</rss>
`;
}

// {{quote:slug}}: the client's own words from marketing/work.json ("quote": {"text", "by", "role"}).
export function quote(p, link = true) {
  if (!p?.quote) throw new Error(`No quote for ${p?.slug}`);
  return `<figure class="tquote reveal">
  <blockquote><p>“${esc(p.quote.text)}”</p></blockquote>
  <figcaption><b>${esc(p.quote.by)}</b>${p.quote.role ? `<span>${esc(p.quote.role)}</span>` : ''}${link ? `<a class="link" href="work-${p.slug}.html">See the site we built</a>` : ''}</figcaption>
</figure>`;
}
