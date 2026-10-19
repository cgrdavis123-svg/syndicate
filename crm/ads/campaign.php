<?php
// One campaign: details, results by day, and typing in a day's numbers by hand. ?client=ID starts a new campaign.
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$c = get('id') !== '' ? one('SELECT * FROM ad_campaigns WHERE id = ?', [(int)get('id')]) : null;
$a = ads_client($c ? (int)$c['client_id'] : (int)(get('client') ?: post('client')));
if (!$a) {
    // No client picked yet: choose one.
    $list = all("SELECT a.id, co.name FROM ad_clients a JOIN companies co ON co.id = a.company_id WHERE a.status != 'ended' ORDER BY co.name");
    layout_top('New campaign', 'campaigns', $user); ?>
    <div class="page-head"><div><h1>New campaign</h1></div></div>
    <form class="card form-grid" method="get"><label class="f span2"><span>For which client?</span><select name="client" required><?php foreach ($list as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['name']) ?></option><?php endforeach; ?></select></label><button class="btn primary span2" type="submit">Continue</button></form>
    <?php layout_bottom(); exit;
}
$id = $c ? (int)$c['id'] : 0;
if (is_post()) {
    $act = post('action');
    if ($act === 'save') {
        $name = mb_substr(post('name'), 0, 200);
        if ($name === '') { flash('Give the campaign a name.'); redirect($id ? 'campaign.php?id=' . $id : 'campaign.php?client=' . $a['id']); }
        $st = isset(AD_CAMPAIGN_STATUSES[post('status')]) ? post('status') : 'draft';
        $url = post('landing_url'); if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $vals = [isset(AD_PLATFORM_NAMES[post('platform')]) ? post('platform') : 'google', $name, isset(AD_OBJECTIVES[post('objective')]) ? post('objective') : 'leads', $st, max(0, (float)post('daily_budget')),
            valid_date(post('start_date')) ? post('start_date') : null, valid_date(post('end_date')) ? post('end_date') : null, filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : '',
            mb_substr(preg_replace('/[^A-Za-z0-9_\-.]/', '-', post('utm_campaign')), 0, 80), mb_substr(post('audience'), 0, 2000), mb_substr(post('notes'), 0, 4000), now()];
        if ($id) {
            q('UPDATE ad_campaigns SET platform = ?, name = ?, objective = ?, status = ?, daily_budget = ?, start_date = ?, end_date = ?, landing_url = ?, utm_campaign = ?, audience = ?, notes = ?, updated_at = ? WHERE id = ?', array_merge($vals, [$id]));
            if ($st !== $c['status']) ads_log((int)$a['id'], $st === 'live' ? 'launch' : 'note', '“' . $name . '” ' . ['live' => 'went live', 'paused' => 'paused', 'ended' => 'ended', 'review' => 'sent for approval', 'draft' => 'moved back to draft'][$st], '', true, $id);
            if ((float)post('daily_budget') !== (float)$c['daily_budget'] && $c['status'] === 'live') ads_log((int)$a['id'], 'budget', '“' . $name . '” budget changed to ' . ads_money(post('daily_budget')) . '/day', '', true, $id);
        } else {
            q('INSERT INTO ad_campaigns (platform, name, objective, status, daily_budget, start_date, end_date, landing_url, utm_campaign, audience, notes, updated_at, client_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$a['id'], now()]));
            $id = (int)db()->lastInsertId();
            if ($st === 'live') ads_log((int)$a['id'], 'launch', '“' . $name . '” went live', '', true, $id);
        }
        if ($st === 'live' && $a['status'] === 'onboarding') q("UPDATE ad_clients SET status = 'active', updated_at = ? WHERE id = ?", [now(), $a['id']]);
        flash('Campaign saved.'); redirect('campaign.php?id=' . $id);
    }
    if ($id && $act === 'day' && valid_date(post('day'))) {
        q("INSERT OR REPLACE INTO ad_stats (campaign_id, day, impressions, clicks, spend, conversions, value, source) VALUES (?,?,?,?,?,?,?, 'manual')",
            [$id, post('day'), max(0, (int)post('impressions')), max(0, (int)post('clicks')), max(0, round((float)post('spend'), 2)), max(0, (float)post('conversions')), max(0, (float)post('value'))]);
        flash('Results for ' . fmt_date(post('day'), 'M j') . ' saved.'); redirect('campaign.php?id=' . $id . '#days');
    }
    if ($id && $act === 'delete_day') { q('DELETE FROM ad_stats WHERE campaign_id = ? AND day = ?', [$id, post('day')]); redirect('campaign.php?id=' . $id . '#days'); }
    if ($id && $act === 'delete') { q('DELETE FROM ad_campaigns WHERE id = ?', [$id]); flash('Campaign deleted.'); redirect('client.php?id=' . $a['id']); }
}
$c = $id ? one('SELECT * FROM ad_campaigns WHERE id = ?', [$id]) : ['platform' => ads_platforms($a)[0] ?? 'google', 'name' => '', 'objective' => 'leads', 'status' => 'draft', 'daily_budget' => 0, 'start_date' => '', 'end_date' => '', 'landing_url' => '', 'utm_campaign' => '', 'audience' => '', 'notes' => ''];
if ($id) {
    $from = date('Y-m-d', strtotime('-59 days')); $to = date('Y-m-d');
    $daily = ads_daily((int)$a['id'], $from, $to, $id); $T = ads_totals((int)$a['id'], '2000-01-01', $to, $id); $T30 = ads_totals((int)$a['id'], date('Y-m-d', strtotime('-29 days')), $to, $id);
    $rows = all('SELECT * FROM ad_stats WHERE campaign_id = ? ORDER BY day DESC LIMIT 90', [$id]);
    $creatives = all('SELECT * FROM ad_creatives WHERE campaign_id = ? ORDER BY id DESC', [$id]);
}
layout_top($id ? $c['name'] : 'New campaign', 'campaigns', $user);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="client.php?id=<?= (int)$a['id'] ?>">← <?= e($a['company']) ?></a><h1><?= $id ? e($c['name']) : 'New campaign' ?></h1>
  <?php if ($id): ?><p class="row muted small"><span class="tag <?= $c['status'] === 'live' ? 'lime' : '' ?>"><?= e(AD_CAMPAIGN_STATUSES[$c['status']]) ?></span><?= e(ads_platform($c['platform'])) ?> · <?= e(AD_OBJECTIVES[$c['objective']] ?? '') ?></p><?php endif; ?></div>
  <?php if ($id): ?><a class="btn ghost sm" href="creative.php?client=<?= (int)$a['id'] ?>&amp;campaign=<?= $id ?>">+ Ad for this campaign</a><?php endif; ?></div>
<?php if ($id): ?>
<div class="kpis">
  <div class="card kpi"><span>Spend, 30 days</span><b class="num"><?= e(ads_money($T30['spend'])) ?></b><small><?= e(ads_money($T['spend'])) ?> all time</small></div>
  <div class="card kpi"><span>Clicks, 30 days</span><b class="num"><?= number_format((int)$T30['clicks']) ?></b><small>CTR <?= e(ads_pct($T30['ctr'], 2)) ?></small></div>
  <div class="card kpi"><span>Conversions, 30 days</span><b class="num"><?= e(rtrim(rtrim(number_format((float)$T30['conversions'], 1), '0'), '.')) ?></b><small>Reported by <?= e(ads_platform($c['platform'])) ?></small></div>
  <div class="card kpi"><span>Cost per conversion</span><b class="num"><?= $T30['conversions'] > 0 ? e(ads_money($T30['spend'] / $T30['conversions'], 2)) : '—' ?></b><small><?= $T30['cpc'] !== null ? e(ads_money($T30['cpc'], 2)) . ' per click' : '' ?></small></div>
</div>
<section class="card"><div class="card-h"><h2>Spend, last 60 days</h2></div><?= ads_bars(array_column($daily, 'spend'), array_map(function ($d) { return date('M j', strtotime($d['day'])); }, $daily), '$', 'Spend by day') ?></section>
<?php endif; ?>
<div class="grid-2">
<form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="client" value="<?= (int)$a['id'] ?>">
  <div class="card-h span2"><h2>Details</h2></div>
  <label class="f span2"><span>Name <small class="muted">(match the name in the ad platform so imports line up)</small></span><input name="name" required value="<?= e($c['name']) ?>"></label>
  <label class="f"><span>Platform</span><select name="platform"><?php foreach (AD_PLATFORM_NAMES as $k => $n): ?><option value="<?= $k ?>"<?= selected($k, $c['platform']) ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Status</span><select name="status"><?php foreach (AD_CAMPAIGN_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $c['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Goal</span><select name="objective"><?php foreach (AD_OBJECTIVES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $c['objective']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Daily budget</span><input name="daily_budget" inputmode="decimal" value="<?= (float)$c['daily_budget'] ?: '' ?>"></label>
  <label class="f"><span>Starts</span><input type="date" name="start_date" value="<?= e((string)$c['start_date']) ?>"></label>
  <label class="f"><span>Ends <small class="muted">(optional)</small></span><input type="date" name="end_date" value="<?= e((string)$c['end_date']) ?>"></label>
  <label class="f span2"><span>Landing page</span><input name="landing_url" inputmode="url" value="<?= e($c['landing_url']) ?>"></label>
  <label class="f span2"><span>UTM campaign <small class="muted">(the utm_campaign on the ad’s link; matches Site Stats leads to this campaign)</small></span><input name="utm_campaign" value="<?= e($c['utm_campaign']) ?>" placeholder="e.g. ac-repair-search"></label>
  <?php if ($c['landing_url'] !== '' && $c['utm_campaign'] !== ''): ?><p class="span2 muted small">Link to use in the ad: <code><?= e($c['landing_url'] . (strpos($c['landing_url'], '?') === false ? '?' : '&') . 'utm_source=' . $c['platform'] . '&utm_medium=' . ($c['platform'] === 'google' ? 'cpc' : 'paid_social') . '&utm_campaign=' . $c['utm_campaign']) ?></code></p><?php endif; ?>
  <label class="f span2"><span>Audience &amp; targeting <small class="muted">(the client sees this)</small></span><textarea name="audience" rows="3" placeholder="e.g. Homeowners within 25 miles of Spartanburg searching for AC repair"><?= e($c['audience']) ?></textarea></label>
  <label class="f span2"><span>Private notes</span><textarea name="notes" rows="2"><?= e($c['notes']) ?></textarea></label>
  <div class="span2 row between"><?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this campaign and its results?">Delete</button><?php else: ?><span></span><?php endif; ?><button class="btn primary" type="submit">Save campaign</button></div>
</form>
<?php if ($id): ?>
<section class="card" id="days"><div class="card-h"><h2>Results by day</h2><a class="muted small" href="import.php?client=<?= (int)$a['id'] ?>">Import a file instead</a></div>
  <form method="post" class="ad-day"><?= csrf_field() ?><input type="hidden" name="action" value="day">
    <input type="date" name="day" value="<?= e(date('Y-m-d', strtotime('yesterday'))) ?>" aria-label="Day" required><input name="impressions" inputmode="numeric" placeholder="Impr." aria-label="Impressions"><input name="clicks" inputmode="numeric" placeholder="Clicks" aria-label="Clicks">
    <input name="spend" inputmode="decimal" placeholder="Spend $" aria-label="Spend" required><input name="conversions" inputmode="decimal" placeholder="Conv." aria-label="Conversions"><button class="btn primary xs" type="submit">Save day</button></form>
  <?php if (!$rows): ?><p class="empty" style="margin-top:10px">No results yet.</p><?php else: ?><div class="table-wrap" style="margin-top:10px"><table class="tbl"><thead><tr><th>Day</th><th class="r">Impr.</th><th class="r">Clicks</th><th class="r">Spend</th><th class="r">Conv.</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= e(fmt_date($r['day'], 'D, M j')) ?> <small class="muted"><?= $r['source'] === 'csv' ? 'import' : 'typed' ?></small></td><td class="r num"><?= number_format((int)$r['impressions']) ?></td><td class="r num"><?= number_format((int)$r['clicks']) ?></td><td class="r num"><?= e(ads_money($r['spend'], 2)) ?></td><td class="r num"><?= e(rtrim(rtrim(number_format((float)$r['conversions'], 2), '0'), '.')) ?></td>
      <td class="r"><form method="post" class="inline" data-confirm="Remove this day?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_day"><input type="hidden" name="day" value="<?= e($r['day']) ?>"><button class="x" type="submit" aria-label="Remove">×</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
  <?php if ($creatives): ?><h3 class="small" style="margin-top:18px">Ads in this campaign</h3><ul class="plain-list small"><?php foreach ($creatives as $cr): ?><li><a href="creative.php?id=<?= (int)$cr['id'] ?>"><?= e($cr['title']) ?></a> · <?= e(AD_CREATIVE_STATUSES[$cr['status']]) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php endif; ?>
</div>
<?php layout_bottom(); ?>
