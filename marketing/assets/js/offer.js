/* Offer landing page (starter-website-offer.html).
   - Personal greeting from the email link: ?b=Business+Name&n=FirstName (shown as plain text only).
   - Live price, spots left and end date from the CRM (shop-api.php?a=offer), so the page never says more than checkout allows.
   - When the offer has ended or is full, the buttons point to the regular packages instead. */
(function () {
  'use strict';
  var d = document, w = window, SM = w.SM, C = w.SMCards, SLUG = 'starter-500';
  if (!SM || !C) return;
  function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  function set(key, text) { $$('[data-o="' + key + '"]').forEach(function (el) { el.textContent = text; }); }

  /* ---------- Greeting ---------- */
  function clean(v) { return (v || '').replace(/[\u0000-\u001f<>]/g, '').replace(/\s+/g, ' ').trim().slice(0, 60); }
  var qs = new URLSearchParams(location.search), who = {};
  try { who = JSON.parse(sessionStorage.getItem('sm_offer_who') || '{}') || {}; } catch (e) {}
  if (qs.get('b') || qs.get('n')) { who = { b: clean(qs.get('b')), n: clean(qs.get('n')) }; try { sessionStorage.setItem('sm_offer_who', JSON.stringify(who)); } catch (e) {} }
  if (who.b) $$('[data-o-greet]').forEach(function (el) { el.textContent = 'An offer for ' + who.b; });
  if (who.n) $$('[data-o-hi]').forEach(function (el) { el.textContent = 'Hi ' + who.n; });

  /* ---------- Live numbers ---------- */
  function paint(o) {
    if (!o || !o.items) return;
    var web = o.items.filter(function (i) { return i.interval !== 'month'; })[0];
    var trialValue = o.items.reduce(function (a, i) { return a + (i.trial_months ? i.list_price * i.trial_months : 0); }, 0);
    set('today', C.money(o.today)); set('value', C.money(o.value)); set('save', C.money(o.save));
    if (web) set('list', C.money(web.list_price));
    if (o.monthly) set('monthly', C.money(o.monthly));
    if (trialValue) set('trialvalue', C.money(trialValue));
    set('badge', 'Save ' + C.money(o.save));
    if (o.ends_label) set('ends', o.ends_label);
    var limits = [];
    if (o.spots) { set('spots', String(o.spots)); set('left', String(o.left)); limits.push(o.left + ' of ' + o.spots + ' spots left'); }
    if (o.ends_label) limits.push('ends ' + o.ends_label);
    if (!o.live) return closed(o);
    set('strip', 'Limited offer · ' + limits.join(' · '));
    set('final', (o.spots ? (o.left === 1 ? 'Only 1 spot left' : 'Only ' + o.left + ' spots left') : 'Limited offer') + (o.ends_label ? ', until ' + o.ends_label + '.' : '.'));
    set('sticky', o.spots ? o.left + ' of ' + o.spots + ' spots left' : '+ ' + o.trial_months + ' months of Care Plus free');
    var meter = d.querySelector('[data-o-meter]');
    if (meter && o.spots) { meter.hidden = false; var f = meter.querySelector('[data-o-fill]'); if (f) f.style.width = Math.max(4, Math.round(o.left / o.spots * 100)) + '%'; }
  }
  function closed(o) {
    var box = d.querySelector('[data-o-closed]'); if (box) box.hidden = false;
    set('closed', (o && o.error) || 'This offer has ended.');
    set('strip', (o && o.state === 'full') ? 'All spots for this offer have been taken' : 'This offer has ended');
    set('final', 'This offer has closed, but the Business website is still available at its regular price.');
    var meter = d.querySelector('[data-o-meter]'); if (meter) meter.hidden = true;
    $$('[data-o-cta]').forEach(function (a) { a.setAttribute('href', 'websites.html'); a.innerHTML = '<span>See website packages</span> ' + (C.arrow || ''); });
    d.documentElement.classList.add('o-ended');
  }
  SM.loadOffer(SLUG, function (o) {
    if (!o) return; // CRM unreachable: keep the built-in numbers, and checkout will check again
    if (o.state === 'missing') return closed(o);
    paint(o);
  });

  /* ---------- Buttons and the sticky bar ---------- */
  d.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-o-cta]');
    if (a && !d.documentElement.classList.contains('o-ended')) SM.track('Offer claim clicked', { offer: SLUG, from: a.closest('.o-card') ? 'card' : a.closest('.o-sticky') ? 'sticky' : a.closest('.o-final') ? 'final' : 'hero' });
  });
  var bar = d.querySelector('[data-o-sticky]'), hero = d.querySelector('.o-hero'), fin = d.querySelector('.o-final');
  if (bar && hero && 'IntersectionObserver' in w) {
    var heroOut = false, finIn = false;
    var upd = function () { var on = heroOut && !finIn; bar.classList.toggle('on', on); bar.setAttribute('aria-hidden', on ? 'false' : 'true'); $$('a', bar).forEach(function (x) { x.tabIndex = on ? 0 : -1; }); };
    new IntersectionObserver(function (es) { heroOut = !es[0].isIntersecting; upd(); }, { threshold: 0, rootMargin: '-120px 0px 0px 0px' }).observe(hero);
    if (fin) new IntersectionObserver(function (es) { finIn = es[0].isIntersecting; upd(); }).observe(fin);
  }
})();
