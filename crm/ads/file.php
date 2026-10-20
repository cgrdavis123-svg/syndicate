<?php
// Serves an ad's image or video to staff, or (with &p=1) to the client it belongs to. Files live outside the web root.
require __DIR__ . '/../app/ads-boot.php';
$r = one('SELECT * FROM ad_creatives WHERE id = ?', [(int)get('id')]);
$ok = false;
if ($r && $r['file'] !== '') {
    if (get('p') === '1') { $c = portal_contact(); $ok = $c && $c['company_id'] && (int)val('SELECT company_id FROM ad_clients WHERE id = ?', [$r['client_id']]) === (int)$c['company_id'] && !in_array($r['status'], ['draft'], true); }
    else $ok = (bool)current_user();
}
$path = $ok ? ads_storage() . '/' . basename($r['file']) : '';
if (!$r) demo_miss();
if (!$ok || !is_file($path)) { http_response_code(404); exit('Not found'); }
$types = array_flip(AD_FILE_TYPES); $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$size = filesize($path); $start = 0; $end = $size - 1;
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="ad-' . (int)$r['id'] . '.' . $ext . '"');
// Videos are played in pieces (Safari insists), so answer byte-range requests.
if (preg_match('/^bytes=(\d*)-(\d*)$/', (string)($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
    if ($m[1] === '' && $m[2] !== '') { $start = max(0, $size - (int)$m[2]); } else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); }
    if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206); header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));
$fh = fopen($path, 'rb'); fseek($fh, $start); $left = $end - $start + 1;
while ($left > 0 && !feof($fh)) { $chunk = fread($fh, (int)min(262144, $left)); echo $chunk; $left -= strlen($chunk); flush(); }
fclose($fh);
