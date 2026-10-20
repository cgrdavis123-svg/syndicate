<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/quotes.php';
$user = require_user();
$f = get('status', 'open');
$filters = ['open' => 'Open', 'draft' => 'Drafts', 'won' => 'Won', 'lost' => 'Declined & expired', 'all' => 'All'];
if (!isset($filters[$f])) $f = 'open';
$where = ['open' => "q.status IN ('sent','viewed','accepted') AND (q.valid_until IS NULL OR q.valid_until >= '" . today() . "' OR q.status = 'accepted')", 'draft' => "q.status = 'draft'", 'won' => "q.status = 'won'",
    'lost' => "(q.status IN ('declined','expired') OR (q.status IN ('sent','viewed') AND q.valid_until < '" . today() . "'))", 'all' => '1=1'][$f];
$rows = all("SELECT q.*, co.name company, c.first_name, c.last_name, c.email FROM quotes q LEFT JOIN companies co ON co.id = q.company_id LEFT JOIN contacts c ON c.id = q.contact_id WHERE $where ORDER BY q.updated_at DESC LIMIT 300");
$tot = function (array $q) { return quote_totals($q, quote_items((int)$q['id'])); };
$open = 0; $openN = 0;
foreach (all("SELECT * FROM quotes WHERE status IN ('sent','viewed','accepted') AND (valid_until IS NULL OR valid_until >= ? OR status = 'accepted')", [today()]) as $q) { $t = $tot($q); $open += $t['one_time'] + $t['monthly']; $openN++; }
$since = date('Y-m-d', strtotime('-90 days'));
$won90 = (int)val("SELECT COUNT(*) FROM quotes WHERE status = 'won' AND updated_at >= ?", [$since]);
$decided90 = (int)val("SELECT COUNT(*) FROM quotes WHERE status IN ('won','declined','expired') AND updated_at >= ?", [$since]);
$wonMonth = 0; foreach (all("SELECT * FROM quotes WHERE status = 'won' AND updated_at >= ?", [date('Y-m-01')]) as $q) { $t = $tot($q); $wonMonth += $t['one_time'] + $t['monthly']; }
layout_top('Quotes', 'quotes', $user);
?>
<div class="page-head"><div><p class="eyebrow">Proposals clients accept and pay online</p><h1>Quotes</h1></div><a class="btn primary sm" href="quote-edit.php">+ New quote</a></div>
<div class="mini-kpis">
  <div><span>Open proposals</span><b class="num"><?= money($open, false) ?></b><small><?= $openN ?> waiting on clients</small></div>
  <div><span>Won in <?= date('F') ?></span><b class="num"><?= money($wonMonth, false) ?></b></div>
  <div><span>Win rate, 90 days</span><b class="num"><?= $decided90 ? round($won90 / $decided90 * 100) . '%' : '—' ?></b><small><?= $won90 ?> of <?= $decided90 ?> decided</small></div>
</div>
<div class="seg-links" style="margin-bottom:4px"><?php foreach ($filters as $k => $l): ?><a href="quotes.php?status=<?= $k ?>"<?= $f === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></div>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= $f === 'open' ? 'No open proposals. Create a quote for a lead, send them the link, and they can accept and pay online.' : 'Nothing here.' ?> <a href="quote-edit.php">New quote</a></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Quote</th><th>Client</th><th>Status</th><th>Valid until</th><th class="r">Value</th></tr></thead>
  <tbody><?php foreach ($rows as $r): $t = $tot($r); $st = quote_expired($r) ? 'expired' : $r['status']; ?>
    <tr data-href="quote.php?id=<?= (int)$r['id'] ?>"><td><a class="cell-main" href="quote.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['title'] ?: $r['number']) ?></b><small class="num"><?= e($r['number']) ?> · <?= e($r['brand']) ?></small></a></td>
      <td><?= e(quote_client($r)) ?></td>
      <td><?= quote_pill($st) ?><?= $r['viewed_at'] && in_array($st, ['sent', 'viewed'], true) ? ' <small class="muted">· opened ' . e(ago($r['viewed_at'])) . '</small>' : '' ?></td>
      <td class="muted num"><?= $r['valid_until'] ? e(fmt_date($r['valid_until'], 'M j')) : '—' ?></td>
      <td class="r num"><b><?= money($t['one_time'], false) ?></b><?= $t['monthly'] > 0 ? '<small class="muted" style="display:block">+ ' . money($t['monthly'], false) . '/mo</small>' : '' ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
