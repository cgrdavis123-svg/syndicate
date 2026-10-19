<?php
// Write or edit an article. Every save keeps the previous version, so any change can be undone.
require __DIR__ . '/../app/wiki-boot.php';
$user = require_user();
$a = get('id') !== '' ? one('SELECT * FROM wiki_articles WHERE id = ?', [(int)get('id')]) : null;
$id = $a ? (int)$a['id'] : 0; $err = ''; $preview = null;
$cats = WIKI_CATEGORIES + WIKI_TEAM_CATEGORIES;
$f = $a ?: ['title' => '', 'slug' => '', 'summary' => '', 'category' => get('category') ?: 'start', 'status' => 'published', 'keywords' => '', 'position' => 100, 'body' => get('q') !== '' ? "Answer to: " . get('q') . "\n\n" : ''];
if (is_post()) {
    $act = post('action');
    if ($act === 'restore' && $id && ($r = one('SELECT * FROM wiki_revisions WHERE id = ? AND article_id = ?', [(int)post('rev'), $id]))) {
        q('INSERT INTO wiki_revisions (article_id, title, summary, body, by_name, created_at) VALUES (?,?,?,?,?,?)', [$id, $a['title'], $a['summary'], $a['body'], $a['updated_by'], now()]);
        q('UPDATE wiki_articles SET title = ?, summary = ?, body = ?, edited = 1, updated_by = ?, updated_at = ? WHERE id = ?', [$r['title'], $r['summary'], $r['body'], $user['name'], now(), $id]);
        flash('Restored the version from ' . fmt_date($r['created_at'], 'M j, g:i a') . '.'); redirect('edit.php?id=' . $id);
    }
    if ($act === 'delete' && $id) { q('DELETE FROM wiki_articles WHERE id = ?', [$id]); flash('Article deleted.'); redirect('articles.php'); }
    $f = ['title' => mb_substr(post('title'), 0, 160), 'slug' => wiki_slug(post('slug') ?: post('title')), 'summary' => mb_substr(post('summary'), 0, 300), 'category' => isset($cats[post('category')]) ? post('category') : 'start',
        'status' => post('status') === 'draft' ? 'draft' : 'published', 'keywords' => mb_substr(post('keywords'), 0, 300), 'position' => max(0, min(999, (int)post('position'))), 'body' => mb_substr(str_replace("\r", '', post('body')), 0, 100000)];
    $clash = $f['slug'] !== '' ? one('SELECT id FROM wiki_articles WHERE slug = ? AND id != ?', [$f['slug'], $id]) : null;
    $reserved = in_array($f['slug'], ['index', 'search', 'ask', 'team', 'edit', 'articles', 'insights', 'login', 'logout', 'sitemap', 'feedback', 'category', 'article', 'c', 'assets', 'robots', 'wiki'], true);
    if ($act === 'preview') $preview = wiki_render($f['body']);
    elseif ($f['title'] === '') $err = 'Give the article a title.';
    elseif ($f['slug'] === '' || $clash || $reserved) $err = 'That web address is taken. Change the address field.';
    elseif (trim($f['body']) === '') $err = 'Write the article first.';
    else {
        $aud = isset(WIKI_TEAM_CATEGORIES[$f['category']]) ? 'team' : 'public';
        if ($id) {
            q('INSERT INTO wiki_revisions (article_id, title, summary, body, by_name, created_at) VALUES (?,?,?,?,?,?)', [$id, $a['title'], $a['summary'], $a['body'], $a['updated_by'] ?: 'Original', now()]);
            q('UPDATE wiki_articles SET title = ?, slug = ?, summary = ?, category = ?, audience = ?, status = ?, keywords = ?, position = ?, body = ?, edited = 1, updated_by = ?, updated_at = ? WHERE id = ?',
                [$f['title'], $f['slug'], $f['summary'], $f['category'], $aud, $f['status'], $f['keywords'], $f['position'], $f['body'], $user['name'], now(), $id]);
        } else {
            q('INSERT INTO wiki_articles (title, slug, summary, category, audience, status, keywords, position, body, edited, updated_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?)',
                [$f['title'], $f['slug'], $f['summary'], $f['category'], $aud, $f['status'], $f['keywords'], $f['position'], $f['body'], $user['name'], now(), now()]);
            $id = (int)db()->lastInsertId();
        }
        flash('Saved. ' . ($f['status'] === 'draft' ? 'It’s a draft, so only staff can see it.' : ($aud === 'team' ? 'Visible to staff only.' : 'It’s live on the help center.'))); redirect('edit.php?id=' . $id);
    }
}
$revs = $id ? all('SELECT id, title, by_name, created_at FROM wiki_revisions WHERE article_id = ? ORDER BY id DESC LIMIT 15', [$id]) : [];
$fbs = $id ? all("SELECT * FROM wiki_feedback WHERE article_id = ? AND comment != '' ORDER BY id DESC LIMIT 10", [$id]) : [];
layout_top($id ? 'Edit: ' . $f['title'] : 'New article', 'articles', $user);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="articles.php">← All articles</a><h1><?= $id ? 'Edit article' : 'New article' ?></h1></div>
  <?php if ($id): ?><a class="btn ghost sm" href="<?= e($a['slug']) ?>" target="_blank" rel="noopener">View ↗</a><?php endif; ?></div>
<?php if ($err): ?><p class="notice bad" role="alert"><?= e($err) ?></p><?php endif; ?>
<?php if ($a && !$a['edited'] && $a['seed_hash'] !== ''): ?><p class="notice">This article came with the system. Once you save it here, your version is kept even when the built-in text is updated.</p><?php endif; ?>
<div class="grid-2 wiki-edit">
<form method="post" class="card form-grid"><?= csrf_field() ?>
  <label class="f span2"><span>Title</span><input name="title" required value="<?= e($f['title']) ?>" placeholder="Write it as the question someone would ask"></label>
  <label class="f"><span>Topic</span><select name="category"><optgroup label="Help center (public)"><?php foreach (WIKI_CATEGORIES as $k => [$n]): ?><option value="<?= $k ?>"<?= selected($k, $f['category']) ?>><?= e($n) ?></option><?php endforeach; ?></optgroup><optgroup label="Team only"><?php foreach (WIKI_TEAM_CATEGORIES as $k => [$n]): ?><option value="<?= $k ?>"<?= selected($k, $f['category']) ?>><?= e($n) ?></option><?php endforeach; ?></optgroup></select></label>
  <label class="f"><span>Status</span><select name="status"><option value="published"<?= selected('published', $f['status']) ?>>Published</option><option value="draft"<?= selected('draft', $f['status']) ?>>Draft (staff only)</option></select></label>
  <label class="f span2"><span>One-line summary <small class="muted">(shown under the title and in search results)</small></span><input name="summary" maxlength="300" value="<?= e($f['summary']) ?>"></label>
  <label class="f span2"><span>Article <small class="muted">(Markdown: ## heading, **bold**, - list, [link](https://…), [[other-article]], > **Tip:** callout)</small></span><textarea name="body" rows="22" class="mono" style="font-size:13.5px;line-height:1.55"><?= e($f['body']) ?></textarea></label>
  <details class="span2"><summary class="muted small">Live tags, address, search words and order</summary>
    <p class="muted small">Tags fill in from the store’s products, so prices never drift: <code>{{price:web-starter}}</code> <code>{{timeline:web-business}}</code> <code>{{features:care-plan}}</code> <code>{{products:ads}}</code> <code>{{name:sku}}</code> <code>{{book:discovery-call}}</code> <code>{{email}}</code> <code>{{portal}}</code> <code>{{plans}}</code> <code>{{adsportal}}</code> <code>{{pricing}}</code> <code>{{terms}}</code></p>
    <div class="form-grid" style="margin-top:10px"><label class="f"><span>Web address</span><input name="slug" value="<?= e($f['slug']) ?>" placeholder="made from the title"></label><label class="f"><span>Order in topic</span><input name="position" type="number" value="<?= (int)$f['position'] ?>"></label>
    <label class="f span2"><span>Extra search words</span><input name="keywords" value="<?= e($f['keywords']) ?>" placeholder="Other ways people might ask"></label></div></details>
  <div class="span2 row between"><?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this article? This can’t be undone.">Delete</button><?php else: ?><span></span><?php endif; ?>
    <span class="row"><button class="btn ghost sm" type="submit" name="action" value="preview" formnovalidate>Preview</button><button class="btn primary sm" type="submit" name="action" value="save">Save</button></span></div>
</form>
<div style="display:grid;gap:16px;align-content:start">
  <section class="card"><div class="card-h"><h2>Preview</h2><span class="muted small"><?= $preview ? 'Not saved yet' : 'Saved version' ?></span></div>
    <div class="wiki-preview"><?= ($preview ?? wiki_render($f['body']))['html'] ?: '<p class="empty">Write something to see it here.</p>' ?></div></section>
  <?php if ($fbs): ?><section class="card"><div class="card-h"><h2>What readers said</h2><span class="muted small"><?= (int)$a['helpful_yes'] ?> yes · <?= (int)$a['helpful_no'] ?> no</span></div><ul class="plain-list small"><?php foreach ($fbs as $x): ?><li><b><?= $x['helpful'] ? 'Yes' : 'No' ?>:</b> “<?= e($x['comment']) ?>” <span class="muted"><?= e(ago($x['created_at'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($revs): ?><section class="card"><div class="card-h"><h2>Earlier versions</h2></div><ul class="plain-list small"><?php foreach ($revs as $r): ?><li class="row between"><span><?= e(fmt_date($r['created_at'], 'M j, g:i a')) ?> · <?= e($r['by_name'] ?: '—') ?></span>
    <form method="post" class="inline" data-confirm="Go back to this version? The current text is kept as a version too."><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="rev" value="<?= (int)$r['id'] ?>"><button class="btn ghost xs" type="submit">Restore</button></form></li><?php endforeach; ?></ul></section><?php endif; ?>
</div>
</div>
<?php layout_bottom(); ?>
