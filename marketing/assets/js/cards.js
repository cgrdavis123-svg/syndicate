/* Product cards for the Syndicate Marketing site. Used by the build (to write prices into the HTML)
   and in the browser (to refresh them from the live catalog in the CRM). */
(function (w) {
  'use strict';
  var CHECK = '<svg viewBox="0 0 18 18" aria-hidden="true"><path d="M9 1.5a7.5 7.5 0 1 1 0 15 7.5 7.5 0 0 1 0-15Z" fill="#c6ff3d"/><path d="m5.6 9.2 2.2 2.2 4.6-4.8" fill="none" stroke="#0d0f0c" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var ARROW = '<svg class="arr" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var PLUS = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
  var REQ = { website: 'website package', ads: 'ads plan' };

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function money(n) {
    n = Number(n) || 0;
    var cents = Math.round(n * 100) % 100 !== 0;
    return '$' + n.toFixed(cents ? 2 : 0).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
  function per(p) { return p.interval === 'month' ? '/month' : (p.unit ? p.unit : 'one-time'); }
  function short(p) { return money(p.price) + (p.interval === 'month' ? '/mo' : '') + (p.unit ? ' <small>' + esc(p.unit) + '</small>' : ''); }
  function needs(p) { return (p.requires || []).length ? 'Add-on for a ' + p.requires.map(function (r) { return REQ[r] || r; }).join(' or ') : ''; }

  // A full package card (websites, ads plans, care plan).
  function pkg(p) {
    var feat = (p.features || []).map(function (f) { return '<li>' + CHECK + '<span>' + esc(f) + '</span></li>'; }).join('');
    return '<article class="pkg' + (p.featured ? ' featured' : '') + '" data-sku="' + esc(p.sku) + '">' +
      (p.badge ? '<span class="pkg-badge">' + esc(p.badge) + '</span>' : '') +
      '<h3 class="pkg-name">' + esc(p.name) + '</h3>' +
      '<div class="pkg-price"><b>' + money(p.price) + '</b><span class="pkg-per">' + esc(per(p)) + '</span></div>' +
      '<p class="pkg-blurb">' + esc(p.blurb) + '</p>' +
      (feat ? '<ul class="pkg-feat">' + feat + '</ul>' : '') +
      '<div class="pkg-cta"><button type="button" class="btn ' + (p.featured ? 'btn-lime' : 'btn-ink') + ' add-btn" data-add="' + esc(p.sku) + '">Add to cart</button>' +
      '<button type="button" class="btn btn-ghost btn-sm" data-buy="' + esc(p.sku) + '">Buy now ' + ARROW + '</button></div>' +
      '</article>';
  }
  // A compact card (add-ons, audits and one-off services).
  function mini(p) {
    var req = needs(p);
    return '<article class="mini" data-sku="' + esc(p.sku) + '">' +
      '<div class="mini-top"><b>' + esc(p.name) + '</b><span>' + short(p) + '</span></div>' +
      '<p>' + esc(p.blurb) + '</p>' + (req ? '<span class="req">' + esc(req) + '</span>' : '') +
      '<button type="button" class="btn btn-ghost btn-xs add-btn" data-add="' + esc(p.sku) + '">' + PLUS + ' Add</button>' +
      '</article>';
  }
  // Picks products for a container: data-products="website" (a category) or "sku:a,b,c".
  function pick(products, spec) {
    if (spec.indexOf('sku:') === 0) {
      var list = spec.slice(4).split(',');
      return list.map(function (s) { return products.filter(function (p) { return p.sku === s; })[0]; }).filter(Boolean);
    }
    var cats = spec.split(',');
    return products.filter(function (p) { return cats.indexOf(p.category) !== -1; });
  }
  function render(products, spec, kind) { return pick(products, spec).map(kind === 'mini' ? mini : pkg).join(''); }

  w.SMCards = { pkg: pkg, mini: mini, render: render, pick: pick, money: money, esc: esc, short: short, check: CHECK, arrow: ARROW, plus: PLUS };
})(typeof window !== 'undefined' ? window : this);
