<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/quotes.php';
$user = require_user();
$id = (int)get('id');
$q = load_quote($id);
if (!$q) { flash('That quote no longer exists.'); redirect('quotes.php'); }
$back = 'quote.php?id=' . $id;
if (is_post()) {
    $a = post('action');
    if ($a === 'send' || $a === 'remind') {
        $to = post('to');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) flash('Enter a valid email address.');
        elseif (quote_expired($q)) flash('This quote has expired. Change the “Valid until” date first.');
        else flash(email_quote($q, $to, post('message'), $user['name'], $a === 'remind') ? ($a === 'remind' ? 'Reminder sent to ' : 'Quote sent to ') . $to . '.' : 'The email couldn’t be sent. Check the mail settings on your host.');
        redirect($back);
    }
    if ($a === 'won' && !in_array($q['status'], ['won', 'declined'], true)) { quote_fulfil($q, null, 'marked won by ' . $user['name']); flash('Marked as won. The deal, invoice, project and client portal are set up.'); redirect($back); }
    if ($a === 'check' && $q['stripe_session']) { try { $n = quote_sync_session($q); flash($n['status'] === 'won' ? 'Payment found. The quote is won.' : 'No payment yet.'); } catch (Throwable $e) { flash('Couldn’t check with Stripe: ' . $e->getMessage()); } redirect($back); }
    if ($a === 'declined' && quote_open($q)) { quote_decline($q, 'Marked declined by ' . $user['name'] . (post('reason') !== '' ? ': ' . post('reason') : '')); flash('Marked as declined.'); redirect($back); }
    if ($a === 'reopen' && in_array($q['status'], ['declined', 'expired'], true)) {
        q("UPDATE quotes SET status = 'sent', declined_at = NULL, valid_until = CASE WHEN valid_until < ? THEN ? ELSE valid_until END, updated_at = ? WHERE id = ?", [today(), date('Y-m-d', strtotime('+' . max(1, (int)setting('quote_valid_days')) . ' days')), now(), $id]);
        flash('Reopened. The client can accept it again.'); redirect($back);
    }
    if ($a === 'duplicate') {
        q('INSERT INTO quotes (number, token, brand, title, intro, contact_id, company_id, deal_id, valid_until, deposit_pct, terms, steps, timeline_days, notes, created_at, updated_at) SELECT ?, ?, brand, title, intro, contact_id, company_id, CASE WHEN status = \'won\' THEN NULL ELSE deal_id END, ?, deposit_pct, terms, steps, timeline_days, notes, ?, ? FROM quotes WHERE id = ?',
            [next_quote_number(), bin2hex(random_bytes(16)), date('Y-m-d', strtotime('+' . max(1, (int)setting('quote_valid_days')) . ' days')), now(), now(), $id]);
        $new = (int)db()->lastInsertId();
        q('INSERT INTO quote_items (quote_id, product_id, description, details, quantity, unit_price, interval, optional, selected, position) SELECT ?, product_id, description, details, quantity, unit_price, interval, optional, CASE WHEN optional = 1 THEN 0 ELSE 1 END, position FROM quote_items WHERE quote_id = ?', [$new, $id]);
        flash('Copied to a new draft.'); redirect('quote-edit.php?id=' . $new);
    }
    if ($a === 'delete' && in_array($q['status'], ['draft', 'declined', 'expired'], true)) { q('DELETE FROM quotes WHERE id = ?', [$id]); flash('Quote deleted.'); redirect('quotes.php'); }
}
$items = quote_items($id);
$t = quote_totals($q, $items);
$offered = $t; // what the full proposal is worth with every optional item ticked
$opt = array_sum(array_map(function ($i) { return $i['optional'] ? (float)$i['quantity'] * (float)$i['unit_price'] : 0; }, $items));
$expired = quote_expired($q);
$status = $expired ? 'expired' : $q['status'];
layout_top($q['number'] . ' · ' . ($q['title'] ?: 'Quote'), 'quotes', $user);
?>
<div class="page-head">
  <div><a class="muted small" href="quotes.php">← Quotes</a><h1><?= e($q['title'] ?: $q['number']) ?></h1>
    <p class="row muted"><?= quote_pill($status) ?> <span class="num"><?= e($q['number']) ?></span> · <?= e(quote_client($q)) ?> · <?= brand_tag($q['brand']) ?></p></div>
  <div class="row">
    <a class="btn ghost sm" href="<?= e(url('q.php?t=' . $q['token'])) ?>" target="_blank" rel="noopener">Preview as client ↗</a>
    <form method="post" class="inline"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="duplicate">Duplicate</button></form>
    <?php if (!in_array($q['status'], ['won', 'accepted'], true)): ?><a class="btn primary sm" href="quote-edit.php?id=<?= $id ?>">Edit</a><?php endif; ?>
  </div>
</div>
<?php if ($q['status'] === 'accepted'): ?><div class="notice"><b><?= e($q['accepted_name']) ?> accepted this <?= e(ago($q['accepted_at'])) ?></b> but hasn’t finished paying <?= money($t['due_now']) ?>. They can pay from the same link. <?php if ($q['stripe_session']): ?><form method="post" class="inline"><?= csrf_field() ?><button class="btn ghost xs" name="action" value="check">Check payment with Stripe</button></form><?php endif; ?></div><?php endif; ?>
<?php if ($q['status'] === 'won'): ?><div class="notice" style="border-color:rgba(63,240,160,.4)"><b>Won <?= e(ago($q['paid_at'] ?: $q['updated_at'])) ?>.</b> <?= $q['accepted_name'] ? 'Accepted by ' . e($q['accepted_name']) . ' on ' . e(fmt_date($q['accepted_at'], 'M j, Y g:i a')) . '. ' : '' ?><?= $q['paid_total'] > 0 ? 'Paid ' . money($q['paid_total']) . ' through Stripe.' : '' ?></div><?php endif; ?>
<?php if ($q['status'] === 'declined'): ?><div class="notice bad">Declined <?= e(ago($q['declined_at'])) ?><?= $q['decline_reason'] ? ': “' . e($q['decline_reason']) . '”' : '.' ?></div><?php endif; ?>

<div class="grid-detail">
  <div class="stack">
    <section class="card share">
      <div class="card-h"><h2>Client link</h2><span class="muted small"><?= $q['viewed_at'] ? 'Opened ' . e(ago($q['viewed_at'])) : 'Not opened yet' ?><?= $q['sent_at'] ? ' · sent ' . e(ago($q['sent_at'])) : '' ?><?= $q['reminded_at'] ? ' · reminded ' . e(ago($q['reminded_at'])) : '' ?></span></div>
      <div class="copy-row"><input readonly value="<?= e(quote_link($q)) ?>" id="shareLink" aria-label="Client quote link"><button class="btn ghost sm" type="button" data-copy="#shareLink">Copy link</button></div>
      <p class="muted small">Anyone with this link can view and accept this proposal. It doesn’t give access to anything else.</p>
    </section>
    <section class="card flush">
      <div class="card-h" style="padding:18px 20px 0"><h2>Items</h2></div>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Amount</th></tr></thead>
        <tbody><?php foreach ($items as $it): $off = $it['optional'] && !$it['selected']; ?><tr<?= $off ? ' style="opacity:.55"' : '' ?>><td><span class="cell-main"><b><?= e($it['description']) ?><?= $it['optional'] ? ' <span class="tag' . ($it['selected'] ? ' lime">Optional · chosen' : '">Optional') . '</span>' : '' ?></b><?php if ($it['details']): ?><small><?= e($it['details']) ?></small><?php endif; ?></span></td>
          <td class="r num"><?= e(rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.')) ?></td><td class="r num"><?= money($it['unit_price']) ?><?= $it['interval'] === 'month' ? '/mo' : '' ?></td><td class="r num"><b><?= money((float)$it['quantity'] * (float)$it['unit_price']) ?><?= $it['interval'] === 'month' ? '/mo' : '' ?></b></td></tr><?php endforeach; ?></tbody>
        <tfoot>
          <tr><td colspan="3" class="r muted">One-time<?= $opt > 0 && $q['status'] !== 'won' ? ' (without optional extras)' : '' ?></td><td class="r num"><?= money($t['one_time']) ?></td></tr>
          <?php if ($t['monthly'] > 0): ?><tr><td colspan="3" class="r muted">Monthly</td><td class="r num"><?= money($t['monthly']) ?>/mo</td></tr><?php endif; ?>
          <?php if ($opt > 0 && !in_array($q['status'], ['won', 'accepted'], true)): ?><tr><td colspan="3" class="r muted">Optional extras offered</td><td class="r num">+<?= money($opt) ?></td></tr><?php endif; ?>
          <tr><td colspan="3" class="r"><b>Due when accepted</b> <small class="muted">· <?= e(QUOTE_DEPOSITS[$t['pct']] ?? '') ?></small></td><td class="r num"><b><?= money($t['due_now']) ?></b></td></tr>
        </tfoot>
      </table></div>
    </section>
    <?php if ($q['intro']): ?><section class="card"><div class="card-h"><h2>Message to the client</h2></div><div class="note-box"><?= nl2br(e($q['intro'])) ?></div></section><?php endif; ?>
    <?php if (in_array($status, ['draft', 'sent', 'viewed'], true)): $remind = $q['status'] !== 'draft'; ?>
    <form method="post" class="card" id="send">
      <?= csrf_field() ?><input type="hidden" name="action" value="<?= $remind ? 'remind' : 'send' ?>">
      <div class="card-h"><h2><?= $remind ? 'Send a reminder' : 'Send to client' ?></h2><span class="muted small">Sends from <?= e(setting('mail_from')) ?> as <?= e(quote_from($q)) ?></span></div>
      <label class="f"><span>To</span><input name="to" type="email" required value="<?= e($q['email']) ?>"></label>
      <label class="f"><span>Message</span><textarea name="message" rows="5"><?= e($remind ? "Hi " . ($q['first_name'] ?: 'there') . ",\n\nJust checking in on the proposal I sent for " . ($q['title'] ?: 'your project') . ". Happy to answer any questions or adjust anything." : quote_message($q, $t)) ?></textarea></label>
      <p class="muted small">The email includes the link, the total<?= $q['valid_until'] ? ' and the date it’s valid until' : '' ?>.<?= $remind ? '' : ' Sending marks the quote as sent.' ?> One automatic reminder goes out three days before it expires.</p>
      <div class="row-end"><button class="btn primary sm" type="submit"><?= $remind ? 'Send reminder' : 'Send quote' ?> <?= ARROW ?></button></div>
    </form>
    <?php endif; ?>
  </div>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Details</h2></div>
      <dl class="facts">
        <div><dt>Client</dt><dd><?= $q['contact_id'] ? '<a href="contact.php?id=' . (int)$q['contact_id'] . '">' . e(person_name($q)) . '</a>' : '—' ?><?= $q['company'] ? ' · <a href="company.php?id=' . (int)$q['company_id'] . '">' . e($q['company']) . '</a>' : '' ?></dd></div>
        <div><dt>Payment</dt><dd><?= e(QUOTE_DEPOSITS[$t['pct']] ?? '') ?></dd></div>
        <div><dt>Valid until</dt><dd class="<?= $expired ? 'warn' : '' ?>"><?= $q['valid_until'] ? e(fmt_date($q['valid_until'])) : 'No expiry' ?></dd></div>
        <?php if ($q['timeline_days']): ?><div><dt>Timeline</dt><dd><?= (int)$q['timeline_days'] ?> days</dd></div><?php endif; ?>
        <div><dt>Created</dt><dd><?= e(fmt_date($q['created_at'])) ?></dd></div>
      </dl>
      <?php if ($q['notes']): ?><div class="note-box"><?= nl2br(e($q['notes'])) ?></div><?php endif; ?>
    </section>
    <?php if ($q['status'] === 'won' || $q['deal_id']): ?>
    <section class="card"><div class="card-h"><h2>In the CRM</h2></div>
      <ul class="list">
        <?php if ($q['deal_id']): ?><li><a href="deal.php?id=<?= (int)$q['deal_id'] ?>"><span class="grow"><b>Deal</b><small><?= $q['status'] === 'won' ? 'Marked won' : 'Linked deal' ?></small></span>→</a></li><?php endif; ?>
        <?php foreach ([['invoice_id', 'Paid invoice'], ['balance_invoice_id', 'Invoice to send on delivery']] as [$k, $l]): if (!$q[$k]) continue; $inv = one('SELECT number, status FROM invoices WHERE id = ?', [$q[$k]]); if (!$inv) continue; ?><li><a href="invoice.php?id=<?= (int)$q[$k] ?>"><span class="grow"><b><?= e($l) ?></b><small><?= e($inv['number']) ?> · <?= e(INVOICE_STATUSES[$inv['status']]) ?></small></span>→</a></li><?php endforeach; ?>
        <?php if ($q['project_id']): ?><li><a href="project.php?id=<?= (int)$q['project_id'] ?>"><span class="grow"><b>Project</b><small>In the client portal</small></span>→</a></li><?php endif; ?>
        <?php if ($q['stripe_subscription']): ?><li><a href="<?= e(stripe_dashboard('subscriptions', $q['stripe_subscription'], (bool)$q['live'])) ?>" target="_blank" rel="noopener"><span class="grow"><b>Monthly plan</b><small><?= e(SUB_STATUSES[$q['sub_status']] ?? 'Stripe subscription') ?></small></span>↗</a></li><?php elseif ($q['stripe_payment']): ?><li><a href="<?= e(stripe_dashboard('payments', $q['stripe_payment'], (bool)$q['live'])) ?>" target="_blank" rel="noopener"><span class="grow"><b>Payment in Stripe</b><small><?= money($q['paid_total']) ?></small></span>↗</a></li><?php endif; ?>
      </ul>
    </section>
    <?php endif; ?>
    <section class="card"><div class="card-h"><h2>Actions</h2></div>
      <div class="stack-sm">
        <?php if (!in_array($q['status'], ['won', 'declined'], true)): ?><form method="post" data-confirm="Mark this quote as won without online payment? The deal, invoices, project and client portal will be set up."><?= csrf_field() ?><button class="btn ghost sm" name="action" value="won">Mark as won (accepted offline)</button></form><?php endif; ?>
        <?php if (quote_open($q)): ?><form method="post" class="row" data-confirm="Mark this quote as declined?"><?= csrf_field() ?><input name="reason" placeholder="Reason (optional)" aria-label="Reason"><button class="btn ghost sm" name="action" value="declined">Mark declined</button></form><?php endif; ?>
        <?php if (in_array($status, ['declined', 'expired'], true)): ?><form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="reopen">Reopen for the client</button></form><?php endif; ?>
        <?php if (in_array($status, ['draft', 'declined', 'expired'], true)): ?><form method="post" data-confirm="Delete this quote?"><?= csrf_field() ?><button class="link-danger" name="action" value="delete">Delete quote</button></form><?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
