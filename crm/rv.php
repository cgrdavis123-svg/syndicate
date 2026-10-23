<?php
// The link in a review request email: notes that it was opened, then goes straight to our Google review page.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/reviews.php';
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$t = get('t');
$r = preg_match('/^[a-f0-9]{32}$/', $t) ? one("SELECT * FROM review_requests WHERE token = ? AND status = 'sent'", [$t]) : null;
if ($r && !$r['clicked_at']) q('UPDATE review_requests SET clicked_at = ? WHERE id = ?', [time(), $r['id']]);
$u = rv_url();
if ($u !== '') { header('Location: ' . $u, true, 302); exit; }
http_response_code($r ? 200 : 404);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Thank you</title>
<style>body{margin:0;background:#0b0d10;color:#eef1f4;font:16px/1.5 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:16px}main{max-width:460px;background:#fff;color:#0d0f0c;border-radius:16px;padding:28px}</style></head>
<body><main><h1 style="font-size:22px;margin:0 0 8px">Thank you</h1><p>Our review page isn’t available right now. If you’d like to share how it went, just reply to our email or write to <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p></main></body></html>
