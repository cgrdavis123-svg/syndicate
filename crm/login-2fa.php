<?php
// Second sign-in step: a code from the authenticator app, or a one-time recovery code.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/totp.php';
start_session();
$uid = (int)($_SESSION['tfa_uid'] ?? 0);
if (!$uid || (int)($_SESSION['tfa_at'] ?? 0) < time() - 300) { unset($_SESSION['tfa_uid'], $_SESSION['tfa_at']); if ($uid) flash('That took a while, so please sign in again.'); redirect('login.php'); }
$u = one('SELECT * FROM users WHERE id = ?', [$uid]);
if (!$u || $u['totp_secret'] === '') redirect('login.php');
$error = '';
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('tfa', 6, 15)) $error = 'Too many tries. Wait 15 minutes and sign in again.';
    else {
        $code = post('code'); $done = false;
        if (preg_match('/^\D*(\d\D*){6}$/', $code) && ($st = totp_check(totp_open($u['totp_secret']), $code, (int)$u['totp_last']))) { q('UPDATE users SET totp_last = ? WHERE id = ?', [$st, $uid]); $done = true; }
        elseif (($left = totp_use_recovery($u['recovery_codes'], $code)) !== null) { q('UPDATE users SET recovery_codes = ? WHERE id = ?', [$left, $uid]); $done = true; flash('You used a recovery code. ' . count(json_decode($left, true)) . ' left. Make new ones in Settings if you’re running low.'); }
        if ($done) {
            unset($_SESSION['tfa_uid'], $_SESSION['tfa_at']);
            sign_in_user($u);
            q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $uid]);
            redirect('./');
        }
        record_attempt('tfa');
        $error = 'That code didn’t work. Use the newest code from your app, or a recovery code.';
    }
}
layout_top('Two-step sign-in', '', null);
?>
<form class="auth-card card" method="post" autocomplete="off">
  <div><h1>Enter your code</h1><p class="muted">Open your authenticator app and enter the 6-digit code for Syndicate CRM. Lost your phone? Enter one of your recovery codes instead.</p></div>
  <?php if ($m = flash()): ?><p class="notice"><?= e($m) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label class="f"><span>Code</span><input name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="20"></label>
  <button class="btn primary" type="submit">Sign in <?= ARROW ?></button>
  <p class="muted small"><a href="login.php">Start over</a></p>
</form>
<?php layout_bottom(); ?>
