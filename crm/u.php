<?php
// Unsubscribe from Syndicate Marketing outreach emails: crm.stusyn.com/u.php?t=TOKEN (in every outreach email's footer
// and its List-Unsubscribe header). Mail apps' one-click unsubscribe POSTs here directly.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/outreach.php';
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
$t = get('t');
$p = preg_match('/^[a-f0-9]{32}$/', $t) && !too_many('unsub', 30, 15) ? one('SELECT * FROM outreach_prospects WHERE token = ?', [$t]) : null;
if (!$p && $t !== '') record_attempt('unsub');
$done = false;
if ($p && is_post()) { out_unsubscribe($p, post('List-Unsubscribe') === 'One-Click' ? 'one-click button in their mail app' : 'unsubscribe link'); $done = true; }
if ($p && !$done && val("SELECT 1 FROM outreach_optout WHERE email = ?", [$p['email']])) $done = true;
if (!$p) http_response_code(404);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $done ? 'You’re unsubscribed' : 'Unsubscribe' ?> · Syndicate Marketing</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public">
<main class="public-wrap">
  <div class="card" style="max-width:520px;margin:12vh auto 0;text-align:center">
  <?php if (!$p): ?>
    <h1 style="font-size:24px">Link not found</h1>
    <p class="muted">This unsubscribe link may be incomplete. Reply to the email with “unsubscribe” and we’ll take you off right away.</p>
  <?php elseif ($done): ?>
    <h1 style="font-size:24px">You’re unsubscribed</h1>
    <p class="muted"><?= e($p['email']) ?> won’t get any more emails from Syndicate Marketing. Sorry for the bother.</p>
  <?php else: ?>
    <h1 style="font-size:24px">Unsubscribe?</h1>
    <p class="muted">Stop all emails from Syndicate Marketing to <?= e($p['email']) ?>.</p>
    <form method="post" style="margin-top:18px"><button class="btn primary" type="submit">Unsubscribe</button></form>
  <?php endif; ?>
  </div>
</main>
</body>
</html>
