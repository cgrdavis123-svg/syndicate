<?php
// Free website checkup: syndicatemarketing.co/checkup.html runs this on any business's website (shop-api.php?a=checkup).
// A one-off, automated look at the home page that is safe to run for strangers:
//   - public web addresses only: every host is resolved here, refused if any address is private or reserved, and pinned
//     for the request; redirects are followed by hand and checked again; ports 80 and 443 only; downloads are capped;
//   - no probing for exposed private files (/.env and friends). That stays in the care plan's weekly scan, for owners;
//   - one report per website for 6 hours (asking again shows the same one), plus per-visitor and daily limits.
// Results live in `checkups` (migration v25). The phone speed test (Google PageSpeed) is a second call because it's slow.
// Staff page: checkups.php. Outreach agent: outreach-api.php?a=checkup.
require_once __DIR__ . '/care.php';
require_once __DIR__ . '/shop.php';

const CK_UA = 'SyndicateCheckup/1.0 (free website checkup; +https://syndicatemarketing.co/checkup.html)';
const CK_REUSE = 6 * 3600;  // the same site asked again within this time gets the same report
const CK_DAILY = 300;       // fresh checks a day, everyone together
const CK_KEEP_DAYS = 180;   // reports nobody asked to have emailed are deleted after this
const CK_AREAS = ['security' => 'Security', 'search' => 'Google & search', 'phones' => 'Phones & speed', 'leads' => 'Leads & tracking', 'links' => 'Links', 'renewals' => 'Renewals'];

/* ---------- Addresses ---------- */
// What the visitor typed → ['host' => 'example.com'] or ['error' => '...'].
function ck_normalize(string $in): array {
    $in = trim($in);
    if ($in === '') return ['error' => 'Enter your website address, like yourbusiness.com.'];
    if (mb_strlen($in) > 300) return ['error' => 'That address is too long.'];
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $in)) $in = 'https://' . $in;
    $p = parse_url($in);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) return ['error' => 'That doesn’t look like a website address.'];
    $host = rtrim(strtolower($p['host']), '.');
    if (preg_match('/[^\x21-\x7e]/', $host)) {
        if (!function_exists('idn_to_ascii')) return ['error' => 'That doesn’t look like a website address.'];
        $host = (string)idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
    }
    if (isset($p['port']) && !in_array((int)$p['port'], [80, 443], true)) return ['error' => 'We can only check websites on the normal web ports.'];
    if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) || !preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $host))
        return ['error' => 'Enter a website address with a name, like yourbusiness.com.'];
    if (preg_match('/(^|\.)(localhost|local|localdomain|internal|intranet|lan|home|corp|example|test|invalid|onion|arpa)$/', $host)) return ['error' => 'That address isn’t on the public internet.'];
    return ['host' => $host];
}
function ck_key(string $host): string { return preg_replace('/^www\./', '', strtolower($host)); }

// Tests only (config "checkup_test_hosts", e.g. ['shop-a.com:80' => '127.0.0.1:8841']): connect those names to local servers.
function ck_test_map(): array { $m = config('checkup_test_hosts'); return is_array($m) ? $m : []; }
function ck_ip_public(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $n = ip2long($ip);
        foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['198.18.0.0', 15], ['192.88.99.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4]] as [$net, $bits]) // shared, protocol, benchmark, relay, multicast, reserved
            if (($n & (-1 << (32 - $bits))) === (ip2long($net) & (-1 << (32 - $bits)))) return false;
        return true;
    }
    $l = strtolower($ip);
    return !preg_match('/^(fc|fd|fe[89ab]|ff|::ffff:|64:ff9b:|2001:db8:|2002:)/', $l);
}
// The address to connect to: an IP, '' (not found) or '!' (points somewhere private). Every address the name has must be public.
function ck_ip(string $host): string {
    static $cache = [];
    if (isset($cache[$host])) return $cache[$host];
    $ips = gethostbynamel($host) ?: [];
    if (function_exists('dns_get_record')) foreach ((array)@dns_get_record($host, DNS_AAAA) as $r) if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
    if (!$ips) return $cache[$host] = '';
    foreach ($ips as $ip) if (!ck_ip_public($ip)) return $cache[$host] = '!';
    return $cache[$host] = $ips[0];
}
function ck_abs(string $loc, string $base): string {
    $loc = trim($loc);
    if (preg_match('#^https?://#i', $loc)) return $loc;
    $b = parse_url($base); $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (strpos($loc, '//') === 0) return $b['scheme'] . ':' . $loc;
    if (strpos($loc, '/') === 0) return $origin . $loc;
    $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
    return $origin . ($dir ?: '/') . $loc;
}

/* ---------- Fetching, safely ---------- */
// Several URLs at once. Each request: ['url', 'method' => 'GET'|'HEAD', 'max' => bytes, 'follow' => bool].
// Returns, in the same order: ['code', 'ms', 'err', 'body', 'headers' (lowercased), 'final'].
function ck_fetch(array $reqs, int $timeout = 15): array {
    $out = []; $todo = [];
    if (!function_exists('curl_multi_init')) {
        foreach ($reqs as $i => $r) $out[$i] = ['code' => 0, 'ms' => 0, 'err' => 'This server can’t make web requests (cURL is off).', 'body' => '', 'headers' => [], 'final' => $r['url']];
        return $out;
    }
    $map = ck_test_map();
    foreach ($reqs as $i => $r) $todo[$i] = $r + ['hops' => 0, 'start' => microtime(true)];
    while ($todo) {
        $mh = curl_multi_init(); $hs = []; $bufs = []; $hdrs = []; $full = [];
        foreach ($todo as $i => $r) {
            $p = parse_url($r['url']) ?: []; $host = strtolower((string)($p['host'] ?? '')); $scheme = strtolower((string)($p['scheme'] ?? ''));
            $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
            $fail = function (string $err) use (&$out, $i, $r) { $out[$i] = ['code' => 0, 'ms' => 0, 'err' => $err, 'body' => '', 'headers' => [], 'final' => $r['url']]; };
            if (!in_array($scheme, ['http', 'https'], true) || $host === '' || !in_array($port, [80, 443], true) || isset($p['user'])) { $fail('Address not allowed'); unset($todo[$i]); continue; }
            $pin = [];
            if (isset($map["$host:$port"])) $pin = [CURLOPT_CONNECT_TO => ["$host:$port:" . $map["$host:$port"]], CURLOPT_NOPROXY => '*'];
            elseif ($map && !isset($map["$host:" . ($port === 443 ? 80 : 443)])) { $fail('Couldn’t find that website'); unset($todo[$i]); continue; } // tests never reach the internet
            elseif (!$map) {
                $ip = ck_ip($host);
                if ($ip === '') { $fail('Couldn’t find that website'); unset($todo[$i]); continue; }
                if ($ip === '!') { $fail('Not a public web address'); unset($todo[$i]); continue; }
                $pin = [CURLOPT_RESOLVE => ["$host:$port:" . (strpos($ip, ':') !== false ? "[$ip]" : $ip)], CURLOPT_NOPROXY => '*'];
            } else { $fail('Couldn’t connect'); unset($todo[$i]); continue; }
            $ch = curl_init($r['url']);
            $bufs[$i] = ''; $hdrs[$i] = []; $full[$i] = false; $max = (int)($r['max'] ?? 1048576);
            curl_setopt_array($ch, $pin + [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_NOBODY => ($r['method'] ?? 'GET') === 'HEAD',
                CURLOPT_USERAGENT => CK_UA, CURLOPT_ENCODING => '', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.8'],
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$hdrs, $i) { if (preg_match('#^HTTP/#', $line)) $hdrs[$i] = []; elseif (strpos($line, ':')) { [$k, $v] = explode(':', $line, 2); $hdrs[$i][strtolower(trim($k))] = trim($v); } return strlen($line); },
                CURLOPT_WRITEFUNCTION => function ($ch, $d) use (&$bufs, &$full, $i, $max) { $room = $max - strlen($bufs[$i]); if ($room <= 0) { $full[$i] = true; return 0; } $bufs[$i] .= substr($d, 0, $room); return strlen($d); },
            ]);
            curl_multi_add_handle($mh, $ch); $hs[$i] = $ch;
        }
        do { $st = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1.0); } while ($running && $st === CURLM_OK);
        $codes = []; // curl_error() is empty under curl_multi; the result code per handle comes from here
        while ($info = curl_multi_info_read($mh)) $codes[is_object($info['handle']) ? spl_object_id($info['handle']) : (int)$info['handle']] = (int)$info['result'];
        $next = [];
        foreach ($hs as $i => $ch) {
            $r = $todo[$i]; $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $loc = (string)($hdrs[$i]['location'] ?? '');
            $res = ['code' => $code, 'ms' => (int)round((microtime(true) - $r['start']) * 1000), 'err' => $full[$i] ? '' : (curl_error($ch) ?: (($c = $codes[is_object($ch) ? spl_object_id($ch) : (int)$ch] ?? 0) ? curl_strerror($c) : '')), 'body' => $bufs[$i], 'headers' => $hdrs[$i], 'final' => $r['url']];
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
            if (($r['follow'] ?? true) && $code >= 300 && $code < 400 && $loc !== '') {
                if ($r['hops'] >= 5) { $res['err'] = 'Too many redirects'; $out[$i] = $res; continue; }
                $next[$i] = ['url' => ck_abs($loc, $r['url']), 'hops' => $r['hops'] + 1] + $r;
            } else $out[$i] = $res;
        }
        curl_multi_close($mh);
        $todo = $next;
    }
    ksort($out);
    return $out;
}

// The certificate's dates, read from the pinned address. ['expires' => ts, 'issuer' => '...'] or [].
function ck_ssl(string $host): array {
    $map = ck_test_map();
    if ($map) { $t = $map["$host:443"] ?? ''; if ($t === '') return []; [$ip, $port] = explode(':', $t) + [1 => 443]; }
    else { $ip = ck_ip($host); if ($ip === '' || $ip === '!') return []; $port = 443; }
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client('ssl://' . (strpos($ip, ':') !== false ? "[$ip]" : $ip) . ':' . (int)$port, $no, $msg, 8, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [];
    $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
    fclose($fp);
    $info = $cert ? openssl_x509_parse($cert) : null;
    return $info ? ['expires' => (int)$info['validTo_time_t'], 'issuer' => (string)($info['issuer']['O'] ?? $info['issuer']['CN'] ?? '')] : [];
}
// When the domain is paid up to, from RDAP. ['expires' => ts, 'registrar' => '...'] or [].
function ck_domain(string $host): array {
    $root = care_root_domain($host);
    $base = rtrim((string)(config('checkup_rdap') ?: 'https://rdap.org/domain/'), '/') . '/';
    $r = ck_fetch([['url' => $base . rawurlencode($root), 'max' => 262144]], 12)[0];
    $d = json_decode($r['body'], true);
    if (!is_array($d)) return [];
    $exp = null; $reg = '';
    foreach ((array)($d['events'] ?? []) as $ev) if (($ev['eventAction'] ?? '') === 'expiration' && !empty($ev['eventDate'])) $exp = strtotime($ev['eventDate']) ?: null;
    foreach ((array)($d['entities'] ?? []) as $en) if (in_array('registrar', (array)($en['roles'] ?? []), true))
        foreach ((array)($en['vcardArray'][1] ?? []) as $vc) if (($vc[0] ?? '') === 'fn') $reg = mb_substr((string)$vc[3], 0, 80);
    return $exp ? ['expires' => $exp, 'registrar' => $reg, 'domain' => $root] : [];
}

/* ---------- The scan ---------- */
const CK_ANALYTICS = ['googletagmanager.com' => 'Google tag', 'google-analytics.com' => 'Google Analytics', 'gtag(' => 'Google Analytics', 'crm.stusyn.com/a.js' => 'Site Stats',
    'plausible.io' => 'Plausible', 'usefathom.com' => 'Fathom', 'matomo' => 'Matomo', 'umami' => 'Umami', 'clarity.ms' => 'Microsoft Clarity', 'connect.facebook.net' => 'Meta Pixel',
    'static.hotjar.com' => 'Hotjar', 'analytics.tiktok.com' => 'TikTok Pixel', 'shopify.com/s/trekkie' => 'Shopify analytics', 'squarespace.com/universal/scripts' => 'Squarespace analytics', 'stats.wp.com' => 'Jetpack Stats', 'cloudflareinsights.com' => 'Cloudflare analytics'];
const CK_PLATFORMS = ['/wp-content/' => 'WordPress', 'cdn.shopify.com' => 'Shopify', 'static.squarespace.com' => 'Squarespace', 'squarespace-cdn.com' => 'Squarespace', 'wixstatic.com' => 'Wix',
    'website-files.com' => 'Webflow', 'img1.wsimg.com' => 'GoDaddy Website Builder', 'weebly.com' => 'Weebly', 'framerusercontent.com' => 'Framer', 'carrd.co' => 'Carrd', 'duda' => 'Duda'];

function ck_scan(string $host): array {
    $tries = [$host]; if (strpos($host, 'www.') !== 0) $tries[] = 'www.' . $host;
    $home = null; $base = '';
    foreach ($tries as $h) {
        foreach (['https', 'http'] as $sch) {
            $res = ck_fetch([['url' => "$sch://$h/", 'max' => 2097152]], 20)[0];
            if ($sch === 'https' && $res['code'] === 0) $sslErr = preg_match('/SSL|certificate|TLS/i', $res['err']) ? $res['err'] : ($sslErr ?? '');
            if ($res['code'] >= 300 && $res['code'] < 400) return ['ok' => false, 'error' => $tries[0] . ' keeps redirecting without reaching a page, so there’s nothing to check. That’s worth fixing: visitors see an error too.'];
            if ($res['code'] > 0) { $home = $res; $base = "$sch://$h"; $host = $h; break 2; }
        }
    }
    if (!$home) return ['ok' => false, 'error' => 'We couldn’t reach ' . $tries[0] . '. Check the address is right, or try again in a minute.'];
    if ($home['code'] >= 400) return ['ok' => false, 'error' => $tries[0] . ' answered with an error (' . $home['code'] . '), so there’s no home page to check. If the site is new, try again once it’s live.'];
    $final = $home['final']; $fp = parse_url($final); $fhost = strtolower((string)($fp['host'] ?? $host));
    $fbase = ($fp['scheme'] ?? 'https') . '://' . $fhost . (isset($fp['port']) ? ':' . $fp['port'] : '');
    $body = $home['body']; $h = $home['headers'];
    [$plain, $map, $robots] = ck_fetch([['url' => 'http://' . $fhost . '/', 'follow' => false, 'max' => 4096], ['url' => $fbase . '/sitemap.xml', 'max' => 1048576], ['url' => $fbase . '/robots.txt', 'max' => 65536]], 12);
    $s = ['ok' => true, 'host' => $fhost, 'url' => $final, 'code' => $home['code'], 'ms' => $home['ms'], 'kb' => (int)round(strlen($body) / 1024)];
    $s['https'] = stripos($final, 'https://') === 0;
    $s['ssl_error'] = !$s['https'] && !empty($sslErr) ? mb_substr($sslErr, 0, 160) : '';
    $loc = (string)($plain['headers']['location'] ?? '');
    $s['redirect'] = $plain['code'] >= 300 && $plain['code'] < 400 && stripos($loc, 'https://') === 0;
    $s['headers'] = ['hsts' => isset($h['strict-transport-security']), 'nosniff' => stripos((string)($h['x-content-type-options'] ?? ''), 'nosniff') !== false,
        'frames' => isset($h['x-frame-options']) || stripos((string)($h['content-security-policy'] ?? ''), 'frame-ancestors') !== false, 'csp' => isset($h['content-security-policy']), 'referrer' => isset($h['referrer-policy'])];
    $low = strtolower($body);
    $s['platform'] = preg_match('#<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']{1,60})#i', $body, $m) ? html_entity_decode($m[1]) : '';
    if ($s['platform'] === '') foreach (CK_PLATFORMS as $needle => $name) if (strpos($low, $needle) !== false) { $s['platform'] = $name; break; }
    $s['title'] = preg_match('#<title[^>]*>(.*?)</title>#is', $body, $m) ? mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES))), 0, 200) : '';
    $s['description'] = preg_match('#<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)#i', $body, $m) || preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']#i', $body, $m) ? mb_substr(trim(html_entity_decode($m[1], ENT_QUOTES)), 0, 400) : '';
    $s['viewport'] = (bool)preg_match('#<meta[^>]+name=["\']viewport["\']#i', $body);
    $s['h1'] = preg_match_all('#<h1[\s>]#i', $body);
    $s['og_image'] = (bool)preg_match('#<meta[^>]+(?:property|name)=["\'](?:og:image|twitter:image)["\']#i', $body);
    $s['schema'] = [];
    if (preg_match_all('#"@type"\s*:\s*"([A-Za-z]+)"#', $body, $mm)) $s['schema'] = array_slice(array_values(array_unique($mm[1])), 0, 8);
    $s['noindex'] = (bool)preg_match('#<meta[^>]+name=["\']robots["\'][^>]+content=["\'][^"\']*noindex#i', $body) || stripos((string)($h['x-robots-tag'] ?? ''), 'noindex') !== false;
    $s['robots_block'] = $robots['code'] === 200 && ck_robots_blocks_all($robots['body']);
    $s['sitemap'] = $map['code'] === 200 && preg_match('#<(urlset|sitemapindex)\b#i', $map['body']);
    if (!$s['sitemap'] && $robots['code'] === 200 && preg_match('#^\s*sitemap:\s*\S+#im', $robots['body'])) $s['sitemap'] = true;
    $s['analytics'] = [];
    foreach (CK_ANALYTICS as $needle => $name) if (strpos($low, $needle) !== false && !in_array($name, $s['analytics'], true)) $s['analytics'][] = $name;
    $s['tel'] = (bool)preg_match('#href=["\']tel:#i', $body);
    $s['mailto'] = (bool)preg_match('#href=["\']mailto:#i', $body);
    $s['form'] = (bool)preg_match('#<form\b#i', $body);
    $imgs = preg_match_all('#<img\b[^>]*>#i', $body, $mm) ? $mm[0] : [];
    $s['images'] = count($imgs);
    $s['no_alt'] = count(array_filter($imgs, function ($t) { return !preg_match('#\balt\s*=#i', $t); }));
    $s['mixed'] = [];
    if ($s['https'] && preg_match_all('#<(?:img|script|iframe|link|source|video|audio)\b[^>]+(?:src|href)=["\'](http://[^"\']+)#i', $body, $mm)) $s['mixed'] = array_slice(array_values(array_unique($mm[1])), 0, 10);
    // Broken links: links on the home page to the same site (up to 40).
    $same = function ($u) use ($fhost) { $uh = strtolower((string)parse_url($u, PHP_URL_HOST)); return $uh === $fhost || $uh === 'www.' . $fhost || 'www.' . $uh === $fhost; };
    $urls = [];
    if (preg_match_all('#<a\b[^>]+href=["\']([^"\'\#]+)#i', $body, $mm)) foreach ($mm[1] as $u) {
        $u = html_entity_decode(trim($u), ENT_QUOTES);
        if ($u === '' || preg_match('#^(mailto|tel|javascript|data|sms|whatsapp):#i', $u)) continue;
        $u = ck_abs($u, $final);
        if ($same($u) && rtrim($u, '/') !== rtrim($final, '/')) $urls[$u] = true;
    }
    $urls = array_slice(array_keys($urls), 0, 40);
    $s['links'] = count($urls); $s['broken'] = [];
    if ($urls) {
        $r = ck_fetch(array_map(function ($u) { return ['url' => $u, 'max' => 4096]; }, $urls), 12);
        foreach ($urls as $i => $u) if ($r[$i]['code'] >= 400 && $r[$i]['code'] !== 429 && $r[$i]['code'] !== 403 || ($r[$i]['code'] === 0 && $r[$i]['err'] !== 'Too many redirects' && $r[$i]['err'] !== ''))
            $s['broken'][] = ['url' => mb_substr($u, 0, 300), 'code' => $r[$i]['code']];
    }
    $s['ssl'] = $s['https'] || $s['ssl_error'] !== '' ? ck_ssl($fhost) : [];
    $s['domain'] = ck_domain($fhost);
    return $s;
}
function ck_robots_blocks_all(string $txt): bool {
    $agents = []; $inGroup = false; $block = false; $lastWasAgent = false;
    foreach (preg_split('/\R/', $txt) as $line) {
        $line = trim(preg_replace('/#.*$/', '', $line));
        if ($line === '' || strpos($line, ':') === false) continue;
        [$k, $v] = array_map('trim', explode(':', $line, 2)); $k = strtolower($k);
        if ($k === 'user-agent') { if (!$lastWasAgent) $agents = []; $agents[] = $v; $lastWasAgent = true; continue; }
        $lastWasAgent = false;
        if (in_array('*', $agents, true)) { if ($k === 'disallow' && $v === '/') $block = true; if ($k === 'allow' && $v === '/') return false; }
    }
    return $block;
}

/* ---------- Plain-language findings ---------- */
// Each: ['id', 'area', 'level' good|warn|bad, 'title', 'detail', 'fix' (a SKU, or '')].
function ck_findings(array $s, array $psi = []): array {
    $f = []; $add = function ($id, $area, $level, $title, $detail = '', $fix = '') use (&$f) { $f[] = compact('id', 'area', 'level', 'title', 'detail', 'fix'); };
    $days = function ($ts) { return (int)floor(($ts - time()) / 86400); };
    // Security
    if ($s['ssl_error'] !== '') $add('ssl', 'security', 'bad', 'The security certificate has a problem', 'Browsers show a warning instead of the site when the certificate is wrong or out of date, and most visitors leave.', 'care-plan');
    elseif (!$s['https']) $add('https', 'security', 'bad', 'No secure connection (HTTPS)', 'Browsers label the site “Not secure”, and Google prefers secure sites. A free certificate fixes this on almost any host.', 'care-plan');
    elseif (!$s['redirect']) $add('https', 'security', 'warn', 'Secure, but plain http:// visits aren’t sent to it', 'Someone who types the address without https can land on the insecure version. One redirect rule fixes it.', 'care-plan');
    else $add('https', 'security', 'good', 'Secure connection (HTTPS) on every visit');
    if (!empty($s['ssl']['expires'])) {
        $d = $days($s['ssl']['expires']); $when = date('F j, Y', $s['ssl']['expires']);
        if ($d < 0) $add('cert', 'security', 'bad', 'The security certificate has expired', "It ran out on $when.", 'care-plan');
        elseif ($d <= 14) $add('cert', 'security', 'warn', "The security certificate runs out in $d day" . ($d === 1 ? '' : 's'), "On $when. Most renew on their own; if this one doesn’t, the site will show a warning.", 'care-plan');
        else $add('cert', 'security', 'good', 'Security certificate good until ' . $when);
    }
    $hd = $s['headers']; $have = count(array_filter($hd)); $tot = count($hd);
    $add('headers', 'security', $have >= 4 ? 'good' : 'warn', $have >= 4 ? "Browser security settings in place ($have of $tot)" : "Browser security settings: $have of $tot in place", $have >= 4 ? '' : 'These are small server settings that stop other sites framing yours or browsers guessing file types. Nothing visitors see, but cheap protection.', $have >= 4 ? '' : 'care-plan');
    if ($s['mixed']) $add('mixed', 'security', 'warn', count($s['mixed']) . ' item' . (count($s['mixed']) === 1 ? '' : 's') . ' loading over an insecure connection', 'Images or scripts loaded with http:// can break the padlock in some browsers.', 'care-plan');
    // Google & search
    if ($s['noindex']) $add('noindex', 'search', 'bad', 'Your home page tells Google not to list it', 'It has a “noindex” setting, often left on by accident after a site launches. While it’s there, the page won’t show up in Google.', 'svc-site-audit');
    if ($s['robots_block']) $add('robots', 'search', 'bad', 'Your robots.txt file blocks search engines from the whole site', 'This is usually a leftover from building the site. It keeps Google from reading any page.', 'svc-site-audit');
    $tl = mb_strlen($s['title']);
    if ($tl === 0) $add('title', 'search', 'bad', 'The home page has no title', 'The title is the blue link people click in Google. Without one, Google makes one up.', 'svc-site-audit');
    elseif ($tl < 15 || $tl > 65) $add('title', 'search', 'warn', 'The page title is ' . ($tl < 15 ? 'very short' : 'long, so Google will cut it off'), '“' . mb_substr($s['title'], 0, 80) . ($tl > 80 ? '…' : '') . '”. Aim for 30–60 characters with what you do and where.', 'svc-site-audit');
    else $add('title', 'search', 'good', 'Good page title', '“' . $s['title'] . '”');
    $dl = mb_strlen($s['description']);
    if ($dl < 50) $add('desc', 'search', 'warn', $dl ? 'The search description is very short' : 'No search description', 'This is the gray text under your link in Google. Without a good one, Google grabs random text from the page.', 'svc-site-audit');
    else $add('desc', 'search', 'good', 'Has a search description');
    if ($s['h1'] === 0) $add('h1', 'search', 'warn', 'No main heading (H1) on the home page', 'A clear main heading tells visitors and Google what the page is about.', 'svc-site-audit');
    else $add('h1', 'search', 'good', 'Has a main heading');
    $add('sitemap', 'search', $s['sitemap'] ? 'good' : 'warn', $s['sitemap'] ? 'Has a sitemap for Google' : 'No sitemap found', $s['sitemap'] ? '' : 'A sitemap lists your pages so Google finds them all.', $s['sitemap'] ? '' : 'svc-site-audit');
    $biz = array_intersect($s['schema'], ['LocalBusiness', 'Organization', 'Store', 'Restaurant', 'Dentist', 'HomeAndConstructionBusiness', 'ProfessionalService', 'VideoGame', 'Corporation', 'HVACBusiness', 'Plumber', 'Electrician', 'RoofingContractor', 'AutoRepair', 'MedicalBusiness', 'LegalService', 'FinancialService', 'BeautySalon', 'HealthAndBeautyBusiness', 'SportsActivityLocation', 'EntertainmentBusiness']);
    $add('schema', 'search', $biz ? 'good' : 'warn', $biz ? 'Business details marked up for Google' : 'No business details marked up for Google', $biz ? '' : 'A small block of code (schema) tells Google your business name, hours and location, which can help you show up in local results.', $biz ? '' : 'svc-site-audit');
    $add('share', 'search', $s['og_image'] ? 'good' : 'warn', $s['og_image'] ? 'Shows a picture when the link is shared' : 'No picture when the link is shared', $s['og_image'] ? '' : 'Links to the site in texts, Facebook or Discord show a blank box instead of an image.', $s['og_image'] ? '' : 'svc-site-audit');
    // Phones & speed
    if (!$s['viewport']) $add('viewport', 'phones', 'bad', 'Not set up for phones', 'The page has no mobile setting, so phones show a tiny desktop page. Most local searches happen on phones.', 'web-starter');
    else $add('viewport', 'phones', 'good', 'Set up for phones');
    $sec = round($s['ms'] / 1000, 1);
    if ($s['ms'] > 6000) $add('ttfb', 'phones', 'bad', "The page took {$sec} seconds to arrive", 'Measured from our server. Over a few seconds and many visitors give up before anything shows.', 'svc-site-audit');
    elseif ($s['ms'] > 2500) $add('ttfb', 'phones', 'warn', "The page took {$sec} seconds to arrive", 'Measured from our server. A well-tuned page arrives in under a second or two.', 'svc-site-audit');
    else $add('ttfb', 'phones', 'good', 'The page arrived quickly (' . ($s['ms'] < 1000 ? 'under a second' : "$sec seconds") . ')');
    if ($s['images'] >= 3 && $s['no_alt'] > 0) $add('alt', 'phones', 'warn', $s['no_alt'] . ' of ' . $s['images'] . ' images have no description', 'Image descriptions (alt text) are read aloud to blind visitors and help Google understand the picture.', 'svc-site-audit');
    elseif ($s['images'] >= 3) $add('alt', 'phones', 'good', 'Images have descriptions');
    if (!empty($psi['scores'])) {
        $p = (int)($psi['scores']['performance'] ?? 0);
        $add('psi', 'phones', $p >= 90 ? 'good' : ($p >= 50 ? 'warn' : 'bad'), "Google’s phone speed score: $p out of 100", $p >= 90 ? '' : 'Google tests the page on a mid-range phone. Slow pages lose visitors and rank lower. The top fixes are listed above.', $p >= 90 ? '' : ($p >= 50 ? 'care-plus' : 'svc-site-audit'));
    }
    // Leads & tracking
    $ways = array_keys(array_filter(['a contact form' => $s['form'], 'a tap-to-call link' => $s['tel'], 'an email link' => $s['mailto']]));
    if (!$ways) $add('contact', 'leads', 'warn', 'No easy way to get in touch on the home page', 'We didn’t see a form, a tap-to-call number or an email link. Visitors who are ready to buy shouldn’t have to hunt.', 'web-starter');
    else $add('contact', 'leads', 'good', 'Easy to get in touch', 'Found ' . implode(', ', $ways) . '.');
    if (!$s['analytics']) $add('analytics', 'leads', 'warn', 'We didn’t see any visitor tracking', 'Without it you can’t tell how many people visit, where they come from, or what they do. (Some trackers load in ways we can’t see.)', 'svc-analytics');
    else $add('analytics', 'leads', 'good', 'Visitor tracking found', implode(', ', $s['analytics']) . '.');
    // Links
    $nb = count($s['broken']);
    if ($nb) $add('broken', 'links', $nb > 5 ? 'bad' : 'warn', "$nb broken link" . ($nb === 1 ? '' : 's') . ' on the home page', 'Of ' . $s['links'] . ' links to your own pages we checked. Broken links frustrate visitors and look neglected.', 'care-plan');
    elseif ($s['links']) $add('broken', 'links', 'good', 'No broken links', $s['links'] . ' links to your own pages checked.');
    // Renewals
    if (!empty($s['domain']['expires'])) {
        $d = $days($s['domain']['expires']); $when = date('F j, Y', $s['domain']['expires']);
        if ($d < 0) $add('domain', 'renewals', 'bad', 'The domain registration looks expired', "Listed as ending $when. If it lapses, the website and email stop working.", 'care-plan');
        elseif ($d <= 30) $add('domain', 'renewals', 'bad', "{$s['domain']['domain']} is up for renewal in $d day" . ($d === 1 ? '' : 's'), "On $when" . ($s['domain']['registrar'] ? ' at ' . $s['domain']['registrar'] : '') . '. If auto-renew is off, the website and email stop working when it lapses.', 'care-plan');
        elseif ($d <= 60) $add('domain', 'renewals', 'warn', "{$s['domain']['domain']} is up for renewal on $when", 'Worth checking auto-renew is on' . ($s['domain']['registrar'] ? ' at ' . $s['domain']['registrar'] : '') . '.', 'care-plan');
        else $add('domain', 'renewals', 'good', "Domain paid up until $when");
    }
    return $f;
}
// 0–100 from the findings: good counts fully, warnings half, problems nothing; the most serious checks count double.
function ck_score(array $findings): int {
    $heavy = ['https', 'ssl', 'cert', 'noindex', 'robots', 'viewport', 'domain', 'psi', 'title'];
    $got = 0; $tot = 0;
    foreach ($findings as $x) { $w = in_array($x['id'], $heavy, true) ? 2 : 1; $tot += $w; $got += $w * ['good' => 1, 'warn' => 0.5, 'bad' => 0][$x['level']]; }
    return $tot ? (int)round(100 * $got / $tot) : 0;
}
// What would fix the problems, most useful first: [['sku', 'count']].
function ck_fixes(array $findings): array {
    $n = [];
    foreach ($findings as $x) if ($x['level'] !== 'good' && $x['fix'] !== '') $n[$x['fix']] = ($n[$x['fix']] ?? 0) + ($x['level'] === 'bad' ? 2 : 1);
    if (isset($n['web-starter'])) $n['web-starter'] += 20; // a site that isn't set up for phones or for leads needs rebuilding more than tuning
    arsort($n);
    return array_map(function ($sku, $c) { return ['sku' => $sku, 'weight' => $c]; }, array_keys($n), $n);
}

/* ---------- Running and storing ---------- */
function ck_row(string $token): ?array { return $token !== '' && preg_match('/^[a-f0-9]{32}$/', $token) ? one('SELECT * FROM checkups WHERE token = ?', [$token]) : null; }
function ck_report_url(array $row, string $extra = ''): string { return (shop_site_url() ?: 'https://syndicatemarketing.co') . '/checkup.html?r=' . $row['token'] . $extra; }
// Runs a checkup (or finds one from the last few hours). $via: site | agent | staff. → ['ok', 'token'] or ['ok' => false, 'error'].
function ck_run(string $input, string $via = 'site', string $label = ''): array {
    $n = ck_normalize($input);
    if (isset($n['error'])) return ['ok' => false, 'error' => $n['error']];
    $key = ck_key($n['host']);
    // Reused only within the same source, so a report the agent sent someone is never one a stranger ran (its views mean something).
    $old = one("SELECT * FROM checkups WHERE host = ? AND via = ? AND created_at > ? AND result != '' ORDER BY id DESC LIMIT 1", [$key, $via, time() - CK_REUSE]);
    if ($old) {
        if ($label !== '' && $old['label'] === '') q('UPDATE checkups SET label = ? WHERE id = ?', [mb_substr($label, 0, 150), $old['id']]);
        return ['ok' => true, 'token' => $old['token'], 'reused' => true];
    }
    if ((int)val('SELECT COUNT(*) FROM checkups WHERE created_at > ?', [time() - 86400]) >= CK_DAILY) return ['ok' => false, 'error' => 'We’ve run a lot of checkups today. Please try again tomorrow, or email ' . setting('business_email') . ' and we’ll look for you.'];
    @set_time_limit(120);
    $s = ck_scan($n['host']);
    if (empty($s['ok'])) return ['ok' => false, 'error' => $s['error'] ?? 'We couldn’t check that website.'];
    $f = ck_findings($s);
    $token = bin2hex(random_bytes(16));
    q('INSERT INTO checkups (token, host, url, via, label, score, result, created_at) VALUES (?,?,?,?,?,?,?,?)',
        [$token, $key, $s['url'], $via, mb_substr($label, 0, 150), ck_score($f), json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), time()]);
    return ['ok' => true, 'token' => $token, 'reused' => false];
}
// The phone speed test, run once per report (it takes 10–60 seconds). → the row, updated.
function ck_speed(array $row): array {
    $psi = json_decode($row['psi'] ?: '', true);
    if (is_array($psi) && (!empty($psi['scores']) || (!empty($psi['error']) && (int)$psi['at'] > time() - 600))) return $row;
    if ((int)$row['psi_started'] > time() - 100) return $row; // already running for someone else
    q('UPDATE checkups SET psi_started = ? WHERE id = ?', [time(), $row['id']]);
    @set_time_limit(120);
    $r = care_fetch([['url' => care_psi_endpoint($row['url']), 'max' => 12582912]], 90)[0];
    $p = care_psi_parse($r['body']);
    $s = json_decode($row['result'], true) ?: [];
    $score = empty($p['scores']) ? (int)$row['score'] : ck_score(ck_findings($s, $p));
    q('UPDATE checkups SET psi = ?, psi_started = NULL, score = ? WHERE id = ?', [json_encode($p, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $score, $row['id']]);
    return one('SELECT * FROM checkups WHERE id = ?', [$row['id']]);
}
// What the report page shows. Nothing personal: no names, emails or who ran it.
function ck_public(array $row): array {
    $s = json_decode($row['result'], true) ?: [];
    $psi = json_decode($row['psi'] ?: '', true) ?: [];
    $f = ck_findings($s, $psi);
    $state = !empty($psi['scores']) ? 'done' : (!empty($psi['error']) ? 'error' : ((int)$row['psi_started'] > time() - 100 ? 'running' : 'waiting'));
    return ['ok' => true, 'token' => $row['token'], 'host' => $s['host'] ?? $row['host'], 'url' => $row['url'], 'checked' => date('F j, Y \a\t g:i a', (int)$row['created_at']), 'checked_ts' => (int)$row['created_at'],
        'score' => ck_score($f), 'platform' => $s['platform'] ?? '', 'areas' => CK_AREAS, 'findings' => $f, 'fixes' => ck_fixes($f),
        'counts' => ['good' => count(array_filter($f, function ($x) { return $x['level'] === 'good'; })), 'warn' => count(array_filter($f, function ($x) { return $x['level'] === 'warn'; })), 'bad' => count(array_filter($f, function ($x) { return $x['level'] === 'bad'; }))],
        'broken' => array_slice($s['broken'] ?? [], 0, 10), 'speed' => ['state' => $state, 'scores' => $psi['scores'] ?? [], 'metrics' => $psi['metrics'] ?? [], 'fixes' => $psi['fixes'] ?? [], 'labels' => CARE_PSI_CATS, 'metric_labels' => CARE_PSI_METRICS],
        'emailed' => (bool)$row['contact_id']];
}
// Counts a view; the first view of a report the outreach agent sent someone becomes a task, because it's a warm moment.
function ck_viewed(array $row): void {
    q('UPDATE checkups SET views = views + 1, viewed_at = ? WHERE id = ?', [time(), $row['id']]);
    if ((int)$row['views'] === 0 && $row['via'] === 'agent') {
        $who = $row['label'] !== '' ? $row['label'] : $row['host'];
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [$who . ' opened the website checkup we sent (' . $row['host'] . ', score ' . (int)$row['score'] . ')', today(), 1, $row['deal_id'] ? 'deal' : '', $row['deal_id'] ?: null, now()]);
    }
}
// The report as plain text, for emails and the CRM timeline.
function ck_summary_text(array $pub): string {
    $t = 'Website checkup for ' . $pub['host'] . ' — score ' . $pub['score'] . "/100\n";
    $bad = array_filter($pub['findings'], function ($x) { return $x['level'] !== 'good'; });
    usort($bad, function ($a, $b) { return ($a['level'] === 'bad' ? 0 : 1) <=> ($b['level'] === 'bad' ? 0 : 1); });
    if ($bad) { $t .= "\nWorth fixing:\n"; foreach ($bad as $x) $t .= '  ' . ($x['level'] === 'bad' ? '✕ ' : '! ') . $x['title'] . "\n"; }
    $t .= "\nLooking good: " . $pub['counts']['good'] . ' check' . ($pub['counts']['good'] === 1 ? '' : 's') . "\n";
    if (!empty($pub['speed']['scores'])) $t .= 'Google phone speed score: ' . (int)($pub['speed']['scores']['performance'] ?? 0) . "/100\n";
    return $t;
}

/* ---------- “Email me the report” ---------- */
function ck_lead(array $in): array {
    $s = function ($k, $max = 200) use ($in) { return mb_substr(trim((string)($in[$k] ?? '')), 0, $max); };
    $row = ck_row($s('r', 40));
    if (!$row) return ['ok' => false, 'error' => 'That report has expired. Run the checkup again and we’ll email the new one.'];
    $name = $s('name', 120); $email = strtolower($s('email', 190)); $biz = $s('business', 150);
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Enter your name.', 'field' => 'name'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.', 'field' => 'email'];
    $pub = ck_public($row); $host = $pub['host'];
    $contact = find_or_create_contact($name, $email, $biz !== '' ? $biz : $host);
    if ($contact['company_id']) q("UPDATE companies SET website = ? WHERE id = ? AND website = ''", ['https://' . $host, $contact['company_id']]);
    $summary = ck_summary_text($pub);
    $deal = one("SELECT id FROM deals WHERE contact_id = ? AND source = 'Marketing site — checkup' AND title LIKE ? AND stage NOT IN ('won','lost')", [$contact['id'], '%' . $host]);
    if ($deal) $dealId = (int)$deal['id'];
    else {
        q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?,?, 'new', 0, 'Marketing site — checkup', ?, ?, ?)",
            ['Website checkup — ' . $host, $contact['company_id'], $contact['id'], SHOP_BRAND, 'Ran the free website checkup and asked for the report by email.', now(), now()]);
        $dealId = (int)db()->lastInsertId();
        if (($rc = (string)($in['ref'] ?? '')) !== '') {
            require_once __DIR__ . '/referrals.php';
            if (ref_attach($rc, $contact, 'link', 'website checkup') !== null) q("UPDATE deals SET source = 'Referral' WHERE id = ?", [$dealId]);
        }
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Follow up on the website checkup for ' . $host . ' (' . person_name($contact) . ', score ' . $pub['score'] . ')', today(), 'deal', $dealId, now()]);
    }
    q('UPDATE checkups SET contact_id = ?, deal_id = ? WHERE id = ?', [$contact['id'], $dealId, $row['id']]);
    $log = $summary . "\nReport: " . ck_report_url($row);
    log_activity('deal', $dealId, 'note', $log);
    log_activity('contact', (int)$contact['id'], 'note', $log);
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') send_mail($to, '[' . SHOP_BRAND . '] Website checkup — ' . $host . ' (' . $pub['score'] . '/100)', "$name ($email" . ($biz ? ", $biz" : '') . ") asked for their checkup report.\n\n$log\n\nIn the CRM: " . abs_url('deal.php?id=' . $dealId) . "\n", $email, SHOP_BRAND);
    // The email holds only our own words and the site they checked, and goes to each address at most once a day.
    $rk = hash('sha256', 'checkup-mail|' . $email);
    if (!val('SELECT 1 FROM attempts WHERE kind = ? AND ip_hash = ? AND created_at > ?', ['checkup_mail', $rk, time() - 86400])) {
        q('INSERT INTO attempts (kind, ip_hash, created_at) VALUES (?,?,?)', ['checkup_mail', $rk, time()]);
        $first = explode(' ', $name)[0];
        send_mail($email, 'Your website checkup for ' . $host, "Hi $first,\n\nHere’s the free checkup you ran on " . SHOP_BRAND . "’s website.\n\n$summary\nThe full report, with what each item means and how to fix it:\n" . ck_report_url($row, '&utm_source=checkup-email') .
            "\n\nWant a second pair of eyes? Reply to this email or book a free 20-minute call, and we’ll go through it with you. No pressure either way:\n" . abs_url('book.php?type=discovery-call') .
            "\n\nIf you didn’t ask for this, you can ignore it.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
    }
    return ['ok' => true];
}

/* ---------- Cleanup (cron) ---------- */
function ck_cleanup(): void {
    q('DELETE FROM checkups WHERE contact_id IS NULL AND deal_id IS NULL AND created_at < ?', [time() - CK_KEEP_DAYS * 86400]);
}
