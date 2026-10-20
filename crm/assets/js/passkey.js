// Passkeys (Face ID, Touch ID, Windows Hello, Android, security keys) for two-step sign-in.
// Buttons: [data-passkey-add] on the settings page (name from [data-passkey-name]) and [data-passkey-login] on the
// second sign-in step. Each carries data-endpoint and data-csrf. Messages go to [data-passkey-msg].
// The same file is used by the CRM and by Hiring HQ.
(function () {
  var add = document.querySelector('[data-passkey-add]'), login = document.querySelector('[data-passkey-login]');
  if (!add && !login) return;
  var msg = document.querySelector('[data-passkey-msg]');
  function say(t, bad) { if (msg) { msg.textContent = t; msg.hidden = !t; msg.classList.toggle('bad', !!bad); } }
  if (!window.PublicKeyCredential || !navigator.credentials) {
    [add, login].forEach(function (b) { if (b) b.disabled = true; });
    say('This browser can’t use passkeys. Try Safari, Chrome, Edge or Firefox on an up-to-date device.', true);
    return;
  }
  function bin(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; var r = atob(s), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a.buffer; }
  function b64u(buf) { var a = new Uint8Array(buf), s = ''; for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
  function call(btn, data) {
    data.csrf = btn.getAttribute('data-csrf');
    return fetch(btn.getAttribute('data-endpoint'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' }, body: new URLSearchParams(data) })
      .then(function (r) { return r.json().catch(function () { throw new Error('Something went wrong. Refresh the page and try again.'); }); })
      .then(function (j) { if (j.error) throw new Error(j.error); return j; });
  }
  function why(e) {
    if (e && e.name === 'NotAllowedError') return 'Cancelled, or it timed out. Try again when you’re ready.';
    if (e && e.name === 'InvalidStateError') return 'This device already has a passkey for your account.';
    return (e && e.message) || 'That didn’t work. Try again.';
  }
  // Same page with only a different #hash wouldn't reload, so reload instead.
  function go(to) { var u = new URL(to, location.href); if (u.pathname === location.pathname && u.search === location.search) { location.hash = u.hash; location.reload(); } else location.href = u.href; }
  function busy(btn, on) { btn.disabled = on; btn.setAttribute('aria-busy', on ? 'true' : 'false'); }

  if (add) add.addEventListener('click', function () {
    var nameEl = document.querySelector('[data-passkey-name]');
    busy(add, true); say('');
    call(add, { a: 'reg_options' }).then(function (o) {
      o.challenge = bin(o.challenge); o.user.id = bin(o.user.id);
      o.excludeCredentials = (o.excludeCredentials || []).map(function (c) { return { type: c.type, id: bin(c.id) }; });
      return navigator.credentials.create({ publicKey: o });
    }).then(function (cred) {
      return call(add, { a: 'reg_finish', name: nameEl ? nameEl.value : '', client: b64u(cred.response.clientDataJSON), att: b64u(cred.response.attestationObject) });
    }).then(function (j) { go(j.to); })
      .catch(function (e) { busy(add, false); say(why(e), true); });
  });

  function signIn() {
    busy(login, true); say('');
    call(login, { a: 'login_options' }).then(function (o) {
      o.challenge = bin(o.challenge);
      o.allowCredentials = (o.allowCredentials || []).map(function (c) { return { type: c.type, id: bin(c.id) }; });
      return navigator.credentials.get({ publicKey: o });
    }).then(function (cred) {
      return call(login, { a: 'login_finish', id: b64u(cred.rawId), client: b64u(cred.response.clientDataJSON), auth: b64u(cred.response.authenticatorData), sig: b64u(cred.response.signature) });
    }).then(function (j) { go(j.to); })
      .catch(function (e) { busy(login, false); say(why(e), true); });
  }
  if (login) login.addEventListener('click', signIn);
})();
