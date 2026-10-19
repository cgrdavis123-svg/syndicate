/* Syndicate Help: the “No” on Was this helpful? opens a comment box; “/” focuses search. */
(function () {
  var no = document.querySelector('[data-no]');
  if (no) no.addEventListener('click', function () { var m = document.querySelector('.w-fb-more'); m.hidden = false; no.setAttribute('aria-pressed', 'true'); m.querySelector('textarea').focus(); });
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || /input|textarea|select/i.test((document.activeElement || {}).tagName || '')) return;
    var s = document.querySelector('.w-search input'); if (s) { e.preventDefault(); s.focus(); }
  });
})();
