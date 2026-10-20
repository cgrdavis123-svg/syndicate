<?php
// Every campaign across clients, last 30 days.
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$st = isset(AD_CAMPAIGN_STATUSES[get('status')]) ? get('status') : '';
$from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d');
$rows = all("SELECT c.*, co.name company, a.id aid,
    (SELECT COALESCE(SUM(spend),0) FROM ad_stats s WHERE s.campaign_id = c.id AND s.day >= ?) spend,
    (SELECT COALESCE(SUM(clicks),0) FROM ad_stats s WHERE s.campaign_id = c.id AND s.day >= ?) clicks,
    (SELECT COALESCE(SUM(conversions),0) FROM ad_stats s WHERE s.campaign_id = c.id AND s.day >= ?) conv,
    (SELECT MAX(day) FROM ad_stats s WHERE s.campaign_id = c.id) last_day
    FROM ad_campaigns c JOIN ad_clients a ON a.id = c.client_id JOIN companies co ON co.id = a.company_id
    WHERE a.status != 'ended'" . ($st ? ' AND c.status = ?' : '') . " ORDER BY c.status = 'live' DESC, co.name, c.name", $st ? [$from, $from, $from, $st] : [$from, $from, $from]);
layout_top('Campaigns', 'campaigns', $user);
?>
<div class="page-head"><div><p class="eyebrow">Last 30 days</p><h1>Campaigns</h1></div><a class="btn primary sm" href="campaign.php">+ New campaign</a></div>
<nav class="seg-links"><a href="campaigns.php"<?= $st === '' ? ' aria-current="page"' : '' ?>>All</a><?php foreach (AD_CAMPAIGN_STATUSES as $k => $l): ?><a href="campaigns.php?status=<?= $k ?>"<?= $st === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
<section class="card"><?php if (!$rows): ?><p class="empty">No campaigns here.</p><?php else: ?><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Campaign</th><th>Client</th><th>Status</th><th class="r">Spend</th><th class="r">Clicks</th><th class="r">Conv.</th><th class="r">Cost/conv.</th><th>Latest results</th></tr></thead><tbody>
  <?php foreach ($rows as $c): ?><tr data-href="campaign.php?id=<?= (int)$c['id'] ?>"><td><a class="cell-main" href="campaign.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a><br><small class="muted"><?= e(ads_platform($c['platform'])) ?></small></td>
    <td><a href="client.php?id=<?= (int)$c['aid'] ?>"><?= e($c['company']) ?></a></td><td><span class="tag <?= $c['status'] === 'live' ? 'lime' : '' ?>"><?= e(AD_CAMPAIGN_STATUSES[$c['status']]) ?></span></td>
    <td class="r num"><?= e(ads_money($c['spend'])) ?></td><td class="r num"><?= number_format((int)$c['clicks']) ?></td><td class="r num"><?= e(rtrim(rtrim(number_format((float)$c['conv'], 1), '0'), '.')) ?></td>
    <td class="r num"><?= $c['conv'] > 0 ? e(ads_money($c['spend'] / $c['conv'], 2)) : '—' ?></td><td class="small <?= $c['status'] === 'live' && (!$c['last_day'] || $c['last_day'] < date('Y-m-d', strtotime('-4 days'))) ? 'warn' : 'muted' ?>"><?= $c['last_day'] ? e(fmt_date($c['last_day'], 'M j')) : 'None' ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php layout_bottom(); ?>
