<?php
// Public store API for the Syndicate Marketing website.
//   GET  shop-api.php?a=catalog                      products and prices
//   POST shop-api.php?a=checkout  {items, customer, brief, agree}  → Stripe Checkout address
//   GET  shop-api.php?a=offer&o=slug                 a special offer: prices, free months, end date, spots left
//   GET  shop-api.php?a=order&ref=…&k=…[&session_id=…]  order status for the thank-you page
//   GET  shop-api.php?a=resume&ref=…&k=…           an unpaid order, to finish it from the reminder email
//   POST shop-api.php?a=lead      {name, email, business, topic, message}  contact form
//   POST shop-api.php?a=refer     {name, email, business, payout, payout_details, agree}  referral program sign-up
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$a = get('a');
shop_cors($a === 'catalog' || $a === 'offer');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

if ($a === 'catalog') {
    header('Cache-Control: public, max-age=60');
    reply(200, ['ok' => true, 'currency' => 'usd', 'ready' => shop_ready(), 'promo' => setting('shop_promo') === '1',
        'products' => array_map('product_public', shop_products()), 'categories' => SHOP_CATEGORIES, 'platforms' => AD_PLATFORMS]);
}

if ($a === 'offer') {
    $of = offer_row(mb_substr(get('o'), 0, 60));
    if (!$of) reply(404, ['ok' => false, 'state' => 'missing', 'error' => 'This offer isn’t available.']);
    header('Cache-Control: public, max-age=30');
    $pub = offer_public($of);
    if (!$pub['live']) $pub['error'] = offer_closed_text($of, $pub['state']);
    reply(200, $pub);
}

if ($a === 'order') {
    $o = one('SELECT * FROM orders WHERE ref = ?', [get('ref')]);
    if (!$o || !hash_equals($o['access_key'], get('k'))) reply(404, ['ok' => false, 'error' => 'Order not found.']);
    // Coming back from Stripe: check the payment right away instead of waiting for the webhook.
    if (in_array($o['status'], ['pending', 'processing'], true) && get('session_id') !== '' && hash_equals($o['stripe_session'], get('session_id'))) {
        try { $o = shop_sync_session($o); } catch (Throwable $e) { error_log('Store order check ' . $o['ref'] . ': ' . $e->getMessage()); }
    }
    $items = order_items((int)$o['id']);
    reply(200, ['ok' => true, 'ref' => $o['ref'], 'status' => $o['status'], 'email' => $o['email'], 'name' => explode(' ', $o['name'])[0],
        'paid' => (float)$o['paid_total'], 'one_time' => (float)$o['one_time'], 'monthly' => (float)$o['monthly'], 'trial_end' => $o['trial_end'] ? date('F j, Y', (int)$o['trial_end']) : null,
        'items' => array_map(function ($i) { return ['sku' => $i['sku'], 'name' => $i['name'], 'category' => $i['category'], 'price' => (float)$i['price'], 'interval' => $i['interval'], 'qty' => (int)$i['quantity'], 'trial_months' => (int)$i['trial_months']]; }, $items),
        'portal' => abs_url('portal-login.php')]);
}

if ($a === 'resume') {
    $o = one('SELECT * FROM orders WHERE ref = ?', [get('ref')]);
    if (!$o || !hash_equals($o['access_key'], get('k'))) reply(404, ['ok' => false, 'error' => 'Order not found.']);
    reply(200, shop_resume($o));
}

if (!is_post()) reply(405, ['ok' => false, 'error' => 'Use POST.']);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && !in_array(strtolower($origin), shop_origins(), true)) reply(403, ['ok' => false, 'error' => 'This website isn’t allowed to use the store. Set the store website address in the CRM under Store → Settings.']);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
if (!is_array($in)) reply(400, ['ok' => false, 'error' => 'Something went wrong. Refresh the page and try again.']);
if (trim((string)($in['website_url'] ?? '')) !== '') reply(200, ['ok' => true, 'url' => shop_site_url() . '/thank-you.html']); // spam trap

if ($a === 'checkout') {
    if (too_many('checkout', 12, 60)) reply(429, ['ok' => false, 'error' => 'Too many tries. Please wait a few minutes, or email ' . setting('business_email') . '.']);
    record_attempt('checkout');
    $r = shop_checkout($in);
    reply($r['ok'] ? 200 : 422, $r);
}
if ($a === 'lead') {
    if (!captcha_ok((string)($in['cf-turnstile-response'] ?? ''))) reply(422, ['ok' => false, 'error' => CAPTCHA_FAIL, 'captcha' => true]);
    if (too_many('lead', 6, 60)) reply(429, ['ok' => false, 'error' => 'Too many messages. Please wait a little, or email ' . setting('business_email') . '.']);
    record_attempt('lead');
    $r = shop_lead($in);
    reply($r['ok'] ? 200 : 422, $r);
}
if ($a === 'refer') {
    require_once __DIR__ . '/app/referrals.php';
    if (!captcha_ok((string)($in['cf-turnstile-response'] ?? ''))) reply(422, ['ok' => false, 'error' => CAPTCHA_FAIL, 'captcha' => true]);
    if (too_many('refer', 5, 60)) reply(429, ['ok' => false, 'error' => 'Too many tries. Please wait a little, or email ' . setting('business_email') . '.']);
    record_attempt('refer');
    $r = ref_signup($in);
    reply($r['ok'] ? 200 : 422, $r);
}
reply(400, ['ok' => false, 'error' => 'Unknown request.']);
