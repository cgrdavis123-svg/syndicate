// Fernhollow Interactive — fictional indie game studio. Shows a Business website (9 pages incl. news posts) for a studio:
// games, team, news, careers with pay ranges, press and contact, linking out to its game site (Lampwright).
// No reviews, awards, sales numbers or press quotes: none would be true.
import { head, demoBar, navLinks, menuBtn, esc } from './common.mjs';
import { scene, logo as lampLogo } from './lampwright.mjs';

const NAV = [['games.html', 'Games'], ['studio.html', 'Studio'], ['news.html', 'News'], ['careers.html', 'Careers'], ['press.html', 'Press']];
const demo = (label, cls = 'btn', note = 'Demo only') => `<a class="${cls}" href="#" data-demo="${note}">${label}</a>`;

// Key art for Mossletter: a mossy tile board with a winding path, little houses and a letter on its way.
function mossArt(id, { w = 1600, h = 900, label = 'Mossletter key art: a mossy village board with a letter on its way' } = {}) {
  const p = id, tiles = [];
  const greens = ['#9cc58a', '#86b678', '#b4d39b', '#7aa86c', '#a9cc8f'];
  for (let r = 0; r < 7; r++) for (let c = 0; c < 9; c++) {
    const x = 220 + c * 130 + (r % 2) * 65, y = 140 + r * 92, k = (r * 7 + c * 3) % greens.length;
    const path = (r === 3 && c > 0 && c < 8) || (c === 2 && r > 0 && r < 3) || (c === 6 && r > 3 && r < 6);
    tiles.push(`<rect x="${x}" y="${y}" width="118" height="80" rx="20" fill="${path ? '#f1e3c4' : greens[k]}" stroke="#5e8c51" stroke-opacity=".35" stroke-width="3"/>`);
    if (!path && (r * c) % 5 === 1) tiles.push(`<circle cx="${x + 34}" cy="${y + 34}" r="14" fill="#5e8c51"/><circle cx="${x + 56}" cy="${y + 26}" r="10" fill="#4c7a43"/>`);
  }
  const house = (x, y, roof) => `<g><rect x="${x}" y="${y}" width="74" height="56" rx="10" fill="#fff6e6" stroke="#3a4a33" stroke-width="4"/><path d="M${x - 10} ${y + 6}l47-38 47 38z" fill="${roof}" stroke="#3a4a33" stroke-width="4" stroke-linejoin="round"/><rect x="${x + 28}" y="${y + 26}" width="18" height="30" rx="6" fill="#3a4a33"/></g>`;
  return `<svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${esc(label)}" preserveAspectRatio="xMidYMid slice">
<defs><linearGradient id="${p}b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e9f2dc"/><stop offset="1" stop-color="#cfe2c0"/></linearGradient></defs>
<rect width="${w}" height="${h}" fill="url(#${p}b)"/>
<circle cx="1380" cy="150" r="90" fill="#ffe7a8" opacity=".8"/>
${tiles.join('')}
${house(350, 236, '#d4573b')}${house(980, 420, '#e9a23b')}${house(1210, 236, '#6b8fd4')}${house(560, 610, '#b06ab3')}
<g transform="translate(720 360) rotate(-8)"><rect width="150" height="100" rx="12" fill="#fffdf7" stroke="#3a4a33" stroke-width="5"/><path d="M6 10l69 50 69-50" fill="none" stroke="#3a4a33" stroke-width="5" stroke-linejoin="round"/><circle cx="75" cy="62" r="16" fill="#d4573b"/></g>
<path d="M640 470c40-60 90-80 140-60" stroke="#3a4a33" stroke-width="4" stroke-dasharray="4 14" stroke-linecap="round" fill="none"/>
<g transform="translate(560 470)"><ellipse cx="40" cy="44" rx="44" ry="14" fill="#3a4a33" opacity=".15"/><path d="M8 40c0-22 18-36 38-36s32 16 32 32c0 6-4 8-10 8H14c-4 0-6-2-6-4z" fill="#c98a5b" stroke="#3a4a33" stroke-width="4"/><path d="M30 20c8-6 22-4 26 6s-4 16-12 14-8-10-2-12" fill="none" stroke="#3a4a33" stroke-width="4" stroke-linecap="round"/><path d="M70 20l6-14M78 22l12-8" stroke="#3a4a33" stroke-width="4" stroke-linecap="round"/></g>
</svg>`;
}
const lampArt = (id, label) => scene({ id, seed: 3, eyes: 3, label: label || 'Lampwright key art: a lampwright with a lantern above a city at night' });

const GAMES = [
  { slug: 'lampwright', name: 'Lampwright', status: 'Coming 2027', chip: 'In development', art: lampArt, genre: 'Co-op roguelite · 1–4 players', platforms: 'PC (Steam); consoles planned',
    short: 'The night is alive, and you’re the last one who knows how to make light. Craft lanterns, win the city back, hold out until dawn.',
    long: 'Our biggest game yet. Every run is one long night across a city that rebuilds itself each time. Salvage glass, craft lanterns with strange properties, and decide which streets to save before the oil runs out. Solo, or with up to three friends online or on one couch.',
    points: ['Closed playtest this winter', 'Online and couch co-op', 'Its own site with trailer, media and press kit'] },
  { slug: 'mossletter', name: 'Mossletter', status: 'Out now', chip: 'Released', art: mossArt, genre: 'Cozy puzzle · 1 player', platforms: 'PC and Mac',
    short: 'A tiny courier snail, a mossy village, and a bag full of letters. Lay the paths, deliver the mail, learn everyone’s business.',
    long: 'Our first game. Each day you rearrange the village’s tiles to get every letter where it needs to go, and the letters tell a slow, small story about the neighbors. No timers and no way to fail; just puzzles that get cleverer as the village grows.',
    points: ['About 6 hours, 120 puzzles', 'Free Winter Route update', 'Full controller support'] },
];

const TEAM = [
  ['Ada Kwan', 'Creative director', 'Started Fernhollow after a decade making other people’s games.', '#2f6b3a'],
  ['Theo Marsh', 'Lead programmer', 'Owns the co-op netcode and most of the bugs, by his count.', '#d4573b'],
  ['Priya Sol', 'Art lead', 'Paints every lantern by hand before it becomes a model.', '#e9a23b'],
  ['Jonah Reyes', 'Game designer', 'Writes the puzzles, then makes them harder.', '#6b8fd4'],
  ['Mae Okafor', 'Audio', 'Records the city at night for Lampwright’s soundscape.', '#b06ab3'],
  ['Sam Whit', 'Producer', 'Keeps the schedule honest and the four-day week intact.', '#3a8f86'],
  ['Lu Brandt', 'Community & QA', 'Runs playtests and reads every bug report.', '#8a6d3b'],
];

const POSTS = [
  ['lampwright-playtest', 'Lampwright', 'Lampwright playtest sign-ups are open', 'The closed playtest starts this winter, in waves. Here’s what’s in it and how to get a key.', 'October 14', [
    'We’ve been playing Lampwright on our own for two years. It’s time to see what happens when other people get their hands on it.',
    '## What’s in the playtest', 'About two hours of the game: the first three districts of Varrow, the first five kinds of glass and a full night from dusk to dawn. Solo and co-op are both open, online and on one screen.',
    '## How to get a key', 'Sign up on the Lampwright site and tell us how you like to play. We’ll send keys by email in waves, starting with the Discord. Your saves won’t carry over to launch, and that’s on purpose: we’ll be changing a lot.',
    '## What we want from you', 'Honest reports. If something is confusing, unfair or boring, we want to hear it. There’s a feedback form in the game and a bug channel in the Discord, and Lu reads every one.']],
  ['mossletter-winter-route', 'Mossletter', 'Mossletter 1.3: the Winter Route', 'A free update with 20 new puzzles, snow tiles and a few letters you might have been waiting for.', 'September 2', [
    'The village is getting colder. Mossletter’s free Winter Route update adds a new end-of-year chapter to the game.',
    '## What’s new', '20 new puzzles built around snow tiles, which slide when you place them next to each other. Six new letters that pick up where some of the neighbors’ stories left off. A warmer coat for the snail.',
    '## Fixes', 'Controller cursor no longer gets stuck on the edge of the board. Undo now works after a delivery. Colorblind palettes cover the new tiles.',
    '## How to get it', 'It downloads automatically. If you finished the main game, the Winter Route opens from the village map.']],
];
const prose = parts => parts.map(t => t.startsWith('## ') ? `<h2>${t.slice(3)}</h2>` : `<p>${t}</p>`).join('');

const css = `
:root { --cream: #f6f1e6; --paper: #fffcf5; --ink: #1f2a1d; --fern: #2f6b3a; --fern-2: #1e4a27; --moss: #c9ddb3; --berry: #d4573b; --sun: #e9a23b; --line: #e2dacb; --muted: #5d6656; --display: "Fraunces", Georgia, serif; --body: "Bricolage Grotesque", system-ui, sans-serif; }
html, body { overflow-x: clip; } body { margin: 0; background: var(--cream); color: var(--ink); font: 17px/1.6 var(--body); }
.demo-bar { background: var(--ink); color: #e4e9dc; } .demo-bar a { color: #c9ddb3; font-weight: 700; }
.wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
header { display: flex; align-items: center; gap: 28px; height: 80px; }
.logo { display: flex; align-items: center; gap: 12px; text-decoration: none; font: 700 23px/1 var(--display); letter-spacing: -.01em; } .logo small { display: block; font: 600 11px/1 var(--body); letter-spacing: .2em; text-transform: uppercase; color: var(--muted); margin-top: 4px; }
.site-nav { display: flex; gap: 6px; margin-left: auto; } .site-nav a { text-decoration: none; font-weight: 600; padding: 8px 14px; border-radius: 999px; } .site-nav a:hover { background: var(--moss); } .site-nav a[aria-current="page"] { background: var(--ink); color: var(--cream); text-decoration: none; }
.btn { display: inline-flex; align-items: center; gap: 8px; background: var(--fern); color: #fff; padding: 14px 24px; border-radius: 14px; text-decoration: none; font-weight: 700; border: 0; cursor: pointer; box-shadow: 0 4px 0 var(--fern-2); transition: transform .15s, box-shadow .15s; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 6px 0 var(--fern-2); } .btn:active { transform: translateY(2px); box-shadow: 0 2px 0 var(--fern-2); }
.btn.alt { background: var(--paper); color: var(--ink); box-shadow: 0 4px 0 var(--line), inset 0 0 0 2px var(--ink); } .btn.sm { padding: 10px 16px; font-size: 15px; }
h1, h2, h3 { font-family: var(--display); font-weight: 700; line-height: 1.05; letter-spacing: -.02em; margin: 0 0 16px; font-variation-settings: "SOFT" 100, "WONK" 1; }
h1 { font-size: clamp(46px, 7vw, 88px); } h2 { font-size: clamp(32px, 4vw, 50px); } h3 { font-size: 24px; } h1 em, h2 em { font-style: italic; color: var(--fern); }
.kicker { display: inline-flex; align-items: center; gap: 8px; font: 700 13px/1 var(--body); letter-spacing: .16em; text-transform: uppercase; color: var(--berry); margin-bottom: 14px; }
.lede { font-size: 21px; max-width: 640px; color: #3b4637; } .sub { color: var(--muted); max-width: 640px; margin: 0 0 36px; }
section { padding: 92px 0; } .tint { background: var(--paper); border-block: 1px solid var(--line); } .dark { background: var(--fern-2); color: #eef3e6; } .dark .sub, .dark p { color: #c4d4b8; } .dark .kicker { color: var(--sun); }
.row { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 30px; }
.hero { display: grid; grid-template-columns: 1.05fr 1fr; gap: 48px; align-items: center; padding-top: 56px; padding-bottom: 96px; }
.carts { position: relative; height: 520px; } .cart { position: absolute; width: 78%; border-radius: 22px; overflow: hidden; border: 3px solid var(--ink); box-shadow: 10px 10px 0 var(--ink); background: var(--paper); text-decoration: none; }
.cart svg { display: block; width: 100%; height: auto; aspect-ratio: 16/10; } .cart span { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; font-weight: 700; border-top: 3px solid var(--ink); } .cart small { font-weight: 600; font-size: 13px; padding: 4px 10px; border-radius: 999px; background: var(--moss); }
.cart.a { top: 0; right: 0; transform: rotate(2.5deg); z-index: 2; } .cart.b { bottom: 0; left: 0; transform: rotate(-3deg); } .cart:hover { z-index: 3; }
.game { display: grid; grid-template-columns: 1.15fr 1fr; gap: 0; background: var(--paper); border: 3px solid var(--ink); border-radius: 26px; overflow: hidden; box-shadow: 8px 8px 0 var(--ink); }
.game + .game { margin-top: 40px; } .game .art svg { display: block; width: 100%; height: 100%; min-height: 340px; } .game .info { padding: 36px; display: flex; flex-direction: column; gap: 6px; } .game .info p { color: #3b4637; margin: 0 0 8px; }
.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; } .chip { font-size: 13px; font-weight: 700; padding: 5px 12px; border-radius: 999px; background: var(--moss); } .chip.live { background: var(--berry); color: #fff; } .chip.soon { background: var(--sun); }
.checks { list-style: none; padding: 0; margin: 10px 0 0; display: grid; gap: 8px; } .checks li { padding-left: 30px; position: relative; } .checks li::before { content: ""; position: absolute; left: 0; top: 6px; width: 16px; height: 16px; border-radius: 6px; background: var(--fern); box-shadow: inset 0 0 0 4px var(--moss); }
.vals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; } .val { background: var(--paper); border: 2px solid var(--line); border-radius: 22px; padding: 28px; } .val b { font: 700 46px/1 var(--display); color: var(--berry); display: block; margin-bottom: 10px; } .val p { margin: 0; color: var(--muted); }
.posts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; } .post { background: var(--paper); border: 2px solid var(--line); border-radius: 20px; padding: 24px; text-decoration: none; display: flex; flex-direction: column; gap: 8px; transition: border-color .2s, transform .2s; } .post:hover { border-color: var(--ink); transform: translateY(-3px); }
.post small { color: var(--muted); font-weight: 600; } .post h3 { font-size: 22px; margin: 0; } .post p { margin: 0; color: var(--muted); font-size: 15.5px; } .post .tagline { color: var(--berry); font-weight: 700; font-size: 13px; letter-spacing: .1em; text-transform: uppercase; }
.community { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: center; }
.panel { background: var(--paper); border: 3px solid var(--ink); border-radius: 22px; padding: 30px; box-shadow: 8px 8px 0 var(--ink); color: var(--ink); } .panel p { color: var(--muted); }
form.inline { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; } form.inline label { flex: 1 1 220px; }
input, select, textarea { font: inherit; color: var(--ink); background: #fff; border: 2px solid #cfc6b4; border-radius: 12px; padding: 12px 14px; width: 100%; } input:focus, select:focus, textarea:focus { outline: none; border-color: var(--fern); box-shadow: 0 0 0 4px rgba(47,107,58,.18); }
label { display: grid; gap: 6px; font-weight: 700; font-size: 14px; } .note { color: var(--berry); font-weight: 700; font-size: 14px; margin: 8px 0 0; width: 100%; }
form.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; } form.grid .full { grid-column: 1 / -1; }
.team { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; } .person { background: var(--paper); border: 2px solid var(--line); border-radius: 20px; padding: 22px; } .av { width: 64px; height: 64px; border-radius: 20px; display: grid; place-items: center; color: #fff; font: 700 24px/1 var(--display); margin-bottom: 14px; transform: rotate(-4deg); }
.person b { display: block; font-size: 18px; } .person em { font-style: normal; color: var(--berry); font-weight: 700; font-size: 14px; } .person p { margin: 8px 0 0; color: var(--muted); font-size: 15px; }
.split { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; }
.role { background: var(--paper); border: 2px solid var(--ink); border-radius: 22px; padding: 30px; display: grid; grid-template-columns: 1fr auto; gap: 18px 32px; align-items: start; } .role + .role { margin-top: 20px; }
.role .pay { font: 700 22px/1.2 var(--display); text-align: right; } .role .pay small { display: block; font: 600 13px/1.3 var(--body); color: var(--muted); } .role p { margin: 0; color: #3b4637; } .role .meta { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 0; }
.steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; counter-reset: s; } .steps div { background: var(--paper); border: 2px solid var(--line); border-radius: 18px; padding: 22px; counter-increment: s; } .steps div::before { content: counter(s); display: grid; place-items: center; width: 36px; height: 36px; border-radius: 12px; background: var(--ink); color: var(--cream); font: 700 18px/1 var(--display); margin-bottom: 12px; } .steps b { display: block; } .steps span { color: var(--muted); font-size: 15px; }
.facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; border: 2px solid var(--ink); border-radius: 20px; overflow: hidden; background: var(--paper); } .facts div { padding: 20px 22px; border-right: 2px solid var(--line); border-bottom: 2px solid var(--line); } .facts dt { font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); font-weight: 700; } .facts dd { margin: 4px 0 0; font-weight: 600; }
.logos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; } .logos div { border-radius: 18px; padding: 34px 16px; display: grid; place-items: center; gap: 10px; border: 2px solid var(--line); font: 700 22px/1 var(--display); } .logos small { font: 600 12px/1 var(--body); letter-spacing: .1em; text-transform: uppercase; opacity: .7; }
.dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; } .dl a { display: flex; justify-content: space-between; gap: 12px; padding: 16px 18px; background: var(--paper); border: 2px solid var(--line); border-radius: 14px; text-decoration: none; font-weight: 700; } .dl a small { color: var(--muted); font-weight: 600; } .dl a:hover { border-color: var(--ink); }
.page-hero { padding-top: 48px; padding-bottom: 24px; } .page-hero .lede { margin: 0; }
article.prose { max-width: 720px; } .prose h2 { font-size: 30px; margin-top: 44px; } .prose p { color: #3b4637; } .post-meta { color: var(--muted); font-weight: 600; margin-bottom: 30px; }
.post-art { border-radius: 24px; overflow: hidden; border: 3px solid var(--ink); margin-bottom: 36px; } .post-art svg { display: block; width: 100%; height: auto; aspect-ratio: 16/8; }
.cta-band { background: var(--berry); color: #fff; border-radius: 28px; padding: 44px; display: flex; gap: 24px; justify-content: space-between; align-items: center; flex-wrap: wrap; } .cta-band h2 { margin: 0; } .cta-band p { margin: 8px 0 0; color: #ffe2d9; } .cta-band .btn { background: var(--ink); box-shadow: 0 4px 0 #000; }
footer { background: var(--ink); color: #b9c4ae; padding: 60px 0 40px; font-size: 14.5px; }
.foot { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 28px; } .foot b { color: #fff; display: block; margin-bottom: 10px; } .foot a { display: block; text-decoration: none; padding: 3px 0; } .foot a:hover { color: #fff; }
.foot-bottom { margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,.12); display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; } .foot-bottom a { color: var(--moss); }
@media (max-width: 900px) {
  header { height: 68px; } .site-nav { background: var(--paper); border-bottom: 2px solid var(--ink); margin: 0; } .site-nav a { padding: 10px 14px; } .wrap { padding: 0 18px; } section { padding: 64px 0; }
  .hero, .game, .community, .split { grid-template-columns: 1fr; gap: 32px; } .hero { padding-top: 32px; padding-bottom: 64px; } .carts { height: 400px; margin-right: 10px; } .cart { width: 80%; }
  .vals, .posts, .steps, .foot { grid-template-columns: 1fr 1fr; } .team { grid-template-columns: 1fr 1fr; } .facts { grid-template-columns: 1fr 1fr; } .game .art svg { min-height: 0; aspect-ratio: 16/10; }
}
@media (max-width: 560px) { .vals, .posts, .steps, .foot, .team, .facts, .logos, .dl, form.grid { grid-template-columns: 1fr; } .carts { height: 300px; } .role { grid-template-columns: 1fr; } .role .pay { text-align: left; } .lede { font-size: 18px; } .cta-band, .panel, .game .info { padding: 26px 22px; } }
`;

const leaf = `<svg viewBox="0 0 44 44" width="44" height="44" aria-hidden="true"><rect width="44" height="44" rx="13" fill="#2f6b3a"/><path d="M22 36V14" stroke="#f6f1e6" stroke-width="2.6" stroke-linecap="round"/><path d="M22 30c-6-1-9-4-10-8 5 0 8 3 10 8Zm0-6c6-1 9-4 10-8-5 0-8 3-10 8Zm0-6c-4-1-6-3-7-6 4 0 6 2 7 6Z" fill="#c9ddb3"/><circle cx="31" cy="11" r="3" fill="#e9a23b"/></svg>`;
const gameLinks = g => g.slug === 'lampwright'
  ? `<a class="btn" href="../lampwright/index.html">Visit the Lampwright site</a>${demo('Wishlist on Steam', 'btn alt', 'Demo only · no store page')}`
  : `${demo('Buy on Steam', 'btn', 'Demo only · no store page')}${demo('Buy on itch.io', 'btn alt', 'Demo only · no store page')}`;
const newsletter = `<form class="inline" data-demo-form="This is a demo, so you weren’t signed up. On a live site this joins the studio list and Site Stats counts a “Newsletter signup” goal."><label>Your email<input type="email" name="email" autocomplete="email" required placeholder="you@example.com"></label><button class="btn" type="submit" style="align-self:end">Subscribe</button><p class="note" role="status"></p></form>`;
const postCard = ([s, tag, t, d, date]) => `<a class="post" href="news-${s}.html"><span class="tagline">${tag}</span><h3>${t}</h3><p>${d}</p><small>${date}</small></a>`;

const pages = [
  { file: 'index.html', title: 'Fernhollow Interactive · Small games with big hearts', desc: 'An independent game studio making cozy, strange games. Makers of Mossletter and the upcoming co-op game Lampwright.', home: true, body: `
    <div class="wrap hero">
      <div><span class="kicker">Independent game studio · Est. 2021</span>
        <h1>Small games with <em>big hearts.</em></h1>
        <p class="lede">We’re seven people making cozy, strange games from our kitchen tables. Our first game is out now. Our second has lanterns.</p>
        <div class="row"><a class="btn" href="games.html">See our games</a><a class="btn alt" href="../lampwright/index.html">Meet Lampwright</a></div></div>
      <div class="carts">
        <a class="cart a" href="../lampwright/index.html">${lampArt('ca')}<span>Lampwright <small style="background:#e9a23b">Coming 2027</small></span></a>
        <a class="cart b" href="games.html#mossletter">${mossArt('cb')}<span>Mossletter <small>Out now</small></span></a>
      </div>
    </div>
    <section class="tint"><div class="wrap">
      <span class="kicker">Our games</span><h2>Two worlds, so far</h2><p class="sub">One cozy puzzle game about the mail. One co-op game about keeping the dark away.</p>
      ${GAMES.map(g => `<div class="game" id="${g.slug}"><div class="art">${g.art(`g${g.slug}`)}</div><div class="info"><div class="chips"><span class="chip ${g.chip === 'Released' ? 'live' : 'soon'}">${g.status}</span><span class="chip">${g.genre}</span></div><h3 style="font-size:36px">${g.name}</h3><p>${g.short}</p><div class="row" style="margin-top:auto">${gameLinks(g)}</div></div></div>`).join('')}
    </div></section>
    <section><div class="wrap">
      <span class="kicker">How we work</span><h2>Slow on purpose</h2><p class="sub">We’d rather make fewer games and make them well.</p>
      <div class="vals"><div class="val"><b>7</b><h3>People, fully remote</h3><p>Spread across four time zones, with a shared studio day once a month.</p></div><div class="val"><b>4</b><h3>Day work week</h3><p>Crunch makes worse games. We plan around it instead.</p></div><div class="val"><b>∞</b><h3>Playtests</h3><p>Everything we make goes in front of players early and often.</p></div></div>
    </div></section>
    <section class="tint"><div class="wrap">
      <span class="kicker">News</span><h2>From the studio</h2>
      <div class="posts">${POSTS.map(postCard).join('')}<a class="post" href="news.html"><span class="tagline">Studio</span><h3>Everything else we’ve written</h3><p>Devlogs, updates and the occasional behind-the-scenes post.</p><small>All news →</small></a></div>
    </div></section>
    <section class="dark"><div class="wrap community">
      <div><span class="kicker">Hang out with us</span><h2 style="color:#fff">Come say hello</h2><p>Our Discord is where playtests start, where we share work in progress and where players help each other. It’s moderated every day and it has very good emoji.</p><div class="row">${demo('Join the Discord', 'btn', 'Demo only · no invite link')}</div></div>
      <div class="panel"><h3>The Fernhollow letter</h3><p style="margin:0">News about our games, about once a month. No spam, unsubscribe any time.</p>${newsletter}</div>
    </div></section>
    <section><div class="wrap"><div class="cta-band"><div><h2>We’re hiring</h2><p>A gameplay programmer and a part-time community manager. Pay ranges listed on every role.</p></div><a class="btn" href="careers.html">See open roles</a></div></div></section>` },

  { file: 'games.html', title: 'Games · Fernhollow Interactive', desc: 'Mossletter, out now on PC and Mac, and Lampwright, a co-op roguelite coming 2027.', nav: 'games.html',
    kicker: 'Games', h1: 'Our <em>games</em>', lede: 'Everything we’ve made and what we’re making next.',
    body: `<section style="padding-top:24px"><div class="wrap">
      ${GAMES.map(g => `<div class="game" id="${g.slug}"><div class="art">${g.art(`d${g.slug}`)}</div><div class="info"><div class="chips"><span class="chip ${g.chip === 'Released' ? 'live' : 'soon'}">${g.status}</span><span class="chip">${g.chip}</span></div><h2>${g.name}</h2><p>${g.long}</p>
        <dl class="facts" style="grid-template-columns:1fr;margin:8px 0 12px"><div><dt>Genre</dt><dd>${g.genre}</dd></div><div><dt>Platforms</dt><dd>${g.platforms}</dd></div></dl>
        <ul class="checks">${g.points.map(p => `<li>${p}</li>`).join('')}</ul><div class="row">${gameLinks(g)}</div></div></div>`).join('')}
    </div></section>
    <section class="tint"><div class="wrap split">
      <div><span class="kicker">Making videos?</span><h2>Stream any of our games</h2><p class="sub" style="margin:0">You can stream, record and monetize all of our games, no permission needed. Want a key? Ask on the press page.</p></div>
      <div class="row" style="margin:0"><a class="btn" href="press.html">Press &amp; creators</a><a class="btn alt" href="../lampwright/presskit.html">Lampwright press kit</a></div>
    </div></section>` },

  { file: 'studio.html', title: 'Studio · Fernhollow Interactive', desc: 'Who we are: seven people, fully remote, making small games with big hearts.', nav: 'studio.html',
    kicker: 'Studio', h1: 'Seven people, <em>one hollow</em>', lede: 'Fernhollow started in 2021 with two people and a game about a snail who delivers the mail. We’re a few more people now, and the snail is still around.',
    body: `<section style="padding-top:24px"><div class="wrap">
      <div class="team">${TEAM.map(([n, r, b, c]) => `<div class="person"><div class="av" style="background:${c}">${n.split(' ').map(x => x[0]).join('')}</div><b>${n}</b><em>${r}</em><p>${b}</p></div>`).join('')}
        <div class="person" style="border-style:dashed;display:grid;place-items:center;text-align:center"><div><b>You?</b><p>We’re hiring a gameplay programmer.</p><p><a href="careers.html">Open roles →</a></p></div></div></div>
      <p class="illus" style="margin-top:16px">Everyone here is fictional, made up for this concept site.</p>
    </div></section>
    <section class="tint"><div class="wrap split">
      <div><span class="kicker">How we make games</span><h2>Small team, <em>long runway</em></h2><p class="sub">We fund our games with what our last game earned and a small amount of outside funding, so we can take the time a game needs.</p>
        <ul class="checks"><li>Four-day weeks, no crunch, ever</li><li>Players see the game early through open playtests</li><li>Accessibility built in from the first prototype</li><li>Everyone gets credited, by name, in every game</li></ul></div>
      <div class="post-art" style="margin:0">${mossArt('sm')}</div>
    </div></section>
    <section><div class="wrap"><div class="cta-band"><div><h2>Want to work together?</h2><p>Publishing, porting, events or just saying hello.</p></div><a class="btn" href="contact.html">Get in touch</a></div></div></section>` },

  { file: 'news.html', title: 'News · Fernhollow Interactive', desc: 'Updates, devlogs and announcements from Fernhollow Interactive.', nav: 'news.html',
    kicker: 'News', h1: 'News &amp; <em>devlogs</em>', lede: 'What we’re working on, what we’ve shipped and why.',
    body: `<section style="padding-top:24px"><div class="wrap">
      <div class="posts">${POSTS.map(postCard).join('')}
        <a class="post" href="#" data-demo="Demo only"><span class="tagline">Lampwright</span><h3>Announcing Lampwright</h3><p>Our second game, a co-op roguelite about lighting the dark.</p><small>June 10</small></a>
        <a class="post" href="#" data-demo="Demo only"><span class="tagline">Studio</span><h3>Why we work four days a week</h3><p>Two years in, here’s what it’s done for us and the games.</p><small>April 22</small></a>
        <a class="post" href="#" data-demo="Demo only"><span class="tagline">Mossletter</span><h3>Mossletter is out</h3><p>Our first game is out on PC and Mac. Thank you for playing.</p><small>March 3</small></a>
        <a class="post" href="#" data-demo="Demo only"><span class="tagline">Mossletter</span><h3>Designing puzzles you can’t fail</h3><p>How we made a puzzle game with no timers and no game over.</p><small>January 19</small></a>
      </div>
    </div></section>
    <section class="tint"><div class="wrap split"><div><h2>Get news by email</h2><p class="sub" style="margin:0">About once a month. Unsubscribe any time.</p></div>${newsletter}</div></section>` },

  ...POSTS.map(([s, tag, t, d, date, parts]) => ({ file: `news-${s}.html`, title: `${t} · Fernhollow Interactive`, desc: d, nav: 'news.html', post: true, tag, h1: t,
    body: `<section style="padding-top:24px"><div class="wrap"><article class="prose">
      <p class="post-meta">${tag} · ${date} · by the Fernhollow team</p>
      <div class="post-art">${tag === 'Lampwright' ? lampArt(`p${s}`) : mossArt(`p${s}`)}</div>
      ${prose(parts)}
      <div class="row">${tag === 'Lampwright' ? '<a class="btn" href="../lampwright/community.html#playtest">Sign up for the playtest</a>' : demo('Get Mossletter', 'btn', 'Demo only · no store page')}<a class="btn alt" href="news.html">All news</a></div>
    </article></div></section>` })),

  { file: 'careers.html', title: 'Careers · Fernhollow Interactive', desc: 'Open roles at Fernhollow Interactive, with pay ranges. Fully remote in the US.', nav: 'careers.html',
    kicker: 'Careers', h1: 'Make games <em>with us</em>', lede: 'Fully remote in the US. Four-day weeks. Every role lists its pay range, because you shouldn’t have to ask.',
    body: `<section style="padding-top:24px"><div class="wrap">
      <div class="role"><div><div class="chips"><span class="chip soon">Full time</span><span class="chip">Remote, US</span></div><h3>Gameplay programmer</h3><p>Build Lampwright’s lanterns, glass and co-op systems with Theo and Jonah. You’ve shipped a game, you like working with designers, and you can explain your code to an artist.</p></div><div class="pay">$85,000–$105,000<small>per year · plus benefits</small></div>
        <div style="grid-column:1/-1" class="row">${demo('Apply for this role', 'btn', 'Demo only · applications are off')}</div></div>
      <div class="role"><div><div class="chips"><span class="chip soon">Part time · 20 hrs/week</span><span class="chip">Contract</span></div><h3>Community manager</h3><p>Help run our Discord and playtests with Lu: announcements, events, moderation and turning player feedback into something the team can use.</p></div><div class="pay">$28–$34<small>per hour</small></div>
        <div style="grid-column:1/-1" class="row">${demo('Apply for this role', 'btn', 'Demo only · applications are off')}</div></div>
      <p class="illus" style="margin-top:16px">Example roles and pay for this concept site.</p>
    </div></section>
    <section class="tint"><div class="wrap">
      <span class="kicker">How hiring works</span><h2>Four steps, about three weeks</h2><p class="sub">We reply to every application, including the no’s.</p>
      <div class="steps"><div><b>Apply</b><span>A short form and your portfolio or résumé. No cover letter needed.</span></div><div><b>Chat</b><span>30 minutes with Sam about the role and how we work.</span></div><div><b>Paid task</b><span>A small, paid take-home task. Never more than 4 hours.</span></div><div><b>Meet the team</b><span>An hour with the people you’d work with most.</span></div></div>
    </div></section>
    <section><div class="wrap split">
      <div><h2>What you get</h2><ul class="checks"><li>Four-day, 32-hour weeks at full pay</li><li>Health, dental and vision for you and family</li><li>A home-office budget and a new computer</li><li>Paid studio days together, once a month</li></ul></div>
      <div class="panel"><h3>Don’t see your role?</h3><p>We keep good applications on file for a year. Tell us what you do.</p>${demo('Send an open application', 'btn', 'Demo only · applications are off')}</div>
    </div></section>` },

  { file: 'press.html', title: 'Press · Fernhollow Interactive', desc: 'Studio fact sheet, logos, game press kits and press contact.', nav: 'press.html',
    kicker: 'Press', h1: 'Press &amp; <em>creators</em>', lede: 'Everything you need to write about the studio. Each game has its own full press kit.',
    body: `<section style="padding-top:24px"><div class="wrap">
      <h2>Studio fact sheet</h2>
      <dl class="facts"><div><dt>Studio</dt><dd>Fernhollow Interactive</dd></div><div><dt>Founded</dt><dd>2021</dd></div><div><dt>Team</dt><dd>7 people</dd></div><div><dt>Based</dt><dd>Fully remote, US</dd></div><div><dt>Games</dt><dd>Mossletter (out now), Lampwright (2027)</dd></div><div><dt>Press contact</dt><dd>press@fernhollow.example</dd></div></dl>
      <h2 style="margin-top:56px">About the studio</h2><p class="lede" style="font-size:19px">Fernhollow Interactive is a small, fully remote independent studio that makes cozy, strange games. Its first game, the puzzle game Mossletter, released on PC and Mac. Its second, the co-op roguelite Lampwright, is coming to PC in 2027.</p>
    </div></section>
    <section class="tint"><div class="wrap">
      <h2>Game press kits</h2>
      <div class="dl"><a href="../lampwright/presskit.html"><span>Lampwright press kit</span><small>Trailer, screenshots, logos →</small></a><a href="#" data-demo="Demo only · no file attached"><span>Mossletter press kit</span><small>ZIP · screenshots, logos</small></a></div>
      <h2 style="margin-top:56px">Studio logos</h2>
      <div class="logos"><div style="background:var(--cream)">${leaf}Fernhollow<small>On light</small></div><div style="background:var(--ink);color:#fff">${leaf}Fernhollow<small>On dark</small></div><div style="background:var(--fern);color:#fff">${lampLogo(44)}Lampwright<small>Game logo</small></div></div>
      <div class="dl" style="margin-top:14px"><a href="#" data-demo="Demo only · no file attached"><span>Logo pack</span><small>SVG &amp; PNG</small></a><a href="#" data-demo="Demo only · no file attached"><span>Team photos</span><small>JPG</small></a></div>
    </div></section>
    <section><div class="wrap split">
      <div><h2>Creators &amp; keys</h2><p class="sub" style="margin:0">Streamers, video makers and writers can ask for a key to any of our games. Tell us where you publish and we’ll reply within a few days.</p></div>
      <form class="grid panel" data-demo-form="This is a demo, so nothing was sent. On a live site the request reaches the press inbox and Site Stats counts a “Key request” goal.">
        <label>Name<input name="name" autocomplete="name" required></label><label>Email<input type="email" name="email" autocomplete="email" required></label>
        <label>Game<select name="game"><option>Mossletter</option><option>Lampwright (playtest)</option></select></label><label>Where you publish<input name="channel" placeholder="Channel or site link"></label>
        <button class="btn full" type="submit" style="justify-content:center">Request a key</button><p class="note full" role="status"></p>
      </form>
    </div></section>` },

  { file: 'contact.html', title: 'Contact · Fernhollow Interactive', desc: 'Get in touch with Fernhollow Interactive about support, press, business or jobs.', nav: 'contact.html',
    kicker: 'Contact', h1: 'Say <em>hello</em>', lede: 'Pick a topic so your message reaches the right person. We reply within two business days.',
    body: `<section style="padding-top:24px"><div class="wrap split" style="align-items:start">
      <form class="grid panel" data-demo-form="This is a demo, so nothing was sent. On a live site the message reaches the studio inbox and Site Stats counts a “Contact form sent” goal.">
        <label>Name<input name="name" autocomplete="name" required></label><label>Email<input type="email" name="email" autocomplete="email" required></label>
        <label class="full">Topic<select name="topic"><option>Game support or a bug</option><option>Press or creator keys</option><option>Business, publishing or porting</option><option>Jobs</option><option>Something else</option></select></label>
        <label class="full">Message<textarea name="msg" rows="5" required></textarea></label>
        <button class="btn full" type="submit" style="justify-content:center">Send message</button><p class="note full" role="status"></p>
      </form>
      <div>
        <h3>Faster ways to get help</h3>
        <ul class="checks"><li><b>Bugs in Lampwright’s playtest:</b> the bug channel in our Discord</li><li><b>Press:</b> press@fernhollow.example</li><li><b>Jobs:</b> apply on the <a href="careers.html">careers page</a></li></ul>
        <details class="faq"><summary>Do you accept game pitches?</summary><p>We don’t publish other studios’ games, but we’re always happy to talk shop.</p></details>
        <details class="faq"><summary>Where are you based?</summary><p>Everywhere and nowhere: the team is fully remote across the US.</p></details>
        <details class="faq"><summary>Can I use your games in my video?</summary><p>Yes, and you can monetize it. A link back is appreciated.</p></details>
      </div>
    </div></section>` },
];

function shell(p) {
  const crumb = p.post ? `<a href="index.html">Home</a><span>/</span><a href="news.html">News</a><span>/</span>${esc(p.tag)}` : `<a href="index.html">Home</a><span>/</span>${p.kicker}`;
  const top = p.home ? '' : `<div class="wrap page-hero"><div class="crumbs">${crumb}</div>${p.post ? '' : `<span class="kicker">${p.kicker}</span>`}<h1 style="font-size:clamp(40px,6vw,${p.post ? 58 : 76}px)">${p.h1}</h1>${p.lede ? `<p class="lede">${p.lede}</p>` : ''}</div>`;
  return `${head({ title: p.title.replace(/&(?!amp;)/g, '&amp;'), desc: p.desc, fonts: 'family=Fraunces:ital,opsz,wght,SOFT,WONK@0,9..144,600..800,0..100,0..1;1,9..144,600..800,0..100,0..1&family=Bricolage+Grotesque:opsz,wght@12..96,400..700' })}
<body>
<a class="skip" href="#main">Skip to content</a>
${demoBar('fernhollow', 'Fernhollow Interactive', 'studio site')}
<div class="wrap">
  <header>
    <a class="logo" href="index.html">${leaf}<span>Fernhollow<small>Interactive</small></span></a>
    <nav class="site-nav" aria-label="Main">${navLinks(NAV, p.nav)}</nav>${menuBtn}
  </header>
</div>
<main id="main">
${top}
${p.body}
</main>
<footer><div class="wrap">
  <div class="foot">
    <div><b>Fernhollow Interactive</b>An independent game studio making small games with big hearts. Fully remote, US.</div>
    <div><b>Games</b><a href="../lampwright/index.html">Lampwright</a><a href="games.html#mossletter">Mossletter</a></div>
    <div><b>Studio</b><a href="studio.html">Team</a><a href="news.html">News</a><a href="careers.html">Careers</a></div>
    <div><b>Contact</b><a href="press.html">Press &amp; creators</a><a href="contact.html">Get in touch</a></div>
  </div>
  <div class="foot-bottom"><span>Fictional studio · its people, games and roles are made up</span><span>A concept by <a href="../../work-fernhollow.html">Syndicate Marketing</a></span></div>
</div></footer>
<script src="site.js"></script>
</body>
</html>
`;
}

export default { slug: 'fernhollow', css, pages, shell };
