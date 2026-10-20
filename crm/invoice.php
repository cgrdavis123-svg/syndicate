<?php
require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/invoice-doc.php';
$user = require_user();
$id = (int)get('id');
$inv = load_invoice($id);
if (!$inv) { flash('That invoice no longer exists.'); redirect('invoices.php'); }
if ($inv['share_token'] === '') { $inv['share_token'] = bin2hex(random_bytes(16)); q('UPDATE invoices SET share_token = ? WHERE id = ?', [$inv['share_token'], $id]); }
$tot = invoice_total($id);
if (is_post()) {
    $a = post('action');
    if ($a === 'send' || $a === 'remind') {
        $to = post('to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { flash('Enter a valid email address to send to.'); redirect('invoice.php?id=' . $id); }
        if (email_invoice($inv, $to, $a === 'remind', post('message'), $user['name'])) {
            flash($a === 'remind' ? 'Reminder sent to ' . $to . '.' : 'Invoice sent to ' . $to . '.');
        } else {
            flash("The email couldn't be sent from the server. Copy the invoice link and send it yourself.");
        }
        redirect('invoice.php?id=' . $id);
    }
    if ($a === 'sent') { q("UPDATE invoices SET status = 'sent', sent_at = COALESCE(sent_at, ?), updated_at = ? WHERE id = ?", [now(), now(), $id]); flash('Marked as sent.'); }
    elseif ($a === 'paid') { q("UPDATE invoices SET status = 'paid', paid_at = ?, paid_method = ?, updated_at = ? WHERE id = ?", [valid_date(post('paid_date')) ? post('paid_date') . ' 12:00:00' : now(), mb_substr(post('method'), 0, 60), now(), $id]); flash('Marked as paid.'); }
    elseif ($a === 'unpaid') { q("UPDATE invoices SET status = 'sent', paid_at = NULL, paid_method = '', updated_at = ? WHERE id = ?", [now(), $id]); flash('Marked as unpaid.'); }
    elseif ($a === 'stop_repeat') { q("UPDATE invoices SET repeat_every = '', repeat_next = NULL, updated_at = ? WHERE id = ?", [now(), $id]); flash('This invoice no longer repeats. Invoices already created stay as they are.'); }
    elseif ($a === 'void') { q("UPDATE invoices SET status = 'void', repeat_every = '', repeat_next = NULL, updated_at = ? WHERE id = ?", [now(), $id]); q('UPDATE consultations SET invoice_id = NULL WHERE invoice_id = ?', [$id]); flash('Invoice voided. Its consultations are unbilled again.'); }
    elseif ($a === 'delete' && $inv['status'] === 'draft') { q('UPDATE consultations SET invoice_id = NULL WHERE invoice_id = ?', [$id]); q('DELETE FROM invoices WHERE id = ?', [$id]); flash('Draft deleted.'); redirect('invoices.php'); }
    elseif ($a === 'duplicate') {
        $num = next_invoice_number(); $terms = (int)setting('invoice_terms_days') ?: 14;
        q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, tax_rate, notes, payment_url, share_token, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?,?)", [$num, $inv['brand'], $inv['company_id'], $inv['contact_id'], today(), date('Y-m-d', strtotime("+$terms days")), $inv['tax_rate'], $inv['notes'], $inv['payment_url'], bin2hex(random_bytes(16)), now(), now()]);
        $new = (int)db()->lastInsertId();
        foreach (all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position', [$id]) as $it) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$new, $it['description'], $it['quantity'], $it['unit_price'], $it['position']]);
        flash("Duplicated as $num."); redirect('invoice.php?id=' . $new);
    }
    redirect('invoice.php?id=' . $id);
}
$items = all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position, id', [$id]);
$t = $tot;
$late = $inv['status'] === 'sent' && $inv['due_date'] < today();
$fromName = invoice_from($inv);
$parent = $inv['repeat_parent'] ? one('SELECT id, number, repeat_every FROM invoices WHERE id = ?', [$inv['repeat_parent']]) : null;
$series = $inv['repeat_every'] || $inv['repeat_count'] ? all('SELECT id, number, issue_date, status, due_date FROM invoices WHERE repeat_parent = ? ORDER BY issue_date DESC, id DESC LIMIT 6', [$id]) : [];
layout_top('Invoice ' . $inv['number'], 'invoices', $user);
?>
<div class="page-head no-print">
  <div><a class="muted small" href="invoices.php">← Invoices</a><h1 class="num"><?= e($inv['number']) ?></h1><p class="row"><?= pill('inv', $late ? 'overdue' : $inv['status'], $late ? 'Overdue' : INVOICE_STATUSES[$inv['status']]) ?><span class="muted"><?= money($t['total']) ?> · due <?= e(fmt_date($inv['due_date'])) ?></span></p></div>
  <div class="row">
    <button class="btn ghost sm" type="button" data-print>Print / Save PDF</button>
    <?php if ($inv['status'] !== 'void' && $inv['status'] !== 'paid'): ?><a class="btn ghost sm" href="#send"><?= $inv['status'] === 'draft' ? 'Send to client' : 'Send reminder' ?></a><?php endif; ?>
    <a class="btn ghost sm" href="invoice-edit.php?id=<?= $id ?>">Edit</a>
    <form method="post" class="inline"><?= csrf_field() ?>
      <?php if ($inv['status'] === 'draft'): ?><button class="btn primary sm" name="action" value="sent">Mark as sent</button><?php endif; ?>
      <?php if ($inv['status'] === 'paid'): ?><button class="btn ghost sm" name="action" value="unpaid">Mark unpaid</button><?php endif; ?>
    </form>
  </div>
</div>
<?php if ($inv['status'] === 'sent'): ?>
<form method="post" class="card pay-bar no-print"><?= csrf_field() ?><input type="hidden" name="action" value="paid">
  <b>Record payment</b><input type="date" name="paid_date" value="<?= e(today()) ?>" aria-label="Payment date"><input name="method" placeholder="Method, e.g. ACH, Zelle, check" aria-label="Payment method"><button class="btn primary sm" type="submit">Mark as paid</button>
</form>
<?php endif; ?>

<?php if ($inv['repeat_every'] || $series): ?>
<section class="card repeat-card no-print">
  <div class="card-h"><h2>↻ <?= $inv['repeat_every'] ? 'Repeats ' . e(REPEAT_ADJ[$inv['repeat_every']]) : 'Recurring (stopped)' ?></h2>
    <?php if ($inv['repeat_every']): ?><form method="post" class="inline"><?= csrf_field() ?><button class="link-danger" name="action" value="stop_repeat" data-confirm="Stop repeating this invoice?">Stop repeating</button></form><?php endif; ?></div>
  <?php if ($inv['repeat_every']): ?><p class="muted small" style="margin:0">Next invoice on <b><?= e(fmt_date($inv['repeat_next'])) ?></b> · <?= $inv['repeat_send'] ? 'emailed to the client automatically' : 'created as a draft for you to review' ?>. <a href="invoice-edit.php?id=<?= $id ?>#repeat">Change</a></p><?php endif; ?>
  <?php if ($series): ?><ul class="series"><?php foreach ($series as $s): $sl = $s['status'] === 'sent' && $s['due_date'] < today(); ?><li><a class="num" href="invoice.php?id=<?= (int)$s['id'] ?>"><?= e($s['number']) ?></a><span class="muted small"><?= e(fmt_date($s['issue_date'])) ?></span><?= pill('inv', $sl ? 'overdue' : $s['status'], $sl ? 'Overdue' : INVOICE_STATUSES[$s['status']]) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php elseif ($parent): ?><p class="notice no-print">↻ Created automatically from the recurring invoice <a href="invoice.php?id=<?= (int)$parent['id'] ?>"><?= e($parent['number']) ?></a>.</p><?php endif; ?>

<section class="card share no-print">
  <div class="card-h"><h2>Client link</h2><span class="muted small"><?= $inv['viewed_at'] ? 'Viewed by client ' . e(ago($inv['viewed_at'])) : 'Not viewed yet' ?><?= $inv['sent_at'] ? ' · sent ' . e(ago($inv['sent_at'])) : '' ?><?= $inv['reminded_at'] ? ' · reminded ' . e(ago($inv['reminded_at'])) : '' ?></span></div>
  <div class="copy-row"><input readonly value="<?= e(invoice_link($inv)) ?>" id="shareLink" aria-label="Client invoice link"><button class="btn ghost sm" type="button" data-copy="#shareLink">Copy link</button><a class="btn ghost sm" href="<?= e(url('i.php?t=' . $inv['share_token'])) ?>" target="_blank" rel="noopener">Open</a></div>
  <p class="muted small">Anyone with this link can view and print this invoice<?= $inv['payment_url'] ? ' and pay it online' : '' ?>. It doesn&rsquo;t give access to anything else.</p>
</section>

<?php render_invoice_doc($inv, $items, $t); ?>

<?php if ($inv['status'] !== 'void' && $inv['status'] !== 'paid'): $isRemind = $inv['status'] === 'sent'; ?>
<form method="post" class="card no-print" id="send" style="scroll-margin-top:90px">
  <?= csrf_field() ?><input type="hidden" name="action" value="<?= $isRemind ? 'remind' : 'send' ?>">
  <div class="card-h"><h2><?= $isRemind ? 'Send a payment reminder' : 'Send to client' ?></h2><span class="muted small">Sends from <?= e(setting('mail_from')) ?></span></div>
  <label class="f"><span>To</span><input name="to" type="email" required value="<?= e($inv['email']) ?>" placeholder="client@example.com"></label>
  <label class="f"><span>Message</span><textarea name="message" rows="4"><?= e(invoice_message($inv, $t['total'], $isRemind)) ?></textarea></label>
  <p class="muted small">The email includes the invoice link, the amount and due date<?= $inv['payment_url'] ? ', and your payment link' : '' ?>.<?= !$isRemind ? ' Sending marks the invoice as sent.' : '' ?></p>
  <div class="row-end"><button class="btn primary sm" type="submit"><?= $isRemind ? 'Send reminder' : 'Send invoice' ?> <?= ARROW ?></button></div>
</form>
<?php endif; ?>

<form method="post" class="row no-print danger-row"><?= csrf_field() ?>
  <button class="btn ghost xs" name="action" value="duplicate">Duplicate</button>
  <?php if ($inv['status'] !== 'void' && $inv['status'] !== 'draft'): ?><button class="link-danger" name="action" value="void" data-confirm="Void this invoice? Any consultations on it become unbilled again.">Void invoice</button><?php endif; ?>
  <?php if ($inv['status'] === 'draft'): ?><button class="link-danger" name="action" value="delete" data-confirm="Delete this draft invoice?">Delete draft</button><?php endif; ?>
</form>
<?php layout_bottom(); ?>
