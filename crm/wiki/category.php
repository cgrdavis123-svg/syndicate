<?php
// One topic and its articles. Team topics are for signed-in staff only.
require __DIR__ . '/../app/wiki-boot.php';
$staff = wiki_staff();
$k = get('c'); $team = isset(WIKI_TEAM_CATEGORIES[$k]);
if ((!isset(WIKI_CATEGORIES[$k]) && !$team) || ($team && !$staff)) { http_response_code(404); wiki_top('Not found', '', ['staff' => $staff, 'index' => false]); echo '<div class="w-wrap w-narrow"><h1>That topic isn’t here</h1><p><a href="./">Back to the help center</a></p></div>'; wiki_bottom(); exit; }
[$name, $desc] = wiki_categories($team)[$k];
$rows = all("SELECT slug, title, summary FROM wiki_articles WHERE category = ? AND status = 'published' ORDER BY position, title", [$k]);
wiki_top($name . ' · Syndicate Help', $desc, ['staff' => $staff, 'canonical' => wiki_url('c/' . $k), 'index' => !$team]);
?>
<div class="w-wrap w-narrow">
  <nav class="w-crumbs"><a href="<?= $team ? 'team.php' : './' ?>"><?= $team ? 'Team' : 'Help center' ?></a><span>/</span><span><?= e($name) ?></span></nav>
  <h1><?= e($name) ?></h1><p class="w-lede"><?= e($desc) ?></p>
  <ul class="w-arts"><?php foreach ($rows as $a): ?><li><a href="<?= e($a['slug']) ?>"><b><?= e($a['title']) ?></b><?php if ($a['summary'] !== ''): ?><span><?= e($a['summary']) ?></span><?php endif; ?></a></li><?php endforeach; ?></ul>
</div>
<?php wiki_bottom(); ?>
