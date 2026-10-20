<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();
$db = db();

if (is_post()) {
    $id = (int)post('id');
    if (post('action') === 'delete') { $db->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]); flash('Message deleted.'); }
    elseif (post('action') === 'unread') { $db->prepare('UPDATE messages SET is_read = 0 WHERE id = ?')->execute([$id]); }
    elseif (post('action') === 'all_read') { $db->exec('UPDATE messages SET is_read = 1'); flash('All messages marked as read.'); }
    redirect('admin/messages.php' . (post('action') === 'unread' ? '' : ''));
}

$open = (int)get('id');
if ($open) $db->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$open]);
$msgs = $db->query('SELECT * FROM messages ORDER BY id DESC LIMIT 300')->fetchAll();
$unread = count(array_filter($msgs, function ($m) { return !$m['is_read']; }));

admin_header('Messages', 'messages', $admin);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span><?= $unread ?> unread</span><h1>Messages</h1><p class="muted" style="margin:6px 0 0">From the contact form. Each one is also emailed to <?= e(config('contact_to')) ?>.</p></div>
  <?php if ($unread): ?><form method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" name="action" value="all_read">Mark all as read</button></form><?php endif; ?>
</div>

<?php if (!$msgs): ?>
  <div class="panel"><p class="empty">No messages yet. Messages sent from the contact page will appear here.</p></div>
<?php else: ?>
<div class="msgs">
  <?php foreach ($msgs as $m): $isOpen = $open === (int)$m['id']; ?>
    <details class="msg<?= $m['is_read'] ? '' : ' unread' ?>" <?= $isOpen ? 'open' : '' ?> data-id="<?= (int)$m['id'] ?>">
      <summary>
        <span class="av"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
        <span class="grow"><b><?= e($m['name']) ?><?= $m['company'] ? ' · ' . e($m['company']) : '' ?></b><small><?= e(mb_strimwidth($m['message'], 0, 110, '…')) ?></small></span>
        <span class="tag"><?= e(strtoupper($m['topic'])) ?></span>
        <small class="muted mono"><?= e(ago($m['created_at'])) ?></small>
      </summary>
      <div class="msg-body">
        <p class="muted mono" style="font-size:12px;margin:0 0 12px"><?= e($m['email']) ?> · <?= e(fmt_date($m['created_at'], 'M j, Y g:i a')) ?></p>
        <div class="quote"><?= nl2br(e($m['message'])) ?></div>
        <div class="cta-row" style="margin-top:16px">
          <a class="btn btn-primary btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: your message to Syndicate') ?>">Reply by email</a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-ghost btn-sm" name="action" value="unread">Mark unread</button></form>
          <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="link-danger" name="action" value="delete">Delete</button></form>
        </div>
      </div>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
