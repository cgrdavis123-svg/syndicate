<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/mail.php';
$user = require_user();
$boxes = mailboxes();
if (!$boxes) redirect('mailboxes.php');
if (is_post() && post('action') === 'sync') {
    $r = mail_sync(true);
    flash($r['errors'] ? 'Couldn’t check ' . implode(', ', array_keys($r['errors'])) . ': ' . reset($r['errors']) : ($r['new'] ? $r['new'] . ' new email' . ($r['new'] === 1 ? '' : 's') . '.' : 'No new email.'));
    redirect('inbox.php' . (get('view') ? '?view=' . rawurlencode(get('view')) : ''));
}
if (is_post() && post('action') === 'archive' && !empty($_POST['t'])) {
    $ts = array_slice(array_map('strval', (array)$_POST['t']), 0, 200);
    q('UPDATE emails SET archived = 1 WHERE thread_id IN (' . implode(',', array_fill(0, count($ts), '?')) . ')', $ts);
    flash(count($ts) . ' conversation' . (count($ts) === 1 ? '' : 's') . ' archived.'); redirect('inbox.php');
}

$views = ['inbox' => 'Inbox', 'unread' => 'Unread', 'clients' => 'From contacts', 'unknown' => 'New people', 'sent' => 'Sent', 'archived' => 'Archived'];
$view = isset($views[get('view')]) ? get('view') : 'inbox';
$box = (int)get('box'); $qs = get('q');
$where = []; $args = [];
if ($view === 'sent') $where[] = "folder = 'sent'";
elseif ($view === 'archived') $where[] = "thread_id IN (SELECT thread_id FROM emails WHERE folder = 'inbox' AND archived = 1) AND thread_id NOT IN (SELECT thread_id FROM emails WHERE folder = 'inbox' AND archived = 0)";
else $where[] = "thread_id IN (SELECT thread_id FROM emails WHERE folder = 'inbox' AND archived = 0)";
if ($box) { $where[] = 'mailbox_id = ?'; $args[] = $box; }
if ($qs !== '') { $where[] = '(subject LIKE ? OR from_name LIKE ? OR addrs LIKE ? OR body_text LIKE ?)'; array_push($args, "%$qs%", "%$qs%", "%$qs%", "%$qs%"); }
$having = ['unread' => 'HAVING unread > 0', 'clients' => 'HAVING linked > 0', 'unknown' => 'HAVING linked = 0'][$view] ?? '';
$page = max(1, (int)get('p')); $per = 50;
$threads = all('SELECT thread_id, MAX(sent_at) last_at, COUNT(*) n, SUM(seen = 0) unread, MAX(contact_id IS NOT NULL) linked FROM emails WHERE ' . implode(' AND ', $where)
    . " GROUP BY thread_id $having ORDER BY last_at DESC LIMIT " . ($per + 1) . ' OFFSET ' . (($page - 1) * $per), $args);
$more = count($threads) > $per; $threads = array_slice($threads, 0, $per);
$boxNames = array_column($boxes, 'email', 'id');
$errors = array_filter(array_column($boxes, 'last_error', 'email'));
layout_top('Inbox', 'inbox', $user);
$link = function (array $o) use ($view, $box, $qs) { return 'inbox.php?' . http_build_query(array_filter(array_merge(['view' => $view === 'inbox' ? '' : $view, 'box' => $box ?: '', 'q' => $qs], $o))); };
?>
<div class="page-head">
  <div><p class="eyebrow"><?= e(implode(' · ', $boxNames)) ?></p><h1>Inbox</h1></div>
  <div class="row">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button class="btn ghost sm" type="submit" title="Last checked <?= e(ago(setting('mail_last_sync'))) ?>">Check for mail</button></form>
    <a class="btn ghost sm" href="mailboxes.php">Accounts</a>
    <a class="btn primary sm" href="compose.php">Write email</a>
  </div>
</div>
<?php foreach ($errors as $em => $err): ?><p class="notice bad"><b><?= e($em) ?>:</b> <?= e($err) ?> <a href="mailboxes.php">Check the account</a></p><?php endforeach; ?>
<p class="notice" id="newMail" hidden></p>
<div class="filters">
  <nav class="seg-links" aria-label="Views"><?php foreach ($views as $k => $l): ?><a href="<?= e($link(['view' => $k === 'inbox' ? '' : $k, 'p' => ''])) ?>"<?= $view === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
  <form class="filters" method="get">
    <?php if ($view !== 'inbox'): ?><input type="hidden" name="view" value="<?= e($view) ?>"><?php endif; ?>
    <?php if (count($boxes) > 1): ?><select name="box" data-autosubmit aria-label="Mailbox"><option value="">All mailboxes</option><?php foreach ($boxes as $b): ?><option value="<?= (int)$b['id'] ?>"<?= selected($box, $b['id']) ?>><?= e($b['email']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search email" aria-label="Search email">
  </form>
</div>
<form method="post" class="card flush mail-list-card" id="mailList">
  <?= csrf_field() ?><input type="hidden" name="action" value="archive">
  <?php if (!$threads): ?><p class="empty pad"><?= $qs !== '' ? 'No email matches “' . e($qs) . '”.' : ($view === 'inbox' ? 'You’re all caught up.' : 'Nothing here.') ?><?= !setting('mail_last_sync') ? ' Your mail is being copied in for the first time. This can take a minute.' : '' ?></p>
  <?php else: ?>
  <div class="mail-bar"><label class="chk"><input type="checkbox" id="selAll"> Select</label><button class="btn ghost xs" type="submit" id="archiveBtn" disabled>Archive</button><span class="muted small">Archiving only hides a conversation here. Your mail stays on the server.</span></div>
  <ul class="mail-list">
    <?php foreach ($threads as $t):
      $m = one('SELECT * FROM emails WHERE thread_id = ? ORDER BY sent_at DESC, id DESC LIMIT 1', [$t['thread_id']]);
      $first = one('SELECT subject FROM emails WHERE thread_id = ? ORDER BY sent_at LIMIT 1', [$t['thread_id']]);
      $sent = $m['folder'] === 'sent';
      $to = json_decode($m['to_list'], true) ?: [];
      $who = $sent ? 'To: ' . ($to ? short_name($to[0]) : '—') : ($m['from_name'] ?: $m['from_addr']);
      $c = $m['contact_id'] ? one('SELECT c.*, co.name company FROM contacts c LEFT JOIN companies co ON co.id = c.company_id WHERE c.id = ?', [$m['contact_id']]) : null;
      $hasAtt = array_filter(json_decode($m['attachments'], true) ?: [], function ($a) { return !$a['inline']; }); ?>
      <li class="<?= $t['unread'] ? 'unread' : '' ?>">
        <input type="checkbox" name="t[]" value="<?= e($t['thread_id']) ?>" aria-label="Select conversation">
        <a href="email.php?t=<?= e(rawurlencode($t['thread_id'])) ?>">
          <span class="av"><?= e(initials($sent && $to ? short_name($to[0]) : ($m['from_name'] ?: $m['from_addr']))) ?></span>
          <span class="mail-who"><b><?= e($who) ?></b><?php if ($t['n'] > 1): ?><small class="num"><?= (int)$t['n'] ?></small><?php endif; ?></span>
          <span class="mail-what"><b><?= e(($first['subject'] ?? '') ?: '(no subject)') ?><?= $hasAtt ? ' <span class="clip-ic" title="Attachment">⧉</span>' : '' ?></b><small><?= e($m['snippet']) ?></small></span>
          <span class="mail-meta"><?php if ($c): ?><span class="tag lime" title="Contact"><?= e(person_name($c)) ?></span><?php elseif (!$sent): ?><span class="tag">New</span><?php endif; ?><?php if (count($boxes) > 1): ?><span class="tag"><?= e(strstr($boxNames[$m['mailbox_id']] ?? '', '@', true)) ?></span><?php endif; ?><time title="<?= e($m['sent_at']) ?>"><?= e(mail_when($m['sent_at'])) ?></time></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($page > 1 || $more): ?><div class="mail-bar"><?php if ($page > 1): ?><a class="btn ghost xs" href="<?= e($link(['p' => $page - 1])) ?>">← Newer</a><?php endif; ?><?php if ($more): ?><a class="btn ghost xs" href="<?= e($link(['p' => $page + 1])) ?>">Older →</a><?php endif; ?></div><?php endif; ?>
  <?php endif; ?>
</form>
<?php layout_bottom(); ?>
