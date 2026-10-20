<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
if (is_post()) {
    if (post('action') === 'add' && post('title') !== '') {
        q('INSERT INTO tasks (title, due_date, priority, created_at) VALUES (?,?,?,?)', [mb_substr(post('title'), 0, 300), valid_date(post('due_date')) ? post('due_date') : null, post('priority') === '1' ? 1 : 0, now()]);
        flash('Task added.');
    } elseif (post('action') === 'delete') { q('DELETE FROM tasks WHERE id = ?', [(int)post('id')]); flash('Task deleted.'); }
    elseif (post('action') === 'clear_done') { q("DELETE FROM tasks WHERE done_at IS NOT NULL AND done_at < ?", [date('Y-m-d H:i:s', strtotime('-30 days'))]); flash('Cleared tasks completed over 30 days ago.'); }
    redirect('tasks.php' . (get('show') ? '?show=' . rawurlencode(get('show')) : ''));
}
$show = get('show') === 'done' ? 'done' : 'open';
$t = today();
$groups = $show === 'done'
    ? ['Completed' => all('SELECT * FROM tasks WHERE done_at IS NOT NULL ORDER BY done_at DESC LIMIT 100')]
    : [
        'Overdue' => all('SELECT * FROM tasks WHERE done_at IS NULL AND due_date < ? ORDER BY due_date, priority DESC', [$t]),
        'Today' => all('SELECT * FROM tasks WHERE done_at IS NULL AND due_date = ? ORDER BY priority DESC, id', [$t]),
        'Upcoming' => all('SELECT * FROM tasks WHERE done_at IS NULL AND due_date > ? ORDER BY due_date, priority DESC LIMIT 100', [$t]),
        'No date' => all('SELECT * FROM tasks WHERE done_at IS NULL AND due_date IS NULL ORDER BY priority DESC, id DESC'),
    ];
layout_top('Tasks', 'tasks', $user);
?>
<div class="page-head"><div><p class="eyebrow"><?= (int)val('SELECT COUNT(*) FROM tasks WHERE done_at IS NULL') ?> open</p><h1>Tasks</h1></div>
  <div class="seg-links"><a href="tasks.php"<?= $show === 'open' ? ' aria-current="page"' : '' ?>>To do</a><a href="tasks.php?show=done"<?= $show === 'done' ? ' aria-current="page"' : '' ?>>Done</a></div></div>
<form method="post" class="card task-add big" id="new">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="title" placeholder="What needs to happen? e.g. Follow up with Upstate Coffee" required aria-label="New task">
  <input name="due_date" type="date" value="<?= e($t) ?>" aria-label="Due date">
  <label class="chk"><input type="checkbox" name="priority" value="1"> High priority</label>
  <button class="btn primary sm" type="submit">Add task</button>
</form>
<p class="muted small">Tasks for a specific deal, contact or company can be added from that record&rsquo;s page.</p>
<?php $any = false; foreach ($groups as $label => $items): if (!$items) continue; $any = true; ?>
  <section class="card">
    <div class="card-h"><h2 class="<?= $label === 'Overdue' ? 'warn' : '' ?>"><?= e($label) ?> <span class="muted small num"><?= count($items) ?></span></h2></div>
    <ul class="tasks">
      <?php foreach ($items as $task): ?>
        <li data-task="<?= (int)$task['id'] ?>" class="<?= $task['done_at'] ? 'done' : '' ?>">
          <button class="check<?= $task['done_at'] ? ' on' : '' ?>" type="button" aria-label="<?= $task['done_at'] ? 'Mark not done' : 'Mark done' ?>"></button>
          <span class="grow"><b><?= e($task['title']) ?></b><?php if ($task['entity_type']): ?><small><?= entity_link($task['entity_type'], (int)$task['entity_id']) ?></small><?php endif; ?></span>
          <?php if ($task['priority'] && !$task['done_at']): ?><span class="tag red">High</span><?php endif; ?>
          <small class="<?= !$task['done_at'] && $task['due_date'] && $task['due_date'] < $t ? 'warn' : 'muted' ?>"><?= e($task['done_at'] ? 'Done ' . ago($task['done_at']) : ($task['due_date'] ? due_label($task['due_date']) : '')) ?></small>
          <form method="post" class="inline" data-confirm="Delete this task?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><button class="x" type="submit" aria-label="Delete task">×</button></form>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
<?php if (!$any): ?><div class="card"><p class="empty"><?= $show === 'done' ? 'No completed tasks yet.' : 'You’re all caught up.' ?></p></div><?php endif; ?>
<?php if ($show === 'done' && $any): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear_done"><button class="btn ghost sm" type="submit">Clear tasks done over 30 days ago</button></form><?php endif; ?>
<?php layout_bottom(); ?>
