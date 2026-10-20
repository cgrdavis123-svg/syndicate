<?php
// Care: every monitored website at a glance (status, uptime, SSL, domain, backups, edit time, requests).
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/care.php';
$user = require_user();
if (is_post()) {
    if (post('action') === 'add' && ($s = one('SELECT * FROM sites WHERE id = ?', [(int)post('site')]))) {
        $sku = isset(CARE_TIERS[post('care')]) ? post('care') : '';
        q('UPDATE sites SET monitor = 1, care = ?, care_sku = ?, care_minutes = ?, care_built = ?, care_since = CASE WHEN ? = 1 THEN COALESCE(care_since, ?) ELSE care_since END WHERE id = ?',
            [$sku ? 1 : 0, $sku ?: 'care-plan', CARE_TIERS[$sku ?: 'care-plan']['minutes'], post('built') === '0' ? 0 : 1, $sku ? 1 : 0, now(), $s['id']]);
        if ($sku) { $s = one('SELECT * FROM sites WHERE id = ?', [$s['id']]); care_report_key($s); }
        care_run_checks((int)$s['id']);
        flash('Now monitoring ' . $s['name'] . '.'); redirect('care-site.php?id=' . $s['id']);
    }
    if (post('action') === 'psi_key') {
        require_once __DIR__ . '/app/mail.php';
        $k = trim(post('psi_key'));
        if (post('clear') === '1') { setting('psi_key', ''); flash('PageSpeed key removed.'); }
        elseif (preg_match('/^[A-Za-z0-9_\-]{20,60}$/', $k)) { setting('psi_key', mail_encrypt($k)); flash('PageSpeed key saved.'); }
        else flash('That doesn’t look like a Google API key.');
        redirect('care.php#psi');
    }
    if (post('action') === 'check') { set_time_limit(120); $n = care_run_checks(); flash("Checked $n site" . ($n === 1 ? '' : 's') . ' just now.'); redirect('care.php'); }
}
$sites = all('SELECT s.*, co.name company FROM sites s LEFT JOIN companies co ON co.id = s.company_id WHERE s.monitor = 1 OR s.care = 1 ORDER BY s.status = \'down\' DESC, s.care DESC, co.name, s.name');
$others = all('SELECT s.id, s.name, co.name company FROM sites s LEFT JOIN companies co ON co.id = s.company_id WHERE s.monitor = 0 AND s.care = 0 ORDER BY s.name');
$open = []; foreach (all("SELECT site_id, COUNT(*) n FROM care_requests WHERE status IN ('open','in_progress') GROUP BY site_id") as $r) $open[$r['site_id']] = (int)$r['n'];
$down = count(array_filter($sites, function ($s) { return $s['status'] === 'down'; }));
$overdue = count(array_filter($sites, function ($s) { return $s['care'] && care_backup_due($s); }));
$from30 = time() - 30 * 86400;
layout_top('Care', 'care', $user);
?>
<div class="page-head">
  <div><p class="eyebrow">Website care plans and monitoring</p><h1>Care</h1></div>
  <form method="post" class="row"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="check">Check all now</button></form>
</div>
<div class="kpis">
  <div class="card kpi"><span>Monitored</span><b class="num"><?= count(array_filter($sites, function ($s) { return $s['monitor']; })) ?></b><small><?= count(array_filter($sites, function ($s) { return $s['care']; })) ?> on a care plan</small></div>
  <div class="card kpi"><span>Down now</span><b class="num<?= $down ? ' warn' : '' ?>"><?= $down ?></b><small><?= $down ? 'Look at these first' : 'Everything is up' ?></small></div>
  <div class="card kpi"><span>Open requests</span><b class="num"><?= array_sum($open) ?></b><small>Edits clients asked for</small></div>
  <div class="card kpi"><span>Backups due</span><b class="num<?= $overdue ? ' warn' : '' ?>"><?= $overdue ?></b><small>Past their schedule</small></div>
</div>
<?php if (!$sites): ?>
  <div class="card an-empty"><h2>Nothing monitored yet</h2><p class="muted">Care plans bought on the store switch on here automatically. You can also start monitoring any website you track in Site stats, including your own.</p></div>
<?php else: ?>
<section class="card"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Website</th><th class="r">Uptime 30d</th><th class="r">Response</th><th class="r">PageSpeed</th><th>SSL</th><th>Domain</th><th>Last backup</th><th class="r">Edits this month</th><th class="r">Requests</th></tr></thead>
  <tbody>
  <?php foreach ($sites as $s): $ssl = care_days_left($s['ssl_expires'] ? (int)$s['ssl_expires'] : null); $dom = care_days_left($s['domain_expires'] ? (int)$s['domain_expires'] : null); $bk = care_last_backup((int)$s['id']); ?>
    <tr data-href="care-site.php?id=<?= (int)$s['id'] ?>">
      <td><a class="cell-main" href="care-site.php?id=<?= (int)$s['id'] ?>"><span class="dot <?= $s['status'] === 'down' ? 'bad' : ($s['status'] === 'up' ? 'ok' : '') ?>"></span><?= e($s['name']) ?></a><br><small class="muted"><?= e($s['company'] ?: 'No company') ?> · <?= $s['care'] ? '<span class="tag lime">' . e(care_tier($s)['name']) . '</span>' . ($s['care_built'] ? '' : ' · not built by us') : 'Monitoring only' ?><?= $s['monitor'] ? '' : ' · <span class="warn">paused</span>' ?></small></td>
      <td class="r num"><?= e(care_fmt_pct(care_uptime((int)$s['id'], $from30, time()))) ?></td>
      <td class="r num"><?= $s['last_ms'] ? (int)$s['last_ms'] . ' ms' : '—' ?></td>
      <?php $ps = json_decode($s['psi'] ?: '', true); $pv = $ps['scores']['performance'] ?? null; ?><td class="r num<?= $pv !== null && $pv < 50 ? ' warn' : '' ?>"><?= $pv === null ? '—' : $pv ?></td>
      <td class="<?= $ssl !== null && $ssl <= 14 ? 'warn' : '' ?>"><?= $ssl === null ? '—' : e($ssl . ' days') ?></td>
      <td class="<?= $dom !== null && $dom <= 30 ? 'warn' : '' ?>"><?= $dom === null ? '—' : e($dom . ' days') ?></td>
      <td class="<?= $s['care'] && care_backup_due($s) ? 'warn' : '' ?>"><?= $s['care'] ? ($bk ? e(ago($bk)) : 'None yet') : '—' ?></td>
      <td class="r num"><?= $s['care'] ? care_minutes_used((int)$s['id']) . ' / ' . (int)$s['care_minutes'] . ' min' : '—' ?></td>
      <td class="r num"><?= !empty($open[$s['id']]) ? '<span class="badge">' . $open[$s['id']] . '</span>' : '0' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></section>
<?php endif; ?>
<?php if ($others): ?>
<form method="post" class="card row" style="flex-wrap:wrap;gap:12px"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <label class="f grow"><span>Start monitoring a website from Site stats</span><select name="site"><?php foreach ($others as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name'] . ($o['company'] ? ' · ' . $o['company'] : '')) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Plan</span><select name="care"><option value="">Monitoring only</option><?php foreach (CARE_TIERS as $k => $t): ?><option value="<?= $k ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Who built it</span><select name="built"><option value="1">We did</option><option value="0">Someone else</option></select></label>
  <button class="btn primary sm" type="submit">Start monitoring</button>
</form>
<?php endif; ?>
<form method="post" class="card row" id="psi" style="flex-wrap:wrap;gap:12px;align-items:end"><?= csrf_field() ?><input type="hidden" name="action" value="psi_key">
  <label class="f grow"><span>Google PageSpeed API key <small class="muted">(free; without one Google limits the weekly tests)</small></span><input name="psi_key" type="password" autocomplete="off" placeholder="<?= setting('psi_key') !== '' ? 'Saved (encrypted). Paste a new one to replace it.' : 'Paste the key from Google Cloud → APIs → PageSpeed Insights API' ?>"></label>
  <button class="btn ghost sm" type="submit">Save key</button><?php if (setting('psi_key') !== ''): ?><button class="link-danger" name="clear" value="1" type="submit">Remove</button><?php endif; ?>
</form>
<?php layout_bottom(); ?>
