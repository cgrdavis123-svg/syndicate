<?php
// Loads a picture from the internet for an email, when you choose "Show pictures".
// It's fetched by the server, so the sender sees your server rather than your own address and device.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/mail.php';
require_user();
session_write_close();
$u = (string)($_GET['u'] ?? '');
if (strpos($u, '//') === 0) $u = 'https:' . $u;
function fail(): void { http_response_code(404); header('Content-Type: image/gif'); echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); exit; }
if (!hash_equals(img_sig((string)($_GET['u'] ?? '')), (string)($_GET['s'] ?? ''))) fail();
// Only public web addresses: never this server or the private network behind it.
function public_ip(string $host): string {
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    foreach ($ips as $ip) if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    return '';
}
for ($hop = 0; $hop < 4; $hop++) {
    $p = parse_url($u);
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) fail();
    $ip = public_ip($p['host']);
    if ($ip === '' || !function_exists('curl_init')) fail();
    $port = (int)($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
    if (!in_array($port, [80, 443], true)) fail();
    $buf = ''; $type = ''; $loc = '';
    $ch = curl_init($u);
    curl_setopt_array($ch, [
        CURLOPT_RESOLVE => [$p['host'] . ':' . $port . ':' . $ip], CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SyndicateCRM image loader)',
        CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$type, &$loc) {
            if (stripos($h, 'content-type:') === 0) $type = strtolower(trim(explode(';', substr($h, 13))[0]));
            if (stripos($h, 'location:') === 0) $loc = trim(substr($h, 9));
            return strlen($h);
        },
        CURLOPT_WRITEFUNCTION => function ($ch, $d) use (&$buf) { $buf .= $d; return strlen($buf) > 5242880 ? 0 : strlen($d); },
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code >= 300 && $code < 400 && $loc !== '') { $u = preg_match('#^https?://#i', $loc) ? $loc : $p['scheme'] . '://' . $p['host'] . '/' . ltrim($loc, '/'); continue; }
    break;
}
if (($code ?? 0) !== 200 || !in_array($type, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp', 'image/x-icon'], true)) fail();
header('Content-Type: ' . $type);
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: inline');
header("Content-Security-Policy: default-src 'none'; sandbox");
echo $buf;
