/* Homepage preview: tell the CRM a real browser looked at the page (after a few seconds in view), so link scanners don't count. */
(function () {
  var s = document.currentScript, t = s && s.getAttribute('data-t'), sent = false;
  if (!t) return;
  function send() { if (sent || document.visibilityState !== 'visible') return; sent = true; try { navigator.sendBeacon ? navigator.sendBeacon('preview.php?seen=1&t=' + t) : fetch('preview.php?seen=1&t=' + t, { method: 'POST', keepalive: true }); } catch (e) {} }
  setTimeout(send, 3000);
  document.addEventListener('visibilitychange', function () { if (!sent) setTimeout(send, 3000); });
})();
