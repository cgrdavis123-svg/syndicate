<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$fields = ['business_name' => 'Business name', 'business_email' => 'Email on invoices', 'business_location' => 'Location on invoices', 'business_website' => 'Website on invoices', 'default_rate' => 'Default consultation rate ($/hr)', 'invoice_prefix' => 'Invoice number prefix', 'invoice_next' => 'Next invoice number', 'invoice_terms_days' => 'Payment terms (days)'];
$error = '';
if (is_post()) {
    $a = post('action');
    if ($a === 'business') {
        foreach ($fields as $k => $l) setting($k, mb_substr(post($k), 0, 200));
        setting('payment_instructions', mb_substr(post('payment_instructions'), 0, 2000));
        setting('invoice_footer', mb_substr(post('invoice_footer'), 0, 500));
        $pl = post('payment_link_default'); if ($pl !== '' && !preg_match('#^https?://#i', $pl)) $pl = 'https://' . $pl;
        setting('payment_link_default', filter_var($pl, FILTER_VALIDATE_URL) ? mb_substr($pl, 0, 500) : '');
        setting('mail_from', filter_var(post('mail_from'), FILTER_VALIDATE_EMAIL) ? post('mail_from') : setting('mail_from'));
        setting('default_rate', (string)max(0, (float)post('default_rate')));
        setting('invoice_next', (string)max(1, (int)post('invoice_next')));
        setting('invoice_terms_days', (string)max(0, (int)post('invoice_terms_days')));
        flash('Settings saved.'); redirect('settings.php');
    }
    if ($a === 'quotes') {
        setting('quote_prefix', mb_substr(post('quote_prefix'), 0, 10));
        setting('quote_next', (string)max(1, (int)post('quote_next')));
        setting('quote_valid_days', (string)max(1, min(365, (int)post('quote_valid_days'))));
        setting('quote_terms', mb_substr(post('quote_terms'), 0, 6000));
        flash('Quote settings saved.'); redirect('settings.php');
    }
    if ($a === 'calendar_reset') { setting('calendar_token', bin2hex(random_bytes(16))); flash('New calendar link created. Re-subscribe with the new link.'); redirect('settings.php'); }
    if ($a === 'sync') { $r = sync_website(true); flash($r['message']); redirect('settings.php'); }
    if ($a === 'password') {
        $u = one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify(post('current'), $u['password_hash'])) $error = 'Your current password isn’t right.';
        elseif (strlen(post('new')) < 10) $error = 'Use at least 10 characters.';
        elseif (post('new') !== post('new2')) $error = 'The new passwords don’t match.';
        else { $hash = password_hash(post('new'), PASSWORD_DEFAULT); q('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $user['id']]); sign_in_user(['id' => $user['id'], 'password_hash' => $hash]); flash('Password updated.'); redirect('settings.php'); }
    }
    if (in_array($a, ['tfa_start', 'tfa_confirm', 'tfa_off', 'tfa_codes', 'pk_remove'], true)) {
        require_once __DIR__ . '/app/totp.php';
        require_once __DIR__ . '/app/passkey.php';
        $me = one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        $hasCodes = count(json_decode($me['recovery_codes'] ?: '[]', true) ?: []) > 0;
        if ($a === 'tfa_start') { $_SESSION['tfa_setup'] = totp_new_secret(); redirect('settings.php#tfa'); }
        if (too_many('tfa_settings', 10, 15)) $error = 'Too many tries. Wait 15 minutes and try again.';
        elseif ($a === 'tfa_confirm') {
            $sec = (string)($_SESSION['tfa_setup'] ?? '');
            if ($sec === '' || !($st = totp_check($sec, post('code')))) { record_attempt('tfa_settings'); $error = 'That code didn’t match. Check the time on your phone is set automatically, and try the newest code.'; }
            else {
                q('UPDATE users SET totp_secret = ?, totp_last = ? WHERE id = ?', [totp_seal($sec), $st, $user['id']]);
                // Passkey users already have recovery codes; keep those.
                if (!$hasCodes) { [$plain, $hashes] = totp_recovery_codes(); q('UPDATE users SET recovery_codes = ? WHERE id = ?', [$hashes, $user['id']]); $_SESSION['tfa_codes'] = $plain; }
                unset($_SESSION['tfa_setup']); flash('Authenticator app added.'); redirect('settings.php#tfa');
            }
        } elseif ($a === 'pk_remove') {
            if (!password_verify(post('current'), $me['password_hash'])) { record_attempt('tfa_settings'); $error = 'Your password isn’t right.'; }
            else {
                $list = array_values(array_filter(pk_list($me), function ($p) { return !hash_equals((string)$p['id'], post('id')); }));
                q('UPDATE users SET passkeys = ? WHERE id = ?', [json_encode($list), $user['id']]);
                if (!$list && $me['totp_secret'] === '') { q("UPDATE users SET recovery_codes = '[]' WHERE id = ?", [$user['id']]); flash('Passkey removed. Two-step sign-in is off.'); }
                else flash('Passkey removed.');
                redirect('settings.php#tfa');
            }
        } elseif ($a === 'tfa_codes' && $me['totp_secret'] === '') {
            // Passkeys only: confirm with the password instead of an app code.
            if (!pk_list($me) || !password_verify(post('current'), $me['password_hash'])) { record_attempt('tfa_settings'); $error = 'Your password isn’t right.'; }
            else { [$plain, $hashes] = totp_recovery_codes(); q('UPDATE users SET recovery_codes = ? WHERE id = ?', [$hashes, $user['id']]); $_SESSION['tfa_codes'] = $plain; flash('New recovery codes made. The old ones no longer work.'); redirect('settings.php#tfa'); }
        } else {
            $st = $me['totp_secret'] !== '' ? totp_check(totp_open($me['totp_secret']), post('code'), (int)$me['totp_last']) : 0;
            if (!$st) { record_attempt('tfa_settings'); $error = 'That code didn’t match.'; }
            elseif ($a === 'tfa_off') {
                if (!password_verify(post('current'), $me['password_hash'])) { record_attempt('tfa_settings'); $error = 'Your password isn’t right.'; }
                else {
                    $keep = pk_list($me) !== [];
                    q('UPDATE users SET totp_secret = \'\', totp_last = 0' . ($keep ? '' : ", recovery_codes = '[]'") . ' WHERE id = ?', [$user['id']]);
                    flash($keep ? 'Authenticator app removed. Your passkeys still work.' : 'Two-step sign-in is off.'); redirect('settings.php#tfa');
                }
            } else { [$plain, $hashes] = totp_recovery_codes(); q('UPDATE users SET totp_last = ?, recovery_codes = ? WHERE id = ?', [$st, $hashes, $user['id']]); $_SESSION['tfa_codes'] = $plain; flash('New recovery codes made. The old ones no longer work.'); redirect('settings.php#tfa'); }
        }
    }
    if ($a === 'profile') { if (mb_strlen(post('name')) >= 2) { q('UPDATE users SET name = ? WHERE id = ?', [post('name'), $user['id']]); flash('Name updated.'); } redirect('settings.php'); }
    if ($a === 'backup') {
        $file = STORAGE . '/backups/crm-' . date('Y-m-d-His') . '.sqlite';
        try { db()->exec('VACUUM INTO ' . db()->quote($file)); } catch (Throwable $ex) { copy(STORAGE . '/crm.sqlite', $file); }
        foreach (array_slice(glob(STORAGE . '/backups/crm-*.sqlite') ?: [], 0, -10) as $old) @unlink($old);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file); exit;
    }
}
$sitePath = site_db_path();
if (setting('calendar_token') === '') setting('calendar_token', bin2hex(random_bytes(16)));
$calUrl = abs_url('calendar.php?feed=' . setting('calendar_token'));
layout_top('Settings', 'settings', $user);
?>
<div class="page-head"><div><p class="eyebrow">Signed in as <?= e($user['email']) ?></p><h1>Settings</h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<div class="grid-detail">
  <form method="post" class="card form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="business">
    <div class="card-h span2"><h2>Business &amp; invoices</h2></div>
    <?php foreach ($fields as $k => $l): ?><label class="f"><span><?= e($l) ?></span><input name="<?= e($k) ?>" value="<?= e(setting($k)) ?>"<?= in_array($k, ['default_rate', 'invoice_next', 'invoice_terms_days'], true) ? ' type="number" min="0"' : '' ?>></label><?php endforeach; ?>
    <label class="f span2"><span>Payment instructions <small class="muted">(printed on every invoice)</small></span><textarea name="payment_instructions" rows="3" placeholder="e.g. Pay by ACH to … or Zelle to …"><?= e(setting('payment_instructions')) ?></textarea></label>
    <label class="f span2"><span>Invoice footer</span><input name="invoice_footer" value="<?= e(setting('invoice_footer')) ?>"></label>
    <label class="f span2"><span>Default online payment link <small class="muted">(optional, added to new invoices)</small></span><input name="payment_link_default" type="text" inputmode="url" autocomplete="off" value="<?= e(setting('payment_link_default')) ?>" placeholder="https://buy.stripe.com/…"></label>
    <label class="f span2"><span>Send invoice emails from <small class="muted">(must be an address on your domain in cPanel)</small></span><input name="mail_from" type="email" value="<?= e(setting('mail_from')) ?>"></label>
    <div class="save-bar span2"><span class="muted small">Next invoice: <b class="num"><?= e(setting('invoice_prefix') . setting('invoice_next')) ?></b></span><button class="btn primary sm" type="submit">Save settings</button></div>
  </form>
  <div class="stack">
    <form method="post" class="card form-grid">
      <?= csrf_field() ?><input type="hidden" name="action" value="quotes">
      <div class="card-h span2"><h2>Quotes</h2></div>
      <label class="f"><span>Number prefix</span><input name="quote_prefix" value="<?= e(setting('quote_prefix')) ?>"></label>
      <label class="f"><span>Next number</span><input name="quote_next" type="number" min="1" value="<?= e(setting('quote_next')) ?>"></label>
      <label class="f span2"><span>Quotes are valid for (days)</span><input name="quote_valid_days" type="number" min="1" max="365" value="<?= e(setting('quote_valid_days')) ?>"></label>
      <label class="f span2"><span>Standard terms <small class="muted">(filled into new quotes; edit per quote)</small></span><textarea name="quote_terms" rows="6"><?= e(setting('quote_terms')) ?></textarea></label>
      <div class="save-bar span2"><span></span><button class="btn primary sm" type="submit">Save</button></div>
    </form>
    <section class="card">
      <div class="card-h"><h2>Website sync</h2></div>
      <p class="muted small">New stusyn.com contact form messages and consultation requests become leads in your pipeline, with a "Reply to…" task for today. It checks every time you open the dashboard. Careers messages stay in the hiring dashboard.</p>
      <dl class="facts">
        <div><dt>Status</dt><dd><?= $sitePath && is_readable($sitePath) ? '<span class="dot ok"></span>Connected' : '<span class="dot off"></span>Not connected' ?></dd></div>
        <div><dt>Database</dt><dd class="mono small"><?= e($sitePath ?: 'Not found') ?></dd></div>
        <div><dt>Last sync</dt><dd><?= e(setting('site_last_sync') ? ago(setting('site_last_sync')) : 'Never') ?></dd></div>
        <div><dt>Messages imported up to</dt><dd class="num">#<?= e(setting('site_last_message_id')) ?></dd></div>
      </dl>
      <?php if (!$sitePath): ?><p class="notice small">Set <code>site_db</code> in <code>app/config.php</code> to the full path of <code>public_html/storage/syndicate.sqlite</code>.</p><?php endif; ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button class="btn ghost sm" type="submit">Sync now</button></form>
    </section>
    <section class="card">
      <div class="card-h"><h2>Email inbox</h2><a class="muted small" href="mailboxes.php">Manage →</a></div>
      <?php $mbs = all('SELECT email, last_error FROM mailboxes ORDER BY id'); ?>
      <?php if (!$mbs): ?><p class="muted small">Connect hello@ or careers@ to read and answer email inside the CRM, matched to your contacts.</p><a class="btn ghost sm" href="mailboxes.php">Connect a mailbox</a>
      <?php else: ?><dl class="facts"><?php foreach ($mbs as $mb): ?><div><dt><?= e($mb['email']) ?></dt><dd><?= $mb['last_error'] ? '<span class="dot off"></span>' . e($mb['last_error']) : '<span class="dot ok"></span>Connected' ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    </section>
    <section class="card">
      <div class="card-h"><h2>Calendar feed</h2></div>
      <p class="muted small">Subscribe to this private link in Google Calendar (Other calendars → From URL) or Apple Calendar (File → New Calendar Subscription), and your scheduled consultations show up automatically. Keep it private.</p>
      <div class="copy-row"><input readonly id="calLink" value="<?= e($calUrl) ?>" aria-label="Calendar feed link"><button class="btn ghost sm" type="button" data-copy="#calLink">Copy</button></div>
      <form method="post" data-confirm="Create a new link? The old one will stop working."><?= csrf_field() ?><input type="hidden" name="action" value="calendar_reset"><button class="link-danger" type="submit">Reset link</button></form>
    </section>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="card-h"><h2>Your name</h2></div>
      <label class="f"><span>Name</span><input name="name" value="<?= e($user['name']) ?>"></label>
      <button class="btn ghost sm" type="submit">Save</button>
    </form>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="card-h"><h2>Change password</h2></div>
      <label class="f"><span>Current password</span><input name="current" type="password" required autocomplete="current-password"></label>
      <label class="f"><span>New password</span><input name="new" type="password" required minlength="10" autocomplete="new-password"></label>
      <label class="f"><span>Confirm new password</span><input name="new2" type="password" required minlength="10" autocomplete="new-password"></label>
      <button class="btn ghost sm" type="submit">Update password</button>
    </form>
    <?php require_once __DIR__ . '/app/passkey.php'; $me = one('SELECT totp_secret, recovery_codes, passkeys FROM users WHERE id = ?', [$user['id']]); $keys = pk_list($me); $app = $me['totp_secret'] !== ''; $setup = (string)($_SESSION['tfa_setup'] ?? ''); $codes = $_SESSION['tfa_codes'] ?? null; unset($_SESSION['tfa_codes']); $left = count(json_decode($me['recovery_codes'] ?: '[]', true) ?: []); ?>
    <section class="card" id="tfa"><div class="card-h"><h2>Two-step sign-in</h2><?= tfa_on($me) ? '<span class="tag lime">On</span>' : '<span class="tag red">Off</span>' ?></div>
      <?php if ($codes): ?>
        <p class="notice">Save these recovery codes somewhere safe, like your password manager. Each one works once if you lose your phone. They won’t be shown again.</p>
        <pre class="code-sm num"><?= e(implode("\n", $codes)) ?></pre>
      <?php endif; ?>
      <p class="muted small">After your password, the CRM, ads and wiki ask for a passkey or a code from your phone, so a stolen password isn’t enough to get in.<?= tfa_on($me) ? ' ' . $left . ' recovery codes left.' : '' ?></p>

      <h3 class="tfa-sub">Passkeys</h3>
      <?php if ($keys): ?><ul class="pk-list">
        <?php foreach ($keys as $p): ?><li><span><b><?= e($p['name']) ?></b><span class="muted small"><?= e(pk_when($p)) ?></span></span>
          <details><summary class="muted small">Remove</summary><form method="post" class="row" style="flex-wrap:wrap;gap:8px;margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="pk_remove"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><input name="current" type="password" placeholder="Password" aria-label="Password" required autocomplete="current-password"><button class="link-danger" type="submit">Remove passkey</button></form></details></li>
        <?php endforeach; ?></ul>
      <?php else: ?><p class="muted small">Sign in with Face ID or Touch ID on your iPhone, iPad or Mac (or Windows Hello, an Android phone or a security key). Your iPhone keeps it in iCloud Keychain, so it works on your other Apple devices too.</p><?php endif; ?>
      <p class="notice bad" data-passkey-msg role="alert" hidden></p>
      <div class="row" style="flex-wrap:wrap;gap:10px"><input data-passkey-name maxlength="60" placeholder="Name it, e.g. Grady’s iPhone" aria-label="Passkey name" style="flex:1;min-width:180px"><button class="btn <?= $keys ? 'ghost' : 'primary' ?> sm" type="button" data-passkey-add data-endpoint="passkey.php" data-csrf="<?= e(csrf_token()) ?>">Add a passkey</button></div>
      <script src="assets/js/passkey.js" defer></script>

      <h3 class="tfa-sub">Authenticator app</h3>
      <?php if ($app): ?>
        <p class="muted small">On. Signing in can use a 6-digit code from your app.</p>
        <form method="post" class="row" style="flex-wrap:wrap;gap:10px"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_codes"><input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" aria-label="Code from your app" required style="width:140px"><button class="btn ghost sm" type="submit">New recovery codes</button></form>
        <details><summary class="muted small">Remove the authenticator app</summary><form method="post" class="row" style="flex-wrap:wrap;gap:10px;margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_off"><input name="current" type="password" placeholder="Password" aria-label="Password" required autocomplete="current-password"><input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="6-digit code" aria-label="Code from your app" required style="width:140px"><button class="link-danger" type="submit"><?= $keys ? 'Remove app' : 'Turn off two-step sign-in' ?></button></form></details>
      <?php elseif ($setup !== ''): require_once __DIR__ . '/app/totp.php'; ?>
        <p>1. Open an authenticator app (1Password, Bitwarden, Google Authenticator, Authy) and scan this code.</p>
        <div class="qr" data-qr="<?= e(totp_uri($setup, $user['email'], 'Syndicate CRM')) ?>" role="img" aria-label="QR code for your authenticator app"></div>
        <p class="muted small">Can’t scan? Enter this key instead: <code class="num"><?= e(implode(' ', str_split($setup, 4))) ?></code></p>
        <form method="post" class="row" style="flex-wrap:wrap;gap:10px"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_confirm"><label class="f grow"><span>2. Enter the 6-digit code it shows</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required></label><button class="btn primary sm" type="submit" style="align-self:end">Turn on</button></form>
        <script src="assets/js/qrcode.js" defer></script>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_start"><button class="btn ghost sm" type="submit">Set up an authenticator app</button></form>
      <?php endif; ?>
      <?php if (!$app && $keys): ?>
        <details><summary class="muted small">New recovery codes</summary><form method="post" class="row" style="flex-wrap:wrap;gap:10px;margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="tfa_codes"><input name="current" type="password" placeholder="Password" aria-label="Password" required autocomplete="current-password"><button class="btn ghost sm" type="submit">Make new codes</button></form></details>
      <?php endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>Your data</h2></div>
      <div class="row wrap"><a class="btn ghost xs" href="export.php?type=contacts">Contacts CSV</a><a class="btn ghost xs" href="export.php?type=companies">Companies CSV</a><a class="btn ghost xs" href="export.php?type=deals">Deals CSV</a><a class="btn ghost xs" href="export.php?type=consultations">Consultations CSV</a><a class="btn ghost xs" href="export.php?type=invoices">Invoices CSV</a></div>
      <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="backup"><button class="btn ghost sm" type="submit">Download full backup</button><p class="muted small">A copy of the whole CRM database. Keep it somewhere safe; it includes client details.</p></form>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
