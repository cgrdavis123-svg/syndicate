<?php
// Second sign-in step: a passkey (Face ID, Touch ID, security key), a code from the authenticator app,
// or a one-time recovery code.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/totp.php';
require __DIR__ . '/app/passkey.php';
start_session();
$uid = (int)($_SESSION['tfa_uid'] ?? 0);
if (!$uid || (int)($_SESSION['tfa_at'] ?? 0) < time() - 300) { unset($_SESSION['tfa_uid'], $_SESSION['tfa_at']); if ($uid) flash('That took a while, so please sign in again.'); redirect('login.php'); }
$u = one('SELECT * FROM users WHERE id = ?', [$uid]);
if (!$u || !tfa_on($u)) redirect('login.php');
$hasApp = $u['totp_secret'] !== ''; $hasKey = pk_list($u) !== [];
$error = '';
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('tfa', 6, 15)) $error = 'Too many tries. Wait 15 minutes and sign in again.';
    else {
        $code = post('code'); $done = false;
        if ($hasApp && preg_match('/^\D*(\d\D*){6}$/', $code) && ($st = totp_check(totp_open($u['totp_secret']), $code, (int)$u['totp_last']))) { q('UPDATE users SET totp_last = ? WHERE id = ?', [$st, $uid]); $done = true; }
        elseif (($left = totp_use_recovery($u['recovery_codes'], $code)) !== null) { q('UPDATE users SET recovery_codes = ? WHERE id = ?', [$left, $uid]); $done = true; flash('You used a recovery code. ' . count(json_decode($left, true)) . ' left. Make new ones in Settings if you’re running low.'); }
        if ($done) {
            unset($_SESSION['tfa_uid'], $_SESSION['tfa_at']);
            sign_in_user($u);
            q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $uid]);
            redirect('./');
        }
        record_attempt('tfa');
        $error = $hasApp ? 'That code didn’t work. Use the newest code from your app, or a recovery code.' : 'That recovery code didn’t work.';
    }
}
layout_top('Two-step sign-in', '', null);
?>
<div class="auth-card card">
  <div><h1><?= $hasKey ? 'Confirm it’s you' : 'Enter your code' ?></h1><p class="muted"><?= $hasKey ? 'Use your passkey: Face ID or Touch ID on your iPhone or Mac, or your security key.' . ($hasApp ? ' Or enter the 6-digit code from your authenticator app.' : '') : 'Open your authenticator app and enter the 6-digit code for Syndicate CRM.' ?></p></div>
  <?php if ($m = flash()): ?><p class="notice"><?= e($m) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <p class="notice bad" data-passkey-msg role="alert" hidden></p>
  <?php if ($hasKey): ?>
    <button class="btn primary" type="button" data-passkey-login data-endpoint="passkey.php" data-csrf="<?= e(csrf_token()) ?>" autofocus>Use a passkey <?= ARROW ?></button>
  <?php endif; ?>
  <form method="post" autocomplete="off" style="display:grid;gap:12px">
    <?= csrf_field() ?>
    <?php if ($hasKey): ?><details<?= $error ? ' open' : '' ?>><summary class="muted small"><?= $hasApp ? 'Use a code instead' : 'Lost your device? Use a recovery code' ?></summary><div style="display:grid;gap:12px;margin-top:10px"><?php endif; ?>
    <label class="f"><span><?= $hasApp ? 'Code' : 'Recovery code' ?></span><input name="code" <?= $hasKey ? '' : 'required autofocus' ?> inputmode="<?= $hasApp ? 'numeric' : 'text' ?>" autocomplete="one-time-code" maxlength="20"></label>
    <button class="btn <?= $hasKey ? 'ghost' : 'primary' ?>" type="submit">Sign in <?= ARROW ?></button>
    <?php if ($hasApp): ?><p class="muted small">Lost your phone? Enter one of your recovery codes instead.</p><?php endif; ?>
    <?php if ($hasKey): ?></div></details><?php endif; ?>
  </form>
  <p class="muted small"><a href="login.php">Start over</a></p>
</div>
<?php if ($hasKey): ?><script src="assets/js/passkey.js" defer></script><?php endif; ?>
<?php layout_bottom(); ?>
