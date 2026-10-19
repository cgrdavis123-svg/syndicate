<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
$user = require_user();
$status = get('status', 'paid');
$filters = ['paid' => 'Paid', 'processing' => 'Processing', 'unfinished' => 'Not finished', 'refunded' => 'Refunded', 'all' => 'All'];
if (!isset($filters[$status])) $status = 'paid';
$where = ['paid' => "o.status = 'paid'", 'processing' => "o.status = 'processing'", 'unfinished' => "o.status IN ('pending','expired','failed')", 'refunded' => "o.status = 'refunded'", 'all' => '1=1'][$status];
$rows = all("SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) n FROM orders o WHERE $where ORDER BY COALESCE(o.paid_at, o.created_at) DESC LIMIT 300");
$itemsBy = [];
if ($rows) foreach (all('SELECT * FROM order_items WHERE order_id IN (' . implode(',', array_map('intval', array_column($rows, 'id'))) . ') ORDER BY id') as $i) $itemsBy[$i['order_id']][] = $i;
$month = date('Y-m-01');
$k = [
    'sales' => (float)val("SELECT COALESCE(SUM(ii.quantity * ii.unit_price), 0) FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id WHERE i.brand = ? AND i.paid_method = 'Stripe' AND i.status = 'paid' AND i.paid_at >= ?", [SHOP_BRAND, $month]),
    'orders' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND paid_at >= ?", [$month]),
    'mrr' => (float)val("SELECT COALESCE(SUM(monthly), 0) FROM orders WHERE status = 'paid' AND sub_status IN ('active','trialing','past_due')"),
    'subs' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND sub_status IN ('active','trialing','past_due')"),
    'open' => (int)val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','expired') AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]),
    'open_value' => (float)val("SELECT COALESCE(SUM(one_time + monthly), 0) FROM orders WHERE status IN ('pending','expired') AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]),
];
$started = (int)val('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [date('Y-m-d', strtotime('-30 days'))]);
$paid30 = (int)val("SELECT COUNT(*) FROM orders WHERE status IN ('paid','refunded') AND created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
$counts = []; foreach (all('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
layout_top('Store', 'store', $user);
?>
<div class="page-head">
  <div><p class="eyebrow"><?= e(SHOP_BRAND) ?> website<?= shop_site_url() ? ' · ' . e(preg_replace('#^https?://#', '', shop_site_url())) : '' ?><?= shop_ready() && !stripe_live() ? ' · <span class="warn">Stripe test mode</span>' : '' ?></p><h1>Store</h1></div>
  <div class="row"><a class="btn ghost sm" href="store-products.php">Products</a><a class="btn ghost sm" href="store-offers.php">Offers</a><a class="btn ghost sm" href="store-settings.php">Settings</a><?php if (shop_site_url()): ?><a class="btn primary sm" href="<?= e(shop_site_url()) ?>/pricing.html" target="_blank" rel="noopener">View store ↗</a><?php endif; ?></div>
</div>
<?php if (!shop_ready()): ?>
  <div class="notice"><b>The store isn’t taking payments yet.</b> Add your Stripe secret key and the website address in <a href="store-settings.php">Store → Settings</a>. Until then, the checkout on the website asks people to email you instead.</div>
<?php endif; ?>
<div class="mini-kpis">
  <div><span>Sales in <?= date('F') ?></span><b class="num"><?= money($k['sales']) ?></b><small>including monthly renewals</small></div>
  <div><span>Orders in <?= date('F') ?></span><b class="num"><?= $k['orders'] ?></b></div>
  <div><span>Monthly plans</span><b class="num"><?= money($k['mrr']) ?>/mo</b><small><?= $k['subs'] ?> active</small></div>
  <div><span>Checkout → paid, 30 days</span><b class="num"><?= $started ? round($paid30 / $started * 100) . '%' : '—' ?></b><small><?= $paid30 ?> of <?= $started ?> checkouts</small></div>
  <div><span>Not finished, 30 days</span><b class="num <?= $k['open'] ? 'warn' : '' ?>"><?= $k['open'] ?></b><small><?= money($k['open_value'], false) ?> left in carts</small></div>
</div>
<div class="seg-links" style="margin-bottom:4px"><?php foreach ($filters as $key => $l): $n = $key === 'unfinished' ? ($counts['pending'] ?? 0) + ($counts['expired'] ?? 0) + ($counts['failed'] ?? 0) : ($counts[$key] ?? 0); ?><a href="store.php?status=<?= $key ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= e($l) ?><?= $key !== 'all' && $n ? ' <span class="dim num">' . $n . '</span>' : '' ?></a><?php endforeach; ?></div>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= $status === 'paid' ? 'No paid orders yet. When someone checks out on the website, the order shows up here with its deal, invoice and project already set up.' : 'Nothing here.' ?></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Order</th><th>Customer</th><th>What they bought</th><th>Status</th><th class="r">Paid</th></tr></thead>
  <tbody><?php foreach ($rows as $r): $items = $itemsBy[$r['id']] ?? []; ?>
    <tr data-href="store-order.php?id=<?= (int)$r['id'] ?>"><td><a class="cell-main" href="store-order.php?id=<?= (int)$r['id'] ?>"><b class="num"><?= e($r['ref']) ?><?= $r['status'] === 'paid' && !$r['seen_at'] ? ' <span class="tag lime">New</span>' : '' ?></b><small><?= e(ago($r['paid_at'] ?: $r['created_at'])) ?><?= $r['live'] ? '' : ' · test' ?></small></a></td>
      <td><span class="cell-main"><b><?= e($r['business'] ?: $r['name']) ?></b><small><?= e($r['name']) ?> · <?= e($r['email']) ?></small></span></td>
      <td class="small"><?= e(order_summary($items)) ?></td>
      <td><?= pill('ord', $r['status'], ORDER_STATUSES[$r['status']] ?? $r['status']) ?><?php if ($r['sub_status'] && $r['status'] === 'paid'): ?> <small class="<?= in_array($r['sub_status'], ['past_due', 'unpaid'], true) ? 'warn' : 'muted' ?>">· plan <?= e(strtolower(SUB_STATUSES[$r['sub_status']] ?? $r['sub_status'])) ?></small><?php endif; ?></td>
      <td class="r num"><b><?= $r['status'] === 'paid' || $r['status'] === 'refunded' ? money($r['paid_total']) : '<span class="muted">' . money($r['one_time'] + $r['monthly']) . '</span>' ?></b><?= $r['monthly'] > 0 ? '<small class="muted" style="display:block">+ ' . money($r['monthly'], false) . '/mo</small>' : '' ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
