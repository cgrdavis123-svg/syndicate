<?php
// One ad: the image or video, the words, which campaign it's for, and its approval status.
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$r = get('id') !== '' ? one('SELECT * FROM ad_creatives WHERE id = ?', [(int)get('id')]) : null;
$a = ads_client($r ? (int)$r['client_id'] : (int)(get('client') ?: post('client')));
if (!$a) { flash('Pick a client first.'); redirect('./'); }
$id = $r ? (int)$r['id'] : 0; $error = '';
if (is_post()) {
    $act = post('action');
    if ($act === 'delete' && $id) { if ($r['file'] !== '') @unlink(ads_storage() . '/' . basename($r['file'])); q('DELETE FROM ad_creatives WHERE id = ?', [$id]); flash('Ad deleted.'); redirect('creatives.php?client=' . $a['id']); }
    [$file, $ferr] = ads_save_file($_FILES['file'] ?? []);
    $title = mb_substr(post('title'), 0, 150);
    if ($ferr) $error = $ferr;
    elseif ($title === '') $error = 'Give the ad a short name, like “Summer AC tune-up, video 1”.';
    else {
        $url = post('url'); if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $camp = (int)post('campaign'); $camp = $camp && one('SELECT 1 FROM ad_campaigns WHERE id = ? AND client_id = ?', [$camp, $a['id']]) ? $camp : null;
        $vals = [$camp, isset(AD_PLATFORM_NAMES[post('platform')]) ? post('platform') : 'meta', isset(AD_FORMATS[post('format')]) ? post('format') : 'image', $title, mb_substr(post('headline'), 0, 200),
            mb_substr(post('primary_text'), 0, 3000), mb_substr(post('cta'), 0, 40), filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : '', now()];
        if ($id) {
            q('UPDATE ad_creatives SET campaign_id = ?, platform = ?, format = ?, title = ?, headline = ?, primary_text = ?, cta = ?, url = ?, updated_at = ? WHERE id = ?', array_merge($vals, [$id]));
            if ($file !== '') { if ($r['file'] !== '') @unlink(ads_storage() . '/' . basename($r['file'])); q('UPDATE ad_creatives SET file = ? WHERE id = ?', [$file, $id]); }
        } else {
            q("INSERT INTO ad_creatives (campaign_id, platform, format, title, headline, primary_text, cta, url, updated_at, client_id, file, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'draft', ?)", array_merge($vals, [$a['id'], $file, now()]));
            $id = (int)db()->lastInsertId();
        }
        $to = post('to');
        if (in_array($to, ['review', 'live', 'retired', 'draft', 'approved'], true)) {
            $cur = (string)val('SELECT status FROM ad_creatives WHERE id = ?', [$id]);
            if ($to !== $cur) {
                q('UPDATE ad_creatives SET status = ?, nudged_at = NULL, updated_at = ? WHERE id = ?', [$to, now(), $id]);
                if ($to === 'review') { ads_notify_review($a, 1); ads_log((int)$a['id'], 'creative', 'Sent “' . $title . '” for approval'); }
                if ($to === 'live') ads_log((int)$a['id'], 'creative', '“' . $title . '” is now running', '', true, $camp);
                if ($to === 'retired') ads_log((int)$a['id'], 'creative', '“' . $title . '” retired', '', true, $camp);
            }
        }
        flash($to === 'review' ? 'Saved and sent to the client for approval.' : 'Ad saved.'); redirect('creative.php?id=' . $id);
    }
}
$r = $id ? one('SELECT * FROM ad_creatives WHERE id = ?', [$id]) : ['campaign_id' => (int)get('campaign') ?: null, 'platform' => ads_platforms($a)[0] ?? 'meta', 'format' => 'image', 'title' => post('title'), 'headline' => post('headline'), 'primary_text' => post('primary_text'), 'cta' => post('cta'), 'url' => post('url'), 'file' => '', 'status' => 'draft', 'client_note' => '', 'decided_by' => '', 'decided_at' => null];
$camps = all('SELECT id, name, platform FROM ad_campaigns WHERE client_id = ? ORDER BY name', [$a['id']]);
layout_top($id ? $r['title'] : 'New ad', 'creatives', $user);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="creatives.php?client=<?= (int)$a['id'] ?>">← Ads for <?= e($a['company']) ?></a><h1><?= $id ? e($r['title']) : 'New ad' ?></h1>
  <?php if ($id): ?><p class="row muted small"><span class="tag <?= ['approved' => 'lime', 'live' => 'lime', 'changes' => 'red', 'review' => 'blue'][$r['status']] ?? '' ?>"><?= e($r['status'] === 'review' ? 'With the client' : AD_CREATIVE_STATUSES[$r['status']]) ?></span><?= $r['decided_at'] ? e(($r['status'] === 'changes' ? 'Changes asked for by ' : 'Decided by ') . $r['decided_by'] . ', ' . fmt_date($r['decided_at'], 'M j')) : '' ?></p><?php endif; ?></div></div>
<?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($id && $r['status'] === 'changes' && $r['client_note'] !== ''): ?><p class="notice"><b>The client asked:</b> “<?= e($r['client_note']) ?>”</p><?php endif; ?>
<div class="grid-2">
<form method="post" enctype="multipart/form-data" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="client" value="<?= (int)$a['id'] ?>">
  <label class="f span2"><span>Name <small class="muted">(for you and the client)</small></span><input name="title" required value="<?= e($r['title']) ?>" placeholder="e.g. Fall tune-up, image 2"></label>
  <label class="f"><span>Platform</span><select name="platform"><?php foreach (AD_PLATFORM_NAMES as $k => $n): ?><option value="<?= $k ?>"<?= selected($k, $r['platform']) ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Format</span><select name="format"><?php foreach (AD_FORMATS as $k => $n): ?><option value="<?= $k ?>"<?= selected($k, $r['format']) ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span>Campaign <small class="muted">(optional)</small></span><select name="campaign"><option value="">Not set yet</option><?php foreach ($camps as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $r['campaign_id']) ?>><?= e($c['name'] . ' · ' . ads_platform($c['platform'])) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span><?= $r['file'] !== '' ? 'Replace the image or video' : 'Image or video' ?> <small class="muted">(JPG, PNG, WebP, GIF, MP4 or MOV, up to 100 MB)</small></span><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime"></label>
  <label class="f span2"><span>Headline</span><input name="headline" maxlength="200" value="<?= e($r['headline']) ?>"></label>
  <label class="f span2"><span>Main text</span><textarea name="primary_text" rows="4"><?= e($r['primary_text']) ?></textarea></label>
  <label class="f"><span>Button</span><input name="cta" maxlength="40" value="<?= e($r['cta']) ?>" placeholder="e.g. Call now, Get a quote"></label>
  <label class="f"><span>Goes to</span><input name="url" inputmode="url" value="<?= e($r['url']) ?>" placeholder="Landing page"></label>
  <div class="span2 row between" style="flex-wrap:wrap;gap:10px">
    <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this ad?">Delete</button><?php else: ?><span></span><?php endif; ?>
    <span class="row"><button class="btn ghost sm" type="submit" name="to" value="">Save</button>
      <?php if (in_array($r['status'], ['draft', 'changes'], true)): ?><button class="btn primary sm" type="submit" name="to" value="review">Save &amp; send for approval</button><?php endif; ?>
      <?php if ($r['status'] === 'approved'): ?><button class="btn primary sm" type="submit" name="to" value="live">Mark as live</button><?php endif; ?>
      <?php if ($r['status'] === 'live'): ?><button class="btn ghost sm" type="submit" name="to" value="retired">Retire</button><?php endif; ?>
      <?php if ($r['status'] === 'review'): ?><button class="btn ghost sm" type="submit" name="to" value="draft">Take back to draft</button><?php endif; ?></span>
  </div>
</form>
<section class="card"><div class="card-h"><h2>Preview</h2><span class="muted small">How the client sees it</span></div>
  <div class="ad-preview">
    <?php if ($id && $r['file'] !== ''): ?><?php if (ads_file_kind($r['file']) === 'video'): ?><video src="file.php?id=<?= $id ?>" controls preload="metadata"></video><?php else: ?><img src="file.php?id=<?= $id ?>" alt="<?= e($r['title']) ?>"><?php endif; ?><?php endif; ?>
    <?php if ($r['primary_text'] !== ''): ?><p><?= nl2br(e($r['primary_text'])) ?></p><?php endif; ?>
    <div class="ad-preview-foot"><span><b><?= e($r['headline'] ?: 'Headline') ?></b><small class="muted"><?= e($r['url'] !== '' ? parse_url($r['url'], PHP_URL_HOST) : 'yourwebsite.com') ?></small></span><?php if ($r['cta'] !== ''): ?><span class="btn ghost xs"><?= e($r['cta']) ?></span><?php endif; ?></div>
  </div>
</section>
</div>
<?php layout_bottom(); ?>
