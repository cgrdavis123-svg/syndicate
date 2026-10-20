<?php
// Accepting a team invite from users.php: the emailed link (join.php?t=…) lets the person choose a name and password.
// Links work once and expire after 7 days.
require __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$tok = get('t');
$inv = invite_lookup($tok);
$error = ''; $name = post('name', $inv['name'] ?? '');
if ($inv && is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('join', 10, 15)) $error = 'Too many attempts. Wait 15 minutes and try again.';
    elseif (mb_strlen($name) < 2) $error = 'Enter your name.';
    elseif (strlen(post('password')) < 10) $error = 'Use a password with at least 10 characters.';
    elseif (post('password') !== post('password2')) $error = 'The passwords don’t match.';
    elseif (val('SELECT 1 FROM users WHERE email = ?', [$inv['email']])) $error = 'That email already has an account. Sign in instead.';
    else {
        record_attempt('join');
        q('INSERT INTO users (name, email, password_hash, role, created_at, last_login_at) VALUES (?,?,?,?,?,?)', [mb_substr($name, 0, 80), $inv['email'], password_hash(post('password'), PASSWORD_DEFAULT), $inv['role'], now(), now()]);
        $uid = (int)db()->lastInsertId();
        q('UPDATE user_invites SET accepted_at = ? WHERE id = ?', [now(), $inv['id']]);
        sign_in_user(one('SELECT * FROM users WHERE id = ?', [$uid]));
        $to = setting('shop_notify') ?: setting('business_email');
        if ($to) send_mail($to, $name . ' joined the CRM', $name . ' (' . $inv['email'] . ') accepted the invite from ' . $inv['invited_by'] . ' and can now sign in to the CRM, ads and wiki as ' . strtolower(USER_ROLES[$inv['role']] ?? 'member') . ".\n\nManage the team: " . abs_url('users.php') . "\n");
        flash('Welcome, ' . explode(' ', $name)[0] . '. Set up two-step sign-in now: a passkey with Face ID or Touch ID takes about a minute.');
        redirect('settings.php#tfa');
    }
}
layout_top('Join the team', '', null);
?>
<form class="auth-card card" method="post" autocomplete="off">
  <a class="logo" href="./"><svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span>Syndicate<small>CRM</small></span></a>
  <?php if (!$inv): ?>
    <div><h1>Invite expired</h1><p class="muted">That invite link has expired or was already used. Ask whoever invited you to send a new one.</p></div>
    <a class="btn ghost" href="login.php">Go to sign in</a>
  <?php else: ?>
    <div><h1>Join the team</h1><p class="muted"><?= e($inv['invited_by']) ?> invited <?= e($inv['email']) ?> to Syndicate CRM. One login covers the CRM, ads and wiki.</p></div>
    <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="username" value="<?= e($inv['email']) ?>" autocomplete="username">
    <label class="f"><span>Your name</span><input name="name" required value="<?= e($name) ?>" autocomplete="name" autofocus></label>
    <label class="f"><span>Password</span><input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
    <label class="f"><span>Confirm password</span><input name="password2" type="password" required minlength="10" autocomplete="new-password"></label>
    <button class="btn primary" type="submit">Create my login <?= ARROW ?></button>
  <?php endif; ?>
</form>
<?php layout_bottom(); ?>
