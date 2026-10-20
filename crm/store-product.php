<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
$user = require_user();
$id = (int)get('id');
$p = ['sku' => '', 'category' => get('category') ?: 'website', 'name' => '', 'blurb' => '', 'features' => '', 'price' => '', 'interval' => '', 'unit' => '', 'max_qty' => 1,
    'requires' => '', 'badge' => '', 'featured' => 0, 'timeline_days' => 14, 'steps' => '', 'site' => 0, 'active' => 1, 'position' => (int)val('SELECT COALESCE(MAX(position), 0) + 10 FROM products')];
if ($id) { $p = one('SELECT * FROM products WHERE id = ?', [$id]); if (!$p) { flash('That product no longer exists.'); redirect('store-products.php'); } }
$error = '';
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        if (val('SELECT 1 FROM order_items WHERE product_id = ?', [$id])) { q('UPDATE products SET active = 0 WHERE id = ?', [$id]); flash('This product has orders, so it was hidden instead of deleted.'); }
        else { q('DELETE FROM products WHERE id = ?', [$id]); flash('Product deleted.'); }
        redirect('store-products.php');
    }
    $p = array_merge($p, [
        'category' => isset(SHOP_CATEGORIES[post('category')]) ? post('category') : 'website', 'name' => mb_substr(post('name'), 0, 120), 'blurb' => mb_substr(post('blurb'), 0, 400),
        'features' => implode("\n", array_slice(product_lines(post('features')), 0, 12)), 'price' => round(max(0, (float)post('price')), 2), 'interval' => post('interval') === 'month' ? 'month' : '',
        'unit' => mb_substr(post('unit'), 0, 30), 'max_qty' => max(1, min(100, (int)post('max_qty'))), 'requires' => implode(',', array_intersect((array)($_POST['requires'] ?? []), array_keys(SHOP_CATEGORIES))),
        'badge' => mb_substr(post('badge'), 0, 24), 'featured' => post('featured') === '1' ? 1 : 0, 'timeline_days' => max(0, min(365, (int)post('timeline_days'))),
        'free_with' => implode(',', array_intersect((array)($_POST['free_with'] ?? []), array_column(shop_products(false), 'sku'))),
        'steps' => implode("\n", array_slice(product_lines(post('steps')), 0, 30)), 'site' => post('site') === '1' ? 1 : 0, 'active' => post('active') === '1' ? 1 : 0, 'position' => (int)post('position'),
    ]);
    $sku = $id ? $p['sku'] : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', post('sku') ?: $p['name']), '-'));
    if (mb_strlen($p['name']) < 2) $error = 'Give the product a name.';
    elseif ($p['price'] < 1) $error = 'Enter a price of at least $1.';
    elseif (!$id && ($sku === '' || val('SELECT 1 FROM products WHERE sku = ?', [$sku]))) $error = 'That product code is already used. Choose another.';
    else {
        $cols = ['category', 'name', 'blurb', 'features', 'price', 'interval', 'unit', 'max_qty', 'requires', 'free_with', 'badge', 'featured', 'timeline_days', 'steps', 'site', 'active', 'position'];
        $vals = array_map(function ($c) use ($p) { return $p[$c]; }, $cols);
        if ($id) q('UPDATE products SET ' . implode(', ', array_map(function ($c) { return "$c = ?"; }, $cols)) . ', updated_at = ? WHERE id = ?', array_merge($vals, [now(), $id]));
        else { q('INSERT INTO products (sku, ' . implode(', ', $cols) . ', created_at, updated_at) VALUES (?' . str_repeat(',?', count($cols)) . ',?,?)', array_merge([$sku], $vals, [now(), now()])); $id = (int)db()->lastInsertId(); }
        flash('Saved. The website shows the change within a minute.'); redirect('store-products.php');
    }
}
$req = array_filter(explode(',', $p['requires']));
$freeWith = array_filter(explode(',', (string)($p['free_with'] ?? '')));
layout_top($id ? $p['name'] : 'New product', 'store', $user);
?>
<div class="page-head"><div><a class="muted small" href="store-products.php">← Products</a><h1><?= $id ? e($p['name']) : 'New product' ?></h1><?php if ($id): ?><p class="muted small mono"><?= e($p['sku']) ?></p><?php endif; ?></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="grid-detail">
  <?= csrf_field() ?>
  <div class="card form-grid">
    <div class="card-h span2"><h2>On the website</h2></div>
    <label class="f"><span>Name</span><input name="name" required value="<?= e($p['name']) ?>" placeholder="Starter website"></label>
    <label class="f"><span>Category</span><select name="category"><?php foreach (SHOP_CATEGORIES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $p['category']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Price ($)</span><input name="price" type="number" min="1" step="0.01" required value="<?= e($p['price']) ?>"></label>
    <label class="f"><span>Billed</span><select name="interval"><option value="">Once</option><option value="month"<?= selected('month', $p['interval']) ?>>Every month (subscription)</option></select></label>
    <label class="f span2"><span>Short description</span><textarea name="blurb" rows="2" maxlength="400"><?= e($p['blurb']) ?></textarea></label>
    <label class="f span2"><span>What’s included <small class="muted">(one per line, shown as a checklist)</small></span><textarea name="features" rows="6"><?= e($p['features']) ?></textarea></label>
    <label class="f"><span>Badge <small class="muted">(optional, e.g. Most popular)</small></span><input name="badge" maxlength="24" value="<?= e($p['badge']) ?>"></label>
    <label class="f"><span>Sort order <small class="muted">(lower shows first)</small></span><input name="position" type="number" value="<?= (int)$p['position'] ?>"></label>
    <label class="f"><span>Sold <small class="muted">(e.g. per page; leave empty for one)</small></span><input name="unit" maxlength="30" value="<?= e($p['unit']) ?>"></label>
    <label class="f"><span>Most per order</span><input name="max_qty" type="number" min="1" max="100" value="<?= (int)$p['max_qty'] ?>"></label>
    <?php if (!$id): ?><label class="f span2"><span>Product code <small class="muted">(optional; can’t be changed later)</small></span><input name="sku" pattern="[a-z0-9-]+" placeholder="web-starter"></label><?php endif; ?>
    <label class="chk span2"><input type="checkbox" name="featured" value="1"<?= $p['featured'] ? ' checked' : '' ?>> <span>Highlight this one in its section</span></label>
    <label class="chk span2"><input type="checkbox" name="active" value="1"<?= $p['active'] ? ' checked' : '' ?>> <span>For sale on the website</span></label>
    <div class="save-bar span2">
      <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this product? If it has orders, it’s hidden instead.">Delete product</button><?php else: ?><span></span><?php endif; ?>
      <button class="btn primary sm" type="submit" name="action" value="save">Save product</button>
    </div>
  </div>
  <div class="stack">
    <section class="card form-grid">
      <div class="card-h span2"><h2>After someone buys it</h2></div>
      <label class="f span2"><span>Project steps <small class="muted">(one per line; a project with these steps is created in the client portal)</small></span><textarea name="steps" rows="8" placeholder="Kickoff call&#10;First draft shared&#10;Launch"><?= e($p['steps']) ?></textarea></label>
      <label class="f span2"><span>Days until done <small class="muted">(sets the project due date; 0 for none)</small></span><input name="timeline_days" type="number" min="0" max="365" value="<?= (int)$p['timeline_days'] ?>"></label>
      <label class="chk span2"><input type="checkbox" name="site" value="1"<?= $p['site'] ? ' checked' : '' ?>> <span>Set up Site Stats for the client’s website</span></label>
      <fieldset class="f span2"><span>Only sold together with <small class="muted">(for add-ons)</small></span>
        <?php foreach (['website' => 'A website package', 'ads' => 'An ads plan'] as $k => $l): ?><label class="chk"><input type="checkbox" name="requires[]" value="<?= $k ?>"<?= in_array($k, $req, true) ? ' checked' : '' ?>> <span><?= e($l) ?></span></label><?php endforeach; ?>
      </fieldset>
      <fieldset class="f span2"><span>Included free with <small class="muted">(costs $0 when one of these is in the same order)</small></span>
        <?php foreach (shop_products(false) as $o): if ($o['category'] === 'addon' || $o['sku'] === ($p['sku'] ?? '')) continue; ?><label class="chk"><input type="checkbox" name="free_with[]" value="<?= e($o['sku']) ?>"<?= in_array($o['sku'], $freeWith, true) ? ' checked' : '' ?>> <span><?= e($o['name']) ?></span></label><?php endforeach; ?>
      </fieldset>
      <p class="muted small span2">Add-on steps are added to the package they were bought with, right after the kickoff call.</p>
    </section>
  </div>
</form>
<?php layout_bottom(); ?>
