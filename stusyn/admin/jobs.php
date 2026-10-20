<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();

if (is_post()) {
    $id = (int)post('id');
    $to = post('status');
    if ($id && isset(JOB_STATUSES[$to])) {
        $db->prepare("UPDATE jobs SET status = ?, updated_at = ?, published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN ? ELSE published_at END WHERE id = ?")
            ->execute([$to, now(), $to, now(), $id]);
        flash('Job ' . strtolower(JOB_STATUSES[$to]) . '.');
    }
    redirect('admin/jobs.php');
}

$jobs = $db->query("SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) total,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.status = 'new') fresh
    FROM jobs j ORDER BY CASE j.status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END, j.updated_at DESC")->fetchAll();

admin_header('Jobs', 'jobs', $admin);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span><?= count(array_filter($jobs, function ($j) { return $j['status'] === 'published'; })) ?> live on the careers page</span><h1>Jobs</h1></div>
  <a class="btn btn-primary btn-sm" href="admin/job-edit.php">Post a job <?= ARROW ?></a>
</div>

<?php if (!$jobs): ?>
  <div class="panel"><p class="empty">No jobs yet. <a href="admin/job-edit.php">Post your first role</a>.</p></div>
<?php else: ?>
<div class="panel" style="padding:0">
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Role</th><th>Company</th><th>Status</th><th>Applicants</th><th>Updated</th><th style="text-align:right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr>
          <td><a href="admin/job-edit.php?id=<?= (int)$j['id'] ?>" class="who"><span><b><?= e($j['title']) ?></b><small><?= e($j['location']) ?> · <?= e($j['workplace']) ?> · <?= e($j['employment_type']) ?></small></span></a></td>
          <td><span class="tag <?= company_tone($j['company']) ?>"><?= e($j['company']) ?></span></td>
          <td><?= status_pill($j['status']) ?></td>
          <td><a href="admin/applications.php?job=<?= (int)$j['id'] ?>"><b class="mono"><?= (int)$j['total'] ?></b><?= $j['fresh'] ? ' <span class="badge">' . (int)$j['fresh'] . ' new</span>' : '' ?></a></td>
          <td class="mono"><?= e(ago($j['updated_at'])) ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-xs" href="<?= e(job_path($j['slug'])) ?>" target="_blank" rel="noopener"><?= $j['status'] === 'draft' ? 'Preview' : 'View' ?> ↗</a>
              <a class="btn btn-ghost btn-xs" href="admin/job-edit.php?id=<?= (int)$j['id'] ?>">Edit</a>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                <?php if ($j['status'] === 'published'): ?><button class="btn btn-ghost btn-xs" name="status" value="closed">Close</button>
                <?php else: ?><button class="btn btn-primary btn-xs" name="status" value="published">Publish</button><?php endif; ?>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<p class="form-note" style="margin-top:14px">Closed roles stay visible at their link with a "no longer accepting applications" note, and are removed from the careers page. Drafts are only visible to signed-in admins.</p>
<?php endif; ?>
<?php admin_footer(); ?>
