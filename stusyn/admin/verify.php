<?php
// Second sign-in step for Hiring HQ: a code from the authenticator app, or a one-time recovery code.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/totp.php';
header('X-Robots-Tag: noindex, nofollow');
start_session();
$aid = (int)($_SESSION['tfa_aid'] ?? 0);
if (!$aid || (int)($_SESSION['tfa_at'] ?? 0) < time() - 300) { unset($_SESSION['tfa_aid'], $_SESSION['tfa_at']); if ($aid) flash('That took a while, so please sign in again.'); redirect('admin/login.php'); }
$st = db()->prepare('SELECT * FROM admins WHERE id = ?'); $st->execute([$aid]); $a = $st->fetch();
if (!$a || $a['totp_secret'] === '') redirect('admin/login.php');
$error = '';
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('tfa', 6, 15)) $error = 'Too many tries. Wait 15 minutes and sign in again.';
    else {
        $code = post('code'); $done = false;
        if (($step = totp_check(totp_open($a['totp_secret']), $code, (int)$a['totp_last']))) { db()->prepare('UPDATE admins SET totp_last = ? WHERE id = ?')->execute([$step, $aid]); $done = true; }
        elseif (($left = totp_use_recovery($a['recovery_codes'], $code)) !== null) { db()->prepare('UPDATE admins SET recovery_codes = ? WHERE id = ?')->execute([$left, $aid]); $done = true; flash('You used a recovery code. ' . count(json_decode($left, true)) . ' left.'); }
        if ($done) {
            $next = (string)($_SESSION['tfa_next'] ?? '');
            unset($_SESSION['tfa_aid'], $_SESSION['tfa_at'], $_SESSION['tfa_next']);
            sign_in_admin($a);
            db()->prepare('UPDATE admins SET last_login_at = ? WHERE id = ?')->execute([now(), $aid]);
            redirect($next !== '' && preg_match('#^admin/[\w./?=&-]*$#', $next) ? $next : 'admin/');
        }
        record_attempt('tfa');
        $error = "That code didn't work. Use the newest code from your app, or a recovery code.";
    }
}
admin_header('Two-step sign-in', '', null);
?>
<form class="auth-card form spot" method="post" autocomplete="off">
  <div><h1>Enter your code</h1><p class="muted">Open your authenticator app and enter the 6-digit code for Syndicate Hiring HQ. Lost your phone? Enter a recovery code instead.</p></div>
  <?php if ($m = flash()): ?><p class="notice"><?= e($m) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="notice warn" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <div class="f"><label for="v-code">Code</label><input id="v-code" name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="20"></div>
  <button class="btn btn-primary" type="submit" style="justify-content:center">Sign in <?= ARROW ?></button>
  <p class="form-note"><a href="admin/login.php">Start over</a></p>
</form>
<?php admin_footer(); ?>
