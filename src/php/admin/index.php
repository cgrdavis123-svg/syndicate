<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();

$openJobs = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE status = 'published'")->fetchColumn();
$total = (int)$db->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$new = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status = 'new'")->fetchColumn();
$st = $db->prepare('SELECT COUNT(*) FROM applications WHERE created_at >= ?');
$st->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);
$week = (int)$st->fetchColumn();
$unread = (int)$db->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();

$byStatus = array_fill_keys(array_keys(APP_STATUSES), 0);
foreach ($db->query('SELECT status, COUNT(*) c FROM applications GROUP BY status') as $r) $byStatus[$r['status']] = (int)$r['c'];

// Applications per day, last 30 days
$days = [];
for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
$st = $db->prepare('SELECT substr(created_at,1,10) d, COUNT(*) c FROM applications WHERE created_at >= ? GROUP BY d');
$st->execute([array_key_first($days) . ' 00:00:00']);
foreach ($st as $r) if (isset($days[$r['d']])) $days[$r['d']] = (int)$r['c'];
$maxDay = max(1, max($days));

$recent = $db->query('SELECT id, name, job_title, status, created_at FROM applications ORDER BY id DESC LIMIT 8')->fetchAll();
$jobs = $db->query("SELECT j.id, j.title, j.company, j.status, j.slug,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) total,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'new') fresh
    FROM jobs j WHERE j.status != 'closed' ORDER BY j.status = 'published' DESC, total DESC LIMIT 8")->fetchAll();
$openApps = (int)$db->query('SELECT COUNT(*) FROM applications WHERE job_id IS NULL')->fetchColumn();

admin_header('Dashboard', 'dashboard', $admin);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span><?= e(date('l, F j')) ?></span><h1>Hiring dashboard</h1></div>
  <div class="cta-row"><a class="btn btn-ghost btn-sm" href="admin/applications.php?status=new">Review new applicants</a><a class="btn btn-primary btn-sm" href="admin/job-edit.php">Post a job <?= ARROW ?></a></div>
</div>

<div class="kpis">
  <a class="kpi spot" href="admin/applications.php?status=new"><span>New applicants</span><b><?= $new ?></b><small>Waiting for review</small></a>
  <a class="kpi spot" href="admin/applications.php"><span>Last 7 days</span><b><?= $week ?></b><small><?= $total ?> applicants all time</small></a>
  <a class="kpi spot" href="admin/jobs.php"><span>Open roles</span><b><?= $openJobs ?></b><small>Live on the careers page</small></a>
  <a class="kpi spot" href="admin/messages.php"><span>Unread messages</span><b><?= $unread ?></b><small>From the contact form</small></a>
</div>

<div class="adm-grid">
  <section class="panel">
    <div class="panel-h"><h2>Applications, last 30 days</h2><span class="muted mono" style="font-size:12px">peak <?= $maxDay ?>/day</span></div>
    <svg class="bars" viewBox="0 0 300 80" preserveAspectRatio="none" role="img" aria-label="Applications per day for the last 30 days">
      <line x1="0" y1="79.5" x2="300" y2="79.5" stroke="var(--line-2)"/>
      <?php $i = 0; foreach ($days as $d => $c): $h = $c ? max(3, 72 * $c / $maxDay) : 1; ?>
        <rect x="<?= $i * 10 + 1.5 ?>" y="<?= 79 - $h ?>" width="7" height="<?= $h ?>" rx="1.5" fill="<?= $c ? 'var(--accent)' : 'var(--line-2)' ?>"><title><?= e(date('M j', strtotime($d))) ?>: <?= $c ?></title></rect>
      <?php $i++; endforeach; ?>
    </svg>
    <div class="bars-x mono"><span><?= e(date('M j', strtotime(array_key_first($days)))) ?></span><span>Today</span></div>
  </section>

  <section class="panel">
    <div class="panel-h"><h2>Pipeline</h2><a class="muted" href="admin/applications.php">All →</a></div>
    <div class="pipe">
      <?php foreach (APP_STATUSES as $k => $label): ?>
        <a href="admin/applications.php?status=<?= e($k) ?>" class="pipe-row">
          <span><?= status_pill($k) ?></span>
          <span class="pipe-bar"><i class="s-<?= e($k) ?>" style="width:<?= $total ? round(100 * $byStatus[$k] / $total) : 0 ?>%"></i></span>
          <b class="mono"><?= $byStatus[$k] ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <div class="panel-h"><h2>Latest applicants</h2><a class="muted" href="admin/applications.php">All →</a></div>
    <?php if (!$recent): ?>
      <p class="empty">No applications yet. Once your careers page is live, new applicants appear here and are emailed to <?= e(config('careers_to')) ?>.</p>
    <?php else: ?>
    <ul class="list">
      <?php foreach ($recent as $r): ?>
        <li><a href="admin/application.php?id=<?= (int)$r['id'] ?>">
          <span class="av"><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
          <span class="grow"><b><?= e($r['name']) ?></b><small><?= e($r['job_title']) ?></small></span>
          <?= status_pill($r['status']) ?><small class="muted"><?= e(ago($r['created_at'])) ?></small>
        </a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-h"><h2>Roles</h2><a class="muted" href="admin/jobs.php">Manage →</a></div>
    <ul class="list">
      <?php foreach ($jobs as $j): ?>
        <li><a href="admin/applications.php?job=<?= (int)$j['id'] ?>">
          <span class="grow"><b><?= e($j['title']) ?></b><small><?= e($j['company']) ?></small></span>
          <?php if ($j['status'] !== 'published'): ?><?= status_pill($j['status']) ?><?php endif; ?>
          <?php if ($j['fresh']): ?><span class="badge"><?= (int)$j['fresh'] ?> new</span><?php endif; ?>
          <b class="mono"><?= (int)$j['total'] ?></b>
        </a></li>
      <?php endforeach; ?>
      <li><a href="admin/applications.php?job=open">
        <span class="grow"><b>Open applications</b><small>Not tied to a role</small></span>
        <b class="mono"><?= $openApps ?></b>
      </a></li>
    </ul>
  </section>
</div>
<?php admin_footer(); ?>
