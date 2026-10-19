/*! Syndicate site stats · cookie-free analytics by Syndicate Holdings Group · stusyn.com
   <script defer src="https://crm.stusyn.com/a.js" data-site="SITE_ID"></script>
   Options: data-api="/your/proxy" · data-hash="true" (count #/routes) · data-local="true" (count localhost)
   Custom events: syn('Signup', {plan: 'Pro'}) or <button data-syn-event="Book now" data-syn-prop-service="Cleaning">
   Opt out on a browser: visit any page with ?syn_ignore=1 (undo with ?syn_ignore=0). */
(function (w, d, nav, loc) {
  'use strict';
  var me = d.currentScript || d.querySelector('script[data-site]');
  if (!me || w.__synLoaded) return;
  w.__synLoaded = true;
  var site = me.getAttribute('data-site');
  var api = me.getAttribute('data-api') || me.src.replace(/\/[^\/?#]*([?#].*)?$/, '/t.php');
  var hashMode = me.getAttribute('data-hash') === 'true';
  var queued = (w.syn && w.syn.q) || [];
  var store = null; try { store = w.localStorage; } catch (e) {}

  try {
    var opt = loc.search.match(/[?&]syn_ignore=(1|0)/);
    if (opt && store) { if (opt[1] === '1') store.setItem('syn_ignore', '1'); else store.removeItem('syn_ignore'); }
  } catch (e) {}
  function ignored() {
    if (w.synOff) return true; // the site's own consent switch
    try { if (store && store.getItem('syn_ignore') === '1') return true; } catch (e) {}
    if (nav.webdriver || w._phantom || w.__nightmare || w.Cypress) return true;
    return /^(localhost|127\.|0\.0\.0\.0|\[::1\])/.test(loc.hostname) && me.getAttribute('data-local') !== 'true';
  }

  function send(data) {
    if (ignored()) return;
    data.s = site;
    var body = JSON.stringify(data);
    try { if (nav.sendBeacon && nav.sendBeacon(api, body)) return; } catch (e) {}
    try { w.fetch(api, { method: 'POST', body: body, keepalive: true, mode: 'no-cors', credentials: 'omit' }); } catch (e) {}
  }
  function rid() { var a = ''; for (var i = 0; i < 4; i++) a += Math.floor(Math.random() * 0x10000).toString(16); return a + Date.now().toString(36); }
  function path() { return loc.pathname + (hashMode && loc.hash ? loc.hash.split('?')[0] : ''); }
  function tz() { try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return ''; } }

  /* Engaged time: counts only while the tab is visible and someone has moved, scrolled or typed in the last 30 seconds. */
  var pid = null, cur = null, engaged = 0, lastActive = Date.now(), maxScroll = 0, first = true, vitals = {};
  function active() { lastActive = Date.now(); }
  ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'wheel'].forEach(function (t) { w.addEventListener(t, active, { passive: true, capture: true }); });
  setInterval(function () { if (pid && d.visibilityState === 'visible' && Date.now() - lastActive < 30000) engaged++; }, 1000);
  function scrolled() {
    var h = Math.max(d.documentElement.scrollHeight, d.body ? d.body.scrollHeight : 0), y = w.scrollY || d.documentElement.scrollTop || 0;
    var pct = h <= w.innerHeight ? 100 : Math.round(Math.min(100, (y + w.innerHeight) / h * 100));
    if (pct > maxScroll) maxScroll = pct;
  }
  w.addEventListener('scroll', scrolled, { passive: true });

  function flush() {
    if (!pid) return;
    scrolled();
    var v = {}; for (var k in vitals) if (vitals[k] != null) v[k] = Math.round(k === 'cls' ? vitals[k] * 1000 : vitals[k]);
    send({ t: 'en', id: pid, e: engaged, sc: maxScroll, v: v });
  }

  function pageview() {
    var p = path();
    if (p === cur) return;
    if (pid) flush();
    cur = p; pid = rid(); engaged = 0; maxScroll = 0; lastActive = Date.now();
    if (!first) vitals = {}; // Core Web Vitals belong to the first, full page load
    send({
      t: 'pv', id: pid, u: loc.protocol + '//' + loc.host + p + loc.search,
      r: first ? d.referrer : '', ti: d.title.slice(0, 150), w: w.screen ? w.screen.width : 0,
      tz: tz(), l: nav.language || '', tp: nav.maxTouchPoints || 0
    });
    first = false;
    setTimeout(scrolled, 600);
  }

  function event(name, props) {
    if (!name) return;
    var p = {}, n = 0;
    if (props && typeof props === 'object') for (var k in props) if (n++ < 20 && props[k] != null) p[String(k).slice(0, 40)] = String(props[k]).slice(0, 200);
    send({ t: 'ev', id: pid, n: String(name).slice(0, 80), p: p, u: loc.protocol + '//' + loc.host + path() });
  }
  var api_ = function (name, props) { if (name === 'pageview') { cur = null; pageview(); } else event(name, props); };
  api_.q = []; w.syn = api_;

  /* Core Web Vitals, measured the way Google's tools do (p75 is worked out on the server). */
  try {
    var po = function (type, cb, opts) { try { var o = new PerformanceObserver(function (l) { l.getEntries().forEach(cb); }); o.observe(Object.assign({ type: type, buffered: true }, opts || {})); } catch (e) {} };
    po('largest-contentful-paint', function (e) { vitals.lcp = e.startTime; });
    po('paint', function (e) { if (e.name === 'first-contentful-paint') vitals.fcp = e.startTime; });
    var win = 0, winStart = 0, winLast = 0; vitals.cls = 0;
    po('layout-shift', function (e) {
      if (e.hadRecentInput) return;
      if (win && e.startTime - winLast < 1000 && e.startTime - winStart < 5000) win += e.value; else { win = e.value; winStart = e.startTime; }
      winLast = e.startTime; if (win > vitals.cls) vitals.cls = win;
    });
    po('event', function (e) { if (e.interactionId && (!vitals.inp || e.duration > vitals.inp)) vitals.inp = e.duration; }, { durationThreshold: 40 });
    var navEntry = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
    if (navEntry) vitals.ttfb = navEntry.responseStart;
  } catch (e) {}

  /* Automatic events: calls, emails, outbound links, downloads, forms, and anything marked data-syn-event. */
  var files = /\.(pdf|zip|docx?|xlsx?|pptx?|csv|txt|rtf|mp3|mp4|mov|dmg|exe|apk|key|pages|numbers)$/i;
  d.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target : null; if (!t) return;
    var tagged = t.closest('[data-syn-event]');
    if (tagged) {
      var props = {}; for (var i = 0; i < tagged.attributes.length; i++) { var a = tagged.attributes[i]; if (a.name.indexOf('data-syn-prop-') === 0) props[a.name.slice(14)] = a.value; }
      event(tagged.getAttribute('data-syn-event'), props);
    }
    var link = t.closest('a[href]'); if (!link) return;
    var href = link.getAttribute('href') || '';
    if (/^tel:/i.test(href)) return event('Phone call', { number: href.slice(4).replace(/[^\d+]/g, '') });
    if (/^mailto:/i.test(href)) return event('Email click', { to: href.slice(7).split('?')[0] });
    if (/^sms:/i.test(href)) return event('Text message', {});
    var u; try { u = new URL(link.href, loc.href); } catch (err) { return; }
    if (files.test(u.pathname)) return event('File download', { file: u.pathname.split('/').pop() });
    if (/^https?:$/.test(u.protocol) && u.host !== loc.host) event('Outbound link', { url: u.host + u.pathname });
  }, true);
  d.addEventListener('submit', function (e) {
    var f = e.target; if (!f || f.tagName !== 'FORM' || f.hasAttribute('data-syn-ignore')) return;
    var action = ''; try { action = new URL(f.action || loc.href, loc.href).pathname; } catch (err) {}
    event('Form submit', { form: f.getAttribute('data-syn-name') || f.id || f.getAttribute('name') || action || 'form' }); // never the fields people typed
  }, true);

  /* Single-page sites (React, Next.js, Webflow, Wix and similar): count each route change. */
  var wrap = function (fn) { return function () { var r = fn.apply(this, arguments); setTimeout(pageview, 0); return r; }; };
  try { history.pushState = wrap(history.pushState); history.replaceState = wrap(history.replaceState); } catch (e) {}
  w.addEventListener('popstate', function () { setTimeout(pageview, 0); });
  if (hashMode) w.addEventListener('hashchange', pageview);
  d.addEventListener('visibilitychange', function () { if (d.visibilityState === 'hidden') flush(); });
  w.addEventListener('pagehide', flush);

  if (d.visibilityState === 'prerender') d.addEventListener('visibilitychange', function once() { if (d.visibilityState === 'visible') { d.removeEventListener('visibilitychange', once); pageview(); } });
  else pageview();
  queued.forEach(function (c) { api_.apply(null, c); });
})(window, document, navigator, location);
