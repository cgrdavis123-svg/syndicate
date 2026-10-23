<?php
// Outreach agent API (JSON). Every call needs the key from CRM → Outreach: "Authorization: Bearer <key>".
//   GET  ?a=status                     on/off, problems, today's sends and limit, counts
//   POST ?a=check     {emails: [...]}  which addresses can't be emailed, and why
//   POST ?a=add       {batch, prospects: [{business, email, contact_name, website, city, niche, notes, best_fit, sku, priority, subject, body, followup1, followup2}]}
//   GET  ?a=pending                    replies waiting for the agent, with the conversation so far
//   POST ?a=respond   {id, action: reply|interested|decline|handoff|resume, body, note}
//   POST ?a=checkup   {url, business}  free website checkup → report link, score and findings to mention in the email
//   POST ?a=preview   {business, niche, city, phone, email, website, contact_name, tagline, about, services[], areas[], hours[], color, theme}
//                     a free homepage concept from their public details → preview link (30 days, labelled as a concept)
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
if ($a === 'checkup' && is_post()) {
    require_once __DIR__ . '/app/checkup.php';
    if (too_many('checkup_agent', 60, 1440)) out_reply(429, ['ok' => false, 'error' => 'The agent has run 60 checkups today. Try again tomorrow.']);
    record_attempt('checkup_agent');
    $r = ck_run((string)($in['url'] ?? ''), 'agent', (string)($in['business'] ?? ''));
    if (!$r['ok']) out_reply(422, $r);
    $pub = ck_public(ck_row($r['token']));
    out_reply(200, ['ok' => true, 'report_url' => ck_report_url(ck_row($r['token'])), 'host' => $pub['host'], 'score' => $pub['score'], 'platform' => $pub['platform'],
        'findings' => array_map(function ($x) { return ['level' => $x['level'], 'area' => $x['area'], 'title' => $x['title']]; }, $pub['findings']), 'fixes' => array_column($pub['fixes'], 'sku'),
        'note' => 'The report link works as is; add &utm_source=outreach if you like. Mention one or two real findings, never the score alone, and never claim more than the report says.']);
}
if ($a === 'preview' && is_post()) {
    require_once __DIR__ . '/app/previews.php';
    if (too_many('preview_agent', 40, 1440)) out_reply(429, ['ok' => false, 'error' => 'The agent has made 40 previews today. Try again tomorrow.']);
    $email = strtolower(trim((string)($in['email'] ?? '')));
    if ($email !== '' && ($why = out_blocked($email)) && !one('SELECT 1 FROM outreach_prospects WHERE email = ?', [$email])) out_reply(422, ['ok' => false, 'error' => "won’t make one: $why"]);
    record_attempt('preview_agent');
    $r = pv_create($in, 'agent');
    if (!$r['ok']) out_reply(422, $r);
    out_reply(200, $r + ['note' => 'Link it as a free concept we made for them, e.g. “I mocked up what a new homepage could look like for you: <link>”. Say it’s a concept, never that it’s live or theirs. Use only details they publish themselves: no logos, photos, reviews, prices or claims. It expires in ' . PV_DAYS . ' days and they can remove it themselves.']);
}
out_reply(404, ['ok' => false, 'error' => 'Unknown action.']);
