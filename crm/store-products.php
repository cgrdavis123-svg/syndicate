<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
$user = require_user();
if (is_post() && post('action') === 'toggle') {
    $p = one('SELECT * FROM products WHERE id = ?', [(int)post('id')]);
    if ($p) { q('UPDATE products SET active = ?, updated_at = ? WHERE id = ?', [$p['active'] ? 0 : 1, now(), $p['id']]); flash($p['name'] . ($p['active'] ? ' hidden from the store.' : ' is back in the store.')); }
    redirect('store-products.php');
}
$by = [];
foreach (shop_products(false) as $p) $by[$p['category']][] = $p;
$sold = []; foreach (all("SELECT i.sku, COUNT(*) n, SUM(i.price * i.quantity) v FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status = 'paid' GROUP BY i.sku") as $r) $sold[$r['sku']] = $r;
layout_top('Products', 'store', $user);
?>
<div class="page-head">
  <div><a class="muted small" href="store.php">← Store</a><h1>Products</h1><p class="muted small">What’s for sale on the <?= e(SHOP_BRAND) ?> website. Price changes show on the site within a minute.</p></div>
  <a class="btn primary sm" href="store-product.php">+ New product</a>
</div>
<?php foreach (SHOP_CATEGORIES as $cat => $label): if (empty($by[$cat])) continue; ?>
<section class="card flush" style="margin-bottom:16px">
  <div class="card-h" style="padding:18px 20px 0"><h2><?= e($label) ?></h2></div>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Product</th><th class="r">Price</th><th class="r">Sold</th><th>Status</th><th></th></tr></thead>
    <tbody><?php foreach ($by[$cat] as $p): ?>
      <tr data-href="store-product.php?id=<?= (int)$p['id'] ?>"><td><a class="cell-main" href="store-product.php?id=<?= (int)$p['id'] ?>"><b><?= e($p['name']) ?><?= $p['badge'] ? ' <span class="tag lime">' . e($p['badge']) . '</span>' : '' ?></b><small><?= e(mb_strimwidth($p['blurb'], 0, 110, '…')) ?></small></a></td>
        <td class="r num"><b><?= e(price_label((float)$p['price'], $p['interval'], $p['unit'])) ?></b></td>
        <td class="r num muted"><?= isset($sold[$p['sku']]) ? (int)$sold[$p['sku']]['n'] . ' · ' . money_short($sold[$p['sku']]['v']) : '—' ?></td>
        <td><?= $p['active'] ? pill('ord', 'paid', 'For sale') : pill('ord', 'expired', 'Hidden') ?></td>
        <td class="r"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn ghost xs" type="submit"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endforeach; ?>
<?php layout_bottom(); ?>
