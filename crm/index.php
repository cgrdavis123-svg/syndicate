<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/recurring.php';
$user = require_user();

if (is_post() && post('action') === 'sync') { $r = sync_website(true); flash($r['message']); redirect('./'); }
if (is_post() && post('action') === 'digest') { require_once __DIR__ . '/app/digest.php'; flash(pipeline_digest_send(true) . '.'); redirect('./'); }
$sync = sync_website();
$rec = recurring_message(run_recurring());
if ($sync['imported'] || $rec) flash(trim(($sync['imported'] ? $sync['message'] : '') . ' ' . $rec));

$monthStart = date('Y-m-01');
$openValue = (float)val("SELECT COALESCE(SUM(value),0) FROM deals WHERE stage IN ('new','contacted','qualified','proposal')");
$openCount = (int)val("SELECT COUNT(*) FROM deals WHERE stage IN ('new','contacted','qualified','proposal')");
$wonMonth = (float)val("SELECT COALESCE(SUM(value),0) FROM deals WHERE stage = 'won' AND closed_at >= ?", [$monthStart]);
$unbilled = one("SELECT COALESCE(SUM(minutes),0) mins, COALESCE(SUM(minutes * rate / 60.0),0) amt FROM consultations WHERE status = 'completed' AND billable = 1 AND invoice_id IS NULL");
$outstanding = 0.0; $overdue = 0;
foreach (all("SELECT id, due_date FROM invoices WHERE status = 'sent'") as $inv) { $outstanding += invoice_total((int)$inv['id'])['total']; if ($inv['due_date'] < today()) $overdue++; }

$tasks = all("SELECT * FROM tasks WHERE done_at IS NULL AND (due_date IS NULL OR due_date <= ?) ORDER BY due_date IS NULL, due_date, priority DESC, id LIMIT 12", [date('Y-m-d', strtotime('+1 day'))]);
$byStage = [];
foreach (all("SELECT stage, COUNT(*) n, COALESCE(SUM(value),0) v FROM deals GROUP BY stage") as $r) $byStage[$r['stage']] = $r;
$maxStage = max(1, ...array_map(function ($s) use ($byStage) { return (int)($byStage[$s]['n'] ?? 0); }, OPEN_STAGES));

$months = [];
for ($i = 5; $i >= 0; $i--) $months[date('Y-m', strtotime("first day of -$i months"))] = 0.0;
foreach (all("SELECT id, substr(paid_at,1,7) m FROM invoices WHERE status = 'paid' AND paid_at >= ?", [array_key_first($months) . '-01']) as $p) {
    if (isset($months[$p['m']])) $months[$p['m']] += invoice_total((int)$p['id'])['total'];
}
$maxMonth = max(1, max($months));
$webLeads = all("SELECT d.*, c.first_name, c.last_name, c.email FROM deals d LEFT JOIN contacts c ON c.id = d.contact_id WHERE d.source LIKE 'Website%' AND d.stage = 'new' ORDER BY d.created_at DESC LIMIT 5");
$recent = all("SELECT * FROM activities ORDER BY id DESC LIMIT 8");
$upcoming = all("SELECT co.*, c.first_name, c.last_name FROM consultations co LEFT JOIN contacts c ON c.id = co.contact_id WHERE co.status = 'scheduled' AND co.date >= ? ORDER BY co.date, co.start_time LIMIT 5", [today()]);

layout_top('Dashboard', 'dashboard', $user);
$hour = (int)date('G');
?>
<?php require_once __DIR__ . '/app/backup.php'; if ($bkWarn = backup_warning()): ?><p class="notice"><?= e($bkWarn) ?> <a href="settings.php#backups">Check backups in Settings</a>.</p><?php endif; ?>
<?php require_once __DIR__ . '/app/passkey.php'; if (!tfa_on(one('SELECT totp_secret, passkeys FROM users WHERE id = ?', [$user['id']]))): ?><p class="notice">Protect the CRM with two-step sign-in: Face ID or Touch ID with a passkey, or a code from your phone. <a href="settings.php#tfa">Set it up in Settings</a> (about a minute).</p><?php endif; ?>
<div class="page-head">
  <div><p class="eyebrow"><?= e(date('l, F j')) ?></p><h1>Good <?= $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['name'])[0]) ?>.</h1></div>
  <form method="post" class="sync"><?= csrf_field() ?><input type="hidden" name="action" value="sync">
    <span class="muted small" title="<?= e($sync['message']) ?>"><span class="dot <?= $sync['ok'] ? 'ok' : 'off' ?>"></span>Website sync<?= setting('site_last_sync') ? ' · ' . e(ago(setting('site_last_sync'))) : '' ?></span>
    <button class="btn ghost sm" type="submit">Sync now</button>
  </form>
  <form method="post" class="sync"><?= csrf_field() ?><input type="hidden" name="action" value="digest">
    <button class="btn ghost sm" type="submit" title="The same summary cron sends every Monday morning">Email me this week’s summary</button>
  </form>
</div>
<?php if (!$sync['ok']): ?><p class="notice"><?= e($sync['message']) ?> Website leads will appear here once it&rsquo;s connected. See Settings.</p><?php endif; ?>

<div class="kpis">
  <a class="kpi card" href="deals.php"><span>Open pipeline</span><b><?= money_short($openValue) ?></b><small><?= $openCount ?> open deal<?= $openCount === 1 ? '' : 's' ?></small></a>
  <a class="kpi card" href="deals.php?view=list&amp;stage=won"><span>Won this month</span><b><?= money_short($wonMonth) ?></b><small><?= e(date('F')) ?></small></a>
  <a class="kpi card" href="consultations.php?filter=unbilled"><span>Unbilled consulting</span><b><?= money_short($unbilled['amt']) ?></b><small><?= hours($unbilled['mins']) ?> ready to invoice</small></a>
  <a class="kpi card" href="invoices.php?status=sent"><span>Outstanding</span><b><?= money_short($outstanding) ?></b><small class="<?= $overdue ? 'warn' : '' ?>"><?= $overdue ? $overdue . ' overdue' : 'Nothing overdue' ?></small></a>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card-h"><h2>Today</h2><a href="tasks.php" class="muted">All tasks →</a></div>
    <?php if (!$tasks): ?><p class="empty">Nothing due. Nice. <a href="tasks.php#new">Add a task</a></p><?php else: ?>
    <ul class="tasks">
      <?php foreach ($tasks as $t): $late = $t['due_date'] && $t['due_date'] < today(); ?>
        <li data-task="<?= (int)$t['id'] ?>">
          <button class="check" type="button" aria-label="Mark done"></button>
          <span class="grow"><b><?= e($t['title']) ?></b><?php if ($t['entity_type']): ?><small><?= entity_link($t['entity_type'], (int)$t['entity_id']) ?></small><?php endif; ?></span>
          <?php if ($t['priority']): ?><span class="tag red">High</span><?php endif; ?>
          <small class="<?= $late ? 'warn' : 'muted' ?>"><?= e($t['due_date'] ? due_label($t['due_date']) : 'No date') ?></small>
        </li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </section>

  <section class="card">
    <div class="card-h"><h2>Pipeline</h2><a href="deals.php" class="muted">Board →</a></div>
    <div class="stage-bars">
      <?php foreach (OPEN_STAGES as $s): $n = (int)($byStage[$s]['n'] ?? 0); ?>
        <a href="deals.php?view=list&amp;stage=<?= $s ?>" class="sb"><span><?= stage_pill($s) ?></span><span class="bar"><i class="st-<?= $s ?>" style="width:<?= round(100 * $n / $maxStage) ?>%"></i></span><b class="num"><?= $n ?></b><small class="num"><?= money_short($byStage[$s]['v'] ?? 0) ?></small></a>
      <?php endforeach; ?>
    </div>
    <div class="won-lost muted small"><span>Won: <b><?= (int)($byStage['won']['n'] ?? 0) ?></b> · <?= money_short($byStage['won']['v'] ?? 0) ?></span><span>Lost: <b><?= (int)($byStage['lost']['n'] ?? 0) ?></b></span></div>
  </section>

  <section class="card">
    <div class="card-h"><h2>New website leads</h2><a href="deals.php?view=list&amp;source=Website" class="muted">All →</a></div>
    <?php if (!$webLeads): ?><p class="empty">No new leads from stusyn.com. Contact form messages and consultation requests show up here automatically.</p><?php else: ?>
    <ul class="list">
      <?php foreach ($webLeads as $d): ?>
        <li><a href="deal.php?id=<?= (int)$d['id'] ?>"><span class="av"><?= e(initials(person_name($d))) ?></span><span class="grow"><b><?= e($d['title']) ?></b><small><?= e($d['email']) ?></small></span><?= brand_tag($d['brand']) ?><small class="muted"><?= e(ago($d['created_at'])) ?></small></a></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
  </section>

  <section class="card">
    <div class="card-h"><h2>Paid revenue</h2><span class="muted small">Last 6 months</span></div>
    <div class="months">
      <?php foreach ($months as $m => $amt): ?>
        <div class="mo" title="<?= e(date('F Y', strtotime($m . '-01'))) ?>: <?= money($amt) ?>"><span class="mo-v num"><?= $amt ? money_short($amt) : '' ?></span><span class="mo-bar"><i style="height:<?= max(2, round(100 * $amt / $maxMonth)) ?>%"></i></span><span class="mo-l"><?= e(date('M', strtotime($m . '-01'))) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-h"><h2>Upcoming consultations</h2><a href="consultation-edit.php" class="muted">+ Log one</a></div>
    <?php if (!$upcoming): ?><p class="empty">Nothing scheduled. Consultations are $<?= e(setting('default_rate')) ?>/hr.</p><?php else: ?>
    <ul class="list"><?php foreach ($upcoming as $c): ?>
      <li><a href="consultation-edit.php?id=<?= (int)$c['id'] ?>"><span class="date-chip"><b><?= e(date('j', strtotime($c['date']))) ?></b><?= e(date('M', strtotime($c['date']))) ?></span><span class="grow"><b><?= e(person_name($c)) ?></b><small><?= e($c['topic'] ?: 'Consultation') ?><?= $c['start_time'] ? ' · ' . e(date('g:i a', strtotime($c['start_time']))) : '' ?></small></span><small class="muted"><?= hours($c['minutes']) ?></small></a></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>

  <section class="card">
    <div class="card-h"><h2>Recent activity</h2></div>
    <?php if (!$recent): ?><p class="empty">Activity from deals, contacts and companies shows up here.</p><?php else: ?>
    <ul class="feed"><?php foreach ($recent as $a): ?>
      <li><span class="kind k-<?= e($a['kind']) ?>"></span><span class="grow"><small><?= entity_link($a['entity_type'], (int)$a['entity_id']) ?> · <?= e(ago($a['created_at'])) ?></small><span class="clip"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $a['body']), 0, 120, '…')) ?></span></span></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>
<?php layout_bottom(); ?>
