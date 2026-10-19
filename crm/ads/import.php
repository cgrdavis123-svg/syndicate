<?php
// Import daily results from an ad platform's CSV export. Campaigns are matched by name (and created if new).
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$clients = all("SELECT a.*, co.name company FROM ad_clients a JOIN companies co ON co.id = a.company_id WHERE a.status != 'ended' ORDER BY co.name");
$result = null; $error = '';
if (is_post() && post('action') === 'import') {
    $a = ads_client((int)post('client')); $plat = isset(AD_PLATFORM_NAMES[post('platform')]) ? post('platform') : '';
    $f = $_FILES['file'] ?? null;
    if (!$a || !$plat) $error = 'Choose the client and the platform.';
    elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) $error = 'Choose the exported file to upload.';
    elseif ($f['size'] > 10 * 1048576) $error = 'That file is over 10 MB. Export a shorter date range.';
    else {
        $p = ads_parse_csv((string)file_get_contents($f['tmp_name']));
        if ($p['error'] !== '') $error = $p['error'];
        else { $result = ads_import($a, $plat, $p['rows'], (string)$f['name'], $user['name']) + ['client' => $a]; ads_log((int)$a['id'], 'report', 'Results updated from ' . ads_platform($plat) . ' (' . fmt_date($result['from'], 'M j') . '–' . fmt_date($result['to'], 'M j') . ')', '', false); }
    }
}
if (is_post() && post('action') === 'undo' && ($imp = one('SELECT * FROM ad_imports WHERE id = ?', [(int)post('import')]))) {
    $n = q('DELETE FROM ad_stats WHERE import_id = ?', [$imp['id']])->rowCount();
    q('DELETE FROM ad_imports WHERE id = ?', [$imp['id']]);
    flash("Removed $n day" . ($n === 1 ? '' : 's') . ' of results from that import.'); redirect('import.php');
}
$recent = all('SELECT i.*, co.name company FROM ad_imports i JOIN ad_clients a ON a.id = i.client_id JOIN companies co ON co.id = a.company_id ORDER BY i.id DESC LIMIT 15');
$pick = (int)(get('client') ?: post('client'));
layout_top('Import results', 'import', $user);
?>
<div class="page-head"><div><h1>Import results</h1><p class="muted">Export a daily campaign report from the ad platform and upload it here. Days you’ve imported before are replaced, so you can re-import any range.</p></div></div>
<?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($result): ?><p class="notice ok" role="status">Imported <?= (int)$result['rows'] ?> rows for <?= e($result['client']['company']) ?>: <?= (int)$result['campaigns'] ?> campaign<?= $result['campaigns'] === 1 ? '' : 's' ?><?= $result['new'] ? ' (' . (int)$result['new'] . ' new)' : '' ?>, <?= e(fmt_date($result['from'], 'M j')) ?>–<?= e(fmt_date($result['to'], 'M j, Y')) ?>. <a href="client.php?id=<?= (int)$result['client']['id'] ?>">See the results</a></p><?php endif; ?>
<div class="grid-2">
  <form method="post" enctype="multipart/form-data" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="import">
    <label class="f span2"><span>Client</span><select name="client" required><option value="">Choose…</option><?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $pick) ?>><?= e($c['company']) ?></option><?php endforeach; ?></select></label>
    <fieldset class="f span2"><span>Platform</span><div class="row"><?php foreach (AD_PLATFORM_NAMES as $k => $n): ?><label class="chk"><input type="radio" name="platform" value="<?= $k ?>" required> <span><?= e($n) ?></span></label><?php endforeach; ?></div></fieldset>
    <label class="f span2"><span>Exported file <small class="muted">(.csv or .tsv)</small></span><input type="file" name="file" accept=".csv,.tsv,.txt,text/csv" required></label>
    <button class="btn primary span2" type="submit">Import</button>
  </form>
  <section class="card"><div class="card-h"><h2>How to export</h2></div>
    <dl class="facts">
      <div><dt>Google Ads</dt><dd>Campaigns → set the date range → Segment → Time → Day → Download (CSV). Keep the Cost, Impr., Clicks and Conversions columns.</dd></div>
      <div><dt>Meta</dt><dd>Ads Manager → Campaigns → date range → Breakdown → By time → Day → Export table data (CSV). Keep Amount spent, Impressions, Link clicks and Results.</dd></div>
      <div><dt>TikTok</dt><dd>Ads Manager → Campaign → date range → Custom columns with Cost, Impressions, Clicks and Conversions → split by Day → Export (CSV).</dd></div>
    </dl>
    <p class="muted small">Column names are matched automatically, including Google’s UTF-16 files and the title rows above the header. Rows without a date (like totals) are skipped.</p>
  </section>
</div>
<section class="card"><div class="card-h"><h2>Recent imports</h2></div>
  <?php if (!$recent): ?><p class="empty">Nothing imported yet.</p><?php else: ?><div class="table-wrap"><table class="tbl"><thead><tr><th>When</th><th>Client</th><th>Platform</th><th>Dates</th><th class="r">Rows</th><th></th></tr></thead><tbody>
  <?php foreach ($recent as $i): ?><tr><td><?= e(ago($i['created_at'])) ?> <small class="muted"><?= e($i['created_by']) ?></small></td><td><?= e($i['company']) ?></td><td><?= e(ads_platform($i['platform'])) ?></td><td><?= $i['from_day'] ? e(fmt_date($i['from_day'], 'M j') . '–' . fmt_date($i['to_day'], 'M j')) : '—' ?></td><td class="r num"><?= (int)$i['rows'] ?></td>
    <td class="r"><form method="post" class="inline" data-confirm="Remove the results this import wrote?"><?= csrf_field() ?><input type="hidden" name="action" value="undo"><input type="hidden" name="import" value="<?= (int)$i['id'] ?>"><button class="link-danger" type="submit">Undo</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<?php layout_bottom(); ?>
