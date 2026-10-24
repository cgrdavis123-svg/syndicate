<?php
// Creates the first account. Once an account exists, this page only resets passwords (setup.php?reset=1):
// the setup key plus the account email sends a one-time link to that inbox, so the key alone can't take over an account.
require __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store');
start_session(); // before any output: the form's CSRF token lives in the session, and hosts without output buffering can't start one later
$has = (int)val('SELECT COUNT(*) FROM users') > 0;
$reset = $has && get('reset') === '1';
if ($has && !$reset) redirect('login.php');
$tok = get('t');
$linkUser = $reset && $tok !== '' ? reset_link_user($tok) : null;
$step = !$reset ? 'create' : ($tok === '' ? 'request' : ($linkUser ? 'choose' : 'expired'));
$error = ''; $sent = false; $email = post('email'); $name = post('name');
if (is_post()) {
    $pw = function () { return strlen(post('password')) < 10 ? 'Use a password with at least 10 characters.' : (post('password') !== post('password2') ? "The passwords don't match." : ''); };
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('setup', 10, 15)) $error = 'Too many attempts. Wait 15 minutes and try again.';
    elseif ($step === 'choose') {
        if (($error = $pw()) === '') {
            $hash = password_hash(post('password'), PASSWORD_DEFAULT);
            q('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $linkUser['id']]); // also signs out every other session
            setting('reset_token', '');
            $_SESSION = []; session_regenerate_id(true);
            flash('Password reset. Sign in with your new password.'); redirect('login.php');
        }
    } elseif ($step === 'expired') $error = 'That reset link has expired or was already used.';
    elseif (!setup_key_ok(post('setup_key'))) { record_attempt('setup'); $error = "That setup key doesn't match the one in app/config.php (it must be at least 20 characters and not the sample value)."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif ($step === 'request') {
        record_attempt('setup');
        $u = one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u) send_mail($u['email'], 'Reset your Syndicate CRM password',
            "Someone (hopefully you) asked to reset the password for this CRM account.\n\nChoose a new password with this link. It works once and expires in 30 minutes:\n\n"
            . abs_url('setup.php?reset=1&t=' . reset_link_create((int)$u['id'])) . "\n\nIf this wasn't you, ignore this email and change the setup key in app/config.php: someone has it.\n");
        $sent = true;
    } elseif (mb_strlen($name) < 2) $error = 'Enter your name.';
    elseif (($error = $pw()) === '') {
        q('INSERT INTO users (name, email, password_hash, created_at, last_login_at) VALUES (?,?,?,?,?)', [$name, $email, password_hash(post('password'), PASSWORD_DEFAULT), now(), now()]);
        sign_in_user(one('SELECT * FROM users WHERE id = ?', [(int)db()->lastInsertId()]));
        flash('Welcome to Syndicate CRM.');
        redirect('./');
    }
}
layout_top($reset ? 'Reset password' : 'Set up', '', null);
$heads = [
    'create' => ['Set up your CRM', 'This only happens once. Enter the setup key from app/config.php.'],
    'request' => $sent ? ['Check your email', 'If ' . $email . ' has an account, a reset link is on its way. It works once and expires in 30 minutes.'] : ['Reset a password', 'Enter the setup key from app/config.php and the account email. We’ll email that address a link to choose a new password.'],
    'choose' => ['Choose a new password', 'For ' . ($linkUser['email'] ?? '') . '. This signs out every other device.'],
    'expired' => ['Link expired', 'That reset link has expired or was already used. Ask for a new one.'],
][$step];
?>
<form class="auth-card card" method="post" autocomplete="off">
  <a class="logo" href="./"><svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span>Syndicate<small>CRM</small></span></a>
  <div><h1><?= e($heads[0]) ?></h1><p class="muted"><?= e($heads[1]) ?></p></div>
  <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($step === 'expired'): ?>
    <a class="btn primary" href="setup.php?reset=1">Request a new link <?= ARROW ?></a>
  <?php elseif (!$sent): ?>
    <?= csrf_field() ?>
    <?php if ($step !== 'choose'): ?><label class="f"><span>Setup key</span><input name="setup_key" required></label><?php endif; ?>
    <?php if ($step === 'create'): ?><label class="f"><span>Your name</span><input name="name" required value="<?= e($name) ?>" autocomplete="name"></label><?php endif; ?>
    <?php if ($step !== 'choose'): ?><label class="f"><span>Email</span><input name="email" type="email" required value="<?= e($email) ?>" autocomplete="username"></label><?php endif; ?>
    <?php if ($step !== 'request'): ?>
      <label class="f"><span><?= $reset ? 'New password' : 'Password' ?></span><input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
      <label class="f"><span>Confirm password</span><input name="password2" type="password" required minlength="10" autocomplete="new-password"></label>
    <?php endif; ?>
    <button class="btn primary" type="submit"><?= ['create' => 'Create account', 'request' => 'Email me a reset link', 'choose' => 'Set new password'][$step] ?> <?= ARROW ?></button>
  <?php else: ?><a class="btn ghost" href="login.php">Back to sign in</a><?php endif; ?>
</form>
<?php layout_bottom(); ?>
