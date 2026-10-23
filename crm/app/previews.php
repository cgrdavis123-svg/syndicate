<?php
// Free homepage previews: a draft home page for a prospect's business, built from their public details, linked in outreach.
// Honest by design:
//   - every page carries a banner, always visible, saying it's a free concept by Syndicate Marketing and not their website;
//   - noindex, an unguessable link, and it expires after 30 days; the owner can remove it with one click;
//   - no logos, photos, reviews, prices, awards or claims we can't back: only what they publish about themselves
//     (name, kind of business, town, phone, services, hours, areas) arranged into a better page;
//   - the contact form doesn't send anything and says so.
// Views count only when a real browser shows the page (assets/js/pv.js), so mail-scanner prefetches don't look like interest.
// Agent: outreach-api.php?a=preview. Staff: previews.php. Public page: preview.php?t=…  Migration v28.
require_once __DIR__ . '/shop.php';

const PV_DAYS = 30;
const PV_THEMES = [
    'trades' => ['label' => 'Home services', 'accent' => '#f26b1d', 'ink' => '#14202b', 'bg' => '#f6f4ef', 'font' => 'sans', 'cta' => 'Call now', 'cta2' => 'Request a quote',
        'lede' => 'Call %phone% or request a quote online.%area%', 'svc' => 'What we do', 'form' => 'Request a quote', 'formNote' => 'Tell us what’s going on and the best time to reach you.'],
    'food' => ['label' => 'Food & drink', 'accent' => '#b5452b', 'ink' => '#2b1d16', 'bg' => '#fbf6ee', 'font' => 'serif', 'cta' => 'See the menu', 'cta2' => 'Order ahead',
        'lede' => 'See what’s on the menu, check today’s hours and order ahead.', 'svc' => 'On the menu', 'form' => 'Order ahead or ask about catering', 'formNote' => 'Pick a day and time, tell us what you’d like, and we’ll confirm.'],
    'health' => ['label' => 'Health & care', 'accent' => '#2b7a78', 'ink' => '#14302f', 'bg' => '#f3f8f7', 'font' => 'sans', 'cta' => 'Request an appointment', 'cta2' => 'Call the office',
        'lede' => 'New patients welcome. Request an appointment online or call %phone%.', 'svc' => 'How we can help', 'form' => 'Request an appointment', 'formNote' => 'Share your name, how to reach you and a few times that work. Please don’t include health details here.'],
    'shop' => ['label' => 'Shop', 'accent' => '#6b4fd8', 'ink' => '#1c1830', 'bg' => '#f7f5fb', 'font' => 'sans', 'cta' => 'Shop now', 'cta2' => 'Visit us',
        'lede' => 'Browse what’s in store, then stop by or order online.', 'svc' => 'Shop by category', 'form' => 'Ask us anything', 'formNote' => 'Looking for something specific? Ask and we’ll get back to you.'],
    'studio' => ['label' => 'Game studio', 'accent' => '#7cf0c3', 'ink' => '#0c1016', 'bg' => '#0c1016', 'font' => 'sans', 'dark' => true, 'cta' => 'Wishlist now', 'cta2' => 'Join the Discord',
        'lede' => 'Follow along, join the community and be first to know about playtests and launch.', 'svc' => 'Our games', 'form' => 'Press and business', 'formNote' => 'Writing about us, streaming, or want to work together? Say hello.'],
    'general' => ['label' => 'Local business', 'accent' => '#1f6feb', 'ink' => '#111827', 'bg' => '#f6f7f9', 'font' => 'sans', 'cta' => 'Get in touch', 'cta2' => 'See what we do',
        'lede' => 'Here’s what we do and how to reach us.%area%', 'svc' => 'What we do', 'form' => 'Get in touch', 'formNote' => 'Tell us what you need and the best way to reach you.'],
];
const PV_GUESS = ['trades' => '/hvac|heat|air|plumb|electric|roof|landscap|lawn|pest|clean|paint|garage|pool|fence|concrete|handyman|contractor|remodel|floor|tree|septic|pressure|gutter|window|siding|car ?wash|detail|auto|mechanic|tow|moving/i',
    'food' => '/bak|cafe|café|coffee|restaurant|food|pizza|bbq|barbecue|grill|bistro|deli|donut|taco|brew|bar\b|kitchen|cater|truck|dessert|ice cream|tea/i',
    'health' => '/dent|ortho|chiro|clinic|medical|therap|vet|salon|spa|barber|massage|optom|eye|derm|pediatric|physical|wellness|fitness|gym|yoga/i',
    'shop' => '/shop|store|boutique|retail|comic|card|game store|tcg|book|gift|florist|jewel|candle|thrift|vintage|hobby/i',
    'studio' => '/studio|game|indie|interactive|esports/i'];

function pv_theme_for(string $niche, string $asked = ''): string {
    if (isset(PV_THEMES[$asked])) return $asked;
    foreach (PV_GUESS as $t => $re) if (preg_match($re, $niche)) return $t;
    return 'general';
}
function pv_s($v, int $max): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v))), 0, $max); }
function pv_list($v, int $count, int $max): array { return array_values(array_filter(array_map(function ($x) use ($max) { return pv_s(is_array($x) ? ($x['name'] ?? '') : $x, $max); }, array_slice((array)$v, 0, $count)), 'strlen')); }

// Builds and stores a preview from what the agent (or staff) gathered. → ['ok', 'token', 'url'] or ['ok' => false, 'error'].
function pv_create(array $in, string $via = 'agent'): array {
    $biz = pv_s($in['business'] ?? '', 80);
    if (mb_strlen($biz) < 2) return ['ok' => false, 'error' => 'needs the business name'];
    $niche = pv_s($in['niche'] ?? '', 60);
    $theme = pv_theme_for($niche . ' ' . $biz, (string)($in['theme'] ?? ''));
    $phone = preg_replace('/[^0-9+()\-. ]/', '', (string)($in['phone'] ?? ''));
    $phone = strlen(preg_replace('/\D/', '', $phone)) >= 10 ? mb_substr(trim($phone), 0, 24) : '';
    $svc = [];
    foreach (array_slice((array)($in['services'] ?? []), 0, 8) as $x) {
        $name = pv_s(is_array($x) ? ($x['name'] ?? '') : $x, 60);
        if ($name !== '') $svc[] = ['name' => $name, 'text' => is_array($x) ? pv_s($x['text'] ?? '', 160) : ''];
    }
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string)($in['color'] ?? '')) ? strtolower($in['color']) : '';
    $site = pv_s($in['website'] ?? '', 200);
    $d = ['business' => $biz, 'niche' => $niche ?: PV_THEMES[$theme]['label'], 'city' => pv_s($in['city'] ?? '', 60), 'phone' => $phone,
        'tagline' => pv_s($in['tagline'] ?? '', 90), 'about' => pv_s($in['about'] ?? '', 600), 'services' => $svc,
        'areas' => pv_list($in['areas'] ?? [], 14, 40), 'hours' => pv_list($in['hours'] ?? [], 8, 60), 'color' => $color, 'first_name' => pv_s($in['contact_name'] ?? '', 40)];
    if (!$svc && $theme !== 'studio') return ['ok' => false, 'error' => 'needs at least one service (from their own site or listing)'];
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $token = bin2hex(random_bytes(16));
    q('INSERT INTO previews (token, business, email, website, theme, data, via, created_at, expires_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$token, $biz, filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '', $site, $theme, json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $via, time(), time() + PV_DAYS * 86400]);
    return ['ok' => true, 'token' => $token, 'url' => pv_url(['token' => $token]), 'theme' => $theme, 'expires' => date('F j, Y', time() + PV_DAYS * 86400)];
}
function pv_url(array $r, string $extra = ''): string { return abs_url('preview.php?t=' . $r['token'] . $extra); }
function pv_row(string $t): ?array { return preg_match('/^[a-f0-9]{32}$/', $t) ? one('SELECT * FROM previews WHERE token = ?', [$t]) : null; }
function pv_live(array $r): bool { return !$r['removed_at'] && (int)$r['expires_at'] > time(); }

// The link to "make it real": the live Starter offer page for most, the game studio page for studios.
function pv_get_url(array $r, array $d): string {
    $site = shop_site_url() ?: 'https://syndicatemarketing.co';
    if ($r['theme'] === 'studio') return $site . '/websites-for-game-studios.html?utm_source=preview';
    return $site . '/starter-website-offer.html?b=' . rawurlencode($d['business']) . ($d['first_name'] !== '' ? '&n=' . rawurlencode($d['first_name']) : '') . '&utm_source=preview';
}

// A real browser showed the page (pv.js). The first time, a task, because that's a warm moment to follow up.
function pv_seen(array $r): void {
    q('UPDATE previews SET views = views + 1, viewed_at = ? WHERE id = ?', [time(), $r['id']]);
    if ((int)$r['views'] === 0) {
        $p = $r['email'] !== '' ? one('SELECT id FROM outreach_prospects WHERE email = ? ORDER BY id DESC LIMIT 1', [$r['email']]) : null;
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', [$r['business'] . ' opened the homepage preview we made them', today(), '', null, now()]);
        if ($p) { require_once __DIR__ . '/outreach.php'; out_log((int)$p['id'], 'note', 'Opened their homepage preview.'); }
    }
}
// The owner asked us to take it down (and maybe never email them again).
function pv_remove(array $r, bool $stop): void {
    q("UPDATE previews SET removed_at = ?, removed_by = 'owner' WHERE id = ?", [time(), $r['id']]);
    if ($stop && $r['email'] !== '') {
        require_once __DIR__ . '/outreach.php';
        $dom = substr(strrchr($r['email'], '@') ?: '', 1);
        q('INSERT INTO outreach_optout (email, domain, reason, created_at) VALUES (?,?,?,?)', [$r['email'], out_business_domain($dom) ? $dom : '', 'Removed their homepage preview and asked not to be contacted', now()]);
        q("UPDATE outreach_prospects SET status = 'unsubscribed', next_at = NULL, updated_at = ? WHERE email = ? AND status IN ('queued','active','replied','paused')", [now(), $r['email']]);
    }
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') send_mail($to, '[Preview removed] ' . $r['business'], $r['business'] . ' removed the homepage preview we made them' . ($stop ? ' and asked not to be contacted again (they’re on the do-not-contact list now).' : '.') . "\n");
}
function pv_cleanup(): void { q('DELETE FROM previews WHERE expires_at < ?', [time() - 60 * 86400]); }

/* ---------- The page ---------- */
function pv_icon(string $theme): string {
    $p = ['trades' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>', 'food' => '<path d="M4 11h16a8 8 0 0 1-16 0zM8 4c0 1.5 1 1.5 1 3M12 3c0 1.5 1 1.5 1 3M16 4c0 1.5 1 1.5 1 3"/>',
        'health' => '<path d="M12 21s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.6-7 10-7 10z"/>', 'shop' => '<path d="M5 8h14l-1 12H6zM9 8V6a3 3 0 0 1 6 0v2"/>',
        'studio' => '<rect x="3" y="7" width="18" height="11" rx="4"/><path d="M8 11v3M6.5 12.5h3M15 12h.01M17 14h.01"/>', 'general' => '<path d="M5 12l4 4 10-10"/>'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$theme] ?? $p['general']) . '</svg>';
}
function pv_render(array $r): void {
    $d = json_decode($r['data'], true) ?: [];
    $t = PV_THEMES[$r['theme']] ?? PV_THEMES['general'];
    $acc = $d['color'] ?: $t['accent']; $dark = !empty($t['dark']);
    $tel = $d['phone'] !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $d['phone']) : '';
    $area = $d['areas'] ? ' Serving ' . implode(', ', array_slice($d['areas'], 0, 4)) . (count($d['areas']) > 4 ? ' and nearby.' : '.') : ($d['city'] !== '' ? ' Serving ' . $d['city'] . ' and nearby.' : '');
    $lede = str_replace(['%phone%', '%area%'], [$d['phone'] !== '' ? $d['phone'] : 'us', $area], $t['lede']);
    if ($d['phone'] === '') $lede = str_replace(['Call us or request', ' or call us.'], ['Request', '.'], $lede);
    $eyebrow = implode(' · ', array_filter([$d['niche'], $d['city']]));
    $get = pv_get_url($r, $d);
    $nameCss = $t['font'] === 'serif' ? 'font-family:"Instrument Serif",Georgia,serif;font-weight:400;letter-spacing:-.01em' : 'font-weight:800;letter-spacing:-.035em';
    header('X-Robots-Tag: noindex, nofollow');
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($d['business']) ?> — free homepage concept by <?= e(SHOP_BRAND) ?></title>
<link rel="stylesheet" href="assets/css/fonts.css">
<style>
:root{--acc:<?= e($acc) ?>;--ink:<?= e($t['ink']) ?>;--bg:<?= e($t['bg']) ?>;--card:<?= $dark ? '#151b24' : '#fff' ?>;--text:<?= $dark ? '#e9eef5' : e($t['ink']) ?>;--muted:<?= $dark ? '#9aa6b5' : '#5b6470' ?>;--line:<?= $dark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.09)' ?>}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.6 "Inter Tight",system-ui,-apple-system,"Segoe UI",sans-serif}
a{color:inherit}.w{max-width:1120px;margin:0 auto;padding:0 20px}
.pv-bar{position:sticky;top:0;z-index:20;background:#0b0d10;color:#eef1f4;font-size:14px}.pv-bar .w{display:flex;gap:10px 18px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding-top:10px;padding-bottom:10px}
.pv-bar b{color:#c6ff3d}.pv-bar .act{display:flex;gap:10px;flex-wrap:wrap}.pv-bar a{color:#eef1f4}.pv-bar .go{background:#c6ff3d;color:#0d0f0c;text-decoration:none;font-weight:700;padding:7px 14px;border-radius:999px}.pv-bar .rm{opacity:.75}
header.top .w{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:18px;padding-bottom:18px}
.logo{font-size:22px;<?= $nameCss ?>;text-decoration:none}.nav{display:flex;gap:22px;align-items:center;font-size:15px}.nav a{text-decoration:none;color:var(--muted)}.nav a.btn{color:<?= $dark ? '#0c1016' : '#fff' ?>}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--acc);color:<?= $dark ? '#0c1016' : '#fff' ?>;text-decoration:none;font-weight:700;padding:13px 22px;border-radius:12px;border:0;font:inherit;font-weight:700;cursor:pointer}
.btn.o{background:transparent;color:var(--text);box-shadow:inset 0 0 0 1.5px var(--line)}
.hero .w{display:grid;grid-template-columns:1.25fr 1fr;gap:48px;align-items:center;padding-top:56px;padding-bottom:64px}
.eb{font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--acc)}
h1{font-size:clamp(38px,5.4vw,66px);line-height:1.02;margin:14px 0 18px;<?= $nameCss ?>}h2{font-size:clamp(26px,3vw,38px);line-height:1.1;margin:0 0 18px;<?= $nameCss ?>}
.lede{font-size:19px;color:var(--muted);max-width:560px;margin:0 0 26px}.row{display:flex;gap:12px;flex-wrap:wrap}
.panel{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:28px;box-shadow:0 30px 60px -30px rgba(0,0,0,.35)}
.panel .ic{width:48px;height:48px;border-radius:14px;background:var(--acc);color:<?= $dark ? '#0c1016' : '#fff' ?>;display:grid;place-items:center;margin-bottom:16px}.panel .ic svg{width:26px;height:26px}
.panel b{font-size:20px;display:block}.panel p{color:var(--muted);margin:6px 0 16px}.panel ul{list-style:none;margin:0;padding:0;display:grid;gap:8px}.panel li{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding-top:8px;font-size:15px}
section{padding:56px 0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:22px}.card .ic{color:var(--acc)}.card .ic svg{width:26px;height:26px}.card b{display:block;font-size:18px;margin:10px 0 4px}.card span{color:var(--muted);font-size:15px}
.split{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}.chips{display:flex;flex-wrap:wrap;gap:8px;padding:0;list-style:none;margin:0}.chips li{background:var(--card);border:1px solid var(--line);border-radius:999px;padding:6px 14px;font-size:15px}
form.f{display:grid;gap:12px}form.f label{display:grid;gap:6px;font-size:14px;font-weight:600}form.f input,form.f textarea{font:inherit;padding:12px 14px;border-radius:10px;border:1px solid var(--line);background:<?= $dark ? '#0f141b' : '#fff' ?>;color:var(--text)}
.note{font-size:13px;color:var(--muted)}.demo{background:rgba(127,127,127,.12);border-radius:10px;padding:10px 12px;font-size:14px}
footer{border-top:1px solid var(--line);padding:28px 0 90px;color:var(--muted);font-size:14px}
.callbar{position:fixed;left:12px;right:12px;bottom:12px;display:none;z-index:15}.callbar a{width:100%;justify-content:center;box-shadow:0 12px 30px -10px rgba(0,0,0,.4)}
@media(max-width:820px){.hero .w,.split{grid-template-columns:1fr}.nav a:not(.btn){display:none}<?= $tel !== '' ? '.callbar{display:block}' : '' ?>}
</style></head><body>
<div class="pv-bar" role="note"><div class="w"><span><b>Free concept</b> · We made this for <?= e($d['business']) ?> to show what a new homepage could look like. It isn’t their real website and nothing on it sends anything.</span>
  <span class="act"><a class="go" href="<?= e($get) ?>">Make it your real site</a><a href="<?= e(abs_url('preview.php?t=' . $r['token'] . '&remove=1')) ?>" class="rm">Remove this preview</a></span></div></div>
<header class="top"><div class="w"><a class="logo" href="#top"><?= e($d['business']) ?></a>
  <nav class="nav"><?= $d['services'] ? '<a href="#services">' . e($t['svc']) . '</a>' : '' ?><?= $d['about'] !== '' ? '<a href="#about">About</a>' : '' ?><a href="#contact">Contact</a><?= $tel !== '' ? '<a class="btn" href="' . e($tel) . '">' . e($d['phone']) . '</a>' : '' ?></nav></div></header>
<main id="top">
<div class="hero"><div class="w">
  <div><div class="eb"><?= e($eyebrow) ?></div><h1><?= e($d['tagline'] !== '' ? $d['tagline'] : $d['business']) ?></h1><p class="lede"><?= e($lede) ?></p>
    <div class="row"><?php if ($r['theme'] === 'trades' || ($r['theme'] === 'general' && $tel !== '')): ?><?= $tel !== '' ? '<a class="btn" href="' . e($tel) . '">Call ' . e($d['phone']) . '</a>' : '' ?><a class="btn<?= $tel !== '' ? ' o' : '' ?>" href="#contact"><?= e($t['cta2']) ?></a>
    <?php elseif ($r['theme'] === 'health'): ?><a class="btn" href="#contact"><?= e($t['cta']) ?></a><?= $tel !== '' ? '<a class="btn o" href="' . e($tel) . '">' . e($t['cta2']) . '</a>' : '' ?>
    <?php else: ?><a class="btn" href="#<?= $r['theme'] === 'food' ? 'services' : 'contact' ?>"><?= e($t['cta']) ?></a><a class="btn o" href="#contact"><?= e($t['cta2']) ?></a><?php endif; ?></div></div>
  <div class="panel"><div class="ic"><?= pv_icon($r['theme']) ?></div>
    <?php if ($d['hours']): ?><b>Hours</b><p><?= e($d['city'] !== '' ? $d['city'] : 'Open') ?></p><ul><?php foreach ($d['hours'] as $h): $parts = strpos($h, ':') !== false && !preg_match('/^\d/', $h) ? array_map('trim', explode(':', $h, 2)) : [$h]; ?><li><span><?= e($parts[0]) ?></span><span><?= e($parts[1] ?? '') ?></span></li><?php endforeach; ?></ul>
    <?php elseif ($d['areas']): ?><b>Where we work</b><p>Close to home, wherever you are in the area.</p><ul><?php foreach (array_slice($d['areas'], 0, 6) as $a): ?><li><span><?= e($a) ?></span><span>✓</span></li><?php endforeach; ?></ul>
    <?php else: ?><b><?= e($t['form']) ?></b><p><?= e($t['formNote']) ?></p><a class="btn" href="#contact"><?= e($t['cta']) ?></a><?php endif; ?>
  </div>
</div></div>
<?php if ($d['services']): ?><section id="services"><div class="w"><h2><?= e($t['svc']) ?></h2><div class="grid">
  <?php foreach ($d['services'] as $s): ?><div class="card"><span class="ic"><?= pv_icon($r['theme']) ?></span><b><?= e($s['name']) ?></b><?= $s['text'] !== '' ? '<span>' . e($s['text']) . '</span>' : '' ?></div><?php endforeach; ?>
</div></div></section><?php endif; ?>
<?php if ($d['about'] !== '' || $d['areas']): ?><section id="about"><div class="w<?= $d['about'] !== '' && $d['areas'] ? ' split' : '' ?>">
  <?php if ($d['about'] !== ''): ?><div><h2>About <?= e($d['business']) ?></h2><p class="lede" style="font-size:17px"><?= e($d['about']) ?></p></div><?php endif; ?>
  <?php if ($d['areas']): ?><div><h2>Areas we serve</h2><ul class="chips"><?php foreach ($d['areas'] as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
</div></section><?php endif; ?>
<section id="contact"><div class="w split">
  <div><h2><?= e($t['form']) ?></h2><p class="lede" style="font-size:17px"><?= e($t['formNote']) ?></p><?= $tel !== '' ? '<p><a class="btn" href="' . e($tel) . '">Call ' . e($d['phone']) . '</a></p>' : '' ?></div>
  <form class="f card" aria-describedby="demo-note"><p class="demo" id="demo-note">This is a demo form on a concept page. It doesn’t send anything.</p>
    <label>Name<input name="n" autocomplete="off" disabled></label><label>Phone or email<input name="c" autocomplete="off" disabled></label><label>How can we help?<textarea rows="3" disabled></textarea></label>
    <button class="btn" type="button" disabled style="opacity:.6;cursor:not-allowed">Send</button></form>
</div></section>
</main>
<footer><div class="w"><?= e($d['business']) ?><?= $d['city'] !== '' ? ' · ' . e($d['city']) : '' ?> &nbsp;·&nbsp; Concept page made by <a href="<?= e(shop_site_url() ?: 'https://syndicatemarketing.co') ?>/?utm_source=preview"><?= e(SHOP_BRAND) ?></a> from public details. Not affiliated with or endorsed by <?= e($d['business']) ?>.</div></footer>
<?php if ($tel !== ''): ?><div class="callbar"><a class="btn" href="<?= e($tel) ?>">Call <?= e($d['phone']) ?></a></div><?php endif; ?>
<script src="assets/js/pv.js" data-t="<?= e($r['token']) ?>" defer></script>
</body></html><?php
}
