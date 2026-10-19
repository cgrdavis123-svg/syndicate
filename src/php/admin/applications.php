<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();

// Bulk status change
if (is_post() && post('action') === 'bulk') {
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $to = post('to');
    if ($ids && isset(APP_STATUSES[$to])) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE applications SET status = ?, updated_at = ? WHERE id IN ($in)")->execute(array_merge([$to, now()], $ids));
        flash(count($ids) . ' applicant' . (count($ids) === 1 ? '' : 's') . ' moved to ' . APP_STATUSES[$to] . '.');
    }
    redirect('admin/applications.php?' . http_build_query(array_filter(['status' => get('status'), 'job' => get('job'), 'q' => get('q')])));
}

$status = get('status');
$jobF = get('job');
$q = get('q');

$where = [];
$args = [];
if (isset(APP_STATUSES[$status])) { $where[] = 'a.status = ?'; $args[] = $status; }
if ($jobF === 'open') $where[] = 'a.job_id IS NULL';
elseif ($jobF !== '') { $where[] = 'a.job_id = ?'; $args[] = (int)$jobF; }
if ($q !== '') {
    $where[] = '(a.name LIKE ? OR a.email LIKE ? OR a.location LIKE ? OR a.job_title LIKE ? OR a.why LIKE ?)';
    array_push($args, ...array_fill(0, 5, '%' . $q . '%'));
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$page = max(1, (int)get('page', '1'));
$per = 50;
$st = $db->prepare("SELECT COUNT(*) FROM applications a $sqlWhere");
$st->execute($args);
$count = (int)$st->fetchColumn();
$st = $db->prepare("SELECT a.* FROM applications a $sqlWhere ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();

$jobs = $db->query("SELECT id, title, status FROM jobs ORDER BY status = 'published' DESC, title")->fetchAll();
$counts = ['' => (int)$db->query('SELECT COUNT(*) FROM applications')->fetchColumn()];
foreach ($db->query('SELECT status, COUNT(*) c FROM applications GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
$qs = function (array $over) use ($status, $jobF, $q) { return http_build_query(array_filter(array_merge(['status' => $status, 'job' => $jobF, 'q' => $q], $over), 'strlen')); };

admin_header('Applicants', 'applications', $admin);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span><?= $count ?> applicant<?= $count === 1 ? '' : 's' ?></span><h1>Applicants</h1></div>
  <a class="btn btn-ghost btn-sm" href="admin/export.php?<?= e($qs([])) ?>">Export CSV</a>
</div>

<div class="tabs" role="tablist">
  <a href="admin/applications.php?<?= e($qs(['status' => ''])) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>All <span><?= $counts[''] ?></span></a>
  <?php foreach (APP_STATUSES as $k => $label): ?>
    <a href="admin/applications.php?<?= e($qs(['status' => $k])) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span><?= $counts[$k] ?? 0 ?></span></a>
  <?php endforeach; ?>
</div>

<form class="filters" method="get" action="admin/applications.php">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <select name="job" aria-label="Filter by role" onchange="this.form.submit()">
    <option value="">All roles</option>
    <option value="open"<?= $jobF === 'open' ? ' selected' : '' ?>>Open applications</option>
    <?php foreach ($jobs as $j): ?><option value="<?= (int)$j['id'] ?>"<?= $jobF === (string)$j['id'] ? ' selected' : '' ?>><?= e($j['title']) ?><?= $j['status'] !== 'published' ? ' (' . e($j['status']) . ')' : '' ?></option><?php endforeach; ?>
  </select>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, location…" aria-label="Search applicants">
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
  <?php if ($q !== '' || $jobF !== ''): ?><a class="muted" href="admin/applications.php?<?= e(http_build_query(array_filter(['status' => $status]))) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="panel"><p class="empty"><?= $counts[''] ? 'No applicants match these filters.' : 'No applications yet. New applicants will show up here as soon as they apply.' ?></p></div>
<?php else: ?>
<form method="post" class="panel" style="padding:0">
  <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
  <div class="bulk" id="bulkBar" hidden>
    <span id="bulkCount">0 selected</span>
    <select name="to" aria-label="Move selected to"><?php foreach (APP_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
    <button class="btn btn-primary btn-sm" type="submit">Move</button>
  </div>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr>
        <th style="width:36px"><input type="checkbox" id="checkAll" aria-label="Select all"></th>
        <th>Applicant</th><th>Role</th><th>Status</th><th>Rating</th><th>Applied</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr data-href="admin/application.php?id=<?= (int)$r['id'] ?>">
          <td><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select <?= e($r['name']) ?>"></td>
          <td><a href="admin/application.php?id=<?= (int)$r['id'] ?>" class="who"><span class="av"><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?></span><span><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?><?= $r['location'] ? ' · ' . e($r['location']) : '' ?></small></span></a></td>
          <td><?= e($r['job_title']) ?></td>
          <td><?= status_pill($r['status']) ?></td>
          <td class="stars-s" aria-label="<?= (int)$r['rating'] ?> of 5"><?= str_repeat('★', (int)$r['rating']) ?><span><?= str_repeat('★', 5 - (int)$r['rating']) ?></span></td>
          <td class="mono" title="<?= e($r['created_at']) ?>"><?= e(ago($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</form>
<?php if ($count > $per): ?>
  <div class="pager">
    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="admin/applications.php?<?= e($qs(['page' => (string)($page - 1)])) ?>">← Newer</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= (int)ceil($count / $per) ?></span>
    <?php if ($page * $per < $count): ?><a class="btn btn-ghost btn-sm" href="admin/applications.php?<?= e($qs(['page' => (string)($page + 1)])) ?>">Older →</a><?php endif; ?>
  </div>
<?php endif; ?>
<?php endif; ?>
<?php admin_footer(); ?>
