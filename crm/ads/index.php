<?php
// Ads overview: every ads client this month, what needs attention, and adding a client by hand.
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
if (is_post() && post('action') === 'add') {
    $co = (int)post('company');
    if (!one('SELECT 1 FROM companies WHERE id = ?', [$co])) { flash('Pick a company first.'); redirect('./'); }
    if (one('SELECT 1 FROM ad_clients WHERE company_id = ?', [$co])) { flash('That company already has an ads account.'); redirect('client.php?id=' . val('SELECT id FROM ad_clients WHERE company_id = ?', [$co])); }
    $sku = isset(AD_PLANS[post('sku')]) ? post('sku') : 'ads-one';
    $site = (int)val('SELECT id FROM sites WHERE company_id = ? ORDER BY care DESC, id LIMIT 1', [$co]) ?: null;
    $id = ads_enable_for($co, 0, $sku, ['platforms' => (array)($_POST['platforms'] ?? []), 'ad_budget' => post('budget')], $site);
    flash('Ads account created. Work through the setup checklist next.');
    redirect('client.php?id=' . $id);
}
$from = date('Y-m-01'); $to = date('Y-m-d');
$clients = all("SELECT a.*, co.name company, co.brand FROM ad_clients a JOIN companies co ON co.id = a.company_id ORDER BY a.status = 'ended', co.name");
$rows = []; $tot = ['spend' => 0, 'leads' => 0, 'clicks' => 0]; $alerts = [];
foreach ($clients as $a) {
    if ($a['status'] === 'ended') { $rows[] = $a + ['S' => null, 'pace' => null]; continue; }
    $S = ads_summary($a, $from, $to); $p = ads_pacing($a);
    $tot['spend'] += $S['spend']; $tot['leads'] += $S['leads']; $tot['clicks'] += $S['clicks'];
    foreach (ads_alerts($a) as $al) $alerts[] = $al + [3 => $a['company']];
    $rows[] = $a + ['S' => $S, 'pace' => $p];
}
usort($alerts, function ($x, $y) { return array_search($x[0], ['bad', 'warn', 'info']) <=> array_search($y[0], ['bad', 'warn', 'info']); });
$live = (int)val("SELECT COUNT(*) FROM ad_campaigns c JOIN ad_clients a ON a.id = c.client_id WHERE c.status = 'live' AND a.status != 'ended'");
$companies = all('SELECT id, name FROM companies WHERE id NOT IN (SELECT company_id FROM ad_clients) ORDER BY name');
layout_top('Ads', 'dashboard', $user);
?>
<div class="page-head"><div><p class="eyebrow"><?= e(date('F Y')) ?> so far</p><h1>Ads</h1></div>
  <div class="row"><a class="btn ghost sm" href="import.php">Import results</a><a class="btn ghost sm" href="settings.php">Access details</a></div></div>
<div class="kpis">
  <div class="card kpi"><span>Ad spend</span><b class="num"><?= e(ads_money($tot['spend'])) ?></b><small>Across <?= count(array_filter($clients, function ($a) { return $a['status'] !== 'ended'; })) ?> client<?= count($clients) === 1 ? '' : 's' ?></small></div>
  <div class="card kpi"><span>Leads</span><b class="num"><?= e(rtrim(rtrim(number_format($tot['leads'], 1), '0'), '.')) ?></b><small>Calls, forms and sales</small></div>
  <div class="card kpi"><span>Cost per lead</span><b class="num"><?= $tot['leads'] > 0 ? e(ads_money($tot['spend'] / $tot['leads'], 2)) : '—' ?></b><small>All clients</small></div>
  <div class="card kpi"><span>Live campaigns</span><b class="num"><?= $live ?></b><small><?= number_format($tot['clicks']) ?> clicks this month</small></div>
</div>
<?php if ($alerts): ?><section class="card"><div class="card-h"><h2>Needs attention</h2><span class="muted small"><?= count($alerts) ?></span></div>
  <ul class="ad-alerts"><?php foreach (array_slice($alerts, 0, 20) as [$k, $t, $l, $who]): ?><li class="<?= e($k) ?>"><a href="<?= e($l) ?>"><b><?= e($who) ?></b> · <?= e($t) ?></a></li><?php endforeach; ?></ul>
</section><?php endif; ?>
<section class="card"><div class="card-h"><h2>Clients</h2></div>
<?php if (!$clients): ?><p class="empty">No ads clients yet. Ads plans bought on the store or won on a quote show up here automatically, or add one below.</p>
<?php else: ?><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Client</th><th>Plan</th><th style="min-width:160px">Budget pacing</th><th class="r">Spend</th><th class="r">Leads</th><th class="r">Cost/lead</th><th>Latest results</th></tr></thead>
  <tbody><?php foreach ($rows as $a): $S = $a['S']; ?>
    <tr data-href="client.php?id=<?= (int)$a['id'] ?>">
      <td><a class="cell-main" href="client.php?id=<?= (int)$a['id'] ?>"><?= e($a['company']) ?></a><br><small class="muted"><?= e(AD_STATUSES[$a['status']] ?? $a['status']) ?><?= ads_platforms($a) ? ' · ' . e(implode(', ', array_map('ads_platform', ads_platforms($a)))) : '' ?></small></td>
      <td><?= isset(AD_PLANS[$a['sku']]) ? AD_PLANS[$a['sku']] . ' platform' . (AD_PLANS[$a['sku']] === 1 ? '' : 's') : '—' ?></td>
      <td><?= $a['status'] === 'ended' ? '<span class="muted small">Ended</span>' : ads_pace_bar($a['pace']) ?><?php if ($a['pace'] && $a['pace']['budget']): ?><small class="muted"><?= e(ads_money($a['pace']['spent'])) ?> of <?= e(ads_money($a['pace']['budget'])) ?></small><?php endif; ?></td>
      <td class="r num"><?= $S ? e(ads_money($S['spend'])) : '—' ?></td>
      <td class="r num"><?= $S ? e(rtrim(rtrim(number_format($S['leads'], 1), '0'), '.')) : '—' ?></td>
      <td class="r num<?= $S && $S['cpl'] && (float)$a['target_cpl'] > 0 && $S['cpl'] > 1.3 * (float)$a['target_cpl'] ? ' warn' : '' ?>"><?= $S && $S['cpl'] !== null ? e(ads_money($S['cpl'], 2)) : '—' ?></td>
      <td class="muted small"><?= $S && $S['last_day'] ? e(fmt_date($S['last_day'], 'M j')) : 'None yet' ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php if ($companies): ?>
<form method="post" class="card form-grid" id="add"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="card-h span2"><h2>Add an ads client by hand</h2><span class="muted small">For plans sold outside the store</span></div>
  <label class="f"><span>Company</span><select name="company" required><option value="">Choose…</option><?php foreach ($companies as $co): ?><option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Plan</span><select name="sku"><option value="ads-one">One platform</option><option value="ads-two">Two platforms</option><option value="ads-all">Google, Meta &amp; TikTok</option></select></label>
  <fieldset class="f span2"><span>Platforms</span><div class="row"><?php foreach (AD_PLATFORM_NAMES as $k => $n): ?><label class="chk"><input type="checkbox" name="platforms[]" value="<?= $k ?>"> <span><?= e($n) ?></span></label><?php endforeach; ?></div></fieldset>
  <label class="f"><span>Monthly ad budget <small class="muted">(paid to the platforms)</small></span><input name="budget" inputmode="decimal" placeholder="e.g. 1500"></label>
  <div class="f" style="align-self:end"><button class="btn primary" type="submit">Add client</button></div>
</form>
<?php endif; ?>
<?php layout_bottom(); ?>
