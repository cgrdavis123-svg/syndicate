<?php
// One monitored website: status, uptime history, incidents, SSL/domain, weekly scan, speed, edit requests,
// the care work log, backups and settings.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/care.php';
$user = require_user();
$s = one('SELECT s.*, co.name company FROM sites s LEFT JOIN companies co ON co.id = s.company_id WHERE s.id = ?', [(int)get('id')]);
if (!$s) { flash('That website isn’t set up.'); redirect('care.php'); }
$id = (int)$s['id']; $back = 'care-site.php?id=' . $id;
if (is_post()) {
    $a = post('action');
    if ($a === 'settings') {
        $url = post('check_url'); if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $sku = isset(CARE_TIERS[post('care_sku')]) ? post('care_sku') : $s['care_sku'];
        $mins = max(0, min(600, (int)post('care_minutes')));
        if ($sku !== $s['care_sku'] && $mins === (int)$s['care_minutes']) $mins = CARE_TIERS[$sku]['minutes']; // switching plans moves the allowance too, unless it was changed by hand
        q('UPDATE sites SET check_url = ?, check_keyword = ?, monitor = ?, care = ?, notify_client = ?, care_minutes = ?, backup_days = ?, care_sku = ?, care_platform = ?, care_built = ?, care_since = CASE WHEN ? = 1 THEN COALESCE(care_since, ?) ELSE care_since END WHERE id = ?', [
            filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 300) : '', mb_substr(post('check_keyword'), 0, 120), post('monitor') === '1' ? 1 : 0, post('care') === '1' ? 1 : 0,
            post('notify_client') === '1' ? 1 : 0, $mins, max(1, min(31, (int)post('backup_days'))), $sku, isset(CARE_PLATFORMS[post('care_platform')]) ? post('care_platform') : '', post('care_built') === '1' ? 1 : 0,
            post('care') === '1' ? 1 : 0, now(), $id]);
        if (post('care') === '1') { $fresh = one('SELECT * FROM sites WHERE id = ?', [$id]); care_report_key($fresh); }
        flash('Care settings saved.'); redirect($back);
    }
    if ($a === 'check') {
        set_time_limit(180); care_run_checks($id); $fresh = one('SELECT * FROM sites WHERE id = ?', [$id]);
        care_check_ssl($fresh); care_check_domain($fresh); care_scan($fresh); care_psi_due(1, $id);
        flash('Checked uptime, SSL and domain, ran the security scan and the PageSpeed test.'); redirect($back);
    }
    if ($a === 'log' || $a === 'backup') {
        $kind = $a === 'backup' ? 'backup' : (isset(CARE_KINDS[post('kind')]) ? post('kind') : 'note');
        $title = $a === 'backup' ? 'Full backup taken' . (post('where') !== '' ? ' (' . mb_substr(post('where'), 0, 80) . ')' : '') : mb_substr(post('title'), 0, 200);
        if ($title !== '') {
            q('INSERT INTO care_log (site_id, kind, title, details, minutes, request_id, shared, created_at) VALUES (?,?,?,?,?,?,?,?)', [$id, $kind, $title, mb_substr(post('details'), 0, 4000),
                max(0, min(600, (int)post('minutes'))), (int)post('request') ?: null, post('shared', '1') === '1' ? 1 : 0, valid_date(post('date')) && post('date') !== today() ? post('date') . ' 12:00:00' : now()]);
            flash(CARE_KINDS[$kind] . ' logged.');
        }
        redirect($back . '#log');
    }
    if ($a === 'delete_log') { q('DELETE FROM care_log WHERE id = ? AND site_id = ?', [(int)post('log'), $id]); flash('Removed from the log.'); redirect($back . '#log'); }
    if ($a === 'request' && ($r = one('SELECT * FROM care_requests WHERE id = ? AND site_id = ?', [(int)post('request'), $id]))) {
        $st = isset(CARE_REQ_STATUSES[post('status')]) ? post('status') : $r['status']; $min = max(0, min(600, (int)post('minutes'))); $reply = mb_substr(trim(post('reply')), 0, 3000);
        q('UPDATE care_requests SET status = ?, reply = ?, minutes = minutes + ?, updated_at = ?, done_at = CASE WHEN ? = \'done\' THEN COALESCE(done_at, ?) ELSE done_at END WHERE id = ?', [$st, $reply, $min, now(), $st, now(), $r['id']]);
        if ($min) q("INSERT INTO care_log (site_id, kind, title, details, minutes, request_id, shared, created_at) VALUES (?, 'edit', ?, ?, ?, ?, 1, ?)", [$id, $r['title'], $reply, $min, $r['id'], now()]);
        if ($st !== $r['status'] && in_array($st, ['done', 'declined', 'in_progress'], true) && ($c = one('SELECT * FROM contacts WHERE id = ?', [(int)$r['contact_id']])) && $c['email'] !== '') {
            $what = ['done' => 'is done', 'declined' => 'needs a quick word', 'in_progress' => 'is in progress'][$st];
            send_mail($c['email'], 'Your change request ' . $what . ' · ' . $s['name'], 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\nYour request “" . $r['title'] . "” $what." . ($reply !== '' ? "\n\n$reply" : '')
                . ($st === 'done' ? "\n\nEdit time used this month: " . care_minutes_used($id) . ' of ' . (int)$s['care_minutes'] . ' minutes.' : '') . "\n\nSee all your requests: " . abs_url('portal-care.php?id=' . $id) . "\n\n" . care_brand($s) . "\n" . setting('business_email'));
        }
        flash('Request updated.'); redirect($back . '#requests');
    }
}
$now = time(); $days = care_days($id, 90);
$inc = all('SELECT * FROM site_incidents WHERE site_id = ? ORDER BY started DESC LIMIT 12', [$id]);
$reqs = all("SELECT r.*, c.first_name, c.last_name, c.email FROM care_requests r LEFT JOIN contacts c ON c.id = r.contact_id WHERE r.site_id = ? ORDER BY r.status IN ('done','declined'), r.id DESC LIMIT 40", [$id]);
$log = all('SELECT * FROM care_log WHERE site_id = ? ORDER BY created_at DESC, id DESC LIMIT 60', [$id]);
$scan = json_decode($s['scan'] ?: '', true);
$R = an_report($s, an_range('30d'), []); $V = array_filter($R['vitals']['all'] ?? []);
$ssl = care_days_left($s['ssl_expires'] ? (int)$s['ssl_expires'] : null); $dom = care_days_left($s['domain_expires'] ? (int)$s['domain_expires'] : null);
$used = care_minutes_used($id); $bk = care_last_backup($id); $psi = json_decode($s['psi'] ?: '', true);
layout_top($s['name'] . ' care', 'care', $user);
?>
<div class="page-head">
  <div class="grow-head"><a class="muted small" href="care.php">← Care</a><h1><span class="dot <?= $s['status'] === 'down' ? 'bad' : ($s['status'] === 'up' ? 'ok' : '') ?>"></span><?= e($s['name']) ?></h1>
    <p class="row muted small"><a href="<?= e(care_url($s)) ?>" target="_blank" rel="noopener noreferrer"><?= e(care_url($s) ?: 'No address yet') ?> ↗</a><?php if ($s['company_id']): ?> · <a href="company.php?id=<?= (int)$s['company_id'] ?>"><?= e($s['company']) ?></a><?php endif; ?> · <?= $s['care'] ? '<span class="tag lime">' . e(care_tier($s)['name']) . '</span>' : '<span class="tag">Monitoring only</span>' ?><?= $s['care'] ? ' · ' . ($s['care_built'] ? 'Built by us' : 'Not built by us') . ($s['care_platform'] ? ' · ' . e(CARE_PLATFORMS[$s['care_platform']] ?? $s['care_platform']) : '') : '' ?> · <a href="site.php?id=<?= $id ?>">Site stats</a></p></div>
  <form method="post" class="row"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="check">Check everything now</button></form>
</div>
<?php if ($s['status'] === 'down'): ?><div class="notice bad">Down since <?= e(date('M j, g:i a', (int)$s['status_since'])) ?> (<?= e(care_dur($now - (int)$s['status_since'])) ?>). Last error: <?= e($s['last_error']) ?></div><?php endif; ?>
<div class="kpis">
  <div class="card kpi"><span>Status</span><b class="<?= $s['status'] === 'down' ? 'warn' : '' ?>"><?= $s['status'] === 'down' ? 'Down' : ($s['status'] === 'up' ? 'Up' : 'Not checked') ?></b><small><?= $s['last_check'] ? 'Checked ' . e(ago(date('Y-m-d H:i:s', (int)$s['last_check']))) : 'Every 15 minutes' ?></small></div>
  <div class="card kpi"><span>Uptime 30 days</span><b class="num"><?= e(care_fmt_pct(care_uptime($id, $now - 30 * 86400, $now))) ?></b><small>90 days: <?= e(care_fmt_pct(care_uptime($id, $now - 90 * 86400, $now))) ?></small></div>
  <div class="card kpi"><span>Response time</span><b class="num"><?= ($ms = care_avg_ms($id, $now - 7 * 86400, $now)) ? $ms . ' ms' : '—' ?></b><small>Average, last 7 days</small></div>
  <div class="card kpi"><span>Edits this month</span><b class="num"><?= $used ?><small> / <?= (int)$s['care_minutes'] ?> min</small></b><small><?= $bk ? 'Last backup ' . e(ago($bk)) : 'No backup logged yet' ?></small></div>
</div>
<section class="card"><div class="card-h"><h2>Uptime, last 90 days</h2><span class="muted small">Each bar is a day. Hover for details.</span></div><?= care_uptime_bar($days) ?>
  <?php if ($inc): ?><table class="tbl" style="margin-top:14px"><thead><tr><th>Outage</th><th>Length</th><th>Cause</th></tr></thead><tbody>
  <?php foreach ($inc as $i): ?><tr><td><?= e(date('M j, g:i a', (int)$i['started'])) ?></td><td><?= $i['ended'] ? e(care_dur((int)$i['ended'] - (int)$i['started'])) : '<span class="warn">Ongoing</span>' ?></td><td class="muted"><?= e($i['cause']) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php else: ?><p class="muted small">No outages recorded.</p><?php endif; ?>
</section>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>Renewals</h2></div>
    <dl class="facts">
      <div><dt>SSL certificate</dt><dd class="<?= $ssl !== null && $ssl <= 14 ? 'warn' : '' ?>"><?= $s['ssl_expires'] ? e('Expires ' . date('M j, Y', (int)$s['ssl_expires']) . " ($ssl days)") : 'Not checked yet' ?><?= $s['ssl_issuer'] ? '<br><small class="muted">' . e($s['ssl_issuer']) . '</small>' : '' ?></dd></div>
      <div><dt>Domain</dt><dd class="<?= $dom !== null && $dom <= 30 ? 'warn' : '' ?>"><?= $s['domain_expires'] ? e('Expires ' . date('M j, Y', (int)$s['domain_expires']) . " ($dom days)") : ($s['domain_checked'] ? 'Registry doesn’t publish it' : 'Not checked yet') ?><?= $s['domain_registrar'] ? '<br><small class="muted">' . e($s['domain_registrar']) . '</small>' : '' ?></dd></div>
    </dl>
  </section>
  <section class="card"><div class="card-h"><h2>Speed from real visitors</h2><span class="muted small">Last 30 days, 75th percentile</span></div>
    <?php if (!$V): ?><p class="empty">No speed data yet. It comes from Site Stats once the install code is on the site.</p><?php else: ?><div class="vitals">
    <?php foreach (['lcp' => 'Main content', 'inp' => 'Responsiveness', 'cls' => 'Layout shift'] as $m => $n): if (empty($V[$m])) continue; ?><div class="vital <?= vital_rating($m, $V[$m]['p75']) ?>"><span><?= e($n) ?></span><b class="num"><?= e(vital_fmt($m, $V[$m]['p75'])) ?></b></div><?php endforeach; ?></div><?php endif; ?>
  </section>
</div>
<section class="card"><div class="card-h"><h2>Google PageSpeed</h2><span class="muted small"><?= $psi && !empty($psi['at']) ? 'Phone test, ' . e(ago(date('Y-m-d H:i:s', (int)$psi['at']))) : 'Runs weekly' ?></span></div>
  <?php if (!$psi): ?><p class="empty">Not tested yet. Use “Check everything now”.</p>
  <?php elseif (!empty($psi['error'])): ?><p class="notice bad">PageSpeed couldn’t test the site: <?= e($psi['error']) ?></p>
  <?php else: ?><?= care_psi_rings($psi) ?><?php if (!empty($psi['retry_error'])): ?><p class="muted small">Last retry failed (<?= e($psi['retry_error']) ?>); showing the previous result.</p><?php endif; ?>
    <?php if ($psi['metrics']): ?><p class="muted small" style="margin-top:12px"><?= e(implode(' · ', array_map(function ($k) use ($psi) { return CARE_PSI_METRICS[$k] . ' ' . $psi['metrics'][$k]['show']; }, array_keys(array_filter($psi['metrics'], function ($m) { return $m['show'] !== ''; }))))) ?></p><?php endif; ?>
    <?php if ($psi['fixes']): ?><ul class="psi-fixes"><?php foreach ($psi['fixes'] as $f): ?><li><span><?= e($f['title']) ?></span><span class="muted num"><?= $f['ms'] >= 100 ? '~' . e(number_format($f['ms'] / 1000, 1)) . ' s' : e($f['show']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  <?php endif; ?>
</section>
<?php if ($s['care']): ?><section class="card"><div class="card-h"><h2>Care reports</h2><span class="muted small">Emailed to the client on the 1st with a link to the full report</span></div>
  <div class="row" style="flex-wrap:wrap;gap:8px"><?php foreach (care_report_months($s) as $m): ?><a class="btn ghost sm" href="<?= e(care_report_url($s, $m)) ?>" target="_blank" rel="noopener"><?= e(date('M Y', strtotime($m . '-01'))) ?><?= $m === date('Y-m') ? ' (so far)' : '' ?></a><?php endforeach; ?></div>
</section><?php endif; ?>
<section class="card"><div class="card-h"><h2>Weekly security &amp; health scan</h2><span class="muted small"><?= $scan ? 'Last run ' . e(ago(date('Y-m-d H:i:s', (int)$scan['at']))) : 'Runs weekly' ?></span></div>
  <?php if (!$scan): ?><p class="empty">Not scanned yet. Use “Check everything now”.</p><?php else: ?>
  <ul class="findings"><?php foreach (care_findings($s) as [$k, $t]): ?><li class="<?= e($k) ?>"><?= e($t) ?></li><?php endforeach; ?></ul>
  <p class="muted small">Software: <?= e($scan['software'] ?: 'not detected') ?><?= $scan['server'] ? ' · Server: ' . e($scan['server']) : '' ?> · Home page <?= (int)$scan['kb'] ?> KB in <?= (int)$scan['ms'] ?> ms<?= $scan['title'] !== '' ? ' · Title: “' . e(mb_strimwidth($scan['title'], 0, 70, '…')) . '”' : '' ?></p>
  <?php if ($scan['exposed']): ?><p class="notice bad">Exposed: <?php foreach ($scan['exposed'] as $x): ?><code><?= e($x['path']) ?></code> <?= e($x['what']) ?>. <?php endforeach; ?></p><?php endif; ?>
  <?php if ($scan['broken']): ?><details open><summary><?= count($scan['broken']) ?> broken link<?= count($scan['broken']) === 1 ? '' : 's' ?></summary><ul class="plain-list small"><?php foreach ($scan['broken'] as $b): ?><li><code><?= (int)$b['code'] ?: 'no reply' ?></code> <?= e($b['url']) ?> <span class="muted">(from <?= e($b['from']) ?>)</span></li><?php endforeach; ?></ul></details><?php endif; ?>
  <?php if ($scan['mixed']): ?><details><summary>Insecure (http://) items</summary><ul class="plain-list small"><?php foreach ($scan['mixed'] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
  <?php $miss = array_keys(array_filter($scan['headers'], function ($v) { return !$v; })); if ($miss): ?><p class="muted small">Missing headers: <?= e(implode(', ', array_map(function ($k) { return ['hsts' => 'Strict-Transport-Security', 'nosniff' => 'X-Content-Type-Options', 'frames' => 'X-Frame-Options / frame-ancestors', 'csp' => 'Content-Security-Policy', 'referrer' => 'Referrer-Policy'][$k]; }, $miss))) ?></p><?php endif; ?>
  <?php endif; ?>
</section>
<section class="card" id="requests"><div class="card-h"><h2>Change requests</h2><span class="muted small">Clients send these from their portal</span></div>
  <?php if (!$reqs): ?><p class="empty">No requests yet.</p><?php else: foreach ($reqs as $r): ?>
  <form method="post" class="req <?= e($r['status']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="request"><input type="hidden" name="request" value="<?= (int)$r['id'] ?>">
    <div class="row between"><b><?= e($r['title']) ?></b><span class="muted small"><?= e(trim($r['first_name'] . ' ' . $r['last_name']) ?: 'Client') ?> · <?= e(ago($r['created_at'])) ?><?= $r['minutes'] ? ' · ' . (int)$r['minutes'] . ' min' : '' ?></span></div>
    <?php if ($r['details'] !== ''): ?><p class="text"><?= nl2br(e($r['details'])) ?></p><?php endif; ?><?php if ($r['page_url'] !== ''): ?><p class="small"><a href="<?= e($r['page_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($r['page_url']) ?> ↗</a></p><?php endif; ?>
    <div class="row" style="flex-wrap:wrap;gap:10px"><select name="status" aria-label="Status"><?php foreach (CARE_REQ_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $r['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <input name="minutes" type="number" min="0" max="600" placeholder="Minutes spent" aria-label="Minutes spent" style="width:140px"><input name="reply" class="grow" value="<?= e($r['reply']) ?>" placeholder="Note to the client (emailed when the status changes)" aria-label="Note to the client"><button class="btn primary sm" type="submit">Save</button></div>
  </form>
  <?php endforeach; endif; ?>
</section>
<div class="grid-2">
  <form method="post" class="card form-grid" id="log"><?= csrf_field() ?><input type="hidden" name="action" value="log">
    <div class="card-h span2"><h2>Log work</h2><span class="muted small">Shared entries show in the client’s portal and monthly report</span></div>
    <label class="f"><span>Type</span><select name="kind"><?php foreach (CARE_KINDS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Minutes <small class="muted">(edits count toward the <?= (int)$s['care_minutes'] ?>)</small></span><input name="minutes" type="number" min="0" max="600" value="0"></label>
    <label class="f span2"><span>What you did</span><input name="title" required placeholder="e.g. Updated WordPress to 6.6 and 4 plugins"></label>
    <label class="f span2"><span>Details <small class="muted">(optional)</small></span><textarea name="details" rows="2"></textarea></label>
    <label class="f"><span>Date</span><input name="date" type="date" value="<?= e(today()) ?>"></label>
    <label class="chk" style="align-self:end"><input type="hidden" name="shared" value="0"><input type="checkbox" name="shared" value="1" checked> <span>Show to the client</span></label>
    <button class="btn primary span2" type="submit">Add to log</button>
  </form>
  <form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="backup">
    <div class="card-h span2"><h2>Backups</h2><span class="muted small">Every <?= (int)$s['backup_days'] ?> days · <?= $bk ? 'last ' . e(ago($bk)) : 'none logged' ?></span></div>
    <?php if ($s['care'] && care_backup_due($s)): ?><p class="notice span2">A backup is due.</p><?php endif; ?>
    <label class="f span2"><span>Where it’s stored <small class="muted">(optional)</small></span><input name="where" placeholder="e.g. cPanel backup to Backblaze"></label>
    <button class="btn ghost span2" type="submit">Log a backup taken today</button>
  </form>
</div>
<section class="card"><div class="card-h"><h2>Care log</h2></div>
  <?php if (!$log): ?><p class="empty">Nothing logged yet.</p><?php else: ?><ul class="feed"><?php foreach ($log as $l): ?>
    <li><span class="kind k-note"></span><span class="grow"><small><?= e(CARE_KINDS[$l['kind']] ?? 'Note') ?> · <?= e(fmt_date($l['created_at'])) ?><?= $l['minutes'] ? ' · ' . (int)$l['minutes'] . ' min' : '' ?><?= $l['shared'] ? '' : ' · <span class="warn">private</span>' ?></small><span><?= e($l['title']) ?></span><?php if ($l['details'] !== ''): ?><span class="muted small"><?= e($l['details']) ?></span><?php endif; ?></span>
      <form method="post" class="inline" data-confirm="Remove this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_log"><input type="hidden" name="log" value="<?= (int)$l['id'] ?>"><button class="x" type="submit" aria-label="Remove">×</button></form></li>
  <?php endforeach; ?></ul><?php endif; ?>
</section>
<form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <div class="card-h span2"><h2>Settings</h2></div>
  <label class="f"><span>Address to check</span><input name="check_url" value="<?= e($s['check_url']) ?>" placeholder="<?= e(care_url(array_merge($s, ['check_url' => ''])) ?: 'https://example.com/') ?>"></label>
  <label class="f"><span>Text that must be on the page <small class="muted">(optional)</small></span><input name="check_keyword" value="<?= e($s['check_keyword']) ?>" placeholder="e.g. the business name"></label>
  <label class="f"><span>Plan</span><select name="care_sku"><?php foreach (CARE_TIERS as $k => $t): ?><option value="<?= $k ?>"<?= selected($k, $s['care_sku']) ?>><?= e($t['name'] . ' (' . $t['minutes'] . ' min)') ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Platform</span><select name="care_platform"><option value="">—</option><?php foreach (CARE_PLATFORMS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $s['care_platform']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Edit minutes per month</span><input name="care_minutes" type="number" min="0" max="600" value="<?= (int)$s['care_minutes'] ?>"></label>
  <label class="f"><span>Backup every (days)</span><input name="backup_days" type="number" min="1" max="31" value="<?= (int)$s['backup_days'] ?>"></label>
  <label class="chk"><input type="hidden" name="monitor" value="0"><input type="checkbox" name="monitor" value="1"<?= $s['monitor'] ? ' checked' : '' ?>> <span>Check uptime, SSL, domain and security</span></label>
  <label class="chk"><input type="hidden" name="care" value="0"><input type="checkbox" name="care" value="1"<?= $s['care'] ? ' checked' : '' ?>> <span>On a care plan (portal page, requests, monthly care report)</span></label>
  <label class="chk"><input type="hidden" name="care_built" value="0"><input type="checkbox" name="care_built" value="1"<?= $s['care_built'] ? ' checked' : '' ?>> <span>We built this site</span></label>
  <label class="chk"><input type="hidden" name="notify_client" value="0"><input type="checkbox" name="notify_client" value="1"<?= $s['notify_client'] ? ' checked' : '' ?>> <span>Email the client when the site goes down and comes back</span></label>
  <button class="btn primary span2" type="submit">Save settings</button>
</form>
<?php layout_bottom(); ?>
