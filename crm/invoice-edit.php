<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$id = (int)get('id');
$terms = (int)setting('invoice_terms_days') ?: 14;
$inv = ['brand' => 'Syndicate Holdings Group', 'company_id' => (int)get('company'), 'contact_id' => (int)get('contact'), 'issue_date' => today(), 'due_date' => date('Y-m-d', strtotime("+$terms days")), 'tax_rate' => 0, 'notes' => '', 'status' => 'draft', 'payment_url' => setting('payment_link_default'), 'repeat_every' => '', 'repeat_next' => '', 'repeat_send' => 0];
$items = [];
if ($id) {
    $inv = one('SELECT * FROM invoices WHERE id = ?', [$id]);
    if (!$inv) { flash('That invoice no longer exists.'); redirect('invoices.php'); }
    $items = all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position, id', [$id]);
} else {
    if ($dealId = (int)get('deal')) {
        $d = one('SELECT * FROM deals WHERE id = ?', [$dealId]);
        if ($d) { $inv['company_id'] = (int)$d['company_id']; $inv['contact_id'] = (int)$d['contact_id']; $inv['brand'] = $d['brand'] ?: $inv['brand']; if ($d['value'] > 0) $items[] = ['description' => $d['title'], 'quantity' => 1, 'unit_price' => $d['value'], 'consultation_id' => null]; }
    }
    if (get('from_consults') === '1') {
        $inv['brand'] = 'Creator Ops Media';
        $cs = all("SELECT * FROM consultations WHERE status = 'completed' AND billable = 1 AND invoice_id IS NULL AND COALESCE(company_id,0) = CAST(? AS INTEGER) AND COALESCE(contact_id,0) = CAST(? AS INTEGER) ORDER BY date", [$inv['company_id'], $inv['contact_id']]);
        foreach ($cs as $c) $items[] = ['description' => 'TikTok Shop consultation · ' . fmt_date($c['date']) . ($c['topic'] ? ' · ' . $c['topic'] : ''), 'quantity' => round($c['minutes'] / 60, 2), 'unit_price' => $c['rate'], 'consultation_id' => $c['id']];
    }
    if ($inv['contact_id'] && !$inv['company_id'] && ($c = one('SELECT company_id FROM contacts WHERE id = ?', [$inv['contact_id']]))) $inv['company_id'] = (int)$c['company_id'];
}
if (!$items) $items[] = ['description' => '', 'quantity' => 1, 'unit_price' => '', 'consultation_id' => null];
$error = '';

if (is_post()) {
    foreach (['brand', 'company_id', 'contact_id', 'issue_date', 'due_date', 'tax_rate', 'notes', 'payment_url'] as $k) $inv[$k] = post($k);
    if ($inv['payment_url'] !== '' && !preg_match('#^https://#i', $inv['payment_url'])) $inv['payment_url'] = (preg_match('#^[a-z]+://#i', $inv['payment_url']) ? '' : 'https://' . $inv['payment_url']);
    if (!in_array($inv['brand'], BRANDS, true)) $inv['brand'] = 'Syndicate Holdings Group';
    $inv['tax_rate'] = max(0, min(100, (float)$inv['tax_rate']));
    $items = [];
    foreach ((array)($_POST['desc'] ?? []) as $i => $desc) {
        $desc = trim((string)$desc); $qty = (float)($_POST['qty'][$i] ?? 0); $price = (float)str_replace([',', '$'], '', (string)($_POST['price'][$i] ?? 0));
        $cid = (int)($_POST['consult'][$i] ?? 0) ?: null;
        if ($desc === '' && !$price) continue;
        $items[] = ['description' => mb_substr($desc ?: 'Item', 0, 500), 'quantity' => $qty, 'unit_price' => $price, 'consultation_id' => $cid];
    }
    if (!(int)$inv['company_id'] && !(int)$inv['contact_id']) $error = 'Choose who the invoice is for.';
    elseif (!valid_date($inv['issue_date']) || !valid_date($inv['due_date'])) $error = 'Enter valid issue and due dates.';
    elseif (!$items) $error = 'Add at least one line item.';
    elseif ($inv['payment_url'] !== '' && !filter_var($inv['payment_url'], FILTER_VALIDATE_URL)) $error = 'The payment link doesn’t look like a web address.';
    $every = isset(REPEATS[post('repeat_every')]) ? post('repeat_every') : '';
    $next = post('repeat_next');
    if (!$error && $every && !valid_date($next)) { for ($n = 1; ($next = repeat_date($inv['issue_date'], $every, $n)) <= today(); $n++); }
    if (!$error && $every && $next <= today() && $next !== ($inv['repeat_next'] ?? '')) $error = 'Pick a date after today for the next recurring invoice.';
    if (!$error) {
        // A new or changed schedule starts counting from the chosen next date.
        $sched = $every === ''
            ? ['', null, 0, null]
            : ($every === ($inv['repeat_every'] ?? '') && $next === ($inv['repeat_next'] ?? '') ? [$every, $inv['repeat_start'], (int)$inv['repeat_count'], $next] : [$every, $next, 0, $next]);
        $inv['repeat_every'] = $every; $inv['repeat_next'] = $next; $inv['repeat_send'] = post('repeat_send') === '1' ? 1 : 0;
        $vals = [$inv['brand'], (int)$inv['company_id'] ?: null, (int)$inv['contact_id'] ?: null, $inv['issue_date'], $inv['due_date'], $inv['tax_rate'], $inv['notes'], $inv['payment_url'], $sched[0], $sched[1], $sched[2], $sched[3], $inv['repeat_send']];
        db()->beginTransaction();
        if ($id) q('UPDATE invoices SET brand=?, company_id=?, contact_id=?, issue_date=?, due_date=?, tax_rate=?, notes=?, payment_url=?, repeat_every=?, repeat_start=?, repeat_count=?, repeat_next=?, repeat_send=?, updated_at=? WHERE id=?', array_merge($vals, [now(), $id]));
        else { q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, tax_rate, notes, payment_url, repeat_every, repeat_start, repeat_count, repeat_next, repeat_send, share_token, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,?)", array_merge([next_invoice_number()], $vals, [bin2hex(random_bytes(16)), now(), now()])); $id = (int)db()->lastInsertId(); }
        q('UPDATE consultations SET invoice_id = NULL WHERE invoice_id = ?', [$id]);
        q('DELETE FROM invoice_items WHERE invoice_id = ?', [$id]);
        foreach ($items as $pos => $it) {
            q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, consultation_id, position) VALUES (?,?,?,?,?,?)', [$id, $it['description'], $it['quantity'], $it['unit_price'], $it['consultation_id'], $pos]);
            if ($it['consultation_id']) q('UPDATE consultations SET invoice_id = ? WHERE id = ? AND (invoice_id IS NULL OR invoice_id = ?)', [$id, $it['consultation_id'], $id]);
        }
        db()->commit();
        flash('Invoice saved.');
        redirect('invoice.php?id=' . $id);
    }
}
layout_top($id ? 'Edit invoice' : 'New invoice', 'invoices', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'invoice.php?id=' . $id : 'invoices.php' ?>">← Back</a><h1><?= $id ? 'Edit ' . e($inv['number'] ?? 'invoice') : 'New invoice' ?></h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<?php if ($id && $inv['status'] !== 'draft'): ?><p class="notice">This invoice is marked <?= e(strtolower(INVOICE_STATUSES[$inv['status']])) ?>. Changes update it in place.</p><?php endif; ?>
<form method="post" class="stack" id="invoiceForm">
  <?= csrf_field() ?>
  <div class="card form-grid">
    <label class="f"><span>From</span><select name="brand"><?php foreach (BRANDS as $b): ?><option<?= selected($b, $inv['brand']) ?>><?= e($b) ?></option><?php endforeach; ?></select></label>
    <span></span>
    <label class="f"><span>Bill to company</span><select name="company_id"><option value="">— None —</option><?php foreach (company_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $inv['company_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Attention / contact</span><select name="contact_id"><option value="">— None —</option><?php foreach (contact_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $inv['contact_id']) ?>><?= e(person_name($c)) ?><?= $c['company'] ? ' · ' . e($c['company']) : '' ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Issue date</span><input type="date" name="issue_date" value="<?= e($inv['issue_date']) ?>" required></label>
    <label class="f"><span>Due date</span><input type="date" name="due_date" value="<?= e($inv['due_date']) ?>" required></label>
  </div>
  <div class="card">
    <div class="card-h"><h2>Line items</h2><button class="btn ghost xs" type="button" id="addItem">+ Add line</button></div>
    <div class="items" id="items">
      <div class="item head"><span>Description</span><span>Qty / hours</span><span>Rate</span><span class="r">Amount</span><span></span></div>
      <?php foreach ($items as $it): ?>
        <div class="item">
          <input name="desc[]" value="<?= e($it['description']) ?>" placeholder="e.g. Social media management — October" aria-label="Description">
          <input name="qty[]" type="number" step="0.01" min="0" value="<?= e((float)$it['quantity']) ?>" aria-label="Quantity">
          <input name="price[]" type="number" step="0.01" min="0" value="<?= e($it['unit_price'] === '' ? '' : (float)$it['unit_price']) ?>" placeholder="0.00" aria-label="Rate">
          <span class="r num amt">$0.00</span>
          <input type="hidden" name="consult[]" value="<?= (int)($it['consultation_id'] ?? 0) ?>">
          <button class="x" type="button" aria-label="Remove line">×</button>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="totals">
      <div><span>Subtotal</span><b class="num" id="subOut">$0.00</b></div>
      <div><label for="taxIn">Tax %</label><input id="taxIn" name="tax_rate" type="number" step="0.01" min="0" max="100" value="<?= e((float)$inv['tax_rate']) ?>"><b class="num" id="taxOut">$0.00</b></div>
      <div class="grand"><span>Total</span><b class="num" id="totOut">$0.00</b></div>
    </div>
  </div>
  <div class="card"><label class="f"><span>Online payment link <small class="muted">(optional · Stripe, Square or PayPal payment link)</small></span><input name="payment_url" type="text" inputmode="url" autocomplete="off" value="<?= e($inv['payment_url'] ?? '') ?>" placeholder="https://buy.stripe.com/…"></label><p class="muted small" style="margin:0">Adds a &ldquo;Pay online&rdquo; button to the client&rsquo;s invoice page and email. Set a default in Settings.</p></div>
  <?php if (($inv['status'] ?? 'draft') !== 'void'): $rep = $inv['repeat_every'] ?? ''; ?>
  <div class="card form-grid" id="repeat" style="scroll-margin-top:90px">
    <label class="f"><span>Repeat <small class="muted">(retainers, monthly services)</small></span><select name="repeat_every" id="repeatIn"><option value="">Doesn&rsquo;t repeat</option><?php foreach (REPEATS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $rep) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="f" data-repeat-only><span>Next invoice on</span><input type="date" name="repeat_next" id="repeatNext" value="<?= e($inv['repeat_next'] ?? '') ?>" data-min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"></label>
    <label class="chk span2" data-repeat-only><input type="checkbox" name="repeat_send" value="1"<?= !empty($inv['repeat_send']) ? ' checked' : '' ?>><span>Email each new invoice to the client automatically <small class="muted">(otherwise it&rsquo;s saved as a draft with a task to review it)</small></span></label>
    <p class="muted small span2" data-repeat-only style="margin:0">Each new invoice copies these line items, tax, notes and payment link, with the same number of days to pay. Write <b>{month}</b> in a description to show the invoice&rsquo;s month, e.g. &ldquo;Social media management &mdash; {month}&rdquo;.</p>
  </div>
  <?php endif; ?>
  <div class="card"><label class="f"><span>Notes on the invoice <small class="muted">(optional)</small></span><textarea name="notes" rows="3" placeholder="e.g. Thanks for a great kickoff."><?= e($inv['notes']) ?></textarea></label></div>
  <div class="save-bar"><span class="muted small">Payment instructions and footer come from Settings.</span><div class="row"><a class="btn ghost sm" href="<?= $id ? 'invoice.php?id=' . $id : 'invoices.php' ?>">Cancel</a><button class="btn primary sm" type="submit">Save invoice</button></div></div>
</form>
<template id="itemTpl"><div class="item"><input name="desc[]" placeholder="Description" aria-label="Description"><input name="qty[]" type="number" step="0.01" min="0" value="1" aria-label="Quantity"><input name="price[]" type="number" step="0.01" min="0" placeholder="0.00" aria-label="Rate"><span class="r num amt">$0.00</span><input type="hidden" name="consult[]" value="0"><button class="x" type="button" aria-label="Remove line">×</button></div></template>
<?php layout_bottom(); ?>
