<?php
// Hiring HQ → Team: everyone with a team portal login, their onboarding progress, invites and the shared document links.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
$admin = require_admin();
$db = db();
$errors = [];
$inv = ['name' => post('name'), 'email' => post('email'), 'role' => post('role', 'sales_rep'), 'start_date' => post('start_date')];

if (is_post() && post('action') === 'invite') {
    if (mb_strlen($inv['name']) < 2 || mb_strlen($inv['name']) > 120) $errors[] = 'Enter their full name.';
    if (!filter_var($inv['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!isset(TEAM_ROLES[$inv['role']])) $errors[] = 'Pick a role.';
    if ($inv['start_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $inv['start_date'])) $errors[] = 'Use a real start date.';
    $st = $db->prepare('SELECT id FROM team_members WHERE email = ?'); $st->execute([$inv['email']]);
    if ($ex = $st->fetchColumn()) $errors[] = 'That email is already on the team list.';
    if (!$errors) {
        $t = now();
        $db->prepare('INSERT INTO team_members (name, email, role, status, start_date, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$inv['name'], $inv['email'], $inv['role'], 'onboarding', $inv['start_date'], $t, $t]);
        $mid = (int)$db->lastInsertId();
        if (post('send') === '1') {
            $ok = team_mail_link(team_member($mid), true);
            flash($ok ? 'Added ' . $inv['name'] . ' and emailed their invite.' : 'Added ' . $inv['name'] . ', but the invite email failed. Try “Email a sign-in link” on their page.');
        } else flash('Added ' . $inv['name'] . '. Send the invite from their page when you’re ready.');
        redirect('admin/member.php?id=' . $mid);
    }
}
if (is_post() && post('action') === 'docs') {
    $bad = [];
    foreach (TEAM_DOCS as $k => $label) {
        $raw = post('doc_' . $k); $clean = team_clean_url($raw);
        if ($raw !== '' && $clean === '') $bad[] = $label;
        team_setting_set('doc_' . $k, $clean);
    }
    flash($bad ? 'Saved, but these need a full https:// link: ' . implode(', ', $bad) . '.' : 'Document links saved.');
    redirect('admin/team.php#docs');
}

$status = get('status');
if (!isset(TEAM_STATUSES[$status])) $status = '';
$st = $db->prepare("SELECT * FROM team_members WHERE " . ($status !== '' ? 'status = ?' : "status != 'left'") . " ORDER BY status = 'onboarding' DESC, name COLLATE NOCASE");
$st->execute($status !== '' ? [$status] : []);
$members = $st->fetchAll();
$counts = [];
foreach ($db->query('SELECT status, COUNT(*) c FROM team_members GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
$waiting = [];
foreach ($db->query('SELECT member_id, COUNT(*) c FROM team_steps WHERE nudged_at IS NOT NULL AND done_at IS NULL GROUP BY member_id') as $r) $waiting[(int)$r['member_id']] = (int)$r['c'];

admin_header('Team', 'team', $admin);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span><?= array_sum($counts) - ($counts['left'] ?? 0) ?> on the team</span><h1>Team</h1>
  <p class="muted" style="margin:6px 0 0">Onboarding checklists, signatures and offboarding. Members sign in at <a href="team/" target="_blank" rel="noopener" style="color:var(--text)">stusyn.com/team</a>.</p></div>
  <a class="btn btn-primary btn-sm" href="admin/team.php#invite">Add a team member</a>
</div>

<div class="tabs" role="tablist">
  <a href="admin/team.php"<?= $status === '' ? ' aria-current="page"' : '' ?>>Current <span><?= array_sum($counts) - ($counts['left'] ?? 0) ?></span></a>
  <?php foreach (TEAM_STATUSES as $k => $label): ?><a href="admin/team.php?status=<?= e($k) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span><?= $counts[$k] ?? 0 ?></span></a><?php endforeach; ?>
</div>

<?php if (!$members): ?>
  <div class="panel"><p class="empty">No one here yet. Add a team member below to send them their onboarding checklist.</p></div>
<?php else: ?>
<div class="panel" style="padding:0"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Member</th><th>Role</th><th>Status</th><th>Onboarding</th><th>Last seen</th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $p = team_progress($m); [$d, $n] = $p['start']; ?>
    <tr data-href="admin/member.php?id=<?= (int)$m['id'] ?>">
      <td><a href="admin/member.php?id=<?= (int)$m['id'] ?>" class="who"><span class="av"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span><span><b><?= e($m['name']) ?></b><small><?= e($m['email']) ?></small></span></a></td>
      <td><?= e(team_role($m['role'])) ?></td>
      <td><span class="pill-s t-s-<?= e($m['status']) ?>"><?= e(TEAM_STATUSES[$m['status']]) ?></span><?php if (!empty($waiting[(int)$m['id']])): ?> <span class="badge" title="Waiting on you"><?= $waiting[(int)$m['id']] ?></span><?php endif; ?></td>
      <td><div class="t-meter sm" aria-label="<?= $d ?> of <?= $n ?> done"><span style="width:<?= $n ? (int)round($d / $n * 100) : 0 ?>%"></span></div><small class="muted mono"><?= $d ?>/<?= $n ?></small></td>
      <td class="mono"><?= $m['last_seen_at'] ? e(ago($m['last_seen_at'])) : ($m['invited_at'] ? 'Invited ' . e(ago($m['invited_at'])) : 'Not invited') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php endif; ?>

<div class="adm-grid">
  <form class="panel form" method="post" id="invite">
    <?= csrf_field() ?><input type="hidden" name="action" value="invite">
    <div class="panel-h"><h2>Add a team member</h2></div>
    <?php if ($errors): ?><div class="notice warn" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    <div class="f"><label for="i-name">Full name</label><input id="i-name" name="name" required maxlength="120" value="<?= e($inv['name']) ?>"></div>
    <div class="f"><label for="i-email">Email</label><input id="i-email" name="email" type="email" required value="<?= e($inv['email']) ?>"></div>
    <div class="f"><label for="i-role">Role</label><select id="i-role" name="role"><?php foreach (TEAM_ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $inv['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="f"><label for="i-start">Start date <span class="muted">(optional)</span></label><input id="i-start" name="start_date" type="date" value="<?= e($inv['start_date']) ?>"></div>
    <label class="t-cb"><input type="checkbox" name="send" value="1" checked> Email their invite now (a sign-in link that lasts <?= TEAM_INVITE_DAYS ?> days)</label>
    <button class="btn btn-primary btn-sm" type="submit">Add to team</button>
    <p class="form-note">Before inviting a sales rep, make sure the document links below are filled in so they can sign right away.</p>
  </form>

  <form class="panel form" method="post" id="docs">
    <?= csrf_field() ?><input type="hidden" name="action" value="docs">
    <div class="panel-h"><h2>Document links</h2></div>
    <p class="muted t-small" style="margin:0">Share each doc from Claude (anyone with the link can view) and paste the link here. Members see these on their checklist. You can give one person their own filled-in agreement on their page.</p>
    <?php foreach (TEAM_DOCS as $k => $label): ?>
      <div class="f"><label for="d-<?= e($k) ?>"><?= e($label) ?></label><input id="d-<?= e($k) ?>" name="doc_<?= e($k) ?>" type="url" placeholder="https://…" value="<?= e(team_setting('doc_' . $k)) ?>"></div>
    <?php endforeach; ?>
    <button class="btn btn-primary btn-sm" type="submit">Save links</button>
  </form>
</div>
<?php admin_footer(); ?>
