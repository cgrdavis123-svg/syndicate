<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/mail.php';
$user = require_user();
$boxes = array_column(mailboxes(), null, 'id');
if (!$boxes) { flash('Connect a mailbox first.'); redirect('mailboxes.php'); }
$own = own_addresses();

// What this email answers or forwards, if anything.
$srcId = (int)(is_post() ? post('reply') ?: post('fwd') : (get('reply') ?: get('fwd')));
$isFwd = is_post() ? post('fwd') !== '' : get('fwd') !== '';
$src = $srcId ? one('SELECT * FROM emails WHERE id = ?', [$srcId]) : null;
$f = ['mailbox_id' => (int)($src['mailbox_id'] ?? array_key_first($boxes)), 'to' => '', 'cc' => '', 'bcc' => '', 'subject' => '', 'body' => '', 'quote' => '1', 'fwd_att' => '1'];
$errors = [];

if ($src && !is_post()) {
    $subj = $src['subject'];
    $srcTo = json_decode($src['to_list'], true) ?: []; $srcCc = json_decode($src['cc_list'], true) ?: [];
    if ($isFwd) $f['subject'] = preg_match('/^\s*fwd?\s*:/i', $subj) ? $subj : 'Fwd: ' . $subj;
    else {
        $f['subject'] = preg_match('/^\s*re\s*:/i', $subj) ? $subj : 'Re: ' . $subj;
        $f['to'] = $src['folder'] === 'sent' ? addr_line($srcTo) : ($src['reply_to'] ?: $src['from_addr']);
        if (get('all')) {
            $skip = array_merge($own, array_map('strtolower', array_column(mime_addresses($f['to']), 1)));
            $f['cc'] = addr_line(array_values(array_filter(array_merge($src['folder'] === 'sent' ? [] : $srcTo, $srcCc), function ($a) use ($skip) { return !in_array($a[1], $skip, true); })));
        }
    }
} elseif (!is_post()) {
    if ($c = one('SELECT * FROM contacts WHERE id = ?', [(int)get('contact')])) $f['to'] = $c['email'] !== '' ? (person_name($c) !== $c['email'] ? person_name($c) . ' <' . $c['email'] . '>' : $c['email']) : '';
    elseif (get('to') !== '') $f['to'] = get('to');
    $f['subject'] = mb_substr(get('subject'), 0, 300);
}
if (!is_post()) { $sig = $boxes[$f['mailbox_id']]['signature'] ?? ''; $f['body'] = $sig !== '' ? "\n\n" . $sig : ''; }

if (is_post()) {
    foreach (['to', 'cc', 'bcc', 'subject', 'body'] as $k) $f[$k] = (string)($_POST[$k] ?? '');
    $f['mailbox_id'] = (int)post('mailbox_id'); $f['quote'] = post('quote'); $f['fwd_att'] = post('fwd_att');
    $box = $boxes[$f['mailbox_id']] ?? null;
    $bad = [];
    $to = parse_recipients($f['to'], $bad); $cc = parse_recipients($f['cc'], $bad); $bcc = parse_recipients($f['bcc'], $bad);
    if (!$box) $errors[] = 'Choose a mailbox to send from.';
    if (!$to) $errors[] = 'Add at least one address in To.';
    if ($bad) $errors[] = 'These don’t look like email addresses: ' . implode(', ', array_unique($bad)) . '.';
    if (trim($f['subject']) === '' && !$src) $errors[] = 'Add a subject.';
    if (trim($f['body']) === '' && !$isFwd) $errors[] = 'Write a message.';
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $errors = ['Your attachments are too large to upload here (the limit is ' . fmt_bytes(upload_limit()) . ').'];

    $atts = []; $total = 0;
    foreach ($_FILES['files']['name'] ?? [] as $i => $name) {
        $err = $_FILES['files']['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) { $errors[] = '“' . $name . '” didn’t upload' . (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' because it’s too large.' : '. Try again.'); continue; }
        $data = (string)file_get_contents($_FILES['files']['tmp_name'][$i]);
        $total += strlen($data);
        $type = function_exists('mime_content_type') ? (string)@mime_content_type($_FILES['files']['tmp_name'][$i]) : '';
        $atts[] = [basename(str_replace('\\', '/', $name)), $type ?: 'application/octet-stream', $data];
    }
    $text = rtrim($f['body']);
    if ($src) {
        $fromLine = $src['from_name'] ? $src['from_name'] . ' <' . $src['from_addr'] . '>' : $src['from_addr'];
        if ($isFwd) {
            $text .= "\n\n---------- Forwarded message ----------\nFrom: $fromLine\nDate: " . fmt_date($src['sent_at'], 'D, M j, Y \a\t g:i a') . "\nSubject: " . $src['subject'] . "\nTo: " . addr_line(json_decode($src['to_list'], true) ?: []) . "\n\n" . $src['body_text'];
            if ($f['fwd_att']) {
                $parsed = mime_parse(load_raw($src));
                foreach ($parsed['attachments'] as $a) if (!$a['inline'] || $a['type'] === 'message/rfc822') { $atts[] = [$a['name'], $a['type'], $a['data']]; $total += $a['size']; }
            }
        } elseif ($f['quote']) {
            $text .= "\n\nOn " . fmt_date($src['sent_at'], 'D, M j, Y \a\t g:i a') . ", $fromLine wrote:\n" . preg_replace('/^/m', '> ', rtrim(str_replace("\r\n", "\n", $src['body_text'])));
        }
    }
    if ($total > 20 * 1048576) $errors[] = 'Attachments add up to ' . fmt_bytes($total) . '. Most mail servers only accept about 20 MB, so share a link instead.';

    if (!$errors) {
        $refs = $src && !$isFwd ? array_values(array_unique(array_merge(array_filter(explode(' ', $src['refs'])), [$src['message_id']]))) : [];
        try {
            [$id, $warn] = send_email($box, [
                'from' => [$box['name'], $box['email']], 'to' => $to, 'cc' => $cc, 'bcc' => $bcc, 'subject' => trim($f['subject']) ?: '(no subject)', 'text' => $text,
                'in_reply_to' => $src && !$isFwd ? $src['message_id'] : '', 'references' => array_slice($refs, -20), 'attachments' => $atts,
            ]);
            $em = one('SELECT * FROM emails WHERE id = ?', [$id]);
            if ($em && $em['contact_id']) log_activity('contact', (int)$em['contact_id'], 'email', 'Emailed “' . $em['subject'] . '” from ' . $box['email'] . ":\n\n" . mb_strimwidth(rtrim($f['body']), 0, 2000, '…'));
            flash($warn ?: 'Sent.');
            redirect($em ? 'email.php?t=' . rawurlencode($em['thread_id']) . '#m' . $id : 'inbox.php');
        } catch (Throwable $ex) {
            error_log('Send failed: ' . $ex->getMessage());
            $errors[] = 'Not sent: ' . $ex->getMessage() . ($atts ? ' Attach your files again before resending.' : '');
        }
    }
}
$box = $boxes[$f['mailbox_id']] ?? reset($boxes);
$srcAtts = $src && $isFwd ? array_filter(json_decode($src['attachments'], true) ?: [], function ($a) { return !$a['inline'] || $a['type'] === 'message/rfc822'; }) : [];
$known = all("SELECT first_name, last_name, email FROM contacts WHERE email != '' ORDER BY updated_at DESC LIMIT 500");
layout_top($isFwd ? 'Forward' : ($src ? 'Reply' : 'New email'), 'inbox', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $src ? 'email.php?t=' . e(rawurlencode($src['thread_id'])) : 'inbox.php' ?>">← <?= $src ? 'Back to the conversation' : 'Inbox' ?></a><h1><?= $isFwd ? 'Forward' : ($src ? 'Reply' : 'New email') ?></h1></div></div>
<?php if ($errors): ?><div class="notice bad"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card form-grid compose">
  <?= csrf_field() ?>
  <?php if ($src): ?><input type="hidden" name="<?= $isFwd ? 'fwd' : 'reply' ?>" value="<?= (int)$src['id'] ?>"><?php endif; ?>
  <label class="f span2"><span>From</span><select name="mailbox_id"><?php foreach ($boxes as $b): ?><option value="<?= (int)$b['id'] ?>"<?= selected($b['id'], $box['id']) ?>><?= e(($b['name'] ? $b['name'] . ' — ' : '') . $b['email']) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span>To <small class="muted">(separate addresses with commas)</small></span><input name="to" value="<?= e($f['to']) ?>" list="knownAddr" autocomplete="off" required<?= $f['to'] === '' ? ' autofocus' : '' ?>></label>
  <details class="span2 cc-bcc"<?= $f['cc'] !== '' || $f['bcc'] !== '' ? ' open' : '' ?>><summary class="muted small">Cc / Bcc</summary>
    <div class="form-grid"><label class="f"><span>Cc</span><input name="cc" value="<?= e($f['cc']) ?>" list="knownAddr" autocomplete="off"></label><label class="f"><span>Bcc</span><input name="bcc" value="<?= e($f['bcc']) ?>" list="knownAddr" autocomplete="off"></label></div>
  </details>
  <label class="f span2"><span>Subject</span><input name="subject" value="<?= e($f['subject']) ?>" maxlength="300"<?= $src ? '' : ' required' ?>></label>
  <label class="f span2"><span>Message</span><textarea name="body" rows="14"<?= $isFwd ? '' : ' required' ?><?= $f['to'] !== '' ? ' autofocus' : '' ?>><?= e($f['body']) ?></textarea></label>
  <?php if ($src && !$isFwd): ?><label class="chk span2"><input type="checkbox" name="quote" value="1"<?= $f['quote'] ? ' checked' : '' ?>> <span>Include <?= e($src['from_name'] ?: $src['from_addr']) ?>’s message below your reply</span></label><?php endif; ?>
  <?php if ($srcAtts): ?><label class="chk span2"><input type="checkbox" name="fwd_att" value="1"<?= $f['fwd_att'] ? ' checked' : '' ?>> <span>Include the <?= count($srcAtts) ?> original attachment<?= count($srcAtts) === 1 ? '' : 's' ?> (<?= e(implode(', ', array_column($srcAtts, 'name'))) ?>)</span></label><?php endif; ?>
  <?php if ($isFwd): ?><p class="muted small span2">The original message is added under what you write.</p><?php endif; ?>
  <div class="save-bar span2">
    <label class="file-pick"><input type="file" name="files[]" multiple data-max="<?= upload_limit() ?>"><span>Attach files</span></label>
    <button class="btn primary" type="submit">Send</button>
  </div>
</form>
<datalist id="knownAddr"><?php foreach ($known as $k): ?><option value="<?= e(trim($k['first_name'] . ' ' . $k['last_name']) !== '' ? trim($k['first_name'] . ' ' . $k['last_name']) . ' <' . $k['email'] . '>' : $k['email']) ?>"><?php endforeach; ?></datalist>
<?php layout_bottom(); ?>
