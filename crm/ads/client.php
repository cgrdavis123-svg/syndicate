<?php
// One ads client: results, campaigns, setup checklist, ad accounts, budget and goals, report notes, work log and messages.
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$a = ads_client((int)get('id'));
if (!$a) { flash('That ads client wasn’t found.'); redirect('./'); }
$id = (int)$a['id']; $back = 'client.php?id=' . $id;
if (is_post()) {
    $act = post('action');
    if ($act === 'settings') {
        $plats = array_values(array_intersect((array)($_POST['platforms'] ?? []), array_keys(AD_PLATFORM_NAMES)));
        q('UPDATE ad_clients SET status = ?, sku = ?, platforms = ?, monthly_budget = ?, target_cpl = ?, goal = ?, area = ?, site_id = ?, notes = ?, updated_at = ? WHERE id = ?', [
            isset(AD_STATUSES[post('status')]) ? post('status') : $a['status'], isset(AD_PLANS[post('sku')]) ? post('sku') : $a['sku'], implode(',', $plats),
            max(0, (float)post('monthly_budget')), max(0, (float)post('target_cpl')), mb_substr(post('goal'), 0, 300), mb_substr(post('area'), 0, 200),
            (int)post('site_id') ?: null, mb_substr(post('notes'), 0, 4000), now(), $id]);
        foreach ($plats as $p) if (!one('SELECT 1 FROM ad_accounts WHERE client_id = ? AND platform = ?', [$id, $p])) q("INSERT INTO ad_accounts (client_id, platform, created_at) VALUES (?, ?, ?)", [$id, $p, now()]);
        if ((float)post('monthly_budget') !== (float)$a['monthly_budget']) ads_log($id, 'budget', 'Monthly ad budget set to ' . ads_money(post('monthly_budget')));
        flash('Saved.'); redirect($back);
    }
    if ($act === 'step' && isset(AD_STEPS[post('step')])) {
        $done = json_decode($a['onboarding'] ?: '{}', true) ?: [];
        if (post('on') === '1') $done[post('step')] = now(); else unset($done[post('step')]);
        q('UPDATE ad_clients SET onboarding = ?, updated_at = ? WHERE id = ?', [json_encode($done), now(), $id]);
        if (post('on') === '1') ads_log($id, 'note', 'Setup: ' . strtolower(AD_STEPS[post('step')][0]) . ' done');
        redirect($back . '#setup');
    }
    if ($act === 'account' && ($acc = one('SELECT * FROM ad_accounts WHERE id = ? AND client_id = ?', [(int)post('account'), $id]))) {
        if (post('delete') === '1') { q('DELETE FROM ad_accounts WHERE id = ?', [$acc['id']]); redirect($back . '#setup'); }
        $access = in_array(post('access'), ['requested', 'create', 'granted'], true) ? post('access') : $acc['access'];
        q('UPDATE ad_accounts SET name = ?, external_id = ?, access = ?, pixel = ?, conversions = ?, notes = ? WHERE id = ?', [mb_substr(post('name'), 0, 120), mb_substr(post('external_id'), 0, 60), $access, post('pixel') === '1' ? 1 : 0, post('conversions') === '1' ? 1 : 0, mb_substr(post('notes'), 0, 500), $acc['id']]);
        if ($access === 'granted' && $acc['access'] !== 'granted') ads_log($id, 'note', ads_platform($acc['platform']) . ' ad account access confirmed');
        if (post('pixel') === '1' && post('conversions') === '1' && !($acc['pixel'] && $acc['conversions'])) ads_log($id, 'tracking', ads_platform($acc['platform']) . ' tracking installed and conversions verified');
        flash(ads_platform($acc['platform']) . ' account saved.'); redirect($back . '#setup');
    }
    if ($act === 'log' && post('title') !== '') { ads_log($id, post('kind'), post('title'), post('details'), post('shared', '1') === '1', (int)post('campaign') ?: null); flash('Logged.'); redirect($back . '#log'); }
    if ($act === 'delete_log') { q('DELETE FROM ad_log WHERE id = ? AND client_id = ?', [(int)post('log'), $id]); redirect($back . '#log'); }
    if ($act === 'note' && preg_match('/^\d{4}-\d{2}$/', post('month'))) {
        $old = ads_month_note($id, post('month'));
        q('INSERT OR REPLACE INTO ad_months (client_id, month, summary, next_steps, sent_at) VALUES (?,?,?,?,?)', [$id, post('month'), mb_substr(post('summary'), 0, 6000), mb_substr(post('next_steps'), 0, 4000), $old['sent_at']]);
        if (post('send') === '1') {
            $n = 0; foreach (ads_people($a) as $c) if (send_mail($c['email'], $a['company'] . ' · ' . date('F Y', strtotime(post('month') . '-01')) . ' ads report', 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\n" . ads_report_text($a, post('month')))) $n++;
            q('UPDATE ad_months SET sent_at = ? WHERE client_id = ? AND month = ?', [now(), $id, post('month')]);
            ads_log($id, 'report', date('F', strtotime(post('month') . '-01')) . ' report sent');
            flash($n ? "Report emailed to $n contact" . ($n === 1 ? '' : 's') . '.' : 'Notes saved, but there’s no contact with portal access to email.');
        } else flash('Report notes saved.');
        redirect($back . '#report');
    }
    if ($act === 'message' && ($body = mb_substr(trim(post('body')), 0, 5000)) !== '') {
        q("INSERT INTO ad_messages (client_id, author, name, body, created_at) VALUES (?, 'staff', ?, ?, ?)", [$id, $user['name'], $body, now()]);
        foreach (ads_people($a) as $c) send_mail($c['email'], 'New message about your ads', 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\n" . $body . "\n\nReply in your ads portal: " . ads_url('portal.php#messages') . "\n\n" . $user['name'] . "\n" . ads_brand($a));
        flash('Message sent.'); redirect($back . '#messages');
    }
}
q("UPDATE ad_messages SET seen_at = ? WHERE client_id = ? AND author = 'client' AND seen_at IS NULL", [now(), $id]);
$ranges = ['month' => ['This month', date('Y-m-01'), date('Y-m-d')], 'last' => ['Last month', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    '30' => ['Last 30 days', date('Y-m-d', strtotime('-29 days')), date('Y-m-d')], '90' => ['Last 90 days', date('Y-m-d', strtotime('-89 days')), date('Y-m-d')]];
$rk = isset($ranges[get('r')]) ? get('r') : 'month'; [$rl, $from, $to] = $ranges[$rk];
$S = ads_summary($a, $from, $to); $pace = ads_pacing($a); $daily = ads_daily($id, $from, $to);
$dl = array_map(function ($d) { return date('M j', strtotime($d['day'])); }, $daily);
$camps = all('SELECT * FROM ad_campaigns WHERE client_id = ? ORDER BY status = \'live\' DESC, status = \'review\' DESC, updated_at DESC', [$id]);
$accounts = all('SELECT * FROM ad_accounts WHERE client_id = ? ORDER BY platform', [$id]);
$steps = ads_onboarding($a); $manual = json_decode($a['onboarding'] ?: '{}', true) ?: [];
$log = all('SELECT l.*, c.name camp FROM ad_log l LEFT JOIN ad_campaigns c ON c.id = l.campaign_id WHERE l.client_id = ? ORDER BY l.created_at DESC, l.id DESC LIMIT 40', [$id]);
$msgs = all('SELECT * FROM ad_messages WHERE client_id = ? ORDER BY id DESC LIMIT 30', [$id]);
$sites = all('SELECT id, name FROM sites WHERE company_id = ? OR id = ? ORDER BY name', [(int)$a['company_id'], (int)$a['site_id']]);
$noteMonth = preg_match('/^\d{4}-\d{2}$/', get('nm')) ? get('nm') : ((int)date('j') <= 10 ? date('Y-m', strtotime('first day of last month')) : date('Y-m'));
$note = ads_month_note($id, $noteMonth);
$crs = ['review' => 0, 'changes' => 0, 'approved' => 0]; foreach (all('SELECT status, COUNT(*) n FROM ad_creatives WHERE client_id = ? GROUP BY status', [$id]) as $r) $crs[$r['status']] = (int)$r['n'];
layout_top($a['company'] . ' ads', 'dashboard', $user);
?>
<div class="page-head">
  <div class="grow-head"><a class="muted small" href="./">← All clients</a><h1><?= e($a['company']) ?></h1>
    <p class="row muted small"><span class="tag <?= $a['status'] === 'active' ? 'lime' : ($a['status'] === 'ended' ? 'red' : '') ?>"><?= e(AD_STATUSES[$a['status']]) ?></span>
      <?= isset(AD_PLANS[$a['sku']]) ? AD_PLANS[$a['sku']] . '-platform plan' : 'No plan set' ?><?= ads_platforms($a) ? ' · ' . e(implode(', ', array_map('ads_platform', ads_platforms($a)))) : '' ?>
      · <a href="<?= e(abs_url('company.php?id=' . (int)$a['company_id'])) ?>">In the CRM ↗</a></p></div>
  <div class="row"><a class="btn ghost sm" href="import.php?client=<?= $id ?>">Import results</a><a class="btn ghost sm" href="creatives.php?client=<?= $id ?>">Ads (<?= array_sum($crs) ?>)</a><a class="btn primary sm" href="campaign.php?client=<?= $id ?>">+ Campaign</a></div>
</div>
<?php $al = ads_alerts($a); if ($al): ?><ul class="ad-alerts card"><?php foreach ($al as [$k, $t, $l]): ?><li class="<?= e($k) ?>"><a href="<?= e($l) ?>"><?= e($t) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<nav class="seg-links" aria-label="Date range"><?php foreach ($ranges as $k => [$l]): ?><a href="client.php?id=<?= $id ?>&amp;r=<?= $k ?>"<?= $k === $rk ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></nav>
<div class="kpis">
  <div class="card kpi"><span>Ad spend</span><b class="num"><?= e(ads_money($S['spend'])) ?></b><?php if ($rk === 'month' && $pace): ?><?= ads_pace_bar($pace) ?><small><?= e(ads_money($pace['budget'])) ?> budget · on track for <?= e(ads_money($pace['projected'] ?? 0)) ?></small><?php else: ?><small><?= e($rl) ?></small><?php endif; ?></div>
  <div class="card kpi"><span>Leads</span><b class="num"><?= e(rtrim(rtrim(number_format($S['leads'], 1), '0'), '.')) ?></b><small><?= $S['leads_src'] === 'site' ? 'From Site Stats (paid visits that called, emailed or filled a form)' : 'As reported by the ad platforms' ?></small></div>
  <div class="card kpi"><span>Cost per lead</span><b class="num<?= $S['cpl'] && (float)$a['target_cpl'] > 0 && $S['cpl'] > 1.3 * (float)$a['target_cpl'] ? ' warn' : '' ?>"><?= $S['cpl'] !== null ? e(ads_money($S['cpl'], 2)) : '—' ?></b><small><?= (float)$a['target_cpl'] > 0 ? 'Target ' . e(ads_money($a['target_cpl'], 2)) : 'No target set' ?></small></div>
  <div class="card kpi"><span>Clicks</span><b class="num"><?= number_format((int)$S['clicks']) ?></b><small>CTR <?= e(ads_pct($S['ctr'], 2)) ?> · <?= $S['cpc'] !== null ? e(ads_money($S['cpc'], 2)) . ' per click' : 'no clicks' ?></small></div>
</div>
<div class="grid-2">
  <section class="card"><div class="card-h"><h2>Spend by day</h2><span class="muted small"><?= e($rl) ?></span></div><?= ads_bars(array_column($daily, 'spend'), $dl, '$', 'Ad spend by day') ?></section>
  <section class="card"><div class="card-h"><h2>Conversions by day</h2><span class="muted small">Reported by the platforms</span></div><?= ads_bars(array_column($daily, 'conv'), $dl, '', 'Conversions by day') ?></section>
</div>
<section class="card"><div class="card-h"><h2>Campaigns</h2><a class="btn ghost xs" href="campaign.php?client=<?= $id ?>">+ New</a></div>
  <?php if (!$camps): ?><p class="empty">No campaigns yet. Add one, or import results and campaigns are created from the export.</p><?php else: ?>
  <div class="table-wrap"><table class="tbl"><thead><tr><th>Campaign</th><th>Status</th><th class="r">Spend</th><th class="r">Clicks</th><th class="r">Conv.</th><th class="r">Site leads</th><th class="r">Cost/conv.</th></tr></thead><tbody>
  <?php foreach ($camps as $c): $t = ads_totals($id, $from, $to, (int)$c['id']); $sl = $c['utm_campaign'] !== '' ? ($S['site']['by_campaign'][strtolower($c['utm_campaign'])]['conv'] ?? 0) : null; ?>
    <tr data-href="campaign.php?id=<?= (int)$c['id'] ?>"><td><a class="cell-main" href="campaign.php?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a><br><small class="muted"><?= e(ads_platform($c['platform'])) ?> · <?= e(AD_OBJECTIVES[$c['objective']] ?? $c['objective']) ?><?= (float)$c['daily_budget'] > 0 ? ' · ' . e(ads_money($c['daily_budget'])) . '/day' : '' ?></small></td>
      <td><span class="tag <?= $c['status'] === 'live' ? 'lime' : ($c['status'] === 'review' ? 'blue' : '') ?>"><?= e(AD_CAMPAIGN_STATUSES[$c['status']]) ?></span></td>
      <td class="r num"><?= e(ads_money($t['spend'])) ?></td><td class="r num"><?= number_format((int)$t['clicks']) ?></td><td class="r num"><?= e(rtrim(rtrim(number_format((float)$t['conversions'], 1), '0'), '.')) ?></td>
      <td class="r num"><?= $sl === null ? '<span class="muted" title="Set a UTM campaign on the campaign to match Site Stats">—</span>' : (int)$sl ?></td>
      <td class="r num"><?= $t['conversions'] > 0 ? e(ads_money($t['spend'] / $t['conversions'], 2)) : '—' ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<div class="grid-2">
  <section class="card" id="setup"><div class="card-h"><h2>Setup checklist</h2><span class="muted small"><?= count(array_filter($steps, function ($s) { return $s['done']; })) ?> of <?= count($steps) ?> · the client sees this</span></div>
    <ul class="ad-steps"><?php foreach ($steps as $k => $s): ?><li class="<?= $s['done'] ? 'done' : '' ?>">
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="step"><input type="hidden" name="step" value="<?= $k ?>"><input type="hidden" name="on" value="<?= empty($manual[$k]) ? '1' : '0' ?>">
        <button type="submit" class="ad-check" aria-label="<?= $s['done'] ? 'Done' : 'Mark done' ?>: <?= e($s['title']) ?>"<?= $s['done'] && empty($manual[$k]) ? ' disabled title="Ticked automatically"' : '' ?>></button></form>
      <span><b><?= e($s['title']) ?></b><small class="muted"><?= e($s['text']) ?></small></span></li><?php endforeach; ?></ul>
  </section>
  <section class="card"><div class="card-h"><h2>Ad accounts</h2><a class="muted small" href="settings.php">Our access details</a></div>
    <?php if (!$accounts): ?><p class="empty">Pick the platforms under Budget &amp; goals to add accounts.</p><?php endif; ?>
    <?php foreach ($accounts as $acc): ?><form method="post" class="ad-acc"><?= csrf_field() ?><input type="hidden" name="action" value="account"><input type="hidden" name="account" value="<?= (int)$acc['id'] ?>">
      <div class="row between"><b><?= e(ads_platform($acc['platform'])) ?></b><select name="access" aria-label="Access"><option value="requested"<?= selected('requested', $acc['access']) ?>>Access requested</option><option value="create"<?= selected('create', $acc['access']) ?>>We’re creating it</option><option value="granted"<?= selected('granted', $acc['access']) ?>>Access granted</option></select></div>
      <div class="row"><input name="name" value="<?= e($acc['name']) ?>" placeholder="Account name" aria-label="Account name" class="grow"><input name="external_id" value="<?= e($acc['external_id']) ?>" placeholder="Account ID" aria-label="Account ID" style="width:150px"></div>
      <div class="row"><label class="chk"><input type="hidden" name="pixel" value="0"><input type="checkbox" name="pixel" value="1"<?= $acc['pixel'] ? ' checked' : '' ?>> <span>Pixel / tag on site</span></label><label class="chk"><input type="hidden" name="conversions" value="0"><input type="checkbox" name="conversions" value="1"<?= $acc['conversions'] ? ' checked' : '' ?>> <span>Conversions verified</span></label>
        <span class="grow"></span><button class="link-danger" name="delete" value="1" type="submit" data-confirm="Remove this account?">Remove</button><button class="btn ghost xs" type="submit">Save</button></div>
    </form><?php endforeach; ?>
  </section>
</div>
<div class="grid-2">
  <form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <div class="card-h span2"><h2>Budget &amp; goals</h2></div>
    <label class="f"><span>Status</span><select name="status"><?php foreach (AD_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $a['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Plan</span><select name="sku"><option value="ads-one"<?= selected('ads-one', $a['sku']) ?>>One platform</option><option value="ads-two"<?= selected('ads-two', $a['sku']) ?>>Two platforms</option><option value="ads-all"<?= selected('ads-all', $a['sku']) ?>>Google, Meta &amp; TikTok</option></select></label>
    <fieldset class="f span2"><span>Platforms</span><div class="row"><?php foreach (AD_PLATFORM_NAMES as $k => $n): ?><label class="chk"><input type="checkbox" name="platforms[]" value="<?= $k ?>"<?= in_array($k, ads_platforms($a), true) ? ' checked' : '' ?>> <span><?= e($n) ?></span></label><?php endforeach; ?></div></fieldset>
    <label class="f"><span>Monthly ad budget <small class="muted">(paid to platforms)</small></span><input name="monthly_budget" inputmode="decimal" value="<?= (float)$a['monthly_budget'] ?: '' ?>"></label>
    <label class="f"><span>Target cost per lead</span><input name="target_cpl" inputmode="decimal" value="<?= (float)$a['target_cpl'] ?: '' ?>"></label>
    <label class="f span2"><span>Main goal</span><input name="goal" value="<?= e($a['goal']) ?>" placeholder="e.g. Booked estimates for AC replacement"></label>
    <label class="f"><span>Area to target</span><input name="area" value="<?= e($a['area']) ?>"></label>
    <label class="f"><span>Website for leads (Site Stats)</span><select name="site_id"><option value="">None</option><?php foreach ($sites as $st): ?><option value="<?= (int)$st['id'] ?>"<?= selected($st['id'], $a['site_id']) ?>><?= e($st['name']) ?></option><?php endforeach; ?></select></label>
    <label class="f span2"><span>Private notes</span><textarea name="notes" rows="3"><?= e($a['notes']) ?></textarea></label>
    <button class="btn primary span2" type="submit">Save</button>
  </form>
  <form method="post" class="card form-grid" id="report"><?= csrf_field() ?><input type="hidden" name="action" value="note"><input type="hidden" name="month" value="<?= e($noteMonth) ?>">
    <div class="card-h span2"><h2>Monthly report</h2><span class="muted small"><?= $note['sent_at'] ? 'Sent ' . e(fmt_date($note['sent_at'], 'M j')) : 'Goes out on the 1st–5th' ?></span></div>
    <div class="span2 row" style="gap:6px"><?php foreach (array_slice(ads_report_months($a), 0, 6) as $m): ?><a class="btn <?= $m === $noteMonth ? 'primary' : 'ghost' ?> xs" href="client.php?id=<?= $id ?>&amp;nm=<?= $m ?>#report"><?= e(date('M Y', strtotime($m . '-01'))) ?></a><?php endforeach; ?></div>
    <label class="f span2"><span>What happened in <?= e(date('F', strtotime($noteMonth . '-01'))) ?> <small class="muted">(in plain words, shown at the top of the report)</small></span><textarea name="summary" rows="4" placeholder="e.g. Leads were up after we moved budget to the ‘AC repair’ search campaign…"><?= e($note['summary']) ?></textarea></label>
    <label class="f span2"><span>Next month</span><textarea name="next_steps" rows="3" placeholder="What we’ll test or change next"><?= e($note['next_steps']) ?></textarea></label>
    <div class="span2 row between"><a class="muted small" href="<?= e(ads_report_url($a, $noteMonth)) ?>" target="_blank" rel="noopener">Preview the report ↗</a>
      <span class="row"><button class="btn ghost sm" type="submit">Save notes</button><button class="btn primary sm" type="submit" name="send" value="1" data-confirm="Email the <?= e(date('F', strtotime($noteMonth . '-01'))) ?> report to the client now?">Save &amp; email report</button></span></div>
  </form>
</div>
<div class="grid-2">
  <section class="card" id="log"><div class="card-h"><h2>Work log</h2><span class="muted small">Shared entries show in the client’s portal and report</span></div>
    <form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="log">
      <label class="f"><span>Type</span><select name="kind"><?php foreach (AD_LOG_KINDS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label class="f"><span>Campaign <small class="muted">(optional)</small></span><select name="campaign"><option value="">—</option><?php foreach ($camps as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
      <label class="f span2"><span>What you did</span><input name="title" required placeholder="e.g. Paused 3 keywords with no calls, moved $10/day to ‘Emergency repair’"></label>
      <label class="f span2"><span>Details <small class="muted">(optional)</small></span><textarea name="details" rows="2"></textarea></label>
      <div class="span2 row between"><label class="chk"><input type="hidden" name="shared" value="0"><input type="checkbox" name="shared" value="1" checked> <span>Show to the client</span></label>
      <button class="btn primary sm" type="submit">Add</button></div>
    </form>
    <?php if ($log): ?><ul class="feed" style="margin-top:14px"><?php foreach ($log as $l): ?><li><span class="kind k-note"></span><span class="grow"><small><?= e(AD_LOG_KINDS[$l['kind']] ?? 'Note') ?> · <?= e(fmt_date($l['created_at'])) ?><?= $l['camp'] ? ' · ' . e($l['camp']) : '' ?><?= $l['shared'] ? '' : ' · <span class="warn">private</span>' ?></small><span><?= e($l['title']) ?></span><?php if ($l['details'] !== ''): ?><span class="muted small"><?= e($l['details']) ?></span><?php endif; ?></span>
      <form method="post" class="inline" data-confirm="Remove this entry?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_log"><input type="hidden" name="log" value="<?= (int)$l['id'] ?>"><button class="x" type="submit" aria-label="Remove">×</button></form></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
  <section class="card" id="messages"><div class="card-h"><h2>Messages</h2><span class="muted small">Emailed to the client</span></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="message"><label class="f"><span class="sr-only">Message</span><textarea name="body" rows="3" required placeholder="Write to <?= e($a['company']) ?>…"></textarea></label><div class="row-end" style="margin-top:8px"><button class="btn primary sm" type="submit">Send</button></div></form>
    <?php if ($msgs): ?><ul class="ad-msgs"><?php foreach ($msgs as $m): ?><li class="<?= e($m['author']) ?>"><small class="muted"><?= e($m['name'] ?: ($m['author'] === 'client' ? 'Client' : 'Us')) ?> · <?= e(ago($m['created_at'])) ?></small><p><?= nl2br(e($m['body'])) ?></p></li><?php endforeach; ?></ul><?php else: ?><p class="empty" style="margin-top:10px">No messages yet.</p><?php endif; ?>
  </section>
</div>
<?php layout_bottom(); ?>
