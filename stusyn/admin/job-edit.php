<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();

$id = (int)get('id');
$fields = ['title', 'company', 'team', 'location', 'workplace', 'employment_type', 'compensation', 'summary', 'about', 'responsibilities', 'requirements', 'nice_to_have', 'status', 'slug'];
$job = array_fill_keys($fields, '');
$job['company'] = COMPANIES[0]; $job['workplace'] = 'Hybrid'; $job['employment_type'] = 'Full-time'; $job['status'] = 'draft'; $job['location'] = 'Spartanburg, SC';
$applicants = 0;

if ($id) {
    $st = $db->prepare('SELECT * FROM jobs WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash('That job no longer exists.'); redirect('admin/jobs.php'); }
    $job = $row;
    $st = $db->prepare('SELECT COUNT(*) FROM applications WHERE job_id = ?');
    $st->execute([$id]);
    $applicants = (int)$st->fetchColumn();
}

$errors = [];
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        if ($applicants > 0) {
            flash("This role has applicants, so it was closed instead of deleted.");
            $db->prepare("UPDATE jobs SET status = 'closed', updated_at = ? WHERE id = ?")->execute([now(), $id]);
        } else {
            $db->prepare('DELETE FROM jobs WHERE id = ?')->execute([$id]);
            flash('Job deleted.');
        }
        redirect('admin/jobs.php');
    }

    foreach ($fields as $f) $job[$f] = post($f);
    if (mb_strlen($job['title']) < 3) $errors['title'] = 'Add a job title.';
    if (!in_array($job['company'], COMPANIES, true)) $errors['company'] = 'Choose a company.';
    if (!in_array($job['workplace'], WORKPLACES, true)) $job['workplace'] = 'On-site';
    if (!in_array($job['employment_type'], EMPLOYMENT, true)) $job['employment_type'] = 'Full-time';
    if (!isset(JOB_STATUSES[$job['status']])) $job['status'] = 'draft';
    if (mb_strlen($job['summary']) < 10) $errors['summary'] = 'Add a one-line summary for the careers page.';
    if (mb_strlen($job['about']) < 20) $errors['about'] = 'Describe the role in a few sentences.';

    $slug = slugify($job['slug'] !== '' ? $job['slug'] : $job['title']);
    if ($slug === 'open-application') $slug .= '-role';
    $base = $slug; $n = 2;
    while (true) {
        $st = $db->prepare('SELECT id FROM jobs WHERE slug = ? AND id != ?');
        $st->execute([$slug, $id]);
        if (!$st->fetchColumn()) break;
        $slug = $base . '-' . $n++;
    }
    $job['slug'] = $slug;

    if (!$errors) {
        $vals = array_map(function ($f) use ($job) { return $job[$f]; }, $fields);
        if ($id) {
            $set = implode(', ', array_map(function ($f) { return "$f = ?"; }, $fields));
            $db->prepare("UPDATE jobs SET $set, updated_at = ?, published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN ? ELSE published_at END WHERE id = ?")
                ->execute(array_merge($vals, [now(), $job['status'], now(), $id]));
            flash('Job saved.');
        } else {
            $cols = implode(', ', $fields);
            $qs = implode(', ', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO jobs ($cols, published_at, created_at, updated_at) VALUES ($qs, ?, ?, ?)")
                ->execute(array_merge($vals, [$job['status'] === 'published' ? now() : null, now(), now()]));
            $id = (int)$db->lastInsertId();
            flash($job['status'] === 'published' ? 'Job posted. It\'s live on the careers page.' : 'Draft saved.');
        }
        redirect('admin/job-edit.php?id=' . $id);
    }
}

$err = function ($k) use ($errors) { return isset($errors[$k]) ? '<p class="err">' . e($errors[$k]) . '</p>' : ''; };
admin_header($id ? 'Edit ' . $job['title'] : 'Post a job', 'jobs', $admin);
?>
<div class="adm-head">
  <div><a class="muted" href="admin/jobs.php" style="font-size:14px">← Jobs</a><h1><?= $id ? e($job['title']) : 'Post a job' ?></h1>
    <?php if ($id): ?><p class="muted" style="margin:6px 0 0"><?= status_pill($job['status']) ?> · <a href="admin/applications.php?job=<?= $id ?>"><?= $applicants ?> applicant<?= $applicants === 1 ? '' : 's' ?></a></p><?php endif; ?>
  </div>
  <?php if ($id): ?><a class="btn btn-ghost btn-sm" href="<?= e(job_path($job['slug'])) ?>" target="_blank" rel="noopener"><?= $job['status'] === 'draft' ? 'Preview' : 'View live' ?> ↗</a><?php endif; ?>
</div>

<?php if ($errors): ?><p class="notice warn" role="alert">Please fix the highlighted fields.</p><?php endif; ?>

<form method="post" class="job-form">
  <?= csrf_field() ?>
  <div class="panel form-grid">
    <div class="f span2"><label for="j-title">Job title *</label><input id="j-title" name="title" required value="<?= e($job['title']) ?>" placeholder="e.g. Social Media Manager"><?= $err('title') ?></div>
    <div class="f"><label for="j-company">Company *</label><select id="j-company" name="company"><?php foreach (COMPANIES as $c): ?><option<?= $job['company'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="j-team">Team</label><input id="j-team" name="team" value="<?= e($job['team']) ?>" placeholder="e.g. Social, Content, Growth"></div>
    <div class="f"><label for="j-location">Location</label><input id="j-location" name="location" value="<?= e($job['location']) ?>" placeholder="Spartanburg, SC or United States"></div>
    <div class="f"><label for="j-workplace">Workplace</label><select id="j-workplace" name="workplace"><?php foreach (WORKPLACES as $w): ?><option<?= $job['workplace'] === $w ? ' selected' : '' ?>><?= e($w) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="j-type">Employment type</label><select id="j-type" name="employment_type"><?php foreach (EMPLOYMENT as $t): ?><option<?= $job['employment_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="j-comp">Pay <span class="muted">(optional, shown publicly)</span></label><input id="j-comp" name="compensation" value="<?= e($job['compensation']) ?>" placeholder="e.g. $55k–$70k + bonus"></div>
    <div class="f span2"><label for="j-summary">One-line summary *</label><input id="j-summary" name="summary" maxlength="200" value="<?= e($job['summary']) ?>" placeholder="Shown in the job list on the careers page"><?= $err('summary') ?></div>
  </div>

  <div class="panel form-grid">
    <div class="f span2"><label for="j-about">About the role *</label><textarea id="j-about" name="about" rows="6" placeholder="A few short paragraphs. Leave a blank line between paragraphs."><?= e($job['about']) ?></textarea><?= $err('about') ?></div>
    <div class="f span2"><label for="j-resp">What you&rsquo;ll do <span class="muted">(one per line)</span></label><textarea id="j-resp" name="responsibilities" rows="6"><?= e($job['responsibilities']) ?></textarea></div>
    <div class="f span2"><label for="j-req">What you&rsquo;ll bring <span class="muted">(one per line)</span></label><textarea id="j-req" name="requirements" rows="6"><?= e($job['requirements']) ?></textarea></div>
    <div class="f span2"><label for="j-nice">Nice to have <span class="muted">(one per line, optional)</span></label><textarea id="j-nice" name="nice_to_have" rows="3"><?= e($job['nice_to_have']) ?></textarea></div>
  </div>

  <div class="panel form-grid">
    <div class="f"><label for="j-status">Status</label>
      <select id="j-status" name="status"><?php foreach (JOB_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $job['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <p class="form-note" style="margin-top:8px">Published roles appear on the careers page. Drafts are only visible to admins.</p>
    </div>
    <div class="f"><label for="j-slug">Link</label>
      <div class="slug"><span><?= e(rtrim(config('site_url'), '/') . url(config('pretty_urls') ? 'careers/' : 'job.php?slug=')) ?></span><input id="j-slug" name="slug" value="<?= e($job['slug']) ?>" placeholder="made from the title"></div>
    </div>
  </div>

  <div class="save-bar">
    <?php if ($id): ?>
      <button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="<?= $applicants ? 'This role has applicants, so it will be closed instead of deleted. Continue?' : 'Delete this job? This can\'t be undone.' ?>"><?= $applicants ? 'Close role' : 'Delete job' ?></button>
    <?php else: ?><span></span><?php endif; ?>
    <div class="cta-row"><a class="btn btn-ghost btn-sm" href="admin/jobs.php">Cancel</a><button class="btn btn-primary btn-sm" type="submit" name="action" value="save"><?= $id ? 'Save changes' : 'Save job' ?> <?= ARROW ?></button></div>
  </div>
</form>
<?php admin_footer(); ?>
