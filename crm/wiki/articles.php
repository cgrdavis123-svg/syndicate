<?php
// Every article, public and team, with views and how helpful readers found it.
require __DIR__ . '/../app/wiki-boot.php';
$user = require_user();
$aud = in_array(get('a'), ['public', 'team'], true) ? get('a') : '';
$rows = all('SELECT * FROM wiki_articles' . ($aud ? ' WHERE audience = ?' : '') . ' ORDER BY audience, category, position, title', $aud ? [$aud] : []);
layout_top('All articles', 'articles', $user);
?>
<div class="page-head"><div><h1>All articles</h1><p class="muted"><?= count($rows) ?> articles. Prices in articles come from the store’s products, so they update themselves.</p></div><a class="btn primary sm" href="edit.php">+ New article</a></div>
<nav class="seg-links"><a href="articles.php"<?= $aud === '' ? ' aria-current="page"' : '' ?>>All</a><a href="articles.php?a=public"<?= $aud === 'public' ? ' aria-current="page"' : '' ?>>Help center</a><a href="articles.php?a=team"<?= $aud === 'team' ? ' aria-current="page"' : '' ?>>Team</a></nav>
<section class="card"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Article</th><th>Topic</th><th class="r">Views</th><th class="r">Helpful</th><th>Updated</th></tr></thead><tbody>
  <?php foreach ($rows as $a): $fb = $a['helpful_yes'] + $a['helpful_no']; ?><tr data-href="edit.php?id=<?= (int)$a['id'] ?>">
    <td><a class="cell-main" href="edit.php?id=<?= (int)$a['id'] ?>"><?= e($a['title']) ?></a><br><small class="muted">/<?= e($a['slug']) ?> · <?= $a['audience'] === 'team' ? 'Team only' : 'Public' ?><?= $a['status'] !== 'published' ? ' · <span class="warn">Draft</span>' : '' ?><?= $a['edited'] ? ' · edited here' : '' ?></small></td>
    <td><?= e(wiki_cat_name($a['category'])) ?></td><td class="r num"><?= number_format((int)$a['views']) ?></td>
    <td class="r num<?= $fb >= 3 && $a['helpful_no'] / $fb > .4 ? ' warn' : '' ?>"><?= $fb ? round(100 * $a['helpful_yes'] / $fb) . '% <small class="muted">of ' . $fb . '</small>' : '—' ?></td>
    <td class="muted small"><?= e(ago($a['updated_at'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
<?php layout_bottom(); ?>
