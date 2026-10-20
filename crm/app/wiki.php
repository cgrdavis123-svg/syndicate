<?php
// The wiki / help center (wiki.stusyn.com): public help articles for clients and team-only how-tos.
// Articles start as Markdown files in app/wiki-content/ and are edited in the browser after that.
// Prices and package details come from the live product list through {{tags}}, so they never drift.

const WIKI_CATEGORIES = [
    'start' => ['Getting started', 'How working with us goes, from first call to launch.'],
    'websites' => ['Websites', 'Packages, timelines, what we need from you, domains and ownership.'],
    'ads' => ['Paid ads', 'Google, Meta and TikTok ads: plans, budgets, access, results and reports.'],
    'community' => ['Community management', 'Discord server setup, monthly community management, moderation, access and reports.'],
    'care' => ['Website care', 'Monitoring, updates, backups, edits and the monthly care report.'],
    'analytics' => ['Site Stats analytics', 'Your visitor dashboard, goals, speed numbers and privacy.'],
    'portal' => ['Your client portal', 'Signing in, projects, files, approvals and messages.'],
    'billing' => ['Billing & plans', 'Payments, invoices, monthly plans, cancelling and refunds.'],
    'services' => ['Audits & consulting', 'Audits, strategy sessions and TikTok Shop consulting.'],
    'policies' => ['Policies & privacy', 'Ownership, privacy, security and our promises.'],
    'glossary' => ['Glossary', 'Plain-English meanings of the terms you’ll see.'],
];
const WIKI_TEAM_CATEGORIES = [
    'team-daily' => ['Running the business', 'The daily and monthly routine.'],
    'team-clients' => ['Client work', 'Onboarding and running each kind of client.'],
    'team-systems' => ['Systems & setup', 'Deploys, keys, Stripe, cron and the apps.'],
    'team-rules' => ['Rules & voice', 'Honesty, privacy, prices and how we write.'],
    'team-fixes' => ['When something breaks', 'Quick fixes for common problems.'],
];
function wiki_categories(bool $team = false): array { return $team ? WIKI_TEAM_CATEGORIES : WIKI_CATEGORIES; }
function wiki_cat_name(string $k): string { return (WIKI_CATEGORIES + WIKI_TEAM_CATEGORIES)[$k][0] ?? ucfirst($k); }
function wiki_slug(string $s): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-'); }
function wiki_link(array $a): string { return wiki_url($a['slug']); }

// The signed-in staff member, without starting a session (and a cookie) for every public visitor.
function wiki_staff(): ?array { return isset($_COOKIE['syn_crm']) ? current_user() : null; }

/* ---------- Seeding from app/wiki-content ---------- */
// Files: app/wiki-content/{public,team}/<slug>.md with front matter between --- lines.
// New files are added; changed files update their article unless someone edited it in the browser.
function wiki_seed(bool $force = false): int {
    $dir = __DIR__ . '/wiki-content';
    $files = array_merge(glob("$dir/public/*.md") ?: [], glob("$dir/team/*.md") ?: []);
    $key = count($files) . ':' . ($files ? max(array_map('filemtime', $files)) : 0) . ':' . md5(implode('|', $files));
    if (!$force && setting('wiki_seed_key') === $key) return 0;
    $n = 0;
    foreach ($files as $f) {
        $raw = (string)file_get_contents($f); $hash = md5($raw);
        $meta = []; $body = $raw;
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
            foreach (explode("\n", $m[1]) as $line) if (strpos($line, ':') !== false) { [$k, $v] = explode(':', $line, 2); $meta[trim($k)] = trim($v); }
            $body = $m[2];
        }
        $slug = wiki_slug(basename($f, '.md'));
        $audience = basename(dirname($f)) === 'team' ? 'team' : 'public';
        $row = one('SELECT id, seed_hash, edited FROM wiki_articles WHERE slug = ?', [$slug]);
        $vals = [$meta['title'] ?? $slug, $meta['summary'] ?? '', $meta['category'] ?? 'start', $audience, trim($body) . "\n", (int)($meta['order'] ?? 100), $meta['keywords'] ?? '', $hash];
        if (!$row) { q('INSERT INTO wiki_articles (title, summary, category, audience, body, position, keywords, seed_hash, slug, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$slug, now(), now()])); $n++; }
        elseif (!$row['edited'] && $row['seed_hash'] !== $hash) { q('UPDATE wiki_articles SET title = ?, summary = ?, category = ?, audience = ?, body = ?, position = ?, keywords = ?, seed_hash = ?, updated_at = ? WHERE id = ?', array_merge($vals, [now(), $row['id']])); $n++; }
    }
    setting('wiki_seed_key', $key);
    return $n;
}

/* ---------- Live tags ---------- */
function wiki_product(string $sku): ?array { static $c = []; if (!array_key_exists($sku, $c)) $c[$sku] = one('SELECT * FROM products WHERE sku = ?', [$sku]); return $c[$sku]; }
function wiki_price(array $p): string { $v = (float)$p['price']; $s = '$' . number_format($v, floor($v) == $v ? 0 : 2); return $s . ($p['interval'] === 'month' ? '/month' : '') . ($p['unit'] !== '' ? ' ' . $p['unit'] : ''); }
function wiki_timeline(array $p): string {
    foreach (explode("\n", (string)$p['features']) as $f) if (preg_match('/^Live in (about .+)$/i', trim($f), $m)) return $m[1];
    $d = (int)$p['timeline_days']; return $d ? ($d <= 10 ? "$d days" : 'about ' . round($d / 7) . ' weeks') : '';
}
// Replaces {{tags}}. Inline tags become Markdown; block tags become placeholders filled in after rendering.
function wiki_tags(string $md, array &$blocks): string {
    return preg_replace_callback('/\{\{\s*([a-z]+)(?::([a-z0-9\-]+))?\s*\}\}/i', function ($m) use (&$blocks) {
        $t = strtolower($m[1]); $arg = $m[2] ?? '';
        $p = $arg !== '' ? wiki_product($arg) : null;
        switch ($t) {
            case 'price': return $p ? wiki_price($p) : $m[0];
            case 'amount': return $p ? number_format((float)$p['price'], 0) : $m[0];
            case 'name': return $p ? $p['name'] : $m[0];
            case 'timeline': return $p ? wiki_timeline($p) : $m[0];
            case 'features':
                if (!$p) return $m[0];
                $blocks[] = '<ul class="w-check">' . implode('', array_map(function ($f) { return '<li>' . e($f) . '</li>'; }, array_filter(array_map('trim', explode("\n", (string)$p['features']))))) . '</ul>';
                return "\n\x01" . (count($blocks) - 1) . "\x01\n";
            case 'products':
                $rows = all('SELECT * FROM products WHERE category = ? AND active = 1 ORDER BY position', [$arg]);
                if (!$rows) return $m[0];
                $blocks[] = '<div class="w-table"><table><thead><tr><th>Package</th><th>Price</th><th>What it is</th></tr></thead><tbody>' . implode('', array_map(function ($p) {
                    return '<tr><td><b>' . e($p['name']) . '</b></td><td class="w-num">' . e(wiki_price($p)) . '</td><td>' . e($p['blurb']) . '</td></tr>'; }, $rows)) . '</tbody></table></div>';
                return "\n\x01" . (count($blocks) - 1) . "\x01\n";
            case 'email': return '[' . setting('business_email') . '](mailto:' . setting('business_email') . ')';
            case 'book':
                $names = ['discovery-call' => 'Book a free discovery call', 'kickoff' => 'Book your kickoff call', 'tiktok-shop-consultation' => 'Book a TikTok Shop consultation'];
                return '[' . ($names[$arg] ?? 'Book a call') . '](' . abs_url('book.php?type=' . rawurlencode($arg ?: 'discovery-call')) . ')';
            case 'portal': return '[your client portal](' . abs_url('portal-login.php') . ')';
            case 'plans': return '[Plans & billing](' . abs_url('portal-login.php?to=plans') . ')';
            case 'adsportal': return '[your ads portal](' . ads_url('portal-login.php') . ')';
            case 'store': return '[' . preg_replace('#^https?://#', '', setting('shop_site_url') ?: 'syndicatemarketing.co') . '](' . (setting('shop_site_url') ?: 'https://syndicatemarketing.co') . ')';
            case 'pricing': return '[see every price](' . (setting('shop_site_url') ?: 'https://syndicatemarketing.co') . '/pricing.html)';
            case 'terms': return '[terms of sale](' . (setting('shop_site_url') ?: 'https://syndicatemarketing.co') . '/terms.html)';
        }
        return $m[0];
    }, $md);
}

/* ---------- Markdown (a small, safe subset) ---------- */
// Headings (## and ###), paragraphs, lists, tables, > callouts, ``` code, ---, **bold**, *italic*, `code`,
// [links](url) and [[article-slug]] / [[article-slug|text]] links between articles. All text is escaped first.
function wiki_inline(string $s): string {
    $s = e($s);
    $codes = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) { $codes[] = '<code>' . $m[1] . '</code>'; return "\x02" . (count($codes) - 1) . "\x02"; }, $s);
    $s = preg_replace_callback('/\[\[([a-z0-9\-]+)(?:\|([^\]]+))?\]\]/', function ($m) {
        $a = one('SELECT slug, title FROM wiki_articles WHERE slug = ?', [$m[1]]);
        return '<a href="' . e(wiki_url($m[1])) . '"' . ($a ? '' : ' class="w-missing"') . '>' . ($m[2] ?? ($a ? e($a['title']) : $m[1])) . '</a>';
    }, $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $u = html_entity_decode($m[2], ENT_QUOTES);
        if (!preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $u)) $u = wiki_url($u);
        $ext = preg_match('#^https?://#i', $u) && stripos($u, (string)parse_url(wiki_url(), PHP_URL_HOST)) === false;
        return '<a href="' . e($u) . '"' . ($ext ? ' rel="noopener"' : '') . '>' . $m[1] . '</a>';
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s);
    return preg_replace_callback('/\x02(\d+)\x02/', function ($m) use ($codes) { return $codes[(int)$m[1]]; }, $s);
}
// Returns ['html' => ..., 'toc' => [[id, text], ...]].
function wiki_render(string $md): array {
    $blocks = [];
    $md = wiki_tags(str_replace("\r", '', $md), $blocks);
    $lines = explode("\n", $md); $out = ''; $toc = []; $para = []; $ids = [];
    $flush = function () use (&$para, &$out) { if ($para) { $out .= '<p>' . wiki_inline(implode(' ', $para)) . "</p>\n"; $para = []; } };
    for ($i = 0, $n = count($lines); $i < $n; $i++) {
        $line = $lines[$i]; $t = trim($line);
        if ($t === '') { $flush(); continue; }
        if (preg_match("/^\x01(\d+)\x01$/", $t, $m)) { $flush(); $out .= $blocks[(int)$m[1]] . "\n"; continue; }
        if (strpos($t, '```') === 0) {
            $flush(); $code = [];
            for ($i++; $i < $n && strpos(trim($lines[$i]), '```') !== 0; $i++) $code[] = $lines[$i];
            $out .= '<pre><code>' . e(implode("\n", $code)) . "</code></pre>\n"; continue;
        }
        if (preg_match('/^(#{1,3})\s+(.+)$/', $t, $m)) {
            $flush(); $lvl = strlen($m[1]) === 3 ? 3 : 2; $id = wiki_slug($m[2]) ?: 's'; $base = $id; $k = 2; while (isset($ids[$id])) $id = $base . '-' . $k++; $ids[$id] = 1;
            if ($lvl === 2) $toc[] = [$id, strip_tags(wiki_inline($m[2]))];
            $out .= "<h$lvl id=\"$id\">" . wiki_inline($m[2]) . "</h$lvl>\n"; continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})$/', $t)) { $flush(); $out .= "<hr>\n"; continue; }
        if ($t[0] === '|') {
            $flush(); $rows = [];
            for (; $i < $n && strpos(trim($lines[$i]), '|') === 0; $i++) $rows[] = array_map('trim', explode('|', trim(trim($lines[$i]), '|')));
            $i--;
            $head = array_shift($rows); if ($rows && preg_match('/^:?-+:?$/', $rows[0][0] ?? '')) array_shift($rows);
            $out .= '<div class="w-table"><table><thead><tr>' . implode('', array_map(function ($c) { return '<th>' . wiki_inline($c) . '</th>'; }, $head)) . '</tr></thead><tbody>'
                . implode('', array_map(function ($r) { return '<tr>' . implode('', array_map(function ($c) { return '<td>' . wiki_inline($c) . '</td>'; }, $r)) . '</tr>'; }, $rows)) . "</tbody></table></div>\n";
            continue;
        }
        if ($t[0] === '>') {
            $flush(); $q = [];
            for (; $i < $n && strpos(trim($lines[$i]), '>') === 0; $i++) $q[] = ltrim(substr(trim($lines[$i]), 1));
            $i--;
            $txt = implode(' ', $q);
            $kind = preg_match('/^\*\*(Heads up|Important|Warning)/i', $txt) ? 'warn' : (preg_match('/^\*\*(Tip|Good to know)/i', $txt) ? 'tip' : 'note');
            $out .= '<aside class="w-callout ' . $kind . '"><p>' . wiki_inline($txt) . "</p></aside>\n"; continue;
        }
        if (preg_match('/^([-*]|\d+[.)])\s+/', $t, $lm)) {
            $flush(); $ordered = !in_array($lm[1], ['-', '*'], true); $items = [];
            for (; $i < $n; $i++) {
                $l = $lines[$i]; $lt = trim($l);
                if ($lt === '') { if ($i + 1 < $n && preg_match('/^([-*]|\d+[.)])\s+/', trim($lines[$i + 1]))) continue; break; }
                if (preg_match('/^([-*]|\d+[.)])\s+(.*)$/', $lt, $mm) && strlen($l) - strlen(ltrim($l)) < 2) $items[] = $mm[2];
                elseif ($items && (strlen($l) - strlen(ltrim($l)) >= 2)) $items[count($items) - 1] .= ' ' . $lt;
                else break;
            }
            $i--;
            $tag = $ordered ? 'ol' : 'ul';
            $out .= "<$tag>" . implode('', array_map(function ($x) { return '<li>' . wiki_inline($x) . '</li>'; }, $items)) . "</$tag>\n"; continue;
        }
        $para[] = $t;
    }
    $flush();
    return ['html' => $out, 'toc' => $toc];
}
// Plain text of an article (for search snippets and descriptions).
function wiki_plain(string $md): string {
    $b = []; $md = wiki_tags($md, $b);
    $md = preg_replace("/\x01\d+\x01/", '', $md);
    $md = preg_replace('/\[\[([a-z0-9\-]+)\|([^\]]+)\]\]/', '$2', $md);
    $md = preg_replace('/\[\[([a-z0-9\-]+)\]\]/', '$1', $md);
    $md = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $md);
    $md = preg_replace('/[#>*`|_]+/', ' ', $md);
    return trim(preg_replace('/\s+/', ' ', $md));
}

/* ---------- Search ---------- */
function wiki_search(string $q, bool $team, int $limit = 25): array {
    $words = array_values(array_filter(preg_split('/[^a-z0-9$]+/', strtolower($q)), function ($w) { return strlen($w) > 1 && !in_array($w, ['the', 'and', 'for', 'how', 'do', 'does', 'can', 'what', 'is', 'my', 'to', 'of', 'a', 'an', 'in', 'on', 'i', 'you', 'your', 'it', 'with', 'are', 'we', 'our'], true); }));
    if (!$words) return [];
    $rows = all("SELECT * FROM wiki_articles WHERE status = 'published'" . ($team ? '' : " AND audience = 'public'"));
    $hits = [];
    foreach ($rows as $a) {
        $title = strtolower($a['title']); $sum = strtolower($a['summary'] . ' ' . $a['keywords']); $body = strtolower(wiki_plain($a['body']));
        $score = 0; $found = 0;
        foreach ($words as $w) {
            $stem = strlen($w) > 4 ? rtrim($w, 's') : $w;
            $in = 0;
            if (strpos($title, $stem) !== false) $in += 10;
            if (strpos($sum, $stem) !== false) $in += 5;
            $c = substr_count($body, $stem); if ($c) $in += min(6, 1 + $c);
            if ($in) $found++;
            $score += $in;
        }
        if (!$found || $found < max(1, (int)ceil(count($words) * .6))) continue;
        if (strpos($title, strtolower(trim($q))) !== false) $score += 15;
        $plain = wiki_plain($a['body']); $pos = false;
        foreach ($words as $w) if (($p = stripos($plain, $w)) !== false) { $pos = $p; break; }
        $snip = $pos === false ? mb_strimwidth($plain, 0, 180, '…') : (($pos > 60 ? '…' : '') . mb_strimwidth(mb_substr($plain, max(0, $pos - 60)), 0, 200, '…'));
        $hits[] = $a + ['score' => $score + ($found * 3), 'snippet' => $snip];
    }
    usort($hits, function ($x, $y) { return $y['score'] <=> $x['score'] ?: $y['views'] <=> $x['views']; });
    return array_slice($hits, 0, $limit);
}
function wiki_log_search(string $q, int $results): void {
    $q = mb_strtolower(mb_substr(trim(preg_replace('/\s+/', ' ', $q)), 0, 120));
    if (mb_strlen($q) < 3) return;
    if (one('SELECT 1 FROM wiki_searches WHERE q = ?', [$q])) q('UPDATE wiki_searches SET count = count + 1, results = ?, last_at = ? WHERE q = ?', [$results, now(), $q]);
    else q('INSERT INTO wiki_searches (q, results, count, last_at) VALUES (?,?,1,?)', [$q, $results, now()]);
}

/* ---------- Public forms (no session: same-site check + rate limits) ---------- */
function wiki_same_site(): bool {
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($o === '') return true; // some browsers send neither; rate limits still apply
    return strtolower((string)parse_url($o, PHP_URL_HOST)) === strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
}
function wiki_ask(string $name, string $email, string $question, string $page): array {
    $name = mb_substr(trim($name), 0, 120); $email = strtolower(mb_substr(trim($email), 0, 190)); $question = mb_substr(trim($question), 0, 4000);
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Enter your name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter an email address so we can reply.'];
    if (mb_strlen($question) < 8) return ['ok' => false, 'error' => 'Tell us a little more about your question.'];
    $c = one('SELECT * FROM contacts WHERE email = ? COLLATE NOCASE', [$email]);
    q('INSERT INTO wiki_questions (name, email, question, page, contact_id, created_at) VALUES (?,?,?,?,?,?)', [$name, $email, $question, mb_substr($page, 0, 200), $c['id'] ?? null, now()]);
    if ($c) log_activity('contact', (int)$c['id'], 'note', "Asked on the help center: $question");
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Answer help center question from ' . $name, today(), $c ? 'contact' : '', $c['id'] ?? null, now()]);
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') send_mail($to, '[Help center] Question from ' . $name, "$name <$email>" . ($c ? ' (existing contact)' : '') . " asked:\n\n$question\n\n" . ($page !== '' ? "From: $page\n" : '') . "\nIf the answer would help others, add it to the wiki: " . wiki_url('team.php') . "\n", $email);
    return ['ok' => true];
}

/* ---------- Public page shell ---------- */
function wiki_top(string $title, string $desc = '', array $o = []): void {
    $staff = $o['staff'] ?? null; $q = $o['q'] ?? ''; $canon = $o['canonical'] ?? ''; $index = $o['index'] ?? true;
    header_remove('X-Robots-Tag');
    if (!$index) header('X-Robots-Tag: noindex');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="<?= e(BASE) ?>">
<title><?= e($title) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= e(mb_strimwidth($desc, 0, 160, '…')) ?>"><?php endif; ?>
<?php if ($canon !== ''): ?><link rel="canonical" href="<?= e($canon) ?>"><?php endif; ?>
<?php if (!$index): ?><meta name="robots" content="noindex"><?php endif; ?>
<meta name="theme-color" content="#f5f4ef" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#0b0d10" media="(prefers-color-scheme: dark)">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="wiki.css">
</head>
<body>
<a class="w-skip" href="#main">Skip to content</a>
<header class="w-hdr"><div class="w-wrap w-hdr-in">
  <a class="w-logo" href="./"><svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0b0d10"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.8" stroke-linecap="round"/></svg><span>Syndicate <b>Help</b></span></a>
  <form class="w-search sm" action="search.php" method="get" role="search"><label class="w-sr" for="hq">Search the help center</label><input id="hq" name="q" type="search" value="<?= e($q) ?>" placeholder="Search help…" autocomplete="off"></form>
  <nav class="w-nav"><?php if ($staff): ?><a href="team.php">Team</a><?php endif; ?><a href="<?= e(abs_url('portal-login.php')) ?>">Client portal</a><a class="w-btn" href="ask.php">Ask us</a></nav>
</div></header>
<main id="main" class="w-main">
<?php }
function wiki_bottom(): void { ?>
</main>
<footer class="w-foot"><div class="w-wrap">
  <p><b>Didn’t find it?</b> <a href="ask.php">Ask us a question</a> and we’ll reply within one business day, or email <?= e(setting('business_email')) ?>.</p>
  <p class="w-dim"><?= e(setting('business_name')) ?> · Spartanburg, SC · <a href="https://syndicatemarketing.co">syndicatemarketing.co</a> · <a href="https://stusyn.com">stusyn.com</a></p>
</div></footer>
<script src="wiki.js" defer></script>
</body>
</html>
<?php }
