<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();

$id = (int)get('id');
$st = $db->prepare('SELECT a.*, j.slug job_slug, j.company job_company FROM applications a LEFT JOIN jobs j ON j.id = a.job_id WHERE a.id = ?');
$st->execute([$id]);
$app = $st->fetch();
if (!$app) { flash('That applicant no longer exists.'); redirect('admin/applications.php'); }

if (is_post()) {
    if (post('action') === 'delete') {
        if ($app['resume_file'] && is_file(STORAGE . '/resumes/' . basename($app['resume_file']))) @unlink(STORAGE . '/resumes/' . basename($app['resume_file']));
        $db->prepare('DELETE FROM applications WHERE id = ?')->execute([$id]);
        flash('Applicant deleted, including their resume.');
        redirect('admin/applications.php');
    }
    $status = isset(APP_STATUSES[post('status')]) ? post('status') : $app['status'];
    $rating = max(0, min(5, (int)post('rating')));
    $db->prepare('UPDATE applications SET status = ?, rating = ?, notes = ?, updated_at = ? WHERE id = ?')
        ->execute([$status, $rating, mb_substr(post('notes'), 0, 10000), now(), $id]);
    flash('Saved.');
    redirect('admin/application.php?id=' . $id);
}

// Opening a new application moves it to Reviewing.
if ($app['status'] === 'new') {
    $db->prepare("UPDATE applications SET status = 'reviewing', updated_at = ? WHERE id = ?")->execute([now(), $id]);
    $app['status'] = 'reviewing';
}

$prev = $db->prepare('SELECT id FROM applications WHERE id > ? ORDER BY id ASC LIMIT 1'); $prev->execute([$id]); $prev = $prev->fetchColumn();
$next = $db->prepare('SELECT id FROM applications WHERE id < ? ORDER BY id DESC LIMIT 1'); $next->execute([$id]); $next = $next->fetchColumn();
$others = $db->prepare('SELECT id, job_title, status, created_at FROM applications WHERE email = ? COLLATE NOCASE AND id != ? ORDER BY id DESC');
$others->execute([$app['email'], $id]);
$others = $others->fetchAll();

$first = explode(' ', $app['name'])[0];
$mailto = 'mailto:' . rawurlencode($app['email']) . '?subject=' . rawurlencode('Your application for ' . $app['job_title'] . ' at Syndicate') . '&body=' . rawurlencode("Hi $first,\n\n");

admin_header($app['name'], 'applications', $admin);
?>
<div class="adm-head">
  <div>
    <a class="muted" href="admin/applications.php" style="font-size:14px">← Applicants</a>
    <h1 style="display:flex;align-items:center;gap:14px;flex-wrap:wrap"><?= e($app['name']) ?> <?= status_pill($app['status']) ?></h1>
    <p class="muted" style="margin:6px 0 0">Applied for <b style="color:var(--text)"><?= e($app['job_title']) ?></b> · <?= e(fmt_date($app['created_at'], 'M j, Y \a\t g:i a')) ?></p>
  </div>
  <div class="cta-row">
    <?php if ($prev): ?><a class="btn btn-ghost btn-sm" href="admin/application.php?id=<?= (int)$prev ?>" title="Newer applicant">←</a><?php endif; ?>
    <?php if ($next): ?><a class="btn btn-ghost btn-sm" href="admin/application.php?id=<?= (int)$next ?>" title="Older applicant">→</a><?php endif; ?>
    <a class="btn btn-primary btn-sm" href="<?= e($mailto) ?>">Email <?= e($first) ?> <?= ARROW ?></a>
  </div>
</div>

<div class="adm-grid detail">
  <div style="display:grid;gap:16px;align-content:start">
    <section class="panel">
      <div class="panel-h"><h2>Contact</h2></div>
      <dl class="facts">
        <div><dt>Email</dt><dd><a href="mailto:<?= e($app['email']) ?>"><?= e($app['email']) ?></a></dd></div>
        <?php if ($app['phone']): ?><div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $app['phone'])) ?>"><?= e($app['phone']) ?></a></dd></div><?php endif; ?>
        <?php if ($app['location']): ?><div><dt>Location</dt><dd><?= e($app['location']) ?></dd></div><?php endif; ?>
        <?php if ($app['linkedin']): ?><div><dt>LinkedIn</dt><dd><a href="<?= e($app['linkedin']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://(www\.)?#', '', $app['linkedin'])) ?> ↗</a></dd></div><?php endif; ?>
        <?php if ($app['portfolio']): ?><div><dt>Portfolio</dt><dd><a href="<?= e($app['portfolio']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://(www\.)?#', '', $app['portfolio'])) ?> ↗</a></dd></div><?php endif; ?>
        <div><dt>US work authorization</dt><dd><?= e($app['work_authorized'] ?: '—') ?></dd></div>
        <div><dt>Heard about us</dt><dd><?= e($app['source'] ?: '—') ?></dd></div>
      </dl>
    </section>

    <section class="panel">
      <div class="panel-h"><h2>In their words</h2></div>
      <div class="quote"><?= nl2br(e($app['why'])) ?></div>
    </section>

    <?php if ($app['resume_file']): ?>
    <section class="panel resume-panel">
      <div class="file-ico"><?= e(strtoupper(pathinfo($app['resume_file'], PATHINFO_EXTENSION))) ?></div>
      <div class="grow"><b><?= e($app['resume_name'] ?: 'Resume') ?></b><small class="muted">Uploaded with the application</small></div>
      <a class="btn btn-ghost btn-sm" href="admin/resume.php?id=<?= (int)$id ?>&amp;view=1" target="_blank" rel="noopener">Open</a>
      <a class="btn btn-primary btn-sm" href="admin/resume.php?id=<?= (int)$id ?>">Download</a>
    </section>
    <?php endif; ?>
  </div>

  <div style="display:grid;gap:16px;align-content:start">
    <form class="panel" method="post">
      <?= csrf_field() ?>
      <div class="panel-h"><h2>Review</h2></div>
      <div class="f"><label for="r-status">Stage</label>
        <select id="r-status" name="status"><?php foreach (APP_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= $app['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </div>
      <div class="f"><span class="lbl">Rating</span>
        <div class="stars" role="radiogroup" aria-label="Rating">
          <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>"<?= (int)$app['rating'] === $i ? ' checked' : '' ?>><label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label><?php endfor; ?>
          <input type="radio" name="rating" id="star0" value="0"<?= (int)$app['rating'] === 0 ? ' checked' : '' ?>><label for="star0" class="clear">Clear</label>
        </div>
      </div>
      <div class="f"><label for="r-notes">Private notes</label><textarea id="r-notes" name="notes" placeholder="Interview notes, next steps, red flags…"><?= e($app['notes']) ?></textarea></div>
      <button class="btn btn-primary btn-sm" type="submit">Save review</button>
    </form>

    <?php if ($others): ?>
    <section class="panel">
      <div class="panel-h"><h2>Other applications</h2></div>
      <ul class="list"><?php foreach ($others as $o): ?>
        <li><a href="admin/application.php?id=<?= (int)$o['id'] ?>"><span class="grow"><b><?= e($o['job_title']) ?></b><small><?= e(ago($o['created_at'])) ?></small></span><?= status_pill($o['status']) ?></a></li>
      <?php endforeach; ?></ul>
    </section>
    <?php endif; ?>

    <form method="post" class="danger-zone" data-confirm="Delete <?= e($app['name']) ?>'s application and resume? This can't be undone.">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="link-danger" type="submit">Delete applicant</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
