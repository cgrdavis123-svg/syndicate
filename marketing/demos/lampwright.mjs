// Lampwright — fictional co-op game by the fictional Fernhollow Interactive. Shows a Starter website (5 pages)
// built as a game's own site, plus the press kit add-on, Discord/newsletter community pages and wishlist tracking.
// All art is drawn in SVG here (no real screenshots exist). No reviews, press quotes or wishlist numbers: it's unreleased.
import { head, demoBar, navLinks, menuBtn, esc } from './common.mjs';
import { icon } from '../../tools/icons.mjs';

const voiceIcon = icon('speaker').replace('<svg', '<svg width="13" height="13" style="vertical-align:-2px"');

const NAV = [['features.html', 'The game'], ['media.html', 'Media'], ['community.html', 'Community'], ['presskit.html', 'Press kit']];
const wish = (label = 'Wishlist on Steam', cls = 'btn') => `<a class="${cls}" href="#" data-demo="Demo only · on a live site this opens the store page">${label}</a>`;
const discord = (label = 'Join the Discord', cls = 'btn ghost') => `<a class="${cls}" href="#" data-demo="Demo only · no invite link">${label}</a>`;

// Small seeded random numbers so every scene draws the same way on every build.
function rand(seed) { let a = seed >>> 0; return () => { a = (a + 0x6D2B79F5) | 0; let t = Math.imul(a ^ (a >>> 15), 1 | a); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }; }
const r1 = n => Math.round(n * 10) / 10;

// A night-city scene: sky, moon, three rows of rooftops, strung lanterns, fog, things watching from the dark,
// and optionally the lampwright with a lantern. `hud` adds a game interface so it reads as a screenshot.
export function scene({ id, seed = 1, w = 1600, h = 900, sky = ['#070914', '#1b1840', '#3b2a55'], glow = '#ffb648', moon = true, figure = true, eyes = 3, hud = null, district = '', label = '' }) {
  const R = rand(seed), p = id;
  let out = `<svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${esc(label || 'Illustration of a city at night lit by lanterns')}" preserveAspectRatio="xMidYMid slice">
<defs>
<linearGradient id="${p}s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${sky[0]}"/><stop offset=".65" stop-color="${sky[1]}"/><stop offset="1" stop-color="${sky[2]}"/></linearGradient>
<radialGradient id="${p}g"><stop offset="0" stop-color="${glow}" stop-opacity=".95"/><stop offset=".25" stop-color="${glow}" stop-opacity=".45"/><stop offset="1" stop-color="${glow}" stop-opacity="0"/></radialGradient>
<radialGradient id="${p}m"><stop offset="0" stop-color="#fff6dd" stop-opacity=".5"/><stop offset="1" stop-color="#fff6dd" stop-opacity="0"/></radialGradient>
<linearGradient id="${p}f" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b9b3e6" stop-opacity="0"/><stop offset="1" stop-color="#b9b3e6" stop-opacity=".22"/></linearGradient>
</defs>
<rect width="${w}" height="${h}" fill="url(#${p}s)"/>`;
  for (let i = 0; i < 70; i++) out += `<circle cx="${r1(R() * w)}" cy="${r1(R() * h * .5)}" r="${r1(.6 + R() * 1.4)}" fill="#fff" opacity="${r1(.2 + R() * .6)}"/>`;
  if (moon) { const mx = w * (.62 + R() * .25), my = h * .2; out += `<circle cx="${r1(mx)}" cy="${r1(my)}" r="${h * .22}" fill="url(#${p}m)"/><circle cx="${r1(mx)}" cy="${r1(my)}" r="${h * .065}" fill="#f6ecd2"/><circle cx="${r1(mx + h * .025)}" cy="${r1(my - h * .012)}" r="${h * .058}" fill="${sky[1]}" opacity=".35"/>`; }
  const lanterns = [];
  // Three rows of buildings, back to front.
  [[.42, '#2a2147', .25, .35], [.55, '#18142e', .4, .6], [.7, '#0b0a17', .55, .9]].forEach(([base, fill, lit, scale], row) => {
    let x = -20 - R() * 60; const tops = [];
    while (x < w + 40) {
      const bw = (90 + R() * 150) * (1 + scale * .4), bh = h * (.18 + R() * .26) * (.7 + scale * .5), y = h * base + h * .3 * (1 - scale) - bh * .5;
      const peak = R() < .5;
      out += peak ? `<path d="M${r1(x)} ${h}V${r1(y)}l${r1(bw / 2)} ${r1(-bw * .32)}l${r1(bw / 2)} ${r1(bw * .32)}V${h}Z" fill="${fill}"/>` : `<rect x="${r1(x)}" y="${r1(y)}" width="${r1(bw)}" height="${h}" fill="${fill}"/>`;
      if (R() < .35) out += `<rect x="${r1(x + bw * .7)}" y="${r1(y - (peak ? bw * .32 : 0) - 34)}" width="16" height="44" fill="${fill}"/>`;
      for (let wy = y + 24; wy < h - 30; wy += 46) for (let wx = x + 18; wx < x + bw - 26; wx += 34) if (R() < lit * .5) out += `<rect x="${r1(wx)}" y="${r1(wy)}" width="12" height="18" rx="2" fill="${glow}" opacity="${r1(.35 + R() * .5)}"/>`;
      tops.push([x + bw * .5, y]);
      x += bw + 4 + R() * 30;
    }
    if (row === 2) for (let i = 0; i < tops.length - 1; i++) if (R() < .7) lanterns.push([tops[i], tops[i + 1]]);
  });
  // Strings of lanterns between the front rooftops.
  for (const [[ax, ay], [bx, by]] of lanterns) {
    const sag = 60 + R() * 60, mx = (ax + bx) / 2, my = Math.max(ay, by) + sag;
    out += `<path d="M${r1(ax)} ${r1(ay + 20)}Q${r1(mx)} ${r1(my)} ${r1(bx)} ${r1(by + 20)}" stroke="#05040b" stroke-width="2" fill="none"/>`;
    for (let t = .2; t < .9; t += .2 + R() * .1) {
      const lx = (1 - t) ** 2 * ax + 2 * (1 - t) * t * mx + t * t * bx, ly = (1 - t) ** 2 * (ay + 20) + 2 * (1 - t) * t * my + t * t * (by + 20);
      out += `<circle cx="${r1(lx)}" cy="${r1(ly + 10)}" r="46" fill="url(#${p}g)"/><rect x="${r1(lx - 6)}" y="${r1(ly + 2)}" width="12" height="16" rx="3" fill="${glow}"/>`;
    }
  }
  // Fog and the things in the dark.
  out += `<rect y="${h * .55}" width="${w}" height="${h * .45}" fill="url(#${p}f)"/>`;
  for (let i = 0; i < eyes; i++) { const ex = R() < .5 ? w * (.04 + R() * .18) : w * (.78 + R() * .18), ey = h * (.72 + R() * .18), s = 3 + R() * 3; out += `<g opacity=".9"><ellipse cx="${r1(ex)}" cy="${r1(ey)}" rx="${r1(s * 7)}" ry="${r1(s * 4)}" fill="#05040b" opacity=".6"/><circle cx="${r1(ex - s * 2)}" cy="${r1(ey)}" r="${r1(s)}" fill="#c8f0ff"/><circle cx="${r1(ex + s * 2)}" cy="${r1(ey)}" r="${r1(s)}" fill="#c8f0ff"/></g>`; }
  out += `<rect y="${h * .9}" width="${w}" height="${h * .1}" fill="#05040b"/>`;
  if (figure) {
    const fx = w * .5, fy = h * .9, s = h / 900;
    out += `<circle cx="${r1(fx + 58 * s)}" cy="${r1(fy - 120 * s)}" r="${r1(240 * s)}" fill="url(#${p}g)"/>
<path d="M${r1(fx)} ${r1(fy - 210 * s)}c${r1(-22 * s)} 0 ${r1(-34 * s)} ${r1(16 * s)} ${r1(-34 * s)} ${r1(36 * s)}l${r1(-12 * s)} ${r1(10 * s)} ${r1(10 * s)} ${r1(4 * s)}c${r1(-18 * s)} ${r1(30 * s)} ${r1(-30 * s)} ${r1(80 * s)} ${r1(-34 * s)} ${r1(160 * s)}h${r1(124 * s)}c${r1(-4 * s)} ${r1(-70 * s)} ${r1(-14 * s)} ${r1(-120 * s)} ${r1(-28 * s)} ${r1(-150 * s)}l${r1(16 * s)} ${r1(-6 * s)} ${r1(-14 * s)} ${r1(-10 * s)}c0 ${r1(-22 * s)} ${r1(-14 * s)} ${r1(-36 * s)} ${r1(-34 * s)} ${r1(-36 * s)}Z" fill="#05040b"/>
<path d="M${r1(fx + 20 * s)} ${r1(fy - 128 * s)}l${r1(38 * s)} ${r1(-6 * s)}" stroke="#05040b" stroke-width="${r1(9 * s)}" stroke-linecap="round"/>
<path d="M${r1(fx + 58 * s)} ${r1(fy - 134 * s)}v${r1(10 * s)}" stroke="#05040b" stroke-width="${r1(3 * s)}"/>
<rect x="${r1(fx + 44 * s)}" y="${r1(fy - 124 * s)}" width="${r1(28 * s)}" height="${r1(38 * s)}" rx="${r1(6 * s)}" fill="${glow}" stroke="#05040b" stroke-width="${r1(4 * s)}"/>
<rect x="${r1(fx + 52 * s)}" y="${r1(fy - 114 * s)}" width="${r1(12 * s)}" height="${r1(18 * s)}" rx="${r1(5 * s)}" fill="#fff4d6"/>`;
  }
  if (hud) {
    const f = `font-family="Outfit, sans-serif"`;
    out += `<g ${f}><rect x="40" y="36" width="330" height="88" rx="14" fill="#05040b" opacity=".55"/><text x="64" y="72" fill="#f3ead8" font-size="20" font-weight="600" letter-spacing="3">${esc(district.toUpperCase())}</text><text x="64" y="102" fill="#a9a6b8" font-size="17">${esc(hud.goal)}</text>
<rect x="40" y="${h - 96}" width="420" height="58" rx="14" fill="#05040b" opacity=".55"/><text x="64" y="${h - 60}" fill="#ffb648" font-size="17" font-weight="600">OIL</text><rect x="110" y="${h - 76}" width="220" height="16" rx="8" fill="#2a2440"/><rect x="110" y="${h - 76}" width="${220 * hud.oil}" height="16" rx="8" fill="#ffb648"/><text x="350" y="${h - 60}" fill="#f3ead8" font-size="17">${hud.lit} lit</text>
${hud.players.map((n, i) => `<g transform="translate(${w - 300} ${40 + i * 52})"><rect width="260" height="42" rx="12" fill="#05040b" opacity=".55"/><circle cx="24" cy="21" r="10" fill="${['#ffb648', '#8fd3ff', '#ff7a3d', '#b8e986'][i]}"/><text x="46" y="27" fill="#f3ead8" font-size="17">${esc(n)}</text></g>`).join('')}</g>`;
  }
  return out + '</svg>';
}

export const logo = (size = 40) => `<svg viewBox="0 0 40 40" width="${size}" height="${size}" aria-hidden="true"><circle cx="20" cy="22" r="18" fill="#ffb648" opacity=".18"/><path d="M14 9h12M20 5v4" stroke="#ffb648" stroke-width="2.4" stroke-linecap="round"/><path d="M12 13h16l-2 18H14z" fill="#141a2e" stroke="#ffb648" stroke-width="2.4" stroke-linejoin="round"/><path d="M20 16c3 4 4 6 4 8a4 4 0 0 1-8 0c0-2 1-4 4-8Z" fill="#ffb648"/><path d="M11 33h18" stroke="#ffb648" stroke-width="2.4" stroke-linecap="round"/></svg>`;

const SHOTS = [
  { seed: 11, district: 'Cinder Row', goal: 'Light 6 of 8 streetlamps', oil: .72, lit: 4, players: ['Wren', 'Oskar'], cap: 'Cinder Row at dusk. Every lamp you light pushes the dark back one street.' },
  { seed: 27, district: 'The Glassworks', goal: 'Craft a lantern before the bell', oil: .4, lit: 2, players: ['Wren', 'Oskar', 'Juno'], sky: ['#060c16', '#123048', '#25506a'], glow: '#ffc96b', cap: 'The Glassworks, where salvaged panes become lanterns with their own tricks.' },
  { seed: 43, district: 'Low Canal', goal: 'Escort the ferry home', oil: .9, lit: 7, players: ['Wren'], sky: ['#0a0612', '#2a1238', '#4a1f45'], glow: '#ff9f5a', cap: 'Low Canal in solo mode. Fewer hands, but the dark moves slower.' },
  { seed: 58, district: 'Bellfoundry Hill', goal: 'Hold the square until dawn', oil: .25, lit: 9, players: ['Wren', 'Oskar', 'Juno', 'Pell'], sky: ['#070914', '#1e1b46', '#4a3666'], cap: 'Four players holding Bellfoundry Hill on the last night of a run.' },
  { seed: 71, district: 'Mothmarket', goal: 'Trade glass for oil', oil: .55, lit: 5, players: ['Juno', 'Pell'], sky: ['#081010', '#163a33', '#2c5a4c'], glow: '#ffd27a', cap: 'Mothmarket opens after dark. Bring glass, leave with oil and odd recipes.' },
  { seed: 89, district: 'The Unlit Quarter', goal: 'Find the missing lampwright', oil: .15, lit: 1, players: ['Wren', 'Oskar'], sky: ['#030308', '#0d0c1f', '#1a1633'], glow: '#ff8a4a', cap: 'The Unlit Quarter: no lamps, no map, and something keeping count.' },
];
const shot = (s, i, hudOn = true) => scene({ id: `sh${i}`, seed: s.seed, sky: s.sky, glow: s.glow, eyes: 2 + (i % 3), figure: i % 2 === 0, hud: hudOn ? s : null, district: s.district, label: `Concept screenshot: ${s.district}` });

const FEATURES = [
  ['Craft', 'Build lanterns from salvage', 'Every pane of glass you find changes what a lantern does. Blue glass reveals hidden doors, cracked glass burns hotter but runs out fast, and stained glass can stun what it shines on. Mix them and see what happens.'],
  ['Light', 'Win the city back, street by street', 'Each run is one long night across a city that changes every time. Light streetlamps to make safe paths, then decide which districts to save and which to leave dark.'],
  ['Hold', 'Keep the dark at bay until dawn', 'The night learns which streets you light, and it comes for the ones you forget. Oil is always running low, so every lantern is a choice.'],
  ['Together', 'Play solo or with up to 4 friends', 'Drop-in online co-op or shared-screen on one couch. Pass oil, chain lanterns into a wall of light, and argue about which way to go. Solo runs scale so they’re fair.'],
];

const GLASS = [
  ['Clear', 'Steady light, long burn', 'Your first lantern. Reliable and boring, in a good way.'],
  ['Cobalt', 'Reveals hidden doors and notes', 'Dimmer, but it shows things the city would rather keep hidden.'],
  ['Cracked', 'Hot and bright, burns out fast', 'Scares off anything nearby. Bring spare oil.'],
  ['Stained', 'Stuns what it shines on', 'Slow to build. Worth it on the last night.'],
  ['Mirror', 'Bounces light around corners', 'For co-op players who like to show off.'],
];

const css = `
:root { --night: #0b0e1a; --night-2: #141a2e; --night-3: #1d2440; --amber: #ffb648; --ember: #ff7a3d; --glass: #8fd3ff; --text: #f3ead8; --muted: #a9a6b8; --line: rgba(243,234,216,.12); --display: "Cinzel", Georgia, serif; --body: "Outfit", system-ui, sans-serif; }
html { scroll-behavior: smooth; } body { margin: 0; background: var(--night); color: var(--text); font: 17px/1.6 var(--body); }
.demo-bar { background: #000; color: #c9c5d6; } .demo-bar a { color: var(--amber); font-weight: 600; }
.wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
.top { position: sticky; top: 0; z-index: 10; background: rgba(11,14,26,.86); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
header { display: flex; align-items: center; gap: 28px; height: 72px; }
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; font: 700 22px/1 var(--display); letter-spacing: .08em; }
.site-nav { display: flex; gap: 26px; margin-left: auto; } .site-nav a { text-decoration: none; font-weight: 500; color: var(--muted); } .site-nav a:hover, .site-nav a[aria-current="page"] { color: var(--text); }
.btn { display: inline-flex; align-items: center; gap: 10px; background: var(--amber); color: #1b1203; padding: 14px 24px; border-radius: 999px; text-decoration: none; font-weight: 700; border: 0; cursor: pointer; box-shadow: 0 0 0 0 rgba(255,182,72,.0); transition: box-shadow .25s, transform .25s; }
.btn:hover { box-shadow: 0 0 34px 2px rgba(255,182,72,.45); transform: translateY(-1px); }
.btn.ghost { background: transparent; color: var(--text); box-shadow: inset 0 0 0 1.5px rgba(243,234,216,.35); } .btn.ghost:hover { box-shadow: inset 0 0 0 1.5px var(--amber); }
.btn.sm { padding: 10px 18px; font-size: 15px; } header .btn { margin-left: 4px; }
h1, h2, h3 { font-family: var(--display); font-weight: 700; letter-spacing: .02em; line-height: 1.1; margin: 0 0 16px; }
h1 { font-size: clamp(44px, 7vw, 92px); } h2 { font-size: clamp(30px, 4vw, 46px); } h3 { font-size: 22px; }
.kicker { font: 600 13px/1 var(--body); letter-spacing: .24em; text-transform: uppercase; color: var(--amber); display: inline-block; margin-bottom: 14px; }
.lede { font-size: 20px; color: #d9d2c2; max-width: 620px; } .sub { color: var(--muted); max-width: 640px; margin: 0 0 36px; }
section { padding: 96px 0; } .band { background: var(--night-2); border-block: 1px solid var(--line); }
.row { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 30px; }
.hero { position: relative; min-height: min(86vh, 820px); display: grid; align-items: end; overflow: hidden; }
.hero-art { position: absolute; inset: 0; } .hero-art svg { width: 100%; height: 100%; display: block; }
.hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(11,14,26,.92) 0%, rgba(11,14,26,.55) 45%, rgba(11,14,26,0) 75%), linear-gradient(0deg, var(--night) 0%, rgba(11,14,26,0) 35%); }
.hero .wrap { position: relative; z-index: 1; padding-bottom: 80px; padding-top: 120px; width: 100%; }
.hero h1 { text-shadow: 0 0 40px rgba(255,182,72,.35); } .tag { font: 600 15px/1.4 var(--body); color: var(--glass); letter-spacing: .06em; }
.meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 26px; } .meta span { font-size: 14px; padding: 7px 12px; border-radius: 999px; border: 1px solid var(--line); background: rgba(11,14,26,.6); }
.trailer { position: relative; border-radius: 20px; overflow: hidden; border: 1px solid var(--line); aspect-ratio: 16/9; background: #000; }
.trailer svg { width: 100%; height: 100%; display: block; } .trailer button { position: absolute; inset: 0; margin: auto; width: 96px; height: 96px; border-radius: 50%; border: 0; background: var(--amber); color: #1b1203; cursor: pointer; display: grid; place-items: center; box-shadow: 0 0 60px rgba(255,182,72,.6); }
.trailer button svg { width: 34px; height: 34px; } .trailer .cap { position: absolute; left: 20px; bottom: 16px; font-size: 14px; background: rgba(5,4,11,.7); padding: 6px 12px; border-radius: 8px; }
.pillars { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
.pillar { background: var(--night-2); border: 1px solid var(--line); border-radius: 18px; padding: 26px; } .pillar b { font: 700 26px/1 var(--display); color: var(--amber); display: block; margin-bottom: 12px; } .pillar p { margin: 0; color: var(--muted); font-size: 15.5px; }
.split { display: grid; grid-template-columns: 1.1fr 1fr; gap: 56px; align-items: center; } .split.rev > :first-child { order: 2; }
.frame { border-radius: 18px; overflow: hidden; border: 1px solid var(--line); } .frame svg { display: block; width: 100%; height: auto; aspect-ratio: 16/9; }
.checks { list-style: none; padding: 0; margin: 20px 0 0; display: grid; gap: 12px; } .checks li { padding-left: 30px; position: relative; } .checks li::before { content: ""; position: absolute; left: 4px; top: 9px; width: 10px; height: 10px; border-radius: 50%; background: var(--amber); box-shadow: 0 0 12px var(--amber); }
.facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; background: var(--line); border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
.facts div { background: var(--night-2); padding: 22px; } .facts dt { font-size: 13px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); } .facts dd { margin: 6px 0 0; font-weight: 600; font-size: 18px; }
.signup { background: radial-gradient(120% 140% at 100% 0%, rgba(255,182,72,.22), transparent 55%), var(--night-2); border: 1px solid var(--line); border-radius: 24px; padding: 44px; display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: center; }
.signup h2 { margin-bottom: 8px; } .signup p { color: var(--muted); margin: 0; }
form.inline { display: flex; gap: 10px; flex-wrap: wrap; } form.inline input { flex: 1 1 220px; }
input, select, textarea { font: inherit; color: var(--text); background: var(--night); border: 1.5px solid rgba(243,234,216,.22); border-radius: 12px; padding: 13px 15px; } input:focus, select:focus, textarea:focus { border-color: var(--amber); outline: none; box-shadow: 0 0 0 3px rgba(255,182,72,.25); }
label { display: grid; gap: 6px; font-weight: 600; font-size: 14px; } .note { color: var(--amber); font-size: 14px; margin: 10px 0 0; width: 100%; }
form.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; } form.grid .full { grid-column: 1 / -1; }
.feature { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; padding: 56px 0; border-bottom: 1px solid var(--line); } .feature:last-child { border-bottom: 0; } .feature:nth-child(even) > :first-child { order: 2; }
.feature p { color: #d9d2c2; } .num { font: 700 15px/1 var(--display); color: var(--amber); letter-spacing: .2em; }
table { width: 100%; border-collapse: collapse; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; } th, td { text-align: left; padding: 15px 18px; border-bottom: 1px solid var(--line); vertical-align: top; } th { background: var(--night-3); font-size: 14px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); } td b { color: var(--amber); }
.gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; } .gallery button { padding: 0; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: none; cursor: zoom-in; color: inherit; text-align: left; } .gallery svg { display: block; width: 100%; height: auto; aspect-ratio: 16/9; transition: transform .4s; } .gallery button:hover svg { transform: scale(1.03); }
.gallery span { display: block; padding: 12px 14px; font-size: 14px; color: var(--muted); background: var(--night-2); }
.lightbox { position: fixed; inset: 0; z-index: 40; background: rgba(3,3,8,.92); display: none; place-items: center; padding: 24px; } .lightbox.open { display: grid; }
.lightbox figure { margin: 0; max-width: 1200px; width: 100%; } .lightbox svg { width: 100%; height: auto; border-radius: 12px; display: block; } .lightbox figcaption { margin-top: 14px; color: var(--muted); } .lightbox .x { position: absolute; top: 18px; right: 18px; }
.cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; } .card { background: var(--night-2); border: 1px solid var(--line); border-radius: 18px; padding: 26px; display: flex; flex-direction: column; gap: 10px; } .card p { margin: 0; color: var(--muted); font-size: 15.5px; } .card .btn { margin-top: auto; align-self: flex-start; }
.discord { display: grid; grid-template-columns: 1.2fr 1fr; gap: 0; border: 1px solid var(--line); border-radius: 22px; overflow: hidden; }
.discord .chans { background: #10131f; padding: 22px; font-size: 15px; } .discord .chans b { display: block; font-size: 12px; letter-spacing: .14em; color: var(--muted); margin: 14px 0 6px; } .discord .chans b:first-child { margin-top: 0; } .discord .chans span { display: block; padding: 5px 10px; border-radius: 8px; color: #cfcbe0; } .discord .chans span.on { background: rgba(255,182,72,.12); color: var(--text); }
.discord .why { padding: 32px; background: var(--night-2); } .discord .why p { color: var(--muted); }
.road { list-style: none; padding: 0; margin: 0; display: grid; gap: 0; border-left: 2px solid var(--line); } .road li { position: relative; padding: 0 0 30px 30px; } .road li::before { content: ""; position: absolute; left: -8px; top: 6px; width: 14px; height: 14px; border-radius: 50%; background: var(--night); border: 2px solid var(--muted); }
.road li.done::before { background: var(--amber); border-color: var(--amber); box-shadow: 0 0 14px var(--amber); } .road li.now::before { border-color: var(--amber); } .road b { display: block; } .road small { color: var(--amber); font-weight: 600; letter-spacing: .1em; text-transform: uppercase; } .road p { color: var(--muted); margin: 4px 0 0; }
.posts { display: grid; gap: 12px; } .post { display: flex; justify-content: space-between; gap: 18px; padding: 18px 20px; border: 1px solid var(--line); border-radius: 14px; text-decoration: none; background: var(--night-2); } .post:hover { border-color: var(--amber); } .post small { color: var(--muted); white-space: nowrap; }
.press-grid { display: grid; grid-template-columns: 320px 1fr; gap: 48px; align-items: start; } .sheet { position: sticky; top: 100px; background: var(--night-2); border: 1px solid var(--line); border-radius: 18px; padding: 26px; } .sheet dl { margin: 0; display: grid; gap: 14px; } .sheet dt { font-size: 12px; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); } .sheet dd { margin: 3px 0 0; }
.dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; } .dl a { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border: 1px solid var(--line); border-radius: 12px; text-decoration: none; background: var(--night-2); } .dl a small { color: var(--muted); }
.logos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; } .logos div { border-radius: 14px; padding: 30px 16px; display: grid; place-items: center; gap: 10px; border: 1px solid var(--line); font: 700 22px/1 var(--display); letter-spacing: .08em; } .logos small { font: 500 12px/1 var(--body); letter-spacing: .1em; color: var(--muted); }
.prose p, .prose li { color: #d9d2c2; } .prose h2 { margin-top: 48px; font-size: 30px; } .prose h2:first-child { margin-top: 0; }
.page-hero { padding-top: 88px; padding-bottom: 40px; } .page-hero .lede { margin-top: 0; }
.crumbs a { color: var(--muted); }
footer { border-top: 1px solid var(--line); padding: 56px 0 40px; color: var(--muted); font-size: 14.5px; }
.foot { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 28px; } .foot b { color: var(--text); display: block; margin-bottom: 10px; } .foot a { display: block; text-decoration: none; padding: 3px 0; } .foot a:hover { color: var(--text); }
.foot-bottom { margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--line); display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; } .foot-bottom a { color: var(--amber); }
.rating-note { font-size: 13px; color: var(--muted); }
@media (max-width: 900px) {
  header { height: 64px; gap: 12px; } header .btn { display: none; } .site-nav { background: var(--night-2); margin: 0; } .wrap { padding: 0 18px; } section { padding: 64px 0; }
  .split, .feature, .signup, .discord, .press-grid { grid-template-columns: 1fr; gap: 28px; } .split.rev > :first-child, .feature:nth-child(even) > :first-child { order: 0; }
  .pillars, .facts, .gallery, .cards, .foot { grid-template-columns: 1fr 1fr; } .sheet { position: static; }
  .hero { min-height: 0; display: block; } .hero::after { display: none; } .hero-art { position: relative; inset: auto; height: 320px; -webkit-mask-image: linear-gradient(#000 70%, transparent); mask-image: linear-gradient(#000 70%, transparent); } .hero .wrap { padding-top: 8px; padding-bottom: 40px; }
  .signup { padding: 28px 22px; }
}
@media (max-width: 560px) { .pillars, .facts, .gallery, .cards, .foot, .dl, .logos, form.grid { grid-template-columns: 1fr; } .lede { font-size: 18px; } .post { flex-direction: column; gap: 4px; } th, td { padding: 12px; font-size: 15px; } }
`;

// Gallery lightbox (clicks a thumbnail, shows it large with its caption; Esc or the button closes it).
const js = `
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
`;

const playIcon = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
const trailer = (seed = 5, cap = 'Announcement trailer · 1:42') => `<div class="trailer">${scene({ id: `tr${seed}`, seed, label: 'Trailer poster: the lampwright on a rooftop above the lit city' })}<button type="button" aria-label="Play trailer">${playIcon}</button><span class="cap">${cap}</span></div>`;
const newsletter = `<div class="signup"><div><span class="kicker">The Lamplight letter</span><h2>Hear it first</h2><p>One email when there’s real news: the playtest, the release date, the launch. Never more than twice a month.</p></div>
  <form class="inline" data-demo-form="This is a demo, so you weren’t signed up. On a live site the address goes to the studio’s mailing list and Site Stats counts a “Newsletter signup” goal."><label class="sr" style="flex:1 1 220px">Email<input type="email" name="email" autocomplete="email" required placeholder="you@example.com"></label><button class="btn" type="submit" style="align-self:end">Sign me up</button><p class="note" role="status"></p></form></div>`;

const pages = [
  { file: 'index.html', title: 'Lampwright · A co-op roguelite about lighting the dark', desc: 'Craft lanterns, light the city and hold the night until dawn. Solo or co-op for up to 4. Coming 2027 to PC.', home: true,
    body: `<section class="hero"><div class="hero-art">${scene({ id: 'hero', seed: 3, eyes: 4, label: 'Key art: the lampwright holds a lantern above a city at night while eyes watch from the dark' })}</div>
    <div class="wrap">
      <p class="tag">A co-op roguelite for 1–4 players · Coming 2027</p>
      <h1>Lampwright</h1>
      <p class="lede">The night in Varrow is alive, and you’re the last one who knows how to make light. Craft lanterns from salvaged glass, win the city back street by street, and hold out until dawn.</p>
      <div class="row">${wish()}${discord()}</div>
      <div class="meta"><span>PC (Steam)</span><span>Consoles planned</span><span>Online &amp; couch co-op</span><span>Playtest this winter</span></div>
    </div></section>
    <section><div class="wrap">
      <div class="split"><div><span class="kicker">Watch</span><h2>One long night</h2><p class="sub">Every run of Lampwright is a single night across a city that rebuilds itself each time. Light enough of it and you’ll see the sun come up.</p>${wish('Wishlist now', 'btn sm')}</div>${trailer()}</div>
    </div></section>
    <section class="band"><div class="wrap">
      <span class="kicker">How it plays</span><h2>Craft. Light. Hold. Together.</h2><p class="sub">Four ideas the whole game is built on.</p>
      <div class="pillars">${FEATURES.map(([k, t, d]) => `<div class="pillar"><b>${k}</b><h3 style="font-family:var(--body);font-size:18px;letter-spacing:0">${t}</h3><p>${d.split('. ')[0]}.</p></div>`).join('')}</div>
      <div class="row"><a class="btn ghost" href="features.html">More about the game</a></div>
    </div></section>
    <section><div class="wrap split rev">
      <div><span class="kicker">Better with friends</span><h2>The more lanterns, the safer the street</h2><p class="sub">Up to four lampwrights, online or on one screen. Share oil, combine glass and cover each other’s backs when the bell rings.</p>
        <ul class="checks"><li>Drop-in, drop-out online co-op</li><li>Shared-screen couch co-op on one PC</li><li>Solo runs scale so they’re fair</li><li>Controller and keyboard, fully remappable</li></ul></div>
      <div class="frame">${shot(SHOTS[3], 3)}</div>
    </div></section>
    <section style="padding-top:0"><div class="wrap">${newsletter}</div></section>
    <section class="band"><div class="wrap">
      <span class="kicker">Release</span><h2>Where things stand</h2>
      <dl class="facts"><div><dt>Release</dt><dd>2027</dd></div><div><dt>Platforms</dt><dd>PC first, consoles later</dd></div><div><dt>Players</dt><dd>1–4, online or local</dd></div><div><dt>Next up</dt><dd>Closed playtest this winter</dd></div></dl>
      <div class="row"><a class="btn" href="community.html#playtest">Sign up for the playtest</a><a class="btn ghost" href="presskit.html">Press kit</a></div>
    </div></section>` },

  { file: 'features.html', title: 'The game · Lampwright', desc: 'How Lampwright plays: crafting lanterns, lighting districts, holding the night and playing together.', nav: 'features.html',
    kicker: 'The game', h1: 'Light is the only weapon', lede: 'Lampwright is a co-op roguelite about making light and deciding where it goes. Here’s how a night works.',
    body: `<section style="padding-top:20px"><div class="wrap">
      ${FEATURES.map(([k, t, d], i) => `<div class="feature"><div><span class="num">0${i + 1} · ${k.toUpperCase()}</span><h2 style="margin-top:12px">${t}</h2><p>${d}</p></div><div class="frame">${shot(SHOTS[i], 10 + i, false)}</div></div>`).join('')}
    </div></section>
    <section class="band"><div class="wrap">
      <span class="kicker">The glass</span><h2>Five kinds of glass, a lot of lanterns</h2><p class="sub">These are the first five. Combine two panes in one lantern and you get both, plus a side effect.</p>
      <table><thead><tr><th>Glass</th><th>What it does</th><th>Notes</th></tr></thead><tbody>${GLASS.map(([g, a, n]) => `<tr><td><b>${g}</b></td><td>${a}</td><td>${n}</td></tr>`).join('')}</tbody></table>
    </div></section>
    <section><div class="wrap split">
      <div><span class="kicker">The city</span><h2>Varrow changes every night</h2><p class="sub">Districts, streets and what’s waiting in them are rebuilt each run. Some things stay: the lamps you’ve learned, the friends who live in the market, and the notes from the lampwright who went missing.</p></div>
      <div class="frame">${shot(SHOTS[5], 16)}</div>
    </div></section>
    <section class="band"><div class="wrap" style="max-width:820px">
      <h2>Questions</h2>
      <details class="faq"><summary>When is it coming out?</summary><p>2027 on PC. A closed playtest runs this winter. Sign up on the Community page to get a key.</p></details>
      <details class="faq"><summary>Will it be on consoles?</summary><p>That’s the plan, after the PC release. We’ll announce platforms when they’re confirmed, not before.</p></details>
      <details class="faq"><summary>Can I play the whole game solo?</summary><p>Yes. Solo runs scale the night to one lampwright, and every unlock is available solo.</p></details>
      <details class="faq"><summary>Is it scary?</summary><p>It’s tense more than scary. No gore and no jump scares. The dark is something you outwit, not something that ambushes you.</p></details>
      <details class="faq"><summary>How much will it cost?</summary><p>We’ll share the price with the release date.</p></details>
    </div></section>
    <section style="padding-top:0"><div class="wrap">${newsletter}</div></section>` },

  { file: 'media.html', title: 'Media · Lampwright', desc: 'Trailer and screenshots from Lampwright.', nav: 'media.html',
    kicker: 'Media', h1: 'Trailer &amp; screenshots', lede: 'Work-in-progress footage. Things will look different by launch.',
    body: `<section style="padding-top:20px"><div class="wrap">
      ${trailer(5)}
      <h2 style="margin-top:72px">Screenshots</h2><p class="sub">Tap one to see it larger.</p>
      <div class="gallery">${SHOTS.map((s, i) => `<button type="button" aria-label="View larger: ${s.district}">${shot(s, 20 + i)}<span>${s.cap}</span></button>`).join('')}</div>
      <p class="illus" style="margin-top:18px">These “screenshots” are illustrations made for this concept site. On a live site, this gallery holds real captures from the game.</p>
    </div></section>
    <section class="band"><div class="wrap split">
      <div><span class="kicker">Making videos?</span><h2>Stream it, clip it, share it</h2><p class="sub">You’re welcome to stream, record and monetize Lampwright on any platform, before and after launch. No need to ask. A link to this site in the description is appreciated.</p><a class="btn ghost" href="presskit.html">Get the press kit</a></div>
      <div class="frame">${shot(SHOTS[4], 30, false)}</div>
    </div></section>
    <div class="lightbox" role="dialog" aria-modal="true" aria-label="Screenshot"><button type="button" class="btn sm x">Close</button><figure></figure></div>` },

  { file: 'community.html', title: 'Community · Lampwright', desc: 'Join the Lampwright Discord, sign up for the playtest and follow the devlog.', nav: 'community.html',
    kicker: 'Community', h1: 'Light it up with us', lede: 'Lampwright is being made in the open. Play early builds, tell us what’s broken, and see what changed because of it.',
    body: `<section style="padding-top:20px"><div class="wrap">
      <div class="discord">
        <div class="chans" aria-label="Example of the Discord channel list">
          <b>START HERE</b><span class="on"># welcome</span><span># rules</span><span># announcements</span>
          <b>THE GAME</b><span># general</span><span># lantern-recipes</span><span># fan-art</span><span># looking-for-group</span>
          <b>PLAYTEST</b><span># bug-reports</span><span># feedback</span><span>${voiceIcon} Co-op voice</span>
        </div>
        <div class="why"><span class="kicker">Discord</span><h2>The lamplighters’ guild</h2><p>Where playtests start, where we post devlogs first, and where you can find people to run the night with. Moderated every day by the team and volunteer mods.</p>
          <ul class="checks"><li>Playtest keys go to the Discord first</li><li>A monthly dev stream with Q&amp;A</li><li>Clear rules, and spoilers kept in spoiler tags</li></ul>
          <div class="row">${discord('Join the Discord', 'btn')}</div></div>
      </div>
    </div></section>
    <section class="band" id="playtest"><div class="wrap split">
      <div><span class="kicker">Closed playtest</span><h2>Play it this winter</h2><p class="sub">We’re inviting players in waves. Tell us how you like to play and we’ll send a key by email when your wave opens.</p>
        <ul class="checks"><li>Free, PC only, about 2 hours of game</li><li>Solo and co-op both open</li><li>Your saves won’t carry over to launch</li></ul></div>
      <form class="grid" data-demo-form="This is a demo, so nothing was sent. On a live site you’d get a confirmation email and Site Stats counts a “Playtest signup” goal.">
        <label class="full">Email<input type="email" name="email" autocomplete="email" required></label>
        <label>How do you want to play?<select name="mode"><option>Solo</option><option>Online co-op</option><option>Couch co-op</option><option>All of it</option></select></label>
        <label>Controller or keyboard?<select name="input"><option>Controller</option><option>Keyboard &amp; mouse</option><option>Either</option></select></label>
        <label class="full" style="display:flex;gap:10px;align-items:start;font-weight:500"><input type="checkbox" required style="margin-top:4px"> I’m 13 or older and agree to get playtest emails. I can unsubscribe any time.</label>
        <button class="btn full" type="submit" style="justify-content:center">Request a playtest key</button><p class="note full" role="status"></p>
      </form>
    </div></section>
    <section><div class="wrap split" style="align-items:start">
      <div><span class="kicker">Roadmap</span><h2>The road to dawn</h2><p class="sub">Dates are targets, not promises. We’ll say when something moves.</p>
        <ol class="road">
          <li class="done"><small>Done</small><b>Announcement</b><p>Trailer, store page and this site.</p></li>
          <li class="now"><small>This winter</small><b>Closed playtest</b><p>Two hours of the game, in waves.</p></li>
          <li><small>Spring 2027</small><b>Open demo</b><p>The first district, free for everyone.</p></li>
          <li><small>2027</small><b>Release on PC</b><p>Consoles to follow.</p></li>
        </ol></div>
      <div><span class="kicker">Devlog</span><h2>From the workshop</h2><p class="sub">Short posts on what we’re making and why.</p>
        <div class="posts">
          <a class="post" href="#" data-demo="Demo only"><span>Why every run is one night</span><small>Devlog #6</small></a>
          <a class="post" href="#" data-demo="Demo only"><span>Five kinds of glass and how we picked them</span><small>Devlog #5</small></a>
          <a class="post" href="#" data-demo="Demo only"><span>Making the dark feel smart, not cheap</span><small>Devlog #4</small></a>
          <a class="post" href="#" data-demo="Demo only"><span>Couch co-op on one screen: what broke</span><small>Devlog #3</small></a>
        </div></div>
    </div></section>
    <section style="padding-top:0"><div class="wrap">${newsletter}</div></section>` },

  { file: 'presskit.html', title: 'Press kit · Lampwright', desc: 'Fact sheet, description, screenshots, trailer, logos and press contact for Lampwright.', nav: 'presskit.html',
    kicker: 'Press kit', h1: 'Press kit', lede: 'Everything you need to write about Lampwright. Use anything here freely. Need something else? Email us.',
    body: `<section style="padding-top:20px"><div class="wrap press-grid">
      <aside class="sheet"><h3>Fact sheet</h3><dl>
        <div><dt>Developer</dt><dd><a href="../fernhollow/index.html">Fernhollow Interactive</a></dd></div>
        <div><dt>Based in</dt><dd>Remote, US</dd></div>
        <div><dt>Release</dt><dd>2027</dd></div>
        <div><dt>Platforms</dt><dd>PC (Steam); consoles planned</dd></div>
        <div><dt>Players</dt><dd>1–4, online and local co-op</dd></div>
        <div><dt>Genre</dt><dd>Co-op roguelite, crafting</dd></div>
        <div><dt>Price</dt><dd>Announced with the release date</dd></div>
        <div><dt>Press contact</dt><dd>press@lampwright.example</dd></div>
      </dl></aside>
      <div class="prose">
        <h2>Short description</h2><p>Lampwright is a co-op roguelite for 1–4 players about the last lampwright in a city where the night is alive. Craft lanterns from salvaged glass, light the streets, and hold out until dawn.</p>
        <h2>Long description</h2><p>In the city of Varrow, the dark has learned to move. It creeps up the streets nobody lights and takes what it finds. You are the last person who knows how to make light that lasts.</p><p>Each run is one long night in a city that rebuilds itself every time. Salvage glass, craft lanterns with strange properties, and decide which districts to save before your oil runs out. Play alone or with up to three friends online or on one screen, sharing oil and chaining lanterns into walls of light.</p>
        <h2>Features</h2><ul>${FEATURES.map(([, t]) => `<li>${t}</li>`).join('')}<li>Five kinds of glass, and what happens when you combine them</li><li>Streamer-friendly: monetize videos freely</li></ul>
        <h2>Trailer</h2>${trailer(5)}<p style="margin-top:12px"><a href="#" data-demo="Demo only · no file attached">Download the trailer (MP4, 1080p) →</a></p>
        <h2>Screenshots</h2><div class="gallery">${SHOTS.slice(0, 3).map((s, i) => `<button type="button" aria-label="View larger: ${s.district}">${shot(s, 40 + i)}<span>${s.district}</span></button>`).join('')}</div>
        <div class="dl" style="margin-top:18px"><a href="#" data-demo="Demo only · no file attached"><span>All screenshots</span><small>ZIP · 1080p &amp; 4K</small></a><a href="#" data-demo="Demo only · no file attached"><span>Key art</span><small>PNG · 4K, no text</small></a></div>
        <h2>Logos</h2><div class="logos"><div style="background:var(--night-2)">${logo(44)}LAMPWRIGHT<small>On dark</small></div><div style="background:#f3ead8;color:#141a2e">${logo(44)}LAMPWRIGHT<small style="color:#5a5560">On light</small></div><div style="background:var(--amber);color:#1b1203">${logo(44)}LAMPWRIGHT<small style="color:#5c3d06">On amber</small></div></div>
        <div class="dl" style="margin-top:12px"><a href="#" data-demo="Demo only · no file attached"><span>Logo pack</span><small>SVG &amp; PNG</small></a><a href="#" data-demo="Demo only · no file attached"><span>Full press kit</span><small>ZIP · everything here</small></a></div>
        <h2>About the developer</h2><p>Fernhollow Interactive is a small, fully remote indie studio that makes cozy, strange games. Their first game was the puzzle game Mossletter. <a href="../fernhollow/index.html">Visit the studio site →</a></p>
        <h2>Contact</h2><p>Press and keys: press@lampwright.example. Creators who want a playtest key can ask in the Discord.</p>
        <div class="lightbox" role="dialog" aria-modal="true" aria-label="Screenshot"><button type="button" class="btn sm x">Close</button><figure></figure></div>
      </div>
    </div></section>` },
];

function shell(p) {
  const top = p.home ? '' : `<div class="wrap page-hero"><div class="crumbs"><a href="index.html">Home</a><span>/</span>${p.kicker}</div><span class="kicker">${p.kicker}</span><h1 style="font-size:clamp(40px,6vw,72px)">${p.h1}</h1><p class="lede">${p.lede}</p></div>`;
  return `${head({ title: p.title, desc: p.desc, fonts: 'family=Cinzel:wght@600;700&family=Outfit:wght@400;500;600;700' })}
<body>
<a class="skip" href="#main">Skip to content</a>
${demoBar('lampwright', 'Lampwright', 'game site')}
<div class="top"><div class="wrap">
  <header>
    <a class="logo" href="index.html">${logo()}<span>LAMPWRIGHT</span></a>
    <nav class="site-nav" aria-label="Main">${navLinks(NAV, p.nav)}</nav>${menuBtn}
    ${wish('Wishlist', 'btn sm')}
  </header>
</div></div>
<main id="main">
${top}
${p.body}
</main>
<footer><div class="wrap">
  <div class="foot">
    <div><b>Lampwright</b>A co-op roguelite by Fernhollow Interactive. Coming 2027 to PC.<p class="rating-note">Not yet rated. Rating shown here once it’s issued.</p></div>
    <div><b>The game</b><a href="features.html">How it plays</a><a href="media.html">Trailer &amp; screenshots</a><a href="community.html#playtest">Playtest</a></div>
    <div><b>Community</b><a href="community.html">Discord</a><a href="community.html">Devlog</a><a href="community.html">Newsletter</a></div>
    <div><b>Studio</b><a href="../fernhollow/index.html">Fernhollow Interactive</a><a href="presskit.html">Press kit</a><a href="../fernhollow/contact.html">Contact</a></div>
  </div>
  <div class="foot-bottom"><span>Fictional game · no store page, Discord or downloads exist</span><span>A concept by <a href="../../work-lampwright.html">Syndicate Marketing</a></span></div>
</div></footer>
<script src="site.js"></script>
</body>
</html>
`;
}

export default { slug: 'lampwright', css, js, pages, shell };
