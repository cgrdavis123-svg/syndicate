<?php
// Referral program: everyone with a referral link, what they've brought in, and what's ready to pay.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/referrals.php';
$user = require_user();
if (is_post()) {
    if (post('action') === 'settings') {
        setting('ref_on', post('ref_on') === '1' ? '1' : '0');
        setting('ref_rate', (string)min(50, max(0.5, round((float)post('ref_rate'), 2))));
        setting('ref_months', (string)min(60, max(1, (int)post('ref_months'))));
        setting('ref_hold_days', (string)min(120, max(0, (int)post('ref_hold_days'))));
        setting('ref_min_cash', (string)max(0, round((float)post('ref_min_cash'), 2)));
        flash('Saved. New sign-ups get the new rate; existing referrers keep theirs (change them one by one). Update the numbers in build-marketing.mjs too, so the website matches.');
    } elseif (post('action') === 'sweep') {
        $n = ref_sweep(); flash($n ? "$n new earning(s) recorded." : 'Everything is up to date.');
    } elseif (post('action') === 'add') {
        $name = mb_substr(post('name'), 0, 120); $email = strtolower(mb_substr(post('email'), 0, 190));
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Enter a name and a valid email.');
        elseif (val('SELECT 1 FROM referrers WHERE email = ?', [$email])) flash('Someone with that email already has a referral account.');
        else {
            $kind = post('kind') === 'rep' ? 'rep' : 'referrer';
            $contact = one('SELECT id FROM contacts WHERE email = ? COLLATE NOCASE ORDER BY id LIMIT 1', [$email]);
            q('INSERT INTO referrers (code, name, email, business, contact_id, kind, rate, months, payout, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [ref_new_code($name), $name, $email, mb_substr(post('business'), 0, 150), $contact ? (int)$contact['id'] : null, $kind, $kind === 'rep' ? 10 : ref_rate_default(), ref_months_default(), $kind === 'rep' ? 'cash' : 'credit', now(), now()]);
            $id = (int)db()->lastInsertId();
            if (post('invite') === '1') ref_send_signin(ref_get($id));
            flash('Added.' . (post('invite') === '1' ? ' We emailed them a sign-in link with their referral link.' : ''));
            redirect('referrer.php?id=' . $id);
        }
    }
    redirect('referrals.php');
}
$rows = all("SELECT r.*,
    (SELECT COUNT(*) FROM referrals f WHERE f.referrer_id = r.id AND f.status = 'client') clients,
    (SELECT COUNT(*) FROM referrals f WHERE f.referrer_id = r.id AND f.status = 'lead') leads,
    (SELECT COALESCE(SUM(amount), 0) FROM referral_earnings e WHERE e.referrer_id = r.id AND e.status = 'held') held,
    (SELECT COALESCE(SUM(amount), 0) FROM referral_earnings e WHERE e.referrer_id = r.id AND e.status = 'ready') ready,
    (SELECT COALESCE(SUM(amount), 0) FROM referral_payouts p WHERE p.referrer_id = r.id) paid,
    (SELECT COALESCE(SUM(e.base), 0) FROM referral_earnings e WHERE e.referrer_id = r.id AND e.status != 'void') brought
    FROM referrers r ORDER BY r.status = 'closed', ready DESC, r.id DESC");
$sum = function ($k) use ($rows) { return array_sum(array_column($rows, $k)); };
layout_top('Referrals', 'referrals', $user);
?>
<div class="page-head"><div><h1>Referrals</h1><p class="muted small">Anyone can join at <a href="<?= e(ref_site()) ?>/referrals.html" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', ref_site())) ?>/referrals.html</a>. A new client who comes through a link earns that person <?= e(ref_pct(ref_rate_default())) ?> of what they pay in their first <?= ref_months_default() ?> months, held <?= ref_hold_days() ?> days, then paid as credit or cash.<?= ref_on() ? '' : ' <b>Sign-ups and new links are switched off.</b>' ?></p></div>
  <form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="sweep">Check for new earnings</button></form></div>
<div class="kpis">
  <div class="card kpi"><span>Referred clients</span><b class="num"><?= (int)$sum('clients') ?></b><small><?= (int)$sum('leads') ?> more in the pipeline</small></div>
  <div class="card kpi"><span>Brought in</span><b class="num"><?= money_short($sum('brought')) ?></b><small>Paid by referred clients</small></div>
  <div class="card kpi"><span>Ready to pay</span><b class="num"><?= ref_money($sum('ready')) ?></b><small><?= ref_money($sum('held')) ?> still waiting</small></div>
  <div class="card kpi"><span>Paid out</span><b class="num"><?= ref_money($sum('paid')) ?></b><small>Credit and cash</small></div>
</div>
<section class="card"><div class="table-wrap"><table class="tbl cards"><thead><tr><th>Person</th><th>Rate</th><th>Payout</th><th class="r">Clients</th><th class="r">Brought in</th><th class="r">Waiting</th><th class="r">Ready</th><th class="r">Paid</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><a href="referrer.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['name']) ?></b></a><?= $r['kind'] === 'rep' ? ' <span class="tag">Sales rep</span>' : '' ?><?= $r['status'] !== 'active' ? ' <span class="tag">' . e(REF_STATUSES[$r['status']]) . '</span>' : '' ?><br><span class="muted small"><?= e($r['email']) ?> · <span class="mono"><?= e($r['code']) ?></span></span></td>
    <td data-label="Rate" class="num"><?= e(ref_pct($r['rate'])) ?></td><td data-label="Payout"><?= e(REF_PAYOUTS[$r['payout']] ?? $r['payout']) ?><?= $r['payout'] === 'cash' && !(int)$r['w9'] ? '<br><span class="muted small">No W-9 yet</span>' : '' ?></td>
    <td data-label="Clients" class="r num"><?= (int)$r['clients'] ?><?= $r['leads'] ? ' <span class="muted small">+' . (int)$r['leads'] . '</span>' : '' ?></td><td data-label="Brought in" class="r num"><?= ref_money($r['brought'], false) ?></td>
    <td data-label="Waiting" class="r num"><?= ref_money($r['held']) ?></td><td data-label="Ready" class="r num"><b><?= ref_money($r['ready']) ?></b></td><td data-label="Paid" class="r num"><?= ref_money($r['paid']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No one has joined yet. Share the sign-up page with happy clients.</td></tr><?php endif; ?>
</tbody></table></div></section>
<div class="grid-2">
  <form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="card-h"><h2>Add someone by hand</h2></div>
    <label class="f"><span>Name</span><input name="name" required></label>
    <label class="f"><span>Email</span><input name="email" type="email" required></label>
    <label class="f"><span>Business <small>(optional)</small></span><input name="business"></label>
    <label class="f"><span>Type</span><select name="kind"><option value="referrer">Referrer (<?= e(ref_pct(ref_rate_default())) ?>, credit by default)</option><option value="rep">Commissioned sales rep (10%, cash)</option></select></label>
    <label class="chk"><input type="checkbox" name="invite" value="1" checked> Email them their link and a sign-in link</label>
    <div><button class="btn primary sm">Add</button></div>
  </form>
  <form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <div class="card-h"><h2>Program settings</h2></div>
    <label class="chk"><input type="checkbox" name="ref_on" value="1"<?= ref_on() ? ' checked' : '' ?>> Taking sign-ups and new referrals</label>
    <label class="f"><span>Rate for new sign-ups (%)</span><input name="ref_rate" type="number" step="0.5" min="0.5" max="50" value="<?= e(setting('ref_rate')) ?>"></label>
    <label class="f"><span>Months each client earns for</span><input name="ref_months" type="number" min="1" max="60" value="<?= e(setting('ref_months')) ?>"></label>
    <label class="f"><span>Days to hold each earning (refunds)</span><input name="ref_hold_days" type="number" min="0" max="120" value="<?= e(setting('ref_hold_days')) ?>"></label>
    <label class="f"><span>Smallest cash payout ($)</span><input name="ref_min_cash" type="number" step="1" min="0" value="<?= e(setting('ref_min_cash')) ?>"></label>
    <p class="muted small">The website shows these numbers too. If you change them, change <span class="mono">refRate</span>, <span class="mono">refMonths</span>, <span class="mono">refHoldDays</span> and <span class="mono">refMinCash</span> in build-marketing.mjs, and tell existing referrers, since the rules page promises them.</p>
    <div><button class="btn primary sm">Save settings</button></div>
  </form>
</div>
<?php layout_bottom();
