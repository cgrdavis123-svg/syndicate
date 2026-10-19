<?php
require __DIR__ . '/app/bootstrap.php';
if ((int)val('SELECT COUNT(*) FROM users') === 0) redirect('setup.php');
if (current_user()) redirect('./');
$error = ''; $email = post('email');
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('login', 8, 15)) $error = 'Too many sign-in attempts. Wait 15 minutes and try again.';
    else {
        $u = one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u && password_verify(post('password'), $u['password_hash'])) {
            if ($u['totp_secret'] !== '') { session_regenerate_id(true); $_SESSION['tfa_uid'] = (int)$u['id']; $_SESSION['tfa_at'] = time(); redirect('login-2fa.php'); }
            sign_in_user($u);
            q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
            redirect('./');
        }
        record_attempt('login');
        $error = "That email and password don't match.";
    }
}
layout_top('Sign in', '', null);
?>
<form class="auth-card card" method="post">
  <a class="logo" href="./"><svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span>Syndicate<small>CRM</small></span></a>
  <div><h1>Sign in</h1><p class="muted"><?= defined('ADS_APP') ? 'Ads management for every client.' : (defined('WIKI_APP') ? 'Team docs and the help center editor.' : 'Leads, clients, consultations and invoices.') ?></p></div>
  <?php if ($m = flash()): ?><p class="notice"><?= e($m) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label class="f"><span>Email</span><input name="email" type="email" required value="<?= e($email) ?>" autocomplete="username" autofocus></label>
  <label class="f"><span>Password</span><input name="password" type="password" required autocomplete="current-password"></label>
  <button class="btn primary" type="submit">Sign in <?= ARROW ?></button>
  <p class="muted small">Forgot your password? <a href="<?= e(abs_url('setup.php?reset=1')) ?>">Reset it with your setup key</a><?php if (defined('ADS_APP')): ?><br>A client? <a href="portal-login.php">Sign in to your ads portal</a>.<?php endif; ?>.</p>
</form>
<?php layout_bottom(); ?>
