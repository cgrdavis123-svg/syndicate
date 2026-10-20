<?php
// Team: everyone who can sign in to the CRM, ads and wiki. Owners invite people by email, change roles,
// remove people, clear a teammate's two-step sign-in when they lose their phone, and can make two-step a must.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/passkey.php';
$user = require_user();
$owner = $user['role'] === 'owner';
$error = '';
$owners = function () { return (int)val("SELECT COUNT(*) FROM users WHERE role = 'owner'"); };
if (is_post()) {
    $a = post('action');
    require_owner($user);
    $me = one('SELECT * FROM users WHERE id = ?', [$user['id']]);
    $target = (int)post('id') ? one('SELECT * FROM users WHERE id = ?', [(int)post('id')]) : null;
    if (in_array($a, ['remove', 'reset_2fa'], true) && (too_many('team', 10, 15) || !password_verify(post('current'), $me['password_hash']))) {
        record_attempt('team'); $error = 'Your password isn’t right.';
    } elseif ($a === 'invite') {
        $email = strtolower(post('email')); $name = mb_substr(post('name'), 0, 80); $role = isset(USER_ROLES[post('role')]) ? post('role') : 'member';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
        elseif (val('SELECT 1 FROM users WHERE email = ?', [$email])) $error = $email . ' already has an account.';
        else {
            q('DELETE FROM user_invites WHERE email = ? AND accepted_at IS NULL', [$email]);
            $tok = bin2hex(random_bytes(24));
            q('INSERT INTO user_invites (email, name, role, token_hash, invited_by, created_at, expires_at) VALUES (?,?,?,?,?,?,?)', [$email, $name, $role, hash('sha256', $tok), $user['name'], now(), time() + 7 * 86400]);
            $sent = send_mail($email, $user['name'] . ' invited you to Syndicate CRM',
                'Hi' . ($name !== '' ? ' ' . explode(' ', $name)[0] : '') . ",\n\n" . $user['name'] . " added you to the Syndicate CRM team (" . USER_ROLES[$role] . ").\n\nChoose your password with this link. It works once and expires in 7 days:\n\n"
                . abs_url('join.php?t=' . $tok) . "\n\nOne login covers the CRM, ads and the wiki. After you sign in, set up two-step sign-in in Settings (a passkey with Face ID or Touch ID takes about a minute).\n\nIf you weren't expecting this, you can ignore it.\n", setting('business_email'));
            flash($sent ? 'Invite sent to ' . $email . '.' : 'The invite was saved but the email couldn’t be sent. Check the mail settings on your host.');
            redirect('users.php');
        }
    } elseif ($a === 'cancel_invite') { q('DELETE FROM user_invites WHERE id = ? AND accepted_at IS NULL', [(int)post('invite')]); flash('Invite cancelled. Its link no longer works.'); redirect('users.php'); }
    elseif ($a === 'require_2fa') {
        $on = post('on') === '1';
        if ($on && !tfa_on($me)) $error = 'Turn on two-step sign-in for yourself first (Settings).';
        else { setting('require_2fa', $on ? '1' : '0'); flash($on ? 'Two-step sign-in is now required for everyone.' : 'Two-step sign-in is now optional.'); redirect('users.php'); }
    }
    elseif (!$target) $error = 'That person isn’t on the team any more.';
    elseif ($a === 'role') {
        $role = isset(USER_ROLES[post('role')]) ? post('role') : 'member';
        if ($target['role'] === 'owner' && $role !== 'owner' && $owners() < 2) $error = 'The team needs at least one owner.';
        else { q('UPDATE users SET role = ? WHERE id = ?', [$role, $target['id']]); flash($target['name'] . ' is now ' . (USER_ROLES[$role] === 'Owner' ? 'an owner' : 'a member') . '.'); redirect('users.php'); }
    } elseif ($a === 'remove') {
        if ((int)$target['id'] === (int)$user['id']) $error = 'You can’t remove yourself. Ask another owner.';
        elseif ($target['role'] === 'owner' && $owners() < 2) $error = 'The team needs at least one owner.';
        else { q('DELETE FROM users WHERE id = ?', [$target['id']]); flash($target['name'] . ' was removed and signed out everywhere.'); redirect('users.php'); }
    } elseif ($a === 'reset_2fa') {
        if ((int)$target['id'] === (int)$user['id']) $error = 'Change your own two-step sign-in in Settings.';
        else { q("UPDATE users SET totp_secret = '', totp_last = 0, recovery_codes = '[]', passkeys = '[]' WHERE id = ?", [$target['id']]); flash('Two-step sign-in cleared for ' . $target['name'] . '. They can sign in with their password and set it up again.'); redirect('users.php'); }
    }
}
$team = all('SELECT id, name, email, role, totp_secret, passkeys, created_at, last_login_at FROM users ORDER BY role = \'owner\' DESC, name COLLATE NOCASE');
$invites = all('SELECT * FROM user_invites WHERE accepted_at IS NULL AND expires_at > ? ORDER BY id DESC', [time()]);
$req = setting('require_2fa') === '1';
layout_top('Team', 'users', $user);
?>
<div class="page-head"><div><p class="eyebrow">Signed in as <?= e($user['email']) ?> · <?= e(USER_ROLES[$user['role']] ?? '') ?></p><h1>Team</h1><p class="muted">Everyone here signs in to the CRM, ads and wiki with their own email and password. Owners manage the team; members can use everything else.</p></div></div>
<?php if ($m = flash()): ?><p class="notice"><?= e($m) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>People</h2><span class="muted small"><?= count($team) ?></span></div>
    <ul class="pk-list">
    <?php foreach ($team as $t): $tfa = tfa_on($t); $self = (int)$t['id'] === (int)$user['id']; ?>
      <li><span><b><?= e($t['name']) ?><?= $self ? ' <span class="muted small">(you)</span>' : '' ?></b><span class="muted small"><?= e($t['email']) ?> · <?= e(USER_ROLES[$t['role']] ?? $t['role']) ?> · <?= $tfa ? 'two-step on' : '<span style="color:var(--red)">two-step off</span>' ?> · <?= $t['last_login_at'] ? 'last sign-in ' . e(fmt_date($t['last_login_at'])) : 'never signed in' ?></span></span>
        <?php if ($owner && !$self): ?>
        <details><summary class="muted small">Manage</summary><div style="display:grid;gap:10px;margin-top:10px">
          <form method="post" class="row" style="flex-wrap:wrap;gap:8px"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><select name="role" aria-label="Role"><?php foreach (USER_ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $t['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><button class="btn ghost xs" type="submit">Change role</button></form>
          <?php if ($tfa): ?><form method="post" class="row" style="flex-wrap:wrap;gap:8px"><?= csrf_field() ?><input type="hidden" name="action" value="reset_2fa"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input name="current" type="password" placeholder="Your password" aria-label="Your password" required autocomplete="current-password"><button class="btn ghost xs" type="submit">Clear their two-step (lost phone)</button></form><?php endif; ?>
          <form method="post" class="row" style="flex-wrap:wrap;gap:8px" data-confirm="Remove <?= e($t['name']) ?>? They’ll be signed out everywhere."><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input name="current" type="password" placeholder="Your password" aria-label="Your password" required autocomplete="current-password"><button class="link-danger" type="submit">Remove from team</button></form>
        </div></details>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
  </section>
  <div style="display:grid;gap:16px;align-content:start">
    <?php if ($owner): ?>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="invite">
      <div class="card-h"><h2>Invite someone</h2></div>
      <label class="f"><span>Name</span><input name="name" maxlength="80" autocomplete="off"></label>
      <label class="f"><span>Email</span><input name="email" type="email" required autocomplete="off"></label>
      <label class="f"><span>Role</span><select name="role"><option value="member">Member: uses the CRM, ads and wiki</option><option value="owner">Owner: also manages the team</option></select></label>
      <button class="btn primary sm" type="submit">Send invite</button>
      <p class="muted small">They get an email from <?= e(setting('mail_from') ?: 'hello@stusyn.com') ?> with a link to choose their password. It works once and expires in 7 days.</p>
    </form>
    <?php endif; ?>
    <?php if ($invites): ?>
    <section class="card"><div class="card-h"><h2>Waiting to join</h2></div>
      <ul class="pk-list"><?php foreach ($invites as $i): ?>
        <li><span><b><?= e($i['name'] ?: $i['email']) ?></b><span class="muted small"><?= e($i['email']) ?> · <?= e(USER_ROLES[$i['role']] ?? $i['role']) ?> · invited by <?= e($i['invited_by']) ?>, expires <?= e(date('M j', (int)$i['expires_at'])) ?></span></span>
          <?php if ($owner): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_invite"><input type="hidden" name="invite" value="<?= (int)$i['id'] ?>"><button class="link-danger" type="submit">Cancel</button></form><?php endif; ?></li>
      <?php endforeach; ?></ul>
      <?php if ($owner): ?><p class="muted small">To resend, invite the same email again; the old link stops working.</p><?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="card"><div class="card-h"><h2>Two-step sign-in for everyone</h2><?= $req ? '<span class="tag lime">Required</span>' : '<span class="tag">Optional</span>' ?></div>
      <p class="muted small"><?= $req ? 'Anyone without a passkey or authenticator app is sent to Settings to set one up before they can use anything else.' : 'When required, anyone without a passkey or authenticator app has to set one up right after signing in.' ?></p>
      <?php if ($owner): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="require_2fa"><input type="hidden" name="on" value="<?= $req ? '0' : '1' ?>"><button class="btn <?= $req ? 'ghost' : 'primary' ?> sm" type="submit"><?= $req ? 'Make it optional' : 'Require it for everyone' ?></button></form><?php endif; ?>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
