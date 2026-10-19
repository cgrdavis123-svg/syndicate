/* Gaming communities campaign page (gaming-communities.html).
   The email link says who it's for and greets them: ?t=studio|creator|esports|business&b=Name&n=FirstName
   Everything is set as plain text; nothing from the link is treated as HTML. */
(function () {
  'use strict';
  var d = document;
  function clean(v) { return (v || '').replace(/[\u0000-\u001f<>]/g, '').replace(/\s+/g, ' ').trim().slice(0, 60); }
  var COPY = {
    studio: ['A Discord your players will ', 'actually use.', 'Wishlists, playtests, patch notes, bug reports and feedback in one place, moderated and kept active, with a monthly round-up of what players are asking for.'],
    creator: ['A community that keeps going ', 'when you’re offline.', 'Go-live alerts, member roles, clips channels and community nights, moderated between streams so you can focus on creating.'],
    esports: ['Teams, matches and fans, ', 'all in one place.', 'Team and match channels, check-ins, scrim scheduling, results and announcements, run and moderated so your staff can focus on competing.'],
    business: ['Turn one-time visitors ', 'into regulars.', 'Event sign-ups, tournament nights, drops and support in one friendly server for your LAN center, game shop or brand, moderated and kept active.']
  };
  var qs = new URLSearchParams(location.search), who = {};
  try { who = JSON.parse(sessionStorage.getItem('sm_gaming_who') || '{}') || {}; } catch (e) {}
  if (qs.get('t') || qs.get('b') || qs.get('n')) {
    who = { t: COPY[qs.get('t')] ? qs.get('t') : '', b: clean(qs.get('b')), n: clean(qs.get('n')) };
    try { sessionStorage.setItem('sm_gaming_who', JSON.stringify(who)); } catch (e) {}
  }
  var c = COPY[who.t];
  if (c) {
    var h1 = d.querySelector('[data-g="h1"]');
    if (h1) { h1.textContent = c[0]; var s = d.createElement('span'); s.className = 'serif hl'; s.textContent = c[1]; h1.appendChild(s); }
    var lede = d.querySelector('[data-g="lede"]'); if (lede) lede.textContent = c[2];
  }
  if (who.t === 'studio') { var st = d.querySelector('[data-g-studio]'); if (st) st.hidden = false; }
  if (who.b) { var g = d.querySelector('[data-g-greet]'); if (g) g.textContent = 'For ' + who.b; }
  if (who.n) { var hi = d.querySelector('[data-g-hi]'); if (hi) hi.textContent = 'Hi ' + who.n; }
  try { if (window.SM && who.t) window.SM.track('Gaming page viewed', { audience: who.t }); } catch (e) {}
})();
