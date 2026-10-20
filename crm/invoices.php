<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/recurring.php';
$user = require_user();
if ($msg = recurring_message(run_recurring())) flash($msg);
$status = get('status');
$args = []; $where = '';
if (isset(INVOICE_STATUSES[$status])) { $where = ' WHERE i.status = ?'; $args[] = $status; }
elseif ($status === 'recurring') { $where = " WHERE i.repeat_every != ''"; }
elseif ($status === 'overdue') { $where = " WHERE i.status = 'sent' AND i.due_date < ?"; $args[] = today(); }
$rows = all("SELECT i.*, co.name company, c.first_name, c.last_name, c.email FROM invoices i LEFT JOIN companies co ON co.id = i.company_id LEFT JOIN contacts c ON c.id = i.contact_id$where ORDER BY i.issue_date DESC, i.id DESC", $args);
$sum = ['draft' => 0, 'sent' => 0, 'overdue' => 0, 'paid_year' => 0, 'mrr' => 0, 'repeating' => 0];
foreach (all('SELECT id, status, due_date, paid_at, repeat_every FROM invoices') as $i) {
    $t = invoice_total((int)$i['id'])['total'];
    if ($i['repeat_every'] !== '') { $sum['mrr'] += per_month($t, $i['repeat_every']); $sum['repeating']++; }
    if ($i['status'] === 'draft') $sum['draft'] += $t;
    if ($i['status'] === 'sent') { $sum['sent'] += $t; if ($i['due_date'] < today()) $sum['overdue'] += $t; }
    if ($i['status'] === 'paid' && substr((string)$i['paid_at'], 0, 4) === date('Y')) $sum['paid_year'] += $t;
}
layout_top('Invoices', 'invoices', $user);
?>
<div class="page-head"><div><p class="eyebrow"><?= count($rows) ?> invoice<?= count($rows) === 1 ? '' : 's' ?></p><h1>Invoices</h1></div><div class="row"><a class="btn ghost sm" href="export.php?type=invoices">Export CSV</a><a class="btn primary sm" href="invoice-edit.php">+ New invoice</a></div></div>
<div class="mini-kpis">
  <div><span>Outstanding</span><b class="num"><?= money($sum['sent']) ?></b></div>
  <div><span>Overdue</span><b class="num <?= $sum['overdue'] ? 'warn' : '' ?>"><?= money($sum['overdue']) ?></b></div>
  <div><span>Drafts</span><b class="num"><?= money($sum['draft']) ?></b></div>
  <div><span>Paid in <?= date('Y') ?></span><b class="num"><?= money($sum['paid_year']) ?></b></div>
  <?php if ($sum['repeating']): ?><div><span>Recurring / month</span><b class="num"><?= money($sum['mrr']) ?></b><small><?= $sum['repeating'] ?> repeating invoice<?= $sum['repeating'] === 1 ? '' : 's' ?></small></div><?php endif; ?>
</div>
<div class="seg-links" style="margin-bottom:4px"><?php foreach (['' => 'All', 'draft' => 'Drafts', 'sent' => 'Sent', 'overdue' => 'Overdue', 'paid' => 'Paid', 'recurring' => 'Recurring', 'void' => 'Void'] as $k => $l): ?><a href="invoices.php<?= $k ? '?status=' . $k : '' ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></div>
<?php if (!$rows): ?><div class="card"><p class="empty">No invoices here. <a href="invoice-edit.php">Create one</a>, or bill unbilled hours from <a href="consultations.php?filter=unbilled">Consultations</a>.</p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Invoice</th><th>Client</th><th>Issued</th><th>Due</th><th>Status</th><th class="r">Total</th></tr></thead>
  <tbody><?php foreach ($rows as $r): $late = $r['status'] === 'sent' && $r['due_date'] < today(); ?>
    <tr data-href="invoice.php?id=<?= (int)$r['id'] ?>"><td><a class="cell-main" href="invoice.php?id=<?= (int)$r['id'] ?>"><b class="num"><?= e($r['number']) ?></b><small><?= e($r['brand']) ?><?= $r['repeat_every'] ? ' · ↻ ' . e(REPEAT_ADJ[$r['repeat_every']]) . ', next ' . e(fmt_date($r['repeat_next'], 'M j')) : '' ?></small></a></td>
      <td><?= e($r['company'] ?: person_name($r)) ?></td><td class="muted num"><?= e(fmt_date($r['issue_date'], 'M j, Y')) ?></td>
      <td class="num <?= $late ? 'warn' : 'muted' ?>"><?= e(fmt_date($r['due_date'], 'M j, Y')) ?><?= $late ? ' · overdue' : '' ?></td>
      <td><?= pill('inv', $late ? 'overdue' : $r['status'], $late ? 'Overdue' : INVOICE_STATUSES[$r['status']]) ?><?= $r['viewed_at'] && $r['status'] === 'sent' ? ' <small class="muted" title="Client opened it ' . e(ago($r['viewed_at'])) . '">· viewed</small>' : '' ?></td><td class="r num"><b><?= money(invoice_total((int)$r['id'])['total']) ?></b></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
