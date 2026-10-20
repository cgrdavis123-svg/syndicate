<?php
// Site stats: the collector behind a.js, and the reports shown in the CRM and the client portal.
//
// Privacy by design, the way Plausible and Fathom work: no cookies, nothing stored on the visitor's device,
// and no IP addresses saved. A visitor is counted with a one-way hash of (daily random salt + site + IP +
// browser), and the salt is deleted after a day, so nobody can link visits across days or back to a person.
// Events live in their own database (storage/analytics.sqlite) so busy sites never slow the CRM down.

const AN_SESSION_GAP = 1800;          // 30 minutes without activity ends a visit
const AN_DIMENSIONS = ['page' => 'Page', 'entry' => 'Entry page', 'channel' => 'Channel', 'source' => 'Source', 'campaign' => 'Campaign', 'medium' => 'Medium',
    'country' => 'Country', 'region' => 'State / region', 'city' => 'City', 'device' => 'Device', 'browser' => 'Browser', 'os' => 'Operating system', 'goal' => 'Goal'];
const AN_RANGES = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'lastmonth' => 'Last month', '90d' => 'Last 90 days', '12m' => 'Last 12 months'];

function an_db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DATA . '/analytics.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout = 4000; PRAGMA journal_mode = WAL; PRAGMA synchronous = NORMAL;');
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() < 1) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL, ts INTEGER NOT NULL, day TEXT NOT NULL, hour INTEGER NOT NULL,
                type TEXT NOT NULL, pid TEXT NOT NULL DEFAULT '', visitor TEXT NOT NULL, session TEXT NOT NULL, entry INTEGER NOT NULL DEFAULT 0,
                name TEXT NOT NULL DEFAULT '', path TEXT NOT NULL DEFAULT '', title TEXT NOT NULL DEFAULT '', hostname TEXT NOT NULL DEFAULT '',
                referrer TEXT NOT NULL DEFAULT '', source TEXT NOT NULL DEFAULT '', channel TEXT NOT NULL DEFAULT '', medium TEXT NOT NULL DEFAULT '',
                campaign TEXT NOT NULL DEFAULT '', content TEXT NOT NULL DEFAULT '', term TEXT NOT NULL DEFAULT '',
                device TEXT NOT NULL DEFAULT '', browser TEXT NOT NULL DEFAULT '', os TEXT NOT NULL DEFAULT '', screen INTEGER NOT NULL DEFAULT 0, lang TEXT NOT NULL DEFAULT '',
                country TEXT NOT NULL DEFAULT '', region TEXT NOT NULL DEFAULT '', city TEXT NOT NULL DEFAULT '',
                duration INTEGER NOT NULL DEFAULT 0, scroll INTEGER NOT NULL DEFAULT 0, lcp INTEGER, fcp INTEGER, cls INTEGER, inp INTEGER, ttfb INTEGER, props TEXT NOT NULL DEFAULT '');
            CREATE INDEX IF NOT EXISTS ev_site_ts ON events(site_id, ts);
            CREATE INDEX IF NOT EXISTS ev_visitor ON events(site_id, visitor, ts);
            CREATE INDEX IF NOT EXISTS ev_session ON events(session);
            CREATE INDEX IF NOT EXISTS ev_pid ON events(pid);
            CREATE TABLE IF NOT EXISTS salts (day TEXT PRIMARY KEY, salt TEXT NOT NULL);
            PRAGMA user_version = 1;
        ");
    }
    return $pdo;
}
function aq(string $sql, array $a = []): PDOStatement { $st = an_db()->prepare($sql); $st->execute($a); return $st; }
function aall(string $sql, array $a = []): array { return aq($sql, $a)->fetchAll(); }
function aone(string $sql, array $a = []) { $r = aq($sql, $a)->fetch(); return $r ?: null; }
function aval(string $sql, array $a = []) { return aq($sql, $a)->fetchColumn(); }

function site_key(): string { return substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12); }
function site_snippet(array $s): string { return '<script defer src="' . abs_url('a.js') . '" data-site="' . $s['public_id'] . '"></script>'; }
function site_domains(array $s): array { return array_values(array_filter(array_map(function ($d) { return strtolower(trim(preg_replace('#^(https?://)?(www\.)?#i', '', trim($d)), '/ ')); }, explode(',', $s['domains'])))); }
function host_allowed(string $host, array $domains): bool {
    $host = strtolower(preg_replace('/^www\./', '', $host));
    foreach ($domains as $d) if ($host === $d || substr($host, -strlen('.' . $d)) === '.' . $d) return true;
    return false;
}

/* ---------- Collecting ---------- */
function an_collect(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Cache-Control: no-store, max-age=0');
    header('Cross-Origin-Resource-Policy: cross-origin');
    $gif = function () { header('Content-Type: image/gif'); echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); exit; };
    $done = function (int $code = 204) use ($gif) { if (!is_post()) $gif(); http_response_code($code); exit; };
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
    if (is_post()) {
        $raw = (string)file_get_contents('php://input', false, null, 0, 16384);
        $in = json_decode($raw, true);
        if (!is_array($in)) $done(400);
    } else { // <noscript> image: the page address comes from the browser's Referer header
        $in = ['s' => get('s'), 't' => 'pv', 'u' => (string)($_SERVER['HTTP_REFERER'] ?? ''), 'r' => '', 'ns' => 1];
    }
    $site = one('SELECT * FROM sites WHERE public_id = ?', [(string)($in['s'] ?? '')]);
    if (!$site) $done(404);
    $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
    if (an_is_bot($ua)) $done(202);
    $type = ['pv' => 'pageview', 'ev' => 'event', 'en' => 'engage'][(string)($in['t'] ?? '')] ?? '';
    if ($type === '') $done(400);
    $ip = an_ip();
    $now = time();
    $visitor = substr(hash('sha256', an_salt(date('Y-m-d', $now)) . '|' . $site['id'] . '|' . $ip . '|' . $ua), 0, 16);

    if ($type === 'engage') {
        $v = is_array($in['v'] ?? null) ? $in['v'] : [];
        $num = function ($k, $max) use ($v) { return isset($v[$k]) && is_numeric($v[$k]) && $v[$k] >= 0 ? (int)min($v[$k], $max) : null; };
        aq('UPDATE events SET duration = MAX(duration, ?), scroll = MAX(scroll, ?), lcp = COALESCE(?, lcp), fcp = COALESCE(?, fcp), cls = COALESCE(?, cls), inp = COALESCE(?, inp), ttfb = COALESCE(?, ttfb)
            WHERE pid = ? AND site_id = ? AND visitor = ? AND type = \'pageview\' AND ts > ?', [
            (int)min(max(0, (int)($in['e'] ?? 0)), 7200), (int)min(max(0, (int)($in['sc'] ?? 0)), 100),
            $num('lcp', 120000), $num('fcp', 120000), $num('cls', 100000), $num('inp', 60000), $num('ttfb', 120000),
            (string)($in['id'] ?? ''), $site['id'], $visitor, $now - 86400]);
        $done();
    }

    $url = parse_url((string)($in['u'] ?? ''));
    $host = strtolower((string)($url['host'] ?? ''));
    if ($host === '' || !host_allowed($host, site_domains($site))) $done(403); // only the client's own domains can send data
    $path = mb_substr(rawurldecode((string)($url['path'] ?? '/')), 0, 300) ?: '/';
    if (strlen($path) > 1) $path = rtrim($path, '/');
    parse_str((string)($url['query'] ?? ''), $qs);
    $str = function ($k) use ($qs) { return isset($qs[$k]) && is_string($qs[$k]) ? mb_substr(trim($qs[$k]), 0, 120) : ''; };
    $ref = (string)($in['r'] ?? '');
    $refHost = strtolower(preg_replace('/^www\./', '', (string)parse_url($ref, PHP_URL_HOST)));
    if ($refHost !== '' && host_allowed($refHost, site_domains($site))) { $ref = ''; $refHost = ''; } // moving around their own site
    [$source, $channel] = an_source($refHost, $str('utm_source') ?: $str('ref') ?: $str('source'), strtolower($str('utm_medium')), $qs);

    // Continue the visitor's current visit, unless they came back through a new ad, link or campaign.
    $last = aone('SELECT * FROM events WHERE site_id = ? AND visitor = ? AND ts > ? ORDER BY ts DESC, id DESC LIMIT 1', [$site['id'], $visitor, $now - AN_SESSION_GAP]);
    $fresh = $type === 'pageview' && ($refHost !== '' || $str('utm_source') !== '' || $str('utm_campaign') !== '');
    $newSession = !$last || ($fresh && ($source !== $last['source'] || $str('utm_campaign') !== $last['campaign']));
    if ($newSession) {
        [$browser, $os, $device] = an_ua($ua, (int)($in['w'] ?? 0), (int)($in['tp'] ?? 0));
        [$country, $region, $city] = an_geo($ip, (string)($in['tz'] ?? ''));
        $s = ['session' => substr(hash('sha256', $visitor . $now . random_bytes(4)), 0, 16), 'source' => $source, 'channel' => $channel, 'medium' => strtolower($str('utm_medium')),
            'campaign' => $str('utm_campaign'), 'content' => $str('utm_content'), 'term' => $str('utm_term'), 'referrer' => mb_substr($ref, 0, 300),
            'device' => $device, 'browser' => $browser, 'os' => $os, 'country' => $country, 'region' => $region, 'city' => $city];
    } else $s = $last;
    $name = $type === 'event' ? mb_substr(trim((string)($in['n'] ?? '')), 0, 80) : '';
    if ($type === 'event' && $name === '') $done(400);
    $props = '';
    if ($type === 'event' && is_array($in['p'] ?? null)) { $p = []; foreach (array_slice($in['p'], 0, 20, true) as $k => $v) if (is_scalar($v)) $p[mb_substr((string)$k, 0, 40)] = mb_substr((string)$v, 0, 200); $props = $p ? json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''; }
    aq('INSERT INTO events (site_id, ts, day, hour, type, pid, visitor, session, entry, name, path, title, hostname, referrer, source, channel, medium, campaign, content, term, device, browser, os, screen, lang, country, region, city, props)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $site['id'], $now, date('Y-m-d', $now), (int)date('G', $now), $type, mb_substr((string)($in['id'] ?? ''), 0, 40), $visitor, $s['session'], $newSession && $type === 'pageview' ? 1 : 0,
        $name, $path, mb_substr(trim((string)($in['ti'] ?? '')), 0, 150), $host, $s['referrer'], $s['source'], $s['channel'], $s['medium'], $s['campaign'], $s['content'], $s['term'],
        $s['device'], $s['browser'], $s['os'], (int)min(max(0, (int)($in['w'] ?? 0)), 10000), mb_substr(strtolower((string)($in['l'] ?? '')), 0, 12), $s['country'], $s['region'], $s['city'], $props]);
    if (mt_rand(1, 500) === 1) aq('DELETE FROM salts WHERE day < ?', [date('Y-m-d', $now - 86400)]);
    $done(202);
}

// The visitor's address, only used to make the daily hash and look up the city. Never saved.
function an_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return (string)$_SERVER['HTTP_CF_CONNECTING_IP']; // behind Cloudflare
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $f = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]); // a proxy on the same server
        if (filter_var($f, FILTER_VALIDATE_IP)) return $f;
    }
    return $ip;
}
function an_salt(string $day): string {
    $s = aval('SELECT salt FROM salts WHERE day = ?', [$day]);
    if ($s) return $s;
    aq('INSERT OR IGNORE INTO salts (day, salt) VALUES (?, ?)', [$day, bin2hex(random_bytes(32))]);
    return (string)aval('SELECT salt FROM salts WHERE day = ?', [$day]);
}
function an_is_bot(string $ua): bool {
    if ($ua === '' || strlen($ua) < 20) return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|scrape|fetch|headless|phantom|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|statuscake|curl|wget|python|httpclient|java\/|go-http|okhttp|axios|node-fetch|facebookexternalhit|meta-externalagent|embedly|preview|whatsapp|telegram|discord|slack|bitly|semrush|ahrefs|mj12|dotbot|yandex|baidu|bytespider|gptbot|claudebot|perplexitybot|ccbot|amazonbot|applebot|petalbot/i', $ua);
}
// [browser, operating system, device]
function an_ua(string $ua, int $screen, int $touch): array {
    $os = 'Other';
    if (preg_match('/iPad/', $ua)) $os = 'iPadOS';
    elseif (preg_match('/iPhone|iPod/', $ua)) $os = 'iOS';
    elseif (preg_match('/Android/', $ua)) $os = 'Android';
    elseif (preg_match('/CrOS/', $ua)) $os = 'ChromeOS';
    elseif (preg_match('/Windows/', $ua)) $os = 'Windows';
    elseif (preg_match('/Macintosh|Mac OS X/', $ua)) $os = $touch > 1 ? 'iPadOS' : 'macOS'; // iPads ask for desktop sites
    elseif (preg_match('/Linux/', $ua)) $os = 'Linux';
    $map = ['/FBAN|FBAV|FB_IAB/' => 'Facebook app', '/Instagram/' => 'Instagram app', '/musical_ly|TikTok|BytedanceWebview|trill/' => 'TikTok app', '/LinkedInApp/' => 'LinkedIn app',
        '/Snapchat/' => 'Snapchat app', '/Pinterest/' => 'Pinterest app', '/Edg(e|A|iOS)?\//' => 'Edge', '/OPR\/|Opera/' => 'Opera', '/SamsungBrowser/' => 'Samsung Internet',
        '/DuckDuckGo/' => 'DuckDuckGo', '/CriOS/' => 'Chrome', '/FxiOS/' => 'Firefox', '/Firefox\//' => 'Firefox', '/Chrome\//' => 'Chrome', '/Safari\//' => 'Safari'];
    $browser = 'Other';
    foreach ($map as $re => $b) if (preg_match($re, $ua)) { $browser = $b; break; }
    if ($os === 'iPadOS' || ($os === 'Android' && !preg_match('/Mobile/', $ua))) $device = 'Tablet';
    elseif (preg_match('/Mobi|iPhone|iPod/', $ua)) $device = 'Mobile';
    elseif ($screen && $screen < 600) $device = 'Mobile';
    else $device = 'Desktop';
    return [$browser, $os, $device];
}

/* ---------- Where visitors came from ---------- */
const AN_REFERRERS = [
    // host pattern => [name, kind]
    '/(^|\.)google\.[a-z.]+$/' => ['Google', 'search'], '/^maps\.google\./' => ['Google Maps', 'search'], '/(^|\.)bing\.com$/' => ['Bing', 'search'],
    '/(^|\.)duckduckgo\.com$/' => ['DuckDuckGo', 'search'], '/(^|\.)yahoo\.com$/' => ['Yahoo', 'search'], '/(^|\.)ecosia\.org$/' => ['Ecosia', 'search'],
    '/(^|\.)search\.brave\.com$/' => ['Brave Search', 'search'], '/(^|\.)baidu\.com$/' => ['Baidu', 'search'], '/(^|\.)yandex\./' => ['Yandex', 'search'],
    '/(^|\.)(facebook\.com|fb\.com|fb\.me)$/' => ['Facebook', 'social'], '/(^|\.)instagram\.com$/' => ['Instagram', 'social'], '/(^|\.)(tiktok\.com|tiktokv\.com)$/' => ['TikTok', 'social'],
    '/(^|\.)(t\.co|twitter\.com|x\.com)$/' => ['X (Twitter)', 'social'], '/(^|\.)(linkedin\.com|lnkd\.in)$/' => ['LinkedIn', 'social'], '/(^|\.)(youtube\.com|youtu\.be)$/' => ['YouTube', 'social'],
    '/(^|\.)(pinterest\.[a-z.]+|pin\.it)$/' => ['Pinterest', 'social'], '/(^|\.)reddit\.com$/' => ['Reddit', 'social'], '/(^|\.)nextdoor\.com$/' => ['Nextdoor', 'social'],
    '/(^|\.)threads\.(net|com)$/' => ['Threads', 'social'], '/(^|\.)snapchat\.com$/' => ['Snapchat', 'social'], '/(^|\.)yelp\.com$/' => ['Yelp', 'referral'],
    '/(^|\.)(chatgpt\.com|chat\.openai\.com)$/' => ['ChatGPT', 'ai'], '/(^|\.)perplexity\.ai$/' => ['Perplexity', 'ai'], '/(^|\.)claude\.ai$/' => ['Claude', 'ai'],
    '/^gemini\.google\.com$/' => ['Gemini', 'ai'], '/(^|\.)copilot\.microsoft\.com$/' => ['Copilot', 'ai'],
    '/(^|\.)(mail\.google\.com|outlook\.(live|office)\.com|mail\.yahoo\.com|com\.google\.android\.gm)$/' => ['Email', 'email'],
];
const AN_SOURCE_NAMES = ['google' => 'Google', 'bing' => 'Bing', 'facebook' => 'Facebook', 'fb' => 'Facebook', 'meta' => 'Facebook', 'instagram' => 'Instagram', 'ig' => 'Instagram',
    'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'twitter' => 'X (Twitter)', 'x' => 'X (Twitter)', 'pinterest' => 'Pinterest', 'nextdoor' => 'Nextdoor',
    'yelp' => 'Yelp', 'gbp' => 'Google Business Profile', 'gmb' => 'Google Business Profile', 'google_business' => 'Google Business Profile', 'googlebusiness' => 'Google Business Profile',
    'google_my_business' => 'Google Business Profile', 'newsletter' => 'Newsletter', 'mailchimp' => 'Mailchimp', 'klaviyo' => 'Klaviyo', 'chatgpt.com' => 'ChatGPT', 'chatgpt' => 'ChatGPT', 'qr' => 'QR code'];
// [source name, channel]
function an_source(string $refHost, string $utmSource, string $medium, array $qs): array {
    $kind = '';
    $name = '';
    if ($utmSource !== '') {
        $k = strtolower($utmSource);
        $name = AN_SOURCE_NAMES[$k] ?? ucfirst($utmSource);
        foreach (AN_REFERRERS as $re => [$n, $kd]) if ($n === $name) { $kind = $kd; break; }
    } elseif ($refHost !== '') {
        foreach (AN_REFERRERS as $re => [$n, $kd]) if (preg_match($re, $refHost)) { $name = $n; $kind = $kd; break; }
        if ($name === '') { $name = $refHost; $kind = 'referral'; }
    }
    $click = function ($k) use ($qs) { return isset($qs[$k]) && $qs[$k] !== ''; };
    if ($name === '' && ($click('gclid') || $click('gbraid') || $click('wbraid'))) { $name = 'Google'; $kind = 'search'; $medium = $medium ?: 'cpc'; }
    if ($name === '' && $click('msclkid')) { $name = 'Bing'; $kind = 'search'; $medium = $medium ?: 'cpc'; }
    if ($click('ttclid')) { $name = $name ?: 'TikTok'; $kind = 'social'; $medium = $medium ?: 'paid'; }
    if ($click('li_fat_id')) { $name = $name ?: 'LinkedIn'; $kind = 'social'; $medium = $medium ?: 'paid'; }
    if ($name === '' && $click('fbclid')) { $name = 'Facebook'; $kind = 'social'; }
    if ($name === '') return ['Direct', 'Direct'];
    $paid = (bool)preg_match('/^(cpc|ppc|cpm|cpv|cpa|paid|paidsearch|paid[_ -]?search|paid[_ -]?social|paidsocial|social[_ -]?paid|ad|ads|display|banner|retargeting|remarketing|sem)$/', $medium) || $click('gclid') || $click('msclkid');
    if ($paid) return [$name, $kind === 'search' ? 'Paid search' : ($kind === 'social' ? 'Paid social' : (preg_match('/display|banner|cpm/', $medium) ? 'Display' : 'Paid other'))];
    if (preg_match('/e-?mail|newsletter/', $medium) || $kind === 'email') return [$name, 'Email'];
    if ($medium === 'sms' || $medium === 'text') return [$name, 'SMS'];
    if ($medium === 'qr' || $medium === 'qrcode' || $name === 'QR code') return [$name, 'QR code'];
    if ($name === 'Google Business Profile') return [$name, 'Local listing'];
    if ($kind === 'ai') return [$name, 'AI assistants'];
    if ($kind === 'search' || $medium === 'organic') return [$name, 'Organic search'];
    if ($kind === 'social' || preg_match('/social/', $medium)) return [$name, 'Organic social'];
    return [$name, $medium === 'affiliate' ? 'Affiliate' : 'Referral'];
}

/* ---------- Where visitors are ---------- */
// Country, state and city from an optional IP database in storage/ (see README); otherwise country from the browser's time zone.
function an_geo(string $ip, string $tz): array {
    static $db = false;
    if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && preg_match('/^[A-Z]{2}$/', $_SERVER['HTTP_CF_IPCOUNTRY']) && $_SERVER['HTTP_CF_IPCOUNTRY'] !== 'XX') $cf = $_SERVER['HTTP_CF_IPCOUNTRY'];
    if ($db === false) {
        $db = null;
        $f = (string)config('geoip_db') ?: (glob(STORAGE . '/*.mmdb') ?: [''])[0];
        if ($f !== '' && is_readable($f)) { require_once __DIR__ . '/mmdb.php'; try { $db = new Mmdb($f); } catch (Throwable $ex) { error_log('GeoIP: ' . $ex->getMessage()); } }
    }
    if ($db) {
        try {
            $r = $db->get($ip);
            if ($r) return [(string)($r['country']['iso_code'] ?? $cf ?? ''), mb_substr((string)($r['subdivisions'][0]['names']['en'] ?? ''), 0, 60), mb_substr((string)($r['city']['names']['en'] ?? ''), 0, 60)];
        } catch (Throwable $ex) {}
    }
    return [$cf ?? an_tz_country($tz), '', ''];
}
function an_tz_country(string $tz): string {
    if ($tz === '') return '';
    $exact = ['America/Toronto' => 'CA', 'America/Vancouver' => 'CA', 'America/Edmonton' => 'CA', 'America/Winnipeg' => 'CA', 'America/Halifax' => 'CA', 'America/St_Johns' => 'CA', 'America/Regina' => 'CA',
        'America/Mexico_City' => 'MX', 'America/Monterrey' => 'MX', 'America/Tijuana' => 'MX', 'America/Cancun' => 'MX', 'America/Puerto_Rico' => 'PR', 'America/Sao_Paulo' => 'BR', 'America/Bogota' => 'CO',
        'America/Lima' => 'PE', 'America/Argentina/Buenos_Aires' => 'AR', 'America/Santiago' => 'CL', 'Europe/London' => 'GB', 'Europe/Dublin' => 'IE', 'Europe/Paris' => 'FR', 'Europe/Berlin' => 'DE',
        'Europe/Madrid' => 'ES', 'Europe/Rome' => 'IT', 'Europe/Amsterdam' => 'NL', 'Europe/Brussels' => 'BE', 'Europe/Zurich' => 'CH', 'Europe/Vienna' => 'AT', 'Europe/Stockholm' => 'SE',
        'Europe/Oslo' => 'NO', 'Europe/Copenhagen' => 'DK', 'Europe/Helsinki' => 'FI', 'Europe/Warsaw' => 'PL', 'Europe/Lisbon' => 'PT', 'Europe/Athens' => 'GR', 'Europe/Kyiv' => 'UA', 'Europe/Kiev' => 'UA',
        'Europe/Istanbul' => 'TR', 'Asia/Kolkata' => 'IN', 'Asia/Calcutta' => 'IN', 'Asia/Manila' => 'PH', 'Asia/Tokyo' => 'JP', 'Asia/Seoul' => 'KR', 'Asia/Shanghai' => 'CN', 'Asia/Hong_Kong' => 'HK',
        'Asia/Singapore' => 'SG', 'Asia/Dubai' => 'AE', 'Asia/Karachi' => 'PK', 'Asia/Jakarta' => 'ID', 'Asia/Bangkok' => 'TH', 'Asia/Ho_Chi_Minh' => 'VN', 'Africa/Lagos' => 'NG', 'Africa/Johannesburg' => 'ZA',
        'Africa/Cairo' => 'EG', 'Africa/Nairobi' => 'KE', 'Pacific/Auckland' => 'NZ', 'Pacific/Honolulu' => 'US', 'America/Anchorage' => 'US', 'America/Phoenix' => 'US', 'America/Boise' => 'US'];
    if (isset($exact[$tz])) return $exact[$tz];
    if (preg_match('#^America/(New_York|Chicago|Denver|Los_Angeles|Detroit|Indiana/|Kentucky/|North_Dakota/|Menominee|Juneau|Nome|Sitka)#', $tz)) return 'US';
    if (strpos($tz, 'Australia/') === 0) return 'AU';
    return '';
}
function country_name(string $c): string {
    $n = ['US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico', 'GB' => 'United Kingdom', 'IE' => 'Ireland', 'FR' => 'France', 'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands',
        'BE' => 'Belgium', 'CH' => 'Switzerland', 'AT' => 'Austria', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'PL' => 'Poland', 'PT' => 'Portugal', 'GR' => 'Greece',
        'UA' => 'Ukraine', 'TR' => 'Turkey', 'IN' => 'India', 'PH' => 'Philippines', 'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China', 'HK' => 'Hong Kong', 'SG' => 'Singapore', 'AE' => 'UAE',
        'PK' => 'Pakistan', 'ID' => 'Indonesia', 'TH' => 'Thailand', 'VN' => 'Vietnam', 'NG' => 'Nigeria', 'ZA' => 'South Africa', 'EG' => 'Egypt', 'KE' => 'Kenya', 'AU' => 'Australia', 'NZ' => 'New Zealand',
        'BR' => 'Brazil', 'CO' => 'Colombia', 'PE' => 'Peru', 'AR' => 'Argentina', 'CL' => 'Chile', 'PR' => 'Puerto Rico', 'RU' => 'Russia', 'IL' => 'Israel', 'SA' => 'Saudi Arabia', 'JM' => 'Jamaica', 'DO' => 'Dominican Republic'];
    if ($c === '') return 'Unknown';
    $flag = preg_match('/^[A-Z]{2}$/', $c) ? mb_chr(0x1F1E6 + ord($c[0]) - 65) . mb_chr(0x1F1E6 + ord($c[1]) - 65) . ' ' : '';
    return $flag . ($n[$c] ?? $c);
}

/* ---------- Reports ---------- */
function an_range(string $key, string $from = '', string $to = ''): array {
    $today = strtotime('today'); $tomorrow = strtotime('tomorrow');
    $r = ['key' => $key, 'bucket' => 'day'];
    switch ($key) {
        case 'today': $r['bucket'] = 'hour'; $r += ['from' => $today, 'to' => $tomorrow, 'pfrom' => strtotime('yesterday'), 'pto' => $today]; break;
        case 'yesterday': $r['bucket'] = 'hour'; $r += ['from' => strtotime('yesterday'), 'to' => $today, 'pfrom' => strtotime('-2 days', $today), 'pto' => strtotime('yesterday')]; break;
        case '7d': case '90d': case '30d':
            $n = (int)$key; $r += ['from' => strtotime('-' . ($n - 1) . ' days', $today), 'to' => $tomorrow]; $r += ['pfrom' => strtotime("-$n days", $r['from']), 'pto' => $r['from']]; break;
        case 'month': $f = strtotime(date('Y-m-01')); $r += ['from' => $f, 'to' => $tomorrow, 'pfrom' => strtotime('-1 month', $f)]; $r['pto'] = min($f, $r['pfrom'] + ($tomorrow - $f)); break;
        case 'lastmonth': $f = strtotime('first day of last month', $today); $r += ['from' => $f, 'to' => strtotime(date('Y-m-01')), 'pfrom' => strtotime('-1 month', $f), 'pto' => $f]; break;
        case '12m': $f = strtotime(date('Y-m-01', strtotime('-11 months', strtotime(date('Y-m-01'))))); $r['bucket'] = 'month'; $r += ['from' => $f, 'to' => $tomorrow, 'pfrom' => strtotime('-12 months', $f), 'pto' => $f]; break;
        default:
            $f = valid_date($from) ? strtotime($from) : strtotime('-29 days', $today);
            $t = valid_date($to) ? strtotime('+1 day', strtotime($to)) : $tomorrow;
            if ($t <= $f) $t = strtotime('+1 day', $f);
            $r = ['key' => 'custom', 'from' => $f, 'to' => $t, 'bucket' => $t - $f > 86400 * 120 ? 'month' : ($t - $f <= 86400 ? 'hour' : 'day'), 'pfrom' => $f - ($t - $f), 'pto' => $f];
    }
    $r['label'] = AN_RANGES[$r['key']] ?? date('M j', $r['from']) . ' – ' . date('M j, Y', $r['to'] - 1);
    return $r;
}
function site_goals(int $siteId): array { return all('SELECT * FROM site_goals WHERE site_id = ? ORDER BY id', [$siteId]); }
function goal_sql(array $g): array {
    if ($g['kind'] === 'page') return ["(type = 'pageview' AND path GLOB ?)", [str_replace(['[', ']', '?'], ['[[]', '[]]', '[?]'], $g['match'])]];
    return ["(type = 'event' AND name = ? COLLATE NOCASE)", [$g['match']]];
}
// WHERE clause for one site, a time range and the filters someone clicked.
function an_where(int $site, int $from, int $to, array $f, array $goals): array {
    $w = 'site_id = ? AND ts >= ? AND ts < ?'; $a = [$site, $from, $to];
    foreach ($f as $k => $v) {
        if (in_array($k, ['channel', 'source', 'campaign', 'medium', 'region', 'city', 'device', 'browser', 'os'], true)) { $w .= " AND $k = ?"; $a[] = $v === '(none)' ? '' : $v; }
        elseif ($k === 'country') { $w .= ' AND country = ?'; $a[] = $v === '(none)' ? '' : $v; }
        elseif ($k === 'page' || $k === 'entry') { $w .= " AND session IN (SELECT session FROM events WHERE site_id = ? AND ts >= ? AND ts < ? AND type = 'pageview'" . ($k === 'entry' ? ' AND entry = 1' : '') . ' AND path = ?)'; array_push($a, $site, $from, $to, $v); }
        elseif ($k === 'goal') {
            foreach ($goals as $g) if ((string)$g['id'] === (string)$v) { [$gs, $ga] = goal_sql($g); $w .= " AND session IN (SELECT session FROM events WHERE site_id = ? AND ts >= ? AND ts < ? AND $gs)"; array_push($a, $site, $from, $to, ...$ga); }
        }
    }
    return [$w, $a];
}
function an_totals(string $w, array $a, array $goals): array {
    $t = aone("SELECT COUNT(DISTINCT visitor) visitors, COUNT(DISTINCT session) visits, COALESCE(SUM(type = 'pageview'), 0) pageviews FROM events WHERE $w", $a);
    $s = aone("SELECT COUNT(*) n, COALESCE(SUM(pv = 1 AND ev = 0), 0) bounces, AVG(dur) dur FROM (SELECT SUM(type = 'pageview') pv, SUM(type = 'event') ev, SUM(duration) dur FROM events WHERE $w GROUP BY session)", $a);
    $conv = 0;
    if ($goals) {
        $parts = []; $ga = [];
        foreach ($goals as $g) { [$gs, $gx] = goal_sql($g); $parts[] = $gs; array_push($ga, ...$gx); }
        $conv = (int)aval("SELECT COUNT(DISTINCT session) FROM events WHERE $w AND (" . implode(' OR ', $parts) . ')', array_merge($a, $ga));
    }
    $v = (int)$t['visits'];
    return ['visitors' => (int)$t['visitors'], 'visits' => $v, 'pageviews' => (int)$t['pageviews'], 'ppv' => $v ? $t['pageviews'] / $v : 0,
        'bounce' => $s['n'] ? 100 * $s['bounces'] / $s['n'] : 0, 'duration' => (float)$s['dur'], 'conversions' => $conv, 'cr' => $v ? 100 * $conv / $v : 0];
}
function an_report(array $site, array $range, array $filters): array {
    if (!$filters && $range['bucket'] !== 'hour') return an_report_fast($site, $range);
    $goals = site_goals((int)$site['id']);
    [$w, $a] = an_where((int)$site['id'], $range['from'], $range['to'], $filters, $goals);
    [$pw, $pa] = an_where((int)$site['id'], $range['pfrom'], $range['pto'], $filters, $goals);
    $R = ['range' => $range, 'filters' => $filters, 'goals' => $goals, 'totals' => an_totals($w, $a, $goals), 'prev' => an_totals($pw, $pa, $goals)];

    // Visitors over time, with empty days filled in
    $bx = ['hour' => 'hour', 'day' => 'day', 'month' => 'substr(day, 1, 7)'][$range['bucket']];
    $rows = []; foreach (aall("SELECT $bx b, COUNT(DISTINCT visitor) v, SUM(type = 'pageview') p, COUNT(DISTINCT session) s FROM events WHERE $w GROUP BY b", $a) as $r) $rows[(string)$r['b']] = $r;
    $series = [];
    if ($range['bucket'] === 'hour') for ($h = 0; $h < 24; $h++) $series[] = [date('g a', strtotime("today $h:00")), (int)($rows[(string)$h]['v'] ?? 0), (int)($rows[(string)$h]['p'] ?? 0), (int)($rows[(string)$h]['s'] ?? 0)];
    elseif ($range['bucket'] === 'month') for ($t = $range['from']; $t < $range['to']; $t = strtotime('+1 month', $t)) { $k = date('Y-m', $t); $series[] = [date('M Y', $t), (int)($rows[$k]['v'] ?? 0), (int)($rows[$k]['p'] ?? 0), (int)($rows[$k]['s'] ?? 0)]; }
    else for ($t = $range['from']; $t < $range['to']; $t = strtotime('+1 day', $t)) { $k = date('Y-m-d', $t); $series[] = [date('D, M j', $t), (int)($rows[$k]['v'] ?? 0), (int)($rows[$k]['p'] ?? 0), (int)($rows[$k]['s'] ?? 0)]; }
    $R['series'] = $series;

    $R['pages'] = aall("SELECT path k, COUNT(*) views, COUNT(DISTINCT visitor) visitors, AVG(NULLIF(duration, 0)) secs, AVG(NULLIF(scroll, 0)) scroll FROM events WHERE $w AND type = 'pageview' GROUP BY path ORDER BY visitors DESC, views DESC LIMIT 12", $a);
    $R['entries'] = aall("SELECT k, COUNT(*) visits, SUM(bounced) bounces FROM (SELECT MAX(CASE WHEN entry = 1 THEN path END) k, SUM(type = 'pageview') = 1 AND SUM(type = 'event') = 0 bounced FROM events WHERE $w GROUP BY session) WHERE k IS NOT NULL GROUP BY k ORDER BY visits DESC LIMIT 10", $a);
    $conv = function (string $dim) use ($w, $a, $goals) {
        if (!$goals) return [];
        $parts = []; $ga = []; foreach ($goals as $g) { [$gs, $gx] = goal_sql($g); $parts[] = $gs; array_push($ga, ...$gx); }
        $o = []; foreach (aall("SELECT $dim k, COUNT(DISTINCT session) c FROM events WHERE $w AND (" . implode(' OR ', $parts) . ") GROUP BY $dim", array_merge($a, $ga)) as $r) $o[(string)$r['k']] = (int)$r['c'];
        return $o;
    };
    foreach (['channel' => 10, 'source' => 12, 'campaign' => 10, 'country' => 10, 'region' => 10, 'city' => 10, 'device' => 4, 'browser' => 8, 'os' => 8] as $dim => $lim) {
        $extra = $dim === 'campaign' ? " AND campaign != ''" : (in_array($dim, ['region', 'city'], true) ? " AND $dim != ''" : '');
        $R[$dim] = aall("SELECT $dim k, COUNT(DISTINCT visitor) visitors, COUNT(DISTINCT session) visits" . ($dim === 'campaign' ? ', MAX(source) src, MAX(medium) med' : '') . " FROM events WHERE $w$extra GROUP BY $dim ORDER BY visitors DESC LIMIT $lim", $a);
        if (in_array($dim, ['channel', 'source', 'campaign'], true)) { $c = $conv($dim); foreach ($R[$dim] as &$row) $row['conv'] = $c[(string)$row['k']] ?? 0; unset($row); }
    }
    $R['events'] = aall("SELECT name k, COUNT(*) n, COUNT(DISTINCT visitor) visitors FROM events WHERE $w AND type = 'event' GROUP BY name ORDER BY n DESC LIMIT 12", $a);
    foreach ($R['events'] as &$ev) { // the most common detail for each event, e.g. which link or file
        $vals = [];
        foreach (aall("SELECT props FROM events WHERE $w AND type = 'event' AND name = ? AND props != '' ORDER BY ts DESC LIMIT 400", array_merge($a, [$ev['k']])) as $p) {
            $d = json_decode($p['props'], true) ?: []; $first = reset($d); if ($first !== false && $first !== '') { $vals[$first] = ($vals[$first] ?? 0) + 1; }
        }
        arsort($vals); $ev['top'] = array_slice($vals, 0, 3, true);
    }
    unset($ev);
    $R['goal_rows'] = [];
    foreach ($goals as $g) {
        [$gs, $ga] = goal_sql($g);
        $x = aone("SELECT COUNT(DISTINCT session) s, COUNT(DISTINCT visitor) v, COUNT(*) n FROM events WHERE $w AND $gs", array_merge($a, $ga));
        $R['goal_rows'][] = ['goal' => $g, 'conversions' => (int)$x['s'], 'visitors' => (int)$x['v'], 'count' => (int)$x['n'], 'cr' => $R['totals']['visits'] ? 100 * $x['s'] / $R['totals']['visits'] : 0];
    }
    // Core Web Vitals at the 75th percentile, the way Google scores a site
    $R['vitals'] = [];
    foreach (['all' => '', 'mobile' => " AND device = 'Mobile'"] as $seg => $cond) {
        foreach (['lcp', 'inp', 'cls', 'fcp', 'ttfb'] as $m) {
            $vals = array_map('intval', aq("SELECT $m FROM events WHERE $w AND type = 'pageview' AND $m IS NOT NULL$cond ORDER BY $m LIMIT 20000", $a)->fetchAll(PDO::FETCH_COLUMN));
            $R['vitals'][$seg][$m] = $vals ? ['p75' => $vals[(int)floor(0.75 * (count($vals) - 1))], 'n' => count($vals)] : null;
        }
    }
    $R['heat'] = [];
    foreach (aall("SELECT CAST(strftime('%w', day) AS INTEGER) wd, hour h, COUNT(DISTINCT session) s FROM events WHERE $w GROUP BY wd, hour", $a) as $r) $R['heat'][(int)$r['wd']][(int)$r['h']] = (int)$r['s'];
    return $R;
}
function an_live(int $siteId): array {
    $since = time() - 300;
    return ['now' => (int)aval('SELECT COUNT(DISTINCT visitor) FROM events WHERE site_id = ? AND ts > ?', [$siteId, $since]),
        'pages' => aall("SELECT path k, COUNT(DISTINCT visitor) n FROM events WHERE site_id = ? AND ts > ? AND type = 'pageview' GROUP BY path ORDER BY n DESC LIMIT 5", [$siteId, $since]),
        'sources' => aall('SELECT source k, COUNT(DISTINCT visitor) n FROM events WHERE site_id = ? AND ts > ? GROUP BY source ORDER BY n DESC LIMIT 5', [$siteId, $since]),
        'last' => (int)aval('SELECT MAX(ts) FROM events WHERE site_id = ?', [$siteId])];
}
// Daily visitors for the last $days, for sparklines and summaries.
function an_daily(int $siteId, int $days = 30): array {
    $from = strtotime('-' . ($days - 1) . ' days', strtotime('today'));
    $rows = []; foreach (an_roll_rows($siteId, $from, strtotime('tomorrow'), site_goals($siteId)) as $r) if ($r['dim'] === 'total') $rows[$r['day']] = (int)$r['v'];
    $o = []; for ($t = $from; $t < strtotime('tomorrow'); $t = strtotime('+1 day', $t)) $o[] = $rows[date('Y-m-d', $t)] ?? 0;
    return $o;
}
function an_cleanup(): void {
    $days = max(30, (int)(setting('analytics_retention_days') ?: 760));
    aq('DELETE FROM events WHERE ts < ?', [time() - $days * 86400]);
    an_roll_schema();
    aq('DELETE FROM rollups WHERE day < ?', [date('Y-m-d', time() - $days * 86400)]);
    aq('DELETE FROM rollup_days WHERE day < ?', [date('Y-m-d', time() - $days * 86400)]);
    foreach (all('SELECT id FROM sites') as $s) an_roll_ensure((int)$s['id'], strtotime('-400 days', strtotime('today')), strtotime('today'), site_goals((int)$s['id'])); // keep summaries warm
    aq('DELETE FROM salts WHERE day < ?', [date('Y-m-d', time() - 86400)]);
}

/* ---------- Formatting ---------- */
function an_num($n): string { $n = (float)$n; return $n >= 1000000 ? round($n / 1000000, 1) . 'M' : ($n >= 10000 ? round($n / 1000, 1) . 'K' : number_format($n)); }
function an_dur($s): string { $s = (int)round((float)$s); return $s < 60 ? $s . 's' : floor($s / 60) . 'm ' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT) . 's'; }
function an_delta(float $now, float $prev, bool $lowerIsBetter = false): string {
    if ($prev <= 0) return $now > 0 ? '<span class="delta new">New</span>' : '<span class="delta">—</span>';
    $pct = ($now - $prev) / $prev * 100;
    if (abs($pct) < 0.5) return '<span class="delta">0%</span>';
    $good = $lowerIsBetter ? $pct < 0 : $pct > 0;
    return '<span class="delta ' . ($good ? 'up' : 'down') . '">' . ($pct > 0 ? '↑' : '↓') . ' ' . round(abs($pct)) . '%</span>';
}
function vital_rating(string $m, int $v): string {
    $t = ['lcp' => [2500, 4000], 'inp' => [200, 500], 'cls' => [100, 250], 'fcp' => [1800, 3000], 'ttfb' => [800, 1800]][$m];
    return $v <= $t[0] ? 'good' : ($v <= $t[1] ? 'ok' : 'poor');
}
function vital_fmt(string $m, int $v): string { return $m === 'cls' ? number_format($v / 1000, 2) : ($v >= 1000 ? number_format($v / 1000, 1) . ' s' : $v . ' ms'); }
function sparkline(array $vals, int $w = 120, int $h = 32): string {
    $max = max(1, max($vals ?: [0])); $n = max(1, count($vals) - 1);
    $pts = []; foreach ($vals as $i => $v) $pts[] = round($i * $w / $n, 1) . ',' . round($h - 2 - ($v / $max) * ($h - 4), 1);
    $line = implode(' ', $pts);
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true"><polygon points="0,' . $h . ' ' . $line . ' ' . $w . ',' . $h . '" class="spark-fill"/><polyline points="' . $line . '" class="spark-line"/></svg>';
}
// The main chart: visitors as a filled area, pageviews as a line. Hover shows the numbers (crm.js).
function an_chart(array $series): string {
    $W = 1000; $H = 260; $L = 46; $R = 14; $T = 16; $B = 30;
    $max = 0; foreach ($series as $s) $max = max($max, $s[1], $s[2]);
    $step = $max <= 4 ? 1 : (function ($m) { $raw = $m / 4; $p = pow(10, floor(log10($raw))); foreach ([1, 2, 2.5, 5, 10] as $k) if ($k * $p >= $raw) return $k * $p; return 10 * $p; })($max);
    $top = max($step * 4, $step * ceil($max / $step));
    $n = count($series); $x = function ($i) use ($n, $W, $L, $R) { return $n <= 1 ? $L + ($W - $L - $R) / 2 : $L + $i * ($W - $L - $R) / ($n - 1); };
    $y = function ($v) use ($top, $H, $T, $B) { return $H - $B - ($v / $top) * ($H - $T - $B); };
    $o = '<svg class="an-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Visitors and pageviews over time" data-points="' . e(json_encode($series)) . '">';
    for ($v = 0; $v <= $top + 0.001; $v += $step) $o .= '<line class="grid" x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . round($y($v), 1) . '" y2="' . round($y($v), 1) . '"/><text class="ax" x="' . ($L - 8) . '" y="' . round($y($v) + 4, 1) . '" text-anchor="end">' . an_num($v) . '</text>';
    $every = max(1, (int)ceil($n / 8));
    foreach ($series as $i => $s) if ($i % $every === 0 || $i === $n - 1 && $n <= 12) $o .= '<text class="ax" x="' . round($x($i), 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . e(preg_replace('/^\w{3}, /', '', $s[0])) . '</text>';
    $vis = []; $pv = []; foreach ($series as $i => $s) { $vis[] = round($x($i), 1) . ',' . round($y($s[1]), 1); $pv[] = round($x($i), 1) . ',' . round($y($s[2]), 1); }
    if ($n) {
        $o .= '<polygon class="area" points="' . round($x(0), 1) . ',' . ($H - $B) . ' ' . implode(' ', $vis) . ' ' . round($x($n - 1), 1) . ',' . ($H - $B) . '"/>';
        $o .= '<polyline class="l-pv" points="' . implode(' ', $pv) . '"/><polyline class="l-vis" points="' . implode(' ', $vis) . '"/>';
        $last = $series[$n - 1]; $o .= '<circle class="dot-end" cx="' . round($x($n - 1), 1) . '" cy="' . round($y($last[1]), 1) . '" r="4"/>';
        $o .= '<line class="hover-line" x1="0" x2="0" y1="' . $T . '" y2="' . ($H - $B) . '" visibility="hidden"/>';
        $o .= '<rect class="hover-zone" x="' . $L . '" y="' . $T . '" width="' . ($W - $L - $R) . '" height="' . ($H - $T - $B) . '" data-l="' . $L . '" data-r="' . $R . '" data-w="' . $W . '"/>';
    }
    return $o . '</svg>';
}

/* ---------- Monthly report email (from cron.php, in the first days of each month) ---------- */
function an_monthly_reports(bool $any_day = false): int {
    if ((int)date('j') > 5 && !$any_day) return 0;
    $month = date('Y-m', strtotime('first day of last month'));
    $sent = 0;
    foreach (all('SELECT s.*, co.name company, co.brand FROM sites s JOIN companies co ON co.id = s.company_id WHERE s.monthly_report = 1 AND s.shared = 1 AND s.care = 0') as $s) {
        if (setting('an_report_' . $s['id']) === $month) continue;
        $people = all("SELECT * FROM contacts WHERE company_id = ? AND portal_access = 1 AND email != ''", [$s['company_id']]);
        setting('an_report_' . $s['id'], $month);
        if (!$people) continue;
        $r = an_range('lastmonth'); $goals = site_goals((int)$s['id']);
        [$w, $a] = an_where((int)$s['id'], $r['from'], $r['to'], [], $goals); [$pw, $pa] = an_where((int)$s['id'], $r['pfrom'], $r['pto'], [], $goals);
        $t = an_totals($w, $a, $goals); $p = an_totals($pw, $pa, $goals);
        if (!$t['visits'] && !$p['visits']) continue;
        $chg = function ($n, $o) { if ($o <= 0) return ''; $pct = round(($n - $o) / $o * 100); return ' (' . ($pct >= 0 ? '+' : '') . $pct . '% vs. the month before)'; };
        $src = aall("SELECT source k, COUNT(DISTINCT visitor) v FROM events WHERE $w GROUP BY source ORDER BY v DESC LIMIT 4", $a);
        $pages = aall("SELECT path k, COUNT(DISTINCT visitor) v FROM events WHERE $w AND type = 'pageview' GROUP BY path ORDER BY v DESC LIMIT 4", $a);
        $goalLines = [];
        foreach ($goals as $g) { [$gs, $ga] = goal_sql($g); $n = (int)aval("SELECT COUNT(DISTINCT session) FROM events WHERE $w AND $gs", array_merge($a, $ga)); if ($n) $goalLines[] = '  ' . $g['name'] . ': ' . $n; }
        $brand = $s['brand'] && $s['brand'] !== 'Syndicate Holdings Group' ? $s['brand'] : setting('business_name');
        $label = date('F Y', $r['from']);
        $body = "Here's how " . $s['name'] . " did in $label:\n\n"
            . '  Visitors: ' . an_num($t['visitors']) . $chg($t['visitors'], $p['visitors']) . "\n"
            . '  Visits: ' . an_num($t['visits']) . "\n  Pageviews: " . an_num($t['pageviews']) . "\n"
            . '  Engaged time per visit: ' . an_dur($t['duration']) . "\n"
            . '  Conversions: ' . an_num($t['conversions']) . ($t['visits'] ? ' (' . number_format($t['cr'], 1) . '% of visits)' : '') . $chg($t['conversions'], $p['conversions']) . "\n"
            . ($goalLines ? "\nConversions by goal:\n" . implode("\n", $goalLines) . "\n" : '')
            . ($src ? "\nWhere visitors came from:\n" . implode("\n", array_map(function ($x) { return '  ' . $x['k'] . ': ' . an_num($x['v']); }, $src)) . "\n" : '')
            . ($pages ? "\nMost visited pages:\n" . implode("\n", array_map(function ($x) { return '  ' . $x['k'] . ': ' . an_num($x['v']); }, $pages)) . "\n" : '')
            . "\nSee the full report: " . abs_url('portal-stats.php?id=' . $s['id'] . '&range=lastmonth') . "\n\n$brand\n" . setting('business_email');
        foreach ($people as $c) if (send_mail($c['email'], $s['name'] . " · $label website report", 'Hi ' . ($c['first_name'] ?: 'there') . ",\n\n" . $body)) $sent++;
        log_activity('company', (int)$s['company_id'], 'email', "Sent the $label website report for " . $s['name'] . ' to ' . count($people) . ' ' . (count($people) === 1 ? 'person' : 'people') . '.');
    }
    return $sent;
}

/* ---------- Daily summaries ----------
   Each finished day is summarised once into `rollups`, so a year of a busy site reports in milliseconds.
   Summing daily numbers is exact here: the visitor code changes every day, so a visitor and their visits
   never span two days. Filtered views and single days read the raw events instead. */
const AN_ROLL_DIMS = ['channel', 'source', 'medium', 'campaign', 'country', 'region', 'city', 'device', 'browser', 'os'];
const AN_VITAL_STEP = ['lcp' => 100, 'fcp' => 100, 'ttfb' => 50, 'inp' => 20, 'cls' => 10];
function an_roll_schema(): void {
    if ((int)an_db()->query('PRAGMA user_version')->fetchColumn() >= 2) return;
    an_db()->exec("
        CREATE TABLE IF NOT EXISTS rollups (site_id INTEGER NOT NULL, day TEXT NOT NULL, dim TEXT NOT NULL, k TEXT NOT NULL, v INTEGER NOT NULL DEFAULT 0, s INTEGER NOT NULL DEFAULT 0,
            pv INTEGER NOT NULL DEFAULT 0, bn INTEGER NOT NULL DEFAULT 0, dur INTEGER NOT NULL DEFAULT 0, durn INTEGER NOT NULL DEFAULT 0, scr INTEGER NOT NULL DEFAULT 0, scrn INTEGER NOT NULL DEFAULT 0,
            conv INTEGER NOT NULL DEFAULT 0, x TEXT NOT NULL DEFAULT '', PRIMARY KEY (site_id, day, dim, k));
        CREATE TABLE IF NOT EXISTS rollup_days (site_id INTEGER NOT NULL, day TEXT NOT NULL, goals TEXT NOT NULL, computed_at INTEGER NOT NULL, PRIMARY KEY (site_id, day));
        PRAGMA user_version = 2;
    ");
}
function an_goal_sig(array $goals): string { return md5(json_encode(array_map(function ($g) { return [$g['id'], $g['kind'], $g['match']]; }, $goals))); }
// All summary rows for one day, computed from the raw events: [[dim, k, cols...], ...]
function an_day_rows(int $site, string $day, array $goals): array {
    $from = strtotime($day); $to = strtotime('+1 day', $from);
    $w = 'site_id = ? AND ts >= ? AND ts < ?'; $a = [$site, $from, $to];
    $rows = [];
    $add = function (string $dim, $k, array $c) use (&$rows) { $rows[] = array_merge(['dim' => $dim, 'k' => (string)$k, 'v' => 0, 's' => 0, 'pv' => 0, 'bn' => 0, 'dur' => 0, 'durn' => 0, 'scr' => 0, 'scrn' => 0, 'conv' => 0, 'x' => ''], $c); };
    $gParts = []; $gArgs = []; foreach ($goals as $g) { [$gs, $gx] = goal_sql($g); $gParts[] = $gs; array_push($gArgs, ...$gx); }
    $convWhere = $gParts ? ' AND (' . implode(' OR ', $gParts) . ')' : ' AND 0';
    $t = aone("SELECT COUNT(DISTINCT visitor) v, COUNT(DISTINCT session) s, COALESCE(SUM(type = 'pageview'), 0) pv FROM events WHERE $w", $a);
    if (!(int)$t['s']) return [];
    $b = aone("SELECT COALESCE(SUM(pv = 1 AND ev = 0), 0) bn, COALESCE(SUM(dur), 0) dur FROM (SELECT SUM(type = 'pageview') pv, SUM(type = 'event') ev, SUM(duration) dur FROM events WHERE $w GROUP BY session)", $a);
    $add('total', '', ['v' => (int)$t['v'], 's' => (int)$t['s'], 'pv' => (int)$t['pv'], 'bn' => (int)$b['bn'], 'dur' => (int)$b['dur'], 'conv' => (int)aval("SELECT COUNT(DISTINCT session) FROM events WHERE $w$convWhere", array_merge($a, $gArgs))]);
    foreach (AN_ROLL_DIMS as $dim) {
        $conv = []; foreach (aall("SELECT $dim k, COUNT(DISTINCT session) c FROM events WHERE $w$convWhere GROUP BY $dim", array_merge($a, $gArgs)) as $r) $conv[(string)$r['k']] = (int)$r['c'];
        foreach (aall("SELECT $dim k, COUNT(DISTINCT visitor) v, COUNT(DISTINCT session) s" . ($dim === 'campaign' ? ', MAX(source) src, MAX(medium) med' : '') . " FROM events WHERE $w GROUP BY $dim", $a) as $r)
            $add($dim, $r['k'], ['v' => (int)$r['v'], 's' => (int)$r['s'], 'conv' => $conv[(string)$r['k']] ?? 0, 'x' => $dim === 'campaign' ? json_encode([$r['src'], $r['med']]) : '']);
    }
    foreach (aall("SELECT path k, COUNT(DISTINCT visitor) v, COUNT(*) pv, SUM(CASE WHEN duration > 0 THEN duration ELSE 0 END) dur, SUM(duration > 0) durn, SUM(CASE WHEN scroll > 0 THEN scroll ELSE 0 END) scr, SUM(scroll > 0) scrn FROM events WHERE $w AND type = 'pageview' GROUP BY path", $a) as $r)
        $add('page', $r['k'], ['v' => (int)$r['v'], 'pv' => (int)$r['pv'], 'dur' => (int)$r['dur'], 'durn' => (int)$r['durn'], 'scr' => (int)$r['scr'], 'scrn' => (int)$r['scrn']]);
    foreach (aall("SELECT k, COUNT(*) s, SUM(bounced) bn FROM (SELECT MAX(CASE WHEN entry = 1 THEN path END) k, SUM(type = 'pageview') = 1 AND SUM(type = 'event') = 0 bounced FROM events WHERE $w GROUP BY session) WHERE k IS NOT NULL GROUP BY k", $a) as $r)
        $add('entry', $r['k'], ['s' => (int)$r['s'], 'bn' => (int)$r['bn']]);
    foreach (aall("SELECT name k, COUNT(*) pv, COUNT(DISTINCT visitor) v FROM events WHERE $w AND type = 'event' GROUP BY name", $a) as $r) {
        $vals = [];
        foreach (aall("SELECT props FROM events WHERE $w AND type = 'event' AND name = ? AND props != '' LIMIT 2000", array_merge($a, [$r['k']])) as $p) { $d = json_decode($p['props'], true) ?: []; $f = reset($d); if ($f !== false && $f !== '') $vals[$f] = ($vals[$f] ?? 0) + 1; }
        arsort($vals);
        $add('event', $r['k'], ['v' => (int)$r['v'], 'pv' => (int)$r['pv'], 'x' => json_encode(array_slice($vals, 0, 10, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    foreach ($goals as $g) { [$gs, $gx] = goal_sql($g); $x = aone("SELECT COUNT(DISTINCT session) s, COUNT(DISTINCT visitor) v, COUNT(*) pv FROM events WHERE $w AND $gs", array_merge($a, $gx)); if ($x['s']) $add('goal', $g['id'], ['s' => (int)$x['s'], 'v' => (int)$x['v'], 'pv' => (int)$x['pv']]); }
    foreach (aall("SELECT hour h, COUNT(DISTINCT session) s FROM events WHERE $w GROUP BY hour", $a) as $r) $add('heat', date('w', $from) . '-' . $r['h'], ['s' => (int)$r['s']]);
    foreach (['all' => '', 'mobile' => " AND device = 'Mobile'"] as $seg => $cond) foreach (AN_VITAL_STEP as $m => $step) {
        $hist = []; foreach (aall("SELECT ($m / $step) b, COUNT(*) n FROM events WHERE $w AND type = 'pageview' AND $m IS NOT NULL$cond GROUP BY b", $a) as $r) $hist[(int)$r['b']] = (int)$r['n'];
        if ($hist) $add('vital', "$seg:$m", ['pv' => array_sum($hist), 'x' => json_encode($hist)]);
    }
    return $rows;
}
// Makes sure every finished day in the range has an up-to-date summary.
function an_roll_ensure(int $site, int $from, int $to, array $goals): void {
    an_roll_schema();
    $sig = an_goal_sig($goals); $end = min($to, strtotime('today'));
    if ($from >= $end) return;
    $have = []; foreach (aall('SELECT day, goals, computed_at FROM rollup_days WHERE site_id = ? AND day >= ? AND day < ?', [$site, date('Y-m-d', $from), date('Y-m-d', $end)]) as $r) $have[$r['day']] = $r;
    $first = (int)aval('SELECT MIN(ts) FROM events WHERE site_id = ?', [$site]);
    @set_time_limit(300);
    for ($t = $from; $t < $end; $t = strtotime('+1 day', $t)) {
        $day = date('Y-m-d', $t); $h = $have[$day] ?? null;
        if ($h && $h['goals'] === $sig && (int)$h['computed_at'] >= strtotime('+1 day', $t) + 3600) continue; // late engagement updates settle within the hour
        if (!$first || strtotime('+1 day', $t) <= strtotime(date('Y-m-d', $first))) { aq('INSERT OR REPLACE INTO rollup_days (site_id, day, goals, computed_at) VALUES (?,?,?,?)', [$site, $day, $sig, time()]); continue; }
        $rows = an_day_rows($site, $day, $goals);
        an_db()->beginTransaction();
        aq('DELETE FROM rollups WHERE site_id = ? AND day = ?', [$site, $day]);
        $ins = an_db()->prepare('INSERT INTO rollups (site_id, day, dim, k, v, s, pv, bn, dur, durn, scr, scrn, conv, x) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) $ins->execute([$site, $day, $r['dim'], $r['k'], $r['v'], $r['s'], $r['pv'], $r['bn'], $r['dur'], $r['durn'], $r['scr'], $r['scrn'], $r['conv'], $r['x']]);
        aq('INSERT OR REPLACE INTO rollup_days (site_id, day, goals, computed_at) VALUES (?,?,?,?)', [$site, $day, $sig, time()]);
        an_db()->commit();
    }
}
// Summary rows for a range: stored days plus today, computed live. Returns rows keyed by day.
function an_roll_rows(int $site, int $from, int $to, array $goals): array {
    an_roll_ensure($site, $from, $to, $goals);
    $out = [];
    foreach (aall('SELECT * FROM rollups WHERE site_id = ? AND day >= ? AND day < ?', [$site, date('Y-m-d', $from), date('Y-m-d', min($to, strtotime('today')))]) as $r) $out[] = $r;
    if ($to > strtotime('today') && $from <= strtotime('today')) foreach (an_day_rows($site, date('Y-m-d'), $goals) as $r) $out[] = $r + ['day' => date('Y-m-d')];
    return $out;
}
function an_roll_totals(array $rows): array {
    $t = ['v' => 0, 's' => 0, 'pv' => 0, 'bn' => 0, 'dur' => 0, 'conv' => 0];
    foreach ($rows as $r) if ($r['dim'] === 'total') foreach ($t as $k => $_) $t[$k] += (int)$r[$k];
    return ['visitors' => $t['v'], 'visits' => $t['s'], 'pageviews' => $t['pv'], 'ppv' => $t['s'] ? $t['pv'] / $t['s'] : 0, 'bounce' => $t['s'] ? 100 * $t['bn'] / $t['s'] : 0,
        'duration' => $t['s'] ? $t['dur'] / $t['s'] : 0, 'conversions' => $t['conv'], 'cr' => $t['s'] ? 100 * $t['conv'] / $t['s'] : 0];
}
// The same report as an_report(), built from daily summaries. Used when nothing is filtered.
function an_report_fast(array $site, array $range): array {
    $goals = site_goals((int)$site['id']);
    $rows = an_roll_rows((int)$site['id'], $range['from'], $range['to'], $goals);
    $R = ['range' => $range, 'filters' => [], 'goals' => $goals, 'totals' => an_roll_totals($rows), 'prev' => an_roll_totals(an_roll_rows((int)$site['id'], $range['pfrom'], $range['pto'], $goals))];
    $by = [];
    foreach ($rows as $r) {
        $key = $r['dim'] . "\0" . $r['k'];
        if (!isset($by[$key])) { $by[$key] = $r; $by[$key]['xs'] = []; foreach (['v', 's', 'pv', 'bn', 'dur', 'durn', 'scr', 'scrn', 'conv'] as $c) $by[$key][$c] = 0; }
        foreach (['v', 's', 'pv', 'bn', 'dur', 'durn', 'scr', 'scrn', 'conv'] as $c) $by[$key][$c] += (int)$r[$c];
        if ($r['x'] !== '') $by[$key]['xs'][] = $r['x'];
    }
    $dim = function (string $d) use ($by) { return array_values(array_filter($by, function ($r) use ($d) { return $r['dim'] === $d; })); };
    $top = function (array $list, string $sort, int $n) { usort($list, function ($a, $b) use ($sort) { return $b[$sort] <=> $a[$sort] ?: strcmp($a['k'], $b['k']); }); return array_slice($list, 0, $n); };
    $series = []; $daily = [];
    foreach ($rows as $r) if ($r['dim'] === 'total') { $k = $range['bucket'] === 'month' ? substr($r['day'], 0, 7) : $r['day']; foreach (['v', 'pv', 's'] as $c) $daily[$k][$c] = ($daily[$k][$c] ?? 0) + (int)$r[$c]; }
    if ($range['bucket'] === 'month') for ($t = $range['from']; $t < $range['to']; $t = strtotime('+1 month', $t)) { $k = date('Y-m', $t); $series[] = [date('M Y', $t), $daily[$k]['v'] ?? 0, $daily[$k]['pv'] ?? 0, $daily[$k]['s'] ?? 0]; }
    else for ($t = $range['from']; $t < $range['to']; $t = strtotime('+1 day', $t)) { $k = date('Y-m-d', $t); $series[] = [date('D, M j', $t), $daily[$k]['v'] ?? 0, $daily[$k]['pv'] ?? 0, $daily[$k]['s'] ?? 0]; }
    $R['series'] = $series;
    $R['pages'] = array_map(function ($r) { return ['k' => $r['k'], 'views' => $r['pv'], 'visitors' => $r['v'], 'secs' => $r['durn'] ? $r['dur'] / $r['durn'] : null, 'scroll' => $r['scrn'] ? $r['scr'] / $r['scrn'] : null]; }, $top($dim('page'), 'v', 12));
    $R['entries'] = array_map(function ($r) { return ['k' => $r['k'], 'visits' => $r['s'], 'bounces' => $r['bn']]; }, $top($dim('entry'), 's', 10));
    foreach (['channel' => 10, 'source' => 12, 'campaign' => 10, 'country' => 10, 'region' => 10, 'city' => 10, 'device' => 4, 'browser' => 8, 'os' => 8] as $d => $lim) {
        $list = $dim($d);
        if (in_array($d, ['campaign', 'region', 'city'], true)) $list = array_values(array_filter($list, function ($r) { return $r['k'] !== ''; }));
        $R[$d] = array_map(function ($r) use ($d) { $o = ['k' => $r['k'], 'visitors' => $r['v'], 'visits' => $r['s'], 'conv' => $r['conv']]; if ($d === 'campaign') { $x = json_decode($r['xs'][0] ?? '[]', true) ?: ['', '']; $o['src'] = $x[0]; $o['med'] = $x[1]; } return $o; }, $top($list, 'v', $lim));
    }
    $R['events'] = array_map(function ($r) {
        $vals = []; foreach ($r['xs'] as $x) foreach (json_decode($x, true) ?: [] as $k => $n) $vals[$k] = ($vals[$k] ?? 0) + $n;
        arsort($vals); return ['k' => $r['k'], 'n' => $r['pv'], 'visitors' => $r['v'], 'top' => array_slice($vals, 0, 3, true)];
    }, $top($dim('event'), 'pv', 12));
    $gRows = []; foreach ($dim('goal') as $r) $gRows[$r['k']] = $r;
    $R['goal_rows'] = array_map(function ($g) use ($gRows, $R) { $r = $gRows[(string)$g['id']] ?? ['s' => 0, 'v' => 0, 'pv' => 0]; return ['goal' => $g, 'conversions' => $r['s'], 'visitors' => $r['v'], 'count' => $r['pv'], 'cr' => $R['totals']['visits'] ? 100 * $r['s'] / $R['totals']['visits'] : 0]; }, $goals);
    $R['vitals'] = ['all' => [], 'mobile' => []];
    foreach (['all', 'mobile'] as $seg) foreach (AN_VITAL_STEP as $m => $step) {
        $hist = []; foreach ($by["vital\0$seg:$m"]['xs'] ?? [] as $x) foreach (json_decode($x, true) ?: [] as $b => $n) $hist[(int)$b] = ($hist[(int)$b] ?? 0) + $n;
        if (!$hist) { $R['vitals'][$seg][$m] = null; continue; }
        ksort($hist); $total = array_sum($hist); $need = 0.75 * $total; $run = 0; $p75 = 0;
        foreach ($hist as $b => $n) { $run += $n; if ($run >= $need) { $p75 = (int)(($b + 0.5) * $step); break; } }
        $R['vitals'][$seg][$m] = ['p75' => $p75, 'n' => $total];
    }
    $R['heat'] = []; foreach ($dim('heat') as $r) { [$wd, $h] = array_map('intval', explode('-', $r['k'])); $R['heat'][$wd][$h] = ($R['heat'][$wd][$h] ?? 0) + $r['s']; }
    return $R;
}
