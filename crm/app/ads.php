<?php
// Ads management (ads.stusyn.com): clients on an ads plan, their ad accounts, campaigns, daily results,
// creatives for approval, the work log, messages and the monthly ads report. Uses the CRM's database.
// Results come in from CSV exports (Google Ads, Meta, TikTok) or are typed in; leads come from Site Stats.
require_once __DIR__ . '/analytics.php';

const AD_STATUSES = ['onboarding' => 'Getting set up', 'active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'];
const AD_CAMPAIGN_STATUSES = ['draft' => 'Draft', 'review' => 'Waiting for approval', 'live' => 'Live', 'paused' => 'Paused', 'ended' => 'Ended'];
const AD_OBJECTIVES = ['leads' => 'Leads (calls and forms)', 'sales' => 'Sales', 'traffic' => 'Website visits', 'awareness' => 'Awareness', 'retargeting' => 'Retargeting'];
const AD_CREATIVE_STATUSES = ['draft' => 'Draft', 'review' => 'Waiting for you', 'approved' => 'Approved', 'changes' => 'Changes asked for', 'live' => 'Live', 'retired' => 'Retired'];
const AD_FORMATS = ['image' => 'Image', 'video' => 'Video', 'carousel' => 'Carousel', 'text' => 'Search ad (text)'];
const AD_LOG_KINDS = ['launch' => 'Launch', 'budget' => 'Budget', 'creative' => 'Creative', 'targeting' => 'Targeting', 'optimize' => 'Optimization', 'tracking' => 'Tracking', 'report' => 'Report', 'note' => 'Note'];
const AD_PAID_CHANNELS = ['Paid search', 'Paid social', 'Display', 'Paid other'];
const AD_PLANS = ['ads-one' => 1, 'ads-two' => 2, 'ads-all' => 3];
const AD_PLATFORM_NAMES = ['google' => 'Google', 'meta' => 'Meta', 'tiktok' => 'TikTok'];
// The setup checklist a client sees. Some steps tick themselves (see ads_onboarding()).
const AD_STEPS = [
    'kickoff' => ['Kickoff call', 'We talk through your goals, customers, area and budget.'],
    'access' => ['Ad account access', 'You add us to your ad accounts (or we set new ones up for you).'],
    'tracking' => ['Tracking installed', 'Pixels and conversion tracking are on your site, so we can count calls, forms and sales.'],
    'budget' => ['Ad budget agreed', 'The monthly amount you’ll spend with the platforms, paid to them directly.'],
    'plan' => ['Campaign plan approved', 'You approve the campaigns, audiences and first ads before anything goes live.'],
    'launch' => ['First ads live', 'Your first campaigns start running.'],
];

function ads_platform(string $k): string { return AD_PLATFORM_NAMES[$k] ?? ucfirst($k); }
function ads_staff_email(): string { return setting('shop_notify') ?: setting('business_email'); }
function ads_storage(): string { $d = STORAGE . '/ads'; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }
function ads_client(int $id): ?array { return one('SELECT a.*, co.name company, co.brand FROM ad_clients a JOIN companies co ON co.id = a.company_id WHERE a.id = ?', [$id]); }
function ads_platforms(array $a): array { return array_values(array_filter(explode(',', (string)$a['platforms']))); }
function ads_people(array $a): array { return all("SELECT * FROM contacts WHERE company_id = ? AND portal_access = 1 AND email != ''", [(int)$a['company_id']]); }
function ads_task(array $a, string $title, int $prio = 1): void {
    if (one('SELECT 1 FROM tasks WHERE done_at IS NULL AND title = ?', [$title])) return;
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [$title, today(), $prio, 'company', (int)$a['company_id'], now()]);
}
function ads_log(int $clientId, string $kind, string $title, string $details = '', bool $shared = true, ?int $campaignId = null): void {
    q('INSERT INTO ad_log (client_id, campaign_id, kind, title, details, shared, created_at) VALUES (?,?,?,?,?,?,?)', [$clientId, $campaignId, isset(AD_LOG_KINDS[$kind]) ? $kind : 'note', mb_substr($title, 0, 200), mb_substr($details, 0, 4000), $shared ? 1 : 0, now()]);
}
function ads_money($v, int $dec = 0): string { return '$' . number_format((float)$v, $dec); }
function ads_pct(?float $v, int $dec = 1): string { return $v === null ? '—' : number_format($v, $dec) . '%'; }

/* ---------- Turning a plan on and off ---------- */
// Called when an ads plan is paid for (store order) or won (quote, $orderId negative).
function ads_enable_for(int $companyId, int $orderId, string $sku, array $brief = [], ?int $siteId = null): int {
    $platforms = array_values(array_intersect((array)($brief['platforms'] ?? []), array_keys(AD_PLATFORM_NAMES)));
    if (($AD = AD_PLANS[$sku] ?? 0) === 3) $platforms = array_keys(AD_PLATFORM_NAMES);
    $budget = 0.0;
    if (preg_match_all('/[\d,]+/', (string)($brief['ad_budget'] ?? ''), $m) && $m[0]) { $nums = array_map(function ($x) { return (float)str_replace(',', '', $x); }, $m[0]); $budget = max($nums); }
    $a = one('SELECT * FROM ad_clients WHERE company_id = ?', [$companyId]);
    if ($a) {
        q("UPDATE ad_clients SET sku = ?, order_id = ?, platforms = CASE WHEN ? != '' THEN ? ELSE platforms END, status = CASE WHEN status = 'ended' THEN 'onboarding' ELSE status END, ended_at = NULL, site_id = COALESCE(site_id, ?), updated_at = ? WHERE id = ?",
            [$sku, $orderId, implode(',', $platforms), implode(',', $platforms), $siteId, now(), $a['id']]);
        $id = (int)$a['id'];
    } else {
        q("INSERT INTO ad_clients (company_id, status, sku, platforms, order_id, site_id, monthly_budget, area, since, onboarding, report_key, created_at, updated_at) VALUES (?, 'onboarding', ?, ?, ?, ?, ?, ?, ?, '{}', ?, ?, ?)",
            [$companyId, $sku, implode(',', $platforms), $orderId, $siteId, $budget, mb_substr((string)($brief['ad_area'] ?? ''), 0, 200), now(), bin2hex(random_bytes(16)), now(), now()]);
        $id = (int)db()->lastInsertId();
    }
    foreach ($platforms as $p) if (!one('SELECT 1 FROM ad_accounts WHERE client_id = ? AND platform = ?', [$id, $p]))
        q("INSERT INTO ad_accounts (client_id, platform, name, access, created_at) VALUES (?, ?, ?, ?, ?)", [$id, $p, '', stripos((string)($brief['ad_accounts'] ?? ''), 'set them up') !== false || ($brief['ad_accounts'] ?? '') === 'new' ? 'create' : 'requested', now()]);
    $a = ads_client($id);
    ads_log($id, 'note', 'Ads management started', 'Plan: ' . (AD_PLANS[$sku] ?? 1) . ' platform' . ((AD_PLANS[$sku] ?? 1) === 1 ? '' : 's') . ($platforms ? ' (' . implode(', ', array_map('ads_platform', $platforms)) . ')' : '') . '.');
    ads_task($a, 'Ads onboarding for ' . $a['company'] . ': get ad account access' . ($platforms ? ' (' . implode(', ', array_map('ads_platform', $platforms)) . ')' : ''), 1);
    ads_task($a, 'Ads onboarding for ' . $a['company'] . ': install pixels and conversion tracking', 1);
    ads_task($a, 'Ads onboarding for ' . $a['company'] . ': write the campaign plan and first ads for approval', 0);
    return $id;
}
function ads_end_order(int $orderId): void {
    foreach (all("SELECT * FROM ad_clients WHERE order_id = ? AND status != 'ended'", [$orderId]) as $a) {
        q("UPDATE ad_clients SET status = 'ended', ended_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $a['id']]);
        ads_log((int)$a['id'], 'note', 'Ads management ended', '', false);
        ads_task(ads_client((int)$a['id']), 'Ads ended for ' . val('SELECT name FROM companies WHERE id = ?', [$a['company_id']]) . ': pause live campaigns and remove our access', 1);
    }
}

/* ---------- Setup checklist ---------- */
function ads_onboarding(array $a): array {
    $done = json_decode($a['onboarding'] ?: '{}', true) ?: [];
    $acc = all('SELECT * FROM ad_accounts WHERE client_id = ?', [$a['id']]);
    if ($acc && !array_filter($acc, function ($x) { return $x['access'] !== 'granted'; })) $done['access'] = $done['access'] ?? 'auto';
    if ($acc && !array_filter($acc, function ($x) { return !$x['pixel'] || !$x['conversions']; })) $done['tracking'] = $done['tracking'] ?? 'auto';
    if ((float)$a['monthly_budget'] > 0) $done['budget'] = $done['budget'] ?? 'auto';
    if (one("SELECT 1 FROM ad_campaigns WHERE client_id = ? AND status IN ('live','paused','ended')", [$a['id']])) { $done['plan'] = $done['plan'] ?? 'auto'; $done['launch'] = $done['launch'] ?? 'auto'; }
    $out = [];
    foreach (AD_STEPS as $k => [$t, $d]) $out[$k] = ['title' => $t, 'text' => $d, 'done' => !empty($done[$k])];
    return $out;
}
function ads_onboarding_done(array $a): bool { return !array_filter(ads_onboarding($a), function ($s) { return !$s['done']; }); }

/* ---------- Results ---------- */
// Spend, clicks and platform conversions for a client (or one campaign) between two dates (Y-m-d, inclusive).
function ads_totals(int $clientId, string $from, string $to, ?int $campaignId = null, ?string $platform = null): array {
    $w = 'c.client_id = ? AND s.day >= ? AND s.day <= ?'; $p = [$clientId, $from, $to];
    if ($campaignId) { $w .= ' AND c.id = ?'; $p[] = $campaignId; }
    if ($platform) { $w .= ' AND c.platform = ?'; $p[] = $platform; }
    $r = one("SELECT COALESCE(SUM(s.impressions),0) impressions, COALESCE(SUM(s.clicks),0) clicks, COALESCE(SUM(s.spend),0) spend, COALESCE(SUM(s.conversions),0) conversions, COALESCE(SUM(s.value),0) value, MAX(s.day) last_day
        FROM ad_stats s JOIN ad_campaigns c ON c.id = s.campaign_id WHERE $w", $p);
    $r['ctr'] = $r['impressions'] > 0 ? 100 * $r['clicks'] / $r['impressions'] : null;
    $r['cpc'] = $r['clicks'] > 0 ? $r['spend'] / $r['clicks'] : null;
    return $r;
}
function ads_daily(int $clientId, string $from, string $to, ?int $campaignId = null): array {
    $rows = []; $p = [$clientId, $from, $to]; $w = '';
    if ($campaignId) { $w = ' AND c.id = ?'; $p[] = $campaignId; }
    foreach (all("SELECT s.day, SUM(s.spend) spend, SUM(s.clicks) clicks, SUM(s.conversions) conv FROM ad_stats s JOIN ad_campaigns c ON c.id = s.campaign_id WHERE c.client_id = ? AND s.day >= ? AND s.day <= ?$w GROUP BY s.day", $p) as $r) $rows[$r['day']] = $r;
    $out = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) { $d = date('Y-m-d', $t); $out[] = ['day' => $d, 'spend' => (float)($rows[$d]['spend'] ?? 0), 'clicks' => (int)($rows[$d]['clicks'] ?? 0), 'conv' => (float)($rows[$d]['conv'] ?? 0), 'has' => isset($rows[$d])]; }
    return $out;
}
// Leads from Site Stats: visits from paid channels that ended in a goal (call, form, purchase…), by channel and by campaign.
function ads_site_leads(array $a, string $from, string $to): array {
    $out = ['leads' => null, 'visits' => null, 'by_channel' => [], 'by_campaign' => []];
    if (!$a['site_id'] || !($site = one('SELECT * FROM sites WHERE id = ?', [(int)$a['site_id']]))) return $out;
    $f = strtotime($from); $t = strtotime('+1 day', strtotime($to));
    try { $R = an_report($site, ['key' => 'custom', 'bucket' => 'day', 'from' => $f, 'to' => $t, 'pfrom' => $f - ($t - $f), 'pto' => $f], []); }
    catch (Throwable $e) { error_log('Ads leads: ' . $e->getMessage()); return $out; }
    $out['leads'] = 0; $out['visits'] = 0;
    foreach ($R['channel'] ?? [] as $r) if (in_array($r['k'], AD_PAID_CHANNELS, true)) { $out['leads'] += (int)$r['conv']; $out['visits'] += (int)$r['visits']; $out['by_channel'][$r['k']] = $r; }
    foreach ($R['campaign'] ?? [] as $r) $out['by_campaign'][strtolower((string)$r['k'])] = $r;
    return $out;
}
// The headline numbers: spend, leads (Site Stats if it's on the site, else what the platforms report) and cost per lead.
function ads_summary(array $a, string $from, string $to): array {
    $T = ads_totals((int)$a['id'], $from, $to);
    $L = ads_site_leads($a, $from, $to);
    $leads = $L['leads'] !== null && ($L['leads'] > 0 || $T['conversions'] == 0) ? (float)$L['leads'] : (float)$T['conversions'];
    $src = $L['leads'] !== null && ($L['leads'] > 0 || $T['conversions'] == 0) ? 'site' : 'platforms';
    return $T + ['leads' => $leads, 'leads_src' => $src, 'site' => $L, 'cpl' => $leads > 0 ? $T['spend'] / $leads : null];
}
// How the month's spend compares with the budget so far. 100 = right on pace.
function ads_pacing(array $a, ?float $spend = null): ?array {
    $budget = (float)$a['monthly_budget'];
    if ($budget <= 0) return null;
    $from = date('Y-m-01'); $to = date('Y-m-d', strtotime('yesterday'));
    if ($to < $from) return ['budget' => $budget, 'spent' => 0.0, 'expected' => 0.0, 'pace' => null, 'projected' => null];
    $spent = $spend ?? (float)ads_totals((int)$a['id'], $from, $to)['spend'];
    $dim = (int)date('t'); $days = (int)date('j', strtotime($to));
    $expected = $budget * $days / $dim;
    return ['budget' => $budget, 'spent' => $spent, 'expected' => $expected, 'pace' => $expected > 0 ? 100 * $spent / $expected : null, 'projected' => $days ? $spent / $days * $dim : null];
}

/* ---------- Things that need attention ---------- */
// [['bad'|'warn'|'info', text, link], ...]
function ads_alerts(array $a): array {
    $out = []; $id = (int)$a['id'];
    if ($a['status'] === 'ended') return [];
    if ($a['status'] === 'onboarding') { $left = array_filter(ads_onboarding($a), function ($s) { return !$s['done']; }); if ($left) $out[] = ['info', 'Setup: ' . implode(', ', array_map(function ($s) { return strtolower($s['title']); }, $left)) . ' still to do', 'client.php?id=' . $id . '#setup']; }
    $pace = ads_pacing($a);
    if ($pace && $pace['pace'] !== null && (int)date('j') > 3) {
        if ($pace['pace'] > 115) $out[] = ['bad', 'Spending ahead of budget (' . round($pace['pace']) . '% of pace; on track for ' . ads_money($pace['projected']) . ' of ' . ads_money($pace['budget']) . ')', 'client.php?id=' . $id];
        elseif ($pace['pace'] < 75 && $a['status'] === 'active') $out[] = ['warn', 'Spending behind budget (' . round($pace['pace']) . '% of pace)', 'client.php?id=' . $id];
    }
    foreach (all("SELECT * FROM ad_campaigns WHERE client_id = ? AND status = 'live'", [$id]) as $c) {
        $last = val('SELECT MAX(day) FROM ad_stats WHERE campaign_id = ?', [$c['id']]);
        if (!$last || $last < date('Y-m-d', strtotime('-4 days'))) $out[] = ['warn', '“' . $c['name'] . '” has no results since ' . ($last ? fmt_date($last, 'M j') : 'it went live') . '. Import the latest numbers.', 'import.php?client=' . $id];
        if ($c['end_date'] && $c['end_date'] < today()) $out[] = ['warn', '“' . $c['name'] . '” passed its end date but is still marked live', 'campaign.php?id=' . $c['id']];
    }
    $wait = (int)val("SELECT COUNT(*) FROM ad_creatives WHERE client_id = ? AND status = 'review' AND updated_at < ?", [$id, date('Y-m-d H:i:s', time() - 3 * 86400)]);
    if ($wait) $out[] = ['info', "$wait ad" . ($wait === 1 ? '' : 's') . ' waiting on the client for more than 3 days', 'creatives.php?client=' . $id];
    $chg = (int)val("SELECT COUNT(*) FROM ad_creatives WHERE client_id = ? AND status = 'changes'", [$id]);
    if ($chg) $out[] = ['warn', "Client asked for changes on $chg ad" . ($chg === 1 ? '' : 's'), 'creatives.php?client=' . $id];
    $unread = (int)val("SELECT COUNT(*) FROM ad_messages WHERE client_id = ? AND author = 'client' AND seen_at IS NULL", [$id]);
    if ($unread) $out[] = ['bad', "$unread new message" . ($unread === 1 ? '' : 's') . ' from the client', 'client.php?id=' . $id . '#messages'];
    if ((float)$a['target_cpl'] > 0 && (int)date('j') > 7) {
        $s = ads_summary($a, date('Y-m-01'), date('Y-m-d'));
        if ($s['cpl'] !== null && $s['cpl'] > 1.3 * (float)$a['target_cpl']) $out[] = ['warn', 'Cost per lead is ' . ads_money($s['cpl'], 2) . ' this month, above the ' . ads_money($a['target_cpl'], 2) . ' target', 'client.php?id=' . $id];
    }
    return $out;
}

/* ---------- CSV import (Google Ads, Meta Ads Manager, TikTok Ads Manager) ---------- */
const AD_CSV_COLS = [
    'day' => ['day', 'date', 'reporting starts', 'by day', 'stat time day', 'time'],
    'campaign' => ['campaign', 'campaign name'],
    'impressions' => ['impressions', 'impr'],
    'clicks' => ['link clicks', 'clicks (destination)', 'destination clicks', 'clicks', 'clicks (all)'],
    'spend' => ['cost', 'amount spent (usd)', 'amount spent', 'spend', 'total cost', 'cost (usd)'],
    'conversions' => ['conversions', 'results', 'leads', 'purchases', 'all conv'],
    'value' => ['conv value', 'conversion value', 'purchase conversion value', 'total conversion value', 'results value', 'all conv value'],
];
function ads_csv_key($h): string { $h = (string)$h; return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9() ]+/', ' ', strtolower(trim($h, " \t\"\xEF\xBB\xBF"))))); }
function ads_csv_num(string $v): float { $v = trim($v); if ($v === '' || $v === '--' || $v === '-') return 0.0; return (float)preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $v)); }
function ads_csv_day(string $v): ?string {
    $v = trim(preg_replace('/^[A-Za-z]{3},\s*/', '', trim($v)));
    if ($v === '' || stripos($v, 'total') !== false) return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) return "$m[1]-$m[2]-$m[3]";
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $v, $m)) { $y = strlen($m[3]) === 2 ? 2000 + (int)$m[3] : (int)$m[3]; return sprintf('%04d-%02d-%02d', $y, $m[1], $m[2]); }
    $t = strtotime($v);
    return $t ? date('Y-m-d', $t) : null;
}
// Reads an export into rows of [day, campaign, impressions, clicks, spend, conversions, value]. Returns ['rows' => [...], 'error' => '', 'cols' => mapped columns].
function ads_parse_csv(string $raw): array {
    if (strncmp($raw, "\xFF\xFE", 2) === 0) $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');      // Google Ads sometimes exports UTF-16
    elseif (strncmp($raw, "\xFE\xFF", 2) === 0) $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $lines = preg_split('/\r\n|\n|\r/', $raw);
    $hdrAt = -1; $map = []; $sep = ',';
    foreach (array_slice($lines, 0, 15) as $i => $line) {
        if (trim($line) === '') continue;
        $sep = substr_count($line, "\t") > substr_count($line, ',') ? "\t" : ',';
        $cells = array_map('ads_csv_key', str_getcsv($line, $sep));
        $m = [];
        foreach (AD_CSV_COLS as $k => $names) foreach ($names as $n) { $j = array_search($n, $cells, true); if ($j !== false) { $m[$k] = $j; break; } }
        if (isset($m['day'], $m['campaign'], $m['spend'])) { $hdrAt = $i; $map = $m; break; }
    }
    if ($hdrAt < 0) return ['rows' => [], 'error' => 'Couldn’t find the columns. The export needs a Day (or Date), Campaign and Cost (or Amount spent) column. In the ad platform, break the report down by day.', 'cols' => []];
    $rows = [];
    foreach (array_slice($lines, $hdrAt + 1) as $line) {
        if (trim($line) === '') continue;
        $c = str_getcsv($line, $sep);
        $day = ads_csv_day((string)($c[$map['day']] ?? '')); $camp = trim((string)($c[$map['campaign']] ?? ''));
        if (!$day || $camp === '' || stripos($camp, 'total') === 0 || $camp === '--') continue;
        $g = function ($k) use ($c, $map) { return isset($map[$k]) ? ads_csv_num((string)($c[$map[$k]] ?? '')) : 0.0; };
        $rows[] = [$day, mb_substr($camp, 0, 200), (int)$g('impressions'), (int)$g('clicks'), $g('spend'), $g('conversions'), $g('value')];
    }
    if (!$rows) return ['rows' => [], 'error' => 'The file had the right columns but no daily rows. Make sure the report is broken down by day.', 'cols' => array_keys($map)];
    return ['rows' => $rows, 'error' => '', 'cols' => array_keys($map)];
}
// Saves parsed rows for one client and platform. Campaigns are matched by name and created if new. A day already imported is replaced.
function ads_import(array $a, string $platform, array $rows, string $filename, string $by): array {
    q('INSERT INTO ad_imports (client_id, platform, filename, rows, created_by, created_at) VALUES (?,?,?,?,?,?)', [$a['id'], $platform, mb_substr($filename, 0, 200), count($rows), $by, now()]);
    $imp = (int)db()->lastInsertId(); $new = 0; $camps = [];
    $up = db()->prepare('INSERT OR REPLACE INTO ad_stats (campaign_id, day, impressions, clicks, spend, conversions, value, source, import_id) VALUES (?,?,?,?,?,?,?,?,?)');
    $min = null; $max = null;
    foreach ($rows as [$day, $name, $imp_, $clk, $spend, $conv, $val]) {
        $k = strtolower($name);
        if (!isset($camps[$k])) {
            $c = one('SELECT id FROM ad_campaigns WHERE client_id = ? AND platform = ? AND lower(name) = ?', [$a['id'], $platform, $k]);
            if (!$c) { q("INSERT INTO ad_campaigns (client_id, platform, name, objective, status, created_at, updated_at) VALUES (?,?,?,?, 'live', ?, ?)", [$a['id'], $platform, $name, 'leads', now(), now()]); $camps[$k] = (int)db()->lastInsertId(); $new++; }
            else $camps[$k] = (int)$c['id'];
        }
        $up->execute([$camps[$k], $day, $imp_, $clk, round($spend, 2), $conv, $val, 'csv', $imp]);
        $min = $min === null || $day < $min ? $day : $min; $max = $max === null || $day > $max ? $day : $max;
    }
    q('UPDATE ad_imports SET from_day = ?, to_day = ? WHERE id = ?', [$min, $max, $imp]);
    if ($a['status'] === 'onboarding' && $rows) q("UPDATE ad_clients SET status = 'active', updated_at = ? WHERE id = ?", [now(), $a['id']]);
    return ['import' => $imp, 'rows' => count($rows), 'campaigns' => count($camps), 'new' => $new, 'from' => $min, 'to' => $max];
}

/* ---------- Creatives ---------- */
const AD_FILE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov'];
function ads_save_file(array $f): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', ''];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 100 * 1048576) return ['', 'That file didn’t upload. Files can be up to 100 MB.'];
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(AD_FILE_TYPES[$type])) return ['', 'Upload a JPG, PNG, WebP, GIF, MP4 or MOV file.'];
    $name = bin2hex(random_bytes(12)) . '.' . AD_FILE_TYPES[$type];
    if (!move_uploaded_file($f['tmp_name'], ads_storage() . '/' . $name)) return ['', 'The file couldn’t be saved. Try again.'];
    return [$name, ''];
}
function ads_file_kind(string $file): string { return preg_match('/\.(mp4|mov)$/', $file) ? 'video' : ($file !== '' ? 'image' : ''); }
// Emails the client that ads are ready for them to look at.
function ads_notify_review(array $a, int $count): void {
    foreach (ads_people($a) as $c) send_mail($c['email'], ($count === 1 ? 'A new ad is' : "$count new ads are") . ' ready for you to approve',
        'Hi ' . ($c['first_name'] ?: 'there') . ",\n\n" . ($count === 1 ? 'We’ve made a new ad' : "We’ve made $count new ads") . ' for ' . $a['company'] . ". Take a look and approve " . ($count === 1 ? 'it' : 'them') . ", or tell us what to change:\n\n"
        . ads_url('portal-creatives.php') . "\n\nNothing goes live until you approve it.\n\n" . ads_brand($a) . "\n" . setting('business_email'));
}
function ads_brand(array $a): string { return !empty($a['brand']) && $a['brand'] !== 'Syndicate Holdings Group' ? $a['brand'] : 'Syndicate Marketing'; }

/* ---------- Monthly report ---------- */
function ads_report_sig(array $a, string $month): string { return substr(hash_hmac('sha256', 'ads|' . $a['id'] . '|' . $month, (string)$a['report_key']), 0, 32); }
function ads_report_url(array $a, string $month): string { return ads_url('report.php?c=' . (int)$a['id'] . '&m=' . $month . '&k=' . ads_report_sig($a, $month)); }
function ads_report_months(array $a, int $max = 12): array {
    $start = strtotime(date('Y-m-01', strtotime((string)($a['since'] ?: $a['created_at']))));
    $out = [];
    for ($t = strtotime(date('Y-m-01')); $t >= $start && count($out) < $max; $t = strtotime('-1 month', $t)) $out[] = date('Y-m', $t);
    return $out;
}
function ads_month_note(int $clientId, string $month): array { return one('SELECT * FROM ad_months WHERE client_id = ? AND month = ?', [$clientId, $month]) ?: ['summary' => '', 'next_steps' => '', 'sent_at' => null]; }
function ads_report_data(array $a, string $month): array {
    $from = $month . '-01'; $to = min(date('Y-m-t', strtotime($from)), date('Y-m-d'));
    $pf = date('Y-m-01', strtotime('-1 month', strtotime($from))); $pt = date('Y-m-t', strtotime($pf));
    $S = ads_summary($a, $from, $to); $P = ads_summary($a, $pf, $pt);
    $plats = [];
    foreach (array_keys(AD_PLATFORM_NAMES) as $p) { $t = ads_totals((int)$a['id'], $from, $to, null, $p); if ($t['spend'] > 0 || $t['impressions'] > 0) $plats[$p] = $t; }
    $camps = [];
    foreach (all('SELECT * FROM ad_campaigns WHERE client_id = ? ORDER BY platform, name', [$a['id']]) as $c) {
        $t = ads_totals((int)$a['id'], $from, $to, (int)$c['id']);
        if ($t['spend'] <= 0 && $t['impressions'] <= 0) continue;
        $sl = $c['utm_campaign'] !== '' ? ($S['site']['by_campaign'][strtolower($c['utm_campaign'])] ?? null) : null;
        $camps[] = $c + ['t' => $t, 'site_leads' => $sl ? (int)$sl['conv'] : null];
    }
    usort($camps, function ($x, $y) { return $y['t']['spend'] <=> $x['t']['spend']; });
    $log = all('SELECT * FROM ad_log WHERE client_id = ? AND shared = 1 AND created_at >= ? AND created_at <= ? ORDER BY created_at', [$a['id'], $from . ' 00:00:00', $to . ' 23:59:59']);
    return ['from' => $from, 'to' => $to, 'S' => $S, 'P' => $P, 'platforms' => $plats, 'campaigns' => $camps, 'log' => $log, 'daily' => ads_daily((int)$a['id'], $from, $to), 'note' => ads_month_note((int)$a['id'], $month)];
}
function ads_report_text(array $a, string $month): string {
    $d = ads_report_data($a, $month); $S = $d['S']; $P = $d['P'];
    $chg = function ($now, $was) { if (!$was) return ''; $c = ($now - $was) / $was * 100; return ' (' . ($c >= 0 ? '+' : '') . round($c) . '% vs last month)'; };
    $t = "Here’s how " . $a['company'] . "’s ads did in " . date('F Y', strtotime($month . '-01')) . ".\n\nSee the full report with charts:\n" . ads_report_url($a, $month) . "\n\n";
    $t .= "THE NUMBERS\n  Ad spend: " . ads_money($S['spend'], 2) . $chg($S['spend'], $P['spend']) . "\n  Leads: " . rtrim(rtrim(number_format($S['leads'], 1), '0'), '.') . $chg($S['leads'], $P['leads']) . "\n"
        . '  Cost per lead: ' . ($S['cpl'] !== null ? ads_money($S['cpl'], 2) : '—') . "\n  Clicks: " . number_format((int)$S['clicks']) . "\n";
    foreach ($d['platforms'] as $p => $x) $t .= "\n  " . ads_platform($p) . ': ' . ads_money($x['spend'], 2) . ' spent, ' . number_format((int)$x['clicks']) . ' clicks';
    if ($d['note']['summary'] !== '') $t .= "\n\nOUR NOTES\n" . $d['note']['summary'] . "\n";
    if ($d['note']['next_steps'] !== '') $t .= "\nNEXT MONTH\n" . $d['note']['next_steps'] . "\n";
    if ($d['log']) $t .= "\nWHAT WE DID\n" . implode('', array_map(function ($l) { return '  ' . date('M j', strtotime($l['created_at'])) . ' · ' . $l['title'] . "\n"; }, $d['log']));
    $t .= "\nAd spend is paid to the platforms directly; it isn’t part of our fee. Questions? Reply to this email or message us in your ads portal: " . ads_url('portal.php') . "\n\n" . ads_brand($a) . "\n" . setting('business_email');
    return $t;
}

/* ---------- Scheduled work (cron) ---------- */
function ads_cron(): string {
    $sent = 0; $tasks = 0; $last = date('Y-m', strtotime('first day of last month'));
    foreach (all("SELECT * FROM ad_clients WHERE status IN ('active','paused')") as $row) {
        $a = ads_client((int)$row['id']);
        // Day 1: ask for the month's notes. Days 1–5: send the report once the notes are in, or on day 5 regardless.
        if ((int)date('j') <= 5) {
            $note = ads_month_note((int)$a['id'], $last);
            if (!$note['sent_at'] && strtotime($a['since'] ?: $a['created_at']) < strtotime(date('Y-m-01'))) {
                if ($note['summary'] === '' && (int)date('j') < 5) { ads_task($a, 'Write the ' . date('F', strtotime($last . '-01')) . ' ads report notes for ' . $a['company'], 1); $tasks++; continue; }
                $body = ads_report_text($a, $last);
                foreach (ads_people($a) as $c) if (send_mail($c['email'], $a['company'] . ' · ' . date('F Y', strtotime($last . '-01')) . ' ads report', 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\n" . $body)) $sent++;
                q('INSERT OR REPLACE INTO ad_months (client_id, month, summary, next_steps, sent_at) VALUES (?,?,?,?,?)', [$a['id'], $last, $note['summary'], $note['next_steps'], now()]);
                ads_log((int)$a['id'], 'report', date('F', strtotime($last . '-01')) . ' report sent', '', true);
            }
        }
        // Creatives waiting on the client: one nudge after 2 days.
        $wait = all("SELECT id FROM ad_creatives WHERE client_id = ? AND status = 'review' AND nudged_at IS NULL AND updated_at < ?", [$a['id'], date('Y-m-d H:i:s', time() - 2 * 86400)]);
        if ($wait) { ads_notify_review($a, count($wait)); q("UPDATE ad_creatives SET nudged_at = ? WHERE id IN (" . implode(',', array_map('intval', array_column($wait, 'id'))) . ')', [now()]); }
    }
    return trim(($sent ? "$sent ads report(s) emailed" : '') . ($tasks ? " $tasks ads report note task(s)" : ''));
}

/* ---------- Charts (dark app) ---------- */
function ads_bars(array $vals, array $labels, string $prefix = '', string $aria = ''): string {
    $max = max(1, max($vals ?: [0])); $n = max(1, count($vals)); $w = 600; $h = 140; $bw = $w / $n;
    $o = '<svg class="ad-chart" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . e($aria) . '">';
    foreach (array_values($vals) as $i => $v) { $bh = $v > 0 ? max(2, $h * $v / $max * .92) : 0; $o .= '<rect x="' . round($i * $bw + $bw * .14, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw * .72, 1) . '" height="' . round($bh, 1) . '" rx="2"><title>' . e(($labels[$i] ?? '') . ': ' . $prefix . number_format($v, $prefix ? 2 : 0)) . '</title></rect>'; }
    return '<div class="ad-cw"><span class="ad-y">' . e($prefix . number_format($max, 0)) . '</span>' . $o . '</svg><div class="ad-x"><span>' . e($labels[0] ?? '') . '</span><span>' . e(end($labels) ?: '') . '</span></div></div>';
}
function ads_pace_bar(?array $p): string {
    if (!$p) return '<span class="muted small">No budget set</span>';
    $pct = $p['budget'] > 0 ? min(100, 100 * $p['spent'] / $p['budget']) : 0; $mark = $p['budget'] > 0 ? min(100, 100 * $p['expected'] / $p['budget']) : 0;
    $cls = $p['pace'] === null ? '' : ($p['pace'] > 115 ? 'bad' : ($p['pace'] < 75 ? 'warn' : 'ok'));
    return '<div class="pace ' . $cls . '" title="' . e(ads_money($p['spent']) . ' of ' . ads_money($p['budget']) . ' · expected by now ' . ads_money($p['expected'])) . '"><i style="width:' . round($pct, 1) . '%"></i><b style="left:' . round($mark, 1) . '%"></b></div>';
}
