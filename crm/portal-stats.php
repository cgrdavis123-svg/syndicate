<?php
// Website stats in the client portal: the same report you see, read-only, for the client's own sites.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/analytics-view.php';
$c = require_portal();
$site = one('SELECT * FROM sites WHERE id = ? AND shared = 1 AND company_id IS NOT NULL AND company_id = ?', [(int)get('id'), (int)$c['company_id']]);
if (get('live') === '1') {
    header('Content-Type: application/json');
    echo json_encode($site ? ['ok' => true] + an_live((int)$site['id']) : ['ok' => false]); exit;
}
if (!$site) { flash('Those stats aren’t available.'); redirect('portal.php'); }
$p = an_params();
$R = an_report($site, an_range($p['range'], $p['from'], $p['to']), $p['f']);
portal_top($site['name'] . ' stats', $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal.php">← Overview</a><h1><?= e($site['name']) ?></h1><p class="muted small"><?= e(implode(', ', site_domains($site))) ?> · Visitor stats, updated live. No cookies, and no personal data about your visitors is collected.</p></div></div>
<?php render_site_report($site, $R, 'portal-stats.php?id=' . (int)$site['id'], $p, false); ?>
<?php portal_bottom(); ?>
