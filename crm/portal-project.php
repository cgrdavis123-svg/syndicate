<?php
// One project, as the client sees it: progress, steps, updates, approvals, files, and a box to message the team.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$c = require_portal();
$p = portal_project($c, (int)get('id'));
if (!$p) { flash('That project isn’t available.'); redirect('portal.php'); }
$back = 'portal-project.php?id=' . (int)$p['id'];
$errors = [];
if (is_post()) {
    $a = post('action'); $note = mb_substr(rtrim((string)($_POST['note'] ?? $_POST['body'] ?? '')), 0, 5000);
    if (in_array($a, ['approve', 'changes'], true) && ($pp = one("SELECT * FROM project_posts WHERE id = ? AND project_id = ? AND approval = 'pending'", [(int)post('post'), $p['id']]))) {
        if ($a === 'changes' && trim($note) === '') $errors[] = 'Tell us what you’d like changed.';
        else {
            q('UPDATE project_posts SET approval = ?, decision_note = ?, decided_by = ?, decided_at = ? WHERE id = ?', [$a === 'approve' ? 'approved' : 'changes', $note, $c['id'], now(), $pp['id']]);
            q('UPDATE projects SET updated_at = ? WHERE id = ?', [now(), $p['id']]);
            require_once __DIR__ . '/app/builds.php'; build_decided((int)$pp['id'], $a, $note);
            notify_staff($p, $c, $a === 'approve' ? 'approved your request' : 'asked for changes', 'Request: ' . mb_strimwidth($pp['body'], 0, 400, '…') . ($note !== '' ? "\n\nTheir note: " . $note : ''));
            flash($a === 'approve' ? 'Approved. Thank you!' : 'Got it. We’ll make the changes and post an update.');
            redirect($back . '#post' . $pp['id']);
        }
    }
    if ($a === 'message') {
        $hasFiles = array_filter($_FILES['files']['name'] ?? [], 'strlen');
        if (trim($note) === '' && !$hasFiles) $errors[] = 'Write a message or attach a file.';
        else {
            q("INSERT INTO project_posts (project_id, author, contact_id, kind, body, created_at) VALUES (?, 'client', ?, 'message', ?, ?)", [$p['id'], $c['id'], $note, now()]);
            $postId = (int)db()->lastInsertId();
            [$n, $errors] = save_project_files((int)$p['id'], 'files', 'client', (int)$c['id'], $postId);
            if (trim($note) === '' && !$n) q('DELETE FROM project_posts WHERE id = ?', [$postId]); // every file failed
            else {
                q('UPDATE projects SET updated_at = ? WHERE id = ?', [now(), $p['id']]);
                notify_staff($p, $c, $n && trim($note) === '' ? 'shared ' . $n . ' file' . ($n === 1 ? '' : 's') : 'sent a message', trim($note) . ($n && trim($note) !== '' ? "\n\n(+ $n file" . ($n === 1 ? '' : 's') . ')' : ''));
                flash($errors ? 'Sent, but some files weren’t: ' . implode(' ', $errors) : 'Sent. We’ll get back to you soon.'); redirect($back . '#post' . $postId);
            }
        }
    }
}
$steps = all('SELECT * FROM project_steps WHERE project_id = ? ORDER BY position, id', [$p['id']]);
$posts = project_posts((int)$p['id']); $files = project_files((int)$p['id']);
portal_top($p['name'], $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal.php">← All projects</a><h1><?= e($p['name']) ?></h1><p class="row muted"><?= project_status_pill($p['status']) ?><?php if ($p['due_date'] && $p['status'] !== 'done'): ?> <span class="small">Target <?= e(fmt_date($p['due_date'])) ?></span><?php endif; ?></p></div></div>
<?php require_once __DIR__ . '/app/builds.php'; if (($kb = build_for_project((int)$p['id'])) && kickoff_open($kb) && !$kb['intake_at']): ?><div class="notice row" style="flex-wrap:wrap;gap:10px"><span class="grow"><b>Start here:</b> tell us about your business in your kickoff form. It takes about 10 minutes, and we start your website as soon as it’s in.</span><a class="btn primary sm" href="portal-kickoff.php?id=<?= (int)$p['id'] ?>">Fill in the kickoff form</a></div>
<?php elseif ($kb && kickoff_open($kb)): ?><p class="muted small"><a href="portal-kickoff.php?id=<?= (int)$p['id'] ?>">Your kickoff form</a> · you can change your answers until we start building.</p><?php endif; ?>
<?php if ($errors): ?><div class="notice bad"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<div class="grid-detail">
  <div class="stack">
    <?php if ($p['summary'] !== ''): ?><section class="card"><div class="note-box summary"><?= nl2br(e($p['summary'])) ?></div></section><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card composer-card" id="message">
      <?= csrf_field() ?><input type="hidden" name="action" value="message">
      <div class="card-h"><h2>Message the team</h2></div>
      <textarea name="body" rows="3" placeholder="Questions, feedback, or files we need (logos, photos, copy)…"></textarea>
      <div class="row reply-foot"><label class="file-pick"><input type="file" name="files[]" multiple data-max="<?= upload_limit() ?>"><span>Attach files</span></label><span class="grow"></span><button class="btn primary sm" type="submit">Send</button></div>
    </form>
    <section class="card"><div class="card-h"><h2>Updates</h2></div><?php render_project_feed($posts, $files, 'client'); ?></section>
  </div>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Progress</h2><b class="num"><?= (int)$p['progress'] ?>%</b></div>
      <div class="progress"><i style="width:<?= (int)$p['progress'] ?>%"></i></div>
      <?php render_project_steps($steps, false); ?>
    </section>
    <section class="card" id="files"><div class="card-h"><h2>Files</h2><span class="muted small"><?= count($files) ?></span></div>
      <?php if (!$files): ?><p class="empty">Files you and the team share show here.</p><?php else: ?><div class="file-stack"><?php foreach ($files as $f) render_file_chip($f, 'client'); ?></div><?php endif; ?>
    </section>
  </div>
</div>
<?php portal_bottom(); ?>
