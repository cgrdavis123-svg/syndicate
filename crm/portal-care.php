<?php
// Website care in the client portal: live status, uptime history, renewals, security scan, speed,
// the work we've done, edit time used, and a form to request a change.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/care.php';
$c = require_portal();
$s = $c['company_id'] ? one('SELECT * FROM sites WHERE id = ? AND care = 1 AND company_id = ?', [(int)get('id'), (int)$c['company_id']]) : null;
if (!$s) { flash('That website isn’t on a care plan.'); redirect('portal.php'); }
$id = (int)$s['id']; $back = 'portal-care.php?id=' . $id;
$errors = [];
if (is_post() && post('action') === 'request') {
    $title = mb_substr(trim(post('title')), 0, 200); $details = mb_substr(trim(post('details')), 0, 5000); $page = mb_substr(trim(post('page_url')), 0, 300);
    if ($page !== '' && !preg_match('#^https?://#i', $page)) $page = 'https://' . $page;
    if (mb_strlen($title) < 3) $errors[] = 'Tell us briefly what you’d like changed.';
    elseif (too_many('care_request', 15, 60)) $errors[] = 'That’s a lot of requests at once. Wait a little, or email us.';
    else {
        record_attempt('care_request');
        q('INSERT INTO care_requests (site_id, contact_id, title, details, page_url, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)', [$id, $c['id'], $title, $details, filter_var($page, FILTER_VALIDATE_URL) ? $page : '', 'open', now(), now()]);
        $rid = (int)db()->lastInsertId();
        send_mail(care_staff_email(), '[Care request] ' . $s['name'] . ': ' . $title, person_name($c) . " asked for a change on " . $s['name'] . ":\n\n$title\n" . ($details !== '' ? "\n$details\n" : '') . ($page !== '' ? "\nPage: $page\n" : '') . "\nEdit time used this month: " . care_minutes_used($id) . ' of ' . (int)$s['care_minutes'] . " minutes.\n\n" . abs_url('care-site.php?id=' . $id . '#requests') . "\n", $c['email']);
        care_task($s, 'Care request from ' . person_name($c) . ': ' . mb_strimwidth($title, 0, 80, '…'), 1);
        log_activity('contact', (int)$c['id'], 'note', 'Care request for ' . $s['name'] . ': ' . $title);
        flash('Got it. We’ll start on it within ' . care_tier($s)['reply'] . ' and email you when it’s done.'); redirect($back . '#requests');
    }
}
$now = time(); $days = care_days($id, 90);
$reqs = all("SELECT * FROM care_requests WHERE site_id = ? ORDER BY status IN ('done','declined'), id DESC LIMIT 30", [$id]);
$log = all('SELECT * FROM care_log WHERE site_id = ? AND shared = 1 ORDER BY created_at DESC, id DESC LIMIT 25', [$id]);
$inc = all('SELECT * FROM site_incidents WHERE site_id = ? AND started > ? ORDER BY started DESC', [$id, $now - 90 * 86400]);
$R = an_report($s, an_range('30d'), []); $V = array_filter($R['vitals']['all'] ?? []);
$used = care_minutes_used($id); $allow = max(1, (int)$s['care_minutes']); $bk = care_last_backup($id);
$scan = json_decode($s['scan'] ?: '', true); $psi = json_decode($s['psi'] ?: '', true); $tier = care_tier($s);
$hasVisits = !empty($R['totals']['visits']);
portal_top($s['name'] . ' care', $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal.php">← Overview</a><h1>Website care</h1><p class="muted"><?= e($s['name']) ?> · <?= e(care_url($s)) ?> · <span class="tag lime"><?= e($tier['name']) ?></span></p></div></div>
<div class="kpis">
  <div class="card kpi"><span>Right now</span><b class="<?= $s['status'] === 'down' ? 'warn' : '' ?>"><span class="dot <?= $s['status'] === 'down' ? 'bad' : 'ok' ?>"></span><?= $s['status'] === 'down' ? 'Down' : 'Online' ?></b><small><?= $s['status'] === 'down' ? 'We’ve been alerted and are on it' : ($s['last_check'] ? 'Checked ' . e(ago(date('Y-m-d H:i:s', (int)$s['last_check']))) : 'Checked every 15 minutes') ?></small></div>
  <div class="card kpi"><span>Uptime, 30 days</span><b class="num"><?= e(care_fmt_pct(care_uptime($id, $now - 30 * 86400, $now))) ?></b><small><?= $inc ? count($inc) . ' outage' . (count($inc) === 1 ? '' : 's') . ' in 90 days' : 'No outages in 90 days' ?></small></div>
  <div class="card kpi"><span>Last backup</span><b><?= $bk ? e(fmt_date($bk, 'M j')) : '—' ?></b><small>Every <?= (int)$s['backup_days'] ?> days</small></div>
  <div class="card kpi"><span>Edit time this month</span><b class="num"><?= $used ?><small> / <?= (int)$s['care_minutes'] ?> min</small></b><div class="minutes-bar" aria-hidden="true"><i style="width:<?= min(100, round($used / $allow * 100)) ?>%"></i></div></div>
</div>
<?php if (!$s['care_built'] && !$hasVisits): ?><section class="card"><div class="card-h"><h2>Getting set up</h2></div>
  <p class="muted">Monitoring and the weekly scan are already running. Two things finish the setup:</p>
  <ul class="findings"><li class="warn">Give us admin access to your <?= e(CARE_PLATFORMS[$s['care_platform']] ?? 'website') ?> so we can take backups, run updates and make your edits. We’ll email you how, or just reply to our welcome email.</li>
    <li class="warn">Add the Site Stats code so your report shows visitors and real-visitor speed. We can add it for you once we have access, or <a href="portal-stats.php?id=<?= $id ?>">copy it here</a>.</li></ul>
</section><?php endif; ?>
<section class="card"><div class="card-h"><h2>Uptime, last 90 days</h2><span class="muted small">Each bar is a day</span></div><?= care_uptime_bar($days) ?>
  <p class="muted small" style="margin-top:10px">We check your site every 15 minutes, day and night. If it stops responding, we get an alert<?= $s['notify_client'] ? ' and so do you' : '' ?>, and we fix it.</p></section>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>Security &amp; health</h2><span class="muted small"><?= $scan ? 'Checked ' . e(ago(date('Y-m-d H:i:s', (int)$scan['at']))) : 'Checked weekly' ?></span></div>
    <?php $f = care_findings($s); if (!$f): ?><p class="empty">Your first weekly scan is on its way.</p><?php else: ?><ul class="findings"><?php foreach ($f as [$k, $t]): ?><li class="<?= e($k) ?>"><?= e($t) ?></li><?php endforeach; ?>
      <?php if ($s['ssl_expires']): ?><li class="<?= care_days_left((int)$s['ssl_expires']) <= 14 ? 'warn' : 'good' ?>">Security certificate valid until <?= e(date('M j, Y', (int)$s['ssl_expires'])) ?></li><?php endif; ?>
      <?php if ($s['domain_expires']): ?><li class="<?= care_days_left((int)$s['domain_expires']) <= 30 ? 'warn' : 'good' ?>">Domain registered until <?= e(date('M j, Y', (int)$s['domain_expires'])) ?></li><?php endif; ?></ul><?php endif; ?>
  </section>
  <section class="card"><div class="card-h"><h2>Speed for your visitors</h2><span class="muted small">Last 30 days</span></div>
    <?php if (!$V): ?><p class="empty">Speed numbers appear once your site has some visits.</p><?php else: ?><div class="vitals">
      <?php foreach (['lcp' => ['Main content shows', 'Good under 2.5 s'], 'inp' => ['Reacts to taps', 'Good under 200 ms'], 'cls' => ['Layout stays put', 'Good under 0.10']] as $m => [$n, $g]): if (empty($V[$m])) continue; ?><div class="vital <?= vital_rating($m, $V[$m]['p75']) ?>"><span><?= e($n) ?></span><b class="num"><?= e(vital_fmt($m, $V[$m]['p75'])) ?></b><small class="muted"><?= e($g) ?></small></div><?php endforeach; ?></div>
      <p class="muted small">Measured from real visits to your site. <a href="portal-stats.php?id=<?= $id ?>">See your full visitor stats</a></p><?php endif; ?>
  </section>
</div>
<?php if ($psi && empty($psi['error'])): ?><section class="card"><div class="card-h"><h2>Google PageSpeed</h2><span class="muted small">Phone test of your home page, <?= e(fmt_date(date('Y-m-d', (int)$psi['at']))) ?></span></div>
  <?= care_psi_rings($psi) ?>
  <p class="muted small" style="margin-top:12px">90 and up is good. Google uses speed as one of the signals for search results. <?= care_is_plus($s) ? 'Your Care Plus tune-up each quarter works on these.' : 'Care Plus includes a speed and search tune-up every quarter.' ?></p>
</section><?php endif; ?>
<section class="card"><div class="card-h"><h2>Monthly reports</h2><span class="muted small">We email you a new one at the start of each month</span></div>
  <div class="row" style="flex-wrap:wrap;gap:8px"><?php foreach (care_report_months($s) as $m): ?><a class="btn ghost sm" href="<?= e(care_report_url($s, $m)) ?>" target="_blank" rel="noopener"><?= e(date('F Y', strtotime($m . '-01'))) ?><?= $m === date('Y-m') ? ' (so far)' : '' ?></a><?php endforeach; ?></div>
</section>
<section class="card" id="requests"><div class="card-h"><h2>Request a change</h2><span class="muted small"><?= (int)$s['care_minutes'] ?> minutes of edits a month included · started within <?= e($tier['reply']) ?></span></div>
  <?php foreach ($errors as $er): ?><p class="notice bad" role="alert"><?= e($er) ?></p><?php endforeach; ?>
  <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="request">
    <label class="f span2"><span>What would you like changed?</span><input name="title" required maxlength="200" placeholder="e.g. Update our holiday hours" value="<?= e(post('title')) ?>"></label>
    <label class="f span2"><span>Details <small class="muted">(new wording, prices, anything we need)</small></span><textarea name="details" rows="4"><?= e(post('details')) ?></textarea></label>
    <label class="f span2"><span>Which page? <small class="muted">(optional)</small></span><input name="page_url" inputmode="url" placeholder="Paste the page address" value="<?= e(post('page_url')) ?>"></label>
    <div class="span2 row between" style="flex-wrap:wrap;gap:10px"><span class="muted small">Need to send photos or files? Add them to your project and mention it here.</span><button class="btn primary" type="submit">Send request</button></div>
  </form>
  <?php if ($reqs): ?><ul class="list" style="margin-top:18px"><?php foreach ($reqs as $r): ?>
    <li><span class="grow"><b><?= e($r['title']) ?></b><small class="muted"><?= e(fmt_date($r['created_at'])) ?><?= $r['reply'] !== '' ? ' · ' . e($r['reply']) : '' ?></small></span><span class="tag <?= $r['status'] === 'done' ? 'lime' : ($r['status'] === 'declined' ? 'red' : '') ?>"><?= e(CARE_REQ_STATUSES[$r['status']]) ?></span></li>
  <?php endforeach; ?></ul><?php endif; ?>
</section>
<section class="card"><div class="card-h"><h2>Work we’ve done</h2></div>
  <?php if (!$log): ?><p class="empty">Our work will show here as we do it.</p><?php else: ?><ul class="feed"><?php foreach ($log as $l): ?>
    <li><span class="kind k-stage"></span><span class="grow"><small><?= e(CARE_KINDS[$l['kind']] ?? 'Note') ?> · <?= e(fmt_date($l['created_at'])) ?><?= $l['kind'] === 'edit' && $l['minutes'] ? ' · ' . (int)$l['minutes'] . ' min' : '' ?></small><span><?= e($l['title']) ?></span><?php if ($l['details'] !== ''): ?><span class="muted small"><?= e($l['details']) ?></span><?php endif; ?></span></li>
  <?php endforeach; ?></ul><?php endif; ?>
</section>
<?php portal_bottom(); ?>
