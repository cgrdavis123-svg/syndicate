<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
$user = require_user();
$id = (int)get('id');
$o = one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$o) { flash('That order no longer exists.'); redirect('store.php'); }
$back = 'store-order.php?id=' . $id;
if (is_post()) {
    $a = post('action');
    if ($a === 'check') {
        try { $n = shop_sync_session($o); flash($n['status'] === $o['status'] ? 'Checked with Stripe: still ' . strtolower(ORDER_STATUSES[$n['status']]) . '.' : 'Updated from Stripe: ' . ORDER_STATUSES[$n['status']] . '.'); }
        catch (Throwable $e) { flash('Couldn’t check with Stripe: ' . $e->getMessage()); }
        redirect($back);
    }
    if ($a === 'resend' && $o['status'] === 'paid') { shop_mail_paid($o); flash('Order confirmation and portal link sent to ' . $o['email'] . '.'); redirect($back); }
    if ($a === 'refunded' && $o['status'] === 'paid') {
        q("UPDATE orders SET status = 'refunded', updated_at = ? WHERE id = ?", [now(), $id]);
        if ($o['deal_id']) log_activity('deal', (int)$o['deal_id'], 'note', 'Order ' . $o['ref'] . ' marked as refunded.');
        flash('Marked as refunded. Refund the payment itself in Stripe.'); redirect($back);
    }
    if ($a === 'delete' && $o['status'] !== 'paid') { q('DELETE FROM orders WHERE id = ?', [$id]); flash('Order deleted.'); redirect('store.php?status=unfinished'); }
}
if ($o['status'] === 'paid' && !$o['seen_at']) q('UPDATE orders SET seen_at = ? WHERE id = ?', [now(), $id]);
$items = order_items($id);
$brief = json_decode($o['brief'], true) ?: [];
$contact = $o['contact_id'] ? one('SELECT * FROM contacts WHERE id = ?', [$o['contact_id']]) : null;
$projects = all('SELECT DISTINCT p.* FROM projects p JOIN order_items i ON i.project_id = p.id WHERE i.order_id = ? ORDER BY p.id', [$id]);
$site = $o['site_id'] ? one('SELECT * FROM sites WHERE id = ?', [$o['site_id']]) : null;
$inv = $o['invoice_id'] ? one('SELECT * FROM invoices WHERE id = ?', [$o['invoice_id']]) : null;
$renewals = $o['contact_id'] && $o['stripe_subscription'] ? all("SELECT * FROM invoices WHERE contact_id = ? AND stripe_invoice != '' AND notes LIKE ? ORDER BY id DESC LIMIT 24", [$o['contact_id'], 'Monthly plan · Order ' . $o['ref'] . '%']) : [];
$live = (bool)$o['live'];
$stripeLink = $o['stripe_subscription'] ? stripe_dashboard('subscriptions', $o['stripe_subscription'], $live) : ($o['stripe_payment'] ? stripe_dashboard('payments', $o['stripe_payment'], $live) : '');
layout_top('Order ' . $o['ref'], 'store', $user);
?>
<div class="page-head">
  <div><a class="muted small" href="store.php">← Store</a><h1><?= e($o['business'] ?: $o['name']) ?></h1>
    <p class="row muted"><?= pill('ord', $o['status'], ORDER_STATUSES[$o['status']] ?? $o['status']) ?> <span class="num"><?= e($o['ref']) ?></span> · <?= e(fmt_date($o['paid_at'] ?: $o['created_at'], 'M j, Y g:i a')) ?><?= $live ? '' : ' · <span class="warn">Stripe test mode</span>' ?></p></div>
  <div class="row">
    <?php if ($stripeLink): ?><a class="btn ghost sm" href="<?= e($stripeLink) ?>" target="_blank" rel="noopener">Open in Stripe ↗</a><?php endif; ?>
    <?php if (in_array($o['status'], ['pending', 'processing', 'expired'], true) && $o['stripe_session']): ?><form method="post" class="inline"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="check">Check payment with Stripe</button></form><?php endif; ?>
    <?php if ($o['status'] === 'paid'): ?><form method="post" class="inline"><?= csrf_field() ?><button class="btn primary sm" name="action" value="resend">Resend confirmation</button></form><?php endif; ?>
  </div>
</div>
<div class="grid-detail">
  <div class="stack">
    <section class="card flush">
      <div class="card-h" style="padding:18px 20px 0"><h2>Items</h2></div>
      <div class="table-wrap"><table class="tbl">
        <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr></thead>
        <tbody><?php foreach ($items as $i): ?><tr><td><span class="cell-main"><b><?= e($i['name']) ?></b><small><?= e(SHOP_CATEGORIES[$i['category']] ?? $i['category']) ?><?= $i['project_id'] ? ' · <a href="project.php?id=' . (int)$i['project_id'] . '">project</a>' : '' ?></small></span></td>
          <td class="r num"><?= (int)$i['quantity'] ?></td><td class="r num"><?= e(price_label((float)$i['price'], $i['interval'])) ?><?php if ($i['list_price'] !== null && (float)$i['list_price'] > (float)$i['price']): ?><br><small class="muted"><s><?= e(price_label((float)$i['list_price'], $i['interval'])) ?></s></small><?php endif; ?><?php if ((int)$i['trial_months']): ?><br><small class="muted"><?= (int)$i['trial_months'] ?> months free</small><?php endif; ?></td><td class="r num"><b><?= e(price_label((float)$i['price'] * $i['quantity'], $i['interval'])) ?></b></td></tr><?php endforeach; ?></tbody>
        <tfoot>
          <tr><td colspan="3" class="r muted">One-time</td><td class="r num"><?= money($o['one_time']) ?></td></tr>
          <?php if ($o['monthly'] > 0): ?><tr><td colspan="3" class="r muted">Monthly<?= $o['trial_end'] ? ' from ' . e(date('M j, Y', (int)$o['trial_end'])) . ' (free until then)' : '' ?></td><td class="r num"><?= money($o['monthly']) ?>/mo</td></tr><?php endif; ?>
          <?php if ($o['offer'] !== ''): $ofr = one('SELECT id, name FROM offers WHERE slug = ?', [$o['offer']]); ?><tr><td colspan="4" class="r muted small">Offer: <?= $ofr ? '<a href="store-offers.php?id=' . (int)$ofr['id'] . '">' . e($ofr['name']) . '</a>' : e($o['offer']) ?></td></tr><?php endif; ?>
          <?php if ($o['discount'] > 0): ?><tr><td colspan="3" class="r muted">Discount</td><td class="r num">−<?= money($o['discount']) ?></td></tr><?php endif; ?>
          <?php if ($o['tax'] > 0): ?><tr><td colspan="3" class="r muted">Sales tax</td><td class="r num"><?= money($o['tax']) ?></td></tr><?php endif; ?>
          <?php if (in_array($o['status'], ['paid', 'refunded'], true)): ?><tr><td colspan="3" class="r"><b>Paid today</b></td><td class="r num"><b><?= money($o['paid_total']) ?></b></td></tr><?php endif; ?>
        </tfoot>
      </table></div>
    </section>
    <section class="card"><div class="card-h"><h2>Project brief</h2><span class="muted small">What they told us at checkout</span></div>
      <?php if (!$brief): ?><p class="empty">They didn’t add any project details.</p><?php else: ?>
      <dl class="facts brief"><?php foreach (BRIEF_LABELS as $key => $l): if (!isset($brief[$key])) continue; ?><div><dt><?= e($l) ?></dt><dd><?= nl2br(e(is_array($brief[$key]) ? implode(', ', array_map(function ($x) { return AD_PLATFORMS[$x] ?? $x; }, $brief[$key])) : $brief[$key])) ?></dd></div><?php endforeach; ?></dl>
      <?php endif; ?>
    </section>
    <?php if ($renewals): ?>
    <section class="card"><div class="card-h"><h2>Monthly payments</h2></div>
      <ul class="list"><?php foreach ($renewals as $r): ?><li><a href="invoice.php?id=<?= (int)$r['id'] ?>"><span class="grow"><b class="num"><?= e($r['number']) ?></b><small><?= e(fmt_date($r['paid_at'])) ?></small></span><span class="num"><?= money(invoice_total((int)$r['id'])['total']) ?></span></a></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
  </div>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Customer</h2></div>
      <dl class="facts">
        <div><dt>Name</dt><dd><?= $contact ? '<a href="contact.php?id=' . (int)$contact['id'] . '">' . e($o['name']) . '</a>' : e($o['name']) ?></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></dd></div>
        <?php if ($o['phone']): ?><div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $o['phone'])) ?>"><?= e($o['phone']) ?></a></dd></div><?php endif; ?>
        <div><dt>Business</dt><dd><?= $o['company_id'] ? '<a href="company.php?id=' . (int)$o['company_id'] . '">' . e($o['business']) . '</a>' : e($o['business']) ?></dd></div>
        <?php if ($o['website']): ?><div><dt>Website</dt><dd><?= e($o['website']) ?></dd></div><?php endif; ?>
        <?php if (($cs = json_decode((string)($o['consent'] ?? ''), true)) && !empty($cs['at'])): ?><div><dt>Agreed at checkout</dt><dd><?= e(fmt_date($cs['at'], 'M j, Y g:i a')) ?><br><span class="muted small"><?= e($cs['text'] ?? '') ?></span></dd></div><?php endif; ?>
        <?php if (!empty($o['cancel_at'])): ?><div><dt>Cancelled</dt><dd>Ends <?= e(date('M j, Y', (int)$o['cancel_at'])) ?></dd></div><?php endif; ?>
        <?php if ($o['sub_status']): ?><div><dt>Monthly plan</dt><dd class="<?= in_array($o['sub_status'], ['past_due', 'unpaid'], true) ? 'warn' : '' ?>"><?= e(SUB_STATUSES[$o['sub_status']] ?? $o['sub_status']) ?></dd></div><?php endif; ?>
        <?php if ($o['source']): ?><div><dt>Came from</dt><dd><?= e($o['source']) ?></dd></div><?php endif; ?>
      </dl>
    </section>
    <?php if ($o['status'] === 'paid' || $o['deal_id']): ?>
    <section class="card"><div class="card-h"><h2>Set up in the CRM</h2></div>
      <ul class="list">
        <?php if ($o['deal_id']): ?><li><a href="deal.php?id=<?= (int)$o['deal_id'] ?>"><span class="grow"><b>Deal</b><small><?= $o['status'] === 'paid' ? 'Marked won' : 'Follow-up deal' ?></small></span>→</a></li><?php endif; ?>
        <?php if ($inv): ?><li><a href="invoice.php?id=<?= (int)$inv['id'] ?>"><span class="grow"><b>Invoice <?= e($inv['number']) ?></b><small>Paid by Stripe</small></span>→</a></li><?php endif; ?>
        <?php foreach ($projects as $p): ?><li><a href="project.php?id=<?= (int)$p['id'] ?>"><span class="grow"><b><?= e($p['name']) ?></b><small>In the client portal<?= $p['due_date'] ? ' · due ' . e(fmt_date($p['due_date'], 'M j')) : '' ?></small></span><?= project_status_pill($p['status']) ?></a></li><?php endforeach; ?>
        <?php if ($site): ?><li><a href="site.php?id=<?= (int)$site['id'] ?>"><span class="grow"><b>Site Stats</b><small><?= e($site['domains']) ?></small></span>→</a></li><?php endif; ?>
        <?php if ($contact && $o['status'] === 'paid'): ?><li><a href="contact.php?id=<?= (int)$contact['id'] ?>"><span class="grow"><b>Client portal</b><small><?= $contact['portal_seen_at'] ? 'Last signed in ' . e(ago($contact['portal_seen_at'])) : 'Invite sent, not signed in yet' ?></small></span>→</a></li><?php endif; ?>
      </ul>
    </section>
    <?php endif; ?>
    <section class="card"><div class="card-h"><h2>Stripe</h2></div>
      <dl class="facts small">
        <?php if ($o['stripe_session']): ?><div><dt>Checkout</dt><dd class="mono"><?= e($o['stripe_session']) ?></dd></div><?php endif; ?>
        <?php if ($o['stripe_customer']): ?><div><dt>Customer</dt><dd><a href="<?= e(stripe_dashboard('customers', $o['stripe_customer'], $live)) ?>" target="_blank" rel="noopener" class="mono"><?= e($o['stripe_customer']) ?></a></dd></div><?php endif; ?>
        <?php if ($o['stripe_subscription']): ?><div><dt>Subscription</dt><dd><a href="<?= e(stripe_dashboard('subscriptions', $o['stripe_subscription'], $live)) ?>" target="_blank" rel="noopener" class="mono"><?= e($o['stripe_subscription']) ?></a></dd></div><?php endif; ?>
        <?php if ($o['stripe_payment']): ?><div><dt>Payment</dt><dd><a href="<?= e(stripe_dashboard('payments', $o['stripe_payment'], $live)) ?>" target="_blank" rel="noopener" class="mono"><?= e($o['stripe_payment']) ?></a></dd></div><?php endif; ?>
      </dl>
      <?php if ($o['status'] === 'paid'): ?><form method="post" data-confirm="Mark this order as refunded? Refund the payment itself in Stripe first."><?= csrf_field() ?><button class="link-danger" name="action" value="refunded">Mark as refunded</button></form>
      <?php elseif ($o['status'] !== 'refunded'): ?><form method="post" data-confirm="Delete this unfinished order?"><?= csrf_field() ?><button class="link-danger" name="action" value="delete">Delete order</button></form><?php endif; ?>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
