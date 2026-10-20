<?php
// Online store for the Syndicate Marketing website: the product catalog, Stripe Checkout, and what
// happens in the CRM when someone pays (contact, won deal, paid invoice, projects, client portal, Site Stats).
// The website itself only holds the cart. Prices, Stripe keys and every order live here.
require_once __DIR__ . '/mail.php';      // encrypts the Stripe keys with storage/mail.key
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/analytics.php';

const SHOP_BRAND = 'Syndicate Marketing';
const SHOP_CATEGORIES = ['website' => 'Websites', 'ads' => 'Paid ads', 'care' => 'Care plans', 'community' => 'Community', 'service' => 'Audits & services', 'addon' => 'Add-ons'];
const SHOP_ONE_PER_ORDER = ['website' => 'one website package', 'ads' => 'one ads plan', 'care' => 'one care plan'];
const ORDER_STATUSES = ['pending' => 'Checkout started', 'processing' => 'Payment processing', 'paid' => 'Paid', 'expired' => 'Not finished', 'failed' => 'Payment failed', 'refunded' => 'Refunded'];
const SUB_STATUSES = ['active' => 'Active', 'trialing' => 'Trial', 'past_due' => 'Past due', 'unpaid' => 'Unpaid', 'canceled' => 'Cancelled', 'incomplete' => 'Incomplete', 'incomplete_expired' => 'Expired', 'paused' => 'Paused'];
const AD_PLATFORMS = ['google' => 'Google', 'meta' => 'Meta (Facebook & Instagram)', 'tiktok' => 'TikTok'];
const ADS_PLATFORM_COUNT = ['ads-one' => 1, 'ads-two' => 2, 'ads-all' => 3];
// Project brief questions, in the order they're shown on an order.
const BRIEF_LABELS = [
    'industry' => 'Industry', 'goal' => 'Main goal', 'pages' => 'Pages wanted', 'style' => 'Style and sites they like', 'has_domain' => 'Has a domain',
    'domain' => 'Domain', 'has_logo' => 'Has a logo', 'assets' => 'Photos and content', 'deadline' => 'Deadline', 'platforms' => 'Ad platforms',
    'ad_budget' => 'Monthly ad budget', 'ad_area' => 'Area to target', 'ad_accounts' => 'Existing ad accounts', 'audit_url' => 'Site or account to review',
    'audit_focus' => 'What to focus on', 'care_url' => 'Website to look after', 'care_platform' => 'Built with', 'care_access' => 'Has admin logins',
    'com_kind' => 'Community for', 'com_name' => 'Game, channel or team', 'com_link' => 'Discord invite or link', 'com_size' => 'Members today', 'notes' => 'Anything else',
];

/* ---------- Catalog ---------- */
function shop_products(bool $activeOnly = true): array {
    return all('SELECT * FROM products' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY position, id');
}
function product_lines(string $s): array { return array_values(array_filter(array_map('trim', explode("\n", $s)), 'strlen')); }
function product_public(array $p): array {
    return ['sku' => $p['sku'], 'category' => $p['category'], 'name' => $p['name'], 'blurb' => $p['blurb'], 'features' => product_lines($p['features']),
        'price' => (float)$p['price'], 'interval' => $p['interval'], 'unit' => $p['unit'], 'max_qty' => max(1, (int)$p['max_qty']),
        'requires' => array_values(array_filter(explode(',', $p['requires']))), 'badge' => $p['badge'], 'featured' => (bool)$p['featured'], 'timeline_days' => (int)$p['timeline_days'],
        'free_with' => array_values(array_filter(explode(',', (string)($p['free_with'] ?? ''))))];
}
function price_label(float $price, string $interval = '', string $unit = ''): string {
    return money($price, floor($price) != $price) . ($interval === 'month' ? '/mo' : '') . ($unit !== '' ? ' ' . $unit : '');
}

// The first step after an order: a link to book the kickoff call when online booking is set up.
function shop_kickoff_line(): string {
    $slug = (string)val("SELECT slug FROM meeting_types WHERE kind = 'kickoff' AND active = 1 ORDER BY position LIMIT 1");
    return $slug !== '' ? "Book your kickoff call at a time that suits you:\n     " . abs_url('book.php?type=' . rawurlencode($slug)) : 'We’ll email you within one business day to schedule a short kickoff call.';
}

/* ---------- Settings ---------- */
function shop_site_url(): string { return rtrim(setting('shop_site_url'), '/'); }
// Websites allowed to call the store: the shop's own domain, with and without www.
function shop_origins(): array {
    $u = parse_url(shop_site_url());
    if (empty($u['host'])) return [];
    $port = isset($u['port']) ? ':' . $u['port'] : '';
    $host = preg_replace('/^www\./i', '', strtolower($u['host']));
    $out = [];
    foreach (['https', 'http'] as $sch) foreach ([$host, 'www.' . $host] as $h) $out[] = $sch . '://' . $h . $port;
    return $out;
}
function shop_cors(bool $public = false): void {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && in_array(strtolower($origin), shop_origins(), true)) { header('Access-Control-Allow-Origin: ' . $origin); header('Vary: Origin'); }
    elseif ($public) header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}
function stripe_key(string $which = 'stripe_secret'): string {
    $v = setting($which);
    if ($v === '') return '';
    try { return mail_decrypt($v); } catch (Throwable $e) { error_log('Stripe key unreadable: ' . $e->getMessage()); return ''; }
}
function stripe_live(): bool { return strpos(stripe_key(), 'sk_live_') === 0 || strpos(stripe_key(), 'rk_live_') === 0; }
function shop_ready(): bool { return stripe_key() !== '' && shop_site_url() !== ''; }

/* ---------- Stripe API (plain HTTPS, no SDK needed) ---------- */
class StripeError extends RuntimeException {}
// Turns nested arrays into Stripe's form format: line_items[0][price_data][currency]=usd
function stripe_form(array $a, string $prefix = ''): array {
    $out = [];
    foreach ($a as $k => $v) {
        $key = $prefix === '' ? (string)$k : $prefix . '[' . $k . ']';
        if (is_array($v)) $out = array_merge($out, stripe_form($v, $key));
        elseif (is_bool($v)) $out[$key] = $v ? 'true' : 'false';
        elseif ($v !== null) $out[$key] = (string)$v;
    }
    return $out;
}
function stripe(string $method, string $path, array $params = [], string $idempotency = ''): array {
    $key = stripe_key();
    if ($key === '') throw new StripeError('Stripe isn’t connected yet. Add your secret key under Store → Settings.');
    $url = rtrim((string)(config('stripe_api') ?: 'https://api.stripe.com'), '/') . '/v1/' . ltrim($path, '/');
    $body = http_build_query(stripe_form($params), '', '&', PHP_QUERY_RFC3986);
    if ($method === 'GET' && $body !== '') { $url .= '?' . $body; $body = ''; }
    $headers = ['Authorization: Bearer ' . $key, 'Stripe-Version: 2024-06-20', 'Content-Type: application/x-www-form-urlencoded'];
    if ($idempotency !== '') $headers[] = 'Idempotency-Key: ' . $idempotency;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10]);
        if ($method !== 'GET') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new StripeError('Couldn’t reach Stripe: ' . $err);
    } else {
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 30, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) throw new StripeError('Couldn’t reach Stripe.');
        $code = preg_match('#HTTP/\S+ (\d{3})#', implode("\n", $http_response_header ?? []), $m) ? (int)$m[1] : 0;
    }
    $d = json_decode((string)$raw, true);
    if (!is_array($d)) throw new StripeError('Stripe sent an unreadable reply (HTTP ' . $code . ').');
    if ($code >= 400 || isset($d['error'])) throw new StripeError((string)($d['error']['message'] ?? 'Stripe error ' . $code));
    return $d;
}
// Checks the Stripe-Signature header. Returns the event, or null if it isn't genuine.
function stripe_verify(string $payload, string $header, string $secret, int $tolerance = 300): ?array {
    if ($secret === '' || $header === '') return null;
    $t = 0; $sigs = [];
    foreach (explode(',', $header) as $part) { [$k, $v] = array_pad(explode('=', trim($part), 2), 2, ''); if ($k === 't') $t = (int)$v; if ($k === 'v1') $sigs[] = $v; }
    if (!$t || !$sigs || abs(time() - $t) > $tolerance) return null;
    $expect = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) if (hash_equals($expect, $s)) { $ev = json_decode($payload, true); return is_array($ev) ? $ev : null; }
    return null;
}
function stripe_dashboard(string $kind, string $id, bool $live): string {
    return $id === '' ? '' : 'https://dashboard.stripe.com/' . ($live ? '' : 'test/') . $kind . '/' . rawurlencode($id);
}

/* ---------- Special offers ---------- */
// An offer sets a price and/or free months on named products, bought together. It can end on a date and be
// limited to a number of orders. Both limits are checked again at checkout, so the page can only say what's true.
// items: [{"sku": "web-business", "price": 500}, {"sku": "care-plus", "trial_months": 3}]
function offer_row(string $slug): ?array { return $slug === '' ? null : one('SELECT * FROM offers WHERE slug = ?', [$slug]); }
function offer_items(array $of): array {
    $out = [];
    foreach ((array)json_decode((string)$of['items'], true) as $i) {
        $sku = (string)($i['sku'] ?? ''); if ($sku === '') continue;
        $out[$sku] = ['price' => isset($i['price']) && $i['price'] !== '' ? max(0, (float)$i['price']) : null, 'trial_months' => max(0, min(12, (int)($i['trial_months'] ?? 0)))];
    }
    return $out;
}
function offer_used(array $of): int { return (int)val("SELECT COUNT(*) FROM orders WHERE offer = ? AND status IN ('paid','processing')", [$of['slug']]); }
// The last moment the offer can be bought: the end of its last day, in the CRM's time zone.
function offer_ends_at(array $of): ?int { return $of['ends_on'] ? strtotime($of['ends_on'] . ' 23:59:59') : null; }
// live, off (switched off), ended (past its date) or full (no spots left).
function offer_state(array $of): string {
    if (!(int)$of['active']) return 'off';
    if (($end = offer_ends_at($of)) && time() > $end) return 'ended';
    if ((int)$of['max_uses'] > 0 && offer_used($of) >= (int)$of['max_uses']) return 'full';
    return 'live';
}
function offer_trial_end(int $months, ?int $from = null): int { return strtotime('+' . $months . ' months', $from ?? time()); }
// What the website shows. Every number comes from here, so the page, the cart and Stripe agree.
function offer_public(array $of): array {
    $bySku = []; foreach (shop_products() as $p) $bySku[$p['sku']] = $p;
    $items = []; $today = 0; $list = 0; $value = 0; $monthly = 0; $trial = 0;
    foreach (offer_items($of) as $sku => $it) {
        $p = $bySku[$sku] ?? null; if (!$p) continue;
        $price = $it['price'] ?? (float)$p['price'];
        $items[] = ['sku' => $sku, 'name' => $p['name'], 'category' => $p['category'], 'interval' => $p['interval'], 'list_price' => (float)$p['price'], 'price' => $price, 'trial_months' => $it['trial_months']];
        if ($p['interval'] === 'month') {
            $monthly += $price; $trial = max($trial, $it['trial_months']);
            $value += (float)$p['price'] * max(1, $it['trial_months']);
            if (!$it['trial_months']) $today += $price;
        } else { $today += $price; $value += (float)$p['price']; }
        $list += (float)$p['price'];
    }
    $state = offer_state($of); $used = offer_used($of); $max = (int)$of['max_uses'];
    $end = offer_ends_at($of);
    return ['ok' => true, 'slug' => $of['slug'], 'name' => $of['name'], 'state' => $state, 'live' => $state === 'live', 'items' => $items,
        'today' => round($today, 2), 'monthly' => round($monthly, 2), 'trial_months' => $trial, 'value' => round($value, 2), 'save' => round(max(0, $value - $today), 2),
        'trial_ends' => $trial ? date('Y-m-d', offer_trial_end($trial)) : null, 'ends_on' => $of['ends_on'] ?: null, 'ends_label' => $end ? date('F j, Y', $end) : null,
        'spots' => $max ?: null, 'left' => $max ? max(0, $max - $used) : null];
}
function offer_closed_text(array $of, string $state): string {
    return $state === 'ended' ? 'This offer ended on ' . date('F j, Y', (int)offer_ends_at($of)) . '.' : ($state === 'full' ? 'All the spots for this offer have been taken.' : 'This offer isn’t available anymore.');
}

/* ---------- Cart ---------- */
// Prices a cart against the catalog, and an offer if there is one. Each line has the price charged today or
// monthly ('price'), the catalog price ('list_price') and any free months ('trial_months').
// Returns ['lines' => [...], 'one_time' => x, 'monthly' => y, 'today' => z, 'trial_months' => n, 'offer' => row|null, 'error' => ''].
function shop_price_cart(array $items, array $brief = [], string $offer = ''): array {
    $bySku = []; foreach (shop_products() as $p) $bySku[$p['sku']] = $p;
    $lines = []; $cats = [];
    foreach (array_slice($items, 0, 30) as $it) {
        $sku = is_array($it) ? (string)($it['sku'] ?? '') : '';
        $p = $bySku[$sku] ?? null;
        if (!$p) return ['error' => 'Something in your cart is no longer available. Remove it and try again.'];
        if (isset($lines[$sku])) continue;
        $qty = max(1, min((int)($it['qty'] ?? 1), max(1, (int)$p['max_qty'])));
        $lines[$sku] = ['product' => $p, 'qty' => $qty, 'price' => (float)$p['price'], 'list_price' => (float)$p['price'], 'trial_months' => 0];
        $cats[$p['category']] = ($cats[$p['category']] ?? 0) + 1;
    }
    if (!$lines) return ['error' => 'Your cart is empty.'];
    foreach (SHOP_ONE_PER_ORDER as $cat => $label) if (($cats[$cat] ?? 0) > 1) return ['error' => 'Choose ' . $label . ' per order.'];
    if (count(array_filter($lines, function ($l) { return $l['product']['category'] === 'community' && $l['product']['interval'] === 'month'; })) > 1) return ['error' => 'Choose one community management plan per order.'];
    // A care plan on its own is for a site someone else built, so we need to know which site.
    if ($brief && isset($cats['care']) && !isset($cats['website']) && !preg_match('#^(https?://)?[^\s/]+\.[a-z]{2,}#i', (string)($brief['care_url'] ?? ''))) return ['error' => 'Enter the address of the website you’d like us to look after.', 'field' => 'care_url'];
    foreach ($lines as $l) {
        $req = array_filter(explode(',', $l['product']['requires']));
        if ($req && !array_intersect($req, array_keys($cats))) return ['error' => $l['product']['name'] . ' is added to ' . implode(' or ', array_map(function ($c) { return strtolower(rtrim(SHOP_CATEGORIES[$c] ?? $c, 's')); }, $req)) . ' package. Add one, or remove ' . $l['product']['name'] . '.'];
        if (isset(ADS_PLATFORM_COUNT[$l['product']['sku']]) && $brief) {
            $need = ADS_PLATFORM_COUNT[$l['product']['sku']];
            $have = count(array_intersect((array)($brief['platforms'] ?? []), array_keys(AD_PLATFORMS)));
            if ($need < 3 && $have !== $need) return ['error' => 'Pick ' . ($need === 1 ? 'one ad platform' : 'two ad platforms') . ' for ' . $l['product']['name'] . '.'];
        }
    }
    $of = null; $trial = 0;
    if ($offer !== '') {
        $of = offer_row($offer);
        if (!$of) return ['error' => 'This offer isn’t available anymore. Your cart shows our regular prices.', 'offer_gone' => true];
        if (($st = offer_state($of)) !== 'live') return ['error' => offer_closed_text($of, $st) . ' Your cart shows our regular prices.', 'offer_gone' => true];
        $want = offer_items($of);
        $missing = array_diff(array_keys($want), array_keys($lines));
        if ($missing) {
            $names = array_map(function ($s) { return (string)val('SELECT name FROM products WHERE sku = ?', [$s]) ?: $s; }, array_keys($want));
            return ['error' => 'This offer is for ' . implode(' and ', $names) . ' together. Add them back, or remove the offer to see regular prices.', 'offer_gone' => true];
        }
        foreach ($want as $sku => $it) {
            if ($it['price'] !== null) $lines[$sku]['price'] = $it['price'];
            $lines[$sku]['trial_months'] = $lines[$sku]['product']['interval'] === 'month' ? $it['trial_months'] : 0;
            $trial = max($trial, $lines[$sku]['trial_months']);
        }
        // Free months are a Stripe trial on the whole subscription, so other monthly plans can't share the order.
        if ($trial) foreach ($lines as $l) if ($l['product']['interval'] === 'month' && !$l['trial_months'])
            return ['error' => $l['product']['name'] . ' can’t be in the same order as this offer’s free months. Remove it and add it later, or email ' . setting('business_email') . '.'];
    }
    // Included free when a package it comes with is in the same order (e.g. domain setup with the Business website).
    foreach ($lines as $sku => $l) if (array_intersect(array_filter(explode(',', (string)($l['product']['free_with'] ?? ''))), array_keys($lines))) $lines[$sku]['price'] = 0.0;
    $one = 0; $mo = 0; $now = 0;
    foreach ($lines as $l) {
        $amt = $l['price'] * $l['qty'];
        if ($l['product']['interval'] === 'month') { $mo += $amt; if (!$l['trial_months']) $now += $amt; } else { $one += $amt; $now += $amt; }
    }
    return ['lines' => array_values($lines), 'one_time' => round($one, 2), 'monthly' => round($mo, 2), 'today' => round($now, 2), 'trial_months' => $trial, 'offer' => $of, 'error' => ''];
}
function shop_clean_brief($in): array {
    $out = [];
    if (!is_array($in)) return $out;
    foreach ($in as $k => $v) {
        if (!isset(BRIEF_LABELS[$k])) continue;
        if (is_array($v)) { $v = array_values(array_slice(array_filter(array_map(function ($x) { return is_scalar($x) ? mb_substr(trim((string)$x), 0, 60) : ''; }, $v), 'strlen'), 0, 10)); if ($v) $out[$k] = $v; }
        elseif (is_scalar($v) && ($v = mb_substr(trim((string)$v), 0, 2000)) !== '') $out[$k] = $v;
    }
    if (isset($out['platforms'])) $out['platforms'] = array_values(array_intersect($out['platforms'], array_keys(AD_PLATFORMS)));
    if (isset($out['care_platform']) && !isset(CARE_PLATFORMS[$out['care_platform']])) unset($out['care_platform']);
    return $out;
}
function brief_text(array $brief): string {
    $rows = [];
    foreach (BRIEF_LABELS as $k => $l) if (isset($brief[$k])) $rows[] = $l . ': ' . (is_array($brief[$k]) ? implode(', ', array_map(function ($x) { return AD_PLATFORMS[$x] ?? $x; }, $brief[$k])) : $brief[$k]);
    return implode("\n", $rows);
}
function order_items(int $orderId): array { return all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]); }
function order_summary(array $items): string {
    return implode(', ', array_map(function ($i) { return ($i['quantity'] > 1 ? $i['quantity'] . ' × ' : '') . $i['name']; }, $items));
}
function new_order_ref(): string {
    do { $r = 'SM-' . strtoupper(base_convert((string)time(), 10, 36)) . '-' . strtoupper(bin2hex(random_bytes(2))); } while (val('SELECT 1 FROM orders WHERE ref = ?', [$r]));
    return $r;
}
function clean_domain(string $s): string {
    $d = strtolower(trim(preg_replace('#^(https?://)?(www\.)?#i', '', trim($s))));
    $d = preg_replace('#[/?\#].*$#', '', $d);
    return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $d) ? $d : '';
}

/* ---------- Checkout ---------- */
// Saves the order and opens a Stripe Checkout session. Returns ['ok' => true, 'url' => ..., 'ref' => ..., 'key' => ...] or ['ok' => false, 'error' => ...].
function shop_checkout(array $in): array {
    $c = is_array($in['customer'] ?? null) ? $in['customer'] : [];
    $str = function ($k, $max = 200) use ($c) { return mb_substr(trim((string)($c[$k] ?? '')), 0, $max); };
    $name = $str('name', 120); $email = strtolower($str('email', 190)); $phone = $str('phone', 40); $biz = $str('business', 150); $site = $str('website', 200);
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Enter your name.', 'field' => 'name'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.', 'field' => 'email'];
    if (mb_strlen($biz) < 2) return ['ok' => false, 'error' => 'Enter your business name.', 'field' => 'business'];
    if (empty($in['agree'])) return ['ok' => false, 'error' => 'Please agree to the terms of sale to continue.', 'field' => 'agree'];
    $brief = shop_clean_brief($in['brief'] ?? []);
    $offer = mb_substr(trim((string)($in['offer'] ?? '')), 0, 60);
    $cart = shop_price_cart(is_array($in['items'] ?? null) ? $in['items'] : [], $brief + ['platforms' => []], $offer);
    if ($cart['error'] !== '') return ['ok' => false, 'error' => $cart['error'], 'field' => $cart['field'] ?? '', 'offer_gone' => !empty($cart['offer_gone'])];
    $trialEnd = $cart['trial_months'] ? offer_trial_end($cart['trial_months']) : null;
    if (!shop_ready()) return ['ok' => false, 'error' => 'Online checkout isn’t switched on yet. Email ' . setting('business_email') . ' and we’ll send you a payment link.'];

    $ref = new_order_ref(); $key = bin2hex(random_bytes(16));
    q('INSERT INTO orders (ref, access_key, status, name, email, phone, business, website, brief, one_time, monthly, live, source, ip_hash, offer, trial_end, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$ref, $key, 'pending', $name, $email, $phone, $biz, $site, json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $cart['one_time'], $cart['monthly'], stripe_live() ? 1 : 0, mb_substr((string)($in['source'] ?? ''), 0, 120), ip_hash(),
         $cart['offer'] ? $cart['offer']['slug'] : '', $trialEnd, now(), now()]);
    $orderId = (int)db()->lastInsertId();
    // Proof of what was agreed (California asks for it to be kept for at least three years; orders are never deleted).
    q('UPDATE orders SET consent = ? WHERE id = ?', [json_encode(['at' => now(), 'ip_hash' => ip_hash(), 'terms' => shop_site_url() . '/terms.html', 'text' => 'Agreed to the terms of sale, including the refund terms'
        . ($cart['monthly'] > 0 ? ', and to be charged ' . money($cart['monthly']) . ' every month' . ($trialEnd ? ' starting ' . date('F j, Y', $trialEnd) . ', after ' . $cart['trial_months'] . ' free months,' : '') . ' until cancelled (cancel online in the client portal)' : '') . '.'
        . ($cart['offer'] ? ' Offer: ' . $cart['offer']['name'] . '.' : '')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $orderId]);
    $lineItems = [];
    foreach ($cart['lines'] as $l) {
        $p = $l['product'];
        q('INSERT INTO order_items (order_id, product_id, sku, name, category, price, interval, quantity, list_price, trial_months) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$orderId, $p['id'], $p['sku'], $p['name'], $p['category'], $l['price'], $p['interval'], $l['qty'], $l['list_price'], $l['trial_months']]);
        if ($l['price'] <= 0 && !$l['trial_months'] && $p['interval'] !== 'month') continue; // included free: recorded on the order, nothing to charge
        $label = $p['name'] . ($l['trial_months'] ? ' — first ' . $l['trial_months'] . ' months free' : ($l['price'] < $l['list_price'] ? ' (offer price, regularly ' . money($l['list_price']) . ')' : ''));
        $pd = ['currency' => 'usd', 'unit_amount' => (int)round($l['price'] * 100), 'product_data' => ['name' => $label, 'metadata' => ['sku' => $p['sku']]]];
        if ($p['blurb'] !== '') $pd['product_data']['description'] = mb_substr($p['blurb'], 0, 300);
        if ($p['interval'] === 'month') $pd['recurring'] = ['interval' => 'month'];
        $lineItems[] = ['price_data' => $pd, 'quantity' => $l['qty']];
    }
    $sub = $cart['monthly'] > 0;
    $site_url = shop_site_url();
    $params = [
        'mode' => $sub ? 'subscription' : 'payment',
        'line_items' => $lineItems,
        'customer_email' => $email,
        'client_reference_id' => $ref,
        'metadata' => ['order_ref' => $ref, 'business' => mb_substr($biz, 0, 100)],
        'success_url' => $site_url . '/thank-you.html?ref=' . rawurlencode($ref) . '&k=' . $key . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $site_url . '/checkout.html?cancelled=' . rawurlencode($ref),
        'billing_address_collection' => setting('shop_tax') === '1' ? 'required' : 'auto',
        'custom_text' => ['submit' => ['message' => ($trialEnd ? 'Your free months run until ' . date('F j, Y', $trialEnd) . '. We’ll email you a week before the first ' . money($cart['monthly']) . ' charge, and you can cancel online before then and pay nothing more. ' : '')
            . 'You’ll get a receipt by email, and a link to your ' . SHOP_BRAND . ' client portal to start your project.']],
    ];
    if ($cart['offer']) $params['metadata']['offer'] = $cart['offer']['slug'];
    if (setting('shop_promo') === '1' && !$cart['offer']) $params['allow_promotion_codes'] = true; // offers don't stack with promo codes
    if (setting('shop_tax') === '1') $params['automatic_tax'] = ['enabled' => true];
    if ($sub) $params['subscription_data'] = ['metadata' => ['order_ref' => $ref], 'description' => SHOP_BRAND . ' · ' . $biz . ' · ' . $ref];
    // Free months: one-off items are charged today, and monthly billing starts when the trial ends.
    if ($sub && $trialEnd) $params['subscription_data'] += ['trial_end' => $trialEnd, 'trial_settings' => ['end_behavior' => ['missing_payment_method' => 'cancel']]];
    else { $params['invoice_creation'] = ['enabled' => true, 'invoice_data' => ['metadata' => ['order_ref' => $ref], 'description' => SHOP_BRAND . ' order ' . $ref]];
           $params['payment_intent_data'] = ['metadata' => ['order_ref' => $ref], 'description' => SHOP_BRAND . ' · ' . $biz . ' · ' . $ref];
           $params['customer_creation'] = 'always'; }
    try { $s = stripe('POST', 'checkout/sessions', $params, 'checkout-' . $ref); }
    catch (Throwable $e) {
        error_log('Stripe checkout failed for ' . $ref . ': ' . $e->getMessage());
        q("UPDATE orders SET status = 'failed', updated_at = ? WHERE id = ?", [now(), $orderId]);
        return ['ok' => false, 'error' => 'We couldn’t open the secure payment page. Please try again in a minute, or email ' . setting('business_email') . '.'];
    }
    q('UPDATE orders SET stripe_session = ?, updated_at = ? WHERE id = ?', [(string)$s['id'], now(), $orderId]);
    return ['ok' => true, 'url' => (string)$s['url'], 'ref' => $ref, 'key' => $key];
}

/* ---------- After payment ---------- */
// Brings an order up to date with its Checkout session. Safe to call more than once.
function shop_sync_session(array $o, ?array $s = null): array {
    if ($o['stripe_session'] === '') return $o;
    if ($s === null) $s = stripe('GET', 'checkout/sessions/' . rawurlencode($o['stripe_session']));
    if (($s['id'] ?? '') !== $o['stripe_session']) return $o;
    $ps = (string)($s['payment_status'] ?? ''); $st = (string)($s['status'] ?? '');
    if ($st === 'complete' && in_array($ps, ['paid', 'no_payment_required'], true)) shop_fulfil($o, $s);
    elseif ($st === 'complete' && $ps === 'unpaid' && $o['status'] === 'pending') q("UPDATE orders SET status = 'processing', stripe_customer = ?, updated_at = ? WHERE id = ?", [(string)($s['customer'] ?? ''), now(), $o['id']]);
    elseif ($st === 'expired' && $o['status'] === 'pending') shop_expire($o);
    return one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
}

function shop_fulfil(array $o, array $s): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $claim = $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = ?, updated_at = ? WHERE id = ? AND status IN ('pending', 'processing', 'expired', 'failed')");
        $claim->execute([now(), now(), $o['id']]);
        if ($claim->rowCount() === 0) { $pdo->commit(); return; } // already done
        $items = order_items((int)$o['id']);
        $brief = json_decode($o['brief'], true) ?: [];
        $cents = function ($k, $src = null) use ($s) { $src = $src ?? $s; return round((float)($src[$k] ?? 0) / 100, 2); };
        $paid = $cents('amount_total'); $discount = $cents('amount_discount', $s['total_details'] ?? []); $tax = $cents('amount_tax', $s['total_details'] ?? []);
        $email = (string)($s['customer_details']['email'] ?? '') ?: $o['email'];
        $phone = $o['phone'] !== '' ? $o['phone'] : (string)($s['customer_details']['phone'] ?? '');
        $isSub = ($s['mode'] ?? '') === 'subscription';

        // Contact and company
        $contact = find_or_create_contact($o['name'], $o['email'], $o['business']);
        if ($phone !== '' && $contact['phone'] === '') q('UPDATE contacts SET phone = ?, updated_at = ? WHERE id = ?', [$phone, now(), $contact['id']]);
        $companyId = $contact['company_id'] ? (int)$contact['company_id'] : null;
        if ($companyId) {
            q("UPDATE companies SET brand = CASE WHEN brand = '' THEN ? ELSE brand END, website = CASE WHEN website = '' THEN ? ELSE website END, phone = CASE WHEN phone = '' THEN ? ELSE phone END, updated_at = ? WHERE id = ?",
                [SHOP_BRAND, $o['website'], $phone, now(), $companyId]);
        }
        $who = $o['business'] ?: person_name($contact);
        $summary = order_summary($items);

        // Won deal (reuses the deal from an unfinished checkout, if there is one)
        $title = 'Online order — ' . $who;
        $dealNote = "Order {$o['ref']}: $summary" . ($o['monthly'] > 0 ? "\nMonthly: " . money($o['monthly']) . '/mo' . ($o['trial_end'] ? ' from ' . date('M j, Y', (int)$o['trial_end']) . ' (free until then)' : '') : '')
            . ($o['offer'] !== '' ? "\nOffer: " . ((string)val('SELECT name FROM offers WHERE slug = ?', [$o['offer']]) ?: $o['offer']) : '');
        $deal = one("SELECT id FROM deals WHERE contact_id = ? AND source = 'Marketing site — checkout' AND stage IN ('new','contacted','qualified','proposal') ORDER BY id DESC LIMIT 1", [$contact['id']]);
        if ($deal) {
            $dealId = (int)$deal['id'];
            q("UPDATE deals SET title = ?, stage = 'won', value = ?, source = 'Marketing site — order', notes = ?, closed_at = ?, updated_at = ? WHERE id = ?", [$title, $paid, $dealNote, now(), now(), $dealId]);
        } else {
            q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at, closed_at) VALUES (?,?,?,?, 'won', ?, 'Marketing site — order', ?, ?, ?, ?)",
                [$title, $companyId, $contact['id'], SHOP_BRAND, $paid, $dealNote, now(), now(), now()]);
            $dealId = (int)db()->lastInsertId();
        }

        // Paid invoice, matching what Stripe charged
        q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, status, tax_rate, notes, paid_at, paid_method, share_token, stripe_invoice, created_at, updated_at) VALUES (?,?,?,?,?,?, 'paid', 0, ?, ?, 'Stripe', ?, ?, ?, ?)",
            [next_invoice_number(), SHOP_BRAND, $companyId, $contact['id'], today(), today(), 'Paid online · Order ' . $o['ref'], now(), bin2hex(random_bytes(16)), (string)($s['invoice'] ?? ''), now(), now()]);
        $invoiceId = (int)db()->lastInsertId();
        $pos = 0;
        foreach ($items as $it) {
            $free = (int)$it['trial_months'] > 0;
            $desc = $it['name'] . ($free ? ' — first ' . (int)$it['trial_months'] . ' months free, then ' . money((float)$it['price']) . '/month' : ($it['interval'] === 'month' ? ' — first month' : ''))
                . (!$free && $it['list_price'] !== null && (float)$it['list_price'] > (float)$it['price'] ? ((float)$it['price'] <= 0 ? ' (included with your package; regularly ' : ' (offer price; regularly ') . money((float)$it['list_price']) . ')' : '');
            q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$invoiceId, $desc, $it['quantity'], $free ? 0 : $it['price'], $pos++]);
        }
        if ($discount > 0) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$invoiceId, 'Discount', 1, -$discount, $pos++]);
        if ($tax > 0) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$invoiceId, 'Sales tax', 1, $tax, $pos++]);

        // Projects in the client portal: one per package or service; add-on steps join the package they belong to.
        $projects = [];
        $rush = (bool)array_filter($items, function ($i) { return $i['sku'] === 'add-rush'; });
        $prod = function ($it) { return one('SELECT * FROM products WHERE sku = ?', [$it['sku']]) ?: ['steps' => '', 'timeline_days' => 0, 'blurb' => '', 'site' => 0]; };
        foreach ($items as $it) {
            if (!in_array($it['category'], ['website', 'ads', 'service', 'community'], true)) continue;
            $p = $prod($it);
            $days = (int)$p['timeline_days']; if ($rush && $it['category'] === 'website') $days = (int)ceil($days / 2);
            $info = $p['blurb'] . ($rush && $it['category'] === 'website' ? ' Rush delivery.' : '') . "\nOrder " . $o['ref'] . '.';
            q("INSERT INTO projects (name, company_id, contact_id, deal_id, brand, status, progress, start_date, due_date, summary, shared, created_at, updated_at) VALUES (?,?,?,?,?, 'planning', 0, ?, ?, ?, 1, ?, ?)",
                [$it['name'] . ' — ' . $who, $companyId, $contact['id'], $dealId, SHOP_BRAND, today(), $days ? date('Y-m-d', strtotime("+$days days")) : null, $info, now(), now()]);
            $pid = (int)db()->lastInsertId();
            $projects[$it['category']] = $projects[$it['category']] ?? $pid;
            $projects['_all'][] = $pid;
            q('UPDATE order_items SET project_id = ? WHERE id = ?', [$pid, $it['id']]);
            foreach (product_lines($p['steps']) as $i => $step) q('INSERT INTO project_steps (project_id, title, position) VALUES (?,?,?)', [$pid, mb_substr($step, 0, 200), ($i + 1) * 10]);
        }
        foreach ($items as $it) {
            if ($it['category'] !== 'addon') continue;
            $steps = product_lines($prod($it)['steps']);
            if (!$steps) continue;
            $target = $it['sku'] === 'add-ads-setup' ? ($projects['ads'] ?? null) : ($projects['website'] ?? $projects['ads'] ?? $projects['service'] ?? null);
            if (!$target) {
                q("INSERT INTO projects (name, company_id, contact_id, deal_id, brand, status, progress, start_date, summary, shared, created_at, updated_at) VALUES (?,?,?,?,?, 'planning', 0, ?, ?, 1, ?, ?)",
                    [$it['name'] . ' — ' . $who, $companyId, $contact['id'], $dealId, SHOP_BRAND, today(), 'Order ' . $o['ref'] . '.', now(), now()]);
                $target = (int)db()->lastInsertId(); $projects['addon'] = $target; $projects['_all'][] = $target;
            }
            q('UPDATE order_items SET project_id = ? WHERE id = ?', [$target, $it['id']]);
            foreach ($steps as $i => $step) q('INSERT INTO project_steps (project_id, title, position) VALUES (?,?,?)', [$target, mb_substr($step, 0, 200), 15 + $i]); // right after kickoff
        }
        foreach ($projects['_all'] ?? [] as $pid) {
            q("INSERT INTO project_posts (project_id, author, kind, body, created_at) VALUES (?, 'staff', 'update', ?, ?)", [$pid,
                "Welcome! Your order is confirmed and we’re getting started. We’ll be in touch within one business day to schedule your kickoff call.\n\nThis is where you’ll see progress, review drafts, approve work and send us files. You can upload your logo, photos and anything else you’d like us to use right here.", now()]);
        }

        // Site Stats for the client's website
        $siteId = null;
        if (array_filter($items, function ($i) use ($prod) { return (int)$prod($i)['site'] === 1; })) {
            $dom = clean_domain((string)($brief['domain'] ?? '')) ?: clean_domain((string)($brief['care_url'] ?? '')) ?: clean_domain($o['website']);
            if ($dom !== '') {
                $existing = one("SELECT id FROM sites WHERE ',' || REPLACE(domains, ' ', '') || ',' LIKE ?", ['%,' . $dom . ',%']);
                if ($existing) $siteId = (int)$existing['id'];
                else {
                    q('INSERT INTO sites (name, company_id, domains, public_id, shared, monthly_report, created_at) VALUES (?,?,?,?,1,1,?)', [$who . ' website', $companyId, $dom, site_key(), now()]);
                    $siteId = (int)db()->lastInsertId();
                    foreach ([['Form sent', 'Form submit'], ['Phone call', 'Phone call'], ['Email click', 'Email click']] as [$n, $m]) q("INSERT INTO site_goals (site_id, name, kind, match) VALUES (?, ?, 'event', ?)", [$siteId, $n, $m]);
                }
            }
        }

        // Your to-dos
        foreach ($items as $i) if ($i['category'] === 'ads' && $companyId) { require_once __DIR__ . '/ads.php'; ads_enable_for($companyId, (int)$o['id'], $i['sku'], $brief, $siteId); }
        foreach ($items as $i) if (isset(CARE_TIERS[$i['sku']])) {
            require_once __DIR__ . '/care.php';
            care_enable_for($siteId, (int)$o['id'], $who, $i['sku'], $brief + ['built_by_us' => (bool)array_filter($items, function ($x) { return $x['category'] === 'website'; })]);
        }
        $task = function (string $t, int $days, int $prio, string $type = 'deal', ?int $id = null) use ($dealId) { q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [$t, date('Y-m-d', strtotime("+$days days")), $prio, $type, $id ?? $dealId, now()]); };
        $task('Schedule kickoff call with ' . person_name($contact) . ' (' . $o['ref'] . ')', 1, 1);
        if (isset($projects['ads']) || array_filter($items, function ($i) { return $i['category'] === 'ads'; })) $task('Get ad account access from ' . person_name($contact), 2, 1);
        if (!$siteId && array_filter($items, function ($i) use ($prod) { return (int)$prod($i)['site'] === 1; })) $task('Add Site Stats for ' . $who . ' once the domain is known', 7, 0);
        if (array_filter($items, function ($i) { return $i['category'] === 'community'; })) $task('Get Discord access from ' . person_name($contact) . ($brief['com_link'] ?? '' ? ' (' . $brief['com_link'] . ')' : '') . ': a staff role for us, or create the server', 1, 1);

        // Client portal access
        portal_enable((int)$contact['id'], true);

        q('UPDATE orders SET contact_id = ?, company_id = ?, deal_id = ?, invoice_id = ?, site_id = ?, paid_total = ?, discount = ?, tax = ?, stripe_customer = ?, stripe_subscription = ?, stripe_payment = ?, sub_status = ?, updated_at = ? WHERE id = ?',
            [$contact['id'], $companyId, $dealId, $invoiceId, $siteId, $paid, $discount, $tax, (string)($s['customer'] ?? ''), (string)($s['subscription'] ?? ''), (string)($s['payment_intent'] ?? ''), $isSub ? ($o['trial_end'] ? 'trialing' : 'active') : '', now(), $o['id']]);
        $log = 'Paid ' . money($paid) . ' online (order ' . $o['ref'] . '): ' . $summary . ($o['monthly'] > 0 ? ' · then ' . money($o['monthly']) . '/mo' . ($o['trial_end'] ? ' from ' . date('M j, Y', (int)$o['trial_end']) : '') : '') . ($o['offer'] !== '' ? ' · offer ' . $o['offer'] : '') . '.';
        log_activity('deal', $dealId, 'note', $log . ($brief ? "\n\nProject brief:\n" . brief_text($brief) : ''));
        log_activity('contact', (int)$contact['id'], 'note', $log);
        if ($companyId) log_activity('company', $companyId, 'note', $log);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $o = one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
    shop_mail_paid($o);
}

function shop_mail_paid(array $o): void {
    $c = one('SELECT * FROM contacts WHERE id = ?', [(int)$o['contact_id']]);
    $items = order_items((int)$o['id']);
    $lines = implode("\n", array_map(function ($i) { return '  ' . ($i['quantity'] > 1 ? $i['quantity'] . ' × ' : '') . $i['name'] . ' — ' . ((int)$i['trial_months'] ? 'free for ' . (int)$i['trial_months'] . ' months, then ' : '') . price_label((float)$i['price'] * $i['quantity'], $i['interval']); }, $items));
    $first = $c ? ($c['first_name'] ?: 'there') : 'there';
    $trialEnd = (int)($o['trial_end'] ?? 0);
    if ($c && $c['email'] !== '') {
        send_mail($c['email'], 'You’re in — order ' . $o['ref'] . ' confirmed',
            "Hi $first,\n\nThanks for your order with " . SHOP_BRAND . ". Your payment went through, and Stripe has emailed you a receipt.\n\n"
            . "Order $o[ref]\n$lines\n\nPaid today: " . money($o['paid_total']) . ($o['monthly'] > 0 ? ($trialEnd
                ? "\nYour free months run until " . date('F j, Y', $trialEnd) . ". Then " . money($o['monthly']) . "/month, billed automatically on that date and the same date each month until you cancel. We’ll email you a week before the first charge."
                : "\nThen " . money($o['monthly']) . "/month, billed automatically on the same date each month until you cancel.")
                . "\nCancel any time online in your client portal (Plans & billing): " . abs_url('portal-login.php?to=plans')
                . (array_filter($items, function ($i) { return $i['category'] === 'ads'; }) ? "\nAds management ends at the first billing date at least 30 days after you cancel; other monthly plans end when the month you’ve paid for runs out." : ($trialEnd ? "\nCancel before " . date('F j', $trialEnd) . " and you won’t pay anything more." : "\nYour plan ends when the month you’ve paid for runs out.")) : '') . "\n\n"
            . "What happens next\n  1. " . shop_kickoff_line() . "\n  2. Your client portal is ready. You can follow progress, upload your logo and photos, and approve work there:\n     " . portal_invite_url($c) . "\n  3. You’ll see each draft in the portal before anything goes live.\n\n"
            . "Questions? Just reply to this email.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
    }
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') {
        send_mail($to, '[New order] ' . money($o['paid_total']) . ' — ' . ($o['business'] ?: $o['name']) . ' (' . $o['ref'] . ')',
            "A new order was paid on the " . SHOP_BRAND . " website" . ($o['live'] ? '' : ' (Stripe test mode)') . ".\n\n$lines\n\nPaid: " . money($o['paid_total']) . ($o['monthly'] > 0 ? ' · then ' . money($o['monthly']) . '/mo' . ($trialEnd ? ' from ' . date('M j, Y', $trialEnd) : '') : '')
            . ($o['offer'] !== '' ? "\nOffer: " . ((string)val('SELECT name FROM offers WHERE slug = ?', [$o['offer']]) ?: $o['offer']) : '')
            . "\n\nName: $o[name]\nEmail: $o[email]\n" . ($o['phone'] ? "Phone: $o[phone]\n" : '') . "Business: $o[business]\n"
            . (($b = brief_text(json_decode($o['brief'], true) ?: [])) !== '' ? "\nProject brief:\n$b\n" : '')
            . "\nThe deal, paid invoice, project and client portal access are set up in the CRM:\n" . abs_url('store-order.php?id=' . $o['id']) . "\n", $o['email'], SHOP_BRAND);
    }
}

// Checkout started but not paid: keep the lead so you can follow up.
function shop_expire(array $o): void {
    $claim = q("UPDATE orders SET status = 'expired', updated_at = ? WHERE id = ? AND status = 'pending'", [now(), $o['id']]);
    if ($claim->rowCount() === 0 || $o['followed_up']) return;
    $items = order_items((int)$o['id']);
    $contact = find_or_create_contact($o['name'], $o['email'], $o['business']);
    if ($o['phone'] !== '' && $contact['phone'] === '') q('UPDATE contacts SET phone = ? WHERE id = ?', [$o['phone'], $contact['id']]);
    $open = one("SELECT id FROM deals WHERE contact_id = ? AND source = 'Marketing site — checkout' AND stage IN ('new','contacted','qualified','proposal')", [$contact['id']]);
    $value = $o['one_time'] + $o['monthly'];
    if ($open) { $dealId = (int)$open['id']; q('UPDATE deals SET value = ?, updated_at = ? WHERE id = ?', [$value, now(), $dealId]); }
    else {
        q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?,?, 'proposal', ?, 'Marketing site — checkout', ?, ?, ?)",
            ['Unfinished checkout — ' . ($o['business'] ?: person_name($contact)), $contact['company_id'], $contact['id'], SHOP_BRAND, $value, 'Started checkout for ' . order_summary($items) . ' but didn’t pay.', now(), now()]);
        $dealId = (int)db()->lastInsertId();
    }
    log_activity('deal', $dealId, 'note', 'Started checkout (order ' . $o['ref'] . ') for ' . order_summary($items) . ' — ' . money($o['one_time']) . ($o['monthly'] > 0 ? ' + ' . money($o['monthly']) . '/mo' : '') . ' — but didn’t finish paying.');
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,0,?,?,?)', ['Follow up: ' . person_name($contact) . ' didn’t finish checkout', today(), 'deal', $dealId, now()]);
    q('UPDATE orders SET followed_up = 1, contact_id = ?, company_id = ?, deal_id = ? WHERE id = ?', [$contact['id'], $contact['company_id'], $dealId, $o['id']]);
}

// One friendly reminder, 1–48 hours after a checkout that wasn't paid, with a link that puts the order back together.
// Never twice for the same order, at most once a week per email, and not if they've paid for anything since.
function shop_resume_url(array $o): string {
    return shop_site_url() . '/checkout.html?resume=' . rawurlencode($o['ref']) . '&k=' . rawurlencode($o['access_key']) . '&utm_source=email&utm_medium=email&utm_campaign=checkout-reminder';
}
function shop_remind_abandoned(): int {
    $sent = 0;
    $rows = all("SELECT * FROM orders WHERE status IN ('pending','expired') AND reminded_at IS NULL AND email != '' AND created_at < ? AND created_at > ? ORDER BY id LIMIT 20",
        [date('Y-m-d H:i:s', time() - 3600), date('Y-m-d H:i:s', time() - 48 * 3600)]);
    foreach ($rows as $o) {
        $claim = q('UPDATE orders SET reminded_at = ? WHERE id = ? AND reminded_at IS NULL', [now(), $o['id']]);
        if ($claim->rowCount() === 0) continue;
        if (one("SELECT 1 FROM orders WHERE email = ? COLLATE NOCASE AND id != ? AND (status IN ('paid','processing') AND created_at >= ? OR reminded_at > ? AND id < ?)",
            [$o['email'], $o['id'], $o['created_at'], date('Y-m-d H:i:s', time() - 7 * 86400), $o['id']])) continue; // paid since, or already reminded this week
        $items = order_items((int)$o['id']);
        if (!$items) continue;
        $lines = implode("\n", array_map(function ($i) { return '  • ' . $i['name'] . ((int)$i['quantity'] > 1 ? ' × ' . (int)$i['quantity'] : '') . ' — ' . ((int)$i['trial_months'] ? (int)$i['trial_months'] . ' months free, then ' : '') . money((float)$i['price'] * (int)$i['quantity']) . ($i['interval'] === 'month' ? '/month' : ''); }, $items));
        $first = trim(explode(' ', $o['name'])[0]) ?: 'there';
        $ok = send_mail($o['email'], 'Your order is saved — finish whenever you’re ready',
            "Hi $first,\n\nYou started an order on the " . SHOP_BRAND . " website but didn’t get to the end. No problem, we saved it for you:\n\n$lines\n\n"
            . "Pick up where you left off (your details are already filled in):\n" . shop_resume_url($o) . "\n\n"
            . "Got a question first, or not sure which package fits? Just reply to this email, or book a free 20-minute call: " . abs_url('book.php?type=discovery-call') . "\n\n"
            . "This is the only reminder we’ll send about this order.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
        if ($ok) $sent++;
        $who = $o['deal_id'] ? ['deal', (int)$o['deal_id']] : ($o['contact_id'] ? ['contact', (int)$o['contact_id']] : null);
        if ($who) log_activity($who[0], $who[1], 'email', 'Sent the one checkout reminder for order ' . $o['ref'] . '.');
    }
    return $sent;
}
// What the checkout page needs to rebuild an unpaid order from the reminder link.
function shop_resume(array $o): array {
    if (!in_array($o['status'], ['pending', 'expired', 'cancelled'], true)) return ['ok' => false, 'paid' => in_array($o['status'], ['paid', 'processing'], true)];
    $brief = json_decode($o['brief'], true) ?: [];
    foreach (['has_domain', 'has_logo', 'assets', 'ad_accounts'] as $k) unset($brief[$k]); // stored as the words they picked; they'll choose again
    $of = offer_row((string)$o['offer']);
    return ['ok' => true, 'offer' => $of && offer_state($of) === 'live' ? $of['slug'] : '', 'items' => array_map(function ($i) { return ['sku' => $i['sku'], 'qty' => (int)$i['quantity']]; }, order_items((int)$o['id'])),
        'customer' => ['name' => $o['name'], 'email' => $o['email'], 'phone' => $o['phone'], 'business' => $o['business'], 'website' => $o['website']], 'brief' => $brief];
}

/* ---------- Stripe webhook events ---------- */
function shop_order_by(string $col, string $v): ?array { return $v === '' ? null : one("SELECT * FROM orders WHERE $col = ? ORDER BY id DESC LIMIT 1", [$v]); }
// A monthly plan can come from a store order or from a won quote. Returns the row with 'table' and 'ref' added.
function shop_plan_by(string $col, string $v): ?array {
    if ($v === '') return null;
    if ($o = shop_order_by($col, $v)) return $o + ['table' => 'orders'];
    $q = one("SELECT q.*, q.number ref, COALESCE(c.first_name || ' ' || c.last_name, '') name FROM quotes q LEFT JOIN contacts c ON c.id = q.contact_id WHERE q.$col = ? ORDER BY q.id DESC LIMIT 1", [$v]);
    return $q ? $q + ['table' => 'quotes'] : null;
}
function shop_handle_event(array $ev): string {
    $obj = $ev['data']['object'] ?? [];
    switch ($ev['type'] ?? '') {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
        case 'checkout.session.expired':
            if (($obj['metadata']['kind'] ?? '') === 'booking') {
                require_once __DIR__ . '/booking.php';
                $bk = one('SELECT * FROM bookings WHERE stripe_session = ?', [(string)($obj['id'] ?? '')]);
                if (!$bk) return 'no matching booking';
                book_sync_session($bk, $obj);
                return 'booking synced';
            }
            if (($obj['metadata']['kind'] ?? '') === 'quote') {
                require_once __DIR__ . '/quotes.php';
                $qt = one('SELECT id FROM quotes WHERE stripe_session = ?', [(string)($obj['id'] ?? '')]);
                if (!$qt) return 'no matching quote';
                if (($ev['type'] ?? '') !== 'checkout.session.expired') quote_sync_session(load_quote((int)$qt['id']), $obj);
                return 'quote synced';
            }
            $o = shop_order_by('stripe_session', (string)($obj['id'] ?? '')) ?: shop_order_by('ref', (string)($obj['client_reference_id'] ?? ''));
            if (!$o) return 'no matching order';
            shop_sync_session($o, $obj);
            return 'order ' . $o['ref'] . ' synced';
        case 'checkout.session.async_payment_failed':
            $o = shop_order_by('stripe_session', (string)($obj['id'] ?? ''));
            if (!$o) return 'no matching order';
            q("UPDATE orders SET status = 'failed', updated_at = ? WHERE id = ? AND status IN ('pending','processing')", [now(), $o['id']]);
            q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Bank payment failed for ' . $o['name'] . ' (' . $o['ref'] . ')', today(), '', null, now()]);
            return 'payment failed';
        case 'invoice.paid':
            return shop_renewal_paid($obj);
        case 'invoice.payment_failed':
            $o = shop_plan_by('stripe_subscription', (string)($obj['subscription'] ?? ''));
            if (!$o || !$o['contact_id']) return 'no matching subscription';
            q("UPDATE {$o['table']} SET sub_status = 'past_due', updated_at = ? WHERE id = ?", [now(), $o['id']]);
            log_activity('contact', (int)$o['contact_id'], 'note', 'Monthly payment of ' . money((float)($obj['amount_due'] ?? 0) / 100) . ' failed. Stripe will retry the card automatically.');
            q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Card declined: ask ' . $o['name'] . ' to update their payment method', today(), 'contact', $o['contact_id'], now()]);
            return 'renewal failed';
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            $o = shop_plan_by('stripe_subscription', (string)($obj['id'] ?? ''));
            if (!$o) return 'no matching subscription';
            $st = (string)($obj['status'] ?? '');
            $cancelling = !empty($obj['cancel_at_period_end']);
            $cancelling = $cancelling || !empty($obj['cancel_at']);
            if ($st === $o['sub_status'] && !($cancelling && $ev['type'] === 'customer.subscription.updated')) return 'no change';
            $endsAt = (int)($obj['cancel_at'] ?? 0) ?: ($cancelling ? (int)($obj['current_period_end'] ?? 0) : 0);
            q("UPDATE {$o['table']} SET sub_status = ?, cancel_at = ?, updated_at = ? WHERE id = ?", [$st, $endsAt ?: null, now(), $o['id']]);
            if ($st === 'canceled') { // quotes are stored as negative ids
                require_once __DIR__ . '/care.php'; require_once __DIR__ . '/ads.php';
                care_disable_order($o['table'] === 'orders' ? (int)$o['id'] : -(int)$o['id']); ads_end_order($o['table'] === 'orders' ? (int)$o['id'] : -(int)$o['id']);
            }
            if ($o['contact_id'] && ($st === 'canceled' || $cancelling)) {
                $when = $cancelling && $endsAt ? ' It ends on ' . date('M j, Y', $endsAt) . '.' : '';
                log_activity('contact', (int)$o['contact_id'], 'note', 'Monthly plan from order ' . $o['ref'] . ($st === 'canceled' ? ' was cancelled.' : ' is set to cancel.' . $when));
                q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,0,?,?,?)', [$o['name'] . '’s monthly plan ' . ($st === 'canceled' ? 'was cancelled' : 'is cancelling') . ' — check in', today(), 'contact', $o['contact_id'], now()]);
            }
            return 'subscription ' . $st;
        case 'charge.refunded':
            $o = shop_plan_by('stripe_payment', (string)($obj['payment_intent'] ?? ''));
            if (!$o) return 'no matching order';
            $full = (int)($obj['amount_refunded'] ?? 0) >= (int)($obj['amount'] ?? 0);
            $amt = money((float)($obj['amount_refunded'] ?? 0) / 100);
            if ($full && $o['table'] === 'orders') q("UPDATE orders SET status = 'refunded', updated_at = ? WHERE id = ?", [now(), $o['id']]);
            if ($o['invoice_id']) q("UPDATE invoices SET notes = notes || ?, updated_at = ? WHERE id = ?", ["\nRefunded $amt on " . date('M j, Y') . '.', now(), $o['invoice_id']]);
            if ($o['deal_id']) log_activity('deal', (int)$o['deal_id'], 'note', ($full ? 'Fully refunded ' : 'Partly refunded ') . $amt . ' in Stripe.');
            return 'refund recorded';
    }
    return 'ignored';
}
// Each month's payment for ads and care plans becomes a paid invoice in the CRM.
function shop_renewal_paid(array $inv): string {
    $id = (string)($inv['id'] ?? '');
    if ($id === '' || val('SELECT 1 FROM invoices WHERE stripe_invoice = ?', [$id])) return 'invoice already recorded';
    if (($inv['billing_reason'] ?? '') === 'subscription_create') return 'first payment is recorded with the order';
    $o = shop_plan_by('stripe_subscription', (string)($inv['subscription'] ?? ''));
    if (!$o || !$o['contact_id']) return 'no matching subscription';
    if ((int)($inv['amount_paid'] ?? 0) <= 0) return 'nothing paid';
    $isQuote = $o['table'] === 'quotes';
    q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, status, tax_rate, notes, paid_at, paid_method, share_token, stripe_invoice, created_at, updated_at) VALUES (?,?,?,?,?,?, 'paid', 0, ?, ?, 'Stripe', ?, ?, ?, ?)",
        [next_invoice_number(), $isQuote ? ($o['brand'] ?: SHOP_BRAND) : SHOP_BRAND, $o['company_id'], $o['contact_id'], today(), today(), 'Monthly plan · ' . ($isQuote ? 'Proposal ' : 'Order ') . $o['ref'] . ' · paid automatically', now(), bin2hex(random_bytes(16)), $id, now(), now()]);
    $invoiceId = (int)db()->lastInsertId();
    $pos = 0;
    foreach ($inv['lines']['data'] ?? [] as $l) {
        $qty = max(1, (int)($l['quantity'] ?? 1));
        q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$invoiceId, mb_substr((string)($l['description'] ?? 'Monthly plan'), 0, 300), $qty, round((float)($l['amount'] ?? 0) / 100 / $qty, 2), $pos++]);
    }
    $tax = (int)($inv['tax'] ?? 0);
    if ($tax > 0) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$invoiceId, 'Sales tax', 1, $tax / 100, $pos++]);
    $diff = round((float)$inv['amount_paid'] / 100 - invoice_total($invoiceId)['total'], 2); // discounts and credits
    if (abs($diff) >= 0.01) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$invoiceId, $diff < 0 ? 'Discount' : 'Adjustment', 1, $diff, $pos++]);
    q("UPDATE {$o['table']} SET sub_status = 'active', updated_at = ? WHERE id = ?", [now(), $o['id']]);
    log_activity('contact', (int)$o['contact_id'], 'note', 'Monthly plan renewed: ' . money((float)$inv['amount_paid'] / 100) . ' paid automatically.');
    return 'renewal recorded';
}

/* ---------- Maintenance (run from cron.php) ---------- */
// Catches payments even if the webhook isn't set up, and closes checkouts that were never finished.
/* ---------- Monthly plans: online cancellation ---------- */
// Clients cancel from the client portal (portal-plans.php). Plans with ads management end at the first billing
// date at least plan_cancel_notice_days away (30 by default, per the terms of sale); other plans end at the
// end of the month already paid for. Set plan_cancel_notice_days to 0 to end every plan at the period end.
const PLAN_LIVE = ['active', 'trialing', 'past_due', 'unpaid'];
// The client's monthly plans from store orders and won quotes. Each row has 'table', 'ref', 'items' and 'has_ads'.
function portal_plans(array $c): array {
    $w = $c['company_id'] ? '(contact_id = ? OR company_id = ?)' : 'contact_id = ?';
    $a = $c['company_id'] ? [(int)$c['id'], (int)$c['company_id']] : [(int)$c['id']];
    $in = "'" . implode("','", PLAN_LIVE) . "'";
    $out = [];
    foreach (all("SELECT *, ref FROM orders WHERE stripe_subscription != '' AND sub_status IN ($in) AND $w ORDER BY id DESC", $a) as $o) {
        $items = all("SELECT name, price, quantity, category FROM order_items WHERE order_id = ? AND interval = 'month'", [$o['id']]);
        $out[] = $o + ['table' => 'orders', 'items' => $items, 'has_ads' => (bool)array_filter($items, function ($i) { return $i['category'] === 'ads'; })];
    }
    foreach (all("SELECT *, number ref FROM quotes WHERE stripe_subscription != '' AND sub_status IN ($in) AND $w ORDER BY id DESC", $a) as $q) {
        $items = all("SELECT qi.description name, qi.unit_price price, qi.quantity, COALESCE(p.category, '') category FROM quote_items qi LEFT JOIN products p ON p.id = qi.product_id WHERE qi.quote_id = ? AND qi.interval = 'month' AND qi.selected = 1", [$q['id']]);
        $out[] = $q + ['table' => 'quotes', 'items' => $items, 'has_ads' => (bool)array_filter($items, function ($i) { return $i['category'] === 'ads'; })];
    }
    return $out;
}
function plan_monthly(array $p): float { return array_sum(array_map(function ($i) { return (float)$i['price'] * (float)$i['quantity']; }, $p['items'])); }
// When the plan would end if cancelled now: [timestamp, true if that's simply the end of the current period].
function plan_end_date(array $p, array $sub): array {
    $period = (int)($sub['current_period_end'] ?? ($sub['items']['data'][0]['current_period_end'] ?? 0));
    if ($period <= 0) $period = time();
    $notice = $p['has_ads'] ? max(0, (int)setting('plan_cancel_notice_days')) : 0;
    $end = $period;
    while ($end < time() + $notice * 86400) $end = strtotime('+1 month', $end);
    return [$end, $end === $period];
}
// Cancels a plan in Stripe. Returns the end date (timestamp).
function plan_cancel(array $p, array $c): int {
    $sub = stripe('GET', 'subscriptions/' . rawurlencode($p['stripe_subscription']));
    [$end, $atPeriodEnd] = plan_end_date($p, $sub);
    stripe('POST', 'subscriptions/' . rawurlencode($p['stripe_subscription']), $atPeriodEnd ? ['cancel_at_period_end' => 'true'] : ['cancel_at' => $end, 'proration_behavior' => 'none'], 'cancel-' . $p['stripe_subscription']);
    q("UPDATE {$p['table']} SET cancel_at = ?, updated_at = ? WHERE id = ?", [$end, now(), $p['id']]);
    $names = implode(', ', array_map(function ($i) { return $i['name']; }, $p['items'])) ?: 'Monthly plan';
    log_activity('contact', (int)$c['id'], 'note', 'Cancelled online in the client portal: ' . $names . ' (' . $p['ref'] . '). Ends ' . date('M j, Y', $end) . '.');
    $brand = SHOP_BRAND;
    send_mail($c['email'], 'Your plan is cancelled — ends ' . date('M j, Y', $end),
        "Hi " . ($c['first_name'] ?: 'there') . ",\n\nThis confirms you cancelled your monthly plan with $brand.\n\nPlan: $names (" . $p['ref'] . ")\nLast day of service: " . date('F j, Y', $end) . "\n"
        . ($atPeriodEnd ? "You won’t be charged again.\n" : "Because of the 30-day notice period for ads management, your last charge is on or before that date and you won’t be charged after it.\n")
        . "\nChanged your mind? Reply to this email before " . date('M j', $end) . " and we’ll keep it running.\n\n$brand\n" . setting('business_email'), setting('business_email'), $brand);
    send_mail(setting('shop_notify') ?: setting('business_email'), '[Plan cancelled] ' . ($c['first_name'] . ' ' . $c['last_name']) . ' — ' . $names,
        trim($c['first_name'] . ' ' . $c['last_name']) . ' cancelled ' . $names . ' (' . $p['ref'] . ') in the client portal. It ends ' . date('M j, Y', $end) . ".\n\n" . abs_url('contact.php?id=' . $c['id']) . "\n", $c['email'], $brand);
    return $end;
}

// Once a year on each live monthly plan: what it is, what it costs and how to cancel (California's annual notice).
function plans_yearly_reminders(): int {
    $n = 0; $in = "'" . implode("','", PLAN_LIVE) . "'"; $yearAgo = date('Y-m-d H:i:s', time() - 365 * 86400);
    foreach (['orders' => 'ref', 'quotes' => 'number'] as $table => $refCol) {
        foreach (all("SELECT t.*, t.$refCol ref, c.first_name, c.email cemail FROM $table t JOIN contacts c ON c.id = t.contact_id WHERE t.stripe_subscription != '' AND t.sub_status IN ($in) AND t.cancel_at IS NULL AND t.paid_at IS NOT NULL AND t.paid_at <= ? AND (t.renewal_reminded_at IS NULL OR t.renewal_reminded_at <= ?) LIMIT 50", [$yearAgo, $yearAgo]) as $p) {
            if ($p['cemail'] === '') continue;
            $items = $table === 'orders' ? all("SELECT name, price, quantity FROM order_items WHERE order_id = ? AND interval = 'month'", [$p['id']]) : all("SELECT description name, unit_price price, quantity FROM quote_items WHERE quote_id = ? AND interval = 'month' AND selected = 1", [$p['id']]);
            $names = implode(', ', array_column($items, 'name')) ?: 'Monthly plan';
            $amt = array_sum(array_map(function ($i) { return (float)$i['price'] * (float)$i['quantity']; }, $items));
            send_mail($p['cemail'], 'Your yearly plan reminder — ' . $names,
                "Hi " . ($p['first_name'] ?: 'there') . ",\n\nA once-a-year reminder about your monthly plan with " . SHOP_BRAND . ".\n\nPlan: $names (" . $p['ref'] . ")\nPrice: " . money($amt) . " a month, charged automatically on the same date each month until you cancel.\n\n"
                . "To cancel, sign in to your client portal and open Plans & billing: " . abs_url('portal-login.php?to=plans') . "\nOr just reply to this email and we’ll cancel it for you.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
            q("UPDATE $table SET renewal_reminded_at = ? WHERE id = ?", [now(), $p['id']]);
            $n++;
        }
    }
    return $n;
}
// Free months ending: one email 7 days before the first charge (card networks and California's
// auto-renewal law both ask for a notice before a free trial turns into a paid plan).
function plans_trial_reminders(): int {
    $n = 0;
    foreach (all("SELECT o.*, c.first_name, c.email cemail FROM orders o JOIN contacts c ON c.id = o.contact_id WHERE o.trial_end IS NOT NULL AND o.trial_end > ? AND o.trial_end <= ? AND o.trial_reminded_at IS NULL AND o.cancel_at IS NULL AND o.sub_status = 'trialing' AND o.status = 'paid' LIMIT 50",
        [time(), time() + 8 * 86400]) as $o) {
        if ($o['trial_end'] > time() + 7 * 86400 + 3600) continue; // a day early is fine; a week and an hour isn't yet
        $claim = q('UPDATE orders SET trial_reminded_at = ? WHERE id = ? AND trial_reminded_at IS NULL', [now(), $o['id']]);
        if ($claim->rowCount() === 0 || $o['cemail'] === '') continue;
        $items = all("SELECT name, price, quantity FROM order_items WHERE order_id = ? AND interval = 'month'", [$o['id']]);
        $names = implode(', ', array_column($items, 'name')) ?: 'Monthly plan';
        $amt = array_sum(array_map(function ($i) { return (float)$i['price'] * (float)$i['quantity']; }, $items));
        $when = date('F j, Y', (int)$o['trial_end']);
        send_mail($o['cemail'], 'Your free months end ' . date('M j', (int)$o['trial_end']) . ' — ' . $names,
            "Hi " . ($o['first_name'] ?: 'there') . ",\n\nA heads-up: the free months of $names on your order " . $o['ref'] . " end on $when.\n\n"
            . "From $when you’ll be charged " . money($amt) . " a month on the card you used, on the same date each month, until you cancel.\n\n"
            . "Happy to keep it? You don’t need to do anything.\nWant to stop? Cancel before $when in your client portal (Plans & billing) and you won’t be charged:\n" . abs_url('portal-login.php?to=plans')
            . "\nOr just reply to this email and we’ll cancel it for you.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
        if ($o['contact_id']) log_activity('contact', (int)$o['contact_id'], 'email', 'Sent the free-months-ending reminder for order ' . $o['ref'] . " (first charge $when).");
        $n++;
    }
    return $n;
}
// Community management: a task on the 1st of each month to write each client's community report.
function community_report_tasks(): int {
    if ((int)date('j') > 5) return 0;
    $month = date('Y-m', strtotime('first day of last month'));
    $in = "'" . implode("','", PLAN_LIVE) . "'"; $n = 0;
    foreach (all("SELECT DISTINCT o.id, o.ref, o.business, o.name, o.contact_id, o.paid_at FROM orders o JOIN order_items i ON i.order_id = o.id WHERE i.category = 'community' AND i.interval = 'month' AND o.status = 'paid' AND o.sub_status IN ($in) AND o.paid_at < ?", [date('Y-m-01')]) as $o) {
        $key = 'community_report_' . $o['id'];
        if (setting($key) === $month) continue;
        setting($key, $month);
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Write ' . ($o['business'] ?: $o['name']) . '’s community report for ' . date('F Y', strtotime($month . '-01')) . ' and post it in their portal', date('Y-m-d', strtotime('+2 days')), 'contact', $o['contact_id'], now()]);
        $n++;
    }
    return $n;
}
function shop_cleanup(): string {
    if (!shop_ready()) return '';
    $n = 0;
    foreach (all("SELECT * FROM orders WHERE status IN ('pending','processing') AND stripe_session != '' AND created_at > ? ORDER BY id DESC LIMIT 25", [date('Y-m-d H:i:s', time() - 3 * 86400)]) as $o) {
        if ($o['status'] === 'pending' && strtotime($o['created_at']) > time() - 600) continue; // still at the payment page
        try { $before = $o['status']; $o = shop_sync_session($o); if ($o['status'] !== $before) $n++; }
        catch (Throwable $e) { error_log('Store sync ' . $o['ref'] . ': ' . $e->getMessage()); break; }
    }
    foreach (all("SELECT * FROM orders WHERE status = 'pending' AND created_at < ?", [date('Y-m-d H:i:s', time() - 25 * 3600)]) as $o) { shop_expire($o); $n++; }
    $r = plans_yearly_reminders(); $ab = shop_remind_abandoned(); $tr = plans_trial_reminders(); community_report_tasks();
    return trim(($n ? "$n store order(s) updated" : '') . ($r ? " $r yearly plan reminder(s) sent" : '') . ($ab ? " $ab checkout reminder(s) sent" : '') . ($tr ? " $tr free-months reminder(s) sent" : ''));
}

/* ---------- Contact form on the marketing site ---------- */
function shop_lead(array $in): array {
    $s = function ($k, $max = 200) use ($in) { return mb_substr(trim((string)($in[$k] ?? '')), 0, $max); };
    $name = $s('name', 120); $email = strtolower($s('email', 190)); $biz = $s('business', 150); $msg = $s('message', 5000);
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Enter your name.', 'field' => 'name'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.', 'field' => 'email'];
    if (mb_strlen($msg) < 10) return ['ok' => false, 'error' => 'Tell us a little more about what you need.', 'field' => 'message'];
    $topics = ['website' => 'Website', 'ads' => 'Paid ads', 'analytics' => 'Analytics', 'other' => 'Something else'];
    $topic = $topics[$s('topic', 20)] ?? 'Something else';
    $budget = $s('budget', 60); $phone = $s('phone', 40);
    $contact = find_or_create_contact($name, $email, $biz);
    if ($phone !== '' && $contact['phone'] === '') q('UPDATE contacts SET phone = ? WHERE id = ?', [$phone, $contact['id']]);
    $body = "Message from the " . SHOP_BRAND . " website ($topic):\n\n$msg" . ($budget !== '' ? "\n\nBudget: $budget" : '') . ($phone !== '' ? "\nPhone: $phone" : '');
    q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?,?, 'new', 0, 'Marketing site', '', ?, ?)",
        [$topic . ' — ' . ($biz ?: person_name($contact)), $contact['company_id'], $contact['id'], SHOP_BRAND, now(), now()]);
    $dealId = (int)db()->lastInsertId();
    log_activity('deal', $dealId, 'email', $body);
    log_activity('contact', (int)$contact['id'], 'email', $body);
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Reply to ' . person_name($contact), today(), 'deal', $dealId, now()]);
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') send_mail($to, '[' . SHOP_BRAND . '] ' . $topic . ' — ' . ($biz ?: $name), "$body\n\nName: $name\nEmail: $email\n" . ($biz ? "Business: $biz\n" : '') . "\nIn the CRM: " . abs_url('deal.php?id=' . $dealId) . "\n", $email, SHOP_BRAND);
    // The confirmation holds none of the visitor's own words, and goes to each address at most once a day,
    // so the form can't be used to send someone else spam from our domain.
    $rk = hash('sha256', 'lead-reply|' . $email);
    if (!val('SELECT 1 FROM attempts WHERE kind = ? AND ip_hash = ? AND created_at > ?', ['lead_reply', $rk, time() - 86400])) {
        q('INSERT INTO attempts (kind, ip_hash, created_at) VALUES (?,?,?)', ['lead_reply', $rk, time()]);
        send_mail($email, 'We got your message — ' . SHOP_BRAND, "Hi there,\n\nThanks for reaching out to " . SHOP_BRAND . " about " . (["Website" => "a website", "Paid ads" => "paid ads", "Analytics" => "analytics"][$topic] ?? "your project") . ". We read every message and will reply within one business day.\n\nIf you didn’t send this, you can ignore this email.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
    }
    return ['ok' => true];
}
