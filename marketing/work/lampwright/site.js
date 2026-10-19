
(function () {
  var t = document.querySelector('.menu-btn'), n = document.querySelector('.site-nav');
  if (t && n) t.addEventListener('click', function () { var o = n.classList.toggle('open'); t.setAttribute('aria-expanded', o); });
  document.querySelectorAll('[data-demo-form]').forEach(function (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); var note = f.querySelector('.note') || f.nextElementSibling; if (note) note.textContent = f.getAttribute('data-demo-form') || 'This is a demo, so nothing was sent.'; });
  });
  document.querySelectorAll('[data-demo]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); a.textContent = a.getAttribute('data-demo') || 'Demo only'; }); });
})();

(function () {
  var box = document.querySelector('.lightbox'); if (!box) return;
  var fig = box.querySelector('figure'), last;
  function close() { box.classList.remove('open'); fig.innerHTML = ''; if (last) last.focus(); }
  document.querySelectorAll('.gallery button').forEach(function (b) {
    b.addEventListener('click', function () { last = b; fig.innerHTML = b.querySelector('svg').outerHTML + '<figcaption>' + b.querySelector('span').textContent + '</figcaption>'; box.classList.add('open'); box.querySelector('.x').focus(); });
  });
  box.querySelector('.x').addEventListener('click', close);
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) close(); });
  document.querySelectorAll('.trailer button').forEach(function (b) { b.addEventListener('click', function () { var c = b.parentNode.querySelector('.cap'); if (c) c.textContent = 'Demo only · on a live site the trailer plays here'; }); });
})();
