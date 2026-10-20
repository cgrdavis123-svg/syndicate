<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/quotes.php';
$user = require_user();
$id = (int)get('id');
$q = ['brand' => 'Syndicate Marketing', 'title' => '', 'intro' => '', 'contact_id' => (int)get('contact') ?: null, 'company_id' => null, 'deal_id' => (int)get('deal') ?: null,
    'valid_until' => date('Y-m-d', strtotime('+' . max(1, (int)setting('quote_valid_days')) . ' days')), 'deposit_pct' => 50, 'terms' => setting('quote_terms'), 'steps' => '', 'timeline_days' => 0, 'notes' => '', 'status' => 'draft'];
$items = [];
if ($id) {
    $q = one('SELECT * FROM quotes WHERE id = ?', [$id]);
    if (!$q) { flash('That quote no longer exists.'); redirect('quotes.php'); }
    if (in_array($q['status'], ['won', 'accepted'], true)) { flash('An accepted quote can’t be edited. Duplicate it to make changes.'); redirect('quote.php?id=' . $id); }
    $items = quote_items($id);
} elseif ($q['deal_id'] && ($d = one('SELECT * FROM deals WHERE id = ?', [$q['deal_id']]))) {
    $q['contact_id'] = $q['contact_id'] ?: $d['contact_id']; $q['company_id'] = $d['company_id']; $q['brand'] = $d['brand'] ?: $q['brand']; $q['title'] = preg_replace('/\s+—\s+.*$/', '', $d['title']);
}
$error = '';
if (is_post()) {
    $q = array_merge($q, [
        'brand' => in_array(post('brand'), BRANDS, true) ? post('brand') : 'Syndicate Marketing', 'title' => mb_substr(post('title'), 0, 150), 'intro' => mb_substr(post('intro'), 0, 4000),
        'contact_id' => (int)post('contact_id') ?: null, 'deal_id' => (int)post('deal_id') ?: null, 'valid_until' => valid_date(post('valid_until')) ? post('valid_until') : null,
        'deposit_pct' => array_key_exists((int)post('deposit_pct'), QUOTE_DEPOSITS) ? (int)post('deposit_pct') : 100, 'terms' => mb_substr(post('terms'), 0, 6000),
        'steps' => implode("\n", array_slice(product_lines(post('steps')), 0, 40)), 'timeline_days' => max(0, min(730, (int)post('timeline_days'))), 'notes' => mb_substr(post('notes'), 0, 4000),
    ]);
    $c = $q['contact_id'] ? one('SELECT * FROM contacts WHERE id = ?', [$q['contact_id']]) : null;
    $q['company_id'] = $c['company_id'] ?? null;
    $items = [];
    foreach ((array)($_POST['desc'] ?? []) as $i => $desc) {
        $desc = trim((string)$desc); $price = round((float)($_POST['price'][$i] ?? 0), 2);
        if ($desc === '' && $price == 0) continue;
        $items[] = ['product_id' => (int)($_POST['product'][$i] ?? 0) ?: null, 'description' => mb_substr($desc ?: 'Item', 0, 200), 'details' => mb_substr(trim((string)($_POST['details'][$i] ?? '')), 0, 600),
            'quantity' => max(0, round((float)($_POST['qty'][$i] ?? 1), 2)), 'unit_price' => max(0, $price), 'interval' => ($_POST['interval'][$i] ?? '') === 'month' ? 'month' : '',
            'optional' => ($_POST['optional'][$i] ?? '') === '1' ? 1 : 0, 'selected' => ($_POST['optional'][$i] ?? '') === '1' ? 0 : 1];
    }
    if (!$c) $error = 'Choose who the quote is for. Add them under Contacts first if they’re new.';
    elseif (!$c['email']) $error = 'That contact has no email address. Add one so they can receive the quote.';
    elseif (mb_strlen($q['title']) < 2) $error = 'Give the quote a title, like “New website and Google Ads”.';
    elseif (!$items) $error = 'Add at least one line item.';
    elseif (!array_filter($items, function ($i) { return !$i['optional']; })) $error = 'At least one item must be included (not optional).';
    else {
        $cols = ['brand', 'title', 'intro', 'contact_id', 'company_id', 'deal_id', 'valid_until', 'deposit_pct', 'terms', 'steps', 'timeline_days', 'notes'];
        $vals = array_map(function ($k) use ($q) { return $q[$k]; }, $cols);
        if ($id) q('UPDATE quotes SET ' . implode(', ', array_map(function ($k) { return "$k = ?"; }, $cols)) . ", status = CASE WHEN status = 'expired' THEN 'sent' ELSE status END, updated_at = ? WHERE id = ?", array_merge($vals, [now(), $id]));
        else { q('INSERT INTO quotes (number, token, ' . implode(', ', $cols) . ', created_at, updated_at) VALUES (?,?' . str_repeat(',?', count($cols)) . ',?,?)', array_merge([next_quote_number(), bin2hex(random_bytes(16))], $vals, [now(), now()])); $id = (int)db()->lastInsertId(); }
        q('DELETE FROM quote_items WHERE quote_id = ?', [$id]);
        foreach ($items as $pos => $it) q('INSERT INTO quote_items (quote_id, product_id, description, details, quantity, unit_price, interval, optional, selected, position) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$id, $it['product_id'], $it['description'], $it['details'], $it['quantity'], $it['unit_price'], $it['interval'], $it['optional'], $it['selected'], $pos]);
        flash('Quote saved.'); redirect('quote.php?id=' . $id);
    }
}
if (!$items) $items[] = ['product_id' => null, 'description' => '', 'details' => '', 'quantity' => 1, 'unit_price' => '', 'interval' => '', 'optional' => 0];
$products = array_map(function ($p) { return ['id' => (int)$p['id'], 'name' => $p['name'], 'details' => $p['blurb'], 'price' => (float)$p['price'], 'interval' => $p['interval'], 'steps' => product_lines($p['steps']), 'days' => (int)$p['timeline_days'], 'cat' => SHOP_CATEGORIES[$p['category']] ?? $p['category']]; }, shop_products(false));
$row = function (array $it) { ?>
  <div class="qitem">
    <input type="hidden" name="product[]" value="<?= (int)($it['product_id'] ?? 0) ?>">
    <div class="qi-main"><input name="desc[]" value="<?= e($it['description']) ?>" placeholder="e.g. Starter website (5 pages)" aria-label="Item"><input name="details[]" value="<?= e($it['details'] ?? '') ?>" placeholder="Details the client sees (optional)" aria-label="Details" class="qi-details"></div>
    <input name="qty[]" type="number" step="0.01" min="0" value="<?= e((float)$it['quantity']) ?>" aria-label="Quantity">
    <input name="price[]" type="number" step="0.01" min="0" value="<?= e($it['unit_price'] === '' ? '' : (float)$it['unit_price']) ?>" placeholder="0.00" aria-label="Price">
    <select name="interval[]" aria-label="Billed"><option value="">Once</option><option value="month"<?= selected('month', $it['interval']) ?>>Monthly</option></select>
    <select name="optional[]" aria-label="Included or optional"><option value="0">Included</option><option value="1"<?= selected(1, $it['optional']) ?>>Optional</option></select>
    <span class="r num amt">$0.00</span>
    <button class="x" type="button" aria-label="Remove line">×</button>
  </div>
<?php };
layout_top($id ? 'Edit ' . $q['number'] : 'New quote', 'quotes', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'quote.php?id=' . $id : 'quotes.php' ?>">← <?= $id ? e($q['number']) : 'Quotes' ?></a><h1><?= $id ? 'Edit quote' : 'New quote' ?></h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="stack" id="quoteForm">
  <?= csrf_field() ?>
  <div class="card form-grid">
    <div class="card-h span2"><h2>Proposal</h2></div>
    <label class="f"><span>For</span><select name="contact_id" required><option value="">Choose a contact…</option><?php foreach (contact_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $q['contact_id']) ?>><?= e(person_name($o)) ?><?= $o['company'] ? ' · ' . e($o['company']) : '' ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Deal <small class="muted">(optional; marked won when accepted)</small></span><select name="deal_id"><option value="">— New deal when won —</option><?php foreach (deal_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $q['deal_id']) ?>><?= e($o['title']) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Title</span><input name="title" required value="<?= e($q['title']) ?>" placeholder="New website and Google Ads"></label>
    <label class="f"><span>From</span><select name="brand"><?php foreach (BRANDS as $b): ?><option<?= selected($b, $q['brand']) ?>><?= e($b) ?></option><?php endforeach; ?></select></label>
    <label class="f span2"><span>Message to the client <small class="muted">(shown at the top of the proposal: their goals and what you’ll do)</small></span><textarea name="intro" rows="5" placeholder="Thanks for walking me through the business. Here’s what we discussed: …"><?= e($q['intro']) ?></textarea></label>
  </div>

  <div class="card">
    <div class="card-h"><h2>Line items</h2>
      <div class="row"><select id="addProduct" aria-label="Add a product" data-products="<?= e(json_encode($products, JSON_UNESCAPED_UNICODE)) ?>"><option value="">+ Add from products…</option><?php $cat = ''; foreach ($products as $p): if ($p['cat'] !== $cat): echo $cat !== '' ? '</optgroup>' : ''; $cat = $p['cat']; ?><optgroup label="<?= e($cat) ?>"><?php endif; ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> · <?= e(price_label($p['price'], $p['interval'])) ?></option><?php endforeach; echo $cat !== '' ? '</optgroup>' : ''; ?></select><button class="btn ghost xs" type="button" id="addQItem">+ Custom line</button></div></div>
    <div class="qitems" id="qitems">
      <div class="qitem head"><span>Item</span><span>Qty</span><span>Price</span><span>Billed</span><span>Type</span><span class="r">Amount</span><span></span></div>
      <?php foreach ($items as $it) $row($it); ?>
    </div>
    <template id="qitemTpl"><?php $row(['product_id' => null, 'description' => '', 'details' => '', 'quantity' => 1, 'unit_price' => '', 'interval' => '', 'optional' => 0]); ?></template>
    <div class="totals qtotals">
      <div><span>One-time</span><b class="num" id="qOne">$0.00</b></div>
      <div><span>Monthly</span><b class="num" id="qMo">$0.00</b></div>
      <div><span>Optional extras offered</span><b class="num" id="qOpt">$0.00</b></div>
      <div class="grand"><span>Due when accepted</span><b class="num" id="qDue">$0.00</b></div>
    </div>
    <p class="muted small">Optional items appear as checkboxes the client can tick. Monthly items become a subscription in Stripe that renews until cancelled.</p>
  </div>

  <div class="grid-detail">
    <div class="card form-grid">
      <div class="card-h span2"><h2>Payment &amp; terms</h2></div>
      <label class="f span2"><span>Payment</span><select name="deposit_pct" id="depositPct"><?php foreach (QUOTE_DEPOSITS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $q['deposit_pct']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label class="f"><span>Valid until</span><input type="date" name="valid_until" value="<?= e($q['valid_until']) ?>"></label>
      <label class="f"><span>Days to complete <small class="muted">(sets the project due date)</small></span><input type="number" name="timeline_days" id="timelineDays" min="0" max="730" value="<?= (int)$q['timeline_days'] ?>"></label>
      <label class="f span2"><span>Terms <small class="muted">(the client agrees to these when accepting)</small></span><textarea name="terms" rows="6"><?= e($q['terms']) ?></textarea></label>
    </div>
    <div class="card form-grid">
      <div class="card-h span2"><h2>When it’s accepted</h2></div>
      <label class="f span2"><span>Project steps <small class="muted">(one per line; filled in from products you add)</small></span><textarea name="steps" id="qSteps" rows="8" placeholder="Kickoff call&#10;First draft shared&#10;Launch"><?= e($q['steps']) ?></textarea></label>
      <label class="f span2"><span>Private notes <small class="muted">(only you see these)</small></span><textarea name="notes" rows="3"><?= e($q['notes']) ?></textarea></label>
    </div>
  </div>
  <div class="save-bar"><span class="muted small">Saving keeps it as a draft until you send it.</span><button class="btn primary sm" type="submit">Save quote</button></div>
</form>
<?php layout_bottom(); ?>
