<?php
// The client's ads portal: this month's results, campaigns, ads waiting for approval, what we did, reports and messages.
require __DIR__ . '/../app/ads-boot.php';
[$c, $a] = ads_require_client();
$id = (int)$a['id'];
if (is_post() && post('action') === 'message') {
    $body = mb_substr(trim(post('body')), 0, 5000);
    if ($body === '') { flash('Write a message first.'); redirect('portal.php#messages'); }
    if (too_many('ads_msg', 20, 60)) { flash('That’s a lot of messages at once. Wait a little, or email us.'); redirect('portal.php#messages'); }
    record_attempt('ads_msg');
    q("INSERT INTO ad_messages (client_id, author, name, body, created_at) VALUES (?, 'client', ?, ?, ?)", [$id, person_name($c), $body, now()]);
    send_mail(ads_staff_email(), '[Ads] Message from ' . person_name($c) . ' (' . $a['company'] . ')', $body . "\n\nReply: " . ads_url('client.php?id=' . $id . '#messages') . "\n", $c['email']);
    ads_task($a, 'Reply to ' . person_name($c) . '’s ads message (' . $a['company'] . ')', 1);
    flash('Sent. We’ll reply within one business day.'); redirect('portal.php#messages');
}
$ranges = ['month' => ['This month', date('Y-m-01'), date('Y-m-d')], 'last' => ['Last month', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))], '90' => ['Last 90 days', date('Y-m-d', strtotime('-89 days')), date('Y-m-d')]];
$rk = isset($ranges[get('r')]) ? get('r') : 'month'; [$rl, $from, $to] = $ranges[$rk];
$S = ads_summary($a, $from, $to); $pace = ads_pacing($a); $daily = ads_daily($id, $from, $to);
$dl = array_map(function ($d) { return date('M j', strtotime($d['day'])); }, $daily);
$camps = all("SELECT * FROM ad_campaigns WHERE client_id = ? AND status IN ('review','live','paused','ended') ORDER BY status = 'live' DESC, name", [$id]);
$waiting = (int)val("SELECT COUNT(*) FROM ad_creatives WHERE client_id = ? AND status = 'review'", [$id]);
$log = all('SELECT * FROM ad_log WHERE client_id = ? AND shared = 1 ORDER BY created_at DESC, id DESC LIMIT 15', [$id]);
$msgs = all('SELECT * FROM ad_messages WHERE client_id = ? ORDER BY id DESC LIMIT 20', [$id]);
q("UPDATE ad_messages SET seen_at = ? WHERE client_id = ? AND author = 'staff' AND seen_at IS NULL", [now(), $id]);
$steps = ads_onboarding($a); $stepsLeft = count(array_filter($steps, function ($s) { return !$s['done']; }));
portal_top('Your ads', $c);
?>
<div class="page-head"><div class="grow-head"><h1>Your ads</h1><p class="muted"><?= e($a['company']) ?><?= ads_platforms($a) ? ' · ' . e(implode(', ', array_map('ads_platform', ads_platforms($a)))) : '' ?></p></div>
  <nav class="seg-links" aria-label="Date range"><?php foreach ($ranges as $k => [$l]): ?><a href="portal.php?r=<?= $k ?>"<?= $k === $rk ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav></div>
<?php if ($waiting): ?><a class="notice ad-callout" href="portal-creatives.php"><b><?= $waiting ?> new ad<?= $waiting === 1 ? '' : 's' ?> to approve.</b> Nothing goes live until you say so. <span>Review <?= ARROW ?></span></a><?php endif; ?>
<?php if ($stepsLeft && $a['status'] === 'onboarding'): ?><a class="notice ad-callout" href="portal-setup.php"><b>Getting set up: <?= count($steps) - $stepsLeft ?> of <?= count($steps) ?> steps done.</b> See what’s next and how to give us access. <span>Setup <?= ARROW ?></span></a><?php endif; ?>
<div class="kpis">
  <div class="card kpi"><span>Ad spend</span><b class="num"><?= e(ads_money($S['spend'])) ?></b><?php if ($rk === 'month' && $pace): ?><?= ads_pace_bar($pace) ?><small>of your <?= e(ads_money($pace['budget'])) ?> monthly budget</small><?php else: ?><small><?= e($rl) ?></small><?php endif; ?></div>
  <div class="card kpi"><span>Leads</span><b class="num"><?= e(rtrim(rtrim(number_format($S['leads'], 1), '0'), '.')) ?></b><small><?= $S['leads_src'] === 'site' ? 'Calls, emails, forms and sales from ad visitors' : 'Reported by the ad platforms' ?></small></div>
  <div class="card kpi"><span>Cost per lead</span><b class="num"><?= $S['cpl'] !== null ? e(ads_money($S['cpl'], 2)) : '—' ?></b><small><?= (float)$a['target_cpl'] > 0 ? 'Our target: ' . e(ads_money($a['target_cpl'], 2)) : 'Ad spend ÷ leads' ?></small></div>
  <div class="card kpi"><span>Clicks</span><b class="num"><?= number_format((int)$S['clicks']) ?></b><small><?= $S['cpc'] !== null ? e(ads_money($S['cpc'], 2)) . ' per click' : 'People who clicked an ad' ?></small></div>
</div>
<section class="card"><div class="card-h"><h2>Ad spend by day</h2><span class="muted small"><?= $S['last_day'] ? 'Updated to ' . e(fmt_date($S['last_day'], 'M j')) : 'Results appear once your ads are running' ?></span></div><?= ads_bars(array_column($daily, 'spend'), $dl, '$', 'Ad spend by day') ?>
  <p class="muted small" style="margin-top:8px">Ad spend goes to <?= e(implode(' and ', array_map('ads_platform', ads_platforms($a) ?: ['google']))) ?> directly from your card. Our management fee is separate.</p></section>
<section class="card"><div class="card-h"><h2>Campaigns</h2></div>
  <?php if (!$camps): ?><p class="empty">Your campaigns show here once they’re planned.</p><?php else: ?><div class="table-wrap"><table class="tbl"><thead><tr><th>Campaign</th><th>Status</th><th class="r">Spend</th><th class="r">Clicks</th><th class="r">Conversions</th></tr></thead><tbody>
  <?php foreach ($camps as $cp): $t = ads_totals($id, $from, $to, (int)$cp['id']); ?><tr><td><b><?= e($cp['name']) ?></b><br><small class="muted"><?= e(ads_platform($cp['platform']) . ' · ' . (AD_OBJECTIVES[$cp['objective']] ?? '')) ?></small><?php if ($cp['audience'] !== ''): ?><br><small class="muted">Who sees it: <?= e(mb_strimwidth($cp['audience'], 0, 140, '…')) ?></small><?php endif; ?></td>
    <td><span class="tag <?= $cp['status'] === 'live' ? 'lime' : '' ?>"><?= e($cp['status'] === 'review' ? 'Waiting for you' : AD_CAMPAIGN_STATUSES[$cp['status']]) ?></span></td><td class="r num"><?= e(ads_money($t['spend'])) ?></td><td class="r num"><?= number_format((int)$t['clicks']) ?></td><td class="r num"><?= e(rtrim(rtrim(number_format((float)$t['conversions'], 1), '0'), '.')) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>What we’ve done</h2></div>
    <?php if (!$log): ?><p class="empty">Our work shows here as we do it.</p><?php else: ?><ul class="feed"><?php foreach ($log as $l): ?><li><span class="kind k-stage"></span><span class="grow"><small><?= e(AD_LOG_KINDS[$l['kind']] ?? 'Note') ?> · <?= e(fmt_date($l['created_at'])) ?></small><span><?= e($l['title']) ?></span><?php if ($l['details'] !== ''): ?><span class="muted small"><?= e($l['details']) ?></span><?php endif; ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
  <section class="card"><div class="card-h"><h2>Monthly reports</h2><span class="muted small">Emailed early each month</span></div>
    <div class="row" style="flex-wrap:wrap;gap:8px"><?php foreach (ads_report_months($a) as $m): ?><a class="btn ghost sm" href="<?= e(ads_report_url($a, $m)) ?>" target="_blank" rel="noopener"><?= e(date('F Y', strtotime($m . '-01'))) ?><?= $m === date('Y-m') ? ' (so far)' : '' ?></a><?php endforeach; ?></div>
  </section>
</div>
<section class="card" id="messages"><div class="card-h"><h2>Messages</h2><span class="muted small">We reply within one business day</span></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="message"><label class="f"><span class="sr-only">Message</span><textarea name="body" rows="3" required placeholder="Questions, a new offer to promote, a busy week coming up…"></textarea></label><div class="row-end" style="margin-top:8px"><button class="btn primary sm" type="submit">Send</button></div></form>
  <?php if ($msgs): ?><ul class="ad-msgs"><?php foreach ($msgs as $m): ?><li class="<?= e($m['author']) ?>"><small class="muted"><?= e($m['author'] === 'client' ? ($m['name'] ?: 'You') : ($m['name'] ?: ads_brand($a))) ?> · <?= e(ago($m['created_at'])) ?></small><p><?= nl2br(e($m['body'])) ?></p></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php portal_bottom(); ?>
