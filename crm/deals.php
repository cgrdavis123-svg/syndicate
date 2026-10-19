<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$view = get('view') === 'list' ? 'list' : 'board';
$brand = get('brand'); $q = get('q'); $stage = get('stage'); $source = get('source');

$where = []; $args = [];
if (in_array($brand, BRANDS, true)) { $where[] = 'd.brand = ?'; $args[] = $brand; }
if ($q !== '') { $where[] = '(d.title LIKE ? OR co.name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)'; array_push($args, ...array_fill(0, 5, "%$q%")); }
if ($view === 'list' && isset(STAGES[$stage])) { $where[] = 'd.stage = ?'; $args[] = $stage; }
if ($source !== '') { $where[] = 'd.source LIKE ?'; $args[] = $source . '%'; }
$sql = 'SELECT d.*, co.name company, c.first_name, c.last_name, c.email,
          (SELECT MIN(due_date) FROM tasks t WHERE t.entity_type = \'deal\' AND t.entity_id = d.id AND t.done_at IS NULL) next_task
        FROM deals d LEFT JOIN companies co ON co.id = d.company_id LEFT JOIN contacts c ON c.id = d.contact_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
$deals = all($sql . ($view === 'board' ? ' ORDER BY d.position, d.updated_at DESC' : ' ORDER BY d.updated_at DESC'), $args);
$cols = array_fill_keys(array_keys(STAGES), []);
foreach ($deals as $d) $cols[$d['stage']][] = $d;
$qs = function (array $o) use ($view, $brand, $q, $stage, $source) { return http_build_query(array_filter(array_merge(compact('view', 'brand', 'q', 'stage', 'source'), $o), 'strlen')); };

layout_top('Pipeline', 'deals', $user);
?>
<div class="page-head">
  <div><p class="eyebrow"><?= count($deals) ?> deal<?= count($deals) === 1 ? '' : 's' ?></p><h1>Pipeline</h1></div>
  <div class="row"><div class="seg-links"><a href="deals.php?<?= e($qs(['view' => 'board', 'stage' => ''])) ?>"<?= $view === 'board' ? ' aria-current="page"' : '' ?>>Board</a><a href="deals.php?<?= e($qs(['view' => 'list'])) ?>"<?= $view === 'list' ? ' aria-current="page"' : '' ?>>List</a></div><a class="btn primary sm" href="deal-edit.php">+ New deal</a></div>
</div>
<form class="filters" method="get">
  <input type="hidden" name="view" value="<?= e($view) ?>">
  <select name="brand" data-autosubmit aria-label="Brand"><option value="">All brands</option><?php foreach (BRANDS as $b): ?><option<?= selected($b, $brand) ?>><?= e($b) ?></option><?php endforeach; ?></select>
  <?php if ($view === 'list'): ?><select name="stage" data-autosubmit aria-label="Stage"><option value="">All stages</option><?php foreach (STAGES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $stage) ?>><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Filter deals" aria-label="Filter deals">
  <?php if ($source): ?><input type="hidden" name="source" value="<?= e($source) ?>"><span class="tag">Source: <?= e($source) ?></span><?php endif; ?>
  <?php if ($brand || $q || $stage || $source): ?><a class="muted small" href="deals.php?view=<?= e($view) ?>">Clear</a><?php endif; ?>
</form>

<?php if ($view === 'board'): ?>
<div class="board" id="board">
  <?php foreach (STAGES as $s => $label): $sum = array_sum(array_column($cols[$s], 'value')); ?>
    <section class="col" data-stage="<?= $s ?>">
      <header><span><?= stage_pill($s) ?> <span class="muted small num"><?= count($cols[$s]) ?></span></span><small class="muted num"><?= money_short($sum) ?></small></header>
      <div class="col-body" data-drop="<?= $s ?>">
        <?php foreach ($cols[$s] as $d): $late = $d['next_task'] && $d['next_task'] < today(); ?>
          <a class="deal" href="deal.php?id=<?= (int)$d['id'] ?>" draggable="true" data-id="<?= (int)$d['id'] ?>">
            <b><?= e($d['title']) ?></b>
            <small><?= e($d['company'] ?: person_name($d)) ?></small>
            <span class="deal-meta"><span class="num"><?= $d['value'] ? money($d['value'], false) : '—' ?></span><?= $d['next_task'] ? '<span class="' . ($late ? 'warn' : 'muted') . '">⏱ ' . e(due_label($d['next_task'])) . '</span>' : '' ?></span>
            <?php if ($d['brand']): ?><span class="brand-dot b-<?= e(substr(md5($d['brand']), 0, 6)) ?>" data-brand="<?= e($d['brand']) ?>"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if ($s === 'new'): ?><a class="add-card" href="deal-edit.php">+ Add deal</a><?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<p class="muted small" style="margin-top:12px">Drag deals between columns to change their stage.</p>
<?php else: ?>
<?php if (!$deals): ?><div class="card"><p class="empty">No deals match. <a href="deal-edit.php">Add a deal</a></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Deal</th><th>Stage</th><th>Brand</th><th class="r">Value</th><th>Next task</th><th>Updated</th></tr></thead>
  <tbody><?php foreach ($deals as $d): ?>
    <tr data-href="deal.php?id=<?= (int)$d['id'] ?>"><td><a href="deal.php?id=<?= (int)$d['id'] ?>" class="cell-main"><b><?= e($d['title']) ?></b><small><?= e($d['company'] ?: person_name($d)) ?><?= $d['source'] ? ' · ' . e($d['source']) : '' ?></small></a></td>
      <td><?= stage_pill($d['stage']) ?></td><td><?= brand_tag($d['brand']) ?></td><td class="r num"><?= money($d['value'], false) ?></td>
      <td class="<?= $d['next_task'] && $d['next_task'] < today() ? 'warn' : 'muted' ?>"><?= e($d['next_task'] ? due_label($d['next_task']) : '—') ?></td><td class="muted"><?= e(ago($d['updated_at'])) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; endif; ?>
<?php layout_bottom(); ?>
