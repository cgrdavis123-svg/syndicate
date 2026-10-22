/* Syndicate Marketing — site behavior: navigation, the cart, live prices from the CRM, the contact form. */
(function () {
  'use strict';
  var d = document, w = window, C = w.SMCards;
  var cfgEl = d.querySelector('meta[name="sm-config"]');
  var CFG = { crm: cfgEl ? cfgEl.getAttribute('data-crm') : '', email: cfgEl ? cfgEl.getAttribute('data-email') : '' };
  var API = CFG.crm.replace(/\/$/, '') + '/shop-api.php';
  var ONE_PER = { website: 'website package', ads: 'ads plan', care: 'care plan' };
  function $(s, r) { return (r || d).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function store(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }
  function track(name, props) { try { if (w.syn) w.syn(name, props || {}); } catch (e) {} }

  // Where this visit came from, saved with an order so you can see which marketing works.
  try {
    if (!sessionStorage.getItem('sm_src')) {
      var qs = new URLSearchParams(location.search), ref = d.referrer ? new URL(d.referrer).hostname : '';
      sessionStorage.setItem('sm_src', qs.get('utm_source') ? qs.get('utm_source') + (qs.get('utm_campaign') ? ' / ' + qs.get('utm_campaign') : '') : (ref && ref !== location.hostname ? ref : 'Direct'));
    }
  } catch (e) {}

  // Referral links (?ref=code): remembered in this browser for 60 days and sent with an order, a message or a
  // discovery-call booking. The first link someone used is the one that counts.
  var REF_DAYS = 60;
  function refCode() { var r = store('sm_ref'); if (r && r.code && r.at > Date.now() - REF_DAYS * 864e5) return r.code; if (r) store('sm_ref', null); return ''; }
  (function () {
    try {
      var qs = new URLSearchParams(location.search), c = (qs.get('ref') || '').toLowerCase();
      if (!qs.has('ref')) return;
      if (/^[a-z0-9][a-z0-9-]{2,39}$/.test(c) && !refCode()) store('sm_ref', { code: c, at: Date.now() });
      qs.delete('ref');
      history.replaceState(null, '', location.pathname + (qs.toString() ? '?' + qs : '') + location.hash);
    } catch (e) {}
  })();
  (function () {
    var c = refCode(); if (!c) return;
    $$('a[href*="book.php"]').forEach(function (a) { try { var u = new URL(a.href); if (!u.searchParams.get('ref')) { u.searchParams.set('ref', c); a.href = u.toString(); } } catch (e) {} });
  })();

  /* ---------- Header ---------- */
  var hdr = $('.hdr');
  function onScroll() { if (hdr) hdr.classList.toggle('scrolled', w.scrollY > 8); }
  w.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  var mbtn = $('#menuBtn'), mnav = $('#mnav');
  if (mbtn && mnav) mbtn.addEventListener('click', function () { var on = !mnav.classList.contains('on'); mnav.classList.toggle('on', on); mbtn.setAttribute('aria-expanded', on); });
  $$('[data-year]').forEach(function (el) { el.textContent = new Date().getFullYear(); });

  /* ---------- Reveal on scroll ---------- */
  if ('IntersectionObserver' in w) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -8% 0px' });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else $$('.reveal').forEach(function (el) { el.classList.add('in'); });

  /* ---------- Catalog: live from the CRM, with the prices baked in at build time as a fallback ---------- */
  var catalog = null, waiting = [];
  function onCatalog(fn) { if (catalog) fn(catalog); else waiting.push(fn); }
  function setCatalog(c) { catalog = c; waiting.splice(0).forEach(function (fn) { fn(c); }); }
  function loadCatalog() {
    var cached = null;
    try { cached = JSON.parse(sessionStorage.getItem('sm_catalog') || 'null'); } catch (e) {}
    if (cached && cached.at > Date.now() - 300000) return setCatalog(cached.data);
    var done = false;
    var fallback = function () { if (done) return; done = true; fetch('assets/catalog.json').then(function (r) { return r.json(); }).then(function (c) { c.offline = true; setCatalog(c); }).catch(function () {}); };
    var t = setTimeout(fallback, 5000);
    fetch(API + '?a=catalog', { credentials: 'omit' }).then(function (r) { if (!r.ok) throw 0; return r.json(); }).then(function (c) {
      if (done) return; done = true; clearTimeout(t);
      try { sessionStorage.setItem('sm_catalog', JSON.stringify({ at: Date.now(), data: c })); } catch (e) {}
      setCatalog(c);
    }).catch(function () { clearTimeout(t); fallback(); });
  }
  function product(sku) { return catalog ? catalog.products.filter(function (p) { return p.sku === sku; })[0] : null; }
  // Re-draws product grids if a price or detail changed in the CRM since the site was built.
  onCatalog(function (c) {
    $$('[data-products]').forEach(function (el) {
      var html = C.render(c.products, el.getAttribute('data-products'), el.getAttribute('data-kind'));
      if (html && el.getAttribute('data-sig') !== String(html.length) ) { el.innerHTML = html; el.setAttribute('data-sig', html.length); }
    });
    $$('[data-price]').forEach(function (el) { var p = product(el.getAttribute('data-price')); if (p) el.innerHTML = C.short(p); });
    markInCart();
  });

  /* ---------- Cart (kept in this browser only) ---------- */
  var cart = (store('sm_cart') || { items: [] });
  if (!Array.isArray(cart.items)) cart = { items: [] };
  function save() { checkOffer(); cart.updated = Date.now(); store('sm_cart', cart); paintCount(); markInCart(); if (drawer && drawer.classList.contains('on')) paintDrawer(); d.dispatchEvent(new CustomEvent('sm:cart')); }

  /* ---------- Special offers (checkout.html?offer=slug): prices and free months come from the CRM ---------- */
  var offerData = null, offerWait = {};
  function loadOffer(slug, fn) {
    var key = 'sm_offer_' + slug, c = null;
    try { c = JSON.parse(sessionStorage.getItem(key) || 'null'); } catch (e) {}
    if (c && c.at > Date.now() - 60000) return fn(c.data);
    if (offerWait[slug]) { offerWait[slug].push(fn); return; }
    offerWait[slug] = [fn];
    var done = function (o) { try { if (o && o.slug) sessionStorage.setItem(key, JSON.stringify({ at: Date.now(), data: o })); } catch (e) {} offerWait[slug].splice(0).forEach(function (f) { f(o); }); delete offerWait[slug]; };
    fetch(API + '?a=offer&o=' + encodeURIComponent(slug), { credentials: 'omit' }).then(function (r) { return r.json(); }).then(done).catch(function () { done(null); });
  }
  function offerOn() { return offerData && offerData.live && cart.offer === offerData.slug ? offerData : null; }
  function offerItem(sku) { var o = offerOn(); return o ? o.items.filter(function (i) { return i.sku === sku; })[0] : null; }
  // The offer only holds while every product in it is still in the cart.
  function checkOffer() {
    if (!cart.offer || !offerData || offerData.slug !== cart.offer) return;
    var gone = !offerData.live || offerData.items.some(function (i) { return !cart.items.some(function (c) { return c.sku === i.sku; }); });
    if (gone) { delete cart.offer; setTimeout(function () { toast(offerData.live ? 'Offer removed, so regular prices apply. Add the offer again from its page.' : (offerData.error || 'This offer has ended.') + ' Regular prices apply.'); }, 30); }
  }
  function setOffer(slug, fn) {
    loadOffer(slug, function (o) {
      if (!o || !o.live) { if (cart.offer === slug) { delete cart.offer; save(); } if (fn) fn(o); return; }
      offerData = o;
      onCatalog(function () {
        var skus = o.items.map(function (i) { return i.sku; });
        var cats = o.items.map(function (i) { return i.category; });
        // Make room: the offer's packages replace any other package of the same kind, and free months can't share an order with other monthly plans.
        cart.items = cart.items.filter(function (i) { var p = product(i.sku); return p && skus.indexOf(i.sku) === -1 && cats.indexOf(p.category) === -1 && !(o.trial_months && p.interval === 'month'); });
        skus.forEach(function (sku) { if (product(sku)) cart.items.push({ sku: sku, qty: 1 }); });
        cart.offer = o.slug; save();
        track('Offer added', { offer: o.slug });
        if (fn) fn(o);
      });
    });
  }
  if (cart.offer) loadOffer(cart.offer, function (o) { offerData = o; if (!o || !o.live) { var was = cart.offer; delete cart.offer; store('sm_cart', cart); if (was) toast((o && o.error) || 'This offer isn’t available anymore. Regular prices apply.'); } d.dispatchEvent(new CustomEvent('sm:cart')); if (drawer && drawer.classList.contains('on')) paintDrawer(); });
  function inCart(sku) { return cart.items.filter(function (i) { return i.sku === sku; })[0]; }
  function add(sku, qty) {
    var p = product(sku); if (!p) return false;
    var msg = '';
    if (p.category === 'community' && p.interval === 'month') {
      var otherPlan = cart.items.filter(function (i) { var q = product(i.sku); return q && q.category === 'community' && q.interval === 'month' && i.sku !== sku; })[0];
      if (otherPlan) { cart.items = cart.items.filter(function (i) { return i !== otherPlan; }); msg = 'Switched to ' + p.name + '. One community management plan per order.'; }
    }
    if (ONE_PER[p.category]) {
      var other = cart.items.filter(function (i) { var q = product(i.sku); return q && q.category === p.category && i.sku !== sku; })[0];
      if (other) { cart.items = cart.items.filter(function (i) { return i !== other; }); msg = 'Switched to ' + p.name + '. One ' + ONE_PER[p.category] + ' per order.'; }
    }
    var cur = inCart(sku);
    if (cur) cur.qty = Math.min(p.max_qty || 1, cur.qty + (qty || 1));
    else cart.items.push({ sku: sku, qty: Math.min(p.max_qty || 1, qty || 1) });
    save();
    track('Add to cart', { item: p.name });
    return msg || p.name + ' added to your cart.';
  }
  function setQty(sku, q) { var i = inCart(sku); if (!i) return; var p = product(sku); i.qty = Math.max(1, Math.min(p ? p.max_qty || 1 : 1, q)); save(); }
  function remove(sku) { cart.items = cart.items.filter(function (i) { return i.sku !== sku; }); save(); }
  // Each line's product carries the price charged (an offer price if there is one), 'was' (the regular price) and 'trial' (free months).
  function lines() { return cart.items.map(function (i) {
    var p = product(i.sku); if (!p) return null;
    var o = offerItem(i.sku);
    if (o) p = Object.assign({}, p, { price: o.price, was: o.list_price > o.price ? o.list_price : 0, trial: p.interval === 'month' ? o.trial_months : 0 });
    // Included free with a package in the same cart (e.g. domain setup with the Business website).
    if ((p.free_with || []).some(function (s) { return inCart(s); })) p = Object.assign({}, p, { price: 0, was: p.price, incl: true });
    return { p: p, qty: i.qty };
  }).filter(Boolean); }
  function totals(ls) {
    var t = { one: 0, mo: 0, trialMo: 0, was: 0 };
    ls.forEach(function (l) { var a = l.p.price * l.qty; if (l.p.interval === 'month') { t.mo += a; if (l.p.trial) t.trialMo += a; } else t.one += a; if (l.p.was && !l.p.incl) t.was += (l.p.was - l.p.price) * l.qty; });
    t.today = t.one + t.mo - t.trialMo;
    var o = offerOn(); t.trialEnds = o && t.trialMo ? o.trial_ends : ''; t.trialMonths = o ? o.trial_months : 0;
    return t;
  }
  function longDate(iso) { if (!iso) return ''; var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); }
  // Problems that stop checkout, e.g. an add-on without the package it belongs to.
  function problems(ls) {
    var cats = ls.map(function (l) { return l.p.category; }), out = [];
    ls.forEach(function (l) {
      var req = l.p.requires || [];
      if (req.length && !req.some(function (r) { return cats.indexOf(r) !== -1; })) out.push({ sku: l.p.sku, text: l.p.name + ' needs a ' + req.map(function (r) { return r === 'ads' ? 'ads plan' : 'website package'; }).join(' or ') + ' in the same order.' });
    });
    return out;
  }
  function suggestions(ls) {
    var have = ls.map(function (l) { return l.p.sku; }), cats = ls.map(function (l) { return l.p.category; });
    var want = [];
    if (cats.indexOf('website') !== -1) want.push('care-plan', 'add-copy', 'add-logo', 'add-page', 'add-gbp');
    want = want.filter(function (s) { var p = product(s); return p && !(ONE_PER[p.category] && cats.indexOf(p.category) !== -1); }); // never swap out a package they chose
    if (cats.indexOf('ads') !== -1) want.push('add-ads-setup');
    if (cats.indexOf('community') !== -1) want.push('com-discord-setup');
    if (cats.indexOf('website') === -1 && cats.indexOf('ads') !== -1) want.push('svc-site-audit');
    if (!ls.length) want.push('web-one-page', 'svc-strategy');
    return want.filter(function (s) { return have.indexOf(s) === -1 && product(s); }).slice(0, 3).map(product);
  }
  function paintCount() {
    var n = cart.items.reduce(function (a, i) { return a + 1; }, 0);
    $$('.cart-btn .count').forEach(function (el) { el.textContent = n || ''; el.setAttribute('data-n', n); });
  }
  function markInCart() {
    $$('[data-sku]').forEach(function (card) {
      var on = !!inCart(card.getAttribute('data-sku'));
      card.classList.toggle('in-cart', on);
      var b = $('.add-btn', card);
      if (b) b.innerHTML = on ? '✓ In your cart' : (card.classList.contains('mini') ? C.plus + ' Add' : 'Add to cart');
    });
  }

  /* ---------- Drawer ---------- */
  var drawer = $('#cart'), scrim = $('#scrim');
  function openCart() { if (!drawer) return; onCatalog(function () { paintDrawer(); drawer.classList.add('on'); scrim.classList.add('on'); drawer.setAttribute('aria-hidden', 'false'); setTimeout(function () { var x = $('.x-btn', drawer); if (x) x.focus(); }, 50); }); }
  function closeCart() { if (!drawer) return; drawer.classList.remove('on'); scrim.classList.remove('on'); drawer.setAttribute('aria-hidden', 'true'); }
  function lineHtml(l, editable) {
    var p = l.p, multi = (p.max_qty || 1) > 1;
    return '<div class="line"><div><span class="cat-tag">' + C.esc((catalog.categories || {})[p.category] || p.category) + '</span><br><b>' + C.esc(p.name) + '</b></div>' +
      '<div class="lp">' + (p.trial ? '<span class="free">Free</span><small>' + p.trial + ' months, then ' + C.money(p.price * l.qty) + '/mo</small>'
        : p.incl ? '<span class="free">Included</span><small><s class="was">' + C.money(p.was * l.qty) + '</s> with your package</small>'
        : (p.was ? '<s class="was">' + C.money(p.was * l.qty) + '</s> ' : '') + C.money(p.price * l.qty) + (p.interval === 'month' ? '<small>per month</small>' : (multi ? '<small>' + C.money(p.price) + ' ' + C.esc(p.unit || 'each') + '</small>' : ''))) + '</div>' +
      (editable ? '<div class="line-meta">' + (multi ? '<span class="qty"><button type="button" data-qty="' + p.sku + '" data-d="-1" aria-label="Fewer">−</button><span>' + l.qty + '</span><button type="button" data-qty="' + p.sku + '" data-d="1" aria-label="More">+</button></span>' : '') +
        '<button type="button" class="rm" data-rm="' + p.sku + '">Remove</button></div>' : '') + '</div>';
  }
  function totalsHtml(t) {
    return '<div class="totals">' + (t.one ? '<div><span>One-time</span><span>' + C.money(t.one) + '</span></div>' : '') +
      (t.mo ? '<div><span>Monthly</span><span>' + C.money(t.mo) + '/mo</span></div>' : '') +
      (t.was ? '<div class="save"><span>Offer savings</span><span>−' + C.money(t.was) + '</span></div>' : '') +
      '<div class="big"><span>Due today</span><span>' + C.money(t.today) + '</span></div>' +
      (t.mo ? '<div class="mo"><span>Then ' + C.money(t.mo) + '/month' + (t.trialEnds ? ' from ' + longDate(t.trialEnds) : '') + '</span><span>cancel any time</span></div>' : '') + '</div>';
  }
  function paintDrawer() {
    var ls = lines(), body = $('.drawer-b', drawer), foot = $('.drawer-f', drawer);
    if (!ls.length) {
      body.innerHTML = '<div class="empty-cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M4 8.5h16l-1.3 11.5H5.3z"/><path d="M8.5 8.5V7a3.5 3.5 0 0 1 7 0v1.5"/></svg><b>Your cart is empty</b><p style="margin-top:6px">Pick a package to get started.</p></div>' + sugHtml(ls);
      foot.innerHTML = '<a class="btn btn-ink btn-block" href="pricing.html">See packages &amp; pricing</a>';
      return;
    }
    var probs = problems(ls);
    var of = offerOn();
    body.innerHTML = (of ? '<div class="alert ok offer-on"><b>Offer applied:</b>&nbsp;' + C.esc(of.name) + '</div>' : '') + ls.map(function (l) { return lineHtml(l, true); }).join('') +
      probs.map(function (x) { return '<div class="alert bad">' + C.esc(x.text) + '</div>'; }).join('') + sugHtml(ls);
    foot.innerHTML = totalsHtml(totals(ls)) + '<a class="btn btn-lime btn-block" href="checkout.html"' + (probs.length ? ' aria-disabled="true" style="opacity:.5;pointer-events:none"' : '') + '>Checkout ' + C.arrow + '</a>' +
      '<p class="fine"><svg viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="7" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" stroke="currentColor" stroke-width="1.4"/></svg>Secure payment by Stripe</p>';
  }
  function sugHtml(ls) {
    var s = suggestions(ls); if (!s.length) return '';
    return '<div class="suggest"><h4>' + (ls.length ? 'Often added' : 'Popular') + '</h4>' + s.map(function (p) {
      return '<div class="sug"><div><b>' + C.esc(p.name) + '</b><span>' + C.short(p) + '</span></div><button type="button" class="btn btn-ghost btn-xs" data-add="' + p.sku + '">' + C.plus + ' Add</button></div>';
    }).join('') + '</div>';
  }

  /* ---------- Toast ---------- */
  var toastEl = null, toastT = 0;
  function toast(msg, action) {
    if (!toastEl) { toastEl = d.createElement('div'); toastEl.className = 'toast'; toastEl.setAttribute('role', 'status'); d.body.appendChild(toastEl); }
    toastEl.innerHTML = '<span>' + C.esc(msg) + '</span>' + (action ? '<button type="button">' + C.esc(action[0]) + '</button>' : '');
    if (action) $('button', toastEl).onclick = action[1];
    toastEl.classList.add('on'); clearTimeout(toastT); toastT = setTimeout(function () { toastEl.classList.remove('on'); }, 4200);
  }

  /* ---------- Clicks ---------- */
  d.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target : null; if (!t) return;
    var a = t.closest('[data-add]'), b = t.closest('[data-buy]'), q = t.closest('[data-qty]'), r = t.closest('[data-rm]');
    if (t.closest('[data-cart-open]')) { e.preventDefault(); openCart(); return; }
    if (t.closest('[data-cart-close]')) { closeCart(); return; }
    if (a) {
      e.preventDefault();
      onCatalog(function () {
        var sku = a.getAttribute('data-add');
        if (inCart(sku) && !a.closest('.drawer')) { openCart(); return; }
        var msg = add(sku); if (!msg) return;
        var cb = $('.cart-btn'); if (cb) { cb.classList.remove('bump'); void cb.offsetWidth; cb.classList.add('bump'); }
        if (a.closest('.drawer')) return;
        toast(msg, ['View cart', openCart]);
      });
    }
    if (b) { e.preventDefault(); onCatalog(function () { var sku = b.getAttribute('data-buy'); if (!inCart(sku)) add(sku); location.href = 'checkout.html'; }); }
    if (q) { setQty(q.getAttribute('data-qty'), (inCart(q.getAttribute('data-qty')) || { qty: 1 }).qty + Number(q.getAttribute('data-d'))); }
    if (r) remove(r.getAttribute('data-rm'));
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCart(); });
  w.addEventListener('storage', function (e) { if (e.key === 'sm_cart') { cart = store('sm_cart') || { items: [] }; paintCount(); markInCart(); } });

  /* ---------- Tabs (pricing page) ---------- */
  $$('.tabs').forEach(function (tabs) {
    $$('button', tabs).forEach(function (btn) {
      btn.addEventListener('click', function () {
        $$('button', tabs).forEach(function (x) { x.setAttribute('aria-selected', x === btn); });
        var k = btn.getAttribute('data-tab');
        $$('[data-cat]').forEach(function (s) { s.hidden = k !== 'all' && s.getAttribute('data-cat') !== k; });
      });
    });
  });

  /* ---------- Contact form → CRM ---------- */
  var cf = $('#contactForm');
  if (cf) {
    var topic = new URLSearchParams(location.search).get('topic');
    if (topic && cf.elements.topic) $$('input[name="topic"]', cf).forEach(function (r) { r.checked = r.value === topic; });
    cf.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('button[type="submit"]', cf), alertEl = $('#contactAlert');
      $$('.field', cf).forEach(function (f) { f.classList.remove('bad'); });
      var data = {}; new FormData(cf).forEach(function (v, k) { data[k] = v; });
      data.ref = refCode();
      var bad = null;
      if ((data.name || '').trim().length < 2) bad = 'name';
      else if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(data.email || '')) bad = 'email';
      else if ((data.message || '').trim().length < 10) bad = 'message';
      if (bad) { var f = cf.elements[bad].closest('.field'); f.classList.add('bad'); cf.elements[bad].focus(); return; }
      btn.classList.add('is-loading'); btn.textContent = 'Sending…';
      fetch(API + '?a=lead', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.error || '');
          track('Contact form sent', { topic: data.topic || '' });
          cf.innerHTML = '<div class="alert ok" style="font-size:16px"><div><b>Thanks, ' + C.esc(data.name.split(' ')[0]) + '. Your message is in.</b><br>We reply within one business day. A confirmation is on its way to ' + C.esc(data.email) + '.</div></div>';
        })
        .catch(function (err) {
          btn.classList.remove('is-loading'); btn.textContent = 'Send message';
          var ts = $('.cf-turnstile', cf); if (ts && w.turnstile) w.turnstile.reset(ts); // tokens work once
          alertEl.hidden = false; alertEl.textContent = (err && err.message) || ('We couldn’t send that. Please email ' + CFG.email + '.');
        });
    });
  }

  // Links from other sites can start a cart: checkout.html?add=web-one-page (or several, comma-separated),
  // or with a special offer: checkout.html?offer=starter-500
  (function () {
    var qs = new URLSearchParams(location.search), slug = qs.get('offer');
    if (!slug) return;
    setOffer(slug, function (o) {
      if (!o || !o.live) toast((o && o.error) || 'This offer isn’t available anymore. You can still buy at our regular prices.');
      qs.delete('offer');
      try { history.replaceState(null, '', location.pathname + (qs.toString() ? '?' + qs : '') + location.hash); } catch (e) {}
    });
  })();
  (function () {
    var qs = new URLSearchParams(location.search), want = (qs.get('add') || '').split(',').filter(Boolean);
    if (!want.length) return;
    onCatalog(function () {
      want.forEach(function (sku) { if (product(sku) && !inCart(sku)) add(sku); });
      qs.delete('add');
      try { history.replaceState(null, '', location.pathname + (qs.toString() ? '?' + qs : '') + location.hash); } catch (e) {}
    });
  })();

  w.SM = { API: API, CFG: CFG, ref: refCode, cart: function () { return cart; }, lines: lines, offer: offerOn, setOffer: setOffer, loadOffer: loadOffer, longDate: longDate,
    dropOffer: function () { delete cart.offer; save(); }, totals: totals, problems: problems, suggestions: suggestions, product: product, onCatalog: onCatalog,
    add: add, setQty: setQty, remove: remove, clear: function () { cart = { items: [] }; save(); }, lineHtml: lineHtml, totalsHtml: totalsHtml, toast: toast, track: track, store: store, openCart: openCart };
  paintCount();
  loadCatalog();
})();
