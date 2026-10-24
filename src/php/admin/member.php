<?php
// Hiring HQ → Team → one member: details, their checklist (tick Grady's steps, see signatures), sign-in links, offboarding.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
$admin = require_admin();
$db = db();
$id = (int)(get('id') !== '' ? get('id') : post('id'));
$m = team_member($id);
if (!$m) not_found();
$errors = [];

if (is_post()) {
    $act = post('action');
    if ($act === 'save') {
        $f = ['name' => post('name'), 'email' => post('email'), 'role' => post('role'), 'status' => post('status'),
              'start_date' => post('start_date'), 'end_date' => post('end_date'), 'ref_link' => post('ref_link'),
              'agreement_url' => post('agreement_url'), 'notes' => post('notes')];
        if (mb_strlen($f['name']) < 2 || mb_strlen($f['name']) > 120) $errors[] = 'Enter their full name.';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!isset(TEAM_ROLES[$f['role']])) $errors[] = 'Pick a role.';
        if (!isset(TEAM_STATUSES[$f['status']])) $errors[] = 'Pick a status.';
        foreach (['start_date', 'end_date'] as $k) if ($f[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k])) $errors[] = 'Use real dates.';
        foreach (['ref_link' => 'referral link', 'agreement_url' => 'agreement link'] as $k => $label) {
            if ($f[$k] !== '' && team_clean_url($f[$k]) === '') $errors[] = 'The ' . $label . ' needs to be a full https:// link.';
            $f[$k] = team_clean_url($f[$k]);
        }
        $st = $db->prepare('SELECT id FROM team_members WHERE email = ? AND id != ?'); $st->execute([$f['email'], $id]);
        if ($st->fetchColumn()) $errors[] = 'Another team member already uses that email.';
        if (!$errors) {
            if (in_array($f['status'], ['leaving', 'left'], true) && $f['end_date'] === '') $f['end_date'] = date('Y-m-d');
            $db->prepare('UPDATE team_members SET name=?, email=?, role=?, status=?, start_date=?, end_date=?, ref_link=?, agreement_url=?, notes=?, updated_at=? WHERE id=?')
                ->execute([$f['name'], $f['email'], $f['role'], $f['status'], $f['start_date'], $f['end_date'], $f['ref_link'], $f['agreement_url'], $f['notes'], now(), $id]);
            // The referral link appearing ticks "Your referral link is set up".
            if ($f['role'] === 'sales_rep' && $f['ref_link'] !== '' && $m['ref_link'] === '') team_step_save($id, 'referrer', true, 'admin');
            if ($f['status'] === 'left') $db->prepare('UPDATE team_links SET used_at = ? WHERE member_id = ? AND used_at IS NULL')->execute([time(), $id]);
            flash($f['status'] !== $m['status'] && $f['status'] === 'leaving' ? 'Saved. Their offboarding steps are now on their checklist.' : 'Saved.');
            redirect('admin/member.php?id=' . $id);
        }
    } elseif (in_array($act, ['done', 'undo'], true)) {
        $def = team_step_def($m, post('step'));
        if ($def) {
            team_step_save($id, $def['key'], $act === 'done', 'admin');
            flash(($act === 'done' ? 'Marked done: ' : 'Reopened: ') . $def['title']);
        }
        redirect('admin/member.php?id=' . $id . '#s-' . preg_replace('/[^a-z_]/', '', post('step')));
    } elseif ($act === 'link') {
        if ($m['status'] === 'left') flash('They’ve left, so sign-in is off. Change their status first.');
        else flash(team_mail_link($m, !$m['invited_at']) ? 'Emailed ' . team_first($m) . ' a sign-in link.' : 'The email failed. Check the mail settings on the server.');
        redirect('admin/member.php?id=' . $id);
    } elseif ($act === 'delete') {
        $db->prepare('DELETE FROM team_members WHERE id = ?')->execute([$id]);
        flash('Removed ' . $m['name'] . ' and their checklist, signatures included.');
        redirect('admin/team.php');
    }
}

$rows = team_step_rows($id);
$steps = team_visible_steps($m);
$prog = team_progress($m, $rows);
$check = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$who = ['sign' => 'They sign', 'tick' => 'They tick', 'pay' => 'They choose', 'admin' => 'You'];
admin_header($m['name'], 'team', $admin);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span><?= e(team_role($m['role'])) ?> · <?= e(TEAM_STATUSES[$m['status']]) ?></span><h1><?= e($m['name']) ?></h1>
  <p class="muted" style="margin:6px 0 0">Onboarding <?= $prog['start'][0] ?>/<?= $prog['start'][1] ?><?= in_array($m['status'], ['leaving', 'left'], true) ? ' · Offboarding ' . $prog['end'][0] . '/' . $prog['end'][1] : '' ?> · <?= $m['last_seen_at'] ? 'Last seen ' . e(ago($m['last_seen_at'])) : ($m['invited_at'] ? 'Invited ' . e(ago($m['invited_at'])) . ', not signed in yet' : 'Not invited yet') ?></p></div>
  <div class="cta-row">
    <a class="btn btn-ghost btn-sm" href="admin/team.php">← Team</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-primary btn-sm" name="action" value="link"><?= $m['invited_at'] ? 'Email a sign-in link' : 'Send invite' ?> <?= ARROW ?></button></form>
  </div>
</div>

<div class="adm-grid detail">
  <section class="panel">
    <div class="panel-h"><h2>Checklist</h2><small class="muted">You tick the steps marked “You”</small></div>
    <ol class="t-steps">
    <?php $phase = ''; foreach ($steps as $s): $r = $rows[$s['key']] ?? null; $isDone = !empty($r['done_at']);
        if ($s['phase'] !== $phase): $phase = $s['phase']; if ($phase === 'end'): ?></ol><h3 class="t-phase">Offboarding</h3><ol class="t-steps"><?php endif; endif; ?>
      <li class="t-step<?= $isDone ? ' done' : '' ?><?= !$isDone && !empty($r['nudged_at']) ? ' nudged' : '' ?>" id="s-<?= e($s['key']) ?>">
        <span class="t-dot" aria-hidden="true"><?= $isDone ? $check : '' ?></span>
        <div class="t-body">
          <b><?= e($s['title']) ?></b> <span class="tag"><?= e(strtoupper($who[$s['type']])) ?></span>
          <?php if ($isDone): ?>
            <p class="t-small muted mono">Done <?= e(fmt_date($r['done_at'], 'M j, Y g:i a')) ?><?= $r['done_by'] === 'admin' ? ' by you' : '' ?></p>
            <?php if ($s['type'] === 'sign'): $d = $r['data']; ?>
              <dl class="facts t-sigfacts">
                <div><dt>Signed as</dt><dd><?= e($d['name'] ?? '') ?></dd></div>
                <?php if (!empty($d['legal'])): ?><div><dt>Legal name</dt><dd><?= e($d['legal']) ?></dd></div><?php endif; ?>
                <?php if (!empty($d['business'])): ?><div><dt>Business</dt><dd><?= e($d['business']) ?></dd></div><?php endif; ?>
                <?php if (!empty($d['address'])): ?><div><dt>Mailing address</dt><dd><?= nl2br(e($d['address'])) ?></dd></div><?php endif; ?>
                <?php if (!empty($d['doc'])): ?><div><dt>Document</dt><dd><?= !empty($d['doc_url']) ? '<a href="' . e($d['doc_url']) . '" target="_blank" rel="noopener noreferrer">' . e($d['doc']) . ' ↗</a>' : e($d['doc']) ?></dd></div><?php endif; ?>
                <div><dt>Statement</dt><dd><?= e($d['statement'] ?? '') ?></dd></div>
                <div><dt>Record</dt><dd class="mono t-small"><?= e($d['at'] ?? '') ?> · IP ref <?= e(substr((string)($d['ip'] ?? ''), 0, 12)) ?> · <?= e($d['ua'] ?? '') ?></dd></div>
              </dl>
            <?php elseif ($s['type'] === 'pay'): ?><p class="t-small"><?= e(TEAM_PAY[$r['data']['method'] ?? ''] ?? '') ?></p><?php endif; ?>
          <?php elseif (!empty($r['nudged_at'])): ?>
            <p class="t-small"><b class="t-nudge">They said “<?= e($s['nudge'] ?? 'done') ?>” <?= e(ago($r['nudged_at'])) ?></b></p>
          <?php endif; ?>
        </div>
        <div class="t-act">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="step" value="<?= e($s['key']) ?>">
            <?php if ($isDone): ?><button class="btn btn-ghost btn-xs" name="action" value="undo" data-confirm="Reopen “<?= e($s['title']) ?>”?<?= $s['type'] === 'sign' ? ' Their signature record stays saved; they’ll sign again.' : '' ?>">Reopen</button>
            <?php elseif ($s['type'] === 'admin'): ?><button class="btn btn-primary btn-sm" name="action" value="done">Mark done</button><?php endif; ?>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
    </ol>
  </section>

  <div style="display:grid;gap:16px;align-content:start">
    <form class="panel form" method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
      <div class="panel-h"><h2>Details</h2></div>
      <?php if ($errors): ?><div class="notice warn" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
      <div class="f"><label for="m-name">Full name</label><input id="m-name" name="name" required maxlength="120" value="<?= e($m['name']) ?>"></div>
      <div class="f"><label for="m-email">Email</label><input id="m-email" name="email" type="email" required value="<?= e($m['email']) ?>"></div>
      <div class="form-grid">
        <div class="f"><label for="m-role">Role</label><select id="m-role" name="role"><?php foreach (TEAM_ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $m['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="f"><label for="m-status">Status</label><select id="m-status" name="status"><?php foreach (TEAM_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $m['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="f"><label for="m-start">Start date</label><input id="m-start" name="start_date" type="date" value="<?= e($m['start_date']) ?>"></div>
        <div class="f"><label for="m-end">End date</label><input id="m-end" name="end_date" type="date" value="<?= e($m['end_date']) ?>"></div>
      </div>
      <?php if ($m['role'] === 'sales_rep'): ?>
      <div class="f"><label for="m-ref">Referral link</label><input id="m-ref" name="ref_link" type="url" placeholder="https://syndicatemarketing.co/?ref=CODE" value="<?= e($m['ref_link']) ?>"><p class="form-note">Add them in CRM → Referrals as a Commissioned sales rep (10%, cash) first, then paste their link. Saving it ticks their setup step.</p></div>
      <?php endif; ?>
      <div class="f"><label for="m-agr">Their own agreement link <span class="muted">(optional)</span></label><input id="m-agr" name="agreement_url" type="url" placeholder="Uses the shared link from Team if empty" value="<?= e($m['agreement_url']) ?>"></div>
      <div class="f"><label for="m-notes">Private notes</label><textarea id="m-notes" name="notes" placeholder="Rate, territory, how the welcome call went…"><?= e($m['notes']) ?></textarea></div>
      <p class="form-note">Payment method: <?= e(TEAM_PAY[$m['pay_method']] ?? 'not chosen yet') ?>. Setting the status to Leaving adds the offboarding steps; Left turns off their sign-in.</p>
      <button class="btn btn-primary btn-sm" type="submit">Save details</button>
    </form>

    <form method="post" class="danger-zone" data-confirm="Remove <?= e($m['name']) ?> and their checklist, including signature records? This can’t be undone. To keep the records, set their status to Left instead.">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
      <button class="link-danger" type="submit">Remove from team</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
