<?php
// Builder agent API (JSON). Uses the same agent key as outreach-api.php: "Authorization: Bearer <key>" or "X-Api-Key".
//   GET  ?a=status                       on/off, what's queued, the upload limit
//   POST ?a=next                         claims the next build that's ready or has changes → the job (brief, notes, files, previous draft)
//   GET  ?a=file&build=&id=              one of the client's portal files for a build you've claimed
//   GET  ?a=source&build=&v=             the source zip of an earlier draft
//   POST ?a=start    {build}             starts a new version → {version}
//   POST ?a=upload   multipart: build, version, paths[], files[]   built site files (the dist folder), in batches under the upload limit
//   POST ?a=source   multipart: build, version, source             the project source as one .zip (no node_modules, no dist)
//   POST ?a=finish   {build, version, summary, confirm: [...]}     marks the draft ready for Grady to review → preview link
//   POST ?a=fail     {build, question}   stop and hand it to Grady with what's missing
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/builds.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
function bapi(int $code, array $d): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$hash = setting('outreach_api_hash');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$key = preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m) ? $m[1] : trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if (too_many('build_api', 10, 15)) bapi(429, ['ok' => false, 'error' => 'Too many failed attempts. Try again later.']);
if ($hash === '' || $key === '') bapi(401, ['ok' => false, 'error' => 'Send the agent key from CRM → Outreach in an Authorization: Bearer header.']);
if (!hash_equals($hash, hash('sha256', $key))) { record_attempt('build_api'); bapi(401, ['ok' => false, 'error' => 'Wrong key.']); }

$a = get('a');
$json = [];
if (is_post() && stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) { $json = json_decode((string)file_get_contents('php://input'), true); if (!is_array($json)) bapi(400, ['ok' => false, 'error' => 'Send valid JSON.']); }
$in = function (string $k) use ($json) { return $json[$k] ?? $_POST[$k] ?? null; };

if ($a === 'status') {
    $c = []; foreach (all('SELECT status, COUNT(*) n FROM builds GROUP BY status') as $r) $c[$r['status']] = (int)$r['n'];
    bapi(200, ['ok' => true, 'on' => build_on(), 'counts' => $c, 'upload_limit_bytes' => upload_limit(), 'max_files' => BUILD_MAX_FILES, 'max_bytes' => BUILD_MAX_BYTES, 'allowed_types' => array_keys(BUILD_TYPES)]);
}
if ($a === 'next' && is_post()) {
    if (!build_on()) bapi(200, ['ok' => true, 'job' => null, 'note' => 'The builder is switched off in CRM → Website drafts.']);
    $job = build_claim();
    bapi(200, ['ok' => true, 'job' => $job] + ($job ? [] : ['note' => 'Nothing to build right now.']));
}
if ($a === 'file' && !is_post()) {
    $b = build_claimed((int)get('build'));
    $f = $b ? one('SELECT * FROM project_files WHERE id = ? AND project_id = ?', [(int)get('id'), $b['project_id']]) : null;
    if (!$f) bapi(404, ['ok' => false, 'error' => 'No such file for a build you’ve claimed.']);
    require_once __DIR__ . '/app/portal.php';
    send_project_file($f);
}
if ($a === 'source' && !is_post()) {
    $b = build_row((int)get('build'));
    $v = $b && in_array($b['status'], ['building'], true) ? one("SELECT * FROM build_versions WHERE build_id = ? AND version = ? AND status = 'ready'", [$b['id'], (int)get('v')]) : null;
    if (!$v || $v['source'] === '' || !is_file(DATA . '/' . $v['source'])) bapi(404, ['ok' => false, 'error' => 'No source for that draft.']);
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize(DATA . '/' . $v['source']));
    header('Content-Disposition: attachment; filename="build-' . (int)$b['id'] . '-v' . (int)$v['version'] . '-source.zip"');
    readfile(DATA . '/' . $v['source']);
    exit;
}
if (!is_post()) bapi(404, ['ok' => false, 'error' => 'Unknown action.']);
$b = build_claimed((int)$in('build'));
if (!$b) bapi(409, ['ok' => false, 'error' => 'That build isn’t claimed (it may have timed out after ' . BUILD_STALE_HOURS . ' hours, or been held). Call ?a=next again.']);
if ($a === 'start') { $v = build_start_version($b); bapi(200, ['ok' => true, 'version' => (int)$v['version']]); }
if ($a === 'fail') { $r = build_fail($b, (string)$in('question')); bapi($r['ok'] ? 200 : 400, $r); }
$v = build_uploading($b, (int)$in('version'));
if (!$v) bapi(409, ['ok' => false, 'error' => 'Start the version first with ?a=start.']);
if ($a === 'upload') {
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) bapi(413, ['ok' => false, 'error' => 'That batch is larger than the upload limit (' . upload_limit() . ' bytes). Send fewer files at a time.']);
    [$n, $errors] = build_add_files($v, (array)($_POST['paths'] ?? []));
    $v = build_uploading($b, (int)$v['version']);
    bapi($errors && !$n ? 400 : 200, ['ok' => !$errors, 'saved' => $n, 'errors' => $errors, 'files' => (int)$v['files'], 'bytes' => (int)$v['bytes']]);
}
if ($a === 'source') { $err = build_add_source($v); bapi($err ? 400 : 200, ['ok' => $err === ''] + ($err ? ['error' => $err] : [])); }
if ($a === 'finish') { $r = build_finish($b, $v, (string)$in('summary'), (array)($in('confirm') ?? [])); bapi($r['ok'] ? 200 : 400, $r); }
bapi(404, ['ok' => false, 'error' => 'Unknown action.']);
