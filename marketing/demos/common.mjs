// Shared helpers for the portfolio demo sites. Each demo (marketing/demos/<slug>.mjs) exports
// { slug, css, js, pages: [{ file, title, nav, body }], shell(page, site) } and build.mjs writes
// marketing/work/<slug>/*.html, style.css and site.js. Run: node marketing/demos/build.mjs
export const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// Common <head>. Fonts come from Google Fonts (allowed only under /work/ by its .htaccess).
export function head({ title, desc, fonts }) {
  return `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>${title}</title>
<meta name="description" content="${esc(desc || '')}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?${fonts}&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>`;
}

// The bar at the top of every demo page that says it's fictional.
export const demoBar = (slug, name, kind = 'site') =>
  `<div class="demo-bar">Concept ${kind} by <a href="../../work-${slug}.html">Syndicate Marketing</a> · ${name} is a fictional business made for our portfolio. Forms, calls and checkout are switched off.</div>`;

export const navLinks = (items, active) => items.map(([href, label]) => `<a href="${href}"${href === active ? ' aria-current="page"' : ''}>${label}</a>`).join('');

// Mobile menu toggle, demo forms and demo links, shared by every demo.
export const baseJs = `
(function () {
  var t = document.querySelector('.menu-btn'), n = document.querySelector('.site-nav');
  if (t && n) t.addEventListener('click', function () { var o = n.classList.toggle('open'); t.setAttribute('aria-expanded', o); });
  document.querySelectorAll('[data-demo-form]').forEach(function (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); var note = f.querySelector('.note') || f.nextElementSibling; if (note) note.textContent = f.getAttribute('data-demo-form') || 'This is a demo, so nothing was sent.'; });
  });
  document.querySelectorAll('[data-demo]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); a.textContent = a.getAttribute('data-demo') || 'Demo only'; }); });
})();
`;

// Shared layout rules every demo uses (mobile menu, FAQ, page heroes); brand styles come from each demo.
export const baseCss = `
* { box-sizing: border-box; } img, svg { max-width: 100%; } a { color: inherit; } button { font: inherit; }
.demo-bar { font: 500 13px/1.35 var(--body); padding: 9px 16px; text-align: center; }
.skip { position: absolute; left: -9999px; } .skip:focus { left: 12px; top: 12px; z-index: 50; background: #fff; color: #000; padding: 10px 14px; border-radius: 8px; }
.menu-btn { display: none; background: none; border: 0; width: 44px; height: 44px; border-radius: 10px; cursor: pointer; color: inherit; }
.menu-btn svg { width: 24px; height: 24px; }
.site-nav a[aria-current="page"] { text-decoration: underline; text-underline-offset: 6px; text-decoration-thickness: 2px; }
details.faq { border-bottom: 1px solid var(--line); padding: 18px 0; } details.faq summary { cursor: pointer; font-weight: 700; font-size: 18px; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
details.faq summary::after { content: "+"; font-size: 24px; line-height: 1; } details.faq[open] summary::after { content: "–"; } details.faq p { margin: 12px 0 0; color: var(--muted); }
.crumbs { font-size: 14px; opacity: .8; margin-bottom: 12px; } .crumbs a { text-decoration: none; } .crumbs span { margin: 0 6px; }
.note { min-height: 1em; }
.illus { font-size: 13px; color: var(--muted); font-style: italic; }
:focus-visible { outline: 3px solid currentColor; outline-offset: 2px; }
@media (max-width: 900px) {
  .menu-btn { display: grid; place-items: center; margin-left: auto; }
  .site-nav { display: none !important; } .site-nav.open { display: flex !important; flex-direction: column; position: absolute; left: 0; right: 0; top: 100%; padding: 16px 18px 22px; gap: 14px; z-index: 20; box-shadow: 0 20px 30px -20px rgba(0,0,0,.4); }
  header { position: relative; }
}
@media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; scroll-behavior: auto !important; } }
`;

export const menuBtn = `<button class="menu-btn" type="button" aria-label="Menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>`;
