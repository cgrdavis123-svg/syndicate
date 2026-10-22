/* Referral program sign-up (referrals.html) → CRM shop-api.php?a=refer. */
(function () {
  'use strict';
  var d = document, w = window, f = d.getElementById('referForm');
  if (!f || !w.SM) return;
  var cash = f.querySelector('[data-cash]');
  function showCash() { if (cash) cash.hidden = !f.querySelector('input[name="payout"][value="cash"]').checked; }
  Array.prototype.forEach.call(f.querySelectorAll('input[name="payout"]'), function (r) { r.addEventListener('change', showCash); });
  showCash();
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = f.querySelector('button[type="submit"]'), alertEl = d.getElementById('referAlert');
    Array.prototype.forEach.call(f.querySelectorAll('.field'), function (x) { x.classList.remove('bad'); });
    alertEl.hidden = true;
    var data = {}; new FormData(f).forEach(function (v, k) { data[k] = v; });
    var bad = (data.name || '').trim().length < 2 ? 'name' : (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(data.email || '') ? 'email' : '');
    if (bad) { f.elements[bad].closest('.field').classList.add('bad'); f.elements[bad].focus(); return; }
    if (!data.agree) { alertEl.hidden = false; alertEl.textContent = 'Please confirm you’re 18 or older and agree to the referral rules.'; f.elements.agree.focus(); return; }
    btn.classList.add('is-loading'); btn.textContent = 'Sending…';
    fetch(SM.API + '?a=refer', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (r) {
        if (!r.ok) throw new Error(r.error || '');
        SM.track('Referral sign-up');
        f.innerHTML = '<div class="alert ok" style="font-size:16px" role="status"><div><b>Check your email, ' + SMCards.esc(data.name.trim().split(' ')[0]) + '.</b><br>Your referral link and a sign-in link for your referrals page are on their way to ' + SMCards.esc(data.email) + '. If you already signed up with that email, we’ve sent you a new sign-in link instead.</div></div>';
      })
      .catch(function (err) {
        btn.classList.remove('is-loading'); btn.textContent = 'Email me my link';
        var ts = f.querySelector('.cf-turnstile'); if (ts && w.turnstile) w.turnstile.reset(ts);
        alertEl.hidden = false; alertEl.textContent = (err && err.message) || ('We couldn’t sign you up just now. Please email ' + SM.CFG.email + '.');
      });
  });
})();
