<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/mail.php';
$user = require_user();
$tid = get('t');
$msgs = all('SELECT * FROM emails WHERE thread_id = ? ORDER BY sent_at, id', [$tid]);
if (!$msgs) { flash('That conversation is no longer here.'); redirect('inbox.php'); }
$back = 'email.php?t=' . rawurlencode($tid);
$own = own_addresses();
$last = end($msgs);
$lastIn = null; foreach ($msgs as $m) if ($m['folder'] === 'inbox') $lastIn = $m;
$replyTo = $lastIn ?: $last;
// The person on the other side, for "add contact" and "new deal".
$other = ['', ''];
foreach (array_reverse($msgs) as $m) {
    if ($m['folder'] === 'inbox' && !in_array($m['from_addr'], $own, true)) { $other = [$m['from_name'], $m['reply_to'] ?: $m['from_addr']]; break; }
    if ($m['folder'] === 'sent' && ($to = json_decode($m['to_list'], true))) { $other = $to[0]; break; }
}
$contactId = 0; foreach ($msgs as $m) if ($m['contact_id']) $contactId = (int)$m['contact_id'];
$contact = $contactId ? one('SELECT c.*, co.name company FROM contacts c LEFT JOIN companies co ON co.id = c.company_id WHERE c.id = ?', [$contactId]) : null;
$subject = $msgs[0]['subject'] ?: '(no subject)';

if (is_post()) {
    $a = post('action');
    if ($a === 'archive') { q('UPDATE emails SET archived = 1 WHERE thread_id = ?', [$tid]); flash('Archived.'); redirect('inbox.php'); }
    if ($a === 'unarchive') { q('UPDATE emails SET archived = 0 WHERE thread_id = ?', [$tid]); flash('Moved back to the inbox.'); redirect($back); }
    if ($a === 'unread') {
        if ($lastIn) q('UPDATE emails SET seen = 0, seen_dirty = 1 WHERE id = ?', [$lastIn['id']]);
        flash('Marked as unread.'); redirect('inbox.php');
    }
    if ($a === 'add_contact' && filter_var($other[1], FILTER_VALIDATE_EMAIL)) {
        $c = find_or_create_contact($other[0] ?: '', $other[1], post('company'));
        if ($other[0] === '' && $c['first_name'] === '') q('UPDATE contacts SET first_name = ? WHERE id = ?', [strstr($other[1], '@', true), $c['id']]);
        relink_emails($c);
        q('UPDATE emails SET contact_id = ?, company_id = ? WHERE thread_id = ?', [$c['id'], $c['company_id'], $tid]);
        flash(person_name(one('SELECT * FROM contacts WHERE id = ?', [$c['id']])) . ' added to your contacts. Past and future emails with them are linked.');
        redirect($back);
    }
    if ($a === 'link' && ($c = one('SELECT * FROM contacts WHERE id = ?', [(int)post('contact_id')]))) {
        q('UPDATE emails SET contact_id = ?, company_id = ? WHERE thread_id = ?', [$c['id'], $c['company_id'], $tid]);
        flash('Linked to ' . person_name($c) . '.'); redirect($back);
    }
    if ($a === 'deal' && $contact) {
        $boxEmail = (string)val('SELECT email FROM mailboxes WHERE id = ?', [$replyTo['mailbox_id']]);
        q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?,?, 'new', 0, 'Email', '', ?, ?)",
            [mb_substr(norm_subject($subject) !== '' ? preg_replace('/^\s*((re|fwd?)\s*:\s*)+/i', '', $subject) : 'Email from ' . person_name($contact), 0, 200), $contact['company_id'], $contact['id'], post('brand') ?: 'Syndicate Holdings Group', now(), now()]);
        $did = (int)db()->lastInsertId();
        log_activity('deal', $did, 'email', 'Created from the email “' . $subject . '” to ' . $boxEmail . ":\n\n" . mb_strimwidth(strip_quoted($replyTo['body_text']), 0, 3000, '…'));
        flash('Deal created.'); redirect('deal.php?id=' . $did);
    }
    redirect($back);
}

// Opening the conversation marks it read (on the server too, at the next check).
q('UPDATE emails SET seen = 1, seen_dirty = 1 WHERE thread_id = ? AND seen = 0', [$tid]);
$archived = !array_filter($msgs, function ($m) { return $m['folder'] === 'inbox' && !$m['archived']; }) && $lastIn;
$images = get('img') === '1';
$boxes = array_column(mailboxes(), null, 'id');
layout_top($subject, 'inbox', $user);
?>
<div class="page-head">
  <div class="grow-head"><a class="muted small" href="inbox.php">← Inbox</a><h1 class="mail-subject"><?= e($subject) ?></h1>
    <p class="row muted small"><?= count($msgs) ?> message<?= count($msgs) === 1 ? '' : 's' ?> · with <?= e(short_name($other) ?: 'yourself') ?><?php if ($contact): ?> · <a class="tag lime" href="contact.php?id=<?= (int)$contact['id'] ?>"><?= e(person_name($contact)) ?><?= $contact['company'] ? ' · ' . e($contact['company']) : '' ?></a><?php endif; ?></p></div>
  <div class="row">
    <a class="btn primary sm" href="#reply">Reply</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $archived ? 'unarchive' : 'archive' ?>"><button class="btn ghost sm" type="submit"><?= $archived ? 'Move to inbox' : 'Archive' ?></button></form>
    <?php if ($lastIn): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unread"><button class="btn ghost sm" type="submit">Mark unread</button></form><?php endif; ?>
  </div>
</div>
<div class="grid-detail">
  <div class="stack">
    <?php foreach ($msgs as $i => $m):
      $isLast = $i === count($msgs) - 1;
      $to = json_decode($m['to_list'], true) ?: []; $cc = json_decode($m['cc_list'], true) ?: [];
      $atts = array_values(array_filter(json_decode($m['attachments'], true) ?: [], function ($a) { return !$a['inline'] || $a['type'] === 'message/rfc822'; }));
      $blocked = 0; $doc = $m['body_html'] !== '' ? email_html_doc($m, $images, $blocked) : ''; ?>
      <details class="card mail-msg<?= $m['folder'] === 'sent' ? ' mine' : '' ?>"<?= $isLast || !$m['seen'] || count($msgs) <= 2 ? ' open' : '' ?> id="m<?= (int)$m['id'] ?>">
        <summary class="mail-head">
          <span class="av"><?= e(initials($m['from_name'] ?: $m['from_addr'])) ?></span>
          <span class="grow"><b><?= e($m['from_name'] ?: $m['from_addr']) ?><?= $m['folder'] === 'sent' ? ' <span class="tag">You</span>' : '' ?></b><small class="mail-peek"><?= e($m['snippet']) ?></small><small class="mail-to">To <?= e(addr_line($to) ?: '—') ?><?= $cc ? ' · Cc ' . e(addr_line($cc)) : '' ?></small></span>
          <time title="<?= e($m['sent_at']) ?>"><?= e(fmt_date($m['sent_at'], 'M j, Y · g:i a')) ?></time>
        </summary>
        <div class="mail-meta-line muted small"><span>From <?= e($m['from_name'] ? $m['from_name'] . ' <' . $m['from_addr'] . '>' : $m['from_addr']) ?></span><?php if ($m['reply_to'] && $m['reply_to'] !== $m['from_addr']): ?><span>Reply-to <?= e($m['reply_to']) ?></span><?php endif; ?><span><?= $m['folder'] === 'sent' ? 'Sent from' : 'Received at' ?> <?= e($boxes[$m['mailbox_id']]['email'] ?? '') ?></span></div>
        <?php if ($blocked): ?><p class="img-note">Pictures in this email are hidden to keep the sender from knowing when you opened it. <a href="<?= e($back) ?>&amp;img=1#m<?= (int)$m['id'] ?>">Show pictures</a></p><?php endif; ?>
        <?php if ($doc !== ''): ?><iframe class="mail-frame" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" srcdoc="<?= e($doc) ?>" title="Email from <?= e($m['from_name'] ?: $m['from_addr']) ?>" loading="lazy"></iframe>
        <?php else: ?><?= email_text_html($m['body_text']) ?><?php endif; ?>
        <?php if ($atts): ?><div class="mail-atts"><?php foreach ($atts as $a): ?><a class="att" href="email-file.php?id=<?= (int)$m['id'] ?>&amp;n=<?= (int)$a['n'] ?>"><span class="att-ic"><?= e(strtoupper(pathinfo($a['name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></span><span class="grow"><b><?= e($a['name']) ?></b><small><?= e(fmt_bytes((int)$a['size'])) ?></small></span></a><?php endforeach; ?></div><?php endif; ?>
        <div class="mail-actions"><a class="btn ghost xs" href="compose.php?reply=<?= (int)$m['id'] ?>">Reply</a><?php if (count($to) + count($cc) > 1): ?><a class="btn ghost xs" href="compose.php?reply=<?= (int)$m['id'] ?>&amp;all=1">Reply all</a><?php endif; ?><a class="btn ghost xs" href="compose.php?fwd=<?= (int)$m['id'] ?>">Forward</a></div>
      </details>
    <?php endforeach; ?>

    <?php
    $rTo = $replyTo['folder'] === 'sent' ? addr_line(json_decode($replyTo['to_list'], true) ?: []) : ($replyTo['reply_to'] ?: $replyTo['from_addr']);
    $rBox = $boxes[$replyTo['mailbox_id']] ?? reset($boxes); ?>
    <form method="post" action="compose.php" enctype="multipart/form-data" class="card reply-box" id="reply">
      <?= csrf_field() ?><input type="hidden" name="reply" value="<?= (int)$replyTo['id'] ?>"><input type="hidden" name="subject" value="<?= e(preg_match('/^\s*re\s*:/i', $subject) ? $subject : 'Re: ' . $subject) ?>">
      <div class="card-h"><h2>Reply</h2><?php if (count($boxes) > 1): ?><select name="mailbox_id" class="w-auto" aria-label="Send from"><?php foreach ($boxes as $b): ?><option value="<?= (int)$b['id'] ?>"<?= selected($b['id'], $rBox['id']) ?>>From <?= e($b['email']) ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="mailbox_id" value="<?= (int)$rBox['id'] ?>"><span class="muted small">From <?= e($rBox['email']) ?></span><?php endif; ?></div>
      <label class="f"><span>To</span><input name="to" value="<?= e($rTo) ?>" required></label>
      <textarea name="body" rows="7" required placeholder="Write your reply…"><?= $rBox['signature'] !== '' ? "\n\n" . e($rBox['signature']) : '' ?></textarea>
      <div class="row reply-foot">
        <label class="file-pick"><input type="file" name="files[]" multiple data-max="<?= upload_limit() ?>"><span>Attach files</span></label>
        <label class="chk"><input type="checkbox" name="quote" value="1" checked> Include their message below</label>
        <span class="grow"></span>
        <button class="btn primary sm" type="submit">Send</button>
      </div>
    </form>
  </div>

  <div class="stack">
    <section class="card"><div class="card-h"><h2><?= $contact ? 'Contact' : 'New person' ?></h2></div>
      <?php if ($contact): ?>
        <a class="person" href="contact.php?id=<?= (int)$contact['id'] ?>"><span class="av"><?= e(initials(person_name($contact))) ?></span><span class="grow"><b><?= e(person_name($contact)) ?></b><small><?= e(implode(' · ', array_filter([$contact['title'], $contact['company'], $contact['email']]))) ?></small></span></a>
        <?php $deals = all("SELECT * FROM deals WHERE contact_id = ? AND stage NOT IN ('won','lost') ORDER BY updated_at DESC LIMIT 4", [$contact['id']]); ?>
        <?php if ($deals): ?><ul class="list"><?php foreach ($deals as $d): ?><li><a href="deal.php?id=<?= (int)$d['id'] ?>"><span class="grow"><b><?= e($d['title']) ?></b><small><?= e($d['brand']) ?></small></span><?= stage_pill($d['stage']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" class="stack-sm"><?= csrf_field() ?><input type="hidden" name="action" value="deal">
          <select name="brand" aria-label="Brand"><?php foreach (BRANDS as $b): ?><option<?= selected($b, 'Syndicate Holdings Group') ?>><?= e($b) ?></option><?php endforeach; ?></select>
          <button class="btn ghost sm" type="submit">Create a deal from this email</button>
        </form>
      <?php elseif (filter_var($other[1], FILTER_VALIDATE_EMAIL)): ?>
        <p class="muted small"><b class="text"><?= e($other[0] ?: $other[1]) ?></b><?= $other[0] ? '<br>' . e($other[1]) : '' ?><br>isn’t in your CRM yet.</p>
        <form method="post" class="stack-sm"><?= csrf_field() ?><input type="hidden" name="action" value="add_contact">
          <input name="company" placeholder="Company (optional)" aria-label="Company">
          <button class="btn primary sm" type="submit">Add as contact</button>
        </form>
        <?php $opts = contact_options(); if ($opts): ?>
        <form method="post" class="stack-sm"><?= csrf_field() ?><input type="hidden" name="action" value="link">
          <select name="contact_id" aria-label="Existing contact" required><option value="">Or link to an existing contact…</option><?php foreach ($opts as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e(person_name($o) . ($o['company'] ? ' — ' . $o['company'] : '')) ?></option><?php endforeach; ?></select>
          <button class="btn ghost sm" type="submit">Link</button>
        </form><?php endif; ?>
      <?php else: ?><p class="empty">No other person on this conversation.</p><?php endif; ?>
    </section>
    <?php if ($contact): $hist = all('SELECT thread_id, MAX(sent_at) last_at, subject FROM emails WHERE contact_id = ? AND thread_id != ? GROUP BY thread_id ORDER BY last_at DESC LIMIT 6', [$contact['id'], $tid]); if ($hist): ?>
    <section class="card"><div class="card-h"><h2>Other conversations</h2></div>
      <ul class="list"><?php foreach ($hist as $h): ?><li><a href="email.php?t=<?= e(rawurlencode($h['thread_id'])) ?>"><span class="grow"><b><?= e($h['subject'] ?: '(no subject)') ?></b><small><?= e(mail_when($h['last_at'])) ?></small></span></a></li><?php endforeach; ?></ul>
    </section><?php endif; endif; ?>
  </div>
</div>
<?php layout_bottom(); ?>
