<?php
// Public care report: crm.stusyn.com/care-report.php?id=SITE&m=YYYY-MM&k=SIGNATURE. Linked from the monthly email, the portal and the CRM.
require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/care-doc.php';
header('Cache-Control: private, no-store');
$s = one('SELECT * FROM sites WHERE id = ?', [(int)get('id')]);
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', get('m')) ? get('m') : '';
$ok = $s && $month !== '' && $s['report_key'] !== '' && preg_match('/^[a-f0-9]{32}$/', get('k')) && !too_many('care_report', 60, 15)
    && hash_equals(care_report_sig($s, $month), get('k')) && $month <= date('Y-m');
if (!$ok) { if (get('k') !== '') record_attempt('care_report'); http_response_code(404); }
$months = $ok ? care_report_months($s) : [];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $ok ? e($s['name'] . ' · ' . date('F Y', strtotime($month . '-01')) . ' care report') : 'Report not found' ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public">
<main class="public-wrap">
<?php if (!$ok): ?>
  <div class="card" style="max-width:520px;margin:12vh auto 0;text-align:center"><h1 style="font-size:24px">Report not found</h1><p class="muted">This link may be mistyped. Your reports are also in your client portal, or email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p></div>
<?php else: ?>
  <div class="public-bar no-print">
    <form method="get" class="row"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
      <label class="sr-only" for="m">Month</label>
      <select id="m" data-go><?php foreach ($months as $m): ?><option data-href="<?= e(care_report_url($s, $m)) ?>"<?= $m === $month ? ' selected' : '' ?>><?= e(date('F Y', strtotime($m . '-01')) . ($m === date('Y-m') ? ' (so far)' : '')) ?></option><?php endforeach; ?></select>
    </form>
    <button class="btn ghost sm" type="button" data-print>Print / Save PDF</button>
  </div>
  <?php render_care_report($s, $month); ?>
<?php endif; ?>
</main>
<script src="assets/js/crm.js" defer></script>
</body>
</html>
