<?php
// Downloads an attachment, or shows an image embedded in an email.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/mail.php';
require_user();
$em = one('SELECT * FROM emails WHERE id = ?', [(int)get('id')]);
$raw = $em ? load_raw($em) : '';
$a = $raw !== '' ? (mime_parse($raw)['attachments'][(int)get('n')] ?? null) : null;
if (!$a) { http_response_code(404); exit('That attachment isn’t available. Open the email in webmail to get it.'); }
// Only plain pictures open in the browser. Everything else downloads, so a file can't run as a page here.
$show = in_array($a['type'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
header('Content-Type: ' . ($show ? $a['type'] : 'application/octet-stream'));
header('Content-Length: ' . strlen($a['data']));
header('Content-Disposition: ' . ($show ? 'inline' : 'attachment') . '; ' . mime_filename($a['name']));
header('Cache-Control: private, max-age=86400');
if ($show) header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox"); // (on downloads, browsers drop the file name)
echo $a['data'];
