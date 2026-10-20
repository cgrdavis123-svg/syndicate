<?php
// Clients approve ads, or ask for changes, before anything goes live.
require __DIR__ . '/../app/ads-boot.php';
[$c, $a] = ads_require_client();
$id = (int)$a['id'];
if (is_post() && ($r = one("SELECT * FROM ad_creatives WHERE id = ? AND client_id = ? AND status IN ('review','changes','approved')", [(int)post('id'), $id]))) {
    $note = mb_substr(trim(post('note')), 0, 2000);
    if (post('decision') === 'approve') {
        q("UPDATE ad_creatives SET status = 'approved', client_note = ?, decided_by = ?, decided_at = ?, updated_at = ? WHERE id = ?", [$note, person_name($c), now(), now(), $r['id']]);
        ads_log($id, 'creative', person_name($c) . ' approved “' . $r['title'] . '”');
        send_mail(ads_staff_email(), '[Ads] Approved: ' . $r['title'] . ' (' . $a['company'] . ')', person_name($c) . ' approved “' . $r['title'] . '”.' . ($note !== '' ? "\n\nNote: $note" : '') . "\n\n" . ads_url('creative.php?id=' . $r['id']) . "\n", $c['email']);
        ads_task($a, 'Launch the approved ad “' . $r['title'] . '” for ' . $a['company'], 1);
        flash('Approved. Thanks! We’ll get it live.');
    } elseif (post('decision') === 'changes') {
        if ($note === '') { flash('Tell us what you’d like changed.'); redirect('portal-creatives.php#ad' . $r['id']); }
        q("UPDATE ad_creatives SET status = 'changes', client_note = ?, decided_by = ?, decided_at = ?, updated_at = ? WHERE id = ?", [$note, person_name($c), now(), now(), $r['id']]);
        send_mail(ads_staff_email(), '[Ads] Changes asked for: ' . $r['title'] . ' (' . $a['company'] . ')', person_name($c) . " asked for changes:\n\n$note\n\n" . ads_url('creative.php?id=' . $r['id']) . "\n", $c['email']);
        ads_task($a, 'Make the changes ' . person_name($c) . ' asked for on “' . $r['title'] . '”', 1);
        flash('Got it. We’ll make the changes and send it back.');
    }
    redirect('portal-creatives.php');
}
$rows = all("SELECT r.*, c.name camp FROM ad_creatives r LEFT JOIN ad_campaigns c ON c.id = r.campaign_id WHERE r.client_id = ? AND r.status IN ('review','changes','approved','live') ORDER BY r.status = 'review' DESC, r.updated_at DESC", [$id]);
portal_top('Approve your ads', $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal.php">← Your ads</a><h1>Approve your ads</h1><p class="muted">Nothing runs until you approve it. If something’s not right, tell us and we’ll change it.</p></div></div>
<?php if (!$rows): ?><section class="card"><p class="empty">No ads to look at right now. We’ll email you when there are.</p></section><?php endif; ?>
<div class="ad-review">
<?php foreach ($rows as $r): ?><article class="card ad-rv s-<?= e($r['status']) ?>" id="ad<?= (int)$r['id'] ?>">
  <div class="ad-preview">
    <?php if ($r['file'] !== ''): ?><?php if (ads_file_kind($r['file']) === 'video'): ?><video src="file.php?id=<?= (int)$r['id'] ?>&amp;p=1" controls preload="metadata"></video><?php else: ?><img src="file.php?id=<?= (int)$r['id'] ?>&amp;p=1" alt="<?= e($r['title']) ?>" loading="lazy"><?php endif; ?><?php endif; ?>
    <?php if ($r['primary_text'] !== ''): ?><p><?= nl2br(e($r['primary_text'])) ?></p><?php endif; ?>
    <div class="ad-preview-foot"><span><b><?= e($r['headline'] ?: $r['title']) ?></b><small class="muted"><?= e($r['url'] !== '' ? (string)parse_url($r['url'], PHP_URL_HOST) : '') ?></small></span><?php if ($r['cta'] !== ''): ?><span class="btn ghost xs"><?= e($r['cta']) ?></span><?php endif; ?></div>
  </div>
  <div class="ad-rv-side">
    <div><span class="tag <?= ['approved' => 'lime', 'live' => 'lime', 'changes' => 'red', 'review' => 'blue'][$r['status']] ?? '' ?>"><?= e(AD_CREATIVE_STATUSES[$r['status']]) ?></span><h2><?= e($r['title']) ?></h2>
      <p class="muted small"><?= e(ads_platform($r['platform']) . ' · ' . (AD_FORMATS[$r['format']] ?? '')) ?><?= $r['camp'] ? ' · ' . e($r['camp']) : '' ?></p>
      <?php if ($r['decided_at']): ?><p class="muted small"><?= $r['status'] === 'changes' ? 'You asked for changes' : 'Approved by ' . e($r['decided_by']) ?> on <?= e(fmt_date($r['decided_at'], 'M j')) ?><?= $r['client_note'] !== '' ? ': “' . e($r['client_note']) . '”' : '' ?></p><?php endif; ?></div>
    <?php if ($r['status'] === 'review'): ?>
    <form method="post" class="ad-decide"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <label class="f"><span>Anything to change? <small class="muted">(needed if you ask for changes)</small></span><textarea name="note" rows="3" placeholder="e.g. Use the photo of the blue van, and say “same-day service”"></textarea></label>
      <div class="row"><button class="btn primary" type="submit" name="decision" value="approve">Approve</button><button class="btn ghost" type="submit" name="decision" value="changes">Ask for changes</button></div>
    </form><?php endif; ?>
  </div>
</article><?php endforeach; ?>
</div>
<?php portal_bottom(); ?>
