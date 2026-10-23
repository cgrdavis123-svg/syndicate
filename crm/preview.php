<?php
// A free homepage preview (app/previews.php): the page itself, the owner's "remove this preview", and the view beacon.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/previews.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$r = pv_row(get('t'));

if (get('seen') === '1') { // from assets/js/pv.js once a real browser has shown the page for a few seconds
    $staff = !empty($_COOKIE['syn_crm']) && current_user(); // us checking it doesn't count
    if ($r && pv_live($r) && is_post() && !$staff && !too_many('pv_seen', 30, 60)) { record_attempt('pv_seen'); pv_seen($r); }
    http_response_code(204); exit;
}
$msg = function (string $title, string $body, int $code = 200) {
    http_response_code($code); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title><?= e($title) ?></title>
<link rel="stylesheet" href="assets/css/fonts.css">
<style>body{margin:0;background:#0b0d10;color:#eef1f4;font:16px/1.6 "Inter Tight",system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:16px;box-sizing:border-box}main{max-width:520px;width:100%;background:#fff;color:#0d0f0c;border-radius:18px;padding:30px}h1{font-size:24px;margin:0 0 10px;letter-spacing:-.02em}p{color:#4b5260}.btn{display:inline-block;background:#0d0f0c;color:#fff;border:0;border-radius:999px;padding:12px 20px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}label{display:flex;gap:10px;align-items:flex-start;margin:14px 0 18px;color:#2a2d29}a{color:inherit}</style></head>
<body><main><?= $body ?></main></body></html><?php
    exit;
};
if (!$r) $msg('Preview not found', '<h1>This preview doesn’t exist</h1><p>The link may be mistyped, or the preview was removed.</p>', 404);
if ($r['removed_at']) $msg('Preview removed', '<h1>This preview has been removed</h1><p>It’s gone for good. If you’d like a website after all, we’re at <a href="mailto:' . e(setting('business_email')) . '">' . e(setting('business_email')) . '</a>.</p>', 410);
if ((int)$r['expires_at'] <= time()) $msg('Preview expired', '<h1>This preview has expired</h1><p>We keep homepage previews for ' . PV_DAYS . ' days. Want to see it again, or turn it into your real website? Email <a href="mailto:' . e(setting('business_email')) . '">' . e(setting('business_email')) . '</a> or see our <a href="' . e((shop_site_url() ?: 'https://syndicatemarketing.co') . '/websites.html') . '">website packages</a>.</p>', 410);

if (get('remove') === '1') {
    if (is_post()) {
        if (too_many('pv_remove', 10, 60)) $msg('Try again later', '<h1>Too many tries</h1><p>Please wait a few minutes and try again, or email ' . e(setting('business_email')) . '.</p>', 429);
        record_attempt('pv_remove');
        pv_remove($r, post('stop') === '1');
        $msg('Removed', '<h1>Done. The preview is gone.</h1><p>Nobody can open it any more.' . (post('stop') === '1' ? ' We won’t email you again.' : '') . ' Sorry for the bother.</p>');
    }
    $msg('Remove this preview', '<h1>Remove this preview?</h1><p>We made a free homepage concept for ' . e($r['business']) . ' to show what a new website could look like. Removing it takes it offline straight away, for everyone.</p>'
        . '<form method="post" action="preview.php?t=' . e($r['token']) . '&amp;remove=1"><label><input type="checkbox" name="stop" value="1"' . ($r['email'] !== '' ? ' checked' : '') . '> Don’t email ' . e($r['business']) . ' about this again</label><button class="btn" type="submit">Remove the preview</button></form>'
        . '<p style="margin-top:18px;font-size:14px"><a href="preview.php?t=' . e($r['token']) . '">← Back to the preview</a></p>');
}
pv_render($r);
