<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/analytics-view.php';
$user = require_user();
$site = one('SELECT s.*, co.name company FROM sites s LEFT JOIN companies co ON co.id = s.company_id WHERE s.id = ?', [(int)get('id')]);
if (!$site) { flash('That website isn’t set up.'); redirect('analytics.php'); }
$p = an_params();
$R = an_report($site, an_range($p['range'], $p['from'], $p['to']), $p['f']);
$hasData = (bool)aval('SELECT 1 FROM events WHERE site_id = ? LIMIT 1', [$site['id']]);
layout_top($site['name'] . ' stats', 'analytics', $user);
?>
<div class="page-head">
  <div class="grow-head"><a class="muted small" href="analytics.php">← Site stats</a><h1><?= e($site['name']) ?></h1>
    <p class="row muted small"><?= e(implode(', ', site_domains($site))) ?><?php if ($site['company_id']): ?> · <a href="company.php?id=<?= (int)$site['company_id'] ?>"><?= e($site['company']) ?></a><?php endif; ?> · <?= $site['shared'] ? '<span class="tag lime">In client portal</span>' : '<span class="tag">Private</span>' ?></p></div>
  <div class="row"><a class="btn ghost sm" href="<?= e(an_url('site-export.php?id=' . (int)$site['id'], $p)) ?>">Export CSV</a><a class="btn primary sm" href="site-edit.php?id=<?= (int)$site['id'] ?>">Settings &amp; install code</a></div>
</div>
<?php if (!$hasData): ?><div class="notice">No visits yet. Add the install code to <?= e(site_domains($site)[0] ?? 'the site') ?>, then open the site in a browser. The first visit shows up here within seconds. <a href="site-edit.php?id=<?= (int)$site['id'] ?>">Get the code</a></div><?php endif; ?>
<?php render_site_report($site, $R, 'site.php?id=' . (int)$site['id'], $p, true); ?>
<?php layout_bottom(); ?>
