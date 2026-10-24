/* Checkout: review the cart, your details, a short project brief, then pay on Stripe.
   Nothing is charged here. The CRM prices the order again, saves it and opens Stripe Checkout. */
(function () {
  'use strict';
  var d = document, w = window, SM = w.SM, C = w.SMCards;
  if (!SM || !d.getElementById('checkout')) return;
  function $(s, r) { return (r || d).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || d).querySelectorAll(s)); }
  var form = $('#coForm'), stepsNav = $('#coSteps'), sum = $('#coSum');
  var DRAFT = 'sm_checkout';
  var step = 1, maxStep = 1;
  var LABELS = { name: 'Name', email: 'Email', phone: 'Phone', business: 'Business', website: 'Current website',
    industry: 'Industry', goal: 'Main goal', pages: 'Pages', style: 'Style', has_domain: 'Has a domain', domain: 'Domain', has_logo: 'Has a logo', assets: 'Photos & text',
    deadline: 'Deadline', platforms: 'Ad platforms', ad_budget: 'Monthly ad budget', ad_area: 'Area to target', ad_accounts: 'Ad accounts', audit_url: 'To review', audit_focus: 'Focus', care_url: 'Website to look after', care_platform: 'Built with', care_access: 'Has admin logins', com_kind: 'Community for', com_name: 'Game, channel or team', com_link: 'Discord', com_size: 'Members today', notes: 'Notes' };
  var CUSTOMER = ['name', 'email', 'phone', 'business', 'website'];
  var PLAT_NEED = { 'ads-one': 1, 'ads-two': 2, 'ads-all': 3 };

  function draft(v) { try { if (v === undefined) return JSON.parse(sessionStorage.getItem(DRAFT) || 'null'); if (v === null) sessionStorage.removeItem(DRAFT); else sessionStorage.setItem(DRAFT, JSON.stringify(v)); } catch (e) { return null; } }
  function values() {
    var out = {};
    $$('input, select, textarea', form).forEach(function (el) {
      if (!el.name || el.closest('[data-for][hidden]') || (el.disabled && el.name !== 'platforms')) return;
      if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; return; }
      if (el.type === 'checkbox') { if (el.name === 'agree') return; if (!out[el.name]) out[el.name] = []; if (el.checked) out[el.name].push(el.value); return; }
      out[el.name] = el.value.trim();
    });
    return out;
  }
  function restore(v) {
    Object.keys(v || {}).forEach(function (k) {
      $$('[name="' + k + '"]', form).forEach(function (el) {
        if (el.type === 'radio') el.checked = el.value === v[k];
        else if (el.type === 'checkbox') el.checked = Array.isArray(v[k]) && v[k].indexOf(el.value) !== -1;
        else el.value = v[k];
      });
    });
  }
  function saveDraft() { draft({ step: step, max: maxStep, v: values() }); }
  function cats() { return SM.lines().map(function (l) { return l.p.category; }); }
  function has(c) { return cats().indexOf(c) !== -1; }
  function adsPlan() { return SM.lines().filter(function (l) { return l.p.category === 'ads'; })[0]; }

  /* ---------- Step 1: the order ---------- */
  function paintOrder() {
    var ls = SM.lines(), box = $('#coLines'), probs = SM.problems(ls);
    if (!ls.length) {
      box.innerHTML = '<div class="empty-cart" style="padding:30px 0"><b>Your cart is empty.</b><p style="margin-top:6px">Pick a website, an ads plan or a service to get started.</p><p style="margin-top:18px"><a class="btn btn-ink" href="pricing.html">See packages &amp; pricing</a></p></div>';
      $('#coProblems').innerHTML = ''; $('#coSuggest').innerHTML = '';
      $('[data-next="1"]').disabled = true; paintSummary(); return;
    }
    box.innerHTML = ls.map(function (l) { return SM.lineHtml(l, true); }).join('');
    $('#coProblems').innerHTML = probs.map(function (x) { return '<div class="alert bad">' + C.esc(x.text) + ' <a class="link" href="pricing.html" style="margin-left:auto;white-space:nowrap">Add one</a></div>'; }).join('');
    var sug = SM.suggestions(ls);
    $('#coSuggest').innerHTML = sug.length ? '<div class="suggest" style="margin-top:18px"><h4>Often added</h4>' + sug.map(function (p) {
      return '<div class="sug"><div><b>' + C.esc(p.name) + ' · ' + C.short(p) + '</b><span>' + C.esc(p.blurb) + '</span></div><button type="button" class="btn btn-ghost btn-xs" data-add="' + p.sku + '">' + C.plus + ' Add</button></div>';
    }).join('') + '</div>' : '';
    $('[data-next="1"]').disabled = probs.length > 0;
    paintSummary();
  }

  /* ---------- Summary sidebar ---------- */
  function paintSummary() {
    var ls = SM.lines(), t = SM.totals(ls), c = cats();
    var perks = ['A short kickoff form or call, whichever you prefer', 'Your own client portal for drafts, files and approvals'];
    if (c.indexOf('website') !== -1 || c.indexOf('ads') !== -1 || ls.some(function (l) { return l.p.sku === 'svc-analytics'; })) perks.push('Live Site Stats dashboard included');
    if (t.trialEnds) perks.unshift('Free months until ' + SM.longDate(t.trialEnds) + '. We email you a week before the first charge');
    if (t.mo) perks.push('Monthly plans: cancel online any time');
    var of = SM.offer();
    $('.sum-lines', sum).innerHTML = (of ? '<div class="offer-tag">' + C.check + '<span>' + C.esc(of.name) + '</span></div>' : '') + ls.map(function (l) {
      return '<div class="sl"><span>' + (l.qty > 1 ? l.qty + ' × ' : '') + C.esc(l.p.name) + '<small>' + C.esc((SM.cart && w.SMCATS && w.SMCATS[l.p.category]) || '') + '</small></span><b>' + priceText(l) + '</b></div>';
    }).join('') || '<p class="muted" style="font-size:14px">No items yet.</p>';
    $('.sum-totals', sum).innerHTML = ls.length ? SM.totalsHtml(t) : '';
    $('.sum-perks', sum).innerHTML = perks.map(function (p) { return '<div class="perk">' + C.check + '<span>' + C.esc(p) + '</span></div>'; }).join('');
    $('.sum-count', sum).textContent = ls.length ? ls.length + ' item' + (ls.length === 1 ? '' : 's') + ' · ' + C.money(t.today) : '';
    var pb = $('#payBtn'); if (pb) pb.innerHTML = 'Pay ' + C.money(t.today) + ' securely ' + C.arrow;
    $$('[data-show-if-mo]').forEach(function (el) { el.hidden = !t.mo; });
    $$('.mo-amt').forEach(function (el) { el.textContent = C.money(t.mo); });
    $$('.mo-start').forEach(function (el) { el.textContent = t.trialEnds ? SM.longDate(t.trialEnds) + ' (after your ' + t.trialMonths + ' free months)' : 'today'; });
  }
  function priceText(l) {
    if (l.p.incl) return '<s class="was">' + C.money(l.p.was * l.qty) + '</s> Included';
    if (l.p.trial) return 'Free for ' + l.p.trial + ' months<small style="display:block;font-weight:400;text-align:right">then ' + C.money(l.p.price * l.qty) + '/mo</small>';
    return (l.p.was ? '<s class="was">' + C.money(l.p.was * l.qty) + '</s> ' : '') + C.money(l.p.price * l.qty) + (l.p.interval === 'month' ? '/mo' : '');
  }

  /* ---------- Step 3: questions that depend on the cart ---------- */
  function paintBrief() {
    var cu = form.elements.care_url, ws = form.elements.website;
    if (cu && ws && !cu.value && ws.value) cu.value = ws.value;
    $$('[data-for]', form).forEach(function (sec) {
      var not = sec.getAttribute('data-not');
      var on = sec.getAttribute('data-for').split(' ').some(has) && !(not && not.split(' ').some(has));
      sec.hidden = !on;
      $$('input, select, textarea', sec).forEach(function (el) { el.disabled = !on; });
    });
    var plan = adsPlan(), need = plan ? PLAT_NEED[plan.p.sku] || 0 : 0;
    var hint = $('#platHint');
    if (plan && hint) {
      hint.textContent = need === 3 ? 'All three are included in your plan.' : 'Pick ' + (need === 1 ? 'one' : 'two') + ' for your ' + plan.p.name.replace('Ads management — ', '') + ' plan.';
      $$('input[name="platforms"]').forEach(function (cb) { if (need === 3) { cb.checked = true; cb.disabled = true; } else cb.disabled = false; });
    }
    toggles();
  }
  function inCart(sku) { return SM.lines().some(function (l) { return l.p.sku === sku; }); }
  // Small follow-ups: show the domain box, and suggest add-ons that fit the answers.
  function toggles() {
    var v = values();
    var dom = $('#domainField'); if (dom) dom.hidden = v.has_domain !== 'yes';
    offer('#domainOffer', (v.has_domain === 'no' || v.has_domain === 'unsure') && !inCart('add-domain-setup'), 'add-domain-setup');
    offer('#logoOffer', v.has_logo === 'no' && !inCart('add-logo'), 'add-logo');
    offer('#copyOffer', v.assets === 'help' && !inCart('add-copy'), 'add-copy');
    offer('#setupOffer', v.ad_accounts === 'new' && !inCart('add-ads-setup'), 'add-ads-setup');
  }
  function offer(sel, show, sku) {
    var el = $(sel); if (!el) return;
    var p = SM.product(sku);
    el.hidden = !show || !p;
    var incl = p && (p.free_with || []).some(inCart);
    if (show && p) el.innerHTML = '<div class="sug" style="margin:0"><div><b>' + C.esc(p.name) + ' · ' + (incl ? 'Free with your package' : C.short(p)) + '</b><span>' + C.esc(p.blurb) + '</span></div><button type="button" class="btn btn-ink btn-xs" data-add="' + sku + '">' + C.plus + ' Add</button></div>';
  }

  /* ---------- Validation ---------- */
  function fieldErr(name, msg) {
    var el = form.elements[name]; if (!el) return false;
    var f = (el.length && !el.tagName ? el[0] : el).closest('.field');
    if (f) { f.classList.add('bad'); var e = $('.err', f); if (e && msg) e.textContent = msg; }
    var first = el.length && !el.tagName ? el[0] : el; if (first && first.focus) first.focus();
    return false;
  }
  function validate(n) {
    $$('.field.bad', form).forEach(function (f) { f.classList.remove('bad'); });
    var v = values();
    if (n === 2) {
      if ((v.name || '').length < 2) return fieldErr('name', 'Enter your name.');
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v.email || '')) return fieldErr('email', 'Enter a valid email address.');
      if ((v.business || '').length < 2) return fieldErr('business', 'Enter your business name.');
    }
    if (n === 3) {
      if (has('website') && !v.goal) return fieldErr('goal', 'Choose the main goal for your website.');
      if (has('care') && !has('website') && !/^[^\s\/]+\.[a-z]{2,}/i.test((v.care_url || '').replace(/^https?:\/\//, ''))) return fieldErr('care_url', 'Enter the address of the website you’d like us to look after.');
      var plan = adsPlan();
      if (plan) {
        var need = PLAT_NEED[plan.p.sku] || 0, got = (v.platforms || []).length;
        if (need && need < 3 && got !== need) return fieldErr('platforms', 'Pick ' + (need === 1 ? 'one platform' : 'two platforms') + ' for this plan.');
      }
      if (v.has_domain === 'yes' && v.domain && !/^[^\s\/]+\.[a-z]{2,}/i.test(v.domain.replace(/^https?:\/\//, ''))) return fieldErr('domain', 'Enter the domain, like yourbusiness.com.');
    }
    return true;
  }

  /* ---------- Step 4: review ---------- */
  function paintReview() {
    var v = values(), ls = SM.lines();
    var dl = function (keys) { return keys.filter(function (k) { return v[k] && (!Array.isArray(v[k]) || v[k].length); }).map(function (k) {
      var val = Array.isArray(v[k]) ? v[k].map(function (x) { return optLabel(k, x); }).join(', ') : optLabel(k, v[k]);
      return '<dt>' + C.esc(LABELS[k] || k) + '</dt><dd>' + C.esc(val) + '</dd>'; }).join(''); };
    var brief = Object.keys(LABELS).filter(function (k) { return CUSTOMER.indexOf(k) === -1; });
    var b = dl(brief);
    $('#coReview').innerHTML =
      '<div class="rev-block"><h4>Order <button type="button" data-goto="1">Edit</button></h4>' + ls.map(function (l) { return '<div style="display:flex;justify-content:space-between;gap:12px"><span>' + (l.qty > 1 ? l.qty + ' × ' : '') + C.esc(l.p.name) + '</span><b style="text-align:right">' + priceText(l) + '</b></div>'; }).join('') + '</div>' +
      '<div class="rev-block"><h4>You <button type="button" data-goto="2">Edit</button></h4><dl>' + dl(CUSTOMER) + '</dl></div>' +
      '<div class="rev-block"><h4>Project <button type="button" data-goto="3">Edit</button></h4>' + (b ? '<dl>' + b + '</dl>' : '<p class="muted">No details yet. That’s fine, we’ll cover it at kickoff.</p>') + '</div>';
  }
  function optLabel(name, value) {
    var el = $('input[name="' + name + '"][value="' + (w.CSS && CSS.escape ? CSS.escape(value) : value) + '"]', form);
    if (el && el.id) { var l = $('label[for="' + el.id + '"]', form); if (l) return (l.childNodes[0] && l.childNodes[0].textContent || l.textContent).trim(); }
    var sel = form.elements[name]; if (sel && sel.tagName === 'SELECT') { var o = sel.querySelector('option[value="' + value + '"]'); if (o) return o.textContent; }
    return value;
  }

  /* ---------- Creator code ---------- */
  var codeChecked = '';
  function paintCode(msg, bad) {
    var cd = SM.code(), of = SM.offer(), box = $('#codeBox'); if (!box) return;
    box.hidden = !!of && !cd;
    var on = $('#codeOn'), fm = $('#codeForm'), m = $('#codeMsg');
    on.hidden = !cd; fm.hidden = !!cd;
    if (cd) on.innerHTML = C.check + '<span><b>' + C.esc(cd.code) + '</b> · ' + cd.pct + '% off the one-time items in this order</span><button type="button" id="codeRemove">Remove</button>';
    if (cd && of) on.innerHTML += '<span class="code-msg bad" style="flex-basis:100%">Codes can’t be combined with ' + C.esc(of.name) + '. Remove the code to keep the offer.</span>';
    m.textContent = msg || ''; m.className = 'code-msg' + (bad ? ' bad' : '');
  }
  function applyCode() {
    var inp = $('#codeIn'), v = (inp.value || '').replace(/\s+/g, '').toLowerCase();
    if (!v) { paintCode('Type the code, then Apply.', true); inp.focus(); return; }
    var b = $('#codeApply'); b.disabled = true;
    SM.checkCode(v, values().email || '', function (r) {
      b.disabled = false;
      if (!r.ok) { paintCode(r.error || 'We don’t recognise that code.', true); inp.focus(); return; }
      inp.value = ''; codeChecked = (values().email || '').toLowerCase(); SM.setCode(r); SM.track('Code applied', { code: r.code }); paintCode();
    });
  }
  // Once we know their email, check the code still applies (it's for a first order from someone new).
  function recheckCode() {
    var cd = SM.code(), em = (values().email || '').toLowerCase();
    if (!cd || !em || em === codeChecked) return;
    codeChecked = em;
    SM.checkCode(cd.ref, em, function (r) { if (!r.ok && !r.offline) { SM.setCode(null); paintCode(r.error, true); } });
  }

  /* ---------- Moving between steps ---------- */
  function go(n, opts) {
    if (n > step) { for (var i = step; i < n; i++) if (i > 1 && !validate(i)) return; }
    if (n > 1 && (!SM.lines().length || SM.problems(SM.lines()).length)) n = 1;
    step = n; maxStep = Math.max(maxStep, n);
    $$('.co-step').forEach(function (s) { s.hidden = Number(s.getAttribute('data-step')) !== n; });
    $$('li', stepsNav).forEach(function (li) {
      var k = Number(li.getAttribute('data-step'));
      li.className = k === n ? 'cur' : (k <= maxStep ? 'done' : '');
      var b = $('button', li); b.disabled = k > maxStep; if (k === n) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
    });
    if (n === 1) paintOrder();
    if (n === 3) paintBrief();
    if (n === 4) { paintReview(); paintCode(); recheckCode(); }
    saveDraft();
    if (!opts || !opts.quiet) { var top = $('#checkout').getBoundingClientRect().top + w.scrollY - 90; if (w.scrollY > top) w.scrollTo({ top: top, behavior: 'smooth' }); var h = $('.co-step:not([hidden]) h2'); if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); } }
    SM.track('Checkout step', { step: String(n) });
  }

  d.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target : null; if (!t) return;
    var nx = t.closest('[data-next]'), bk = t.closest('[data-back]'), gt = t.closest('[data-goto]'), st = t.closest('#coSteps button');
    if (nx) { e.preventDefault(); if (step === 1 || validate(step)) go(step + 1); }
    if (bk) { e.preventDefault(); go(step - 1); }
    if (gt) { e.preventDefault(); go(Number(gt.getAttribute('data-goto'))); }
    if (st && !st.disabled) { e.preventDefault(); var k = Number(st.closest('li').getAttribute('data-step')); if (k <= maxStep) go(k); }
    if (t.closest('#codeApply')) { e.preventDefault(); applyCode(); }
    if (t.closest('#codeRemove')) { e.preventDefault(); SM.setCode(null); paintCode(); var ci = $('#codeIn'); if (ci) ci.focus(); }
    if (t.closest('.sum-toggle')) { sum.classList.toggle('open'); t.closest('.sum-toggle').setAttribute('aria-expanded', sum.classList.contains('open')); }
  });
  form.addEventListener('keydown', function (e) { if (e.target.id === 'codeIn' && e.key === 'Enter') { e.preventDefault(); applyCode(); } });
  form.addEventListener('input', function (e) { var f = e.target.closest('.field'); if (f) f.classList.remove('bad'); toggles(); saveDraft(); });
  form.addEventListener('change', function (e) { if (e.target.name === 'agree' && e.target.checked) $('#payAlert').hidden = true; toggles(); saveDraft(); });
  d.addEventListener('sm:cart', function () { paintOrder(); paintBrief(); if (step === 4) { paintReview(); paintCode(); } });

  /* ---------- Pay ---------- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (step !== 4) { if (validate(step)) go(step + 1); return; }
    var alertEl = $('#payAlert'); alertEl.hidden = true;
    if (!form.elements.agree.checked) { alertEl.hidden = false; alertEl.textContent = 'Please agree to the terms of sale to continue.'; form.elements.agree.focus(); return; }
    var v = values(), customer = {}, brief = {};
    Object.keys(v).forEach(function (k) { if (CUSTOMER.indexOf(k) !== -1) customer[k] = v[k]; else if (k !== 'website_url') brief[k] = v[k]; });
    if (adsPlan() && PLAT_NEED[adsPlan().p.sku] === 3) brief.platforms = ['google', 'meta', 'tiktok'];
    ['has_domain', 'has_logo', 'assets', 'ad_accounts'].forEach(function (k) { if (brief[k]) brief[k] = optLabel(k, brief[k]); }); // the words they picked, for the CRM
    var btn = $('#payBtn'); btn.classList.add('is-loading'); btn.textContent = 'Opening secure payment…';
    var src = ''; try { src = sessionStorage.getItem('sm_src') || ''; } catch (err) {}
    fetch(SM.API + '?a=checkout', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: SM.cart().items, offer: SM.offer() ? SM.cart().offer : '', code: SM.code() ? SM.code().ref : '', customer: customer, brief: brief, agree: true, source: src, ref: SM.ref ? SM.ref() : '', website_url: v.website_url || '' }) })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (r) {
        if (r.offer_gone && SM.cart().offer) { try { sessionStorage.removeItem('sm_offer_' + SM.cart().offer); } catch (e2) {} SM.dropOffer(); }
        if (r.code_bad) { SM.setCode(null); paintCode(r.error, true); }
        if (!r.ok || !r.url) { var er = new Error(r.error || ''); er.field = r.field; throw er; }
        try { sessionStorage.setItem('sm_order', JSON.stringify({ ref: r.ref, k: r.key })); } catch (err) {}
        SM.track('Checkout started', { value: String(SM.totals(SM.lines()).today) });
        location.href = r.url;
      })
      .catch(function (err) {
        btn.classList.remove('is-loading'); paintSummary();
        alertEl.hidden = false; alertEl.textContent = (err && err.message) || ('We couldn’t reach the payment page. Check your connection and try again, or email ' + SM.CFG.email + '.');
        if (err && err.field === 'code') { paintCode(err.message, true); }
        else if (err && err.field && err.field !== 'agree') { var k = CUSTOMER.indexOf(err.field) !== -1 ? 2 : 3; go(k); fieldErr(err.field, err.message); }
      });
  });

  /* ---------- Start ---------- */
  SM.onCatalog(function (c) {
    w.SMCATS = c.categories || {};
    var dr = draft();
    if (dr && dr.v) restore(dr.v);
    var params = new URLSearchParams(location.search);
    if (params.get('cancelled')) { $('#coCancelled').hidden = false; SM.track('Checkout cancelled'); }
    if (c.offline || c.ready === false) $('#coOffline').hidden = false;
    if (c.promo) $$('[data-promo]').forEach(function (el) { el.hidden = false; });
    maxStep = dr && dr.max ? dr.max : 1;
    // From the reminder email: put the saved order back in the cart and the details back in the form.
    var rRef = params.get('resume'), rKey = params.get('k');
    if (rRef && rKey) {
      try { history.replaceState(null, '', location.pathname); } catch (e) {}
      fetch(SM.API + '?a=resume&ref=' + encodeURIComponent(rRef) + '&k=' + encodeURIComponent(rKey), { credentials: 'omit' })
        .then(function (r) { return r.json(); })
        .then(function (r) {
          if (!r.ok) { if (r.paid) location.href = 'thank-you.html?ref=' + encodeURIComponent(rRef) + '&k=' + encodeURIComponent(rKey); else go(1, { quiet: true }); return; }
          SM.clear();
          r.items.forEach(function (i) { SM.add(i.sku); if (i.qty > 1) SM.setQty(i.sku, i.qty); });
          restore(Object.assign({}, r.customer, r.brief));
          if (r.code) SM.checkCode(r.code, r.customer.email, function (x) { if (x.ok) SM.setCode(x); });
          maxStep = 4; SM.track('Checkout resumed');
          var next = function () { go(SM.problems(SM.lines()).length ? 1 : 3, { quiet: true }); };
          if (r.offer) SM.setOffer(r.offer, next); else next();
        })
        .catch(function () { go(1, { quiet: true }); })
        .then(function () { $('#checkout').classList.add('ready'); });
      return;
    }
    go(dr && dr.step && SM.lines().length ? Math.min(dr.step, maxStep) : 1, { quiet: true });
    $('#checkout').classList.add('ready');
  });
})();
