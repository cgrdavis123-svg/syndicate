
(function () {
  var t = document.querySelector('.menu-btn'), n = document.querySelector('.site-nav');
  if (t && n) t.addEventListener('click', function () { var o = n.classList.toggle('open'); t.setAttribute('aria-expanded', o); });
  document.querySelectorAll('[data-demo-form]').forEach(function (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); var note = f.querySelector('.note') || f.nextElementSibling; if (note) note.textContent = f.getAttribute('data-demo-form') || 'This is a demo, so nothing was sent.'; });
  });
  document.querySelectorAll('[data-demo]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); a.textContent = a.getAttribute('data-demo') || 'Demo only'; }); });
})();
