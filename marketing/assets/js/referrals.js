/* Referral and creator sign-up (referrals.html, creators.html) → CRM shop-api.php?a=refer. */
(function () {
  'use strict';
  var d = document, w = window, f = d.getElementById('referForm');
  if (!f || !w.SM) return;
  var cash = f.querySelector('[data-cash]'), creator = f.hasAttribute('data-creator');
  function bad(name, msg) { var el = f.elements[name]; if (!el || !el.closest) return; var fl = el.closest('.field'); if (fl) { fl.classList.add('bad'); var e = fl.querySelector('.err'); if (e && msg) e.textContent = msg; } el.focus(); }
  function showCash() { if (cash) cash.hidden = !f.querySelector('input[name="payout"][value="cash"]').checked; }
  Array.prototype.forEach.call(f.querySelectorAll('input[name="payout"]'), function (r) { r.addEventListener('change', showCash); });
  showCash();
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = f.querySelector('button[type="submit"]'), alertEl = d.getElementById('referAlert');
    Array.prototype.forEach.call(f.querySelectorAll('.field'), function (x) { x.classList.remove('bad'); });
    alertEl.hidden = true;
    var data = {}; new FormData(f).forEach(function (v, k) { data[k] = v; });
    if (creator) data.code = (data.code || '').replace(/\s+/g, '').toLowerCase();
    var miss = (data.name || '').trim().length < 2 ? 'name' : (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(data.email || '') ? 'email'
      : (creator && !/^[a-z0-9]{3,20}$/.test(data.code) ? 'code' : (creator && (data.audience || '').trim().length < 3 ? 'audience' : '')));
    if (miss) { bad(miss); return; }
    if (!data.agree) { alertEl.hidden = false; alertEl.textContent = creator ? 'Please confirm you’re 18 or older and agree to the creator rules.' : 'Please confirm you’re 18 or older and agree to the referral rules.'; f.elements.agree.focus(); return; }
    btn.classList.add('is-loading'); btn.textContent = 'Sending…';
    fetch(SM.API + '?a=refer', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (r) {
        if (!r.ok) { var er = new Error(r.error || ''); er.field = r.field; throw er; }
        SM.track(creator ? 'Creator sign-up' : 'Referral sign-up');
        f.innerHTML = '<div class="alert ok" style="font-size:16px" role="status"><div><b>Check your email, ' + SMCards.esc(data.name.trim().split(' ')[0]) + '.</b><br>' + (creator ? 'Your code, your link and a sign-in link for your partner page' : 'Your referral link and a sign-in link for your referrals page') + ' are on their way to ' + SMCards.esc(data.email) + '. If you already signed up with that email, we’ve sent you a new sign-in link instead.</div></div>';
      })
      .catch(function (err) {
        btn.classList.remove('is-loading'); btn.textContent = creator ? 'Email me my code' : 'Email me my link';
        var ts = f.querySelector('.cf-turnstile'); if (ts && w.turnstile) w.turnstile.reset(ts);
        if (err && err.field && err.field !== 'agree' && f.elements[err.field]) { bad(err.field, err.message); return; }
        alertEl.hidden = false; alertEl.textContent = (err && err.message) || ('We couldn’t sign you up just now. Please email ' + SM.CFG.email + '.');
      });
  });
})();
