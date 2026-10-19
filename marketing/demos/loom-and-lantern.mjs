// Loom & Lantern — fictional candle shop. Shows the Online store: collections, products with variants and stock,
// bundles, a working cart and a checkout that stops before payment (demo).
import { head, demoBar, navLinks, menuBtn, esc } from './common.mjs';

const NAV = [['shop.html', 'Shop all'], ['shop.html#candles', 'Candles'], ['shop.html#throws', 'Throws'], ['bundles.html', 'Bundles'], ['story.html', 'Our story']];

// slug, name, notes, mood, kind, color, bg, new, story, variants [label, price, stock]
const P = [
  ['fireside', 'Fireside', 'Smoked vanilla, amber, birch', 'cozy', 'candle', '#f2b45c', '#4a3355', 1, 'Our bestseller. Like the last hour of a campfire, without the smoke in your hair.', [['8 oz', 32, 14], ['14 oz', 46, 4]]],
  ['orchard', 'Orchard', 'Crisp apple, clove, cider', 'fresh', 'candle', '#e98a7a', '#40304c', 1, 'Bright and spiced, for the first cool weekend of the year.', [['8 oz', 28, 22], ['14 oz', 42, 9]]],
  ['cedar-and-smoke', 'Cedar & Smoke', 'Cedarwood, vetiver, black tea', 'woody', 'candle', '#8aa37b', '#3d3046', 0, 'Dry, woody and calm. The one people buy for their office.', [['8 oz', 32, 3], ['14 oz', 46, 0]]],
  ['wool-blanket', 'Wool Blanket', 'Cashmere musk, tonka, oat milk', 'cozy', 'candle', '#d9c3a5', '#45344f', 0, 'Soft and a little sweet. Smells like the good side of the couch.', [['8 oz', 28, 17], ['14 oz', 42, 11]]],
  ['linen-line', 'Linen Line', 'Cotton, white tea, sea salt', 'fresh', 'candle', '#b7c9d6', '#3b2d45', 0, 'Clean laundry on a breezy day. Light enough for a bedroom.', [['8 oz', 26, 25], ['14 oz', 40, 8]]],
  ['porch-swing', 'Porch Swing', 'Honeysuckle, fig, sweet grass', 'fresh', 'candle', '#e6d27a', '#47364f', 0, 'A Southern summer evening, poured in October so you can keep it.', [['8 oz', 26, 12], ['14 oz', 40, 6]]],
  ['hearth', 'Hearth', 'Firewood, clove, dark rum', 'woody', 'candle', '#c8693f', '#3e2c48', 1, 'Our richest scent. Best in big rooms and on cold nights.', [['8 oz', 34, 9], ['14 oz', 48, 2]]],
  ['woven-throw', 'Woven throw', 'Cotton blend, 50 × 60 in.', 'cozy', 'throw', '#c9b6c7', '#4a3355', 0, 'Woven on a small mill in North Carolina. Heavy enough to matter, light enough to wash.', [['Plum', 89, 7], ['Honey', 89, 5], ['Moss', 89, 0]]],
];
const candles = P.filter(p => p[4] === 'candle');

const css = `
:root { --plum: #2a1d33; --plum-2: #3a2947; --cream: #fbefe4; --honey: #f2b45c; --rose: #e98a7a; --moss: #8aa37b; --muted: #cdbbcb; --line: rgba(251,239,228,.16); --display: "Bricolage Grotesque", system-ui, sans-serif; --body: "Instrument Sans", system-ui, sans-serif; }
body { margin: 0; background: var(--plum); color: var(--cream); font: 16.5px/1.55 var(--body); }
.demo-bar { background: #120b17; color: var(--muted); } .demo-bar a { color: var(--honey); font-weight: 600; }
.ship { background: var(--honey); color: var(--plum); text-align: center; font-weight: 600; font-size: 14px; padding: 10px 16px; }
.ship i { display: inline-block; width: 120px; height: 6px; border-radius: 99px; background: rgba(42,29,51,.2); vertical-align: middle; margin: 0 10px; overflow: hidden; } .ship i b { display: block; height: 100%; background: var(--plum); border-radius: inherit; transition: width .3s; }
.wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; } .narrow { max-width: 760px; }
header { display: flex; align-items: center; gap: 26px; height: 80px; }
.logo { font: 800 26px/1 var(--display); letter-spacing: -.04em; text-decoration: none; } .logo span { color: var(--honey); }
.site-nav { display: flex; gap: 24px; margin: 0 auto; } .site-nav a { text-decoration: none; color: var(--muted); font-weight: 500; } .site-nav a:hover, .site-nav a[aria-current] { color: var(--cream); }
.bag { position: relative; width: 44px; height: 44px; border-radius: 50%; border: 1.5px solid rgba(251,239,228,.25); display: grid; place-items: center; background: none; color: var(--cream); cursor: pointer; flex: none; }
.bag b { position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; border-radius: 99px; background: var(--honey); color: var(--plum); font-size: 12px; display: grid; place-items: center; padding: 0 5px; }
.hero { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; padding: 30px 0 80px; }
.tag { display: inline-block; font: 600 13px/1 var(--body); letter-spacing: .1em; text-transform: uppercase; color: var(--honey); }
h1 { font: 800 clamp(52px, 8.4vw, 120px)/.88 var(--display); letter-spacing: -.05em; margin: 18px 0 24px; } h1 em { font-style: normal; color: var(--honey); }
.page-hero { padding: 20px 0 40px; } .page-hero h1 { font-size: clamp(44px, 6vw, 80px); margin: 8px 0 14px; }
.lede { font-size: 19px; color: var(--muted); max-width: 520px; } .row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 30px; align-items: center; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; padding: 0 26px; border-radius: 999px; background: var(--honey); color: var(--plum); font-weight: 600; text-decoration: none; border: 0; cursor: pointer; white-space: nowrap; flex: none; } .btn:hover { background: #f7c47a; }
.btn.line { background: transparent; color: var(--cream); box-shadow: inset 0 0 0 1.5px rgba(251,239,228,.35); } .btn.dark { background: var(--plum); color: var(--cream); } .btn[disabled] { opacity: .45; cursor: not-allowed; }
.btn.block { width: 100%; }
.shelf { position: relative; aspect-ratio: 1.05; border-radius: 32px; background: radial-gradient(circle at 50% 30%, rgba(242,180,92,.35), transparent 60%), var(--plum-2); overflow: hidden; display: flex; align-items: flex-end; justify-content: center; gap: 4%; padding-bottom: 12%; }
.shelf::after { content: ""; position: absolute; left: 6%; right: 6%; bottom: 10%; height: 14px; border-radius: 8px; background: #1d1424; }
.jar { position: relative; width: 24%; aspect-ratio: .78; border-radius: 16px 16px 20px 20px; background: linear-gradient(170deg, rgba(255,255,255,.28), rgba(255,255,255,.06)); border: 1.5px solid rgba(255,255,255,.25); z-index: 1; display: block; }
.jar::before { content: ""; position: absolute; left: 8%; right: 8%; bottom: 8%; top: 28%; border-radius: 10px; background: var(--c); }
.jar::after { content: attr(data-l); position: absolute; left: 14%; right: 14%; top: 50%; padding: 6px 4px; border-radius: 6px; background: var(--cream); color: var(--plum); font: 700 10px/1.1 var(--display); text-align: center; text-transform: uppercase; letter-spacing: .04em; }
.jar .flame { position: absolute; left: 50%; top: -26%; width: 18%; aspect-ratio: .55; border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%; background: radial-gradient(circle at 50% 70%, #fff4d1, var(--honey) 55%, #e2742b); transform: translateX(-50%); filter: drop-shadow(0 0 14px rgba(242,180,92,.9)); animation: flick 2.4s ease-in-out infinite; }
.jar.tall { width: 28%; aspect-ratio: .7; } .jar.tall .flame { top: -22%; animation-delay: .6s; } .jar.small { width: 20%; aspect-ratio: .9; } .jar.small .flame { animation-delay: 1.2s; }
.throw { width: 60%; aspect-ratio: 1.2; border-radius: 14px; background: repeating-linear-gradient(90deg, var(--c) 0 14px, rgba(255,255,255,.18) 14px 18px); box-shadow: inset 0 -18px 0 rgba(0,0,0,.18); display: block; }
@keyframes flick { 50% { transform: translateX(-50%) scale(.92, 1.06) rotate(-3deg); } }
section { padding: 70px 0; }
.head { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; flex-wrap: wrap; margin-bottom: 28px; }
h2 { font: 800 clamp(34px, 5vw, 60px)/.95 var(--display); letter-spacing: -.04em; margin: 0; } h3 { font: 700 22px/1.2 var(--display); margin: 0 0 8px; letter-spacing: -.02em; }
.chips { display: flex; gap: 8px; flex-wrap: wrap; } .chips button { min-height: 40px; padding: 0 16px; border-radius: 99px; border: 1.5px solid rgba(251,239,228,.2); background: none; color: var(--muted); cursor: pointer; } .chips button[aria-pressed="true"] { background: var(--cream); color: var(--plum); border-color: var(--cream); }
.grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
.p { background: var(--plum-2); border-radius: 24px; padding: 14px; display: flex; flex-direction: column; gap: 10px; }
.p .img { aspect-ratio: 1; border-radius: 16px; background: var(--bg); display: flex; align-items: flex-end; justify-content: center; padding-bottom: 14%; position: relative; text-decoration: none; }
.p .img .jar { width: 42%; } .p .img .jar .flame { display: none; } .p .img .throw { width: 64%; margin-bottom: 6%; }
.badges { position: absolute; top: 10px; left: 10px; right: 10px; display: flex; flex-wrap: wrap; gap: 6px; } .badge { background: var(--plum); color: var(--honey); font-size: 11px; font-weight: 600; padding: 5px 9px; border-radius: 99px; letter-spacing: .06em; text-transform: uppercase; } .badge.low { background: var(--rose); color: var(--plum); }
.p h3 { margin: 4px 4px 0; font-size: 20px; } .p h3 a { text-decoration: none; } .p .notes { margin: 0 4px; color: var(--muted); font-size: 14px; }
.p .foot { display: flex; align-items: center; justify-content: space-between; margin: auto 4px 2px; gap: 8px; } .p .foot b { font-size: 17px; } .p .foot small { color: var(--muted); font-size: 12.5px; display: block; }
.add { min-height: 40px; padding: 0 14px; border-radius: 99px; border: 0; background: var(--cream); color: var(--plum); font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; } .add.done { background: var(--moss); color: #fff; }
.pd { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: start; padding-bottom: 40px; }
.pd .img { aspect-ratio: 1; border-radius: 28px; background: var(--bg); display: flex; align-items: flex-end; justify-content: center; padding-bottom: 16%; } .pd .img .jar { width: 40%; } .pd .img .throw { width: 70%; margin-bottom: 8%; }
.price-big { font: 800 40px/1 var(--display); letter-spacing: -.03em; margin: 10px 0 20px; }
.variants { display: flex; gap: 10px; flex-wrap: wrap; margin: 8px 0 6px; } .variants label { display: block; } .variants input { position: absolute; opacity: 0; }
.variants span { display: inline-flex; flex-direction: column; padding: 10px 16px; border-radius: 14px; border: 1.5px solid var(--line); cursor: pointer; min-width: 100px; } .variants span small { color: var(--muted); font-size: 13px; }
.variants input:checked + span { border-color: var(--honey); background: rgba(242,180,92,.1); } .variants input:disabled + span { opacity: .45; text-decoration: line-through; cursor: not-allowed; } .variants input:focus-visible + span { outline: 3px solid var(--cream); }
.stock { font-size: 14px; color: var(--moss); margin: 10px 0 18px; min-height: 1.4em; } .stock.low { color: var(--honey); } .stock.out { color: var(--rose); }
.qty { display: inline-flex; align-items: center; border: 1.5px solid var(--line); border-radius: 99px; height: 52px; } .qty button { width: 44px; height: 100%; border: 0; background: none; color: var(--cream); cursor: pointer; font-size: 20px; } .qty input { width: 40px; text-align: center; background: none; border: 0; color: var(--cream); font: inherit; }
.specs { margin: 26px 0 0; } .specs div { display: flex; justify-content: space-between; border-top: 1px solid var(--line); padding: 12px 0; gap: 20px; } .specs dt { color: var(--muted); } .specs dd { margin: 0; text-align: right; }
.bundle { display: grid; grid-template-columns: 1.1fr .9fr; gap: 40px; align-items: center; background: var(--cream); color: var(--plum); border-radius: 32px; padding: 50px; }
.bundle h2 { color: var(--plum); } .bundle p { color: #5d4a63; font-size: 18px; } .bundle .price { font: 800 54px/1 var(--display); letter-spacing: -.04em; } .bundle .price s { font-size: 24px; color: #8a7590; margin-left: 10px; }
.trio { display: flex; gap: 6%; justify-content: center; align-items: flex-end; min-height: 160px; } .trio .jar { width: 26%; } .trio .jar::after { color: var(--cream); background: var(--plum); }
.picker { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; } .pick { background: var(--plum-2); border: 1.5px solid transparent; border-radius: 18px; padding: 14px; color: var(--cream); cursor: pointer; text-align: left; display: flex; flex-direction: column; gap: 6px; }
.pick[aria-pressed="true"] { border-color: var(--honey); } .pick small { color: var(--muted); } .pick b { font-family: var(--display); font-size: 18px; } .pick .n { align-self: flex-end; background: var(--honey); color: var(--plum); border-radius: 99px; font-size: 12px; font-weight: 700; padding: 2px 8px; min-height: 20px; }
.vals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; } .vals div { border-top: 1.5px solid var(--line); padding-top: 18px; } .vals b { font: 700 22px/1.2 var(--display); display: block; margin-bottom: 6px; } .vals span { color: var(--muted); }
.news { display: flex; gap: 10px; flex-wrap: wrap; } .news input { flex: 1; min-width: 220px; height: 52px; border-radius: 99px; border: 1.5px solid rgba(251,239,228,.25); background: transparent; color: var(--cream); padding: 0 20px; font: inherit; }
.note { color: var(--honey); font-size: 14px; margin-top: 10px; }
.drawer { position: fixed; inset: 0 0 0 auto; width: min(420px, 100%); background: var(--cream); color: var(--plum); z-index: 40; transform: translateX(100%); visibility: hidden; transition: transform .25s, visibility .25s; display: flex; flex-direction: column; box-shadow: -20px 0 40px rgba(0,0,0,.35); }
.drawer.on { transform: none; visibility: visible; } .drawer header { height: auto; padding: 22px 24px; border-bottom: 1px solid rgba(42,29,51,.12); justify-content: space-between; } .drawer header b { font: 800 24px/1 var(--display); }
.drawer .x { background: none; border: 0; font-size: 28px; cursor: pointer; color: var(--plum); width: 44px; height: 44px; }
.lines { flex: 1; overflow: auto; padding: 8px 24px; } .line { display: grid; grid-template-columns: 56px 1fr auto; gap: 14px; align-items: center; padding: 14px 0; border-bottom: 1px solid rgba(42,29,51,.1); }
.line .sw { width: 56px; height: 56px; border-radius: 12px; background: var(--c); } .line small { display: block; color: #6b5872; } .line .rm { background: none; border: 0; color: #6b5872; text-decoration: underline; cursor: pointer; font-size: 13px; padding: 0; }
.line .q { display: inline-flex; gap: 6px; align-items: center; margin-top: 4px; } .line .q button { width: 28px; height: 28px; border-radius: 8px; border: 1px solid rgba(42,29,51,.2); background: #fff; cursor: pointer; color: var(--plum); }
.drawer .sum { padding: 20px 24px 26px; border-top: 1px solid rgba(42,29,51,.12); display: grid; gap: 10px; } .sum .r { display: flex; justify-content: space-between; } .sum .r.t { font: 800 22px/1 var(--display); }
.empty { padding: 30px 0; color: #6b5872; }
.scrim { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 30; display: none; } .scrim.on { display: block; }
.co { display: grid; grid-template-columns: 1.2fr .8fr; gap: 40px; align-items: start; }
.panel { background: var(--plum-2); border-radius: 24px; padding: 28px; } .panel + .panel { margin-top: 16px; } .panel h3 { margin-bottom: 16px; }
.fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; } .fields .full { grid-column: 1 / -1; }
label.f { display: grid; gap: 6px; font-size: 14px; font-weight: 600; } label.f input, label.f select, label.f textarea { font: inherit; height: 48px; padding: 0 14px; border-radius: 12px; border: 1.5px solid var(--line); background: rgba(0,0,0,.15); color: var(--cream); } label.f textarea { height: auto; padding: 12px 14px; }
.opts label { display: flex; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1.5px solid var(--line); border-radius: 14px; cursor: pointer; margin-bottom: 8px; } .opts label:has(input:checked) { border-color: var(--honey); } .opts input { accent-color: var(--honey); margin-right: 10px; }
.pay { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; } .pay span { display: grid; place-items: center; height: 50px; border-radius: 12px; background: #000; color: #fff; font-weight: 600; font-size: 15px; } .pay span.card { background: rgba(255,255,255,.1); }
.done-box { background: var(--cream); color: var(--plum); border-radius: 24px; padding: 30px; } .done-box h2 { color: var(--plum); font-size: 40px; } .done-box pre { white-space: pre-wrap; background: #fff; border-radius: 14px; padding: 18px; font: 14px/1.5 var(--body); color: #3a2947; }
details.faq summary { color: var(--cream); }
footer { border-top: 1px solid var(--line); padding: 50px 0 36px; color: var(--muted); font-size: 14.5px; margin-top: 40px; }
.foot { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 28px; } .foot b { color: var(--cream); display: block; margin-bottom: 10px; font-family: var(--display); } .foot a { display: block; text-decoration: none; padding: 3px 0; } .foot a:hover { color: var(--cream); }
.foot-bottom { margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; } .foot-bottom a { color: var(--honey); }
@media (max-width: 960px) { .site-nav { background: var(--plum-2); margin: 0; } .hero, .bundle, .pd, .co { grid-template-columns: 1fr; } .grid, .picker { grid-template-columns: 1fr 1fr; } .vals, .foot { grid-template-columns: 1fr 1fr; } .bundle { padding: 30px 22px; } .wrap { padding: 0 18px; } header { justify-content: space-between; } .menu-btn { margin-left: auto; } }
@media (max-width: 520px) { .grid { gap: 12px; } .p h3 { font-size: 17px; } .p .foot { flex-direction: column; align-items: stretch; } .ship i { display: none; } .vals, .foot, .fields { grid-template-columns: 1fr; } .pay { grid-template-columns: 1fr; } }
`;

const media = (p, cls = '') => p[4] === 'throw' ? `<span class="throw ${cls}" style="--c:${p[5]}"></span>` : `<span class="jar ${cls}" data-l="${p[1].split(' ')[0]}" style="--c:${p[5]}"><span class="flame"></span></span>`;
const from = p => Math.min(...p[9].map(v => v[1]));
const stockLeft = p => p[9].reduce((n, v) => n + v[2], 0);
const card = p => `<article class="p" data-mood="${p[3]}" data-kind="${p[4]}">
  <a class="img" href="product-${p[0]}.html" style="--bg:${p[6]}" aria-label="${esc(p[1])}"><span class="badges">${p[7] ? '<span class="badge">New</span>' : ''}${p[9].some(v => v[2] > 0 && v[2] <= 4) ? '<span class="badge low">Low stock</span>' : ''}</span>${media(p)}</a>
  <h3><a href="product-${p[0]}.html">${p[1]}</a></h3><p class="notes">${p[2]}</p>
  <div class="foot"><span><b>${p[9].length > 1 && p[4] === 'candle' ? 'From ' : ''}$${from(p)}</b><small>${p[4] === 'throw' ? '3 colors' : '8 oz or 14 oz'}</small></span>${stockLeft(p) ? `<button class="add" type="button" data-add="${p[0]}">Add</button>` : '<span class="add" aria-disabled="true">Sold out</span>'}</div>
</article>`;

const DATA = `var LL_PRODUCTS = ${JSON.stringify(Object.fromEntries(P.map(p => [p[0], { name: p[1], kind: p[4], color: p[5], variants: p[9].map(([label, price, stock]) => ({ label, price, stock })) }])))};`;

const js = DATA + `
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
      var mail = 'To: ' + (f.get('email') || 'you@email.com') + '\\nSubject: Your Loom & Lantern order ' + ref + '\\n\\nHi ' + ((f.get('name') || 'there').split(' ')[0]) + ',\\n\\nThanks for your order. We’re pouring and packing it now.\\n\\n' + cart.map(function (l) { var y = line(l); return l.q + ' × ' + y.name + ' (' + y.sub + ')  ' + money(y.price * l.q); }).join('\\n') + '\\n\\nShipping (' + (x.method === 'express' ? 'Express, 2 days' : 'Standard, 3–5 days') + '): ' + (x.ship ? money(x.ship) : 'Free') + '\\nTax: ' + money(x.tax) + '\\nTotal: ' + money(x.total) + '\\n\\nWe’ll email tracking as soon as it ships.\\nLoom & Lantern';
      d.querySelector('[data-done]').hidden = false; d.querySelector('[data-done] pre').textContent = mail; co.hidden = true; d.querySelector('[data-done]').scrollIntoView({ behavior: 'smooth' }); LL.clear(); });
  }
})();
`;

const shipBar = '<div class="ship"><span data-msg>Free shipping on orders over $60</span><i><b style="width:0"></b></i></div>';
const drawer = `<div class="scrim"></div><aside class="drawer" aria-label="Cart" aria-hidden="true"><header><b>Your cart</b><button class="x" type="button" aria-label="Close cart">×</button></header><div class="lines" data-lines></div>
<div class="sum"><div class="r"><span>Subtotal</span><b data-sub>$0</b></div><small style="color:#6b5872">Shipping and tax at checkout. Free shipping over $60.</small><a class="btn dark block" href="checkout.html" data-go>Checkout</a><a href="cart.html" style="text-align:center">View cart</a></div></aside>`;
const faq = items => items.map(([q, a]) => `<details class="faq"><summary>${q}</summary><p>${a}</p></details>`).join('');
const bundleTeaser = `<section><div class="wrap"><div class="bundle"><div><span class="tag" style="color:#8a4f1f">Bundle &amp; save</span><h2 style="margin:12px 0 14px">Pick any three.</h2><p>Mix scents from the fall collection. Three 8 oz candles, ships free.</p><div class="price">$64<s>$78+</s></div><div class="row"><a class="btn dark" href="bundles.html">Build my bundle</a></div></div>
<div class="trio" aria-hidden="true">${[candles[1], candles[0], candles[2]].map(p => media(p)).join('')}</div></div></div></section>`;

const pages = [
  { file: 'index.html', title: 'Loom & Lantern · Hand-poured candles & woven throws', desc: 'Small-batch soy candles and woven throws.', home: true, body: `
<section><div class="wrap"><div class="head"><h2>New for fall</h2><a class="btn line" href="shop.html">Shop all</a></div><div class="grid">${P.filter(p => p[7]).concat([P[3]]).map(card).join('')}</div></div></section>
${bundleTeaser}
<section><div class="wrap"><div class="head"><h2>Shop by mood</h2></div><div class="grid">${[['cozy', 'Cozy', 'Vanilla, musk, amber'], ['fresh', 'Fresh', 'Apple, linen, honeysuckle'], ['woody', 'Woody', 'Cedar, firewood, vetiver'], ['throws', 'Throws', 'Woven cotton, three colors']].map(([m, n, s], i) => `<a class="p" href="shop.html${m === 'throws' ? '#throws' : ''}" style="text-decoration:none"><span class="img" style="--bg:${P[i * 2][6]}">${media(m === 'throws' ? P[7] : P.find(p => p[3] === m))}</span><h3>${n}</h3><p class="notes">${s}</p></a>`).join('')}</div></div></section>
<section><div class="wrap"><div class="vals"><div><b>Small batches</b><span>Poured by hand, about 200 at a time.</span></div><div><b>Clean burn</b><span>Soy wax, cotton wicks, phthalate-free fragrance.</span></div><div><b>Refill &amp; reuse</b><span>Send back five jars and your next candle is free.</span></div></div>
<div style="margin-top:70px;max-width:640px"><h2 style="font-size:clamp(32px,4vw,48px)">First dibs on new scents</h2><p style="color:var(--muted)">One email a month. New drops, restocks and early access.</p>
<form class="news" data-demo-form="This is a demo, so you weren’t signed up. On a live store this adds you to the email list."><input type="email" placeholder="you@email.com" aria-label="Email" required><button class="btn" type="submit">Sign up</button></form><p class="note" role="status"></p></div></div></section>` },

  { file: 'shop.html', title: 'Shop all · Loom & Lantern', desc: 'Every candle and throw, filtered by mood.', nav: 'shop.html', h1: 'Shop <em>all.</em>', lede: 'Eight scents and a woven throw. Filter by what you’re in the mood for.', body: `
<section style="padding-top:10px"><div class="wrap"><div class="head"><div class="chips" role="group" aria-label="Type">${[['all', 'Everything'], ['candles', 'Candles'], ['throws', 'Throws']].map(([k, n]) => `<button type="button" data-kind-btn="${k}" aria-pressed="false">${n}</button>`).join('')}</div>
<div class="chips" role="group" aria-label="Mood">${[['all', 'Any mood'], ['cozy', 'Cozy'], ['fresh', 'Fresh'], ['woody', 'Woody']].map(([m, n], i) => `<button type="button" data-mood="${m}" aria-pressed="${!i}">${n}</button>`).join('')}</div></div>
<div class="grid" data-collection>${P.map(card).join('')}</div></div></section>${bundleTeaser}` },

  ...P.map(p => ({ file: `product-${p[0]}.html`, title: `${p[1]} · Loom & Lantern`, desc: `${p[1]}: ${p[2]}.`, nav: 'shop.html', product: p, body: `
<section style="padding-top:10px"><div class="wrap"><div class="crumbs"><a href="index.html">Home</a><span>/</span><a href="shop.html${p[4] === 'throw' ? '#throws' : '#candles'}">${p[4] === 'throw' ? 'Throws' : 'Candles'}</a><span>/</span>${p[1]}</div>
<div class="pd"><div class="img" style="--bg:${p[6]}">${media(p)}</div>
<form data-product="${p[0]}"><span class="tag">${p[4] === 'throw' ? 'Woven throw' : p[3] + ' · soy candle'}</span><h1 style="font-size:clamp(44px,6vw,76px);margin:10px 0 6px">${p[1]}</h1><p class="lede" style="margin:0">${p[2]}</p>
<div class="price-big">$${p[9][0][1]}</div>
<fieldset style="border:0;padding:0;margin:0"><legend style="font-weight:600;margin-bottom:6px">${p[4] === 'throw' ? 'Color' : 'Size'}</legend><div class="variants">${p[9].map(([l, pr, s], i) => `<label><input type="radio" name="v" value="${i}"${i === p[9].findIndex(v => v[2] > 0) ? ' checked' : ''}${s ? '' : ' disabled'}><span>${l}<small>$${pr}${p[4] === 'candle' ? ` · ${l === '8 oz' ? '60+' : '90+'} hr` : ''}${s ? '' : ' · sold out'}</small></span></label>`).join('')}</div></fieldset>
<p class="stock" role="status"></p>
<div class="row" style="margin-top:0"><span class="qty"><button type="button" data-step="-1" aria-label="Fewer">−</button><input name="q" value="1" inputmode="numeric" aria-label="Quantity"><button type="button" data-step="1" aria-label="More">+</button></span><button class="btn" type="submit">Add to cart</button></div>
<p style="color:var(--muted);margin-top:24px">${p[8]}</p>
<dl class="specs">${p[4] === 'throw' ? '<div><dt>Size</dt><dd>50 × 60 in.</dd></div><div><dt>Material</dt><dd>Cotton blend</dd></div><div><dt>Care</dt><dd>Machine wash cold, tumble low</dd></div>' : '<div><dt>Wax</dt><dd>100% soy</dd></div><div><dt>Wick</dt><dd>Cotton, lead-free</dd></div><div><dt>Fragrance</dt><dd>Phthalate-free</dd></div><div><dt>Burn time</dt><dd>60+ hr (8 oz) · 90+ hr (14 oz)</dd></div>'}</dl></form></div></div></section>
<section><div class="wrap"><div class="head"><h2>Goes well with</h2></div><div class="grid">${P.filter(x => x !== p && (x[3] === p[3] || x[4] !== p[4])).slice(0, 4).map(card).join('')}</div></div></section>` })),

  { file: 'bundles.html', title: 'Bundles · Loom & Lantern', desc: 'Pick any three 8 oz candles for $64, shipped free.', nav: 'bundles.html', h1: 'Pick <em>any three.</em>', lede: 'Choose three 8 oz candles, repeats welcome. $64 instead of $78 or more, and it ships free.', body: `
<section style="padding-top:10px"><div class="wrap" data-bundle><div class="bundle" style="margin-bottom:24px"><div><div class="price">$64<s>$78+</s></div><p data-bundle-status role="status" style="margin:14px 0 0"></p><div class="row"><button class="btn dark" type="button" data-bundle-add disabled>Add bundle to cart</button></div></div><div class="trio" aria-live="polite"></div></div>
<div class="picker">${candles.map(p => `<button class="pick" type="button" data-pick="${p[0]}" aria-pressed="false"><span class="n"></span><b>${p[1]}</b><small>${p[2]}</small></button>`).join('')}</div></div></section>` },

  { file: 'cart.html', title: 'Cart · Loom & Lantern', desc: 'Your cart.', h1: 'Your <em>cart.</em>', body: `
<section style="padding-top:10px"><div class="wrap co"><div class="panel" style="background:var(--cream);color:var(--plum)"><div data-lines></div></div>
<div class="panel"><h3>Summary</h3><div class="sum" style="padding:0;border:0"><div class="r"><span>Subtotal</span><b data-sub>$0</b></div><small style="color:var(--muted)">Shipping and tax calculated at checkout.</small><a class="btn block" href="checkout.html" data-go>Checkout</a><a href="shop.html" style="text-align:center">Keep shopping</a></div></div></div></section>` },

  { file: 'checkout.html', title: 'Checkout · Loom & Lantern', desc: 'Demo checkout.', h1: 'Check<em>out.</em>', lede: 'This is a demo store: you can fill this in, but no payment is taken and nothing ships.', body: `
<section style="padding-top:10px"><div class="wrap">
<form class="co" data-checkout><div>
  <div class="panel"><h3>Contact</h3><div class="fields"><label class="f full">Email<input name="email" type="email" autocomplete="email" required></label><label class="f full">Full name<input name="name" autocomplete="name" required></label></div></div>
  <div class="panel"><h3>Shipping address</h3><div class="fields"><label class="f full">Address<input name="addr" autocomplete="street-address" required></label><label class="f">City<input name="city" autocomplete="address-level2" required></label><label class="f">State<input name="state" autocomplete="address-level1" required></label><label class="f">ZIP<input name="zip" autocomplete="postal-code" inputmode="numeric" required></label></div></div>
  <div class="panel"><h3>Delivery</h3><div class="opts"><label><span><input type="radio" name="ship" value="standard" checked>Standard · 3–5 business days</span><span>$7, free over $60</span></label><label><span><input type="radio" name="ship" value="express">Express · 2 business days</span><span>$14</span></label></div></div>
  <div class="panel"><h3>Payment</h3><div class="pay" aria-hidden="true"><span>Apple Pay</span><span>Google Pay</span><span class="card">Card</span></div><p style="color:var(--muted);font-size:14px;margin:12px 0 0">On a live store this is a secure Stripe checkout. In this demo, no card is asked for and nothing is charged.</p></div>
</div>
<div class="panel"><h3>Order</h3><div data-lines style="background:var(--cream);color:var(--plum);border-radius:16px;padding:0 16px;margin-bottom:16px"></div>
  <div class="sum" style="padding:0;border:0"><div class="r"><span>Subtotal</span><span data-sub>$0</span></div><div class="r"><span>Shipping</span><span data-ship>$0</span></div><div class="r"><span>Estimated tax</span><span data-tax>$0</span></div><div class="r t"><span>Total</span><span data-total>$0</span></div>
  <button class="btn block" type="submit" data-go>Place demo order</button><small style="color:var(--muted)">Demo only. No payment is taken.</small></div></div>
</form>
<div class="done-box" data-done hidden><h2>Demo order placed.</h2><p>Nothing was charged or shipped. On a live store, Stripe takes the payment, the shop gets the order, stock goes down, and the customer gets this email:</p><pre></pre><a class="btn dark" href="shop.html">Back to the shop</a></div>
</div></section>` },

  { file: 'story.html', title: 'Our story · Loom & Lantern', desc: 'How Loom & Lantern started at a weekend market.', nav: 'story.html', h1: 'Started at <em>a market stall.</em>', lede: 'Loom & Lantern is a fictional shop made for our portfolio. Here’s the story it would tell.', body: `
<section style="padding-top:10px"><div class="wrap narrow"><p style="font-size:19px">It began with a folding table at a Saturday market and twelve candles poured in a kitchen. People kept coming back for Fireside, then asking if we shipped. Now we pour about 200 at a time in a small studio, still by hand, still testing every batch in our own living room first.</p>
<p style="font-size:19px;color:var(--muted)">The throws came later: a small mill two states away weaves them for us in runs of fifty. Everything we make is meant for slow evenings at home.</p></div></section>
<section><div class="wrap"><div class="vals"><div><b>Small batches</b><span>Poured by hand, about 200 at a time.</span></div><div><b>Clean burn</b><span>Soy wax, cotton wicks, phthalate-free fragrance.</span></div><div><b>Refill &amp; reuse</b><span>Send back five jars and your next candle is free.</span></div></div></div></section>` },

  { file: 'care.html', title: 'Candle care · Loom & Lantern', desc: 'How to get the longest, cleanest burn.', nav: '', h1: 'Candle <em>care.</em>', lede: 'A few habits that double the life of a candle.', body: `
<section style="padding-top:10px"><div class="wrap narrow">${faq([
  ['The first burn matters most', 'Let the wax melt all the way to the edge the first time, about 2–3 hours. Otherwise it tunnels and you lose wax down the sides.'],
  ['Trim the wick', 'Trim to about ¼ inch before every burn. A long wick smokes and blackens the jar.'],
  ['Don’t burn more than 4 hours', 'Blow it out, let it cool for two hours, then relight.'],
  ['When to stop', 'Stop when there’s about ½ inch of wax left. Then send the jar back for our refill program.'],
])}</div></section>` },

  { file: 'shipping-returns.html', title: 'Shipping & returns · Loom & Lantern', desc: 'Shipping times, costs and returns.', nav: '', h1: 'Shipping <em>&amp; returns.</em>', lede: 'Example policy for this concept store.', body: `
<section style="padding-top:10px"><div class="wrap narrow">${faq([
  ['How much is shipping?', 'Standard shipping is $7, or free on orders over $60. Express is $14. Bundles always ship free.'],
  ['When will it arrive?', 'Orders ship in 1–2 business days. Standard takes 3–5 business days, express takes 2.'],
  ['Can I return something?', 'Unburned candles and unwashed throws can be returned within 30 days for a refund.'],
  ['It arrived broken', 'Send a photo within 7 days and we’ll send a replacement, no need to return it.'],
])}</div></section>` },

  { file: 'faq.html', title: 'FAQ · Loom & Lantern', desc: 'Common questions about our candles and throws.', nav: '', h1: 'Questions, <em>answered.</em>', body: `
<section style="padding-top:10px"><div class="wrap narrow">${faq([
  ['Are your candles safe for pets?', 'We use soy wax and phthalate-free fragrance, but always burn candles in a ventilated room and out of reach of pets.'],
  ['Do you do wholesale?', 'Yes, for shops and cafés. Send us a note through the contact page.'],
  ['How does the refill program work?', 'Return five clean jars with the prepaid label from your account and your next 8 oz candle is free.'],
  ['Can I gift wrap?', 'Yes. Add a note at checkout and we’ll wrap it in recycled paper.'],
])}</div></section>` },

  { file: 'contact.html', title: 'Contact · Loom & Lantern', desc: 'Get in touch with Loom & Lantern.', nav: '', h1: 'Say <em>hi.</em>', lede: 'Orders, wholesale or just a scent suggestion. We answer within a day.', body: `
<section style="padding-top:10px"><div class="wrap narrow"><form class="panel" data-demo-form="This is a demo, so no message was sent."><div class="fields"><label class="f">Name<input name="name" autocomplete="name" required></label><label class="f">Email<input name="email" type="email" autocomplete="email" required></label><label class="f full">Order number <small>(optional)</small><input name="order"></label><label class="f full">Message<textarea name="msg" rows="5" required></textarea></label></div><div class="row"><button class="btn" type="submit">Send message</button></div><p class="note" role="status"></p></form></div></section>` },
];

function shell(p) {
  const top = p.home ? `<div class="hero"><div><span class="tag">The fall drop · now live</span><h1>Slow evenings, <em>bottled.</em></h1><p class="lede">Hand-poured soy candles and woven throws, made in small batches. Every 8 oz candle burns for 60+ hours.</p>
    <div class="row"><a class="btn" href="shop.html#candles">Shop the fall collection</a><a class="btn line" href="bundles.html">Build a bundle</a></div></div>
    <div class="shelf" aria-hidden="true">${media(P[1], 'small')}${media(P[0], 'tall')}${media(P[2])}</div></div>`
    : p.product ? '' : `<div class="page-hero">${p.nav !== undefined || true ? `<div class="crumbs"><a href="index.html">Home</a><span>/</span>${p.title.split(' · ')[0]}</div>` : ''}<h1>${p.h1}</h1>${p.lede ? `<p class="lede">${p.lede}</p>` : ''}</div>`;
  return `${head({ title: p.title.replace(/&(?!amp;)/g, '&amp;'), desc: p.desc, fonts: 'family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Instrument+Sans:wght@400;500;600' })}
<body>
<a class="skip" href="#main">Skip to content</a>
${demoBar('loom-and-lantern', 'Loom &amp; Lantern', 'store')}
${shipBar}
<div class="wrap">
  <header>
    <a class="logo" href="index.html">Loom <span>&amp;</span> Lantern</a>
    <nav class="site-nav" aria-label="Main">${navLinks(NAV, p.nav)}</nav>${menuBtn}
    <button class="bag" type="button" aria-label="Open cart"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 8.5h16l-1.3 11.5H5.3z"/><path d="M8.5 8.5V7a3.5 3.5 0 0 1 7 0v1.5"/></svg><b data-count>0</b></button>
  </header>
  ${top}
</div>
<main id="main">
${p.body}
</main>
<footer><div class="wrap">
  <div class="foot">
    <div><b>Loom &amp; Lantern</b>Hand-poured candles &amp; woven goods, in small batches.</div>
    <div><b>Shop</b><a href="shop.html#candles">Candles</a><a href="shop.html#throws">Throws</a><a href="bundles.html">Bundles</a></div>
    <div><b>Help</b><a href="shipping-returns.html">Shipping &amp; returns</a><a href="care.html">Candle care</a><a href="faq.html">FAQ</a><a href="contact.html">Contact</a></div>
    <div><b>About</b><a href="story.html">Our story</a></div>
  </div>
  <div class="foot-bottom"><span>Fictional store. No real orders, payments or shipping.</span><span>A concept by <a href="../../work-loom-and-lantern.html">Syndicate Marketing</a></span></div>
</div></footer>
${drawer}
<script src="site.js"></script>
</body>
</html>
`;
}

export default { slug: 'loom-and-lantern', css, js, pages, shell };
