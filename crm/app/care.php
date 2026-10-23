<?php
// Website care: everything behind the $49/month care plan.
//   Every cron run (15 min): uptime check of every monitored site, incidents, down/recovery alerts.
//   Daily: SSL certificate expiry, overdue-backup reminders. Weekly: domain expiry (RDAP), security & health scan
//   (HTTPS, headers, exposed files, mixed content, broken links, basics). Monthly: the care report email.
// Staff pages: care.php, care-site.php. Client page: portal-care.php.

const CARE_KINDS = ['update' => 'Update', 'security' => 'Security', 'backup' => 'Backup', 'edit' => 'Edit', 'fix' => 'Fix', 'speed' => 'Speed', 'note' => 'Note'];
const CARE_REQ_STATUSES = ['open' => 'Received', 'in_progress' => 'In progress', 'done' => 'Done', 'declined' => 'Not included'];
const CARE_UA = 'SyndicateCare/1.0 (website monitoring; +https://syndicatemarketing.co)';

function care_url(array $s): string {
    if ($s['check_url'] !== '') return $s['check_url'];
    $d = site_domains($s)[0] ?? '';
    return $d === '' ? '' : 'https://' . $d . '/';
}
function care_host(array $s): string { return strtolower((string)parse_url(care_url($s), PHP_URL_HOST)); }
function care_alerts(array $s): array { return json_decode($s['alerts'] ?: '{}', true) ?: []; }
function care_alert_once(array &$s, string $key): bool {
    $a = care_alerts($s);
    if (isset($a[$key])) return false;
    $a[$key] = time();
    $s['alerts'] = json_encode($a);
    q('UPDATE sites SET alerts = ? WHERE id = ?', [$s['alerts'], $s['id']]);
    return true;
}
function care_staff_email(): string { return setting('shop_notify') ?: setting('business_email'); }
function care_people(array $s): array {
    return $s['company_id'] ? all("SELECT * FROM contacts WHERE company_id = ? AND portal_access = 1 AND email != ''", [(int)$s['company_id']]) : [];
}
function care_brand(array $s): string {
    $b = (string)val('SELECT brand FROM companies WHERE id = ?', [(int)$s['company_id']]);
    return $b && $b !== 'Syndicate Holdings Group' ? $b : 'Syndicate Marketing';
}
function care_task(array $s, string $title, int $prio = 1): void {
    if (one('SELECT 1 FROM tasks WHERE done_at IS NULL AND title = ?', [$title])) return;
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [$title, today(), $prio, $s['company_id'] ? 'company' : '', $s['company_id'] ?: null, now()]);
}
function care_dur(int $sec): string {
    if ($sec < 90) return max(1, (int)round($sec / 60)) . ' min';
    if ($sec < 5400) return round($sec / 60) . ' min';
    if ($sec < 172800) return round($sec / 3600, 1) . ' hours';
    return round($sec / 86400, 1) . ' days';
}
function care_days_left(?int $ts): ?int { return $ts ? (int)floor(($ts - time()) / 86400) : null; }
function care_tier(array $s): array { return CARE_TIERS[$s['care_sku'] ?? ''] ?? CARE_TIERS['care-plan']; }
function care_is_plus(array $s): bool { return ($s['care_sku'] ?? '') === 'care-plus'; }
function care_hosted(array $s): bool { return in_array($s['care_platform'] ?? '', CARE_HOSTED, true); }

/* ---------- HTTP ---------- */
// Fetches several URLs at once. Each request: ['url' => ..., 'method' => 'GET'|'HEAD', 'max' => bytes, 'follow' => bool].
// Returns, in the same order: ['code', 'ms', 'err', 'body', 'headers' (lowercased name => value), 'final'].
function care_fetch(array $reqs, int $timeout = 15): array {
    $out = [];
    if (!function_exists('curl_multi_init')) {
        foreach ($reqs as $i => $r) {
            $t = microtime(true);
            $ctx = stream_context_create(['http' => ['method' => $r['method'] ?? 'GET', 'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => ($r['follow'] ?? true) ? 1 : 0, 'header' => 'User-Agent: ' . CARE_UA]]);
            $body = @file_get_contents($r['url'], false, $ctx, 0, $r['max'] ?? 1048576);
            $hs = $http_response_header ?? []; $code = 0; $h = [];
            foreach ($hs as $line) { if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) { $code = (int)$m[1]; $h = []; } elseif (strpos($line, ':')) { [$k, $v] = explode(':', $line, 2); $h[strtolower(trim($k))] = trim($v); } }
            $out[$i] = ['code' => $code, 'ms' => (int)round((microtime(true) - $t) * 1000), 'err' => $body === false && !$code ? 'No response' : '', 'body' => (string)$body, 'headers' => $h, 'final' => $r['url']];
        }
        return $out;
    }
    $mh = curl_multi_init(); $hs = []; $bufs = []; $hdrs = [];
    foreach ($reqs as $i => $r) {
        $ch = curl_init($r['url']);
        $bufs[$i] = ''; $hdrs[$i] = []; $max = $r['max'] ?? 1048576;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => $r['follow'] ?? true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_NOBODY => ($r['method'] ?? 'GET') === 'HEAD', CURLOPT_USERAGENT => CARE_UA, CURLOPT_ENCODING => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$hdrs, $i) { if (preg_match('#^HTTP/#', $line)) $hdrs[$i] = []; elseif (strpos($line, ':')) { [$k, $v] = explode(':', $line, 2); $hdrs[$i][strtolower(trim($k))] = trim($v); } return strlen($line); },
            CURLOPT_WRITEFUNCTION => function ($ch, $d) use (&$bufs, $i, $max) { if (strlen($bufs[$i]) < $max) $bufs[$i] .= substr($d, 0, $max - strlen($bufs[$i])); return strlen($d); },
        ]);
        curl_multi_add_handle($mh, $ch); $hs[$i] = $ch;
    }
    do { $st = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1.0); } while ($running && $st === CURLM_OK);
    $codes = []; // curl_error() is empty under curl_multi; each transfer's result code comes from here
    while ($info = curl_multi_info_read($mh)) $codes[is_object($info['handle']) ? spl_object_id($info['handle']) : (int)$info['handle']] = (int)$info['result'];
    foreach ($hs as $i => $ch) {
        $cc = $codes[is_object($ch) ? spl_object_id($ch) : (int)$ch] ?? 0;
        $out[$i] = ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'ms' => (int)round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000), 'err' => $cc && $cc !== 23 ? care_curl_reason($cc, curl_error($ch)) : curl_error($ch),
            'body' => $bufs[$i], 'headers' => $hdrs[$i], 'final' => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)];
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

// A plain-words reason for a failed request, for alerts, the incident log and client emails.
function care_curl_reason(int $code, string $detail = ''): string {
    $why = care_curl_plain($code);
    return $detail !== '' && stripos($why, $detail) === false ? $why . ' (' . mb_substr($detail, 0, 160) . ')' : $why;
}
function care_curl_plain(int $code): string {
    // libcurl error numbers (stable across versions; not every PHP build defines the constants).
    $plain = [6 => 'The domain name didn’t resolve (DNS problem, or the domain lapsed)', 7 => 'The server refused the connection',
        28 => 'The server didn’t answer in time', 35 => 'The secure connection (SSL) failed', 47 => 'The page keeps redirecting',
        51 => 'The security certificate isn’t valid (expired, or for a different name)', 52 => 'The server closed the connection without answering',
        56 => 'The connection dropped while loading', 60 => 'The security certificate isn’t valid (expired, or for a different name)'];
    return $plain[$code] ?? ('Connection error: ' . curl_strerror($code));
}

/* ---------- Uptime (every cron run) ---------- */
// Checks every monitored site once. A site is "down" after two failed checks in a row (so one blip doesn't alert anyone).
function care_run_checks(?int $onlySite = null): int {
    $sites = all('SELECT * FROM sites WHERE monitor = 1' . ($onlySite ? ' AND id = ' . (int)$onlySite : ''));
    $sites = array_values(array_filter($sites, function ($s) { return preg_match('#^https?://#i', care_url($s)); }));
    if (!$sites) return 0;
    $res = care_fetch(array_map(function ($s) { return ['url' => care_url($s), 'max' => 524288]; }, $sites));
    foreach ($sites as $i => $s) {
        $r = $res[$i]; $now = time();
        $ok = $r['code'] >= 200 && $r['code'] < 400;
        $err = $r['err'] !== '' ? $r['err'] : ($ok ? '' : ($r['code'] ? 'The page answered with an error (HTTP ' . $r['code'] . ')' : 'No response'));
        if ($ok && $s['check_keyword'] !== '' && stripos($r['body'], $s['check_keyword']) === false) { $ok = false; $err = 'The page loaded but “' . $s['check_keyword'] . '” wasn’t on it'; }
        q('INSERT INTO site_checks (site_id, ts, ok, code, ms, err) VALUES (?,?,?,?,?,?)', [$s['id'], $now, $ok ? 1 : 0, $r['code'], $r['ms'], mb_substr($err, 0, 300)]);
        $fails = $ok ? 0 : (int)$s['fail_count'] + 1;
        q('UPDATE sites SET last_check = ?, last_ms = ?, last_error = ?, fail_count = ? WHERE id = ?', [$now, $r['ms'], $err, $fails, $s['id']]);
        if (!$ok && $fails >= 2 && $s['status'] !== 'down') care_went_down($s, $err);
        elseif ($ok && $s['status'] !== 'up') care_came_up($s);
    }
    q('DELETE FROM site_checks WHERE ts < ?', [time() - 400 * 86400]);
    return count($sites);
}
function care_went_down(array $s, string $err): void {
    $since = (int)(val('SELECT MIN(ts) FROM site_checks WHERE site_id = ? AND ts > ? AND ok = 0 AND ts > COALESCE((SELECT MAX(ts) FROM site_checks WHERE site_id = ? AND ok = 1), 0)', [$s['id'], time() - 86400, $s['id']]) ?: time());
    q("UPDATE sites SET status = 'down', status_since = ? WHERE id = ?", [$since, $s['id']]);
    q('INSERT INTO site_incidents (site_id, started, cause) VALUES (?,?,?)', [$s['id'], $since, mb_substr($err, 0, 300)]);
    $url = care_url($s);
    send_mail(care_staff_email(), '[Site down] ' . $s['name'], $s['name'] . " ($url) is down.\n\nWhat we see: $err\nDown since: " . date('M j, g:i a', $since) . "\n\n" . abs_url('care-site.php?id=' . $s['id']) . "\n");
    care_task($s, 'Site down: ' . $s['name'] . ' — check it now', 1);
    if ($s['care'] && $s['notify_client']) foreach (care_people($s) as $c)
        send_mail($c['email'], 'Heads up: ' . $s['name'] . ' is down and we’re on it', 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\nOur monitoring noticed that $url stopped responding at " . date('g:i a', $since) . ". We’ve been alerted and are looking into it now. You don’t need to do anything.\n\nWe’ll email you again as soon as it’s back.\n\n" . care_brand($s) . "\n" . setting('business_email'));
    log_activity('company', (int)$s['company_id'], 'note', 'Website down: ' . $s['name'] . " ($err).");
}
function care_came_up(array $s): void {
    $wasDown = $s['status'] === 'down';
    q("UPDATE sites SET status = 'up', status_since = ? WHERE id = ?", [time(), $s['id']]);
    if (!$wasDown) return;
    $inc = one('SELECT * FROM site_incidents WHERE site_id = ? AND ended IS NULL ORDER BY id DESC LIMIT 1', [$s['id']]);
    $dur = $inc ? time() - (int)$inc['started'] : 0;
    if ($inc) q('UPDATE site_incidents SET ended = ? WHERE id = ?', [time(), $inc['id']]);
    send_mail(care_staff_email(), '[Site back up] ' . $s['name'], $s['name'] . ' is back up after ' . care_dur($dur) . ".\n\n" . abs_url('care-site.php?id=' . $s['id']) . "\n");
    if ($s['care'] && $s['notify_client']) foreach (care_people($s) as $c)
        send_mail($c['email'], $s['name'] . ' is back up', 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\nGood news: " . care_url($s) . ' is working again. It was down for about ' . care_dur($dur) . ".\n\nYou can see the details, and your site’s uptime history, in your client portal: " . abs_url('portal-care.php?id=' . $s['id']) . "\n\n" . care_brand($s) . "\n" . setting('business_email'));
    log_activity('company', (int)$s['company_id'], 'note', 'Website back up: ' . $s['name'] . ' (down ' . care_dur($dur) . ').');
}

/* ---------- Uptime numbers ---------- */
function care_uptime(int $siteId, int $from, int $to): ?float {
    $r = one('SELECT COUNT(*) n, SUM(ok) up FROM site_checks WHERE site_id = ? AND ts >= ? AND ts < ?', [$siteId, $from, $to]);
    return $r && $r['n'] ? 100 * (int)$r['up'] / (int)$r['n'] : null;
}
function care_avg_ms(int $siteId, int $from, int $to): ?int { $v = val('SELECT AVG(ms) FROM site_checks WHERE site_id = ? AND ts >= ? AND ts < ? AND ok = 1', [$siteId, $from, $to]); return $v !== null && $v !== false ? (int)round((float)$v) : null; }
// One entry per day for the uptime bar: ['day' => 'Y-m-d', 'pct' => float|null, 'down' => seconds].
function care_days(int $siteId, int $days): array {
    $start = strtotime('-' . ($days - 1) . ' days', strtotime('today'));
    return care_days_between($siteId, $start, strtotime('tomorrow'));
}
// Same, for any range of whole days, with the average response time ('ms') too.
function care_days_between(int $siteId, int $from, int $to): array {
    $rows = []; foreach (all("SELECT date(ts, 'unixepoch', 'localtime') d, COUNT(*) n, SUM(ok) up, AVG(CASE WHEN ok = 1 THEN ms END) ms FROM site_checks WHERE site_id = ? AND ts >= ? AND ts < ? GROUP BY d", [$siteId, $from, $to]) as $r) $rows[$r['d']] = $r;
    $out = [];
    for ($t = $from; $t < $to; $t = strtotime('+1 day', $t)) { $d = date('Y-m-d', $t); $r = $rows[$d] ?? null; $out[] = ['day' => $d, 'pct' => $r ? 100 * (int)$r['up'] / (int)$r['n'] : null, 'fails' => $r ? (int)$r['n'] - (int)$r['up'] : 0, 'ms' => $r && $r['ms'] !== null ? (int)round((float)$r['ms']) : null]; }
    return $out;
}
function care_fmt_pct(?float $p): string { return $p === null ? '—' : ($p >= 99.995 ? '100%' : number_format(floor($p * 100) / 100, 2) . '%'); }
function care_uptime_bar(array $days): string {
    $o = '<div class="up-bar" role="img" aria-label="Uptime by day">';
    foreach ($days as $d) {
        $c = $d['pct'] === null ? 'none' : ($d['pct'] >= 99.9 ? 'ok' : ($d['pct'] >= 97 ? 'warn' : 'bad'));
        $o .= '<i class="' . $c . '" title="' . e(date('M j', strtotime($d['day'])) . ': ' . ($d['pct'] === null ? 'no checks' : care_fmt_pct($d['pct']) . ' up')) . '"></i>';
    }
    return $o . '</div>';
}

/* ---------- SSL & domain ---------- */
function care_check_ssl(array &$s): void {
    $host = care_host($s);
    if ($host === '' || stripos(care_url($s), 'https://') !== 0) return;
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client('ssl://' . $host . ':443', $no, $msg, 10, STREAM_CLIENT_CONNECT, $ctx);
    $exp = null; $issuer = '';
    if ($fp) {
        $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
        $info = $cert ? openssl_x509_parse($cert) : null;
        if ($info) { $exp = (int)$info['validTo_time_t']; $issuer = (string)($info['issuer']['O'] ?? $info['issuer']['CN'] ?? ''); }
        fclose($fp);
    }
    q('UPDATE sites SET ssl_expires = ?, ssl_issuer = ?, ssl_checked = ? WHERE id = ?', [$exp, $issuer, time(), $s['id']]);
    $s['ssl_expires'] = $exp;
    $left = care_days_left($exp);
    if ($exp && $left !== null && $left <= 14) {
        $tier = $left <= 3 ? '3' : '14';
        if (care_alert_once($s, "ssl$tier:$exp")) {
            send_mail(care_staff_email(), '[SSL expiring] ' . $s['name'] . " in $left days", "The SSL certificate for $host expires " . date('M j, Y', $exp) . " ($left days). Issuer: $issuer.\nMost certificates renew on their own (AutoSSL / Let’s Encrypt); if it hasn’t renewed by now, renew it in the host’s control panel.\n\n" . abs_url('care-site.php?id=' . $s['id']) . "\n");
            care_task($s, "Renew SSL for $host (expires " . date('M j', $exp) . ')', 1);
        }
    }
}
// The registrable domain for a host: example.com from www.example.com, example.co.uk from shop.example.co.uk.
function care_root_domain(string $host): string {
    $p = explode('.', $host); $n = count($p);
    if ($n <= 2) return $host;
    $two = in_array($p[$n - 2], ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu'], true) && strlen($p[$n - 1]) === 2;
    return implode('.', array_slice($p, $two ? -3 : -2));
}
function care_check_domain(array &$s): void {
    $host = care_host($s);
    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') return;
    $root = care_root_domain($host);
    $r = care_fetch([['url' => 'https://rdap.org/domain/' . rawurlencode($root), 'max' => 262144]], 20)[0];
    $d = json_decode($r['body'], true);
    $exp = null; $reg = '';
    foreach ((array)($d['events'] ?? []) as $ev) if (($ev['eventAction'] ?? '') === 'expiration' && !empty($ev['eventDate'])) $exp = strtotime($ev['eventDate']) ?: null;
    foreach ((array)($d['entities'] ?? []) as $en) if (in_array('registrar', (array)($en['roles'] ?? []), true)) {
        foreach ((array)($en['vcardArray'][1] ?? []) as $vc) if (($vc[0] ?? '') === 'fn') $reg = (string)$vc[3];
    }
    q('UPDATE sites SET domain_expires = COALESCE(?, domain_expires), domain_registrar = CASE WHEN ? != \'\' THEN ? ELSE domain_registrar END, domain_checked = ? WHERE id = ?', [$exp, $reg, $reg, time(), $s['id']]);
    if ($exp) $s['domain_expires'] = $exp;
    $exp = (int)($s['domain_expires'] ?? 0);
    $left = care_days_left($exp ?: null);
    if ($exp && $left !== null && $left <= 30) {
        $tier = $left <= 7 ? '7' : '30';
        if (care_alert_once($s, "domain$tier:$exp")) {
            send_mail(care_staff_email(), '[Domain expiring] ' . $root . " in $left days", "The domain $root expires " . date('M j, Y', $exp) . " ($left days)" . ($reg ? " at $reg" : '') . ". If auto-renew is off, the site and email stop working when it lapses.\n\n" . abs_url('care-site.php?id=' . $s['id']) . "\n");
            care_task($s, "Check the renewal of $root (expires " . date('M j', $exp) . ')', 1);
        }
    }
}

/* ---------- Weekly security & health scan ---------- */
const CARE_EXPOSED = [['/.env', '/^\s*[A-Z_]+\s*=/m', 'Environment file with settings or passwords'], ['/.git/HEAD', '/^ref: refs\//', 'Git folder (can leak the site’s source code)'],
    ['/wp-config.php.bak', '/DB_PASSWORD|DB_NAME/', 'Backup of the WordPress config file'], ['/phpinfo.php', '/phpinfo\(\)|PHP Version/i', 'phpinfo page showing server details'], ['/.DS_Store', '/^\x00\x00\x00\x01Bud1/', 'Mac folder listing file']];
function care_scan(array &$s): array {
    $url = care_url($s); $host = care_host($s);
    $base = preg_replace('#^(https?://[^/]+).*$#i', '$1', $url);
    $reqs = [['url' => $url, 'max' => 2097152], ['url' => 'http://' . $host . '/', 'follow' => false, 'max' => 4096], ['url' => $base . '/sitemap.xml', 'max' => 1048576]];
    foreach (CARE_EXPOSED as [$path]) $reqs[] = ['url' => $base . $path, 'max' => 8192, 'follow' => false];
    $res = care_fetch($reqs, 20);
    [$home, $plain, $map] = $res; $exposedRes = array_slice($res, 3);
    $h = $home['headers']; $body = $home['body'];
    $scan = ['at' => time(), 'ok' => $home['code'] >= 200 && $home['code'] < 400, 'code' => $home['code'], 'ms' => $home['ms'], 'kb' => (int)round(strlen($body) / 1024)];
    $scan['https'] = stripos($home['final'] ?: $url, 'https://') === 0;
    $loc = (string)($plain['headers']['location'] ?? '');
    $scan['redirect'] = $plain['code'] >= 300 && $plain['code'] < 400 && stripos($loc, 'https://') === 0;
    $scan['headers'] = ['hsts' => isset($h['strict-transport-security']), 'nosniff' => stripos((string)($h['x-content-type-options'] ?? ''), 'nosniff') !== false,
        'frames' => isset($h['x-frame-options']) || stripos((string)($h['content-security-policy'] ?? ''), 'frame-ancestors') !== false, 'csp' => isset($h['content-security-policy']), 'referrer' => isset($h['referrer-policy'])];
    $scan['exposed'] = [];
    foreach (CARE_EXPOSED as $i => [$path, $re, $what]) { $r = $exposedRes[$i]; if ($r['code'] === 200 && preg_match($re, $r['body'])) $scan['exposed'][] = ['path' => $path, 'what' => $what]; }
    $scan['software'] = preg_match('#<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']{1,60})#i', $body, $m) ? html_entity_decode($m[1]) : '';
    if ($scan['software'] === '' && stripos($body, '/wp-content/') !== false) $scan['software'] = 'WordPress';
    $scan['server'] = mb_substr((string)($h['server'] ?? ''), 0, 60);
    $scan['title'] = preg_match('#<title[^>]*>(.*?)</title>#is', $body, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
    $scan['description'] = (bool)preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']{20,}#i', $body);
    $scan['viewport'] = (bool)preg_match('#<meta[^>]+name=["\']viewport["\']#i', $body);
    $scan['mixed'] = [];
    if ($scan['https'] && preg_match_all('#<(?:img|script|iframe|link|source|video|audio)\b[^>]+(?:src|href)=["\'](http://[^"\']+)#i', $body, $mm)) $scan['mixed'] = array_slice(array_values(array_unique($mm[1])), 0, 10);
    // Broken links: links on the home page and pages in the sitemap, on the same site.
    $urls = [];
    if (preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $map['body'], $mm)) foreach ($mm[1] as $u) $urls[html_entity_decode($u)] = 'sitemap.xml';
    if (preg_match_all('#<a\b[^>]+href=["\']([^"\'\#]+)#i', $body, $mm)) foreach ($mm[1] as $u) {
        $u = html_entity_decode(trim($u));
        if (preg_match('#^(mailto|tel|javascript|data):#i', $u)) continue;
        if (strpos($u, '//') === 0) $u = 'https:' . $u; elseif (!preg_match('#^https?://#i', $u)) $u = $base . '/' . ltrim($u, '/');
        if (!isset($urls[$u])) $urls[$u] = 'home page';
    }
    $urls = array_filter($urls, function ($from, $u) use ($host) { $uh = strtolower((string)parse_url($u, PHP_URL_HOST)); return $uh === $host || $uh === 'www.' . $host || 'www.' . $uh === $host; }, ARRAY_FILTER_USE_BOTH);
    $urls = array_slice($urls, 0, 60, true);
    $scan['links'] = count($urls); $scan['broken'] = [];
    if ($urls) {
        $r = care_fetch(array_map(function ($u) { return ['url' => $u, 'max' => 2048]; }, array_keys($urls)), 15);
        $i = 0;
        foreach ($urls as $u => $from) { $x = $r[$i++]; if ($x['code'] >= 400 || $x['code'] === 0) $scan['broken'][] = ['url' => $u, 'code' => $x['code'], 'from' => $from]; }
    }
    q('UPDATE sites SET scan = ?, scan_checked = ? WHERE id = ?', [json_encode($scan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), time(), $s['id']]);
    $s['scan'] = json_encode($scan);
    if ($scan['exposed'] && care_alert_once($s, 'exposed:' . md5(json_encode($scan['exposed'])))) {
        send_mail(care_staff_email(), '[Security] Exposed files on ' . $s['name'], "This week’s scan of $host found files that shouldn’t be public:\n\n" . implode("\n", array_map(function ($x) use ($base) { return '  ' . $base . $x['path'] . ' — ' . $x['what']; }, $scan['exposed'])) . "\n\nRemove them or block them on the server.\n\n" . abs_url('care-site.php?id=' . $s['id']) . "\n");
        care_task($s, 'Security: remove exposed files on ' . $host, 1);
    }
    if ($scan['broken'] && care_alert_once($s, 'broken:' . date('o-W'))) care_task($s, 'Fix ' . count($scan['broken']) . ' broken link' . (count($scan['broken']) === 1 ? '' : 's') . ' on ' . $host, 0);
    return $scan;
}
// Plain-language findings for the report and the portal: [['good'|'warn'|'bad', text], ...]
function care_findings(array $s): array {
    $scan = json_decode($s['scan'] ?: '', true);
    if (!$scan) return [];
    $f = [];
    $f[] = $scan['https'] && ($scan['redirect'] ?? false) ? ['good', 'Secure connection (HTTPS) on every visit'] : ($scan['https'] ? ['warn', 'HTTPS works, but plain http:// visits aren’t redirected to it'] : ['bad', 'The site doesn’t use a secure connection (HTTPS)']);
    $hd = $scan['headers'] ?? []; $have = count(array_filter($hd)); $tot = count($hd);
    $f[] = $have >= 4 ? ['good', "Browser security settings in place ($have of $tot)"] : ['warn', "Browser security settings: $have of $tot in place"];
    $f[] = $scan['exposed'] ? ['bad', count($scan['exposed']) . ' private file' . (count($scan['exposed']) === 1 ? ' was' : 's were') . ' publicly reachable (we’re fixing it)'] : ['good', 'No private files exposed'];
    if ($scan['mixed']) $f[] = ['warn', count($scan['mixed']) . ' item' . (count($scan['mixed']) === 1 ? '' : 's') . ' loading over an insecure connection'];
    $f[] = $scan['broken'] ? ['warn', count($scan['broken']) . ' broken link' . (count($scan['broken']) === 1 ? '' : 's') . ' found (of ' . $scan['links'] . ' checked)'] : ['good', 'No broken links (' . ($scan['links'] ?? 0) . ' checked)'];
    if (!$scan['description'] || $scan['title'] === '') $f[] = ['warn', 'Home page is missing a ' . ($scan['title'] === '' ? 'title' : 'search description')];
    if (!$scan['viewport']) $f[] = ['warn', 'Home page isn’t set up for phones (no viewport tag)'];
    return $f;
}

/* ---------- Backups ---------- */
function care_last_backup(int $siteId): ?string { $v = val("SELECT MAX(created_at) FROM care_log WHERE site_id = ? AND kind = 'backup'", [$siteId]); return $v ?: null; }
function care_backup_due(array $s): bool { $l = care_last_backup((int)$s['id']); return !$l || strtotime($l) < time() - ((int)$s['backup_days'] + 1) * 86400; }

/* ---------- Edit minutes ---------- */
function care_minutes_used(int $siteId, ?string $month = null): int {
    $month = $month ?: date('Y-m');
    return (int)val("SELECT COALESCE(SUM(minutes), 0) FROM care_log WHERE site_id = ? AND kind = 'edit' AND substr(created_at, 1, 7) = ?", [$siteId, $month]);
}

/* ---------- Scheduled work ---------- */
// Called from cron.php every run. Checks run every time; daily and weekly work runs when it's due.
function care_cron(): string {
    $n = care_run_checks(); $out = $n ? "$n site check(s)" : '';
    foreach (all('SELECT * FROM sites WHERE monitor = 1') as $s) {
        if (!preg_match('#^https?://#i', care_url($s))) continue;
        if ((int)$s['ssl_checked'] < time() - 86000) care_check_ssl($s);
        if ((int)$s['domain_checked'] < time() - 7 * 86400) care_check_domain($s);
        if ((int)$s['scan_checked'] < time() - 7 * 86400) care_scan($s);
        if ($s['care'] && care_backup_due($s) && care_alert_once($s, 'backup:' . today())) care_task($s, 'Back up ' . $s['name'] . ' (' . (care_hosted($s) ? 'content export' : 'care plan') . ', every ' . (int)$s['backup_days'] . ' days)', 0);
        if ($s['care'] && care_is_plus($s)) care_plus_tasks($s);
    }
    $psi = care_psi_due();
    $r = care_monthly_reports();
    return trim($out . ($psi ? " · $psi PageSpeed test(s)" : '') . ($r ? " · $r care report(s) emailed" : ''), ' ·');
}

/* ---------- Turning care on and off ---------- */
// A paid care plan switches care and monitoring on for the client's site. $orderId is the store order id, or minus the quote id.
// $sku is care-plan or care-plus. $brief holds the checkout answers: care_url, care_platform, care_access (sites we didn't build).
function care_enable_for(?int $siteId, int $orderId, string $who, string $sku = 'care-plan', array $brief = []): void {
    if (!$siteId) return;
    $tier = CARE_TIERS[$sku] ?? CARE_TIERS['care-plan']; $sku = isset(CARE_TIERS[$sku]) ? $sku : 'care-plan';
    $ours = !empty($brief['built_by_us']);
    $platform = isset(CARE_PLATFORMS[$brief['care_platform'] ?? '']) ? $brief['care_platform'] : ($ours ? 'custom' : 'unsure');
    $url = trim((string)($brief['care_url'] ?? ''));
    if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
    q("UPDATE sites SET care = 1, monitor = 1, care_order_id = ?, notify_client = 1, care_sku = ?, care_minutes = ?, care_platform = ?, care_built = ?, backup_days = ?,
        care_since = COALESCE(care_since, ?), check_url = CASE WHEN check_url = '' THEN ? ELSE check_url END WHERE id = ?",
        [$orderId, $sku, $tier['minutes'], $platform, $ours ? 1 : 0, in_array($platform, CARE_HOSTED, true) ? 30 : 7, now(), filter_var($url, FILTER_VALIDATE_URL) ? $url : '', $siteId]);
    $s = one('SELECT * FROM sites WHERE id = ?', [$siteId]);
    care_report_key($s);
    q("INSERT INTO care_log (site_id, kind, title, details, minutes, shared, created_at) VALUES (?, 'note', ?, ?, 0, 1, ?)", [$siteId, $tier['name'] . ' started', 'Monitoring, weekly scans and monthly reports are on for ' . $s['name'] . '.', now()]);
    if (!$ours) {
        // A site someone else built: get access, a first backup and the stats snippet in place.
        $pl = CARE_PLATFORMS[$platform];
        care_task($s, "Care onboarding: get admin access to {$s['name']} ($pl)" . (($brief['care_access'] ?? '') === 'no' ? ' — client doesn’t have logins yet, help them find them' : ''), 1);
        care_task($s, "Care onboarding: take the first " . (in_array($platform, CARE_HOSTED, true) ? 'content export' : 'full backup') . " of {$s['name']}", 1);
        care_task($s, "Care onboarding: add the Site Stats snippet to {$s['name']} so speed and visitor numbers show in reports", 0);
        care_task($s, "Care onboarding: review the first scan for {$s['name']} and fix anything urgent", 0);
    }
    care_run_checks($siteId);
}
// Care Plus: a speed and search tune-up each quarter, and a review call each year of the plan.
function care_plus_tasks(array $s): void {
    $since = strtotime((string)($s['care_since'] ?: 'now'));
    $age = (int)floor((time() - $since) / 86400);
    $quarter = date('Y') . '-Q' . (int)ceil(date('n') / 3);
    if ($age >= 21 && care_alert_once($s, 'tuneup:' . $quarter)) care_task($s, "Care Plus: quarterly speed and search tune-up for {$s['name']} ($quarter). Start from the PageSpeed findings, log the work as Speed.", 1);
    $year = (int)floor(($age + 30) / 365);
    if ($year >= 1 && care_alert_once($s, 'review:' . $year)) care_task($s, "Care Plus: book the yearly 30-minute review call for {$s['name']}", 1);
}
function care_disable_order(int $orderId): void {
    foreach (all('SELECT * FROM sites WHERE care = 1 AND care_order_id = ?', [$orderId]) as $s) {
        q('UPDATE sites SET care = 0, monitor = 0 WHERE id = ?', [$s['id']]);
        q("INSERT INTO care_log (site_id, kind, title, shared, created_at) VALUES (?, 'note', 'Care plan ended', 0, ?)", [$s['id'], now()]);
        log_activity('company', (int)$s['company_id'], 'note', 'Care plan ended for ' . $s['name'] . '; monitoring switched off.');
    }
}

/* ---------- Monthly care report ---------- */
function care_monthly_reports(bool $anyDay = false): int {
    if ((int)date('j') > 5 && !$anyDay) return 0;
    require_once __DIR__ . '/analytics.php';
    $from = strtotime('first day of last month', strtotime('today')); $to = strtotime(date('Y-m-01')); $month = date('Y-m', $from); $label = date('F Y', $from);
    $sent = 0;
    foreach (all('SELECT * FROM sites WHERE care = 1') as $s) {
        if (setting('care_report_' . $s['id']) === $month) continue;
        setting('care_report_' . $s['id'], $month);
        $people = care_people($s);
        if (!$people) continue;
        $body = care_report_text($s, $from, $to, $label);
        foreach ($people as $c) if (send_mail($c['email'], $s['name'] . " · $label care report", 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\n" . $body)) $sent++;
        log_activity('company', (int)$s['company_id'], 'email', "Sent the $label care report for " . $s['name'] . '.');
    }
    return $sent;
}
function care_report_data(array $s, int $from, int $to): array {
    require_once __DIR__ . '/analytics.php';
    $id = (int)$s['id'];
    $inc = all('SELECT * FROM site_incidents WHERE site_id = ? AND started < ? AND (ended IS NULL OR ended > ?) ORDER BY started', [$id, $to, $from]);
    $down = 0; foreach ($inc as $i) $down += min((int)($i['ended'] ?: time()), $to) - max((int)$i['started'], $from);
    $log = all('SELECT * FROM care_log WHERE site_id = ? AND shared = 1 AND created_at >= ? AND created_at < ? ORDER BY created_at', [$id, date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $to)]);
    $R = an_report($s, ['key' => 'custom', 'bucket' => 'day', 'from' => $from, 'to' => $to, 'pfrom' => strtotime('-1 month', $from), 'pto' => $from], []);
    return ['uptime' => care_uptime($id, $from, $to), 'ms' => care_avg_ms($id, $from, $to), 'incidents' => $inc, 'down' => max(0, $down), 'log' => $log,
        'minutes' => care_minutes_used($id, date('Y-m', $from)), 'backups' => count(array_filter($log, function ($l) { return $l['kind'] === 'backup'; })),
        'requests_done' => (int)val("SELECT COUNT(*) FROM care_requests WHERE site_id = ? AND status = 'done' AND done_at >= ? AND done_at < ?", [$id, date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $to)]),
        'totals' => $R['totals'] ?? null, 'prev' => $R['prev'] ?? null, 'vitals' => $R['vitals']['all'] ?? [], 'series' => $R['series'] ?? [], 'goals' => $R['goal_rows'] ?? [],
        'days' => care_days_between($id, $from, $to), 'psi' => json_decode($s['psi'] ?: '', true) ?: null, 'tier' => care_tier($s)];
}
function care_report_text(array $s, int $from, int $to, string $label): string {
    $d = care_report_data($s, $from, $to);
    $t = "Here’s what we did for " . $s['name'] . " in $label, and how the site did.\n\nSee the full report with charts:\n" . care_report_url($s, date('Y-m', $from)) . "\n\n";
    $t .= "UPTIME\n  " . care_fmt_pct($d['uptime']) . ' online' . ($d['incidents'] ? ' · ' . count($d['incidents']) . ' outage' . (count($d['incidents']) === 1 ? '' : 's') . ', ' . care_dur($d['down']) . ' in total' : ' · no outages') . "\n";
    if ($d['ms']) $t .= '  Average response time: ' . $d['ms'] . " ms\n";
    $t .= "\nSECURITY & HEALTH\n";
    foreach (care_findings($s) as [$k, $x]) $t .= '  ' . ($k === 'good' ? '✓' : ($k === 'warn' ? '!' : '✗')) . " $x\n";
    if ($s['ssl_expires']) $t .= '  ✓ SSL certificate valid until ' . date('M j, Y', (int)$s['ssl_expires']) . "\n";
    if ($s['domain_expires']) $t .= '  ✓ Domain registered until ' . date('M j, Y', (int)$s['domain_expires']) . "\n";
    $v = array_filter($d['vitals']);
    if ($d['psi'] && empty($d['psi']['error'])) {
        $t .= "\nGOOGLE PAGESPEED (phone test)\n";
        foreach (CARE_PSI_CATS as $k => $n) if (isset($d['psi']['scores'][$k])) $t .= "  $n: " . $d['psi']['scores'][$k] . "/100\n";
    }
    if ($v) {
        $t .= "\nSPEED (real visitors, 75th percentile)\n";
        foreach (['lcp' => 'Main content shows in', 'inp' => 'Reacts to taps in', 'cls' => 'Layout shift'] as $m => $n) if (!empty($v[$m])) $t .= "  $n " . vital_fmt($m, $v[$m]['p75']) . ' (' . ['good' => 'good', 'ok' => 'needs work', 'poor' => 'slow'][vital_rating($m, $v[$m]['p75'])] . ")\n";
    }
    if ($d['totals'] && $d['totals']['visits']) $t .= "\nVISITORS\n  " . an_num($d['totals']['visitors']) . ' visitors, ' . an_num($d['totals']['conversions']) . " calls, forms and other goals\n";
    $t .= "\nWORK DONE\n";
    $t .= $d['log'] ? implode('', array_map(function ($l) { return '  ' . date('M j', strtotime($l['created_at'])) . ' · ' . (CARE_KINDS[$l['kind']] ?? 'Note') . ': ' . $l['title'] . "\n"; }, $d['log'])) : "  Routine checks only; nothing needed fixing.\n";
    $t .= '  Backups: ' . $d['backups'] . "\n  Edit time used: " . $d['minutes'] . ' of ' . (int)$s['care_minutes'] . " minutes\n";
    $t .= "\nNeed a change made? Request it in your portal and we’ll take care of it: " . abs_url('portal-care.php?id=' . $s['id']) . "\n\n" . care_brand($s) . "\n" . setting('business_email');
    return $t;
}

/* ---------- Google PageSpeed (weekly) ---------- */
const CARE_PSI_CATS = ['performance' => 'Speed', 'accessibility' => 'Accessibility', 'best-practices' => 'Best practices', 'seo' => 'Search (SEO)'];
const CARE_PSI_METRICS = ['first-contentful-paint' => 'First content shows', 'largest-contentful-paint' => 'Main content shows', 'total-blocking-time' => 'Time the page is busy', 'cumulative-layout-shift' => 'Layout shift', 'speed-index' => 'Speed index'];
function care_psi_key(): string {
    $k = setting('psi_key');
    if ($k === '') return '';
    require_once __DIR__ . '/mail.php';
    return (string)mail_decrypt($k);
}
function care_psi_endpoint(string $url): string {
    $q = 'url=' . rawurlencode($url) . '&strategy=mobile';
    foreach (array_keys(CARE_PSI_CATS) as $c) $q .= '&category=' . strtoupper(str_replace('-', '_', $c));
    $k = care_psi_key();
    $base = rtrim((string)(config('psi_api') ?: 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'), '?');
    return $base . '?' . $q . ($k !== '' ? '&key=' . rawurlencode($k) : '');
}
// Turns a PageSpeed response into the small summary we keep: scores, lab metrics and the top things to fix.
function care_psi_parse(string $json): array {
    $j = json_decode($json, true);
    if (!is_array($j) || empty($j['lighthouseResult'])) {
        $msg = (string)($j['error']['message'] ?? 'No response from PageSpeed');
        return ['at' => time(), 'error' => mb_substr(preg_replace('/\s+/', ' ', $msg), 0, 200)];
    }
    $lh = $j['lighthouseResult']; $out = ['at' => time(), 'scores' => [], 'metrics' => [], 'fixes' => []];
    foreach (CARE_PSI_CATS as $k => $n) if (isset($lh['categories'][$k]['score'])) $out['scores'][$k] = (int)round($lh['categories'][$k]['score'] * 100);
    foreach (CARE_PSI_METRICS as $k => $n) if (isset($lh['audits'][$k]['numericValue'])) $out['metrics'][$k] = ['v' => (float)$lh['audits'][$k]['numericValue'], 'show' => (string)($lh['audits'][$k]['displayValue'] ?? ''), 'score' => $lh['audits'][$k]['score']];
    $fix = [];
    foreach ($lh['audits'] ?? [] as $id => $a) {
        if (!isset($a['score']) || $a['score'] === null || $a['score'] >= 0.9 || isset(CARE_PSI_METRICS[$id])) continue;
        $save = (float)($a['details']['overallSavingsMs'] ?? 0) + (float)($a['metricSavings']['LCP'] ?? 0) + (float)($a['metricSavings']['FCP'] ?? 0) + (float)($a['metricSavings']['TBT'] ?? 0);
        if ($save <= 0) continue;
        $fix[] = ['title' => mb_substr(strip_tags((string)$a['title']), 0, 120), 'ms' => (int)round($save), 'show' => mb_substr((string)($a['displayValue'] ?? ''), 0, 60)];
    }
    usort($fix, function ($a, $b) { return $b['ms'] <=> $a['ms']; });
    $out['fixes'] = array_slice($fix, 0, 5);
    return $out;
}
// Runs PageSpeed for up to $max sites whose last test is a week old (they're slow, so a few per cron run).
function care_psi_due(int $max = 3, ?int $onlySite = null): int {
    $sites = $onlySite ? all('SELECT * FROM sites WHERE id = ?', [$onlySite])
        : all('SELECT * FROM sites WHERE monitor = 1 AND COALESCE(psi_checked, 0) < ' . (time() - 7 * 86400) . ' ORDER BY care DESC, COALESCE(psi_checked, 0) LIMIT ' . (int)$max);
    $sites = array_values(array_filter($sites, function ($s) { return preg_match('#^https://#i', care_url($s)) && !preg_match('#//(localhost|127\.|\[::1\])#', care_url($s)) || config('psi_api'); }));
    if (!$sites) return 0;
    $res = care_fetch(array_map(function ($s) { return ['url' => care_psi_endpoint(care_url($s)), 'max' => 12582912]; }, $sites), 90);
    foreach ($sites as $i => $s) {
        $p = care_psi_parse($res[$i]['body']);
        $old = json_decode($s['psi'] ?: '', true);
        if (!empty($p['error']) && $old && empty($old['error'])) { // keep the last good result, try again tomorrow
            $old['retry_error'] = $p['error'];
            q('UPDATE sites SET psi = ?, psi_checked = ? WHERE id = ?', [json_encode($old, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), time() - 6 * 86400, $s['id']]);
            continue;
        }
        if (empty($p['error']) && $old && !empty($old['scores'])) $p['prev'] = $old['scores'];
        q('UPDATE sites SET psi = ?, psi_checked = ? WHERE id = ?', [json_encode($p, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), !empty($p['error']) ? time() - 6 * 86400 : time(), $s['id']]);
        if (empty($p['error']) && ($p['scores']['performance'] ?? 100) < 50 && $s['care'] && care_alert_once($s, 'psi:' . date('Y-m'))) care_task($s, 'Speed: ' . $s['name'] . ' scores ' . $p['scores']['performance'] . '/100 on Google PageSpeed (phone). Look at the top fixes.', 0);
    }
    return count($sites);
}
function care_psi_rating(int $score): string { return $score >= 90 ? 'good' : ($score >= 50 ? 'ok' : 'poor'); }
// The four score rings, as HTML (used in the CRM, the portal and the report).
function care_psi_rings(array $psi): string {
    $o = '<div class="psi-rings">';
    foreach (CARE_PSI_CATS as $k => $n) {
        if (!isset($psi['scores'][$k])) continue;
        $v = $psi['scores'][$k]; $prev = $psi['prev'][$k] ?? null; $c = 2 * M_PI * 26;
        $delta = $prev !== null && $prev !== $v ? '<small class="' . ($v > $prev ? 'up' : 'down') . '">' . ($v > $prev ? '▲ ' : '▼ ') . abs($v - $prev) . '</small>' : '';
        $o .= '<div class="psi-ring ' . care_psi_rating($v) . '"><svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="26" class="trk"/><circle cx="32" cy="32" r="26" class="val" stroke-dasharray="' . round($c * $v / 100, 1) . ' ' . round($c, 1) . '"/></svg><b class="num">' . $v . '</b><span>' . e($n) . '</span>' . $delta . '</div>';
    }
    return $o . '</div>';
}

/* ---------- Web care reports ---------- */
function care_report_key(array &$s): string {
    if (($s['report_key'] ?? '') === '') { $s['report_key'] = bin2hex(random_bytes(16)); q('UPDATE sites SET report_key = ? WHERE id = ?', [$s['report_key'], $s['id']]); }
    return $s['report_key'];
}
function care_report_sig(array $s, string $month): string { return substr(hash_hmac('sha256', $s['id'] . '|' . $month, care_report_key($s)), 0, 32); }
function care_report_url(array $s, string $month): string { return abs_url('care-report.php?id=' . (int)$s['id'] . '&m=' . $month . '&k=' . care_report_sig($s, $month)); }
// Months with a report: from the month care started up to this month (this month is "so far").
function care_report_months(array $s, int $max = 12): array {
    $start = strtotime(date('Y-m-01', strtotime((string)($s['care_since'] ?: $s['created_at'] ?? 'now'))));
    $out = [];
    for ($t = strtotime(date('Y-m-01')); $t >= $start && count($out) < $max; $t = strtotime('-1 month', $t)) $out[] = date('Y-m', $t);
    return $out;
}
