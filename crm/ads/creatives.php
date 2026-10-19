<?php
// Ads (creatives) for one client or all: drafts, waiting for approval, approved, changes asked for, live.
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$a = get('client') !== '' ? ads_client((int)get('client')) : null;
if (is_post() && post('action') === 'send' && $a) {
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $n = 0;
    foreach ($ids as $cid) $n += q("UPDATE ad_creatives SET status = 'review', nudged_at = NULL, updated_at = ? WHERE id = ? AND client_id = ? AND status IN ('draft','changes')", [now(), $cid, $a['id']])->rowCount();
    if ($n) { ads_notify_review($a, $n); ads_log((int)$a['id'], 'creative', "Sent $n ad" . ($n === 1 ? '' : 's') . ' for approval'); flash("Sent $n ad" . ($n === 1 ? '' : 's') . ' to the client for approval.'); }
    else flash('Tick the drafts you want to send.');
    redirect('creatives.php?client=' . $a['id']);
}
$st = isset(AD_CREATIVE_STATUSES[get('status')]) ? get('status') : '';
$w = $a ? 'r.client_id = ' . (int)$a['id'] : "a.status != 'ended'";
$rows = all("SELECT r.*, co.name company, c.name camp FROM ad_creatives r JOIN ad_clients a ON a.id = r.client_id JOIN companies co ON co.id = a.company_id LEFT JOIN ad_campaigns c ON c.id = r.campaign_id
    WHERE $w" . ($st ? ' AND r.status = ?' : " AND r.status != 'retired'") . " ORDER BY CASE r.status WHEN 'changes' THEN 0 WHEN 'review' THEN 1 WHEN 'draft' THEN 2 WHEN 'approved' THEN 3 ELSE 4 END, r.updated_at DESC", $st ? [$st] : []);
layout_top('Ads & approvals', 'creatives', $user);
?>
<div class="page-head"><div class="grow-head"><?php if ($a): ?><a class="muted small" href="client.php?id=<?= (int)$a['id'] ?>">← <?= e($a['company']) ?></a><?php endif; ?><h1><?= $a ? 'Ads for ' . e($a['company']) : 'Ads &amp; approvals' ?></h1><p class="muted">Nothing goes live until the client approves it in their portal.</p></div>
  <?php if ($a): ?><a class="btn primary sm" href="creative.php?client=<?= (int)$a['id'] ?>">+ New ad</a><?php endif; ?></div>
<nav class="seg-links"><a href="creatives.php<?= $a ? '?client=' . (int)$a['id'] : '' ?>"<?= $st === '' ? ' aria-current="page"' : '' ?>>Current</a><?php foreach (AD_CREATIVE_STATUSES as $k => $l): ?><a href="creatives.php?<?= $a ? 'client=' . (int)$a['id'] . '&amp;' : '' ?>status=<?= $k ?>"<?= $st === $k ? ' aria-current="page"' : '' ?>><?= e($k === 'review' ? 'With the client' : $l) ?></a><?php endforeach; ?></nav>
<?php if (!$rows): ?><section class="card"><p class="empty"><?= $a ? 'No ads here yet. Make the first one, then send it for approval.' : 'No ads here. Open a client to add ads.' ?></p></section>
<?php else: ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send">
<div class="ad-grid">
  <?php foreach ($rows as $r): ?><article class="card ad-card s-<?= e($r['status']) ?>">
    <?php if ($r['file'] !== ''): ?><a class="ad-media" href="creative.php?id=<?= (int)$r['id'] ?>"><?php if (ads_file_kind($r['file']) === 'video'): ?><video src="file.php?id=<?= (int)$r['id'] ?>" muted preload="metadata"></video><?php else: ?><img src="file.php?id=<?= (int)$r['id'] ?>" alt="" loading="lazy"><?php endif; ?></a>
    <?php else: ?><a class="ad-media text" href="creative.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['headline'] ?: $r['title']) ?></b><span><?= e(mb_strimwidth($r['primary_text'], 0, 120, '…')) ?></span></a><?php endif; ?>
    <div class="ad-meta"><div class="row between"><a class="cell-main" href="creative.php?id=<?= (int)$r['id'] ?>"><?= e($r['title']) ?></a><?php if (in_array($r['status'], ['draft', 'changes'], true) && $a): ?><label class="chk" title="Send for approval"><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"><span class="sr-only">Select</span></label><?php endif; ?></div>
      <small class="muted"><?= $a ? '' : e($r['company']) . ' · ' ?><?= e(ads_platform($r['platform'])) ?> · <?= e(AD_FORMATS[$r['format']] ?? $r['format']) ?><?= $r['camp'] ? ' · ' . e($r['camp']) : '' ?></small>
      <span class="tag <?= ['approved' => 'lime', 'live' => 'lime', 'changes' => 'red', 'review' => 'blue'][$r['status']] ?? '' ?>"><?= e($r['status'] === 'review' ? 'With the client' : AD_CREATIVE_STATUSES[$r['status']]) ?></span>
      <?php if ($r['status'] === 'changes' && $r['client_note'] !== ''): ?><p class="small ad-note">“<?= e($r['client_note']) ?>”</p><?php endif; ?></div>
  </article><?php endforeach; ?>
</div>
<?php if ($a && array_filter($rows, function ($r) { return in_array($r['status'], ['draft', 'changes'], true); })): ?><div class="save-bar"><span class="muted small">Tick drafts, then send them together. The client gets one email.</span><button class="btn primary sm" type="submit">Send for approval</button></div><?php endif; ?>
</form>
<?php endif; ?>
<?php layout_bottom(); ?>
