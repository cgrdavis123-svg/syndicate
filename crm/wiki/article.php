<?php
// One article. Reached as wiki.stusyn.com/<slug> (see .htaccess).
require __DIR__ . '/../app/wiki-boot.php';
$staff = wiki_staff();
$a = one('SELECT * FROM wiki_articles WHERE slug = ?', [wiki_slug(get('s'))]);
if (!$a || (($a['audience'] === 'team' || $a['status'] !== 'published') && !$staff)) {
    http_response_code(404);
    wiki_top('Not found · Syndicate Help', '', ['staff' => $staff, 'index' => false]);
    $guess = wiki_search(str_replace('-', ' ', get('s')), (bool)$staff, 5);
    echo '<div class="w-wrap w-narrow"><h1>We couldn’t find that page</h1><p class="w-lede">It may have moved. ' . ($guess ? 'These might help:' : '<a href="./">Go to the help center</a> or <a href="ask.php">ask us</a>.') . '</p>';
    if ($guess) { echo '<ul class="w-arts">'; foreach ($guess as $g) echo '<li><a href="' . e($g['slug']) . '"><b>' . e($g['title']) . '</b></a></li>'; echo '</ul>'; }
    echo '</div>'; wiki_bottom(); exit;
}
$bot = preg_match('/bot|crawl|spider|slurp|preview|monitor/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
if (!$staff && !$bot) q('UPDATE wiki_articles SET views = views + 1 WHERE id = ?', [$a['id']]);
$R = wiki_render($a['body']);
$team = $a['audience'] === 'team';
$related = all("SELECT slug, title FROM wiki_articles WHERE category = ? AND id != ? AND status = 'published' AND audience = ? ORDER BY position LIMIT 5", [$a['category'], $a['id'], $a['audience']]);
$thanks = get('thanks');
wiki_top($a['title'] . ' · Syndicate Help', $a['summary'] ?: mb_strimwidth(wiki_plain($a['body']), 0, 155, '…'), ['staff' => $staff, 'canonical' => wiki_url($a['slug']), 'index' => !$team && $a['status'] === 'published']);
?>
<div class="w-wrap w-article">
  <article class="w-body">
    <nav class="w-crumbs"><a href="<?= $team ? 'team.php' : './' ?>"><?= $team ? 'Team' : 'Help center' ?></a><span>/</span><a href="c/<?= e($a['category']) ?>"><?= e(wiki_cat_name($a['category'])) ?></a></nav>
    <?php if ($team): ?><p class="w-flag">Team only · not visible to clients</p><?php elseif ($a['status'] !== 'published'): ?><p class="w-flag">Draft · only staff can see this</p><?php endif; ?>
    <h1><?= e($a['title']) ?></h1>
    <?php if ($a['summary'] !== ''): ?><p class="w-lede"><?= e($a['summary']) ?></p><?php endif; ?>
    <div class="w-prose"><?= $R['html'] ?></div>
    <p class="w-updated">Updated <?= e(fmt_date($a['updated_at'], 'F j, Y')) ?><?php if ($staff): ?> · <a href="edit.php?id=<?= (int)$a['id'] ?>">Edit this article</a><?php endif; ?></p>
    <?php if (!$team): ?>
    <section class="w-helpful" aria-label="Was this helpful?">
      <?php if ($thanks === 'yes'): ?><p><b>Thanks!</b> Glad it helped.</p>
      <?php elseif ($thanks === 'no'): ?><p><b>Thanks for telling us.</b> We’ll improve this article. Need an answer now? <a href="ask.php?from=<?= e($a['slug']) ?>">Ask us directly</a>.</p>
      <?php else: ?>
      <form method="post" action="feedback.php" class="w-fb"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <span>Was this helpful?</span><button type="submit" name="helpful" value="1" class="w-btn ghost">Yes</button><button type="button" class="w-btn ghost" data-no>No</button>
        <div class="w-fb-more" hidden><label for="fbc">What were you looking for? <small>(optional)</small></label><textarea id="fbc" name="comment" rows="2" maxlength="1000"></textarea><button type="submit" name="helpful" value="0" class="w-btn">Send</button></div>
      </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </article>
  <aside class="w-side">
    <?php if (count($R['toc']) > 2): ?><nav class="w-toc" aria-label="On this page"><b>On this page</b><?php foreach ($R['toc'] as [$id, $t]): ?><a href="<?= e($a['slug']) ?>#<?= e($id) ?>"><?= e($t) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <?php if ($related): ?><nav class="w-rel" aria-label="Related"><b>More in <?= e(wiki_cat_name($a['category'])) ?></b><?php foreach ($related as $r): ?><a href="<?= e($r['slug']) ?>"><?= e($r['title']) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <?php if (!$team): ?><div class="w-askbox"><b>Still stuck?</b><p>Ask us and we’ll reply within one business day.</p><a class="w-btn" href="ask.php?from=<?= e($a['slug']) ?>">Ask a question</a></div><?php endif; ?>
  </aside>
</div>
<?php wiki_bottom(); ?>
