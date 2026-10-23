<?php
// One website draft: the brief, your notes for the builder, each draft with its preview, and sending it to the client.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/builds.php';
$user = require_user();

// Start a build by hand from a project (for work that didn't come through the store or a quote).
if (is_post() && post('action') === 'create') {
    $p = one('SELECT * FROM projects WHERE id = ?', [(int)post('project')]);
    if (!$p || !isset(BUILD_ROUNDS[post('sku')])) { flash('Pick a project and a website package.'); redirect('projects.php'); }
    $id = build_create((int)$p['id'], post('sku'), ['contact_id' => $p['contact_id'] ? (int)$p['contact_id'] : null, 'company_id' => $p['company_id'] ? (int)$p['company_id'] : null,
        'business' => (string)(val('SELECT name FROM companies WHERE id = ?', [(int)$p['company_id']]) ?: $p['name'])]);
    if (!$id) { $b = build_for_project((int)$p['id']); redirect($b ? 'build.php?id=' . $b['id'] : 'project.php?id=' . $p['id']); }
    flash('Website draft added. Add your notes, then mark it ready for the builder.');
    redirect('build.php?id=' . $id);
}

$b = build_row((int)get('id'));
if (!$b) { flash('That website draft no longer exists.'); redirect('builds.php'); }
$id = (int)$b['id']; $back = 'build.php?id=' . $id;
if (get('dl') !== '') {
    $v = one("SELECT * FROM build_versions WHERE build_id = ? AND version = ? AND status = 'ready'", [$id, (int)get('dl')]);
    if (!$v || $v['source'] === '' || !is_file(DATA . '/' . $v['source'])) { flash('That draft has no source zip.'); redirect($back); }
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize(DATA . '/' . $v['source']));
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9]+/', '-', strtolower(build_who($b))) . '-v' . (int)$v['version'] . '-source.zip"');
    readfile(DATA . '/' . $v['source']);
    exit;
}
$errors = [];
if (is_post()) {
    $a = post('action');
    if ($a === 'save') {
        build_set($id, ['notes' => mb_substr(rtrim((string)($_POST['notes'] ?? '')), 0, 8000), 'publish' => mb_substr(trim((string)($_POST['publish'] ?? '')), 0, 2000)]);
        flash('Saved.');
    }
    if ($a === 'ready' && in_array($b['status'], ['waiting', 'blocked', 'review'], true)) {
        $next = $b['change_notes'] !== '' && (int)$b['version'] > 0 ? 'changes' : 'ready';
        if (post('change_notes') !== '') { $next = 'changes'; build_set($id, ['change_notes' => mb_substr(rtrim((string)$_POST['change_notes']), 0, 6000)]); }
        build_set($id, ['status' => $next, 'ready_at' => time(), 'attempts' => 0, 'question' => '']);
        q("UPDATE tasks SET done_at = ? WHERE done_at IS NULL AND entity_type = 'project' AND entity_id = ? AND title LIKE 'Website draft needs you%'", [now(), $b['project_id']]);
        flash('Queued. The builder picks it up on its next run.');
    }
    if ($a === 'hold' && in_array($b['status'], ['ready', 'changes', 'blocked'], true)) { build_set($id, ['status' => 'waiting']); flash('On hold. The builder won’t touch it until you mark it ready.'); }
    if ($a === 'send' && $b['status'] === 'review') {
        $r = build_send($b, (string)($_POST['message'] ?? ''));
        if ($r['ok']) flash('Sent for approval' . ($r['emailed'] ? ' and emailed ' . $r['emailed'] . ' ' . ($r['emailed'] === 1 ? 'person' : 'people') : '') . '.');
        else $errors[] = $r['error'];
    }
    if ($a === 'cancel') { build_set($id, ['status' => 'cancelled']); flash('Cancelled. Its preview links stop working.'); }
    if ($a === 'restore' && $b['status'] === 'cancelled') { build_set($id, ['status' => (int)$b['version'] ? 'review' : 'waiting']); flash('Restored.'); }
    if (!$errors) redirect($back);
    $b = build_row($id);
}
$p = build_project($b);
$prod = one('SELECT name, features FROM products WHERE sku = ?', [$b['sku']]);
$versions = build_versions($id);
$latest = $versions[0] ?? null;
$brief = json_decode($b['brief'], true) ?: [];
$extras = json_decode($b['extras'], true) ?: [];
$files = $p ? all('SELECT * FROM project_files WHERE project_id = ? ORDER BY id DESC', [$p['id']]) : [];
$kick = $b['contact_id'] ? one("SELECT b.starts_at, b.minutes, b.status FROM bookings b JOIN meeting_types t ON t.id = b.type_id WHERE t.kind = 'kickoff' AND b.contact_id = ? AND b.status = 'confirmed' ORDER BY b.starts_at DESC LIMIT 1", [$b['contact_id']]) : null;
$sentPost = $latest && $latest['post_id'] ? one('SELECT * FROM project_posts WHERE id = ?', [$latest['post_id']]) : null;
layout_top('Website draft · ' . build_who($b), 'builds', $user);
?>
<div class="page-head">
  <div class="grow-head"><a class="muted small" href="builds.php">← Website drafts</a><h1><?= e(build_who($b)) ?></h1>
    <p class="row muted"><?= build_pill($b['status']) ?> <?= e($prod['name'] ?? $b['sku']) ?> · changes <?= (int)$b['rounds_used'] ?> of <?= (int)$b['rounds'] ?> rounds<?= $p ? ' · <a href="project.php?id=' . (int)$p['id'] . '">' . e($p['name']) . '</a>' : '' ?><?= $p && $p['due_date'] ? ' · target ' . e(fmt_date($p['due_date'])) : '' ?></p></div>
  <div class="row">
    <?php if (in_array($b['status'], ['waiting', 'blocked'], true)): ?><form method="post"><?= csrf_field() ?><button class="btn primary sm" name="action" value="ready">Ready for the builder</button></form><?php endif; ?>
    <?php if (in_array($b['status'], ['ready', 'changes', 'blocked'], true)): ?><form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="hold">Hold</button></form><?php endif; ?>
    <?php if ($b['status'] !== 'cancelled'): ?><form method="post" data-confirm="Cancel this website draft? Its preview links stop working."><?= csrf_field() ?><button class="link-danger" name="action" value="cancel">Cancel</button></form>
    <?php else: ?><form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="restore">Restore</button></form><?php endif; ?>
  </div>
</div>
<?php if ($errors): ?><div class="notice bad"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<?php if ($b['status'] === 'blocked' && $b['question'] !== ''): ?><div class="notice bad"><b>The builder needs you.</b><br><?= nl2br(e($b['question'])) ?><br><span class="muted small">Answer it in your notes below (or upload what’s missing to the project), then press Ready for the builder.</span></div><?php endif; ?>
<?php if ($b['status'] === 'waiting'): ?><div class="notice"><?= $kick ? 'Kickoff call ' . e(date('M j, g:i a', (int)$kick['starts_at'])) . '. The builder can start ' . BUILD_KICKOFF_WAIT . ' hours after it ends, so add what you learned below before then.'
  : ($b['intake_at'] && $b['intake_call'] ? 'The client sent the kickoff form and asked for a call first. The builder starts ' . BUILD_KICKOFF_WAIT . ' hours after the call, or press Ready for the builder now.'
  : 'Waiting for the client’s kickoff form (or a kickoff call). It starts by itself when the form comes in, or ' . BUILD_KICKOFF_WAIT . ' hours after a call. Press Ready for the builder to start now.' . ($b['intake_reminded_at'] ? ' One reminder was sent ' . e(date('M j', (int)$b['intake_reminded_at'])) . '.' : '')) ?></div><?php endif; ?>
<?php if ($b['status'] === 'sent' && $sentPost): ?><div class="notice">Draft v<?= (int)$latest['version'] ?> is with the client since <?= e(fmt_date($sentPost['created_at'], 'M j')) ?>. Their Approve or Request changes in the portal moves this on.</div><?php endif; ?>
<?php if ($b['status'] === 'approved'): ?><div class="notice">Approved. Download the source below, put it live on their domain (templates/CLAUDE.md §12), and mark the project Complete.</div><?php endif; ?>
<?php if ($b['status'] === 'changes' || ($b['change_notes'] !== '' && in_array($b['status'], ['building', 'blocked'], true))): ?><div class="notice"><b>Changes the builder is working from:</b><br><?= nl2br(e($b['change_notes'])) ?></div><?php endif; ?>
<div class="grid-detail">
  <div class="stack">
    <?php if ($latest): $confirm = json_decode($latest['confirm'], true) ?: []; ?>
    <section class="card"><div class="card-h"><h2>Draft v<?= (int)$latest['version'] ?></h2><span class="row" style="gap:8px"><a class="btn primary sm" href="<?= e(build_preview_url($latest)) ?>" target="_blank" rel="noopener">Open the preview ↗</a><?php if ($latest['source'] !== ''): ?><a class="btn ghost sm" href="<?= e($back) ?>&amp;dl=<?= (int)$latest['version'] ?>">Source .zip</a><?php endif; ?></span></div>
      <p class="muted small"><?= (int)$latest['files'] ?> files · <?= e(fmt_bytes((int)$latest['bytes'])) ?> · finished <?= e(date('M j, g:i a', (int)$latest['done_at'])) ?>. Check it on your phone too.</p>
      <div class="note-box summary"><?= nl2br(e($latest['summary'])) ?></div>
      <?php if ($confirm): ?><h3 class="small" style="margin-top:14px">To confirm before the client sees it</h3><ul class="small"><?php foreach ($confirm as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($b['status'] === 'review'): ?>
        <form method="post" class="stack" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="send">
          <label class="f"><span>Message to the client <small>(goes above the preview link in their portal)</small></span><textarea name="message" rows="4"><?= e(($b['rounds_used'] ? 'We made the changes you asked for.' : 'Your first draft is ready.') . ' Have a look and tell us what you think.') ?></textarea></label>
          <div class="row"><button class="btn primary sm">Send to the client for approval</button><span class="muted small">They get an email and an Approve / Request changes box in their portal.</span></div>
        </form>
        <details style="margin-top:12px"><summary class="small"><b>Not right? Send it back to the builder</b></summary>
          <form method="post" class="stack" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="ready">
            <textarea name="change_notes" rows="4" required placeholder="What to change, as specifically as you can. This doesn’t use one of the client’s rounds."></textarea>
            <button class="btn ghost sm">Send back</button></form></details>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="card-h"><h2>Notes for the builder</h2><span class="muted small">It reads these every time</span></div>
      <label class="f"><span>What you learned on the kickoff call, and anything to do or avoid</span><textarea name="notes" rows="8" placeholder="Services in their words, who their customers are, colors they like, pages they want, what to leave out…"><?= e($b['notes']) ?></textarea></label>
      <label class="f"><span>Contact details the client approved for the site <small>(empty = the builder leaves them marked to confirm)</small></span><textarea name="publish" rows="3" placeholder="Phone (864) 555-0100 · info@business.com · Serving Spartanburg and Greenville · no street address"><?= e($b['publish']) ?></textarea></label>
      <div class="save-bar"><span></span><button class="btn primary sm">Save notes</button></div>
    </form>
    <?php if (count($versions) > 1): ?><section class="card"><div class="card-h"><h2>Earlier drafts</h2></div><ul class="list"><?php foreach (array_slice($versions, 1) as $v): ?>
      <li class="li-row"><span class="grow"><b>v<?= (int)$v['version'] ?></b><small><?= e(date('M j', (int)$v['done_at'])) ?></small></span><a class="btn ghost xs" href="<?= e(build_preview_url($v)) ?>" target="_blank" rel="noopener">Preview ↗</a><?php if ($v['source'] !== ''): ?><a class="btn ghost xs" href="<?= e($back) ?>&amp;dl=<?= (int)$v['version'] ?>">Source</a><?php endif; ?></li>
    <?php endforeach; ?></ul></section><?php endif; ?>
  </div>
  <div class="stack">
    <?php if ($b['intake_at']): $ka = kickoff_answers($b); ?><section class="card"><div class="card-h"><h2>Kickoff form</h2><span class="muted small">Sent <?= e(date('M j, g:i a', (int)$b['intake_at'])) ?><?= $b['intake_call'] ? ' · wants a call' : '' ?></span></div>
      <dl class="small"><?php foreach (KICKOFF_FIELDS as $k => [$l]): if (trim((string)($ka[$k] ?? '')) === '') continue; ?><dt class="muted"><?= e($l) ?></dt><dd><?= nl2br(e($ka[$k])) ?></dd><?php endforeach; ?></dl>
    </section><?php elseif ($p && in_array($b['status'], ['waiting', 'ready'], true)): ?><section class="card"><div class="card-h"><h2>Kickoff form</h2></div><p class="empty">Not sent yet. The client sees it at the top of their project and on their portal home.</p></section><?php endif; ?>
    <section class="card"><div class="card-h"><h2>From checkout</h2></div>
      <?php if ($brief): ?><dl class="small"><?php foreach (BRIEF_LABELS as $k => $l): if (!isset($brief[$k]) || $brief[$k] === '' || $brief[$k] === []) continue; ?><dt class="muted"><?= e($l) ?></dt><dd><?= e(is_array($brief[$k]) ? implode(', ', $brief[$k]) : $brief[$k]) ?></dd><?php endforeach; ?></dl>
      <?php else: ?><p class="empty">No checkout brief. Put what the builder needs in your notes.</p><?php endif; ?>
      <?php if ($extras): ?><p class="small"><b>Add-ons:</b> <?= e(implode(', ', array_map(function ($x) { return ($x['quantity'] > 1 ? $x['quantity'] . ' × ' : '') . $x['name']; }, $extras))) ?></p><?php endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>Client files</h2><span class="muted small"><?= count($files) ?></span></div>
      <?php if ($files): ?><ul class="list file-list"><?php foreach ($files as $f): ?><li class="li-row"><?php render_file_chip($f, 'staff'); ?></li><?php endforeach; ?></ul><?php else: ?><p class="empty">Nothing uploaded yet. The builder uses whatever the client adds to the project: logo, photos, text.</p><?php endif; ?>
      <?php if ($p): ?><p class="muted small"><a href="project.php?id=<?= (int)$p['id'] ?>#files">Add files on the project →</a></p><?php endif; ?>
    </section>
  </div>
</div>
<?php layout_bottom();
