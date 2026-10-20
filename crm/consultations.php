<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$filter = get('filter');
$where = ''; $args = [];
if ($filter === 'unbilled') $where = " WHERE s.status = 'completed' AND s.billable = 1 AND s.invoice_id IS NULL";
elseif ($filter === 'scheduled') { $where = " WHERE s.status = 'scheduled'"; }
elseif ($filter === 'billed') $where = ' WHERE s.invoice_id IS NOT NULL';
$rows = all("SELECT s.*, c.first_name, c.last_name, c.email, co.name company, i.number inv_number FROM consultations s
    LEFT JOIN contacts c ON c.id = s.contact_id LEFT JOIN companies co ON co.id = s.company_id LEFT JOIN invoices i ON i.id = s.invoice_id$where
    ORDER BY s.date DESC, s.start_time DESC LIMIT 300", $args);
$month = one("SELECT COALESCE(SUM(minutes),0) mins, COALESCE(SUM(minutes * rate / 60.0),0) amt FROM consultations WHERE status = 'completed' AND date >= ?", [date('Y-m-01')]);
$unbilled = one("SELECT COALESCE(SUM(minutes),0) mins, COALESCE(SUM(minutes * rate / 60.0),0) amt FROM consultations WHERE status = 'completed' AND billable = 1 AND invoice_id IS NULL");
$byClient = all("SELECT s.company_id, s.contact_id, co.name company, c.first_name, c.last_name, c.email, SUM(s.minutes) mins, SUM(s.minutes * s.rate / 60.0) amt, COUNT(*) n
    FROM consultations s LEFT JOIN companies co ON co.id = s.company_id LEFT JOIN contacts c ON c.id = s.contact_id
    WHERE s.status = 'completed' AND s.billable = 1 AND s.invoice_id IS NULL GROUP BY s.company_id, s.contact_id ORDER BY amt DESC");
layout_top('Consultations', 'consultations', $user);
?>
<div class="page-head"><div><p class="eyebrow">Creator Ops Media · $<?= e(setting('default_rate')) ?>/hr</p><h1>Consultations</h1></div><a class="btn primary sm" href="consultation-edit.php">+ Log consultation</a></div>
<div class="mini-kpis">
  <div><span>Completed this month</span><b class="num"><?= hours($month['mins']) ?></b><small class="num"><?= money($month['amt']) ?></small></div>
  <div><span>Unbilled</span><b class="num"><?= hours($unbilled['mins']) ?></b><small class="num"><?= money($unbilled['amt']) ?></small></div>
  <div><span>Scheduled</span><b class="num"><?= (int)val("SELECT COUNT(*) FROM consultations WHERE status = 'scheduled' AND date >= ?", [today()]) ?></b><small>upcoming</small></div>
</div>
<?php if ($byClient): ?>
<section class="card"><div class="card-h"><h2>Ready to invoice</h2></div>
  <ul class="list"><?php foreach ($byClient as $b): $who = $b['company'] ?: person_name($b); ?>
    <li><div class="li-row"><span class="grow"><b><?= e($who) ?></b><small><?= (int)$b['n'] ?> session<?= $b['n'] == 1 ? '' : 's' ?> · <?= hours($b['mins']) ?></small></span><b class="num"><?= money($b['amt']) ?></b>
      <a class="btn primary xs" href="invoice-edit.php?from_consults=1&amp;company=<?= (int)$b['company_id'] ?>&amp;contact=<?= (int)$b['contact_id'] ?>">Create invoice</a></div></li>
  <?php endforeach; ?></ul>
</section>
<?php endif; ?>
<div class="seg-links" style="margin:6px 0 2px">
  <?php foreach (['' => 'All', 'scheduled' => 'Scheduled', 'unbilled' => 'Unbilled', 'billed' => 'Invoiced'] as $k => $l): ?><a href="consultations.php<?= $k ? '?filter=' . $k : '' ?>"<?= $filter === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="card"><p class="empty">No consultations here yet. <a href="consultation-edit.php">Log one</a></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Date</th><th>Client</th><th>Topic</th><th>Status</th><th class="r">Time</th><th class="r">Amount</th><th>Billing</th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr data-href="consultation-edit.php?id=<?= (int)$r['id'] ?>"><td class="num"><a href="consultation-edit.php?id=<?= (int)$r['id'] ?>"><?= e(fmt_date($r['date'], 'M j')) ?></a><?= $r['start_time'] ? ' <small class="muted">' . e(date('g:i a', strtotime($r['start_time']))) . '</small>' : '' ?></td>
      <td><b><?= e(person_name($r)) ?></b><?= $r['company'] ? '<small class="muted"> · ' . e($r['company']) . '</small>' : '' ?></td>
      <td class="muted"><?= e(mb_strimwidth($r['topic'], 0, 50, '…')) ?></td><td><?= pill('cs', $r['status'], CONSULT_STATUSES[$r['status']]) ?></td>
      <td class="r num"><?= hours($r['minutes']) ?></td><td class="r num"><?= $r['billable'] ? money($r['minutes'] * $r['rate'] / 60) : '<span class="muted">No charge</span>' ?></td>
      <td><?= $r['invoice_id'] ? '<a href="invoice.php?id=' . (int)$r['invoice_id'] . '">' . e($r['inv_number']) . '</a>' : ($r['status'] === 'completed' && $r['billable'] ? '<span class="warn">Unbilled</span>' : '<span class="muted">—</span>') ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
