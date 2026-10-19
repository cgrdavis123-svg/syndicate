<?php
// Public invoice page for clients: crm.stusyn.com/i.php?t=TOKEN. Shows one invoice and nothing else.
require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/invoice-doc.php';
header('Cache-Control: private, no-store');
$token = get('t');
$inv = null;
if (preg_match('/^[a-f0-9]{32}$/', $token) && !too_many('invoice_link', 40, 15)) {
    $inv = one('SELECT i.*, co.name company, co.address, c.first_name, c.last_name, c.email FROM invoices i LEFT JOIN companies co ON co.id = i.company_id LEFT JOIN contacts c ON c.id = i.contact_id WHERE i.share_token = ?', [$token]);
}
$staff = current_user();
if (!$inv || ($inv['status'] === 'draft' && !$staff)) {
    if (preg_match('/^[a-f0-9]{32}$/', $token)) record_attempt('invoice_link');
    http_response_code(404);
}
if ($inv && !$staff && $inv['status'] !== 'draft' && !$inv['viewed_at']) {
    q('UPDATE invoices SET viewed_at = ? WHERE id = ?', [now(), $inv['id']]);
    $who = $inv['contact_id'] ? 'contact' : ($inv['company_id'] ? 'company' : '');
    if ($who) log_activity($who, (int)($inv[$who . '_id']), 'email', 'Client opened invoice ' . $inv['number'] . '.');
}
$show = $inv && http_response_code() !== 404;
$t = $show ? invoice_total((int)$inv['id']) : null;
$items = $show ? all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position, id', [$inv['id']]) : [];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $show ? e('Invoice ' . $inv['number'] . ' · ' . invoice_from($inv)) : 'Invoice not found' ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public">
<main class="public-wrap">
<?php if (!$show): ?>
  <div class="card" style="max-width:520px;margin:12vh auto 0;text-align:center"><h1 style="font-size:24px">Invoice not found</h1><p class="muted">This link may be mistyped or no longer active. Please contact <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a> for a new link.</p></div>
<?php else: ?>
  <div class="public-bar no-print">
    <span class="row"><?= pill('inv', $inv['status'] === 'sent' && $inv['due_date'] < today() ? 'overdue' : $inv['status'], $inv['status'] === 'sent' ? ($inv['due_date'] < today() ? 'Past due' : 'Due ' . fmt_date($inv['due_date'])) : INVOICE_STATUSES[$inv['status']]) ?><?php if ($staff && $inv['status'] === 'draft'): ?><span class="muted small">Draft preview · only you can see this</span><?php endif; ?></span>
    <span class="row"><?php if ($inv['payment_url'] && $inv['status'] === 'sent'): ?><a class="btn primary sm" href="<?= e($inv['payment_url']) ?>" target="_blank" rel="noopener noreferrer">Pay <?= money($t['total']) ?></a><?php endif; ?><button class="btn ghost sm" type="button" data-print>Print / Save PDF</button></span>
  </div>
  <?php if ($inv['status'] === 'void'): ?><p class="notice bad no-print">This invoice has been voided and no longer needs to be paid.</p><?php endif; ?>
  <?php render_invoice_doc($inv, $items, $t, true); ?>
  <p class="muted small no-print" style="text-align:center">Questions about this invoice? Email <a href="mailto:<?= e(setting('business_email')) ?>?subject=<?= rawurlencode('Invoice ' . $inv['number']) ?>"><?= e(setting('business_email')) ?></a></p>
<?php endif; ?>
</main>
<script src="assets/js/crm.js" defer></script>
</body>
</html>
