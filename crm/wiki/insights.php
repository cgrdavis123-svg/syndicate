<?php
// What readers need: unanswered questions, searches with no answer, and articles that didn't help.
require __DIR__ . '/../app/wiki-boot.php';
$user = require_user();
if (is_post() && post('action') === 'answered') { q('UPDATE wiki_questions SET answered_at = ? WHERE id = ?', [now(), (int)post('id')]); flash('Marked as answered.'); redirect('insights.php'); }
if (is_post() && post('action') === 'dismiss') { q('DELETE FROM wiki_searches WHERE q = ?', [post('q')]); redirect('insights.php#missed'); }
$qs = all('SELECT * FROM wiki_questions WHERE answered_at IS NULL ORDER BY id DESC LIMIT 50');
$missed = all('SELECT * FROM wiki_searches WHERE results = 0 ORDER BY count DESC, last_at DESC LIMIT 30');
$low = all("SELECT * FROM wiki_articles WHERE helpful_no >= 2 AND helpful_no * 1.0 / (helpful_yes + helpful_no) > 0.3 ORDER BY helpful_no DESC LIMIT 15");
$top = all("SELECT slug, title, views FROM wiki_articles WHERE audience = 'public' ORDER BY views DESC LIMIT 10");
$searches = all('SELECT * FROM wiki_searches WHERE results > 0 ORDER BY count DESC LIMIT 15');
layout_top('Questions & insights', 'insights', $user);
?>
<div class="page-head"><div><h1>Questions &amp; insights</h1><p class="muted">What clients ask and look for, so the help center keeps answering it.</p></div></div>
<section class="card"><div class="card-h"><h2>Questions waiting for an answer</h2><span class="muted small">From the “Ask us” form · reply by email, then mark answered</span></div>
  <?php if (!$qs): ?><p class="empty">All caught up.</p><?php else: ?><ul class="ad-msgs" style="margin-top:0"><?php foreach ($qs as $x): ?><li style="max-width:100%">
    <small class="muted"><?= e($x['name']) ?> · <a href="mailto:<?= e($x['email']) ?>"><?= e($x['email']) ?></a> · <?= e(ago($x['created_at'])) ?><?= $x['page'] !== '' ? ' · from ' . e($x['page']) : '' ?></small><p><?= nl2br(e($x['question'])) ?></p>
    <div class="row" style="margin-top:8px"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="answered"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn ghost xs" type="submit">Mark answered</button></form><a class="btn ghost xs" href="edit.php?q=<?= e(rawurlencode(mb_strimwidth($x['question'], 0, 140, '…'))) ?>">Write an article about it</a></div></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<div class="grid-2">
  <section class="card" id="missed"><div class="card-h"><h2>Searches with no answer</h2><span class="muted small">Write these next</span></div>
    <?php if (!$missed): ?><p class="empty">None yet.</p><?php else: ?><table class="tbl"><tbody><?php foreach ($missed as $m): ?><tr><td>“<?= e($m['q']) ?>”</td><td class="r num"><?= (int)$m['count'] ?>×</td><td class="r"><a class="btn ghost xs" href="edit.php?q=<?= e(rawurlencode($m['q'])) ?>">Write</a> <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="dismiss"><input type="hidden" name="q" value="<?= e($m['q']) ?>"><button class="x" type="submit" aria-label="Dismiss">×</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
  <section class="card"><div class="card-h"><h2>Articles that didn’t help</h2><span class="muted small">More than 30% said no</span></div>
    <?php if (!$low): ?><p class="empty">None so far.</p><?php else: ?><ul class="plain-list"><?php foreach ($low as $a): ?><li><a href="edit.php?id=<?= (int)$a['id'] ?>"><?= e($a['title']) ?></a> <small class="muted"><?= (int)$a['helpful_no'] ?> no · <?= (int)$a['helpful_yes'] ?> yes</small></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
</div>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>Most read</h2></div><table class="tbl"><tbody><?php foreach ($top as $a): ?><tr><td><a href="<?= e($a['slug']) ?>"><?= e($a['title']) ?></a></td><td class="r num"><?= number_format((int)$a['views']) ?></td></tr><?php endforeach; ?></tbody></table></section>
  <section class="card"><div class="card-h"><h2>Top searches</h2></div><?php if (!$searches): ?><p class="empty">None yet.</p><?php else: ?><table class="tbl"><tbody><?php foreach ($searches as $m): ?><tr><td>“<?= e($m['q']) ?>”</td><td class="r num"><?= (int)$m['count'] ?>×</td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
</div>
<?php layout_bottom(); ?>
