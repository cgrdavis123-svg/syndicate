<?php
// Monthly ads report: ads.stusyn.com/report.php?c=CLIENT&m=YYYY-MM&k=SIGNATURE (linked from the email and the portal).
require __DIR__ . '/../app/ads-boot.php';
require_once __DIR__ . '/../app/ads-doc.php';
header('Cache-Control: private, no-store');
$a = ads_client((int)get('c'));
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', get('m')) ? get('m') : '';
$ok = $a && $month !== '' && $a['report_key'] !== '' && preg_match('/^[a-f0-9]{32}$/', get('k')) && !too_many('ads_report', 60, 15)
    && hash_equals(ads_report_sig($a, $month), get('k')) && $month <= date('Y-m');
if (!$ok) demo_miss();
if (!$ok) { if (get('k') !== '') record_attempt('ads_report'); http_response_code(404); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $ok ? e($a['company'] . ' · ' . date('F Y', strtotime($month . '-01')) . ' ads report') : 'Report not found' ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public"><?= demo_ribbon() ?>
<main class="public-wrap">
<?php if (!$ok): ?>
  <div class="card" style="max-width:520px;margin:12vh auto 0;text-align:center"><h1 style="font-size:24px">Report not found</h1><p class="muted">This link may be mistyped. Your reports are also in your <a href="portal.php">ads portal</a>, or email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p></div>
<?php else: ?>
  <div class="public-bar no-print">
    <label class="sr-only" for="m">Month</label>
    <select id="m" data-go><?php foreach (ads_report_months($a) as $m): ?><option data-href="<?= e(ads_report_url($a, $m)) ?>"<?= $m === $month ? ' selected' : '' ?>><?= e(date('F Y', strtotime($m . '-01')) . ($m === date('Y-m') ? ' (so far)' : '')) ?></option><?php endforeach; ?></select>
    <button class="btn ghost sm" type="button" data-print>Print / Save PDF</button>
  </div>
  <?php render_ads_report($a, $month); ?>
<?php endif; ?>
</main>
<script src="assets/js/crm.js" defer></script>
</body>
</html>
