<?php
// Client portal home: the client's projects, what needs their attention, and their invoices.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$c = require_portal();
$projects = portal_projects($c);
$ids = array_map('intval', array_column($projects, 'id')) ?: [0];
$in = implode(',', $ids);
$todo = all("SELECT pp.*, p.name project FROM project_posts pp JOIN projects p ON p.id = pp.project_id WHERE pp.project_id IN ($in) AND pp.approval = 'pending' ORDER BY pp.id");
$recent = all("SELECT pp.*, p.name project FROM project_posts pp JOIN projects p ON p.id = pp.project_id WHERE pp.project_id IN ($in) AND pp.author = 'staff' AND pp.kind IN ('update','approval') ORDER BY pp.id DESC LIMIT 5");
$kick = all("SELECT b.project_id, p.name project FROM builds b JOIN projects p ON p.id = b.project_id WHERE b.project_id IN ($in) AND b.status IN ('waiting','ready') AND b.intake_at IS NULL");
$invoices = portal_invoices($c);
$due = array_filter($invoices, function ($i) { return $i['status'] === 'sent'; });
portal_top('Overview', $c);
?>
<div class="page-head"><div><p class="eyebrow"><?= e($c['company'] ?: 'Welcome') ?></p><h1>Hi <?= e($c['first_name'] ?: 'there') ?>.</h1></div></div>
<?php if ($todo || $due || $kick): ?>
<section class="card attn"><div class="card-h"><h2>Needs you</h2></div>
  <ul class="list">
    <?php foreach ($kick as $k): ?><li><a href="portal-kickoff.php?id=<?= (int)$k['project_id'] ?>"><span class="kind k-task"></span><span class="grow"><b>Fill in your kickoff form</b><small><?= e($k['project']) ?> · about 10 minutes, and we start right after</small></span><span class="btn primary xs">Start</span></a></li><?php endforeach; ?>
    <?php foreach ($todo as $t): ?><li><a href="portal-project.php?id=<?= (int)$t['project_id'] ?>#post<?= (int)$t['id'] ?>"><span class="kind k-email"></span><span class="grow"><b>Review and approve</b><small><?= e($t['project']) ?> · <?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $t['body']), 0, 90, '…')) ?></small></span><span class="btn primary xs">Review</span></a></li><?php endforeach; ?>
    <?php foreach ($due as $i): $tot = invoice_total((int)$i['id'])['total']; ?><li><a href="i.php?t=<?= e($i['share_token']) ?>" target="_blank" rel="noopener"><span class="kind k-task"></span><span class="grow"><b>Invoice <?= e($i['number']) ?> · <?= money($tot) ?></b><small class="<?= $i['due_date'] < today() ? 'warn' : '' ?>"><?= $i['due_date'] < today() ? 'Past due since ' : 'Due ' ?><?= e(fmt_date($i['due_date'])) ?></small></span><span class="btn <?= $i['payment_url'] ? 'primary' : 'ghost' ?> xs"><?= $i['payment_url'] ? 'Pay' : 'View' ?></span></a></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<section class="stack">
  <div class="card-h"><h2 class="h-sec">Your projects</h2></div>
  <?php if (!$projects): ?><div class="card"><p class="empty">No projects here yet. When we start working together, you’ll be able to follow along here.</p></div>
  <?php else: ?><div class="proj-grid">
    <?php foreach ($projects as $p): $next = one('SELECT title, due_date FROM project_steps WHERE project_id = ? AND done_at IS NULL ORDER BY position, id LIMIT 1', [$p['id']]); ?>
      <a class="card proj-card" href="portal-project.php?id=<?= (int)$p['id'] ?>">
        <div class="row between"><?= project_status_pill($p['status']) ?><?php if ($p['due_date'] && $p['status'] !== 'done'): ?><span class="muted small">Target <?= e(fmt_date($p['due_date'], 'M j')) ?></span><?php endif; ?></div>
        <b class="proj-name"><?= e($p['name']) ?></b>
        <div class="progress"><i style="width:<?= (int)$p['progress'] ?>%"></i></div>
        <div class="row between small"><span class="muted num"><?= (int)$p['progress'] ?>% done</span><?php if ($next): ?><span class="muted clip-1">Next: <?= e($next['title']) ?></span><?php endif; ?></div>
      </a>
    <?php endforeach; ?>
  </div><?php endif; ?>
</section>
<?php $adsAcct = $c['company_id'] ? one('SELECT * FROM ad_clients WHERE company_id = ? AND status != \'ended\'', [(int)$c['company_id']]) : null;
if ($adsAcct): $adWait = (int)val("SELECT COUNT(*) FROM ad_creatives WHERE client_id = ? AND status = 'review'", [$adsAcct['id']]); ?>
<a class="notice ad-callout" href="portal-ads.php"><b>Your ads</b> Results, campaigns<?= $adWait ? ', and <b>' . $adWait . ' ad' . ($adWait === 1 ? '' : 's') . ' to approve</b>' : ' and monthly reports' ?>, in your ads portal. <span>Open <?= ARROW ?></span></a>
<?php endif; ?>
<?php $careSites = $c['company_id'] ? all('SELECT * FROM sites WHERE company_id = ? AND care = 1 ORDER BY name', [(int)$c['company_id']]) : [];
if ($careSites): require_once __DIR__ . '/app/care.php'; ?>
<section class="stack">
  <div class="card-h"><h2 class="h-sec">Website care</h2></div>
  <div class="proj-grid">
    <?php foreach ($careSites as $cs): $openReq = (int)val("SELECT COUNT(*) FROM care_requests WHERE site_id = ? AND status IN ('open','in_progress')", [$cs['id']]); ?>
      <a class="card proj-card" href="portal-care.php?id=<?= (int)$cs['id'] ?>">
        <div class="row between"><b class="proj-name"><span class="dot <?= $cs['status'] === 'down' ? 'bad' : 'ok' ?>"></span><?= e($cs['name']) ?></b><span class="muted small"><?= $cs['status'] === 'down' ? 'Down, we’re on it' : 'Online' ?></span></div>
        <?= care_uptime_bar(care_days((int)$cs['id'], 30)) ?>
        <div class="row between small"><span><b class="num"><?= e(care_fmt_pct(care_uptime((int)$cs['id'], time() - 30 * 86400, time()))) ?></b> <span class="muted">uptime, 30 days</span></span><span class="muted"><?= care_minutes_used((int)$cs['id']) ?> of <?= (int)$cs['care_minutes'] ?> edit minutes used<?= $openReq ? " · $openReq open request" . ($openReq === 1 ? '' : 's') : '' ?></span></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php $sites = $c['company_id'] ? all('SELECT * FROM sites WHERE company_id = ? AND shared = 1 ORDER BY name', [(int)$c['company_id']]) : [];
if ($sites): require_once __DIR__ . '/app/analytics.php'; ?>
<section class="stack">
  <div class="card-h"><h2 class="h-sec">Your website</h2></div>
  <div class="proj-grid">
    <?php foreach ($sites as $s): $d = an_daily((int)$s['id'], 30); $sum = array_sum($d); $prev = array_sum(array_slice(an_daily((int)$s['id'], 60), 0, 30));
      $g = site_goals((int)$s['id']); [$w, $a] = an_where((int)$s['id'], strtotime('-29 days', strtotime('today')), strtotime('tomorrow'), [], $g); $t = an_totals($w, $a, $g); ?>
      <a class="card proj-card site-card" href="portal-stats.php?id=<?= (int)$s['id'] ?>">
        <div class="row between"><b class="proj-name"><?= e($s['name']) ?></b><span class="muted small">Last 30 days</span></div>
        <?= sparkline($d, 300, 46) ?>
        <div class="row between small"><span><b class="num"><?= an_num($sum) ?></b> <span class="muted">visitors</span> <?= an_delta($sum, $prev) ?></span><span><b class="num"><?= an_num($t['conversions']) ?></b> <span class="muted">calls, forms &amp; goals</span></span></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>Latest updates</h2></div>
    <?php if (!$recent): ?><p class="empty">Nothing yet.</p><?php else: ?><ul class="feed"><?php foreach ($recent as $r): ?>
      <li><span class="kind <?= $r['kind'] === 'approval' ? 'k-email' : 'k-stage' ?>"></span><span class="grow"><small><a href="portal-project.php?id=<?= (int)$r['project_id'] ?>#post<?= (int)$r['id'] ?>"><?= e($r['project']) ?></a> · <?= e(ago($r['created_at'])) ?></small><span class="clip"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $r['body'] ?: 'Shared files'), 0, 160, '…')) ?></span></span></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
  <section class="card"><div class="card-h"><h2>Invoices</h2></div>
    <?php if (!$invoices): ?><p class="empty">No invoices yet.</p><?php else: ?><ul class="list"><?php foreach ($invoices as $i): ?>
      <li><a href="i.php?t=<?= e($i['share_token']) ?>" target="_blank" rel="noopener"><span class="grow"><b><?= e($i['number']) ?></b><small><?= e(fmt_date($i['issue_date'])) ?></small></span><?= pill('inv', $i['status'] === 'sent' && $i['due_date'] < today() ? 'overdue' : $i['status'], $i['status'] === 'paid' ? 'Paid' : ($i['due_date'] < today() ? 'Past due' : 'Due ' . fmt_date($i['due_date'], 'M j'))) ?><span class="num"><?= money(invoice_total((int)$i['id'])['total']) ?></span></a></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>
<?php portal_bottom(); ?>
