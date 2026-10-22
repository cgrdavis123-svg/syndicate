/* Free website checkup (checkup.html) → CRM shop-api.php?a=checkup, checkup-report, checkup-speed, checkup-lead.
   The report lives at checkup.html?r=TOKEN, so it can be shared and reopened. */
(function () {
  'use strict';
  var d = document, w = window, SM = w.SM, C = w.SMCards;
  var form = d.getElementById('ckForm'), working = d.getElementById('ckWorking'), box = d.getElementById('ckReport'), alertEl = d.getElementById('ckAlert');
  if (!form || !SM || !C) return;
  var esc = C.esc, qs = new URLSearchParams(location.search), noView = qs.get('nv') === '1';
  var ICON = { good: '✓', warn: '!', bad: '✕' }, WORD = { good: 'Looks good', warn: 'Worth a look', bad: 'Problem' };
  var stepTimer = null, pollTimer = null, report = null;

  function api(a, body) {
    var opt = body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : { credentials: 'omit' };
    return fetch(SM.API + '?a=' + a, opt).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
  }
  function resetCaptcha(scope) { var ts = (scope || d).querySelector('.cf-turnstile'); if (ts && w.turnstile) try { w.turnstile.reset(ts); } catch (e) {} }
  function showError(msg) { alertEl.hidden = false; alertEl.textContent = msg; }
  function setWorking(on, host) {
    working.hidden = !on;
    clearInterval(stepTimer);
    if (!on) return;
    working.querySelector('[data-ck-host]').textContent = host;
    var li = working.querySelectorAll('.ck-steps li'), i = 0;
    Array.prototype.forEach.call(li, function (x) { x.className = ''; });
    li[0].className = 'on';
    stepTimer = setInterval(function () { if (i < li.length - 1) { li[i].className = 'done'; i++; li[i].className = 'on'; } }, 2600);
  }
  function cleanHost(v) { return String(v || '').trim().replace(/^[a-z]+:\/\//i, '').replace(/[\/?#].*$/, '').toLowerCase(); }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    alertEl.hidden = true;
    var data = {}; new FormData(form).forEach(function (v, k) { data[k] = v; });
    var host = cleanHost(data.url);
    if (!host || host.indexOf('.') < 1) { showError('Enter your website address, like yourbusiness.com.'); form.elements.url.focus(); return; }
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true; box.hidden = true; clearTimeout(pollTimer);
    setWorking(true, host);
    working.scrollIntoView({ behavior: 'smooth', block: 'center' });
    api('checkup', data).then(function (r) {
      if (!r.ok) throw new Error(r.error || '');
      SM.track('Checkup run');
      try { history.replaceState(null, '', location.pathname + '?r=' + r.token); } catch (e2) {}
      return load(r.token, true);
    }).catch(function (err) {
      setWorking(false);
      showError((err && err.message) || ('We couldn’t run the checkup just now. Please try again, or email ' + SM.CFG.email + '.'));
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }).then(function () { btn.disabled = false; resetCaptcha(form); });
  });

  function load(token, fresh) {
    return fetch(SM.API + '?a=checkup-report&r=' + encodeURIComponent(token) + (noView ? '&nv=1' : ''), { credentials: 'omit' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        setWorking(false);
        if (!r.ok) throw new Error(r.error || 'This report has expired.');
        report = r; paint(); box.hidden = false;
        if (fresh) box.focus({ preventScroll: true });
        box.scrollIntoView({ behavior: fresh ? 'smooth' : 'auto', block: 'start' });
        if (r.speed.state === 'waiting') runSpeed();
        else if (r.speed.state === 'running') poll();
      });
  }
  function runSpeed() {
    report.speed.state = 'running'; paintSpeed();
    api('checkup-speed', { r: report.token }).then(function (r) {
      if (!r.ok) throw 0;
      report = r; paint();
      if (r.speed.state === 'running') poll();
    }).catch(function () { report.speed.state = 'error'; paintSpeed(); });
  }
  function poll(n) {
    n = n || 0;
    pollTimer = setTimeout(function () {
      fetch(SM.API + '?a=checkup-report&nv=1&r=' + report.token, { credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (r) {
        if (!r.ok) return;
        if (r.speed.state === 'running' && n < 24) { poll(n + 1); return; }
        report = r; paint();
        if (r.speed.state === 'waiting') runSpeed();
      }).catch(function () {});
    }, 5000);
  }

  /* ---------- Drawing the report ---------- */
  function ring(score, size, label) {
    var r = size / 2 - 6, c = 2 * Math.PI * r, cls = score >= 90 ? 'good' : (score >= 50 ? 'warn' : 'bad');
    return '<div class="ck-ring ' + cls + '" style="width:' + size + 'px;height:' + size + 'px"><svg viewBox="0 0 ' + size + ' ' + size + '" aria-hidden="true">' +
      '<circle cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" class="tr"/><circle cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" class="fg" stroke-dasharray="' + (c * score / 100).toFixed(1) + ' ' + c.toFixed(1) + '"/></svg>' +
      '<b>' + score + '</b>' + (label ? '<span class="sr">' + esc(label) + ': ' + score + ' out of 100</span>' : '') + '</div>';
  }
  function fixLink(sku) {
    var p = SM.product(sku);
    if (!p) return '';
    return '<p class="ck-fix">How we’d fix it: <button type="button" class="link" data-add="' + esc(sku) + '">' + esc(p.name) + ' · ' + C.short(p) + '</button></p>';
  }
  function paint() {
    var r = report, total = r.counts.good + r.counts.warn + r.counts.bad, stale = Date.now() / 1000 - r.checked_ts > 6 * 3600;
    var html = '<div class="wrap">' +
      '<div class="ck-sum">' +
        '<div class="ck-sum-l">' + ring(r.score, 132, 'Checkup score') + '<div><span class="eyebrow"><span class="dot"></span>Checkup report</span>' +
          '<h2 id="ckTitle">' + esc(r.host) + '</h2>' +
          '<p class="ck-counts"><span class="good">' + r.counts.good + ' look good</span><span class="warn">' + r.counts.warn + ' worth a look</span><span class="bad">' + r.counts.bad + ' problem' + (r.counts.bad === 1 ? '' : 's') + '</span></p>' +
          '<p class="fine">Score from ' + total + ' checks · checked ' + esc(r.checked) + (r.platform ? ' · built with ' + esc(r.platform) : '') + '</p></div></div>' +
        '<div class="ck-sum-r"><button type="button" class="btn btn-ghost btn-sm" data-ck="share">Copy report link</button><button type="button" class="btn btn-ghost btn-sm" data-ck="again">' + (stale ? 'Run it again' : 'Check another site') + '</button></div>' +
      '</div>' +
      '<div class="ck-speed" id="ckSpeed"></div>' +
      '<div class="ck-grid">';
    var areas = r.areas;
    Object.keys(areas).forEach(function (a) {
      var list = r.findings.filter(function (f) { return f.area === a; });
      if (!list.length) return;
      list.sort(function (x, y) { return ({ bad: 0, warn: 1, good: 2 })[x.level] - ({ bad: 0, warn: 1, good: 2 })[y.level]; });
      html += '<section class="ck-area"><h3>' + esc(areas[a]) + '</h3><ul>';
      list.forEach(function (f) {
        html += '<li class="lvl-' + f.level + '"><span class="ck-ico" aria-hidden="true">' + ICON[f.level] + '</span><div><b><span class="sr">' + WORD[f.level] + ': </span>' + esc(f.title) + '</b>' +
          (f.detail ? '<p>' + esc(f.detail) + '</p>' : '') +
          (f.id === 'broken' && r.broken.length ? '<ul class="ck-broken">' + r.broken.map(function (b) { return '<li><span class="mono">' + esc(b.url.replace(/^https?:\/\/[^/]+/, '') || '/') + '</span> <small>' + (b.code ? 'error ' + b.code : 'no answer') + '</small></li>'; }).join('') + '</ul>' : '') +
          (f.level !== 'good' && f.fix ? '<span data-fix="' + esc(f.fix) + '"></span>' : '') + '</div></li>';
      });
      html += '</ul></section>';
    });
    html += '</div>';
    if (r.fixes.length) {
      html += '<div class="ck-next"><div class="sec-head"><div><span class="eyebrow"><span class="dot"></span>What would fix this</span><h2>Do it yourself, or <span class="serif">hand it to us.</span></h2>' +
        '<p class="lede">Every item above says what it means, so your web person can fix it. If you’d rather we did, these cover it, at fixed, published prices.</p></div></div>' +
        '<div class="mini-grid" data-ck-fixes></div>' +
        '<p class="ck-call">Not sure where to start? <a class="link" href="' + esc(box.getAttribute('data-book')) + '">Book a free 20-minute call</a> and we’ll go through the report with you.</p></div>';
    }
    html += '<div class="ck-mail" id="ckMail"></div>';
    html += '<p class="fine ck-note">This report is automated and looks at the home page only. Anyone with its link can open it. It doesn’t judge design or writing; for that, a person can go through your whole site in our website &amp; SEO audit.</p></div>';
    box.innerHTML = html;
    paintSpeed(); paintMail();
    SM.onCatalog(function (c) {
      Array.prototype.forEach.call(box.querySelectorAll('[data-fix]'), function (el) { el.outerHTML = fixLink(el.getAttribute('data-fix')); });
      var g = box.querySelector('[data-ck-fixes]');
      if (g) g.innerHTML = r.fixes.slice(0, 3).map(function (x) { var p = SM.product(x.sku); return p ? C.mini(p) : ''; }).join('');
    });
  }
  function paintSpeed() {
    var el = d.getElementById('ckSpeed'); if (!el) return;
    var s = report.speed, html = '<div class="ck-speed-h"><h3>Google’s phone speed test</h3><span class="fine">Google loads your home page on a mid-range phone and scores it out of 100.</span></div>';
    if (s.state === 'done') {
      html += '<div class="ck-rings">' + Object.keys(s.labels).map(function (k) { return s.scores[k] == null ? '' : '<div>' + ring(s.scores[k], 84, s.labels[k]) + '<span>' + esc(s.labels[k]) + '</span></div>'; }).join('') + '</div>';
      var m = Object.keys(s.metric_labels).filter(function (k) { return s.metrics[k] && s.metrics[k].show; });
      if (m.length) html += '<dl class="ck-metrics">' + m.map(function (k) { var x = s.metrics[k]; return '<div class="' + (x.score >= 0.9 ? 'good' : (x.score >= 0.5 ? 'warn' : 'bad')) + '"><dt>' + esc(s.metric_labels[k]) + '</dt><dd>' + esc(x.show) + '</dd></div>'; }).join('') + '</dl>';
      if (s.fixes.length) html += '<div class="ck-psifix"><b>What Google says would speed it up most</b><ol>' + s.fixes.map(function (f) { return '<li>' + esc(f.title) + (f.ms >= 100 ? ' <small>saves about ' + (f.ms >= 1000 ? (f.ms / 1000).toFixed(1) + ' s' : f.ms + ' ms') + '</small>' : '') + '</li>'; }).join('') + '</ol></div>';
    } else if (s.state === 'error') {
      html += '<p class="ck-wait">Google’s speed test didn’t answer this time. <button type="button" class="link" data-ck="speed">Try it again</button></p>';
    } else {
      html += '<p class="ck-wait" aria-live="polite"><span class="ck-spin sm" aria-hidden="true"></span> Running now. Google’s test takes 20–60 seconds; the rest of your report is ready below.</p>';
    }
    el.innerHTML = html;
  }
  function paintMail() {
    var el = d.getElementById('ckMail'); if (!el) return;
    if (report.emailed && !el.getAttribute('data-sent')) { el.innerHTML = '<div class="alert ok" role="status"><div><b>This report has been emailed.</b> Want it sent somewhere else too? <button type="button" class="link" data-ck="mailform">Send another copy</button></div></div>'; return; }
    el.innerHTML = '<form class="co-panel ck-mailform" id="ckMailForm" novalidate><div><h3>Email me this report</h3><p class="muted">Keep it for later or forward it to whoever looks after your site. We’ll send it from ' + esc(SM.CFG.email) + ' and may check in once to see if you want help.</p></div>' +
      '<div class="form-grid"><label class="field"><span>Your name</span><input name="name" autocomplete="name" required><span class="err">Enter your name.</span></label>' +
      '<label class="field"><span>Email</span><input name="email" type="email" autocomplete="email" required><span class="err">Enter a valid email address.</span></label>' +
      '<label class="field full"><span>Business name <small>(optional)</small></span><input name="business" autocomplete="organization"></label>' +
      '<div class="hp" aria-hidden="true"><label>Leave this empty <input name="website_url" tabindex="-1" autocomplete="off"></label></div>' +
      '<div class="full" data-ck-captcha></div><div class="alert bad full" id="ckMailAlert" hidden></div>' +
      '<div class="full"><button type="submit" class="btn btn-ink btn-block">Email me the report</button><p class="fine" style="margin-top:12px">We use your details to send this and follow up. <a class="link" href="privacy.html#checkup">Privacy policy</a></p></div></div></form>';
    var heroTs = form.querySelector('.cf-turnstile'), slot = el.querySelector('[data-ck-captcha]');
    if (heroTs && slot) {
      var mount = function () { if (!w.turnstile) return setTimeout(mount, 400); try { w.turnstile.render(slot, { sitekey: heroTs.getAttribute('data-sitekey'), action: 'checkup', theme: 'light', size: 'flexible', appearance: 'interaction-only' }); } catch (e) {} };
      mount();
    }
    el.querySelector('form').addEventListener('submit', sendMail);
  }
  function sendMail(e) {
    e.preventDefault();
    var f = e.target, btn = f.querySelector('button[type="submit"]'), al = d.getElementById('ckMailAlert');
    Array.prototype.forEach.call(f.querySelectorAll('.field'), function (x) { x.classList.remove('bad'); });
    al.hidden = true;
    var data = {}; new FormData(f).forEach(function (v, k) { data[k] = v; });
    var bad = (data.name || '').trim().length < 2 ? 'name' : (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(data.email || '') ? 'email' : '');
    if (bad) { f.elements[bad].closest('.field').classList.add('bad'); f.elements[bad].focus(); return; }
    data.r = report.token; if (SM.ref && SM.ref()) data.ref = SM.ref();
    btn.disabled = true; btn.textContent = 'Sending…';
    api('checkup-lead', data).then(function (r) {
      if (!r.ok) throw new Error(r.error || '');
      SM.track('Checkup emailed');
      var el = d.getElementById('ckMail'); el.setAttribute('data-sent', '1');
      el.innerHTML = '<div class="alert ok" role="status"><div><b>On its way, ' + esc(data.name.trim().split(' ')[0]) + '.</b> Check ' + esc(data.email) + ' in a minute or two (and your spam folder, just in case).</div></div>';
      report.emailed = true;
    }).catch(function (err) {
      btn.disabled = false; btn.textContent = 'Email me the report';
      resetCaptcha(f);
      al.hidden = false; al.textContent = (err && err.message) || ('We couldn’t send it just now. Please email ' + SM.CFG.email + '.');
    });
  }

  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ck]'); if (!b) return;
    var k = b.getAttribute('data-ck');
    if (k === 'share') {
      var u = location.origin + location.pathname + '?r=' + report.token;
      var done = function () { SM.toast ? SM.toast('Report link copied') : (b.textContent = 'Link copied'); };
      if (navigator.clipboard) navigator.clipboard.writeText(u).then(done, function () { prompt('Copy this link:', u); }); else prompt('Copy this link:', u);
    } else if (k === 'again') {
      form.elements.url.value = b.textContent === 'Run it again' ? report.host : '';
      try { history.replaceState(null, '', location.pathname); } catch (e3) {}
      box.hidden = true; w.scrollTo({ top: 0, behavior: 'smooth' }); setTimeout(function () { form.elements.url.focus(); }, 400);
    } else if (k === 'speed') runSpeed();
    else if (k === 'mailform') { report.emailed = false; paintMail(); }
  });

  if (qs.get('r')) {
    setWorking(true, 'your report');
    load(qs.get('r'), false).catch(function (err) { setWorking(false); showError((err && err.message) || 'This report has expired. Run a new checkup above.'); });
  } else if (qs.get('url')) form.elements.url.value = qs.get('url');
})();
