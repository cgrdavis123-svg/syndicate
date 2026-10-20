<?php
// Special offers: a set price and/or free months on products bought together, with an end date and a limit.
// The website reads them from shop-api.php?a=offer&o=slug and checkout checks them again.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
$user = require_user();
$id = (int)get('id'); $new = get('new') === '1';
$of = $id ? one('SELECT * FROM offers WHERE id = ?', [$id]) : null;
if ($id && !$of) { flash('That offer no longer exists.'); redirect('store-offers.php'); }
$products = shop_products(false);
$error = '';
if (is_post()) {
    $name = mb_substr(trim(post('name')), 0, 160);
    $slug = $of ? $of['slug'] : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', post('slug')), '-'));
    $ends = trim(post('ends_on'));
    $items = [];
    foreach ((array)($_POST['sku'] ?? []) as $i => $sku) {
        $sku = (string)$sku; if ($sku === '' || !val('SELECT 1 FROM products WHERE sku = ?', [$sku])) continue;
        $price = trim((string)($_POST['price'][$i] ?? '')); $months = (int)($_POST['trial'][$i] ?? 0);
        $row = ['sku' => $sku];
        if ($price !== '') $row['price'] = round(max(0, (float)$price), 2);
        if ($months > 0) $row['trial_months'] = min(12, $months);
        $items[$sku] = $row;
    }
    if (mb_strlen($name) < 3) $error = 'Give the offer a name.';
    elseif ($slug === '' || (!$of && val('SELECT 1 FROM offers WHERE slug = ?', [$slug]))) $error = 'Choose a web address for the offer that isn’t taken (letters, numbers and dashes).';
    elseif ($ends !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ends)) $error = 'Pick an end date, or leave it empty for no end date.';
    elseif (!$items) $error = 'Add at least one product to the offer.';
    else {
        $vals = [$name, json_encode(array_values($items), JSON_UNESCAPED_SLASHES), $ends ?: null, max(0, (int)post('max_uses')), post('active') === '1' ? 1 : 0, mb_substr(trim(post('note')), 0, 2000), now()];
        if ($of) q('UPDATE offers SET name = ?, items = ?, ends_on = ?, max_uses = ?, active = ?, note = ?, updated_at = ? WHERE id = ?', array_merge($vals, [$of['id']]));
        else { q('INSERT INTO offers (name, items, ends_on, max_uses, active, note, updated_at, slug, created_at) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($vals, [$slug, now()])); $id = (int)db()->lastInsertId(); }
        flash('Offer saved. The website shows the change within a minute.');
        redirect('store-offers.php?id=' . ($of ? $of['id'] : $id));
    }
}
$STATE = ['live' => ['Live', '#c6ff3d'], 'off' => ['Switched off', '#8a929c'], 'ended' => ['Ended', '#8a929c'], 'full' => ['All spots taken', '#ffb547']];
layout_top($of ? $of['name'] : 'Offers', 'store', $user);
if (!$of && !$new):
    $rows = all('SELECT * FROM offers ORDER BY id DESC');
?>
<div class="page-head"><div><a class="muted small" href="store.php">← Store</a><h1>Offers</h1><p class="muted small">A set price or free months on products bought together, for a campaign. Each offer has its own landing page link; it isn’t shown in the store.</p></div>
  <a class="btn primary sm" href="store-offers.php?new=1">+ New offer</a></div>
<section class="card"><table class="tbl"><thead><tr><th>Offer</th><th>Status</th><th>Ends</th><th class="r">Sold</th><th class="r">Revenue</th></tr></thead><tbody>
<?php foreach ($rows as $r): $st = offer_state($r); $used = offer_used($r); $rev = (float)val("SELECT COALESCE(SUM(paid_total),0) FROM orders WHERE offer = ? AND status = 'paid'", [$r['slug']]); ?>
  <tr><td><a href="store-offers.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b></a><br><span class="muted small mono"><?= e($r['slug']) ?></span></td>
    <td><span class="pill" style="--c:<?= $STATE[$st][1] ?>"><?= $STATE[$st][0] ?></span></td><td><?= $r['ends_on'] ? e(date('M j, Y', strtotime($r['ends_on']))) : '<span class="muted">No end date</span>' ?></td>
    <td class="r num"><?= $used ?><?= (int)$r['max_uses'] ? ' / ' . (int)$r['max_uses'] : '' ?></td><td class="r num"><?= money($rev) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No offers yet.</td></tr><?php endif; ?>
</tbody></table></section>
<?php else:
    $items = $of ? array_values(array_map(function ($sku, $i) { return ['sku' => $sku] + $i; }, array_keys(offer_items($of)), offer_items($of))) : [];
    while (count($items) < 4) $items[] = ['sku' => '', 'price' => null, 'trial_months' => 0];
    $pub = $of ? offer_public($of) : null;
?>
<div class="page-head"><div><a class="muted small" href="store-offers.php">← Offers</a><h1><?= $of ? e($of['name']) : 'New offer' ?></h1><?php if ($of): ?><p class="muted small mono"><?= e($of['slug']) ?></p><?php endif; ?></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<div class="grid-detail">
  <form method="post" class="card form-grid">
    <?= csrf_field() ?>
    <label class="f span2"><span>Name <small class="muted">(shown on order emails and invoices)</small></span><input name="name" required value="<?= e(post('name') ?: ($of['name'] ?? '')) ?>"></label>
    <?php if (!$of): ?><label class="f span2"><span>Short code <small class="muted">(used in the checkout link, can’t be changed later)</small></span><input name="slug" required pattern="[a-zA-Z0-9-]+" value="<?= e(post('slug')) ?>" placeholder="spring-starter"></label><?php endif; ?>
    <label class="f"><span>Last day to buy <small class="muted">(empty = no end)</small></span><input type="date" name="ends_on" value="<?= e(post('ends_on') ?: ($of['ends_on'] ?? '')) ?>"></label>
    <label class="f"><span>Spots <small class="muted">(orders allowed; 0 = no limit)</small></span><input type="number" min="0" name="max_uses" value="<?= (int)(post('max_uses') ?: ($of['max_uses'] ?? 0)) ?>"></label>
    <div class="span2"><span class="muted small">Products, bought together. Leave the price empty to keep the store price. Free months only apply to monthly plans.</span>
      <table class="tbl" style="margin-top:8px"><thead><tr><th>Product</th><th>Offer price</th><th>Free months</th></tr></thead><tbody>
      <?php foreach ($items as $it): ?><tr>
        <td><select name="sku[]"><option value="">—</option><?php foreach ($products as $p): ?><option value="<?= e($p['sku']) ?>"<?= $p['sku'] === $it['sku'] ? ' selected' : '' ?>><?= e($p['name'] . ' · ' . price_label((float)$p['price'], $p['interval'], $p['unit'])) ?></option><?php endforeach; ?></select></td>
        <td><input name="price[]" type="number" min="0" step="0.01" value="<?= $it['price'] !== null ? e((string)$it['price']) : '' ?>" style="width:110px"></td>
        <td><input name="trial[]" type="number" min="0" max="12" value="<?= (int)$it['trial_months'] ?: '' ?>" style="width:80px"></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <label class="f span2"><span>Notes <small class="muted">(for you: where it’s used, who it’s for)</small></span><textarea name="note" rows="2"><?= e(post('note') ?: ($of['note'] ?? '')) ?></textarea></label>
    <label class="chk span2"><input type="checkbox" name="active" value="1"<?= ($of ? (int)$of['active'] : 1) ? ' checked' : '' ?>> <span>Offer is on <small class="muted">(switch off to stop new orders right away)</small></span></label>
    <div class="save-bar span2"><span></span><button class="btn primary sm" type="submit">Save offer</button></div>
  </form>
  <?php if ($of): $orders = all("SELECT * FROM orders WHERE offer = ? AND status IN ('paid','processing','pending','expired') ORDER BY id DESC LIMIT 50", [$of['slug']]); ?>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Right now</h2><span class="pill" style="--c:<?= $STATE[$pub['state']][1] ?>"><?= $STATE[$pub['state']][0] ?></span></div>
      <dl class="facts">
        <div><dt>Pay today</dt><dd class="num"><?= money($pub['today']) ?></dd></div>
        <?php if ($pub['monthly']): ?><div><dt>Then</dt><dd class="num"><?= money($pub['monthly']) ?>/month<?= $pub['trial_months'] ? ' after ' . $pub['trial_months'] . ' free months' : '' ?></dd></div><?php endif; ?>
        <div><dt>Store-price value</dt><dd class="num"><?= money($pub['value']) ?> <span class="muted">(saves <?= money($pub['save']) ?>)</span></dd></div>
        <div><dt>Spots left</dt><dd class="num"><?= $pub['spots'] ? $pub['left'] . ' of ' . $pub['spots'] : 'No limit' ?></dd></div>
        <div><dt>Ends</dt><dd><?= $pub['ends_label'] ? e($pub['ends_label']) . ' (end of day)' : 'No end date' ?></dd></div>
      </dl>
      <?php $site = shop_site_url(); if ($site): ?>
      <p class="muted small" style="margin-top:12px">Checkout link (puts the offer in the cart):</p>
      <div class="code-box"><pre id="offerLink"><?= e($site . '/checkout.html?offer=' . rawurlencode($of['slug'])) ?></pre><button class="btn ghost xs" type="button" data-copy-text="#offerLink">Copy</button></div>
      <?php if ($of['slug'] === 'starter-500'): ?><p class="muted small" style="margin-top:10px">Landing page: <a href="<?= e($site) ?>/starter-website-offer.html" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $site)) ?>/starter-website-offer.html ↗</a>. Personalize it with <code>?b=Business+Name&amp;n=FirstName</code>.</p><?php endif; ?>
      <?php endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>Orders</h2></div>
      <table class="tbl"><tbody><?php foreach ($orders as $o): ?><tr><td><a href="store-order.php?id=<?= (int)$o['id'] ?>"><?= e($o['business'] ?: $o['name']) ?></a><br><span class="muted small"><?= e($o['ref']) ?> · <?= e(ago($o['created_at'])) ?></span></td><td><?= e(ORDER_STATUSES[$o['status']] ?? $o['status']) ?></td><td class="r num"><?= money((float)$o['paid_total']) ?></td></tr><?php endforeach; if (!$orders): ?><tr><td class="empty">No orders yet.</td></tr><?php endif; ?></tbody></table>
    </section>
  </div>
  <?php endif; ?>
</div>
<?php endif; layout_bottom();
