<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$user = require_user();
$id = (int)get('id');
$p = one('SELECT p.*, co.name company, c.first_name, c.last_name, c.email FROM projects p LEFT JOIN companies co ON co.id = p.company_id LEFT JOIN contacts c ON c.id = p.contact_id WHERE p.id = ?', [$id]);
if (!$p) { flash('That project no longer exists.'); redirect('projects.php'); }
$back = 'project.php?id=' . $id;
$touch = function () use ($id) { q('UPDATE projects SET updated_at = ? WHERE id = ?', [now(), $id]); };
$errors = [];
if (is_post()) {
    $a = post('action');
    if ($a === 'post') {
        $kind = post('kind') === 'approval' ? 'approval' : 'update';
        $body = mb_substr(rtrim((string)($_POST['body'] ?? '')), 0, 10000);
        $hasFiles = array_filter($_FILES['files']['name'] ?? [], 'strlen');
        if (trim($body) === '' && !$hasFiles) $errors[] = 'Write something or attach a file.';
        else {
            q('INSERT INTO project_posts (project_id, author, kind, body, approval, created_at) VALUES (?,?,?,?,?,?)', [$id, 'staff', $kind, $body, $kind === 'approval' ? 'pending' : '', now()]);
            $postId = (int)db()->lastInsertId();
            [$n, $errors] = save_project_files($id, 'files', 'staff', null, $postId);
            if (post('progress') !== '' && (int)post('progress') !== (int)$p['progress']) q('UPDATE projects SET progress = ? WHERE id = ?', [max(0, min(100, (int)post('progress'))), $id]);
            $touch();
            $sent = 0;
            if (post('notify') === '1' && $p['shared']) $sent = notify_clients($p, $kind === 'approval' ? 'Ready for your approval' : 'New update', ($kind === 'approval' ? "We have something ready for you to review and approve:\n\n" : '') . ($body !== '' ? $body : 'We added ' . $n . ' file' . ($n === 1 ? '' : 's') . ' for you.'));
            if ($p['contact_id']) log_activity('contact', (int)$p['contact_id'], 'note', 'Portal · ' . $p['name'] . ': posted ' . ($kind === 'approval' ? 'an approval request' : 'an update') . '.' . ($body !== '' ? "\n\n" . mb_strimwidth($body, 0, 1500, '…') : ''));
            if ($errors) { flash('Posted, but some files weren’t: ' . implode(' ', $errors)); redirect($back); }
            else { flash(($kind === 'approval' ? 'Sent for approval.' : 'Posted.') . ($sent ? ' Emailed ' . $sent . ' ' . ($sent === 1 ? 'person' : 'people') . '.' : '')); redirect($back); }
        }
    }
    if ($a === 'files') { [$n, $errors] = save_project_files($id, 'files', 'staff', null); if ($n) $touch(); if (!$errors) { flash($n . ' file' . ($n === 1 ? '' : 's') . ' added.'); redirect($back . '#files'); } }
    if ($a === 'file_delete' && ($f = one('SELECT * FROM project_files WHERE id = ? AND project_id = ?', [(int)post('file'), $id]))) { @unlink(STORAGE . '/portal/' . $f['path']); q('DELETE FROM project_files WHERE id = ?', [$f['id']]); flash('File removed.'); redirect($back . '#files'); }
    if ($a === 'post_delete') {
        foreach (all('SELECT * FROM project_files WHERE post_id = ? AND project_id = ?', [(int)post('post'), $id]) as $f) { @unlink(STORAGE . '/portal/' . $f['path']); q('DELETE FROM project_files WHERE id = ?', [$f['id']]); }
        q('DELETE FROM project_posts WHERE id = ? AND project_id = ?', [(int)post('post'), $id]); flash('Post deleted.'); redirect($back);
    }
    if ($a === 'step_add' && post('title') !== '') {
        q('INSERT INTO project_steps (project_id, title, due_date, position) VALUES (?,?,?,?)', [$id, mb_substr(post('title'), 0, 200), valid_date(post('due_date')) ? post('due_date') : null, (int)val('SELECT COALESCE(MAX(position),0)+1 FROM project_steps WHERE project_id = ?', [$id])]);
        $touch(); redirect($back . '#steps');
    }
    if ($a === 'step_toggle' && ($s = one('SELECT * FROM project_steps WHERE id = ? AND project_id = ?', [(int)post('step'), $id]))) {
        q('UPDATE project_steps SET done_at = ? WHERE id = ?', [$s['done_at'] ? null : now(), $s['id']]);
        // Progress follows the checklist, unless the project is finished.
        $tot = (int)val('SELECT COUNT(*) FROM project_steps WHERE project_id = ?', [$id]); $dn = (int)val('SELECT COUNT(*) FROM project_steps WHERE project_id = ? AND done_at IS NOT NULL', [$id]);
        if ($tot && $p['status'] !== 'done') q('UPDATE projects SET progress = ? WHERE id = ?', [(int)round(100 * $dn / $tot), $id]);
        if (!$s['done_at']) q("INSERT INTO project_posts (project_id, author, kind, body, created_at) VALUES (?, 'staff', 'status', ?, ?)", [$id, 'Step done: ' . $s['title'], now()]);
        $touch(); redirect($back . '#steps');
    }
    if ($a === 'step_delete') { q('DELETE FROM project_steps WHERE id = ? AND project_id = ?', [(int)post('step'), $id]); redirect($back . '#steps'); }
    if (in_array($a, ['grant', 'revoke', 'reset', 'send_link'], true) && ($c = one('SELECT * FROM contacts WHERE id = ?', [(int)post('contact')]))) {
        if ($a === 'grant') { portal_enable((int)$c['id'], true); flash(person_name($c) . ' can now sign in to the portal.'); }
        if ($a === 'revoke') { portal_enable((int)$c['id'], false); flash(person_name($c) . ' no longer has portal access.'); }
        if ($a === 'reset') { portal_reset_link((int)$c['id']); flash('New invite link created. The old one, and any signed-in sessions, stop working.'); }
        if ($a === 'send_link') {
            $c = one('SELECT * FROM contacts WHERE id = ?', [$c['id']]);
            $first = $c['first_name'] ?: 'there';
            $ok = $c['portal_access'] && filter_var($c['email'], FILTER_VALIDATE_EMAIL) && send_mail($c['email'], 'Your client portal for ' . $p['name'],
                "Hi $first,\n\nYou can follow " . $p['name'] . " in your client portal: progress, updates, files to review and invoices, all in one place. You can also send us files and messages there.\n\nOpen your portal: " . portal_invite_url($c)
                . "\n\nThis link is personal to you, so please don't forward it. Next time, you can also sign in at " . abs_url('portal-login.php') . " with just your email.\n\n" . ($p['brand'] && $p['brand'] !== 'Syndicate Holdings Group' ? $p['brand'] : setting('business_name')) . "\n" . setting('business_email'));
            if ($ok) log_activity('contact', (int)$c['id'], 'email', 'Sent the client portal invite for ' . $p['name'] . '.');
            flash($ok ? 'Invite emailed to ' . $c['email'] . '.' : 'The invite couldn’t be sent. Copy the link and send it yourself.');
        }
        redirect($back . '#people');
    }
    if ($a === 'quick_status' && isset(PROJECT_STATUSES[post('status')]) && post('status') !== $p['status']) {
        q('UPDATE projects SET status = ?, progress = CASE WHEN ? = \'done\' THEN 100 ELSE progress END, updated_at = ? WHERE id = ?', [post('status'), post('status'), now(), $id]);
        q("INSERT INTO project_posts (project_id, author, kind, body, created_at) VALUES (?, 'staff', 'status', ?, ?)", [$id, 'Status changed to ' . PROJECT_STATUSES[post('status')] . '.', now()]);
        flash('Status updated.'); redirect($back);
    }
    if (!$errors) redirect($back);
}
$steps = all('SELECT * FROM project_steps WHERE project_id = ? ORDER BY position, id', [$id]);
$posts = project_posts($id); $files = project_files($id);
$people = all('SELECT * FROM contacts WHERE (company_id IS NOT NULL AND company_id = ?) OR id = ? ORDER BY portal_access DESC, first_name', [(int)$p['company_id'], (int)$p['contact_id']]);
$invoices = all("SELECT * FROM invoices WHERE status IN ('sent','paid') AND ((company_id IS NOT NULL AND company_id = ?) OR contact_id = ?) ORDER BY issue_date DESC LIMIT 6", [(int)$p['company_id'], (int)$p['contact_id']]);
layout_top($p['name'], 'projects', $user);
?>
<div class="page-head">
  <div class="grow-head"><a class="muted small" href="projects.php">← Projects</a><h1><?= e($p['name']) ?></h1>
    <p class="row muted"><?= project_status_pill($p['status']) ?> <?= brand_tag($p['brand']) ?> <?= $p['company_id'] ? '<a href="company.php?id=' . (int)$p['company_id'] . '">' . e($p['company']) . '</a>' : '' ?><?= $p['contact_id'] ? ' · <a href="contact.php?id=' . (int)$p['contact_id'] . '">' . e(person_name($p)) . '</a>' : '' ?><?php if (!$p['shared']): ?> <span class="tag">Hidden from the portal</span><?php endif; ?></p></div>
  <div class="row">
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="quick_status"><select name="status" class="w-auto" data-autosubmit aria-label="Status"><?php foreach (PROJECT_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $p['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
    <a class="btn ghost sm" href="invoice-edit.php?<?= $p['company_id'] ? 'company=' . (int)$p['company_id'] : 'contact=' . (int)$p['contact_id'] ?>">+ Invoice</a>
    <a class="btn primary sm" href="project-edit.php?id=<?= $id ?>">Edit</a>
  </div>
</div>
<?php require_once __DIR__ . '/app/reviews.php'; if ($rv = rv_for_project($id)): ?><div class="notice row" style="flex-wrap:wrap;gap:10px"><span class="grow"><?= e(rv_line($rv)) ?></span>
  <?php if ($rv['status'] === 'scheduled'): ?><form method="post" action="reviews.php" class="row" style="gap:8px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$rv['id'] ?>"><input type="hidden" name="back" value="project"><input type="hidden" name="project" value="<?= $id ?>"><button class="btn ghost sm" name="action" value="send">Send now</button><button class="link-danger" name="action" value="skip">Don’t ask</button></form><?php endif; ?>
  <a class="muted small" href="reviews.php">Review requests →</a></div>
<?php elseif ($p['status'] === 'done' && rv_on()): ?><div class="notice">A review request will be scheduled within 15 minutes. <a class="muted small" href="reviews.php">Review requests →</a></div><?php endif; ?>
<?php if ($errors): ?><div class="notice bad"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<div class="grid-detail">
  <div class="stack">
    <?php if ($p['summary'] !== ''): ?><section class="card"><div class="note-box summary"><?= nl2br(e($p['summary'])) ?></div></section><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card composer-card">
      <?= csrf_field() ?><input type="hidden" name="action" value="post">
      <div class="card-h"><h2>Post to the client</h2><div class="seg" role="radiogroup" aria-label="Type"><input type="radio" name="kind" id="pk-u" value="update" checked><label for="pk-u">Update</label><input type="radio" name="kind" id="pk-a" value="approval"><label for="pk-a">Needs approval</label></div></div>
      <textarea name="body" rows="4" placeholder="What’s done, what’s next, or what you need from them. For approvals, say what to look at."></textarea>
      <div class="row reply-foot">
        <label class="file-pick"><input type="file" name="files[]" multiple data-max="<?= upload_limit() ?>"><span>Attach files</span></label>
        <label class="chk">Progress <input type="number" name="progress" min="0" max="100" step="1" value="<?= (int)$p['progress'] ?>" class="pct" aria-label="Progress percent">%</label>
        <label class="chk"><input type="checkbox" name="notify" value="1" checked<?= $p['shared'] ? '' : ' disabled' ?>> Email the client</label>
        <span class="grow"></span><button class="btn primary sm" type="submit">Post</button>
      </div>
    </form>
    <section class="card"><div class="card-h"><h2>Activity</h2><span class="muted small">What the client sees, newest first</span></div><?php render_project_feed($posts, $files, 'staff'); ?></section>
  </div>
  <div class="stack">
    <section class="card" id="steps"><div class="card-h"><h2>Progress</h2><b class="num"><?= (int)$p['progress'] ?>%</b></div>
      <div class="progress"><i style="width:<?= (int)$p['progress'] ?>%"></i></div>
      <?php if ($p['start_date'] || $p['due_date']): ?><p class="muted small"><?= $p['start_date'] ? 'Started ' . e(fmt_date($p['start_date'])) : '' ?><?= $p['start_date'] && $p['due_date'] ? ' · ' : '' ?><?= $p['due_date'] ? 'Target ' . e(fmt_date($p['due_date'])) : '' ?></p><?php endif; ?>
      <?php render_project_steps($steps, true); ?>
      <form method="post" class="task-add"><?= csrf_field() ?><input type="hidden" name="action" value="step_add"><input name="title" placeholder="Add a step" required aria-label="Step"><input name="due_date" type="date" aria-label="Target date"><button class="btn ghost sm" type="submit">Add</button></form>
    </section>
    <section class="card" id="files"><div class="card-h"><h2>Files</h2><span class="muted small"><?= count($files) ?></span></div>
      <?php if (!$files): ?><p class="empty">No files yet. Clients can upload logos, photos and documents from their portal.</p><?php else: ?>
      <ul class="list file-list"><?php foreach ($files as $f): ?><li class="li-row"><?php render_file_chip($f, 'staff'); ?><form method="post" class="inline" data-confirm="Remove <?= e($f['name']) ?>? The client won’t see it any more."><?= csrf_field() ?><input type="hidden" name="action" value="file_delete"><input type="hidden" name="file" value="<?= (int)$f['id'] ?>"><button class="x" type="submit" aria-label="Remove file">×</button></form></li><?php endforeach; ?></ul><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="files"><label class="file-pick"><input type="file" name="files[]" multiple required data-max="<?= upload_limit() ?>"><span>Choose files</span></label><button class="btn ghost sm" type="submit">Upload</button></form>
    </section>
    <section class="card" id="people"><div class="card-h"><h2>Portal access</h2><a class="muted small" href="portal-login.php" target="_blank" rel="noopener">Sign-in page ↗</a></div>
      <?php if (!$people): ?><p class="empty">Add a contact to this project’s company first.</p><?php endif; ?>
      <?php foreach ($people as $c): ?>
        <div class="box-row">
          <span class="grow"><b><span class="dot <?= $c['portal_access'] ? 'ok' : '' ?>"></span><?= e(person_name($c)) ?></b><small><?= $c['portal_access'] ? ($c['portal_seen_at'] ? 'Last visit ' . e(ago($c['portal_seen_at'])) : 'Hasn’t signed in yet') : ($c['email'] ? e($c['email']) : 'Needs an email address first') ?></small></span>
          <?php if ($c['portal_access']): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="contact" value="<?= (int)$c['id'] ?>"><button class="btn ghost xs" type="submit" name="action" value="send_link">Email invite</button></form>
            <details class="more"><summary class="btn ghost xs" aria-label="More">⋯</summary><div class="more-menu">
              <div class="copy-row"><input readonly id="inv<?= (int)$c['id'] ?>" value="<?= e(portal_invite_url($c)) ?>" aria-label="Invite link"><button class="btn ghost xs" type="button" data-copy="#inv<?= (int)$c['id'] ?>">Copy</button></div>
              <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="contact" value="<?= (int)$c['id'] ?>"><button class="link-danger" type="submit" name="action" value="reset" data-confirm="Make a new invite link? The old link stops working and they’re signed out.">Reset link</button><button class="link-danger" type="submit" name="action" value="revoke" data-confirm="Remove <?= e(person_name($c)) ?>’s portal access?">Remove access</button></form>
            </div></details>
          <?php elseif ($c['email']): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="contact" value="<?= (int)$c['id'] ?>"><button class="btn primary xs" type="submit" name="action" value="grant">Give access</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <p class="muted small">People with access see every shared project for their company, plus its sent and paid invoices.</p>
    </section>
    <?php if ($invoices): ?><section class="card"><div class="card-h"><h2>Invoices in their portal</h2></div>
      <ul class="list"><?php foreach ($invoices as $i): ?><li><a href="invoice.php?id=<?= (int)$i['id'] ?>"><span class="grow"><b><?= e($i['number']) ?></b><small><?= e(fmt_date($i['issue_date'])) ?></small></span><?= pill('inv', $i['status'], INVOICE_STATUSES[$i['status']]) ?><span class="num"><?= money(invoice_total((int)$i['id'])['total']) ?></span></a></li><?php endforeach; ?></ul>
    </section><?php endif; ?>
  </div>
</div>
<?php layout_bottom(); ?>
