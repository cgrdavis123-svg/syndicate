<?php
// Team docs home (staff only): internal how-tos by topic, plus what changed recently.
require __DIR__ . '/../app/wiki-boot.php';
$user = require_user();
$counts = []; foreach (all("SELECT category, COUNT(*) n FROM wiki_articles WHERE audience = 'team' GROUP BY category") as $r) $counts[$r['category']] = (int)$r['n'];
$byCat = []; foreach (all("SELECT slug, title, category, status FROM wiki_articles WHERE audience = 'team' ORDER BY position, title") as $a) $byCat[$a['category']][] = $a;
$recent = all('SELECT slug, title, audience, updated_at, updated_by FROM wiki_articles ORDER BY updated_at DESC LIMIT 8');
$open = (int)val('SELECT COUNT(*) FROM wiki_questions WHERE answered_at IS NULL');
layout_top('Team docs', 'team', $user);
?>
<div class="page-head"><div><p class="eyebrow">Only staff can see these</p><h1>Team docs</h1></div>
  <form class="row" action="search.php" method="get" role="search"><input name="q" placeholder="Search everything…" aria-label="Search" style="height:36px;padding:0 12px;border-radius:9px;border:1px solid var(--line-2);background:var(--bg-2)"><button class="btn ghost sm" type="submit">Search</button></form></div>
<?php if ($open): ?><a class="notice ad-callout" href="insights.php"><b><?= $open ?> question<?= $open === 1 ? '' : 's' ?> from the help center waiting for an answer.</b> <span>Answer <?= ARROW ?></span></a><?php endif; ?>
<div class="grid-2">
<?php foreach (WIKI_TEAM_CATEGORIES as $k => [$n, $d]): ?>
  <section class="card"><div class="card-h"><h2><?= e($n) ?></h2><span class="muted small"><?= e($d) ?></span></div>
    <?php if (empty($byCat[$k])): ?><p class="empty">Nothing here yet.</p><?php else: ?><ul class="plain-list"><?php foreach ($byCat[$k] as $a): ?><li><a href="<?= e($a['slug']) ?>"><?= e($a['title']) ?></a><?= $a['status'] !== 'published' ? ' <span class="tag">Draft</span>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
<section class="card"><div class="card-h"><h2>Recently changed</h2><a class="muted small" href="articles.php">All articles</a></div>
  <ul class="plain-list"><?php foreach ($recent as $a): ?><li><a href="<?= e($a['slug']) ?>"><?= e($a['title']) ?></a> <small class="muted"><?= $a['audience'] === 'team' ? 'Team' : 'Public' ?> · <?= e(ago($a['updated_at'])) ?><?= $a['updated_by'] !== '' ? ' by ' . e($a['updated_by']) : '' ?></small></li><?php endforeach; ?></ul>
</section>
<?php layout_bottom(); ?>
