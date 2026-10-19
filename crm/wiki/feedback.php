<?php
// “Was this helpful?” from an article. No cookies: same-site check and a rate limit instead.
require __DIR__ . '/../app/wiki-boot.php';
$a = one("SELECT * FROM wiki_articles WHERE id = ? AND audience = 'public'", [(int)post('id')]);
if (!is_post() || !$a) { redirect('./'); }
$yes = post('helpful') === '1';
if (wiki_same_site() && !too_many('wiki_fb', 30, 60)) {
    record_attempt('wiki_fb');
    q('UPDATE wiki_articles SET ' . ($yes ? 'helpful_yes = helpful_yes + 1' : 'helpful_no = helpful_no + 1') . ' WHERE id = ?', [$a['id']]);
    $c = mb_substr(trim(post('comment')), 0, 1000);
    q('INSERT INTO wiki_feedback (article_id, helpful, comment, created_at) VALUES (?,?,?,?)', [$a['id'], $yes ? 1 : 0, $c, now()]);
}
redirect($a['slug'] . '?thanks=' . ($yes ? 'yes' : 'no') . '#main');
