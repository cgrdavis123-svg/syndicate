<?php
// Help center home: search, the topics, and the most-read answers.
require __DIR__ . '/../app/wiki-boot.php';
$staff = wiki_staff();
$counts = []; foreach (all("SELECT category, COUNT(*) n FROM wiki_articles WHERE audience = 'public' AND status = 'published' GROUP BY category") as $r) $counts[$r['category']] = (int)$r['n'];
$popular = all("SELECT slug, title, category FROM wiki_articles WHERE audience = 'public' AND status = 'published' ORDER BY views DESC, position LIMIT 8");
$start = all("SELECT slug, title FROM wiki_articles WHERE audience = 'public' AND status = 'published' AND category = 'start' ORDER BY position LIMIT 5");
wiki_top('Syndicate Help Center', 'Answers about websites, paid ads, website care, Site Stats, billing and your client portal from Syndicate Marketing.', ['staff' => $staff, 'canonical' => wiki_url()]);
?>
<section class="w-hero"><div class="w-wrap">
  <p class="w-eyebrow">Help center</p>
  <h1>How can we <span class="w-serif">help?</span></h1>
  <form class="w-search lg" action="search.php" method="get" role="search"><label class="w-sr" for="q">Search the help center</label><input id="q" name="q" type="search" placeholder="Try “how long does a website take” or “cancel my plan”" autocomplete="off"><button type="submit">Search</button></form>
  <p class="w-hint">Popular: <a href="search.php?q=cancel">cancel a plan</a> · <a href="search.php?q=ad+spend">ad spend</a> · <a href="search.php?q=domain">domains</a> · <a href="search.php?q=edit">edits</a> · <a href="search.php?q=refund">refunds</a></p>
</div></section>
<div class="w-wrap w-home">
  <section aria-labelledby="topics"><h2 id="topics" class="w-h2">Topics</h2>
    <div class="w-cats"><?php foreach (WIKI_CATEGORIES as $k => [$n, $d]): if (empty($counts[$k])) continue; ?>
      <a class="w-cat" href="c/<?= e($k) ?>"><b><?= e($n) ?></b><span><?= e($d) ?></span><small><?= $counts[$k] ?> article<?= $counts[$k] === 1 ? '' : 's' ?></small></a>
    <?php endforeach; ?></div>
  </section>
  <div class="w-two">
    <section><h2 class="w-h2">New here? Start with these</h2><ul class="w-list"><?php foreach ($start as $a): ?><li><a href="<?= e($a['slug']) ?>"><?= e($a['title']) ?></a></li><?php endforeach; ?></ul></section>
    <section><h2 class="w-h2">Most read</h2><ul class="w-list"><?php foreach ($popular as $a): ?><li><a href="<?= e($a['slug']) ?>"><?= e($a['title']) ?></a> <small><?= e(wiki_cat_name($a['category'])) ?></small></li><?php endforeach; ?></ul></section>
  </div>
</div>
<?php wiki_bottom(); ?>
