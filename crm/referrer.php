<?php
// One referral partner: their terms, who they referred, earnings, and payouts.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/referrals.php';
$user = require_user();
$r = ref_get((int)get('id'));
if (!$r) { flash('That referral account no longer exists.'); redirect('referrals.php'); }
$id = (int)$r['id'];
$back = 'referrer.php?id=' . $id;
if (is_post()) {
    $a = post('action');
    if ($a === 'save') {
        $email = strtolower(trim(post('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || val('SELECT 1 FROM referrers WHERE email = ? AND id != ?', [$email, $id])) { flash('Enter an email that isn’t used by another referral account.'); redirect($back); }
        $rate = min(50, max(0, round((float)post('rate'), 2)));
        q('UPDATE referrers SET name = ?, email = ?, business = ?, kind = ?, rate = ?, months = ?, payout = ?, payout_details = ?, w9 = ?, status = ?, notes = ?, updated_at = ? WHERE id = ?', [
            mb_substr(post('name'), 0, 120) ?: $r['name'], $email, mb_substr(post('business'), 0, 150), isset(REF_KINDS[post('kind')]) ? post('kind') : 'referrer', $rate,
            min(60, max(1, (int)post('months'))), isset(REF_PAYOUTS[post('payout')]) ? post('payout') : 'credit', mb_substr(post('payout_details'), 0, 300), post('w9') === '1' ? 1 : 0,
            isset(REF_STATUSES[post('status')]) ? post('status') : 'active', mb_substr(post('notes'), 0, 4000), now(), $id]);
        if ((float)$r['rate'] !== $rate) flash('Saved. The new rate applies to payments from now on; earnings already recorded keep the old rate.');
        else flash('Saved.');
    } elseif ($a === 'link') {
        $c = one('SELECT * FROM contacts WHERE id = ?', [(int)post('contact_id')]);
        if (!$c) flash('Pick a contact.');
        elseif (ref_same_party($r, $c)) flash('That’s the referrer themselves (or their own business), so it can’t count.');
        elseif ($ex = one('SELECT f.*, r.name rname FROM referrals f JOIN referrers r ON r.id = f.referrer_id WHERE f.contact_id = ?', [$c['id']])) flash(person_name($c) . ' is already tied to ' . $ex['rname'] . '.');
        else {
            $f = ref_attach($r['code'], $c, 'manual', 'added by ' . $user['name'], true);
            if (!$f) flash(person_name($c) . ' had already paid us before, so they aren’t a new referral. Their timeline has a note.');
            else { $n = ref_sweep(); flash(person_name($c) . ' is now tied to ' . $r['name'] . '.' . ($n ? " $n earning(s) from payments since then were recorded." : '')); }
        }
    } elseif ($a === 'void') {
        $f = one('SELECT * FROM referrals WHERE id = ? AND referrer_id = ?', [(int)post('referral'), $id]);
        if ($f) {
            q("UPDATE referrals SET status = 'void', void_reason = ?, updated_at = ? WHERE id = ?", [mb_substr(post('reason'), 0, 300), now(), $f['id']]);
            q("UPDATE referral_earnings SET status = 'void', note = ?, updated_at = ? WHERE referral_id = ? AND status IN ('held','ready')", ['Referral not counted.', now(), $f['id']]);
            flash('That referral no longer counts. Anything not yet paid for it was cancelled.');
        }
    } elseif ($a === 'payout') {
        $res = ref_payout($r, $r['payout'], post('reference'), post('note'), $user['name']);
        flash($res['message']);
    } elseif ($a === 'invite') {
        flash(ref_send_signin($r) ? 'Emailed them a sign-in link.' : 'The email couldn’t be sent.');
    }
    redirect($back);
}
$t = ref_totals($id);
$refs = all("SELECT f.*, (SELECT COALESCE(SUM(base), 0) FROM referral_earnings e WHERE e.referral_id = f.id AND e.status != 'void') brought,
    (SELECT COALESCE(SUM(amount), 0) FROM referral_earnings e WHERE e.referral_id = f.id AND e.status != 'void') earned FROM referrals f WHERE f.referrer_id = ? ORDER BY f.id DESC", [$id]);
$earn = all('SELECT e.*, f.business, f.name, i.number FROM referral_earnings e LEFT JOIN referrals f ON f.id = e.referral_id LEFT JOIN invoices i ON i.id = e.invoice_id WHERE e.referrer_id = ? ORDER BY e.paid_on DESC, e.id DESC LIMIT 200', [$id]);
$pays = all('SELECT * FROM referral_payouts WHERE referrer_id = ? ORDER BY id DESC', [$id]);
$block = ref_payout_block($r, $r['payout']);
$stripeCus = $r['payout'] === 'credit' ? ref_stripe_customer($r) : '';
layout_top($r['name'] . ' · Referrals', 'referrals', $user);
?>
<div class="page-head"><div><a class="muted small" href="referrals.php">← Referrals</a><h1><?= e($r['name']) ?></h1>
  <p class="muted small"><?= e(REF_KINDS[$r['kind']] ?? 'Referrer') ?> · <?= e(ref_pct($r['rate'])) ?> for <?= (int)$r['months'] ?> months · <?= e(REF_STATUSES[$r['status']]) ?><?= $r['contact_id'] ? ' · ' . entity_link('contact', (int)$r['contact_id']) : '' ?><?= $r['agreed_at'] ? ' · agreed to the rules ' . e(fmt_date($r['agreed_at'])) : '' ?></p></div>
  <form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="invite">Email them a sign-in link</button></form></div>
<section class="card"><div class="copy-row"><input readonly id="refLink" value="<?= e(ref_link($r)) ?>" aria-label="Referral link"><button class="btn ghost sm" type="button" data-copy="#refLink">Copy link</button></div></section>
<div class="kpis">
  <div class="card kpi"><span>Clients</span><b class="num"><?= $t['clients'] ?></b><small><?= $t['leads'] ?> not clients yet</small></div>
  <div class="card kpi"><span>Waiting</span><b class="num"><?= ref_money($t['held']) ?></b><small>Held <?= ref_hold_days() ?> days</small></div>
  <div class="card kpi"><span>Ready to pay</span><b class="num"><?= ref_money($t['ready']) ?></b><small>As <?= e(strtolower(REF_PAYOUTS[$r['payout']])) ?></small></div>
  <div class="card kpi"><span>Paid out</span><b class="num"><?= ref_money($t['paid']) ?></b><small><?= count($pays) ?> payout<?= count($pays) === 1 ? '' : 's' ?></small></div>
</div>
<?php if ($t['ready'] > 0): ?><section class="card attn">
  <div class="card-h"><h2>Pay <?= ref_money($t['ready']) ?> as <?= e(strtolower(REF_PAYOUTS[$r['payout']])) ?></h2></div>
  <?php if ($block): ?><p class="notice"><?= e($block) ?></p><?php else: ?>
  <p class="muted"><?php if ($r['payout'] === 'cash'): ?>Send it first (<?= $r['payout_details'] !== '' ? e($r['payout_details']) : 'they haven’t said where; ask them' ?>), then record it here with the reference.
    <?php elseif ($stripeCus !== ''): ?>They have a live monthly plan, so this adds a credit to their Stripe customer and it comes off their next bill automatically.
    <?php else: ?>They don’t have a monthly plan, so this records the credit; take it off their next invoice or order by hand (add a “Referral credit” line).<?php endif; ?> They’re emailed a receipt.</p>
  <form method="post" class="row" style="flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="payout">
    <input name="reference" placeholder="<?= $r['payout'] === 'cash' ? 'PayPal/Venmo ID or check number' : 'Note (optional)' ?>" style="max-width:280px"><input name="note" placeholder="Private note (optional)" style="max-width:280px">
    <button class="btn primary sm" data-confirm="Record a payout of <?= ref_money($t['ready']) ?>?">Record payout</button></form><?php endif; ?>
</section><?php endif; ?>
<div class="grid-detail">
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>People they referred</h2></div>
      <div class="table-wrap"><table class="tbl cards"><thead><tr><th>Client</th><th>Status</th><th class="r">Paid us</th><th class="r">Earned</th><th></th></tr></thead><tbody>
      <?php foreach ($refs as $f): ?>
        <tr><td><?= $f['contact_id'] ? entity_link('contact', (int)$f['contact_id']) : e($f['name']) ?><?= $f['business'] !== '' ? '<br><span class="muted small">' . e($f['business']) . '</span>' : '' ?><br><span class="muted small"><?= $f['how'] === 'manual' ? 'Added by hand' : 'Through the link' ?>, <?= e(fmt_date($f['created_at'])) ?></span></td>
          <td data-label="Status"><?= e(REF_REFERRAL_STATUSES[$f['status']] ?? $f['status']) ?><?= $f['ends_at'] && $f['status'] === 'client' ? '<br><span class="muted small">Earns until ' . e(fmt_date($f['ends_at'])) . '</span>' : '' ?><?= $f['void_reason'] !== '' ? '<br><span class="muted small">' . e($f['void_reason']) . '</span>' : '' ?></td>
          <td data-label="Paid us" class="r num"><?= ref_money($f['brought']) ?></td><td data-label="Earned" class="r num"><?= ref_money($f['earned']) ?></td>
          <td class="r"><?php if ($f['status'] !== 'void'): ?><details><summary class="link-danger small">Don’t count</summary><form method="post" class="stack-sm" style="margin-top:8px" data-confirm="Stop counting this referral? Anything not yet paid for it is cancelled."><?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="referral" value="<?= (int)$f['id'] ?>"><input name="reason" placeholder="Why (private)" aria-label="Why it doesn’t count"><button class="btn ghost xs">Confirm</button></form></details><?php endif; ?></td></tr>
      <?php endforeach; if (!$refs): ?><tr><td colspan="5" class="empty">No referrals yet.</td></tr><?php endif; ?>
      </tbody></table></div>
      <form method="post" class="row" style="margin-top:14px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="link">
        <select name="contact_id" style="max-width:340px"><option value="">A client named them when they got in touch…</option>
          <?php foreach (contact_options() as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(person_name($c)) ?><?= $c['company'] ? ' · ' . e($c['company']) : '' ?></option><?php endforeach; ?></select>
        <button class="btn ghost sm">Tie to <?= e(explode(' ', $r['name'])[0]) ?></button></form>
      <p class="muted small">Only for new clients. Payments from the day they’re tied count; payments before that day don’t.</p>
    </section>
    <section class="card">
      <div class="card-h"><h2>Earnings</h2></div>
      <div class="table-wrap"><table class="tbl cards"><thead><tr><th>Paid</th><th>From</th><th>Invoice</th><th>Status</th><th class="r">Counted</th><th class="r">Earned</th></tr></thead><tbody>
      <?php foreach ($earn as $x): ?>
        <tr><td data-label="Paid"><?= e(fmt_date($x['paid_on'])) ?></td><td data-label="From"><?= e($x['business'] ?: $x['name']) ?><?= $x['note'] !== '' ? '<br><span class="muted small">' . e($x['note']) . '</span>' : '' ?></td>
          <td data-label="Invoice"><?= $x['invoice_id'] ? '<a href="invoice.php?id=' . (int)$x['invoice_id'] . '">' . e($x['number']) . '</a>' : '—' ?></td>
          <td data-label="Status"><?= e((float)$x['amount'] < 0 && $x['status'] === 'ready' ? 'Comes off the next payout' : (REF_EARN_STATUSES[$x['status']] ?? $x['status'])) ?><?= $x['status'] === 'held' ? '<br><span class="muted small">Ready ' . e(fmt_date($x['ready_on'])) . '</span>' : '' ?></td>
          <td data-label="Counted" class="r num"><?= ref_money($x['base']) ?></td><td data-label="Earned" class="r num"><b><?= ref_money($x['amount']) ?></b> <span class="muted small"><?= e(ref_pct($x['rate'])) ?></span></td></tr>
      <?php endforeach; if (!$earn): ?><tr><td colspan="6" class="empty">Nothing yet. Earnings are recorded each cron run from paid invoices of the clients above.</td></tr><?php endif; ?>
      </tbody></table></div>
    </section>
    <?php if ($pays): ?><section class="card"><div class="card-h"><h2>Payouts</h2></div>
      <div class="table-wrap"><table class="tbl cards"><thead><tr><th>Date</th><th>How</th><th>Reference</th><th>By</th><th class="r">Amount</th></tr></thead><tbody>
      <?php foreach ($pays as $p): ?><tr><td data-label="Date"><?= e(fmt_date($p['created_at'])) ?></td><td data-label="How"><?= e(REF_PAYOUTS[$p['method']] ?? $p['method']) ?><?= $p['stripe_txn'] !== '' ? '<br><span class="muted small mono">' . e($p['stripe_txn']) . '</span>' : '' ?></td><td data-label="Reference"><?= e($p['reference']) ?><?= $p['note'] !== '' ? '<br><span class="muted small">' . e($p['note']) . '</span>' : '' ?></td><td data-label="By"><?= e($p['created_by']) ?></td><td data-label="Amount" class="r num"><?= ref_money($p['amount']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></section><?php endif; ?>
  </div>
  <form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="card-h"><h2>Details</h2></div>
    <label class="f"><span>Name</span><input name="name" value="<?= e($r['name']) ?>" required></label>
    <label class="f"><span>Email</span><input name="email" type="email" value="<?= e($r['email']) ?>" required></label>
    <label class="f"><span>Business</span><input name="business" value="<?= e($r['business']) ?>"></label>
    <label class="f"><span>Type</span><select name="kind"><?php foreach (REF_KINDS as $k => $l): ?><option value="<?= $k ?>"<?= selected($r['kind'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Rate (%)</span><input name="rate" type="number" step="0.5" min="0" max="50" value="<?= e((string)(float)$r['rate']) ?>"></label>
    <label class="f"><span>Months each client earns for</span><input name="months" type="number" min="1" max="60" value="<?= (int)$r['months'] ?>"></label>
    <label class="f"><span>Payout</span><select name="payout"><?php foreach (REF_PAYOUTS as $k => $l): ?><option value="<?= $k ?>"<?= selected($r['payout'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Where cash goes</span><input name="payout_details" value="<?= e($r['payout_details']) ?>"></label>
    <label class="chk"><input type="checkbox" name="w9" value="1"<?= (int)$r['w9'] ? ' checked' : '' ?>> W-9 on file (needed before any cash payout)</label>
    <label class="f"><span>Status</span><select name="status"><?php foreach (REF_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($r['status'], $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <p class="muted small">Paused: their link stops tying new people to them, existing clients keep earning. Closed: nothing new earns and they can’t sign in; what’s ready can still be paid.</p>
    <label class="f"><span>Private notes</span><textarea name="notes" rows="3"><?= e($r['notes']) ?></textarea></label>
    <div><button class="btn primary sm">Save</button></div>
  </form>
</div>
<?php layout_bottom();
