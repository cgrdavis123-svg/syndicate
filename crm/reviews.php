<?php
// Review requests: the Google review link, timing, and every request (scheduled, sent, not sent).
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/reviews.php';
$user = require_user();
if (is_post()) {
    $back = post('back') === 'project' && (int)post('project') ? 'project.php?id=' . (int)post('project') : 'reviews.php';
    $r = (int)post('id') ? one('SELECT * FROM review_requests WHERE id = ?', [(int)post('id')]) : null;
    $a = post('action');
    if ($a === 'settings') {
        $u = trim(post('review_url'));
        if ($u !== '' && !preg_match('#^https://\S+$#i', $u)) { flash('The review link has to start with https://'); redirect('reviews.php'); }
        setting('review_url', mb_substr($u, 0, 500));
        setting('review_on', post('review_on') === '1' ? '1' : '0');
        setting('review_delay_days', (string)max(0, min(30, (int)post('review_delay_days'))));
        flash('Saved.');
    } elseif ($r && $a === 'send') {
        $res = rv_send($r, true);
        flash($res === 'sent' ? 'Sent to ' . $r['email'] . '.' : 'Not sent: ' . $res . '.');
    } elseif ($r && $a === 'skip') {
        rv_skip($r); flash('Skipped. They won’t be asked for this project.');
    } elseif ($r && $a === 'again' && $r['status'] === 'skipped') {
        $p = $r['project_id'] ? one('SELECT * FROM projects WHERE id = ?', [(int)$r['project_id']]) : null;
        $c = $p ? rv_person($p) : null;
        q("UPDATE review_requests SET status = 'scheduled', reason = '', due_at = ?, email = COALESCE(?, email), contact_id = COALESCE(?, contact_id) WHERE id = ?", [time(), $c['email'] ?? null, $c['id'] ?? null, $r['id']]);
        flash('Scheduled. It goes out on the next run if nothing stands in the way (an overdue invoice, a refund, an ask in the last year, or the do-not-contact list).');
    }
    redirect($back);
}
$filter = isset(RV_STATUSES[get('s')]) ? get('s') : '';
$rows = all("SELECT r.*, p.name project, c.first_name, c.last_name, co.name company FROM review_requests r LEFT JOIN projects p ON p.id = r.project_id LEFT JOIN contacts c ON c.id = r.contact_id LEFT JOIN companies co ON co.id = r.company_id"
    . ($filter ? ' WHERE r.status = ?' : '') . ' ORDER BY CASE r.status WHEN \'scheduled\' THEN 0 ELSE 1 END, COALESCE(r.sent_at, r.due_at, r.created_at) DESC LIMIT 200', $filter ? [$filter] : []);
$k = one("SELECT SUM(status = 'scheduled') waiting, SUM(status = 'sent') sent, SUM(status = 'sent' AND clicked_at IS NOT NULL) opened FROM review_requests");
layout_top('Review requests', 'settings', $user);
?>
<div class="page-head"><div><a class="muted small" href="settings.php">← Settings</a><h1>Review requests</h1><p class="muted small">A few days after a project is marked Complete, the client gets one email asking for an honest Google review. Everyone gets the same email and link, nothing is offered in return, and each business is asked at most once a year. Nobody is asked while an invoice is overdue, after a refund, or if they’re on the do-not-contact list.</p></div></div>
<?php if (rv_url() === ''): ?><div class="notice bad">Add your Google review link below. Until then, requests wait and nothing is sent.</div><?php endif; ?>
<div class="kpis">
  <div class="card kpi"><span>Waiting to send</span><b class="num"><?= (int)$k['waiting'] ?></b><small><?= rv_on() ? 'Goes out ' . rv_delay() . ' day' . (rv_delay() === 1 ? '' : 's') . ' after Complete' : 'Switched off' ?></small></div>
  <div class="card kpi"><span>Sent</span><b class="num"><?= (int)$k['sent'] ?></b><small>All time</small></div>
  <div class="card kpi"><span>Opened the link</span><b class="num"><?= (int)$k['opened'] ?></b><small>Some mail apps open links to scan them, so this can run a little high</small></div>
</div>
<form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <div class="card-h span2"><h2>Settings</h2></div>
  <label class="f span2"><span>Google review link <small class="muted">(Google Business Profile → “Ask for reviews” → copy the link, e.g. https://g.page/r/…/review)</small></span><input name="review_url" type="text" inputmode="url" autocomplete="off" value="<?= e(setting('review_url')) ?>" placeholder="https://g.page/r/…/review"></label>
  <label class="f"><span>Days after Complete</span><input name="review_delay_days" type="number" min="0" max="30" value="<?= rv_delay() ?>"></label>
  <label class="chk" style="align-self:end"><input type="checkbox" name="review_on" value="1"<?= rv_on() ? ' checked' : '' ?>> Ask clients for reviews</label>
  <div class="save-bar span2"><span class="muted small"><?= rv_url() !== '' ? '<a href="' . e(rv_url()) . '" target="_blank" rel="noopener">Test the link ↗</a>' : '' ?></span><button class="btn primary sm" type="submit">Save</button></div>
</form>
<p class="small"><a href="reviews.php"<?= $filter === '' ? ' aria-current="page"' : '' ?>>All</a><?php foreach (RV_STATUSES as $s => $l): ?> · <a href="reviews.php?s=<?= $s ?>"<?= $filter === $s ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></p>
<section class="card"><div class="table-wrap"><table class="tbl cards"><thead><tr><th>Project</th><th>Client</th><th>Status</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><?= $r['project_id'] ? '<a href="project.php?id=' . (int)$r['project_id'] . '">' . e($r['project'] ?? 'Project') . '</a>' : '<span class="muted">Deleted project</span>' ?><?= $r['company'] ? '<br><span class="muted small">' . e($r['company']) . '</span>' : '' ?></td>
    <td data-label="Client"><?= $r['contact_id'] ? '<a href="contact.php?id=' . (int)$r['contact_id'] . '">' . e(trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['email']) . '</a>' : '<span class="muted">—</span>' ?><?= $r['email'] !== '' ? '<br><span class="muted small">' . e($r['email']) . '</span>' : '' ?></td>
    <td data-label="Status"><span class="tag<?= $r['status'] === 'sent' ? ' lime' : '' ?>"><?= e(RV_STATUSES[$r['status']] ?? $r['status']) ?></span><?= $r['reason'] !== '' ? '<br><span class="muted small">' . e($r['reason']) . '</span>' : '' ?><?= $r['clicked_at'] ? '<br><span class="muted small">Opened the link ' . e(date('M j', (int)$r['clicked_at'])) . '</span>' : '' ?></td>
    <td data-label="When"><?= $r['status'] === 'sent' ? e(date('M j, Y', (int)$r['sent_at'])) : ($r['status'] === 'scheduled' ? 'Goes out ' . e(date('M j', (int)$r['due_at'])) : e(date('M j, Y', (int)$r['created_at']))) ?></td>
    <td class="r"><?php if ($r['status'] === 'scheduled'): ?><form method="post" class="row" style="justify-content:flex-end;gap:8px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost sm" name="action" value="send">Send now</button><button class="link-danger" name="action" value="skip">Don’t ask</button></form>
      <?php elseif ($r['status'] === 'skipped' && $r['project_id'] && !in_array($r['reason'], ['Not linked to a client', 'On the do-not-contact list', 'Already asked in the last year', 'Asked with another project'], true)): ?><form method="post" data-confirm="Ask this client after all?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost sm" name="action" value="again">Ask after all</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">Nothing yet. When a project is marked Complete, its review request shows up here.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php layout_bottom();
