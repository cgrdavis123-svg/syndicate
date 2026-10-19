<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$user = require_user();
$status = get('status'); $q = get('q');
$where = []; $args = [];
if (isset(PROJECT_STATUSES[$status])) { $where[] = 'p.status = ?'; $args[] = $status; } elseif ($status !== 'all') $where[] = "p.status != 'done'";
if ($q !== '') { $where[] = '(p.name LIKE ? OR co.name LIKE ?)'; array_push($args, "%$q%", "%$q%"); }
$rows = all('SELECT p.*, co.name company, c.first_name, c.last_name, c.email,
        (SELECT COUNT(*) FROM project_posts pp WHERE pp.project_id = p.id AND pp.approval = \'pending\') pending,
        (SELECT COUNT(*) FROM project_posts pp WHERE pp.project_id = p.id AND pp.author = \'client\' AND pp.created_at > COALESCE((SELECT MAX(created_at) FROM project_posts s WHERE s.project_id = p.id AND s.author = \'staff\'), \'\')) waiting,
        (SELECT MIN(due_date) FROM project_steps s WHERE s.project_id = p.id AND s.done_at IS NULL) next_due
    FROM projects p LEFT JOIN companies co ON co.id = p.company_id LEFT JOIN contacts c ON c.id = p.contact_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY p.status = 'done', p.updated_at DESC", $args);
$counts = []; foreach (all('SELECT status, COUNT(*) n FROM projects GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
layout_top('Projects', 'projects', $user);
?>
<div class="page-head">
  <div><p class="eyebrow"><?= count($rows) ?> project<?= count($rows) === 1 ? '' : 's' ?> · shared with clients in their portal</p><h1>Projects</h1></div>
  <a class="btn primary sm" href="project-edit.php">+ New project</a>
</div>
<form class="filters" method="get">
  <nav class="seg-links" aria-label="Status">
    <a href="projects.php"<?= $status === '' ? ' aria-current="page"' : '' ?>>Open</a>
    <?php foreach (PROJECT_STATUSES as $k => $l): ?><a href="projects.php?status=<?= $k ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?><?= !empty($counts[$k]) ? ' <span class="dim num">' . $counts[$k] . '</span>' : '' ?></a><?php endforeach; ?>
    <a href="projects.php?status=all"<?= $status === 'all' ? ' aria-current="page"' : '' ?>>All</a>
  </nav>
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Filter projects" aria-label="Filter projects">
</form>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= $q !== '' || $status !== '' ? 'No projects match.' : 'No projects yet. Create one for a client and it appears in their portal: progress, updates, files and approvals in one place.' ?> <a href="project-edit.php">New project</a></p></div>
<?php else: ?>
<div class="proj-grid">
  <?php foreach ($rows as $p): ?>
    <a class="card proj-card" href="project.php?id=<?= (int)$p['id'] ?>">
      <div class="row between"><?= project_status_pill($p['status']) ?><?= brand_tag($p['brand']) ?></div>
      <div><b class="proj-name"><?= e($p['name']) ?></b><small class="muted"><?= e($p['company'] ?: person_name($p)) ?></small></div>
      <div class="progress" aria-label="<?= (int)$p['progress'] ?>% done"><i style="width:<?= (int)$p['progress'] ?>%"></i></div>
      <div class="row between small"><span class="muted num"><?= (int)$p['progress'] ?>%</span>
        <span class="row"><?php if ($p['waiting']): ?><span class="tag lime">Client replied</span><?php endif; ?><?php if ($p['pending']): ?><span class="tag blue">Awaiting approval</span><?php endif; ?><?php if (!$p['shared']): ?><span class="tag">Hidden</span><?php endif; ?>
        <?php if ($p['due_date']): ?><span class="<?= $p['due_date'] < today() && $p['status'] !== 'done' ? 'warn' : 'muted' ?>">Due <?= e(fmt_date($p['due_date'], 'M j')) ?></span><?php endif; ?></span></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_bottom(); ?>
