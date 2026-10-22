<?php
// The referral partner's page: their link, who they referred, what they've earned, payouts, and how they're paid.
// Sign in with an emailed one-time link (no password). Sign up is on the website (referrals.html).
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/referrals.php';
header('Cache-Control: no-store');
$r = ref_current();
$sent = false; $error = ''; $email = strtolower(post('email'));

if (get('t') !== '') {
    $new = too_many('ref_link', 20, 15) ? null : ref_use_link(get('t'));
    if ($new) { ref_sign_in($new); redirect('refer.php'); }
    record_attempt('ref_link');
    $error = 'That sign-in link has expired or was already used. Enter your email and we’ll send a new one.';
}
if (is_post()) {
    if (!csrf_ok()) { flash('Your session expired. Please try again.'); redirect('refer.php'); }
    $a = post('action');
    if ($a === 'signout') { ref_session(); unset($_SESSION['rid']); session_regenerate_id(true); redirect('refer.php'); }
    if (!$r && $a === 'email') {
        if (too_many('ref_email', 6, 15)) $error = 'Too many tries. Wait 15 minutes and try again.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter the email you signed up with.';
        else {
            record_attempt('ref_email');
            $found = one("SELECT * FROM referrers WHERE email = ? AND status != 'closed'", [$email]);
            if ($found) ref_send_signin($found);
            $sent = true; // same answer either way
        }
    }
    if ($r && $a === 'payout') {
        $p = post('payout') === 'cash' ? 'cash' : 'credit';
        q('UPDATE referrers SET payout = ?, payout_details = ?, updated_at = ? WHERE id = ?', [$p, mb_substr(post('payout_details'), 0, 300), now(), $r['id']]);
        if ($r['contact_id']) log_activity('contact', (int)$r['contact_id'], 'note', 'Changed their referral payout to ' . strtolower(REF_PAYOUTS[$p]) . '.');
        flash('Saved. Future payouts go out as ' . strtolower(REF_PAYOUTS[$p]) . '.');
        redirect('refer.php');
    }
}

if (!$r) {
    ref_top('Sign in', null); ?>
<form class="auth-card card portal-login" method="post">
  <div><h1><?= $sent ? 'Check your email' : 'Your referrals' ?></h1><p class="muted"><?= $sent ? 'If ' . e($email) . ' is signed up, a sign-in link is on its way. It works once and expires in ' . REF_SIGNIN_MINUTES . ' minutes. Check spam if you don’t see it.' : 'See who signed up through your link, what you’ve earned and what’s on its way. Enter the email you joined with and we’ll send a sign-in link. No password needed.' ?></p></div>
  <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$sent): ?>
    <?= csrf_field() ?><input type="hidden" name="action" value="email">
    <label class="f"><span>Email</span><input name="email" type="email" required value="<?= e($email) ?>" autocomplete="email" autofocus></label>
    <button class="btn primary" type="submit">Email me a sign-in link <?= ARROW ?></button>
  <?php else: ?><a class="btn ghost" href="refer.php">Use a different email</a><?php endif; ?>
  <p class="muted small">Not signed up yet? <a href="<?= e(ref_site()) ?>/referrals.html#join">Get your referral link</a>.</p>
</form>
<?php ref_bottom(); exit;
}

if ($r['status'] === 'paused') flash('Your referral account is paused. Earnings you already have are safe; email us with any questions.');
$t = ref_totals((int)$r['id']);
$refs = all('SELECT f.*, (SELECT COALESCE(SUM(amount), 0) FROM referral_earnings e WHERE e.referral_id = f.id AND e.status != \'void\') earned FROM referrals f WHERE f.referrer_id = ? ORDER BY f.id DESC', [$r['id']]);
$earn = all('SELECT e.*, f.business, f.name FROM referral_earnings e LEFT JOIN referrals f ON f.id = e.referral_id WHERE e.referrer_id = ? ORDER BY e.paid_on DESC, e.id DESC LIMIT 100', [$r['id']]);
$pays = all('SELECT * FROM referral_payouts WHERE referrer_id = ? ORDER BY id DESC', [$r['id']]);
$who = function ($row) { return $row['business'] ?: ($row['name'] ?: 'A referral'); };
ref_top('Your referrals', $r);
?>
<div class="page-head"><div class="grow-head"><h1>Your referrals</h1><p class="muted">You earn <b><?= e(ref_pct($r['rate'])) ?></b> of what each client you refer pays us in their first <?= (int)$r['months'] ?> months.</p></div></div>
<section class="card">
  <div class="card-h"><h2>Your link</h2></div>
  <div class="copy-row"><input readonly id="refLink" value="<?= e(ref_link($r)) ?>" aria-label="Your referral link"><button class="btn primary sm" type="button" data-copy="#refLink">Copy link</button></div>
  <p class="muted small" style="margin-top:10px">It works on any page of our website: add <span class="mono">?ref=<?= e($r['code']) ?></span> to the end. People can also mention your name or email (<?= e($r['email']) ?>) when they get in touch. Posting it publicly? Say you earn a commission, for example “I get a referral bonus if you sign up.”</p>
</section>
<div class="kpis">
  <div class="card kpi"><span>Clients</span><b class="num"><?= $t['clients'] ?></b><small><?= $t['leads'] ? $t['leads'] . ' more in touch with us' : 'Signed up through you' ?></small></div>
  <div class="card kpi"><span>Waiting</span><b class="num"><?= ref_money($t['held']) ?></b><small>Held <?= ref_hold_days() ?> days for refunds</small></div>
  <div class="card kpi"><span>Ready to pay</span><b class="num"><?= ref_money($t['ready']) ?></b><small><?= $t['ready'] < 0 ? 'A refund after a payout; it comes off your next earnings' : 'Paid out monthly' . ($r['payout'] === 'cash' ? ', from ' . ref_money(ref_min_cash(), false) : '') ?></small></div>
  <div class="card kpi"><span>Paid to you</span><b class="num"><?= ref_money($t['paid']) ?></b><small>As <?= e(strtolower(REF_PAYOUTS[$r['payout']] ?? 'credit')) ?></small></div>
</div>
<div class="grid-2">
  <section class="card">
    <div class="card-h"><h2>People you referred</h2></div>
    <?php if (!$refs): ?><p class="empty">No one yet. Share your link with a business that needs a website, ads, care or community management.</p><?php else: ?>
    <table class="tbl"><thead><tr><th>Business</th><th>Status</th><th class="r">You’ve earned</th></tr></thead><tbody>
    <?php foreach ($refs as $f): ?>
      <tr><td><b><?= e($who($f)) ?></b><br><span class="muted small">Since <?= e(fmt_date($f['created_at'])) ?></span></td>
        <td><?= e(REF_REFERRAL_STATUSES[$f['status']] ?? $f['status']) ?><?php if ($f['status'] === 'client' && $f['ends_at']): ?><br><span class="muted small"><?= $f['ends_at'] > today() ? 'Earning until ' . e(fmt_date($f['ends_at'])) : 'Finished ' . e(fmt_date($f['ends_at'])) ?></span><?php endif; ?></td>
        <td class="r num"><?= ref_money($f['earned']) ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <section class="card">
    <div class="card-h"><h2>How you’re paid</h2></div>
    <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="payout">
      <label class="f"><span>Payout</span><select name="payout">
        <option value="credit"<?= selected($r['payout'], 'credit') ?>>Account credit (any amount, off your own bill)</option>
        <option value="cash"<?= selected($r['payout'], 'cash') ?>>Cash (from <?= ref_money(ref_min_cash(), false) ?>, W-9 needed)</option></select></label>
      <label class="f"><span>Where cash should go <small>(PayPal, Venmo or a mailing address; no bank numbers)</small></span><input name="payout_details" maxlength="300" value="<?= e($r['payout_details']) ?>"></label>
      <div><button class="btn primary sm" type="submit">Save</button></div>
    </form>
    <?php if ($r['payout'] === 'cash' && !(int)$r['w9']): ?><p class="notice" style="margin-top:12px">Before your first cash payout we’ll email you for a W-9. The law asks us to have one before paying cash commissions.</p><?php endif; ?>
    <?php if ($r['payout'] === 'credit'): ?><p class="muted small" style="margin-top:12px">Credit comes off your next bill with us. If you have a monthly plan, it’s taken off automatically.</p><?php endif; ?>
  </section>
</div>
<section class="card">
  <div class="card-h"><h2>Earnings</h2></div>
  <?php if (!$earn): ?><p class="empty">Nothing yet. When someone you referred pays us, it shows up here.</p><?php else: ?>
  <table class="tbl"><thead><tr><th>Date</th><th>From</th><th>Status</th><th class="r">Payment</th><th class="r">You earn</th></tr></thead><tbody>
  <?php foreach ($earn as $x): ?>
    <tr><td><?= e(fmt_date($x['paid_on'])) ?></td><td><?= e($who($x)) ?><?= $x['note'] !== '' ? '<br><span class="muted small">' . e($x['note']) . '</span>' : '' ?></td>
      <td><?= e((float)$x['amount'] < 0 && $x['status'] === 'ready' ? 'Comes off the next payout' : (REF_EARN_STATUSES[$x['status']] ?? $x['status'])) ?><?= $x['status'] === 'held' ? '<br><span class="muted small">Ready ' . e(fmt_date($x['ready_on'])) . '</span>' : '' ?></td>
      <td class="r num"><?= ref_money($x['base']) ?></td><td class="r num"><b><?= ref_money($x['amount']) ?></b></td></tr>
  <?php endforeach; ?></tbody></table><?php endif; ?>
</section>
<?php if ($pays): ?><section class="card">
  <div class="card-h"><h2>Payouts</h2></div>
  <table class="tbl"><thead><tr><th>Date</th><th>How</th><th class="r">Amount</th></tr></thead><tbody>
  <?php foreach ($pays as $p): ?><tr><td><?= e(fmt_date($p['created_at'])) ?></td><td><?= e(REF_PAYOUTS[$p['method']] ?? $p['method']) ?><?= $p['reference'] !== '' ? ' · ' . e($p['reference']) : '' ?></td><td class="r num"><?= ref_money($p['amount']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section><?php endif; ?>
<?php ref_bottom();
