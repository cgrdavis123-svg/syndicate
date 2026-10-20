<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();
$error = '';

if (is_post()) {
    $action = post('action');
    if ($action === 'password') {
        $st = $db->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([$admin['id']]);
        if (!password_verify(post('current'), (string)$st->fetchColumn())) $error = "Your current password isn't right.";
        elseif (strlen(post('new')) < 10) $error = 'Use a new password with at least 10 characters.';
        elseif (post('new') !== post('new2')) $error = "The new passwords don't match.";
        else {
            $hash = password_hash(post('new'), PASSWORD_DEFAULT);
            $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $admin['id']]);
            sign_in_admin(['id' => $admin['id'], 'password_hash' => $hash]); // other devices are signed out
            flash('Password updated.');
            redirect('admin/account.php');
        }
    } elseif (in_array($action, ['tfa_start', 'tfa_confirm', 'tfa_off', 'tfa_codes'], true)) {
        require_once __DIR__ . '/../app/totp.php';
        $st = $db->prepare('SELECT * FROM admins WHERE id = ?'); $st->execute([$admin['id']]); $me = $st->fetch();
        if ($action === 'tfa_start') { $_SESSION['tfa_setup'] = totp_new_secret(); redirect('admin/account.php#tfa'); }
        if (too_many('tfa_settings', 10, 15)) $error = 'Too many tries. Wait 15 minutes and try again.';
        elseif ($action === 'tfa_confirm') {
            $sec = (string)($_SESSION['tfa_setup'] ?? '');
            if ($sec === '' || !($step = totp_check($sec, post('code')))) { record_attempt('tfa_settings'); $error = "That code didn't match. Check your phone's clock is set automatically, and try the newest code."; }
            else { [$plain, $hashes] = totp_recovery_codes(); $db->prepare('UPDATE admins SET totp_secret = ?, totp_last = ?, recovery_codes = ? WHERE id = ?')->execute([totp_seal($sec), $step, $hashes, $admin['id']]); unset($_SESSION['tfa_setup']); $_SESSION['tfa_codes'] = $plain; flash('Two-step sign-in is on.'); redirect('admin/account.php#tfa'); }
        } else {
            $step = $me['totp_secret'] !== '' ? totp_check(totp_open($me['totp_secret']), post('code'), (int)$me['totp_last']) : 0;
            if (!$step) { record_attempt('tfa_settings'); $error = "That code didn't match."; }
            elseif ($action === 'tfa_off') {
                if (!password_verify(post('current'), $me['password_hash'])) { record_attempt('tfa_settings'); $error = "Your password isn't right."; }
                else { $db->prepare("UPDATE admins SET totp_secret = '', totp_last = 0, recovery_codes = '[]' WHERE id = ?")->execute([$admin['id']]); flash('Two-step sign-in is off.'); redirect('admin/account.php#tfa'); }
            } else { [$plain, $hashes] = totp_recovery_codes(); $db->prepare('UPDATE admins SET totp_last = ?, recovery_codes = ? WHERE id = ?')->execute([$step, $hashes, $admin['id']]); $_SESSION['tfa_codes'] = $plain; flash('New recovery codes made. The old ones no longer work.'); redirect('admin/account.php#tfa'); }
        }
    } elseif ($action === 'add') {
        $email = post('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email for the new admin.';
        elseif (strlen(post('password')) < 10) $error = "Use a password with at least 10 characters for the new admin.";
        else {
            try {
                $db->prepare('INSERT INTO admins (email, password_hash, created_at) VALUES (?,?,?)')->execute([$email, password_hash(post('password'), PASSWORD_DEFAULT), now()]);
                flash("$email can now sign in. Share their password with them privately.");
                redirect('admin/account.php');
            } catch (PDOException $ex) { $error = 'That email already has an admin account.'; }
        }
    } elseif ($action === 'remove') {
        $rid = (int)post('id');
        if ($rid === (int)$admin['id']) $error = "You can't remove your own account.";
        else { $db->prepare('DELETE FROM admins WHERE id = ?')->execute([$rid]); flash('Admin removed.'); redirect('admin/account.php'); }
    }
}
$admins = $db->query('SELECT id, email, created_at, last_login_at FROM admins ORDER BY id')->fetchAll();
admin_header('Account', 'account', $admin);
?>
<div class="adm-head"><div><span class="eyebrow"><span class="dot"></span>Signed in as <?= e($admin['email']) ?></span><h1>Account</h1></div></div>
<?php if ($error): ?><p class="notice warn" role="alert"><?= e($error) ?></p><?php endif; ?>

<div class="adm-grid">
  <form class="panel" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="panel-h"><h2>Change password</h2></div>
    <div class="f"><label for="p-cur">Current password</label><input id="p-cur" name="current" type="password" required autocomplete="current-password"></div>
    <div class="f"><label for="p-new">New password</label><input id="p-new" name="new" type="password" required minlength="10" autocomplete="new-password"></div>
    <div class="f"><label for="p-new2">Confirm new password</label><input id="p-new2" name="new2" type="password" required minlength="10" autocomplete="new-password"></div>
    <button class="btn btn-primary btn-sm" type="submit">Update password</button>
  </form>
  <?php $st = $db->prepare('SELECT totp_secret, recovery_codes FROM admins WHERE id = ?'); $st->execute([$admin['id']]); $me = $st->fetch(); $setup = (string)($_SESSION['tfa_setup'] ?? ''); $codes = $_SESSION['tfa_codes'] ?? null; unset($_SESSION['tfa_codes']); ?>
  <section class="panel" id="tfa">
    <div class="panel-h"><h2>Two-step sign-in</h2><span class="tag<?= $me['totp_secret'] !== '' ? ' acc">ON' : '">OFF' ?></span></div>
    <?php if ($codes): ?><p class="notice">Save these recovery codes somewhere safe, like your password manager. Each works once if you lose your phone. They won&rsquo;t be shown again.</p><pre class="tfa-codes"><?= e(implode("\n", $codes)) ?></pre><?php endif; ?>
    <?php if ($me['totp_secret'] !== ''): ?>
      <p class="form-note">Signing in asks for a code from your authenticator app. <?= count(json_decode($me['recovery_codes'] ?: '[]', true) ?: []) ?> recovery codes left.</p>
      <form method="post" class="tfa-row"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_codes"><input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" aria-label="Code from your app" required><button class="btn btn-ghost btn-sm" type="submit">New recovery codes</button></form>
      <details><summary class="form-note">Turn it off</summary><form method="post" class="tfa-row"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_off"><input name="current" type="password" placeholder="Password" aria-label="Password" required autocomplete="current-password"><input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" aria-label="Code from your app" required><button class="link-danger" type="submit">Turn off</button></form></details>
    <?php elseif ($setup !== ''): require_once __DIR__ . '/../app/totp.php'; ?>
      <p>1. Open an authenticator app (1Password, Bitwarden, Google Authenticator, Authy) and scan this code.</p>
      <div class="qr" data-qr="<?= e(totp_uri($setup, $admin['email'], 'Syndicate Hiring HQ')) ?>" role="img" aria-label="QR code for your authenticator app"></div>
      <p class="form-note">Can&rsquo;t scan? Enter this key: <code><?= e(implode(' ', str_split($setup, 4))) ?></code></p>
      <form method="post" class="tfa-row"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_confirm"><input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="2. Enter the 6-digit code" aria-label="6-digit code" required><button class="btn btn-primary btn-sm" type="submit">Turn on</button></form>
      <script src="assets/js/qrcode.js" defer></script>
    <?php else: ?>
      <p class="form-note">Adds a code from your phone to every sign-in, so a stolen password isn&rsquo;t enough to see applicants&rsquo; details.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_start"><button class="btn btn-primary btn-sm" type="submit">Set up two-step sign-in</button></form>
    <?php endif; ?>
  </section>

  <div style="display:grid;gap:16px;align-content:start">
    <section class="panel">
      <div class="panel-h"><h2>Admins</h2></div>
      <ul class="list">
        <?php foreach ($admins as $a): ?>
          <li><div class="li-row"><span class="av"><?= e(strtoupper(mb_substr($a['email'], 0, 1))) ?></span><span class="grow"><b><?= e($a['email']) ?></b><small>Last sign-in: <?= $a['last_login_at'] ? e(ago($a['last_login_at'])) : 'never' ?></small></span>
            <?php if ((int)$a['id'] !== (int)$admin['id']): ?><form method="post" data-confirm="Remove <?= e($a['email']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link-danger" type="submit">Remove</button></form><?php else: ?><span class="tag acc">YOU</span><?php endif; ?>
          </div></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <form class="panel" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="panel-h"><h2>Add an admin</h2></div>
      <div class="f"><label for="n-email">Email</label><input id="n-email" name="email" type="email" required autocomplete="off"></div>
      <div class="f"><label for="n-pass">Temporary password</label><input id="n-pass" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
      <button class="btn btn-ghost btn-sm" type="submit">Add admin</button>
    </form>
  </div>
</div>
<?php admin_footer(); ?>
