<?php
// Shared pieces for deal, contact and company pages: timeline, notes and tasks.

// Handles "add note" and "add task" posts for any record. Returns true when it handled the request.
function handle_entity_post(string $type, int $id, string $back): bool {
    $a = post('action');
    if ($a === 'note') {
        $kind = isset(ACTIVITY_KINDS[post('kind')]) ? post('kind') : 'note';
        if (post('body') !== '') { log_activity($type, $id, $kind, mb_substr(post('body'), 0, 10000)); touch_entity($type, $id); flash(ACTIVITY_KINDS[$kind] . ' logged.'); }
        redirect($back);
    }
    if ($a === 'task') {
        $due = valid_date(post('due_date')) ? post('due_date') : null;
        if (post('title') !== '') { q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [mb_substr(post('title'), 0, 300), $due, post('priority') === '1' ? 1 : 0, $type, $id, now()]); flash('Task added.'); }
        redirect($back);
    }
    if ($a === 'delete_activity') {
        q('DELETE FROM activities WHERE id = ? AND entity_type = ? AND entity_id = ?', [(int)post('activity_id'), $type, $id]);
        flash('Removed from timeline.'); redirect($back);
    }
    return false;
}
function touch_entity(string $type, int $id): void {
    $table = ['deal' => 'deals', 'contact' => 'contacts', 'company' => 'companies'][$type] ?? null;
    if ($table) q("UPDATE $table SET updated_at = ? WHERE id = ?", [now(), $id]);
}

function render_timeline(string $type, int $id): void {
    $items = timeline($type, $id); ?>
  <section class="card">
    <div class="card-h"><h2>Timeline</h2></div>
    <form method="post" class="composer">
      <?= csrf_field() ?><input type="hidden" name="action" value="note">
      <div class="seg" role="radiogroup" aria-label="Type">
        <?php foreach (ACTIVITY_KINDS as $k => $label): ?><input type="radio" name="kind" id="k-<?= $k ?>" value="<?= $k ?>"<?= $k === 'note' ? ' checked' : '' ?>><label for="k-<?= $k ?>"><?= $label ?></label><?php endforeach; ?>
      </div>
      <textarea name="body" rows="3" placeholder="What happened? Call notes, next steps, anything worth remembering…" required></textarea>
      <div class="row-end"><button class="btn primary sm" type="submit">Add to timeline</button></div>
    </form>
    <?php if (!$items): ?><p class="empty">No activity yet.</p><?php else: ?>
    <ol class="timeline">
      <?php foreach ($items as $a): ?>
        <li class="t-<?= e($a['kind']) ?>">
          <div class="t-head"><span class="kind-l"><?= e(ACTIVITY_KINDS[$a['kind']] ?? ucfirst(str_replace('_', ' ', $a['kind']))) ?></span><time title="<?= e($a['created_at']) ?>"><?= e(fmt_date($a['created_at'], 'M j, Y · g:i a')) ?></time>
            <?php if (isset(ACTIVITY_KINDS[$a['kind']])): ?><form method="post" class="inline" data-confirm="Remove this from the timeline?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="<?= (int)$a['id'] ?>"><button class="x" type="submit" aria-label="Remove">×</button></form><?php endif; ?>
          </div>
          <div class="t-body"><?= nl2br(e($a['body'])) ?></div>
        </li>
      <?php endforeach; ?>
    </ol><?php endif; ?>
  </section>
<?php }

function render_tasks(string $type, int $id): void {
    $open = all('SELECT * FROM tasks WHERE entity_type = ? AND entity_id = ? AND done_at IS NULL ORDER BY due_date IS NULL, due_date', [$type, $id]);
    $done = (int)val('SELECT COUNT(*) FROM tasks WHERE entity_type = ? AND entity_id = ? AND done_at IS NOT NULL', [$type, $id]); ?>
  <section class="card">
    <div class="card-h"><h2>Tasks</h2><?php if ($done): ?><span class="muted small"><?= $done ?> done</span><?php endif; ?></div>
    <?php if ($open): ?><ul class="tasks">
      <?php foreach ($open as $t): $late = $t['due_date'] && $t['due_date'] < today(); ?>
        <li data-task="<?= (int)$t['id'] ?>"><button class="check" type="button" aria-label="Mark done"></button><span class="grow"><b><?= e($t['title']) ?></b></span><?php if ($t['priority']): ?><span class="tag red">High</span><?php endif; ?><small class="<?= $late ? 'warn' : 'muted' ?>"><?= e($t['due_date'] ? due_label($t['due_date']) : 'No date') ?></small></li>
      <?php endforeach; ?>
    </ul><?php endif; ?>
    <form method="post" class="task-add">
      <?= csrf_field() ?><input type="hidden" name="action" value="task">
      <input name="title" placeholder="Add a task, e.g. Send proposal" required aria-label="Task">
      <input name="due_date" type="date" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" aria-label="Due date">
      <label class="chk"><input type="checkbox" name="priority" value="1"> High</label>
      <button class="btn ghost sm" type="submit">Add</button>
    </form>
  </section>
<?php }

// Email conversations with a contact or company, newest first.
function render_emails(string $type, int $id, string $email = ''): void {
    if (!val('SELECT 1 FROM mailboxes LIMIT 1')) return;
    $col = $type === 'company' ? 'company_id' : 'contact_id';
    $rows = all("SELECT thread_id, MAX(sent_at) last_at, COUNT(*) n, SUM(seen = 0) unread FROM emails WHERE $col = ? GROUP BY thread_id ORDER BY last_at DESC LIMIT 8", [$id]);
    $total = (int)val("SELECT COUNT(DISTINCT thread_id) FROM emails WHERE $col = ?", [$id]); ?>
  <section class="card">
    <div class="card-h"><h2>Email</h2><?php if ($type === 'contact' && $email !== ''): ?><a class="muted small" href="compose.php?contact=<?= $id ?>">+ Write</a><?php endif; ?></div>
    <?php if (!$rows): ?><p class="empty">No emails yet<?= $email !== '' ? ' with ' . e($email) : '' ?>.</p><?php else: ?>
    <ul class="list mail-mini"><?php foreach ($rows as $r): $m = one('SELECT folder, snippet FROM emails WHERE thread_id = ? ORDER BY sent_at DESC, id DESC LIMIT 1', [$r['thread_id']]); $s = (string)val('SELECT subject FROM emails WHERE thread_id = ? ORDER BY sent_at LIMIT 1', [$r['thread_id']]); ?>
      <li class="<?= $r['unread'] ? 'unread' : '' ?>"><a href="email.php?t=<?= e(rawurlencode($r['thread_id'])) ?>"><span class="kind <?= $m['folder'] === 'sent' ? 'k-call' : 'k-email' ?>" title="<?= $m['folder'] === 'sent' ? 'You wrote last' : 'They wrote last' ?>"></span><span class="grow"><b><?= e($s ?: '(no subject)') ?><?= $r['n'] > 1 ? ' <small class="num">' . (int)$r['n'] . '</small>' : '' ?></b><small><?= $m['folder'] === 'sent' ? 'You: ' : '' ?><?= e($m['snippet']) ?></small></span><small class="muted"><?= e(mail_when($r['last_at'])) ?></small></a></li>
    <?php endforeach; ?></ul>
    <?php if ($total > count($rows)): ?><p class="muted small"><?= $total - count($rows) ?> more in the <a href="inbox.php?q=<?= e(rawurlencode($email)) ?>">inbox</a>.</p><?php endif; endif; ?>
  </section>
<?php }
