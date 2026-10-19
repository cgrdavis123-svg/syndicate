<?php
require __DIR__ . '/../app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

if ((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) redirect('admin/setup.php');
if (current_admin()) redirect('admin/');

$error = '';
$email = post('email');
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('login', 8, 15)) $error = 'Too many sign-in attempts. Wait 15 minutes and try again.';
    else {
        $st = db()->prepare('SELECT * FROM admins WHERE email = ?');
        $st->execute([$email]);
        $a = $st->fetch();
        if ($a && password_verify(post('password'), $a['password_hash'])) {
            if ($a['totp_secret'] !== '') { session_regenerate_id(true); $_SESSION['tfa_aid'] = (int)$a['id']; $_SESSION['tfa_at'] = time(); $_SESSION['tfa_next'] = get('next'); redirect('admin/verify.php'); }
            sign_in_admin($a);
            db()->prepare('UPDATE admins SET last_login_at = ? WHERE id = ?')->execute([now(), $a['id']]);
            $next = get('next');
            redirect($next !== '' && preg_match('#^admin/[\w./?=&-]*$#', $next) ? $next : 'admin/');
        }
        record_attempt('login');
        $error = "That email and password don't match.";
    }
}
admin_header('Sign in', '', null);
?>
<form class="auth-card form spot" method="post">
  <a href="./" class="brand"><svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span class="brand-name">Syndicate<small>Hiring HQ</small></span></a>
  <div><h1>Sign in</h1><p class="muted">Manage jobs, applicants and messages.</p></div>
  <?php if ($error): ?><p class="notice warn" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <div class="f"><label for="l-email">Email</label><input id="l-email" name="email" type="email" required value="<?= e($email) ?>" autocomplete="username" autofocus></div>
  <div class="f"><label for="l-pass">Password</label><input id="l-pass" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary" type="submit" style="justify-content:center">Sign in <?= ARROW ?></button>
  <p class="form-note">Forgot your password? <a href="admin/setup.php?reset=1" style="color:var(--text)">Reset it with your setup key</a>.</p>
</form>
<?php admin_footer(); ?>
