<?php
// Website drafts: every website project the builder agent works on, and what each one is waiting for.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/builds.php';
$user = require_user();
if (is_post()) {
    if (post('action') === 'toggle') { setting('build_on', build_on() ? '0' : '1'); flash(build_on() ? 'The builder is on.' : 'The builder is off. Nothing new gets claimed until you switch it back on.'); }
    redirect('builds.php');
}
$filter = get('s');
$rows = all('SELECT b.*, p.name project, p.due_date FROM builds b JOIN projects p ON p.id = b.project_id' . (isset(BUILD_STATUSES[$filter]) ? ' WHERE b.status = ?' : " WHERE b.status != 'cancelled'")
    . " ORDER BY CASE b.status WHEN 'blocked' THEN 0 WHEN 'review' THEN 1 WHEN 'changes' THEN 2 WHEN 'building' THEN 3 WHEN 'ready' THEN 4 WHEN 'sent' THEN 5 WHEN 'waiting' THEN 6 ELSE 7 END, b.updated_at DESC LIMIT 200", isset(BUILD_STATUSES[$filter]) ? [$filter] : []);
$c = []; foreach (all('SELECT status, COUNT(*) n FROM builds GROUP BY status') as $r) $c[$r['status']] = (int)$r['n'];
$noKey = setting('outreach_api_hash') === '';
layout_top('Website drafts', 'builds', $user);
?>
<div class="page-head"><div><a class="muted small" href="projects.php">← Projects</a><h1>Website drafts</h1>
  <p class="muted small">Paid website projects the builder agent drafts for you. A build starts <?= BUILD_KICKOFF_WAIT ?> hours after the client’s kickoff call (or when you mark it ready). The agent builds from the checkout brief, their portal files and your notes, and you review every draft before the client sees it. The client’s Approve or Request changes in their portal moves it on; changes go back to the agent within the rounds their package includes.</p></div>
  <form method="post"><?= csrf_field() ?><button class="btn <?= build_on() ? 'ghost' : 'primary' ?> sm" name="action" value="toggle"><?= build_on() ? 'Switch the builder off' : 'Switch the builder on' ?></button></form>
</div>
<?php if ($noKey): ?><div class="notice bad">The builder signs in with the outreach agent key, and there isn’t one yet. Make it in <a href="outreach.php">Outreach</a>.</div><?php endif; ?>
<?php if (!build_on()): ?><div class="notice">The builder is off. Drafts in progress can still finish, but nothing new is claimed.</div><?php endif; ?>
<div class="kpis">
  <div class="card kpi"><span>Waiting on you</span><b class="num"><?= ($c['review'] ?? 0) + ($c['blocked'] ?? 0) ?></b><small>Drafts to review, questions</small></div>
  <div class="card kpi"><span>Queued or building</span><b class="num"><?= ($c['ready'] ?? 0) + ($c['changes'] ?? 0) + ($c['building'] ?? 0) ?></b><small>The agent’s turn</small></div>
  <div class="card kpi"><span>With clients</span><b class="num"><?= $c['sent'] ?? 0 ?></b><small>Waiting for approval</small></div>
  <div class="card kpi"><span>Waiting for kickoff</span><b class="num"><?= $c['waiting'] ?? 0 ?></b><small>Not started yet</small></div>
</div>
<p class="row" style="flex-wrap:wrap;gap:6px"><a class="btn <?= $filter === '' ? 'primary' : 'ghost' ?> xs" href="builds.php">All</a><?php foreach (BUILD_STATUSES as $k => $l): if (empty($c[$k])) continue; ?><a class="btn <?= $filter === $k ? 'primary' : 'ghost' ?> xs" href="builds.php?s=<?= e($k) ?>"><?= e($l) ?> <span class="muted"><?= $c[$k] ?></span></a><?php endforeach; ?></p>
<section class="card"><div class="table-wrap"><table class="tbl cards"><thead><tr><th>Client</th><th>Package</th><th>Status</th><th class="r">Draft</th><th>Changes</th><th>Updated</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><a href="build.php?id=<?= (int)$r['id'] ?>"><b><?= e(build_who($r)) ?></b></a><br><span class="muted small"><?= e($r['project']) ?></span></td>
    <td data-label="Package"><?= e((string)val('SELECT name FROM products WHERE sku = ?', [$r['sku']]) ?: $r['sku']) ?></td>
    <td data-label="Status"><?= build_pill($r['status']) ?></td>
    <td data-label="Draft" class="r num"><?= $r['version'] ? 'v' . (int)$r['version'] : '—' ?></td>
    <td data-label="Changes" class="num"><?= (int)$r['rounds_used'] ?> of <?= (int)$r['rounds'] ?></td>
    <td data-label="Updated" class="muted small"><?= e(date('M j, g:i a', (int)$r['updated_at'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No website drafts<?= $filter ? ' with that status' : ' yet. One is added for every paid website order and won quote with a website package; you can also start one from a project' ?>.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php layout_bottom();
