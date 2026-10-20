<?php
// Streams an applicant's resume to signed-in admins only.
require __DIR__ . '/../app/bootstrap.php';
require_admin();
$st = db()->prepare('SELECT resume_file, resume_name, name FROM applications WHERE id = ?');
$st->execute([(int)get('id')]);
$r = $st->fetch();
$path = $r ? STORAGE . '/resumes/' . basename($r['resume_file']) : '';
if (!$r || !$r['resume_file'] || !is_file($path)) { http_response_code(404); exit('Resume not found.'); }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$download = preg_replace('/[^\w.\- ]+/', '_', $r['name'] . ' - Resume.' . $ext);
$inline = get('view') === '1' && $ext === 'pdf';

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $download . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
readfile($path);
