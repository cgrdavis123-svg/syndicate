(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // Ask before destructive actions. Works on forms and on individual submit buttons.
  document.addEventListener('submit', e => {
    const btn = e.submitter, msg = (btn && btn.dataset.confirm) || e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Click anywhere on a table row to open it.
  $$('tr[data-href]').forEach(tr => tr.addEventListener('click', e => {
    if (e.target.closest('a, input, button, label, select')) return;
    location.href = tr.dataset.href;
  }));

  // Bulk selection on the Applicants page.
  const all = $('#checkAll'), bar = $('#bulkBar');
  if (all && bar) {
    const boxes = $$('input[name="ids[]"]');
    const sync = () => {
      const n = boxes.filter(b => b.checked).length;
      bar.hidden = n === 0;
      $('#bulkCount').textContent = n + ' selected';
      all.checked = n === boxes.length; all.indeterminate = n > 0 && n < boxes.length;
    };
    all.addEventListener('change', () => { boxes.forEach(b => { b.checked = all.checked; }); sync(); });
    boxes.forEach(b => b.addEventListener('change', sync));
  }

  // Mark a message read when it is opened.
  $$('details.msg').forEach(d => d.addEventListener('toggle', () => {
    if (d.open && d.classList.contains('unread')) {
      d.classList.remove('unread');
      history.replaceState(null, '', 'admin/messages.php?id=' + d.dataset.id);
      fetch('admin/messages.php?id=' + d.dataset.id, { credentials: 'same-origin' });
    }
  }));

  // Fade out the saved toast.
  const toast = $('.toast');
  if (toast) setTimeout(() => toast.classList.add('gone'), 3500);
})();

// Two-step sign-in: draw the authenticator QR code (assets/js/qrcode.js).
document.querySelectorAll('[data-qr]').forEach(el => {
  if (!window.qrcode) return;
  const q = window.qrcode(0, 'M'); q.addData(el.getAttribute('data-qr')); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
});
