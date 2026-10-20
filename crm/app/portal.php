<?php
// Client portal: what each client can see, how they sign in, shared files and notifications.
// Clients sign in with a one-time link sent to their email, or with the invite link you send them.
// A client sees the projects shared with their company (or with them, if they have no company) and nothing else.

const PORTAL_LINK_MINUTES = 30;
const PORTAL_FILE_TYPES = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'heic', 'svg', 'ai', 'eps', 'psd', 'fig', 'sketch', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'key', 'txt', 'rtf', 'mp4', 'mov', 'm4v', 'mp3', 'wav', 'ttf', 'otf', 'woff', 'woff2'];

/* ---------- Client sign-in ---------- */
// Clients get their own session cookie, separate from yours, kept for 30 days.
function portal_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name(DEMO ? 'syn_portal_demo' : 'syn_portal'); // the demo never touches a real client's sign-in
    session_set_cookie_params(['lifetime' => 2592000, 'path' => BASE, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $dir = STORAGE . '/portal-sessions';
    if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', '2592000');
    session_start();
}
function portal_contact(): ?array {
    portal_session();
    if (empty($_SESSION['pid'])) return null;
    $c = one('SELECT c.*, co.name company, co.brand company_brand FROM contacts c LEFT JOIN companies co ON co.id = c.company_id WHERE c.id = ? AND c.portal_access = 1', [$_SESSION['pid']]);
    if (!$c || !hash_equals((string)($_SESSION['ptok'] ?? ''), $c['portal_token'])) { unset($_SESSION['pid']); return null; } // access removed or link reset
    return $c;
}
function require_portal(): array {
    header('Cache-Control: no-store');
    $c = portal_contact();
    if (!$c) redirect('portal-login.php');
    if (is_post() && !csrf_ok()) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
    demo_read_only();
    if (!$c['portal_seen_at'] || strtotime($c['portal_seen_at']) < time() - 600) q('UPDATE contacts SET portal_seen_at = ? WHERE id = ?', [now(), $c['id']]);
    return $c;
}
// The demo is look-only: a form sent from it saves and sends nothing, and says what would have happened.
function demo_read_only(): void {
    if (!DEMO || !is_post()) return;
    $a = post('action') ?: (post('decision') !== '' ? 'decision' : '');
    $what = [
        'approve' => 'your approval goes straight to our team, and we get to work on the next step.',
        'changes' => 'your notes go straight to our team, and we post the changes here.',
        'decision' => 'your answer reaches us right away. Nothing goes live until you approve it.',
        'message' => 'your message and any files reach us right away, and we reply within one business day.',
        'request' => 'we’d get your request right away and start on it within the time your plan promises.',
        'review' => 'you’d see the exact date your plan ends, then confirm with one more click.',
        'cancel' => 'your plan would be cancelled and you’d get an email confirming the end date.',
    ][$a] ?? 'this would be sent to our team.';
    flash('This is the demo, so nothing was saved or sent. In your own portal, ' . $what);
    $back = (string)($_SERVER['REQUEST_URI'] ?? '');
    header('Location: ' . ($back !== '' ? $back : url('portal.php')), true, 303);
    exit;
}
function portal_sign_in(array $c): void {
    portal_session();
    session_regenerate_id(true);
    $_SESSION['pid'] = (int)$c['id'];
    $_SESSION['ptok'] = $c['portal_token'];
    q('UPDATE contacts SET portal_seen_at = ? WHERE id = ?', [now(), $c['id']]);
    $first = !one("SELECT 1 FROM activities WHERE entity_type = 'contact' AND entity_id = ? AND body = 'Signed in to the client portal.' AND created_at > ?", [$c['id'], date('Y-m-d H:i:s', time() - 86400)]);
    if ($first) log_activity('contact', (int)$c['id'], 'note', 'Signed in to the client portal.');
}
// Turns portal access on and makes sure the contact has an invite token.
function portal_enable(int $contactId, bool $on): void {
    $c = one('SELECT portal_token FROM contacts WHERE id = ?', [$contactId]);
    if (!$c) return;
    q('UPDATE contacts SET portal_access = ?, portal_token = ?, updated_at = ? WHERE id = ?', [$on ? 1 : 0, $c['portal_token'] !== '' ? $c['portal_token'] : bin2hex(random_bytes(20)), now(), $contactId]);
}
function portal_reset_link(int $contactId): void { q('UPDATE contacts SET portal_token = ? WHERE id = ?', [bin2hex(random_bytes(20)), $contactId]); }
function portal_invite_url(array $c): string { return abs_url('portal-login.php?k=' . $c['portal_token']); }
function portal_url(string $p = ''): string { return abs_url($p ?: 'portal.php'); }

// Emails a one-time sign-in link. Returns false only if the email couldn't be sent.
function portal_send_link(array $c, string $to = ''): bool {
    $tok = bin2hex(random_bytes(24));
    q('DELETE FROM portal_links WHERE expires_at < ? OR contact_id = ?', [time(), $c['id']]);
    q('INSERT INTO portal_links (token_hash, contact_id, expires_at) VALUES (?,?,?)', [hash('sha256', $tok), $c['id'], time() + PORTAL_LINK_MINUTES * 60]);
    $first = $c['first_name'] ?: 'there';
    return send_mail($c['email'], 'Your sign-in link for the ' . portal_brand($c) . (defined('ADS_APP') ? ' ads portal' : ' client portal'),
        "Hi $first,\n\nUse this link to sign in to your " . (defined('ADS_APP') ? 'ads portal' : 'client portal') . ". It works once and expires in " . PORTAL_LINK_MINUTES . " minutes:\n\n" . (defined('ADS_APP') ? ads_url('portal-login.php?t=' . $tok) : abs_url('portal-login.php?t=' . $tok . ($to === 'plans' ? '&to=plans' : '')))
        . "\n\nIf you didn't ask for this, you can ignore this email.\n\n" . portal_brand($c) . "\n" . setting('business_email'));
}
function portal_use_link(string $tok): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return null;
    $row = one('SELECT * FROM portal_links WHERE token_hash = ?', [hash('sha256', $tok)]);
    if (!$row) return null;
    q('DELETE FROM portal_links WHERE token_hash = ?', [$row['token_hash']]); // one use only
    if ($row['expires_at'] < time()) return null;
    return one('SELECT * FROM contacts WHERE id = ? AND portal_access = 1', [$row['contact_id']]);
}

/* ---------- What a client can see ---------- */
function portal_scope(array $c): array {
    return $c['company_id'] ? ['(p.company_id = ? OR p.contact_id = ?)', [(int)$c['company_id'], (int)$c['id']]] : ['p.contact_id = ?', [(int)$c['id']]];
}
function portal_projects(array $c): array {
    [$w, $a] = portal_scope($c);
    return all("SELECT p.* FROM projects p WHERE p.shared = 1 AND $w ORDER BY p.status = 'done', p.updated_at DESC", $a);
}
function portal_project(array $c, int $id): ?array {
    [$w, $a] = portal_scope($c);
    return one("SELECT p.* FROM projects p WHERE p.id = ? AND p.shared = 1 AND $w", array_merge([$id], $a));
}
function portal_invoices(array $c): array {
    $w = $c['company_id'] ? '(i.company_id = ? OR i.contact_id = ?)' : 'i.contact_id = ?';
    $a = $c['company_id'] ? [(int)$c['company_id'], (int)$c['id']] : [(int)$c['id']];
    return all("SELECT i.* FROM invoices i WHERE i.status IN ('sent', 'paid') AND $w ORDER BY i.status = 'paid', i.issue_date DESC LIMIT 50", $a);
}
// The brand the client works with, shown at the top of their portal.
function portal_brand(array $c): string {
    $b = $c['company_brand'] ?? (string)val('SELECT brand FROM companies WHERE id = ?', [(int)$c['company_id']]);
    if ($b === '' || $b === null) $b = (string)val("SELECT brand FROM projects WHERE (company_id = ? OR contact_id = ?) AND brand != '' ORDER BY updated_at DESC LIMIT 1", [(int)$c['company_id'], (int)$c['id']]);
    return $b && $b !== 'Syndicate Holdings Group' ? $b : setting('business_name');
}
// People who should hear about a project: everyone with portal access at its company, or its contact.
function project_people(array $p): array {
    return all('SELECT * FROM contacts WHERE portal_access = 1 AND email != \'\' AND ((company_id IS NOT NULL AND company_id = ?) OR id = ?)', [(int)$p['company_id'], (int)$p['contact_id']]);
}
function project_status_pill(string $s): string { return pill('pj', $s, PROJECT_STATUSES[$s] ?? $s); }

/* ---------- Files ---------- */
// Saves uploaded files ($_FILES[$field]) to a project. Returns [saved count, errors].
function save_project_files(int $projectId, string $field, string $author, ?int $contactId, ?int $postId = null): array {
    $saved = 0; $errors = [];
    foreach ($_FILES[$field]['name'] ?? [] as $i => $name) {
        $err = $_FILES[$field]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $name = mb_substr(trim(basename(str_replace('\\', '/', (string)$name))), 0, 180) ?: 'file';
        if ($err !== UPLOAD_ERR_OK) { $errors[] = '“' . $name . '” didn’t upload' . (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' because it’s larger than ' . fmt_bytes(upload_limit()) . '.' : '. Try again.'); continue; }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, PORTAL_FILE_TYPES, true)) { $errors[] = '“' . $name . '” isn’t a file type we accept here. Zip it, or email it instead.'; continue; }
        $dir = DATA . '/portal/' . $projectId;
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        $rel = $projectId . '/' . bin2hex(random_bytes(16));
        if (!@move_uploaded_file($_FILES[$field]['tmp_name'][$i], DATA . '/portal/' . $rel)) { $errors[] = '“' . $name . '” couldn’t be saved.'; continue; }
        $type = function_exists('mime_content_type') ? (string)@mime_content_type(DATA . '/portal/' . $rel) : '';
        q('INSERT INTO project_files (project_id, post_id, author, contact_id, name, type, size, path, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$projectId, $postId, $author, $contactId, $name, $type, (int)$_FILES[$field]['size'][$i], $rel, now()]);
        $saved++;
    }
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) $errors[] = 'Those files are too large to upload (the limit is ' . fmt_bytes(upload_limit()) . ' at a time).';
    return [$saved, $errors];
}
function send_project_file(array $f): void {
    $path = DATA . '/portal/' . $f['path'];
    if (!is_file($path)) { http_response_code(404); exit('That file is no longer available.'); }
    $img = in_array($f['type'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) && get('view') === '1';
    header('Content-Type: ' . ($img ? $f['type'] : 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($img ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^\x20-\x7e]|["\\\\]/u', '_', $f['name']) . '"; filename*=UTF-8\'\'' . rawurlencode($f['name']));
    header('Cache-Control: private, no-store');
    if ($img) header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");
    readfile($path);
    exit;
}
function file_icon(string $name): string { return e(strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE')); }

/* ---------- Notifications ---------- */
// The client did something: tell you by email, and add a task so it doesn't get missed.
function notify_staff(array $p, array $c, string $what, string $detail = ''): void {
    $who = person_name($c);
    $link = abs_url('project.php?id=' . $p['id']);
    send_mail(setting('business_email'), "$who $what · " . $p['name'], "$who $what on " . $p['name'] . '.' . ($detail !== '' ? "\n\n" . $detail : '') . "\n\nOpen the project: $link", $c['email']);
    if (!one('SELECT 1 FROM tasks WHERE done_at IS NULL AND entity_type = ? AND entity_id = ? AND title LIKE ?', ['contact', $c['id'], '%· ' . $p['name']])) {
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', ["Respond to $who in the portal · " . $p['name'], today(), 1, 'contact', $c['id'], now()]);
    }
    log_activity('contact', (int)$c['id'], 'note', 'Portal · ' . $p['name'] . ": $what." . ($detail !== '' ? "\n\n" . mb_strimwidth($detail, 0, 1500, '…') : ''));
}
// You posted something the client should see. Returns how many people were emailed.
function notify_clients(array $p, string $subject, string $body): int {
    $n = 0; $brand = $p['brand'] && $p['brand'] !== 'Syndicate Holdings Group' ? $p['brand'] : setting('business_name');
    foreach (project_people($p) as $c) {
        $first = $c['first_name'] ?: 'there';
        if (send_mail($c['email'], $subject . ' · ' . $p['name'], "Hi $first,\n\n" . trim($body) . "\n\nSee it in your client portal: " . abs_url('portal-project.php?id=' . $p['id'])
            . "\n(If you're signed out, enter your email on that page and we'll send you a sign-in link.)\n\n$brand\n" . setting('business_email'))) $n++;
    }
    return $n;
}

/* ---------- Shared pieces of the project page (you and the client see the same feed) ---------- */
function project_posts(int $pid): array { return all('SELECT pp.*, c.first_name, c.last_name, c.email FROM project_posts pp LEFT JOIN contacts c ON c.id = pp.contact_id WHERE pp.project_id = ? ORDER BY pp.id DESC LIMIT 300', [$pid]); }
function project_files(int $pid): array { return all('SELECT f.*, c.first_name, c.last_name, c.email FROM project_files f LEFT JOIN contacts c ON c.id = f.contact_id WHERE f.project_id = ? ORDER BY f.id DESC', [$pid]); }
function file_link(array $f, string $viewer): string { return ($viewer === 'staff' ? 'project-file.php' : 'portal-file.php') . '?id=' . (int)$f['id']; }
function render_file_chip(array $f, string $viewer): void { ?>
  <a class="att" href="<?= e(file_link($f, $viewer)) ?>"><span class="att-ic"><?= file_icon($f['name']) ?></span><span class="grow"><b><?= e($f['name']) ?></b><small><?= e(fmt_bytes((int)$f['size'])) ?> · <?= $f['author'] === 'client' ? e(person_name($f)) : 'Team' ?></small></span></a>
<?php }
function render_project_steps(array $steps, bool $staff): void {
    $done = count(array_filter($steps, function ($s) { return $s['done_at']; })); ?>
  <ol class="steps">
    <?php foreach ($steps as $s): $late = !$s['done_at'] && $s['due_date'] && $s['due_date'] < today(); ?>
      <li class="<?= $s['done_at'] ? 'done' : '' ?>">
        <?php if ($staff): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="step_toggle"><input type="hidden" name="step" value="<?= (int)$s['id'] ?>"><button class="check<?= $s['done_at'] ? ' on' : '' ?>" type="submit" aria-label="<?= $s['done_at'] ? 'Mark not done' : 'Mark done' ?>"></button></form>
        <?php else: ?><span class="check<?= $s['done_at'] ? ' on' : '' ?>" aria-hidden="true"></span><?php endif; ?>
        <span class="grow"><b><?= e($s['title']) ?></b><?php if ($s['done_at']): ?><small>Done <?= e(fmt_date($s['done_at'], 'M j')) ?></small><?php elseif ($s['due_date']): ?><small class="<?= $late ? 'warn' : '' ?>">Target <?= e(fmt_date($s['due_date'], 'M j')) ?></small><?php endif; ?></span>
        <?php if ($staff): ?><form method="post" class="inline" data-confirm="Remove this step?"><?= csrf_field() ?><input type="hidden" name="action" value="step_delete"><input type="hidden" name="step" value="<?= (int)$s['id'] ?>"><button class="x" type="submit" aria-label="Remove step">×</button></form><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if ($steps): ?><p class="muted small"><?= $done ?> of <?= count($steps) ?> steps done</p><?php endif;
}
// $viewer: 'staff' or 'client'. Clients get Approve / Request changes on pending approvals.
function render_project_feed(array $posts, array $files, string $viewer): void {
    $byPost = []; foreach ($files as $f) if ($f['post_id']) $byPost[$f['post_id']][] = $f;
    if (!$posts): ?><p class="empty"><?= $viewer === 'staff' ? 'Nothing posted yet. Updates you post here show in the client’s portal.' : 'Updates from the team will show here.' ?></p><?php return; endif; ?>
  <ol class="feed-list">
    <?php foreach ($posts as $pp):
      $who = $pp['author'] === 'client' ? person_name($pp) : ($viewer === 'staff' ? 'You' : 'Team'); ?>
      <?php if ($pp['kind'] === 'status'): ?>
        <li class="fp-status"><span class="kind k-stage"></span><span class="muted small"><?= e($pp['body']) ?> · <?= e(ago($pp['created_at'])) ?></span></li>
      <?php else: ?>
      <li class="fp <?= $pp['author'] === 'client' ? 'from-client' : 'from-team' ?><?= $pp['approval'] ? ' ap-' . e($pp['approval']) : '' ?>" id="post<?= (int)$pp['id'] ?>">
        <div class="fp-head"><span class="av sm"><?= e($pp['author'] === 'client' ? initials(person_name($pp)) : 'SH') ?></span><b><?= e($who) ?></b>
          <?php if ($pp['kind'] === 'approval'): ?><span class="tag <?= $pp['approval'] === 'approved' ? 'lime' : ($pp['approval'] === 'changes' ? 'red' : 'blue') ?>"><?= $pp['approval'] === 'approved' ? 'Approved' : ($pp['approval'] === 'changes' ? 'Changes requested' : 'Needs approval') ?></span><?php elseif ($pp['kind'] === 'update'): ?><span class="tag">Update</span><?php endif; ?>
          <time title="<?= e($pp['created_at']) ?>"><?= e(fmt_date($pp['created_at'], 'M j · g:i a')) ?></time>
          <?php if ($viewer === 'staff'): ?><form method="post" class="inline" data-confirm="Delete this post? The client won’t see it any more."><?= csrf_field() ?><input type="hidden" name="action" value="post_delete"><input type="hidden" name="post" value="<?= (int)$pp['id'] ?>"><button class="x" type="submit" aria-label="Delete post">×</button></form><?php endif; ?>
        </div>
        <?php if (trim($pp['body']) !== ''): ?><div class="fp-body"><?= nl2br(e($pp['body'])) ?></div><?php endif; ?>
        <?php if (!empty($byPost[$pp['id']])): ?><div class="mail-atts"><?php foreach ($byPost[$pp['id']] as $f) render_file_chip($f, $viewer); ?></div><?php endif; ?>
        <?php if ($pp['kind'] === 'approval' && $pp['approval'] !== 'pending'): ?>
          <p class="decision"><?= $pp['approval'] === 'approved' ? '✓ Approved' : '↺ Changes requested' ?> by <?= e(person_name(one('SELECT * FROM contacts WHERE id = ?', [(int)$pp['decided_by']]) ?: ['first_name' => 'the client'])) ?> · <?= e(fmt_date($pp['decided_at'], 'M j · g:i a')) ?><?= $pp['decision_note'] !== '' ? '<br><span class="text">“' . nl2br(e($pp['decision_note'])) . '”</span>' : '' ?></p>
        <?php elseif ($pp['kind'] === 'approval' && $viewer === 'client'): ?>
          <form method="post" class="approve-box"><?= csrf_field() ?><input type="hidden" name="post" value="<?= (int)$pp['id'] ?>">
            <textarea name="note" rows="2" placeholder="Anything to add? Required if you’re asking for changes." aria-label="Note"></textarea>
            <div class="row"><button class="btn primary sm" type="submit" name="action" value="approve">Approve</button><button class="btn ghost sm" type="submit" name="action" value="changes">Request changes</button></div>
          </form>
        <?php elseif ($pp['kind'] === 'approval'): ?><p class="decision muted">Waiting for the client to approve or ask for changes.</p><?php endif; ?>
      </li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
<?php }

/* ---------- Client page frame ---------- */
function portal_top(string $title, ?array $c): void {
    $brand = $c ? portal_brand($c) : setting('business_name'); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<base href="<?= e(BASE) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07080a">
<?php if ($c): ?><meta name="csrf" content="<?= e(csrf_token()) ?>"><?php endif; ?>
<title><?= e($title) ?> · <?= e($brand) ?> <?= defined('ADS_APP') ? 'ads' : 'client' ?> portal</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public portal">
<header class="portal-bar">
  <a class="logo" href="portal.php"><svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span><?= e($brand) ?><small><?= defined('ADS_APP') ? 'ADS PORTAL' : 'CLIENT PORTAL' ?></small></span></a>
  <?php if ($c): ?><nav class="row"><span class="muted small hide-sm"><?= e(person_name($c)) ?><?= $c['company'] ? ' · ' . e($c['company']) : '' ?></span><?php if (defined('ADS_APP')): ?><a class="btn ghost xs" href="portal.php">Results</a><a class="btn ghost xs" href="portal-creatives.php">Approvals</a><a class="btn ghost xs" href="portal-setup.php">Setup</a><a class="btn ghost xs hide-sm" href="<?= e(rtrim((string)config('app_url'), '/')) ?>/<?= DEMO ? 'portal-demo.php' : 'portal.php' ?>">Client portal ↗</a><?php else: ?><a class="btn ghost xs" href="portal-plans.php">Plans &amp; billing</a><?php endif; ?><a class="btn ghost xs" href="portal-logout.php?t=<?= e(csrf_token()) ?>">Sign out</a></nav><?php endif; ?>
</header>
<?php if (DEMO && $c): ?><div class="demo-bar" role="note"><p><b>Demo account.</b> Ridgeline Heating &amp; Air is a made-up business and everything here is sample data, so look around freely. Buttons that would send or save something are turned off.</p><span class="row"><a class="btn primary xs" href="<?= e(abs_url('book.php?type=discovery-call')) ?>">Book a free discovery call</a><a class="btn ghost xs" href="portal-logout.php">Exit demo</a></span></div><?php endif; ?>
<main class="public-wrap portal-wrap" id="main">
<?php if ($m = flash()): ?><div class="toast" role="status"><?= e($m) ?></div><?php endif;
}
function portal_bottom(): void { ?>
  <p class="muted small portal-foot"><a href="<?= e(wiki_url()) ?>">Help center</a> · Questions? Email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a> · <?= e(setting('business_name')) ?></p>
</main>
<script src="assets/js/crm.js" defer></script>
</body>
</html>
<?php }
