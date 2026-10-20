
(function () {
  var t = document.querySelector('.menu-btn'), n = document.querySelector('.site-nav');
  if (t && n) t.addEventListener('click', function () { var o = n.classList.toggle('open'); t.setAttribute('aria-expanded', o); });
  document.querySelectorAll('[data-demo-form]').forEach(function (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); var note = f.querySelector('.note') || f.nextElementSibling; if (note) note.textContent = f.getAttribute('data-demo-form') || 'This is a demo, so nothing was sent.'; });
  });
  document.querySelectorAll('[data-demo]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); a.textContent = a.getAttribute('data-demo') || 'Demo only'; }); });
})();
var LL_PRODUCTS = {"fireside":{"name":"Fireside","kind":"candle","color":"#f2b45c","variants":[{"label":"8 oz","price":32,"stock":14},{"label":"14 oz","price":46,"stock":4}]},"orchard":{"name":"Orchard","kind":"candle","color":"#e98a7a","variants":[{"label":"8 oz","price":28,"stock":22},{"label":"14 oz","price":42,"stock":9}]},"cedar-and-smoke":{"name":"Cedar & Smoke","kind":"candle","color":"#8aa37b","variants":[{"label":"8 oz","price":32,"stock":3},{"label":"14 oz","price":46,"stock":0}]},"wool-blanket":{"name":"Wool Blanket","kind":"candle","color":"#d9c3a5","variants":[{"label":"8 oz","price":28,"stock":17},{"label":"14 oz","price":42,"stock":11}]},"linen-line":{"name":"Linen Line","kind":"candle","color":"#b7c9d6","variants":[{"label":"8 oz","price":26,"stock":25},{"label":"14 oz","price":40,"stock":8}]},"porch-swing":{"name":"Porch Swing","kind":"candle","color":"#e6d27a","variants":[{"label":"8 oz","price":26,"stock":12},{"label":"14 oz","price":40,"stock":6}]},"hearth":{"name":"Hearth","kind":"candle","color":"#c8693f","variants":[{"label":"8 oz","price":34,"stock":9},{"label":"14 oz","price":48,"stock":2}]},"woven-throw":{"name":"Woven throw","kind":"throw","color":"#c9b6c7","variants":[{"label":"Plum","price":89,"stock":7},{"label":"Honey","price":89,"stock":5},{"label":"Moss","price":89,"stock":0}]}};
(function () {
  var FREE = 60, KEY = 'll_cart', d = document;
  function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function save(c) { try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) {} paint(); }
  var cart = load();
  function money(n) { return '$' + (Math.round(n * 100) / 100).toFixed(n % 1 ? 2 : 0); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function line(l) { if (l.bundle) return { name: 'Pick-three bundle', sub: l.bundle.map(function (s) { return LL_PRODUCTS[s].name; }).join(', '), price: 64, color: '#f2b45c' }; var p = LL_PRODUCTS[l.id], v = p.variants[l.v]; return { name: p.name, sub: v.label, price: v.price, color: p.color, max: v.stock }; }
  function totals() { var sub = 0, n = 0; cart.forEach(function (l) { sub += line(l).price * l.q; n += l.q; }); return { sub: sub, n: n }; }
  window.LL = {
    add: function (id, v, q) { v = v || 0; q = q || 1; var max = LL_PRODUCTS[id].variants[v].stock, f = cart.filter(function (l) { return l.id === id && l.v === v; })[0]; if (f) f.q = Math.min(max, f.q + q); else cart.push({ id: id, v: v, q: Math.min(max, q) }); save(cart); open(); },
    addBundle: function (ids) { cart.push({ bundle: ids, q: 1 }); save(cart); open(); },
    cart: function () { return cart; }, totals: totals, line: line, money: money, clear: function () { cart = []; save(cart); }, FREE: FREE
  };
  var drawer = d.querySelector('.drawer'), scrim = d.querySelector('.scrim');
  function open() { if (!drawer) return; drawer.classList.add('on'); scrim.classList.add('on'); drawer.setAttribute('aria-hidden', 'false'); var x = drawer.querySelector('.x'); if (x) x.focus(); }
  function close() { if (!drawer) return; drawer.classList.remove('on'); scrim.classList.remove('on'); drawer.setAttribute('aria-hidden', 'true'); }
  function paint() {
    var t = totals();
    d.querySelectorAll('[data-count]').forEach(function (b) { b.textContent = t.n; });
    var left = FREE - t.sub;
    d.querySelectorAll('.ship [data-msg]').forEach(function (m) { m.textContent = t.n === 0 ? 'Free shipping on orders over ' + money(FREE) : left > 0 ? 'You’re ' + money(left) + ' away from free shipping' : 'You’ve got free shipping'; });
    d.querySelectorAll('.ship i b').forEach(function (b) { b.style.width = Math.min(100, t.sub / FREE * 100) + '%'; });
    d.querySelectorAll('[data-lines]').forEach(function (box) {
      box.innerHTML = cart.length ? cart.map(function (l, i) { var x = line(l); return '<div class="line"><span class="sw" style="--c:' + x.color + '"></span><div><b>' + esc(x.name) + '</b><small>' + esc(x.sub) + '</small><span class="q"><button type="button" data-q="' + i + '" data-d="-1" aria-label="Fewer">−</button>' + l.q + '<button type="button" data-q="' + i + '" data-d="1" aria-label="More">+</button></span> <button class="rm" type="button" data-rm="' + i + '">Remove</button></div><b>' + money(x.price * l.q) + '</b></div>'; }).join('') : '<p class="empty">Your cart is empty.</p>';
    });
    d.querySelectorAll('[data-sub]').forEach(function (e) { e.textContent = money(t.sub); });
    d.querySelectorAll('[data-go]').forEach(function (b) { b.toggleAttribute('disabled', !cart.length); b.setAttribute('aria-disabled', !cart.length); });
    d.dispatchEvent(new Event('ll:cart'));
  }
  d.addEventListener('click', function (e) {
    var a = e.target.closest('[data-add]'), q = e.target.closest('[data-q]'), rm = e.target.closest('[data-rm]');
    if (a && !a.closest('form')) { LL.add(a.getAttribute('data-add'), 0, 1); a.textContent = 'Added'; a.classList.add('done'); }
    if (q) { var l = cart[+q.dataset.q], max = line(l).max || 9; l.q = Math.max(1, Math.min(max, l.q + +q.dataset.d)); save(cart); }
    if (rm) { cart.splice(+rm.dataset.rm, 1); save(cart); }
    if (e.target.closest('.bag')) open();
    if (e.target.closest('.x') || e.target === scrim) close();
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  window.addEventListener('storage', function (e) { if (e.key === KEY) { cart = load(); paint(); } });
  paint();

  // Collection filters (shop page): kind via #candles / #throws, mood via chips.
  var grid = d.querySelector('[data-collection]');
  if (grid) {
    var kind = (location.hash || '').slice(1), mood = 'all';
    var apply = function () { grid.querySelectorAll('.p').forEach(function (p) { p.hidden = (kind === 'candles' && p.dataset.kind !== 'candle') || (kind === 'throws' && p.dataset.kind !== 'throw') || (mood !== 'all' && p.dataset.mood !== mood); });
      d.querySelectorAll('[data-kind-btn]').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.kindBtn === (kind || 'all')); }); };
    d.querySelectorAll('[data-mood]').forEach(function (b) { if (b.tagName === 'BUTTON') b.addEventListener('click', function () { mood = b.dataset.mood; d.querySelectorAll('button[data-mood]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); apply(); }); });
    d.querySelectorAll('[data-kind-btn]').forEach(function (b) { b.addEventListener('click', function () { kind = b.dataset.kindBtn === 'all' ? '' : b.dataset.kindBtn; history.replaceState(null, '', kind ? '#' + kind : location.pathname); apply(); }); });
    window.addEventListener('hashchange', function () { kind = location.hash.slice(1); apply(); });
    apply();
  }

  // Product page: variant, stock and quantity.
  var pf = d.querySelector('[data-product]');
  if (pf) {
    var id = pf.dataset.product, P = LL_PRODUCTS[id], qty = pf.querySelector('input[name=q]');
    var show = function () { var v = +pf.querySelector('input[name=v]:checked').value, s = P.variants[v].stock, st = pf.querySelector('.stock');
      pf.querySelector('.price-big').textContent = money(P.variants[v].price); qty.max = s; if (+qty.value > s) qty.value = Math.max(1, s);
      st.className = 'stock' + (s === 0 ? ' out' : s <= 4 ? ' low' : ''); st.textContent = s === 0 ? 'Sold out. Back with the next pour.' : s <= 4 ? 'Only ' + s + ' left in this size' : 'In stock, ships in 1–2 business days';
      pf.querySelector('button[type=submit]').disabled = s === 0; };
    pf.addEventListener('change', show);
    pf.querySelectorAll('[data-step]').forEach(function (b) { b.addEventListener('click', function () { qty.value = Math.max(1, Math.min(+qty.max || 9, +qty.value + +b.dataset.step)); }); });
    pf.addEventListener('submit', function (e) { e.preventDefault(); LL.add(id, +pf.querySelector('input[name=v]:checked').value, +qty.value || 1); });
    show();
  }

  // Bundle builder: pick any three candles.
  var bb = d.querySelector('[data-bundle]');
  if (bb) {
    var picked = [], trio = bb.querySelector('.trio'), go = bb.querySelector('[data-bundle-add]'), status = bb.querySelector('[data-bundle-status]');
    var draw = function () {
      bb.querySelectorAll('.pick').forEach(function (b) { var n = picked.filter(function (x) { return x === b.dataset.pick; }).length; b.setAttribute('aria-pressed', n > 0); b.querySelector('.n').textContent = n ? '× ' + n : ''; });
      trio.innerHTML = picked.map(function (s) { var p = LL_PRODUCTS[s]; return '<span class="jar" data-l="' + esc(p.name.split(' ')[0]) + '" style="--c:' + p.color + '"></span>'; }).join('');
      status.textContent = picked.length < 3 ? 'Pick ' + (3 - picked.length) + ' more' : 'Ready. Tap a scent again to swap it out.'; go.disabled = picked.length < 3;
    };
    bb.addEventListener('click', function (e) { var b = e.target.closest('.pick'); if (!b) return; var s = b.dataset.pick, i = picked.lastIndexOf(s);
      if (picked.length >= 3 && i > -1) picked.splice(i, 1); else if (picked.length < 3) picked.push(s); else { picked.shift(); picked.push(s); } draw(); });
    go.addEventListener('click', function () { LL.addBundle(picked.slice()); picked = []; draw(); });
    draw();
  }

  // Checkout: live totals; stops before payment and shows the order email a real store would send.
  var co = d.querySelector('[data-checkout]');
  if (co) {
    var calc = function () { var t = totals(), method = (co.querySelector('input[name=ship]:checked') || {}).value, ship = method === 'express' ? 14 : (t.sub >= FREE ? 0 : 7), tax = Math.round(t.sub * 0.07 * 100) / 100;
      co.querySelector('[data-ship]').textContent = ship ? money(ship) : 'Free'; co.querySelector('[data-tax]').textContent = money(tax); co.querySelector('[data-total]').textContent = money(t.sub + ship + tax); return { ship: ship, tax: tax, total: t.sub + ship + tax, method: method }; };
    co.addEventListener('change', calc); d.addEventListener('ll:cart', calc); calc();
    co.addEventListener('submit', function (e) { e.preventDefault(); if (!cart.length) return; var x = calc(), f = new FormData(co), ref = 'LL-' + Math.floor(10000 + Math.random() * 89999);
      var mail = 'To: ' + (f.get('email') || 'you@email.com') + '\nSubject: Your Loom & Lantern order ' + ref + '\n\nHi ' + ((f.get('name') || 'there').split(' ')[0]) + ',\n\nThanks for your order. We’re pouring and packing it now.\n\n' + cart.map(function (l) { var y = line(l); return l.q + ' × ' + y.name + ' (' + y.sub + ')  ' + money(y.price * l.q); }).join('\n') + '\n\nShipping (' + (x.method === 'express' ? 'Express, 2 days' : 'Standard, 3–5 days') + '): ' + (x.ship ? money(x.ship) : 'Free') + '\nTax: ' + money(x.tax) + '\nTotal: ' + money(x.total) + '\n\nWe’ll email tracking as soon as it ships.\nLoom & Lantern';
      d.querySelector('[data-done]').hidden = false; d.querySelector('[data-done] pre').textContent = mail; co.hidden = true; d.querySelector('[data-done]').scrollIntoView({ behavior: 'smooth' }); LL.clear(); });
  }
})();
