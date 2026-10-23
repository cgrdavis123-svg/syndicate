<?php
// Website drafts made by the builder agent. A paid website order (or a won quote with a website package) gets one build.
// It waits until the kickoff call has happened (or you mark it ready), then the builder agent claims it through
// build-api.php, builds the site from the brief, the client's files and your notes, and uploads it as a version.
// You review the private preview, send it to the client for approval in their portal, and their Approve or
// Request changes moves the build on: changes go back to the agent, within the rounds the package includes.
// Nothing reaches the client until you send it, and nothing goes live from here: putting the site on its domain stays with you.
// Agent: build-api.php. Staff: builds.php, build.php. Previews: draft/<token>/. Migration v30.
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/shop.php';

const BUILD_STATUSES = [
    'waiting' => 'Waiting for kickoff', 'ready' => 'Ready for the builder', 'building' => 'Being built', 'review' => 'Draft ready for you',
    'sent' => 'With the client', 'changes' => 'Changes to make', 'blocked' => 'Needs you', 'approved' => 'Approved', 'cancelled' => 'Cancelled',
];
const BUILD_ROUNDS = ['web-one-page' => 2, 'web-starter' => 2, 'web-business' => 3, 'web-store' => 3];
const BUILD_STALE_HOURS = 8;     // a claim older than this goes back to the queue
const BUILD_MAX_ATTEMPTS = 3;    // then it needs you
const BUILD_KICKOFF_WAIT = 3;    // hours after the kickoff call ends before the builder may start (time for your notes)
const BUILD_MAX_FILES = 800;
const BUILD_MAX_BYTES = 60 * 1048576;
const BUILD_MAX_FILE = 12 * 1048576;
const BUILD_TYPES = [
    'html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'mjs' => 'text/javascript; charset=utf-8',
    'json' => 'application/json', 'webmanifest' => 'application/manifest+json', 'xml' => 'application/xml', 'txt' => 'text/plain; charset=utf-8',
    'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
    'woff2' => 'font/woff2', 'woff' => 'font/woff', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf',
];

function build_on(): bool { return setting('build_on') !== '0'; }
function build_row(int $id): ?array { return one('SELECT * FROM builds WHERE id = ?', [$id]); }
function build_for_project(int $pid): ?array { return one('SELECT * FROM builds WHERE project_id = ?', [$pid]); }
function build_versions(int $bid): array { return all("SELECT * FROM build_versions WHERE build_id = ? AND status = 'ready' ORDER BY version DESC", [$bid]); }
function build_latest(int $bid): ?array { return one("SELECT * FROM build_versions WHERE build_id = ? AND status = 'ready' ORDER BY version DESC LIMIT 1", [$bid]); }
function build_dir(array $v): string { return DATA . '/builds/' . (int)$v['build_id'] . '/v' . (int)$v['version']; }
function build_preview_url(array $v): string { return abs_url('draft/' . $v['token'] . '/'); }
function build_label(string $s): string { return BUILD_STATUSES[$s] ?? $s; }
function build_pill(string $s): string {
    $tone = ['review' => 'lime', 'blocked' => 'red', 'changes' => 'blue', 'approved' => 'lime', 'sent' => 'blue'][$s] ?? '';
    return '<span class="tag ' . $tone . '">' . e(build_label($s)) . '</span>';
}
function build_set(int $id, array $f): void {
    $f['updated_at'] = time();
    q('UPDATE builds SET ' . implode(', ', array_map(function ($k) { return "$k = ?"; }, array_keys($f))) . ' WHERE id = ?', array_merge(array_values($f), [$id]));
}
function build_project(array $b): ?array {
    return one('SELECT p.*, co.name company, c.first_name, c.last_name, c.email, c.phone FROM projects p LEFT JOIN companies co ON co.id = p.company_id LEFT JOIN contacts c ON c.id = p.contact_id WHERE p.id = ?', [$b['project_id']]);
}
function build_who(array $b): string { return $b['business'] !== '' ? $b['business'] : ((string)val('SELECT name FROM projects WHERE id = ?', [$b['project_id']]) ?: 'a client'); }
function build_notify(string $subject, string $body): void { send_mail(setting('shop_notify') ?: setting('business_email'), '[Website draft] ' . $subject, $body); }
function build_task(array $b, string $title, int $prio = 1, int $days = 0): void {
    if (one('SELECT 1 FROM tasks WHERE done_at IS NULL AND entity_type = ? AND entity_id = ? AND title = ?', ['project', $b['project_id'], $title])) return;
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [$title, date('Y-m-d', strtotime("+$days days")), $prio, 'project', $b['project_id'], now()]);
}

/* ---------- Creating builds ---------- */
// One build per website project. $extras: the add-ons in the same order (sku, name, quantity), so the builder knows about extra pages, copy, a press kit…
function build_create(int $projectId, string $sku, array $o): ?int {
    if (!isset(BUILD_ROUNDS[$sku]) || build_for_project($projectId)) return null;
    q('INSERT INTO builds (project_id, order_id, quote_id, contact_id, company_id, sku, business, brief, extras, status, rounds, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $projectId, $o['order_id'] ?? null, $o['quote_id'] ?? null, $o['contact_id'] ?? null, $o['company_id'] ?? null, $sku, mb_substr((string)($o['business'] ?? ''), 0, 160),
        json_encode($o['brief'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode(array_values($o['extras'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'waiting', BUILD_ROUNDS[$sku], time(), time()]);
    $id = (int)db()->lastInsertId();
    if (!empty($o['order_id']) || !empty($o['quote_id'])) {
        q("INSERT INTO project_posts (project_id, author, kind, body, created_at) VALUES (?, 'staff', 'update', ?, ?)", [$projectId,
            "Start here: tell us about your business in your kickoff form. It takes about 10 minutes, and we start your website as soon as it’s in:\n" . abs_url('portal-kickoff.php?id=' . $projectId)
            . "\n\nRather talk it through? Book a free kickoff call instead, using the link in your order email.", now()]);
    }
    return $id;
}
// From a paid store order (shop_fulfil).
function build_from_order(array $o, array $items, array $brief, ?int $contactId, ?int $companyId): void {
    $extras = [];
    foreach ($items as $i) if ($i['category'] === 'addon') $extras[] = ['sku' => $i['sku'], 'name' => $i['name'], 'quantity' => (int)$i['quantity']];
    foreach ($items as $i) if ($i['category'] === 'website' && $i['project_id']) {
        build_create((int)$i['project_id'], $i['sku'], ['order_id' => (int)$o['id'], 'contact_id' => $contactId, 'company_id' => $companyId, 'business' => $o['business'] ?: $o['name'], 'brief' => $brief, 'extras' => $extras]);
    }
}
// From a won quote: the first website package in it.
function build_from_quote(array $q, array $items, ?int $projectId, ?int $companyId, string $client): void {
    if (!$projectId) return;
    $sku = ''; $extras = [];
    foreach ($items as $i) {
        if (!$i['product_id']) continue;
        $p = one('SELECT sku, category, name FROM products WHERE id = ?', [$i['product_id']]);
        if (!$p) continue;
        if ($p['category'] === 'website' && $sku === '') $sku = $p['sku'];
        if ($p['category'] === 'addon') $extras[] = ['sku' => $p['sku'], 'name' => $p['name'], 'quantity' => (int)$i['quantity']];
    }
    if ($sku === '') return;
    $brief = array_filter(['notes' => trim((string)($q['intro'] ?? '')), 'domain' => (string)($q['website'] ?? '')], 'strlen');
    build_create($projectId, $sku, ['quote_id' => (int)$q['id'], 'contact_id' => $q['contact_id'] ? (int)$q['contact_id'] : null, 'company_id' => $companyId, 'business' => $client, 'brief' => $brief, 'extras' => $extras]);
}

/* ---------- Cron: kickoff readiness, stuck claims ---------- */
function builds_cron(): string {
    if (!build_on()) return '';
    $out = [];
    foreach (all("SELECT * FROM builds WHERE status = 'waiting' AND contact_id IS NOT NULL") as $b) {
        $k = one("SELECT b.starts_at, b.minutes FROM bookings b JOIN meeting_types t ON t.id = b.type_id WHERE t.kind = 'kickoff' AND b.status = 'confirmed' AND b.contact_id = ? AND b.starts_at > ? ORDER BY b.starts_at DESC LIMIT 1",
            [$b['contact_id'], $b['created_at'] - 30 * 86400]);
        if ($k && (int)$k['starts_at'] + (int)$k['minutes'] * 60 + BUILD_KICKOFF_WAIT * 3600 <= time()) {
            build_set((int)$b['id'], ['status' => 'ready', 'ready_at' => time()]);
            $out[] = 'ready: ' . build_who($b);
        }
    }
    foreach (all("SELECT * FROM builds WHERE status = 'building' AND claimed_at < ?", [time() - BUILD_STALE_HOURS * 3600]) as $b) {
        q("DELETE FROM build_versions WHERE build_id = ? AND status = 'uploading'", [$b['id']]);
        if ((int)$b['attempts'] >= BUILD_MAX_ATTEMPTS) build_block($b, 'The builder started this draft ' . (int)$b['attempts'] . ' times without finishing. Check the notes and the brief, then mark it ready again.');
        else build_set((int)$b['id'], ['status' => $b['change_notes'] !== '' && (int)$b['version'] > 0 ? 'changes' : 'ready', 'claimed_at' => null]);
    }
    if ($r = kickoff_reminders()) $out[] = $r . ' kickoff form reminder' . ($r === 1 ? '' : 's');
    return $out ? 'Website drafts ' . implode(', ', $out) : '';
}
function build_block(array $b, string $question): void {
    build_set((int)$b['id'], ['status' => 'blocked', 'question' => mb_substr($question, 0, 4000), 'claimed_at' => null]);
    build_task($b, 'Website draft needs you: ' . build_who($b));
    build_notify('Needs you: ' . build_who($b), $question . "\n\nOpen it: " . abs_url('build.php?id=' . $b['id']) . "\n");
}

/* ---------- The agent's side ---------- */
// Claims the oldest build that's ready or has changes to make. Returns the job, or null.
function build_claim(): ?array {
    if (!build_on()) return null;
    $b = one("SELECT * FROM builds WHERE status IN ('ready','changes') ORDER BY CASE status WHEN 'changes' THEN 0 ELSE 1 END, updated_at LIMIT 1");
    if (!$b) return null;
    $claim = q("UPDATE builds SET status = 'building', claimed_at = ?, attempts = attempts + 1, updated_at = ? WHERE id = ? AND status IN ('ready','changes')", [time(), time(), $b['id']]);
    if ($claim->rowCount() === 0) return null;
    return build_job(build_row((int)$b['id']));
}
function build_job(array $b): array {
    $p = build_project($b) ?: [];
    $prod = one('SELECT * FROM products WHERE sku = ?', [$b['sku']]) ?: [];
    $site = $p ? one('SELECT public_id, domains FROM sites WHERE company_id IS NOT NULL AND company_id = ? ORDER BY id DESC LIMIT 1', [(int)$p['company_id']]) : null;
    $files = $p ? all('SELECT id, name, type, size, author, created_at FROM project_files WHERE project_id = ? ORDER BY id', [$p['id']]) : [];
    $posts = $p ? all("SELECT author, kind, body, approval, decision_note, created_at FROM project_posts WHERE project_id = ? AND kind != 'status' ORDER BY id DESC LIMIT 30", [$p['id']]) : [];
    $prev = build_latest((int)$b['id']);
    return [
        'build' => (int)$b['id'], 'next_version' => (int)$b['version'] + 1, 'round' => $prev ? 'changes' : 'first draft',
        'package' => ['sku' => $b['sku'], 'name' => $prod['name'] ?? $b['sku'], 'features' => product_lines((string)($prod['features'] ?? '')), 'rounds_included' => (int)$b['rounds'], 'rounds_used' => (int)$b['rounds_used']],
        'extras' => json_decode($b['extras'], true) ?: [],
        'business' => build_who($b),
        'client' => ['name' => $p ? person_name($p) : '', 'company' => (string)($p['company'] ?? '')],
        'publish' => $b['publish'] !== '' ? $b['publish'] : 'Nothing approved yet: mark every phone number, email and address as class="fill" for us to confirm.',
        'brief' => json_decode($b['brief'], true) ?: [],
        'kickoff_form' => kickoff_answers($b) ?: null,
        'notes_from_us' => $b['notes'],
        'changes_to_make' => $prev ? $b['change_notes'] : '',
        'site_stats_key' => (string)($site['public_id'] ?? ''), 'domain' => (string)($site['domains'] ?? ''),
        'deadline' => (string)($p['due_date'] ?? ''),
        'client_files' => array_map(function ($f) use ($b) { return $f + ['url' => abs_url('build-api.php?a=file&build=' . $b['id'] . '&id=' . $f['id'])]; }, $files),
        'portal_messages' => array_reverse($posts),
        'previous' => $prev ? ['version' => (int)$prev['version'], 'preview' => build_preview_url($prev), 'source_zip' => $prev['source'] !== '' ? abs_url('build-api.php?a=source&build=' . $b['id'] . '&v=' . $prev['version']) : '', 'summary' => $prev['summary']] : null,
        'upload_limit_bytes' => upload_limit(),
    ];
}
function build_claimed(int $id): ?array { $b = build_row($id); return $b && $b['status'] === 'building' ? $b : null; }
function build_start_version(array $b): array {
    q("DELETE FROM build_versions WHERE build_id = ? AND status = 'uploading'", [$b['id']]);
    $n = (int)$b['version'] + 1;
    $token = bin2hex(random_bytes(16));
    q('INSERT INTO build_versions (build_id, version, token, created_at) VALUES (?,?,?,?)', [$b['id'], $n, $token, time()]);
    $v = one('SELECT * FROM build_versions WHERE id = ?', [(int)db()->lastInsertId()]);
    $dir = build_dir($v);
    build_rmdir($dir);
    @mkdir($dir . '/site', 0700, true);
    return $v;
}
function build_uploading(array $b, int $n): ?array { return one("SELECT * FROM build_versions WHERE build_id = ? AND version = ? AND status = 'uploading'", [$b['id'], $n]); }
// A safe relative path inside the site, or '' when it isn't one.
function build_clean_path(string $p): string {
    $p = str_replace('\\', '/', trim($p));
    $p = ltrim($p, '/');
    if ($p === '' || strlen($p) > 200 || !preg_match('#^[A-Za-z0-9._/-]+$#', $p)) return '';
    foreach (explode('/', $p) as $seg) if ($seg === '' || $seg === '.' || $seg === '..' || $seg[0] === '.') return '';
    return isset(BUILD_TYPES[strtolower(pathinfo($p, PATHINFO_EXTENSION))]) ? $p : '';
}
// Saves uploaded site files ($_FILES['files'] with matching paths[]). Returns [saved, errors].
function build_add_files(array $v, array $paths): array {
    $saved = 0; $errors = [];
    $count = (int)$v['files']; $bytes = (int)$v['bytes'];
    foreach ($_FILES['files']['name'] ?? [] as $i => $name) {
        $raw = (string)($paths[$i] ?? $name);
        $path = build_clean_path($raw);
        $err = $_FILES['files']['error'][$i];
        if ($path === '') { $errors[] = "$raw: not an allowed path or file type"; continue; }
        if ($err !== UPLOAD_ERR_OK) { $errors[] = "$path: upload error $err"; continue; }
        $size = (int)$_FILES['files']['size'][$i];
        if ($size > BUILD_MAX_FILE) { $errors[] = "$path: larger than " . fmt_bytes(BUILD_MAX_FILE); continue; }
        if ($count + 1 > BUILD_MAX_FILES || $bytes + $size > BUILD_MAX_BYTES) { $errors[] = "$path: the draft would pass " . BUILD_MAX_FILES . ' files or ' . fmt_bytes(BUILD_MAX_BYTES); continue; }
        $to = build_dir($v) . '/site/' . $path;
        $existed = is_file($to); $old = $existed ? filesize($to) : 0;
        if (!is_dir(dirname($to))) @mkdir(dirname($to), 0700, true);
        if (!@move_uploaded_file($_FILES['files']['tmp_name'][$i], $to)) { $errors[] = "$path: couldn’t be saved"; continue; }
        if (!$existed) $count++;
        $bytes += $size - $old;
        $saved++;
    }
    q('UPDATE build_versions SET files = ?, bytes = ? WHERE id = ?', [$count, $bytes, $v['id']]);
    return [$saved, $errors];
}
function build_add_source(array $v): string {
    $f = $_FILES['source'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) return 'No source zip received.';
    if ($f['size'] > BUILD_MAX_BYTES) return 'The source zip is larger than ' . fmt_bytes(BUILD_MAX_BYTES) . '.';
    $head = (string)@file_get_contents($f['tmp_name'], false, null, 0, 4);
    if ($head !== "PK\x03\x04") return 'The source must be a .zip file.';
    $rel = 'builds/' . (int)$v['build_id'] . '/v' . (int)$v['version'] . '/source.zip';
    if (!@move_uploaded_file($f['tmp_name'], DATA . '/' . $rel)) return 'The source zip couldn’t be saved.';
    q('UPDATE build_versions SET source = ? WHERE id = ?', [$rel, $v['id']]);
    return '';
}
function build_finish(array $b, array $v, string $summary, array $confirm): array {
    if (!is_file(build_dir($v) . '/site/index.html')) return ['ok' => false, 'error' => 'The draft has no index.html at its root.'];
    $summary = mb_substr(trim($summary), 0, 8000);
    if ($summary === '') return ['ok' => false, 'error' => 'Send a summary: what you built, what you assumed, and anything left to confirm.'];
    $confirm = array_values(array_filter(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 300); }, $confirm), 'strlen'));
    q("UPDATE build_versions SET status = 'ready', summary = ?, confirm = ?, done_at = ? WHERE id = ?", [$summary, json_encode(array_slice($confirm, 0, 60), JSON_UNESCAPED_UNICODE), time(), $v['id']]);
    // Older drafts stay viewable; only the newest is offered to send.
    build_set((int)$b['id'], ['status' => 'review', 'version' => (int)$v['version'], 'claimed_at' => null, 'attempts' => 0, 'question' => '']);
    $who = build_who($b);
    build_task($b, 'Review website draft v' . (int)$v['version'] . ' for ' . $who);
    build_notify("Draft v{$v['version']} ready: $who", "The builder finished draft v{$v['version']} for $who.\n\nPreview: " . build_preview_url($v) . "\nReview and send it: " . abs_url('build.php?id=' . $b['id'])
        . "\n\nWhat it says it did:\n$summary\n" . ($confirm ? "\nTo confirm before it goes to the client:\n- " . implode("\n- ", $confirm) . "\n" : ''));
    log_activity('project', (int)$b['project_id'], 'note', "Website draft v{$v['version']} ready for review.");
    return ['ok' => true, 'preview' => build_preview_url($v), 'version' => (int)$v['version']];
}
function build_fail(array $b, string $question): array {
    q("DELETE FROM build_versions WHERE build_id = ? AND status = 'uploading'", [$b['id']]);
    $question = trim($question);
    if ($question === '') return ['ok' => false, 'error' => 'Say what’s missing or what went wrong.'];
    build_block($b, "The builder stopped and asks:\n\n" . $question);
    return ['ok' => true];
}

/* ---------- Your side ---------- */
function build_send(array $b, string $message): array {
    $v = build_latest((int)$b['id']);
    $p = build_project($b);
    if (!$v || !$p) return ['ok' => false, 'error' => 'There’s no draft to send yet.'];
    if (!$p['shared']) return ['ok' => false, 'error' => 'This project is hidden from the client portal. Share it first (Edit project).'];
    $round = (int)$b['rounds_used'];
    $body = trim($message) . "\n\nPreview: " . build_preview_url($v)
        . "\n\nThis is a private draft: it isn’t live, and its forms don’t send. Look through it on your phone and computer, then press Approve, or Request changes and tell us everything you’d like different in one note. "
        . 'Your package includes ' . (int)$b['rounds'] . ' rounds of changes' . ($round > 0 ? ' and this is the result of round ' . $round : '') . '.';
    q("INSERT INTO project_posts (project_id, author, kind, body, approval, created_at) VALUES (?, 'staff', 'approval', ?, 'pending', ?)", [$p['id'], ltrim($body), now()]);
    $postId = (int)db()->lastInsertId();
    q('UPDATE build_versions SET post_id = ? WHERE id = ?', [$postId, $v['id']]);
    // An older draft still waiting for a decision no longer is.
    q("UPDATE project_posts SET kind = 'update', approval = '' WHERE project_id = ? AND id != ? AND approval = 'pending' AND id IN (SELECT post_id FROM build_versions WHERE build_id = ? AND post_id IS NOT NULL)", [$p['id'], $postId, $b['id']]);
    if ($p['status'] === 'planning' || $p['status'] === 'active') q("UPDATE projects SET status = 'review', updated_at = ? WHERE id = ?", [now(), $p['id']]);
    $n = notify_clients($p, 'Your website draft is ready to review', "Your website draft is ready for you to look over.\n\n" . build_preview_url($v) . "\n\nApprove it or ask for changes in your portal.");
    build_set((int)$b['id'], ['status' => 'sent']);
    q("UPDATE tasks SET done_at = ? WHERE done_at IS NULL AND entity_type = 'project' AND entity_id = ? AND title LIKE 'Review website draft%'", [now(), $p['id']]);
    if ($p['contact_id']) log_activity('contact', (int)$p['contact_id'], 'note', 'Portal · ' . $p['name'] . ": sent website draft v{$v['version']} for approval.");
    return ['ok' => true, 'emailed' => $n];
}
// The client approved or asked for changes on a post (portal-project.php). Moves the build on if the post was a draft.
function build_decided(int $postId, string $decision, string $note): void {
    $v = one('SELECT * FROM build_versions WHERE post_id = ?', [$postId]);
    if (!$v || !($b = build_row((int)$v['build_id'])) || $b['status'] !== 'sent') return;
    $who = build_who($b);
    if ($decision === 'approve') {
        build_set((int)$b['id'], ['status' => 'approved', 'change_notes' => '']);
        build_task($b, "Put {$who}’s approved website live (draft v{$v['version']})", 1, 1);
        build_notify("Approved: $who", "$who approved draft v{$v['version']}.\n\nDownload the source and put it live: " . abs_url('build.php?id=' . $b['id']) . "\n");
        return;
    }
    $used = (int)$b['rounds_used'] + 1;
    build_set((int)$b['id'], ['rounds_used' => $used, 'change_notes' => mb_substr($note, 0, 6000)]);
    if ($used > (int)$b['rounds']) {
        build_block(build_row((int)$b['id']), "$who asked for changes again, beyond the " . (int)$b['rounds'] . " rounds their package includes. Decide whether to make them anyway (mark it ready) or quote them.\n\nTheir note:\n$note");
        return;
    }
    build_set((int)$b['id'], ['status' => 'changes']);
    build_notify("Changes asked: $who", "$who asked for changes on draft v{$v['version']} (round $used of " . (int)$b['rounds'] . "). The builder will pick them up on its next run. Hold it from " . abs_url('build.php?id=' . $b['id']) . " if you'd rather do them yourself.\n\nTheir note:\n$note\n");
}

/* ---------- Serving a draft (draft/index.php) ---------- */
function build_by_token(string $t): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $t)) return null;
    return one("SELECT v.*, b.business, b.status bstatus FROM build_versions v JOIN builds b ON b.id = v.build_id WHERE v.token = ? AND v.status = 'ready' AND b.status != 'cancelled'", [$t]);
}
function build_serve(array $v, string $path): void {
    $root = build_dir($v) . '/site/';
    $path = ltrim(rawurldecode($path), '/');
    $cands = $path === '' || substr($path, -1) === '/' ? [$path . 'index.html'] : [$path, $path . '.html'];
    $file = '';
    foreach ($cands as $c) { $c = build_clean_path($c); if ($c !== '' && is_file($root . $c)) { $file = $c; break; } }
    if ($file === '' && $path !== '' && substr($path, -1) !== '/' && build_clean_path($path . '/index.html') !== '' && is_file($root . $path . '/index.html')) { header('Location: ' . url('draft/' . $v['token'] . '/' . $path . '/'), true, 301); exit; }
    if ($file === '' && is_file($root . '404.html')) { http_response_code(404); $file = '404.html'; }
    if ($file === '') { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('That page isn’t in this draft.'); }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . BUILD_TYPES[$ext]);
    header('Cache-Control: private, max-age=300');
    header('Access-Control-Allow-Origin: *'); // the sandboxed page has no origin of its own, so its fonts need this
    header("Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox; default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: blob:; media-src 'self'; connect-src 'none'; form-action 'none'; frame-src 'none'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    header('Referrer-Policy: no-referrer');
    if ($ext !== 'html' && $ext !== 'css') { header('Content-Length: ' . filesize($root . $file)); readfile($root . $file); exit; }
    $prefix = url('draft/' . $v['token'] . '/');
    $s = (string)file_get_contents($root . $file);
    // Root-relative links (/about, /assets/site.css) would leave the draft, so point them inside it.
    if ($ext === 'css') $s = preg_replace('#url\(\s*([\'"]?)/(?!/)#', 'url($1' . $prefix, $s);
    else {
        $s = preg_replace('#\b(href|src|action|poster|content)=([\'"])/(?!/)#i', '$1=$2' . $prefix, $s);
        $s = preg_replace_callback('#\bsrcset=([\'"])([^\'"]*)\1#i', function ($m) use ($prefix) { return 'srcset=' . $m[1] . preg_replace('#(^|,\s*)/(?!/)#', '$1' . $prefix, $m[2]) . $m[1]; }, $s);
        $bar = '<div style="position:fixed;left:12px;right:12px;bottom:12px;z-index:2147483647;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap;padding:10px 14px;border-radius:12px;background:#0d0f0c;color:#f5f4ef;font:500 14px/1.4 system-ui,-apple-system,sans-serif;box-shadow:0 6px 24px rgba(0,0,0,.25)" role="note">'
            . '<b style="color:#c6ff3d">Draft ' . (int)$v['version'] . ' for review</b><span>Not live yet. Forms don’t send. ' . e($v['business']) . ' · Syndicate Marketing</span></div>';
        $s = stripos($s, '</body>') !== false ? substr_replace($s, $bar . '</body>', strripos($s, '</body>'), 7) : $s . $bar;
    }
    header('Content-Length: ' . strlen($s));
    echo $s;
    exit;
}
function build_rmdir(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($dir);
}
// What the dashboard, nav and weekly summary count as waiting on you.
function builds_waiting_on_you(): int { return (int)val("SELECT COUNT(*) FROM builds WHERE status IN ('review','blocked')"); }

/* ---------- The online kickoff form (portal-kickoff.php) ---------- */
// What we ask, in order: key => [label, hint, kind, required]. Kinds: text, area (several lines), lines (one per line).
const KICKOFF_FIELDS = [
    'business' => ['Business name, as it should appear on the site', '', 'text', true],
    'about' => ['What does your business do?', 'A few sentences in your own words, as you’d tell a new customer.', 'area', true],
    'services' => ['Your services or products', 'One per line. Add a few words or a price after any of them if you like.', 'lines', true],
    'customers' => ['Who are your customers?', 'Homeowners, busy families, other businesses, gamers… whoever you want more of.', 'area', false],
    'area' => ['Where do you work?', 'Towns or areas you serve, or “online, anywhere”.', 'text', false],
    'hours' => ['Opening hours', 'One per line, e.g. “Mon–Fri 8am–5pm”. Leave empty if you don’t keep set hours.', 'lines', false],
    'why' => ['Why do customers pick you?', 'In your own words. We only put on the site what you tell us here, so no made-up claims.', 'area', false],
    'goal' => ['The one thing you most want visitors to do', 'Call you, request a quote, book, buy, visit…', 'text', false],
    'pages' => ['Pages you’d like', 'One per line. Not sure? Leave it and we’ll suggest the usual ones for your package.', 'lines', false],
    'style' => ['Colors, feel, and websites you like', 'Paste links to sites you like and say what you like about them.', 'area', false],
    'avoid' => ['Anything to leave out or avoid?', '', 'area', false],
    'phone' => ['Phone number to show on the site', '', 'text', false],
    'email' => ['Email address to show on the site', '', 'text', false],
    'address' => ['Street address to show on the site', 'Only if customers come to you. Leave empty to keep it off the site.', 'text', false],
    'social' => ['Social media and listing links', 'One per line: Facebook, Instagram, Google Business Profile, Yelp…', 'lines', false],
    'domain' => ['Your web address', 'If you already have one, e.g. yourbusiness.com. Not sure? Leave it empty.', 'text', false],
    'current' => ['Your current website, if you have one', 'We can reuse what’s good on it.', 'text', false],
];
function kickoff_answers(array $b): array { $a = json_decode((string)$b['intake'], true); return is_array($a) ? $a : []; }
function kickoff_open(array $b): bool { return in_array($b['status'], ['waiting', 'ready'], true); }
function kickoff_url(array $c, int $projectId): string { return portal_invite_url($c) . '&to=kickoff&p=' . $projectId; }
// The contact details the client chose to show, in the form the builder reads.
function kickoff_publish(array $a): string {
    $out = [];
    foreach (['phone' => 'Phone', 'email' => 'Email', 'address' => 'Address'] as $k => $l) if (trim((string)($a[$k] ?? '')) !== '') $out[] = $l . ': ' . trim($a[$k]);
    if (trim((string)($a['social'] ?? '')) !== '') $out[] = 'Links: ' . implode(', ', array_filter(array_map('trim', explode("\n", $a['social']))));
    if (trim((string)($a['address'] ?? '')) === '') $out[] = 'No street address on the site';
    return $out ? implode("\n", $out) . "\n(Approved by the client in their kickoff form.)" : '';
}
// Saves the form. Returns [errors, started].
function kickoff_save(array $b, array $c, array $in, bool $call): array {
    $a = []; $errors = [];
    foreach (KICKOFF_FIELDS as $k => [$label, , $kind, $req]) {
        $v = trim(str_replace("\r", '', (string)($in[$k] ?? '')));
        $a[$k] = mb_substr($v, 0, $kind === 'text' ? 300 : 4000);
        if ($req && $a[$k] === '') $errors[] = 'Fill in “' . $label . '”.';
    }
    if ($a['email'] !== '' && !filter_var($a['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Check the email address to show on the site.';
    if ($errors) return [$errors, false];
    $first = !$b['intake_at'];
    $start = !$call && $b['status'] === 'waiting';
    build_set((int)$b['id'], ['intake' => json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'intake_at' => time(), 'intake_call' => $call ? 1 : 0]
        + ($b['publish'] === '' || strpos($b['publish'], '(Approved by the client in their kickoff form.)') !== false ? ['publish' => kickoff_publish($a)] : [])
        + ($start ? ['status' => 'ready', 'ready_at' => time()] : []));
    $p = build_project($b);
    q("INSERT INTO project_posts (project_id, author, contact_id, kind, body, created_at) VALUES (?, 'client', ?, 'message', ?, ?)", [$b['project_id'], $c['id'],
        ($first ? 'Sent the kickoff form.' : 'Updated the kickoff form.') . ($call ? ' I’d also like a kickoff call before you start.' : ''), now()]);
    if (!$call) q("UPDATE project_steps SET done_at = ? WHERE project_id = ? AND done_at IS NULL AND title LIKE 'Kickoff%'", [now(), $b['project_id']]);
    if (!$call) q("UPDATE tasks SET done_at = ? WHERE done_at IS NULL AND title LIKE 'Schedule kickoff call with%' AND entity_type = 'deal' AND entity_id IN (SELECT id FROM deals WHERE contact_id = ?)", [now(), $c['id']]);
    $text = kickoff_text($a);
    if ($p) notify_staff($p, $c, $first ? 'sent their kickoff form' : 'updated their kickoff form', ($start ? "The builder starts on the draft on its next run. Add anything else in Notes for the builder: " . abs_url('build.php?id=' . $b['id']) . "\n\n" : ($call ? "They want a kickoff call first, so the draft waits until 3 hours after it.\n\n" : '')) . $text);
    return [[], $start];
}
function kickoff_text(array $a): string {
    $rows = [];
    foreach (KICKOFF_FIELDS as $k => [$label]) if (trim((string)($a[$k] ?? '')) !== '') $rows[] = $label . ":\n" . trim($a[$k]);
    return implode("\n\n", $rows);
}
// One reminder, 3 days after the order, to clients who haven't sent the form or booked a call.
function kickoff_reminders(): int {
    $n = 0;
    foreach (all("SELECT * FROM builds WHERE status = 'waiting' AND intake_at IS NULL AND intake_reminded_at IS NULL AND (order_id IS NOT NULL OR quote_id IS NOT NULL) AND created_at < ?", [time() - 3 * 86400]) as $b) {
        q('UPDATE builds SET intake_reminded_at = ? WHERE id = ?', [time(), $b['id']]);
        if ($b['contact_id'] && one("SELECT 1 FROM bookings bk JOIN meeting_types t ON t.id = bk.type_id WHERE t.kind = 'kickoff' AND bk.status = 'confirmed' AND bk.contact_id = ?", [$b['contact_id']])) continue;
        $p = build_project($b); $c = $b['contact_id'] ? one('SELECT * FROM contacts WHERE id = ? AND portal_access = 1', [$b['contact_id']]) : null;
        if (!$p || !$c || $c['email'] === '' || !$p['shared']) continue;
        $first = $c['first_name'] ?: 'there';
        $slug = (string)val("SELECT slug FROM meeting_types WHERE kind = 'kickoff' AND active = 1 ORDER BY position LIMIT 1");
        if (send_mail($c['email'], 'One step to start your website',
            "Hi $first,\n\nWe’re ready to start your website as soon as we know a bit about your business. It takes about 10 minutes:\n\n" . kickoff_url($c, (int)$p['id'])
            . ($slug !== '' ? "\n\nRather talk it through? Book a free kickoff call instead: " . abs_url('book.php?type=' . rawurlencode($slug)) : '')
            . "\n\nThis is the only reminder we’ll send about it.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND)) {
            log_activity('contact', (int)$c['id'], 'email', 'Sent the one kickoff form reminder for ' . $p['name'] . '.');
            $n++;
        }
    }
    return $n;
}
