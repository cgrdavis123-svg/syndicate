<?php
// Search. Searches with no results are kept so we know which answers to write next.
require __DIR__ . '/../app/wiki-boot.php';
$staff = wiki_staff();
$q = mb_substr(get('q'), 0, 120);
$hits = $q !== '' ? wiki_search($q, (bool)$staff) : [];
if ($q !== '' && !$staff && !too_many('wiki_search', 120, 15)) { record_attempt('wiki_search'); wiki_log_search($q, count($hits)); }
wiki_top(($q !== '' ? $q . ' · ' : '') . 'Search · Syndicate Help', '', ['staff' => $staff, 'q' => $q, 'index' => false]);
?>
<div class="w-wrap w-narrow">
  <h1><?= $q === '' ? 'Search the help center' : ($hits ? count($hits) . ' answer' . (count($hits) === 1 ? '' : 's') . ' for “' . e($q) . '”' : 'No answers for “' . e($q) . '” yet') ?></h1>
  <form class="w-search lg" action="search.php" method="get" role="search"><label class="w-sr" for="q2">Search</label><input id="q2" name="q" type="search" value="<?= e($q) ?>" autocomplete="off"><button type="submit">Search</button></form>
  <?php if ($hits): ?><ul class="w-arts"><?php foreach ($hits as $h): ?><li><a href="<?= e($h['slug']) ?>"><b><?= e($h['title']) ?></b><span><?= e($h['snippet']) ?></span><small><?= e(wiki_cat_name($h['category'])) ?><?= $h['audience'] === 'team' ? ' · Team only' : '' ?></small></a></li><?php endforeach; ?></ul>
  <p class="w-dim">Not what you needed? <a href="ask.php?q=<?= e(rawurlencode($q)) ?>">Ask us directly</a>.</p>
  <?php elseif ($q !== ''): ?><div class="w-askbox wide"><b>Ask us instead</b><p>We’ve noted the search so we can write this answer. Send us your question and we’ll reply within one business day.</p><a class="w-btn" href="ask.php?q=<?= e(rawurlencode($q)) ?>">Ask a question</a></div>
    <h2 class="w-h2">Browse by topic</h2><div class="w-cats sm"><?php foreach (WIKI_CATEGORIES as $k => [$n]): ?><a class="w-cat" href="c/<?= e($k) ?>"><b><?= e($n) ?></b></a><?php endforeach; ?></div>
  <?php endif; ?>
</div>
<?php wiki_bottom(); ?>
