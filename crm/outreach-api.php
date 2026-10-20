<?php
// Outreach agent API (JSON). Every call needs the key from CRM → Outreach: "Authorization: Bearer <key>".
//   GET  ?a=status                     on/off, problems, today's sends and limit, counts
//   POST ?a=check     {emails: [...]}  which addresses can't be emailed, and why
//   POST ?a=add       {batch, prospects: [{business, email, contact_name, website, city, niche, notes, best_fit, sku, priority, subject, body, followup1, followup2}]}
//   GET  ?a=pending                    replies waiting for the agent, with the conversation so far
//   POST ?a=respond   {id, action: reply|interested|decline|handoff|resume, body, note}
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/outreach.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
function out_reply(int $code, array $d): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

$hash = setting('outreach_api_hash');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$key = preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m) ? $m[1] : trim((string)($_SERVER['HTTP_X_API_KEY'] ?? '')); // X-Api-Key works too
if (too_many('outreach_api', 10, 15)) out_reply(429, ['ok' => false, 'error' => 'Too many failed attempts. Try again later.']);
if ($hash === '') out_reply(401, ['ok' => false, 'error' => 'No agent key has been made yet. CRM → Outreach → Make a key.']);
if ($key === '') out_reply(401, ['ok' => false, 'error' => 'No key received. The request had no Authorization or X-Api-Key header' . ($auth === '' ? '' : ' in the expected form') . '.']);
if (!hash_equals($hash, hash('sha256', $key))) { record_attempt('outreach_api'); out_reply(401, ['ok' => false, 'error' => 'Wrong key. Make sure the key in the cloud environment matches the newest one from CRM → Outreach.']); }

$a = get('a');
$in = [];
if (is_post()) { $in = json_decode((string)file_get_contents('php://input'), true); if (!is_array($in)) out_reply(400, ['ok' => false, 'error' => 'Send JSON.']); }

if ($a === 'status') out_reply(200, ['ok' => true] + out_status());
if ($a === 'check' && is_post()) {
    $res = [];
    foreach (array_slice((array)($in['emails'] ?? []), 0, 200) as $e) { $e = strtolower(trim((string)$e)); $res[$e] = filter_var($e, FILTER_VALIDATE_EMAIL) ? (out_blocked($e) ?: 'ok') : 'not a valid email'; }
    out_reply(200, ['ok' => true, 'emails' => $res]);
}
if ($a === 'add' && is_post()) {
    $list = (array)($in['prospects'] ?? []);
    if (count($list) > 40) out_reply(400, ['ok' => false, 'error' => 'Send at most 40 prospects at a time.']);
    $batch = mb_substr(trim((string)($in['batch'] ?? '')), 0, 80);
    $res = array_map(function ($p) use ($batch) { return out_add(is_array($p) ? $p : [], $batch); }, $list);
    out_reply(200, ['ok' => true, 'added' => count(array_filter($res, function ($r) { return $r['ok']; })), 'results' => $res] + out_status());
}
if ($a === 'pending') out_reply(200, ['ok' => true, 'pending' => out_pending()]);
if ($a === 'respond' && is_post()) {
    $r = out_respond((int)($in['id'] ?? 0), (string)($in['action'] ?? ''), (string)($in['body'] ?? ''), (string)($in['note'] ?? ''));
    out_reply($r['ok'] ? 200 : 400, $r);
}
out_reply(404, ['ok' => false, 'error' => 'Unknown action.']);
