<?php
// Creates the first admin account. Once an admin exists, this page only resets a password (admin/setup.php?reset=1):
// the setup key plus the admin's email sends a one-time link to that inbox, so the key alone can't take over an account.
require __DIR__ . '/../app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$hasAdmin = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
$reset = $hasAdmin && get('reset') === '1';
if ($hasAdmin && !$reset) redirect('admin/login.php');
$tok = get('t');
$linkAdmin = $reset && $tok !== '' ? reset_link_admin($tok) : null;
$step = !$reset ? 'create' : ($tok === '' ? 'request' : ($linkAdmin ? 'choose' : 'expired'));

$error = ''; $sent = false;
$email = post('email');
if (is_post()) {
    $pw = function () { return strlen(post('password')) < 10 ? 'Use a password with at least 10 characters.' : (post('password') !== post('password2') ? "The passwords don't match." : ''); };
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('setup', 10, 15)) $error = 'Too many attempts. Wait 15 minutes and try again.';
    elseif ($step === 'choose') {
        if (($error = $pw()) === '') {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash(post('password'), PASSWORD_DEFAULT), $linkAdmin['id']]); // also signs out every other session
            reset_link_clear();
            $_SESSION = []; session_regenerate_id(true);
            flash('Password reset. Sign in with your new password.'); redirect('admin/login.php');
        }
    } elseif ($step === 'expired') $error = 'That reset link has expired or was already used.';
    elseif (!setup_key_ok(post('setup_key'))) { record_attempt('setup'); $error = "That setup key doesn't match the one in app/config.php (it must be at least 20 characters and not the sample value)."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif ($step === 'request') {
        record_attempt('setup');
        $st = db()->prepare('SELECT * FROM admins WHERE email = ?'); $st->execute([$email]);
        if ($a = $st->fetch()) send_mail($a['email'], 'Reset your Hiring HQ password',
            "Someone (hopefully you) asked to reset the password for this Hiring HQ account.\n\nChoose a new password with this link. It works once and expires in 30 minutes:\n\n"
            . abs_url('admin/setup.php?reset=1&t=' . reset_link_create((int)$a['id'])) . "\n\nIf this wasn't you, ignore this email and change the setup key in app/config.php: someone has it.\n");
        $sent = true;
    } elseif (($error = $pw()) === '') {
        db()->prepare('INSERT INTO admins (email, password_hash, created_at, last_login_at) VALUES (?,?,?,?)')
            ->execute([$email, $hash = password_hash(post('password'), PASSWORD_DEFAULT), now(), now()]);
        sign_in_admin(['id' => (int)db()->lastInsertId(), 'password_hash' => $hash]);
        flash('Welcome! Your admin account is ready.');
        redirect('admin/');
    }
}
admin_header($reset ? 'Reset password' : 'Set up', '', null);
$heads = [
    'create' => ['Create your admin account', 'This only happens once. You’ll use this account to manage jobs and review applicants.'],
    'request' => $sent ? ['Check your email', 'If ' . $email . ' is an admin, a reset link is on its way. It works once and expires in 30 minutes.'] : ['Reset a password', 'Enter the setup key from app/config.php and the admin’s email. We’ll email that address a link to choose a new password.'],
    'choose' => ['Choose a new password', 'For ' . ($linkAdmin['email'] ?? '') . '. This signs out every other device.'],
    'expired' => ['Link expired', 'That reset link has expired or was already used. Ask for a new one.'],
][$step];
?>
<form class="auth-card form spot" method="post" autocomplete="off">
  <a href="./" class="brand"><svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span class="brand-name">Syndicate<small>Hiring HQ</small></span></a>
  <div><h1><?= e($heads[0]) ?></h1><p class="muted"><?= e($heads[1]) ?></p></div>
  <?php if ($error): ?><p class="notice warn" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($step === 'expired'): ?>
  <a class="btn btn-primary" href="admin/setup.php?reset=1" style="justify-content:center">Request a new link <?= ARROW ?></a>
  <?php elseif (!$sent): ?>
  <?= csrf_field() ?>
  <?php if ($step !== 'choose'): ?>
  <div class="f"><label for="s-key">Setup key</label><input id="s-key" name="setup_key" required placeholder="From app/config.php"></div>
  <div class="f"><label for="s-email"><?= $reset ? 'Admin email' : 'Your email' ?></label><input id="s-email" name="email" type="email" required value="<?= e($email) ?>" autocomplete="username"></div>
  <?php endif; ?>
  <?php if ($step !== 'request'): ?>
  <div class="f"><label for="s-pass"><?= $reset ? 'New password' : 'Password' ?></label><input id="s-pass" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
  <div class="f"><label for="s-pass2">Confirm password</label><input id="s-pass2" name="password2" type="password" required minlength="10" autocomplete="new-password"></div>
  <?php endif; ?>
  <button class="btn btn-primary" type="submit" style="justify-content:center"><?= ['create' => 'Create account', 'request' => 'Email me a reset link', 'choose' => 'Set new password'][$step] ?> <?= ARROW ?></button>
  <?php else: ?>
  <a class="btn btn-ghost" href="admin/login.php" style="justify-content:center">Back to sign in</a>
  <?php endif; ?>
</form>
<?php admin_footer(); ?>
