<?php
// Free website checkups: every report run on syndicatemarketing.co/checkup.html, by the outreach agent, or here.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/checkup.php';
$user = require_user();
if (is_post()) {
    if (post('action') === 'run') {
        $r = ck_run(post('url'), 'staff', post('label'));
        if (!$r['ok']) { flash($r['error']); redirect('checkups.php'); }
        flash($r['reused'] ? 'That site was checked in the last few hours, so here’s that report.' : 'Done. Open the report to run the phone speed test.');
        redirect('checkups.php?open=' . $r['token']);
    }
    redirect('checkups.php');
}
q("UPDATE checkups SET seen_at = ? WHERE seen_at IS NULL AND contact_id IS NOT NULL", [now()]);
$via = in_array(get('via'), ['site', 'agent', 'staff'], true) ? get('via') : '';
$rows = all('SELECT k.*, c.first_name, c.last_name, c.email FROM checkups k LEFT JOIN contacts c ON c.id = k.contact_id' . ($via ? ' WHERE k.via = ?' : '') . ' ORDER BY k.id DESC LIMIT 200', $via ? [$via] : []);
$week = time() - 7 * 86400;
$k = one('SELECT COUNT(*) n, SUM(contact_id IS NOT NULL) leads, SUM(via = \'agent\') agent, SUM(via = \'agent\' AND views > 0) opened FROM checkups WHERE created_at > ?', [$week]);
$open = ck_row(get('open'));
layout_top('Website checkups', 'checkups', $user);
$VIA = ['site' => 'Website', 'agent' => 'Outreach', 'staff' => 'Staff'];
?>
<div class="page-head"><div><h1>Website checkups</h1><p class="muted small">The free checkup at <a href="<?= e(shop_site_url() ?: 'https://syndicatemarketing.co') ?>/checkup.html" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', shop_site_url() ?: 'syndicatemarketing.co')) ?>/checkup.html</a>. Anyone who asks for their report by email becomes a deal (source “Marketing site — checkup”). Reports nobody asked for are deleted after <?= CK_KEEP_DAYS ?> days.</p></div></div>
<?php if ($open): ?><div class="alert ok">Report ready for <b><?= e($open['host']) ?></b>: <a href="<?= e(ck_report_url($open, '&nv=1')) ?>" target="_blank" rel="noopener">open it</a> (opening it from here isn’t counted as a view).</div><?php endif; ?>
<div class="kpis">
  <div class="card kpi"><span>Checkups this week</span><b class="num"><?= (int)$k['n'] ?></b><small>All sources</small></div>
  <div class="card kpi"><span>Emailed reports</span><b class="num"><?= (int)$k['leads'] ?></b><small>Became deals this week</small></div>
  <div class="card kpi"><span>Sent by outreach</span><b class="num"><?= (int)$k['agent'] ?></b><small><?= (int)$k['opened'] ?> opened</small></div>
</div>
<form method="post" class="card form-grid" style="grid-template-columns:2fr 1.4fr auto;align-items:end"><?= csrf_field() ?><input type="hidden" name="action" value="run">
  <label class="f"><span>Check a website</span><input name="url" placeholder="theirbusiness.com" required></label>
  <label class="f"><span>Business name <small>(optional)</small></span><input name="label"></label>
  <div><button class="btn primary sm">Run checkup</button></div>
</form>
<p class="small"><a href="checkups.php"<?= $via === '' ? ' aria-current="page"' : '' ?>>All</a><?php foreach ($VIA as $v => $l): ?> · <a href="checkups.php?via=<?= $v ?>"<?= $via === $v ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></p>
<section class="card"><div class="table-wrap"><table class="tbl cards"><thead><tr><th>Website</th><th class="r">Score</th><th>From</th><th>Emailed to</th><th class="r">Views</th><th>Checked</th></tr></thead><tbody>
<?php foreach ($rows as $r): $sc = (int)$r['score']; ?>
  <tr><td><a href="<?= e(ck_report_url($r, '&nv=1')) ?>" target="_blank" rel="noopener"><b><?= e($r['host']) ?></b></a><?= $r['label'] !== '' ? '<br><span class="muted small">' . e($r['label']) . '</span>' : '' ?></td>
    <td data-label="Score" class="r num"><span class="tag <?= $sc >= 80 ? 'lime' : ($sc >= 55 ? '' : 'pink') ?>"><?= $sc ?></span></td>
    <td data-label="From"><?= e($VIA[$r['via']] ?? $r['via']) ?></td>
    <td data-label="Emailed to"><?php if ($r['contact_id']): ?><a href="contact.php?id=<?= (int)$r['contact_id'] ?>"><?= e(trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['email']) ?></a><?= $r['deal_id'] ? ' · <a href="deal.php?id=' . (int)$r['deal_id'] . '">deal</a>' : '' ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
    <td data-label="Views" class="r num"><?= (int)$r['views'] ?><?= $r['viewed_at'] ? '<br><span class="muted small">' . e(date('M j, g:i a', (int)$r['viewed_at'])) . '</span>' : '' ?></td>
    <td data-label="Checked"><?= e(date('M j, g:i a', (int)$r['created_at'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No checkups yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php layout_bottom();
