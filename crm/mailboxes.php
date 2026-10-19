<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/mail.php';
$user = require_user();
$error = ''; $editId = (int)(post('id') ?: get('edit'));
$edit = $editId ? mailbox($editId) : null;
$f = ['email' => $edit['email'] ?? '', 'name' => $edit['name'] ?? setting('business_name'), 'host' => $edit['host'] ?? '', 'imap_port' => $edit['imap_port'] ?? 993, 'smtp_port' => $edit['smtp_port'] ?? 465, 'verify_tls' => $edit['verify_tls'] ?? 1, 'signature' => $edit['signature'] ?? ''];

if (is_post()) {
    $a = post('action');
    if ($a === 'remove' && $edit) {
        q('DELETE FROM mailboxes WHERE id = ?', [$edit['id']]);
        foreach (glob(STORAGE . '/mail/' . (int)$edit['id'] . '/*') ?: [] as $file) @unlink($file);
        @rmdir(STORAGE . '/mail/' . (int)$edit['id']);
        flash($edit['email'] . ' disconnected. Its mail is still on the server.'); redirect('mailboxes.php');
    }
    if ($a === 'save') {
        $f = ['email' => strtolower(post('email')), 'name' => mb_substr(post('name'), 0, 100), 'host' => strtolower(post('host')), 'imap_port' => (int)post('imap_port') ?: 993,
            'smtp_port' => (int)post('smtp_port') ?: 465, 'verify_tls' => post('verify_tls') === '1' ? 1 : 0, 'signature' => mb_substr(rtrim((string)($_POST['signature'] ?? '')), 0, 2000)];
        if ($f['host'] === '' && strpos($f['email'], '@') !== false) $f['host'] = 'mail.' . substr($f['email'], strpos($f['email'], '@') + 1);
        $pass = (string)($_POST['password'] ?? '');
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $error = 'Enter the full email address, e.g. hello@stusyn.com.';
        elseif (!preg_match('/^[a-z0-9.-]+$/', $f['host'])) $error = 'The mail server should look like mail.stusyn.com.';
        elseif ($pass === '' && !$edit) $error = 'Enter the mailbox password.';
        elseif (($dupe = one('SELECT id FROM mailboxes WHERE email = ?', [$f['email']])) && (int)$dupe['id'] !== $editId) $error = $f['email'] . ' is already connected.';
        else {
            $box = $f + ['secret' => $pass !== '' ? mail_encrypt($pass) : $edit['secret'], 'id' => $editId];
            try {
                test_mailbox($box); // sign in to both receiving and sending before saving
                if ($edit) {
                    q('UPDATE mailboxes SET email = ?, name = ?, host = ?, imap_port = ?, smtp_port = ?, verify_tls = ?, secret = ?, signature = ?, last_error = ? WHERE id = ?',
                        [$f['email'], $f['name'], $f['host'], $f['imap_port'], $f['smtp_port'], $f['verify_tls'], $box['secret'], $f['signature'], '', $editId]);
                    if ($edit['email'] !== $f['email'] || $edit['host'] !== $f['host']) { q('DELETE FROM mail_folders WHERE mailbox_id = ?', [$editId]); q("UPDATE mailboxes SET sent_folder = '' WHERE id = ?", [$editId]); }
                    flash('Saved. ' . $f['email'] . ' is connected.');
                } else {
                    q('INSERT INTO mailboxes (email, name, host, imap_port, smtp_port, verify_tls, secret, signature, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                        [$f['email'], $f['name'], $f['host'], $f['imap_port'], $f['smtp_port'], $f['verify_tls'], $box['secret'], $f['signature'], now()]);
                    setting('mail_last_sync', '');
                    flash($f['email'] . ' is connected. Your last ' . MAIL_FIRST_DAYS . ' days of mail are being copied in now.');
                }
                redirect('inbox.php');
            } catch (Throwable $ex) { $error = $ex->getMessage(); }
        }
    }
}
$boxes = mailboxes();
layout_top('Email accounts', 'inbox', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $boxes ? 'inbox.php' : 'settings.php' ?>">← <?= $boxes ? 'Inbox' : 'Settings' ?></a><h1>Email accounts</h1><p class="muted">Connect your @stusyn.com mailboxes to read and answer email inside the CRM, next to each client’s history.</p></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<div class="grid-detail">
  <form method="post" class="card form-grid" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
    <div class="card-h span2"><h2><?= $edit ? 'Edit ' . e($edit['email']) : 'Connect a mailbox' ?></h2><?php if ($edit): ?><a class="muted small" href="mailboxes.php">Cancel</a><?php endif; ?></div>
    <label class="f"><span>Email address</span><input name="email" type="email" value="<?= e($f['email']) ?>" placeholder="hello@stusyn.com" required></label>
    <label class="f"><span>Password <small class="muted"><?= $edit ? '(leave blank to keep it)' : '(the mailbox’s own password)' ?></small></span><input name="password" type="password" autocomplete="new-password"<?= $edit ? '' : ' required' ?>></label>
    <label class="f span2"><span>Name people see</span><input name="name" value="<?= e($f['name']) ?>" placeholder="Grady Davis, or Syndicate Holdings Group"></label>
    <label class="f span2"><span>Signature <small class="muted">(added to new emails and replies)</small></span><textarea name="signature" rows="4" placeholder="Grady Davis&#10;Syndicate Holdings Group · stusyn.com"><?= e($f['signature']) ?></textarea></label>
    <details class="span2"<?= $edit || $error ? ' open' : '' ?>><summary class="muted small">Advanced: mail server</summary>
      <div class="form-grid adv">
        <label class="f span2"><span>Mail server <small class="muted">(blank means mail.yourdomain.com)</small></span><input name="host" value="<?= e($f['host']) ?>" placeholder="mail.stusyn.com"></label>
        <label class="f"><span>Receiving (IMAP) port</span><input name="imap_port" type="number" value="<?= (int)$f['imap_port'] ?>"></label>
        <label class="f"><span>Sending (SMTP) port</span><input name="smtp_port" type="number" value="<?= (int)$f['smtp_port'] ?>"></label>
        <label class="chk span2"><input type="checkbox" name="verify_tls" value="1"<?= $f['verify_tls'] ? ' checked' : '' ?>> <span>Check the server certificate <small class="muted">(untick only if cPanel’s certificate doesn’t match the server name)</small></span></label>
      </div>
    </details>
    <div class="save-bar span2"><span class="muted small">We sign in to check it works before saving.</span><button class="btn primary sm" type="submit"><?= $edit ? 'Save' : 'Connect' ?></button></div>
  </form>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Connected</h2></div>
      <?php if (!$boxes): ?><p class="empty">No mailboxes yet.</p><?php else: foreach ($boxes as $b): ?>
        <div class="box-row">
          <span class="grow"><b><span class="dot <?= $b['last_error'] ? 'off' : 'ok' ?>"></span><?= e($b['email']) ?></b><small><?= $b['last_error'] ? e($b['last_error']) : 'Checked ' . e($b['last_sync'] ? ago($b['last_sync']) : 'not yet') ?></small></span>
          <a class="btn ghost xs" href="mailboxes.php?edit=<?= (int)$b['id'] ?>">Edit</a>
          <form method="post" data-confirm="Disconnect <?= e($b['email']) ?>? Its copies here are removed. Nothing is deleted from the mail server."><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="link-danger" type="submit">Remove</button></form>
        </div>
      <?php endforeach; endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>How it works</h2></div>
      <ul class="plain-list muted small">
        <li>Find the password and server under cPanel → <b>Email Accounts</b> → <b>Connect Devices</b>. It’s usually <code>mail.stusyn.com</code>, ports 993 and 465.</li>
        <li>New mail is copied in every couple of minutes while the CRM is open, and by the daily cron job.</li>
        <li>Email from people in your contacts shows on their contact and company pages. You can add anyone else as a contact in one click.</li>
        <li>Replies are sent through your own mailbox and saved to its Sent folder, so webmail and your phone stay in step.</li>
        <li>Your password is stored encrypted. The key is in <code>storage/mail.key</code>, which the web can’t reach.</li>
      </ul>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
