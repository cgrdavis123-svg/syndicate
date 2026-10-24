<?php
// Team onboarding portal (stusyn.com/team/): who's on the team, their checklist, e-signatures, sign-in links.
// Members sign in with a one-time emailed link (no passwords). Admins manage everyone from Hiring HQ → Team.
declare(strict_types=1);

const TEAM_ROLES = [
    'sales_rep' => 'Commissioned Sales Rep',
    'social' => 'Social Media Manager',
    'paid_social' => 'Paid Social Specialist',
    'other' => 'Specialist',
];
const TEAM_STATUSES = ['onboarding' => 'Onboarding', 'active' => 'Active', 'leaving' => 'Leaving', 'left' => 'Left'];
const TEAM_PAY = ['ach' => 'Direct deposit (ACH)', 'paypal' => 'PayPal'];
const TEAM_DOCS = [
    'agreement_rep' => 'Commissioned Sales Representative Agreement',
    'agreement_contractor' => 'Independent Contractor Services Agreement',
    'handbook' => 'Syndicate Team Handbook',
    'playbook' => 'Sales Rep Playbook',
    'roles' => 'Role Descriptions',
];
const TEAM_INVITE_DAYS = 7;
const TEAM_LINK_MINUTES = 30;

function team_is_rep(array $m): bool { return $m['role'] === 'sales_rep'; }
function team_role(string $r): string { return TEAM_ROLES[$r] ?? 'Team member'; }

/* ---------- Checklist ----------
   type: sign  = the member e-signs a statement (typed name + consent), recorded with time and a hashed IP
         tick  = the member ticks it off
         pay   = the member picks how they're paid (never account numbers)
         admin = Grady marks it done; 'nudge' lets the member say they've done their part
   phase: start (onboarding) or end (offboarding, shown once the status is Leaving or Left) */
function team_steps_for(array $m): array {
    $rep = team_is_rep($m);
    $s = [];
    $s[] = ['key' => 'agreement', 'phase' => 'start', 'type' => 'sign', 'doc' => $rep ? 'agreement_rep' : 'agreement_contractor',
        'title' => 'Sign your agreement',
        'help' => $rep ? 'Your Commissioned Sales Representative Agreement: 10% commission for 12 months, how attribution and payouts work, and the rules you follow.'
                      : 'Your Independent Contractor Services Agreement and its Statement of Work: your rate, deliverables and how invoicing works.',
        'statement' => 'I have read the agreement linked above, including its exhibits, and I agree to its terms. I am signing electronically and my typed name below is my signature.'];
    if ($rep) $s[] = ['key' => 'rules', 'phase' => 'start', 'type' => 'sign', 'doc' => 'agreement_rep',
        'title' => 'Acknowledge the outreach rules',
        'help' => 'Exhibit B of your agreement: honest claims, the CAN-SPAM footer and opt-outs, no autodialers or cold texts, no cold DMs, disclosure on public posts, no minors.',
        'statement' => 'I have read every rule in Exhibit B (Outreach rules acknowledgment) and I will follow each one. I understand that breaking them is a material breach of my agreement.'];
    $s[] = ['key' => 'countersign', 'phase' => 'start', 'type' => 'admin', 'title' => 'Grady countersigns',
        'help' => 'Grady signs for Syndicate and emails you a copy of the signed agreement.'];
    $s[] = ['key' => 'handbook', 'phase' => 'start', 'type' => 'sign', 'doc' => 'handbook',
        'title' => 'Read and acknowledge the Team Handbook',
        'help' => 'Our honesty rules, how we talk to clients, privacy and security, conduct and how you get paid.',
        'statement' => 'I have read the Syndicate Team Handbook. I understand it describes Syndicate\'s standards for contractors and does not change my independent contractor status or my signed agreement. I agree to follow it.'];
    $s[] = ['key' => 'guide', 'phase' => 'start', 'type' => 'tick', 'doc' => $rep ? 'playbook' : 'roles',
        'title' => $rep ? 'Read the Sales Rep Playbook' : 'Read your role description',
        'help' => $rep ? 'What we sell, who to target, the scripts, objections and what you can and can\'t say.' : 'What your role owns, what good looks like and what it never does.'];
    $s[] = ['key' => 'w9', 'phase' => 'start', 'type' => 'admin', 'nudge' => 'I\'ve sent it',
        'title' => 'Send your W-9',
        'help' => 'Fill in IRS Form W-9 (irs.gov/w9), save it as a password-protected PDF and email it to hello@stusyn.com. Send the password separately by text or phone. Never upload it here or email it unprotected.'];
    $s[] = ['key' => 'pay', 'phase' => 'start', 'type' => 'pay', 'title' => 'Choose how you\'re paid',
        'help' => 'Pick ACH or PayPal. Don\'t type bank details here: Grady collects them securely on your welcome call.'];
    $s[] = ['key' => 'twostep', 'phase' => 'start', 'type' => 'tick', 'title' => 'Turn on two-step sign-in',
        'help' => 'On your email and every account you\'ll use for Syndicate work (an authenticator app or passkey is best).'];
    if ($rep) {
        $s[] = ['key' => 'referrer', 'phase' => 'start', 'type' => 'admin', 'title' => 'Your referral link is set up',
            'help' => 'Grady adds you in the CRM as a commissioned sales rep (10%, cash). You\'ll get an email to sign in to your partner page, and your link shows up on this page.'];
        $s[] = ['key' => 'partner', 'phase' => 'start', 'type' => 'tick', 'title' => 'Sign in to your partner page and test your link',
            'help' => 'Open your link in a private window and check it lands on syndicatemarketing.co. Your partner page shows referrals, earnings and payouts.'];
        $s[] = ['key' => 'checkups', 'phase' => 'start', 'type' => 'tick', 'title' => 'Run three free checkups',
            'help' => 'Run syndicatemarketing.co/checkup.html on three businesses you\'d target, and read the reports so you can explain them.'];
        $s[] = ['key' => 'first_email', 'phase' => 'start', 'type' => 'admin', 'nudge' => 'I\'ve sent my draft',
            'title' => 'Send your first email to Grady for review',
            'help' => 'Write your first prospect email using the Playbook and send it to hello@stusyn.com before it goes to anyone else. Grady replies within one business day.'];
    } else {
        $s[] = ['key' => 'crm', 'phase' => 'start', 'type' => 'admin', 'title' => 'Accept your CRM invite',
            'help' => 'Grady invites you to crm.stusyn.com. Accept it and set up two-step sign-in (it\'s required).'];
        $s[] = ['key' => 'wiki', 'phase' => 'start', 'type' => 'tick', 'title' => 'Read the Team docs for your area',
            'help' => 'wiki.stusyn.com/team.php, with your CRM login.'];
        $s[] = ['key' => 'access', 'phase' => 'start', 'type' => 'admin', 'title' => 'Get added to your client accounts',
            'help' => 'Clients add Syndicate\'s business accounts through each platform\'s own sharing. Never ask a client for a password.'];
        $s[] = ['key' => 'shadow', 'phase' => 'start', 'type' => 'admin', 'title' => 'Shadow one client task',
            'help' => 'Work one task alongside Grady before working solo.'];
    }
    $s[] = ['key' => 'welcome', 'phase' => 'start', 'type' => 'admin', 'title' => 'Welcome call with Grady',
        'help' => '30 minutes: questions, payment details, and your first week.'];
    // Offboarding
    $s[] = ['key' => 'off_final', 'phase' => 'end', 'type' => 'admin', 'title' => $rep ? 'Final commission statement' : 'Final invoice paid',
        'help' => $rep ? 'Clients who paid before your end date keep earning for the rest of their 12 months, paid on the normal schedule.' : 'Send your last invoice for work through your end date.'];
    $s[] = ['key' => 'off_handover', 'phase' => 'end', 'type' => 'tick', 'title' => 'Hand over work in progress',
        'help' => $rep ? 'Email hello@stusyn.com any open conversations so prospects aren\'t left hanging, and any opt-outs you haven\'t passed on.' : 'Leave notes and files in the CRM or client portal for anything unfinished.'];
    $s[] = ['key' => 'off_access', 'phase' => 'end', 'type' => 'admin', 'title' => 'All access removed',
        'help' => 'Grady removes your logins: CRM, partner page, client accounts, Discord staff roles and shared files.'];
    $s[] = ['key' => 'off_data', 'phase' => 'end', 'type' => 'sign', 'doc' => '',
        'title' => 'Confirm you\'ve deleted Syndicate data',
        'help' => 'Delete Syndicate and client data from your devices and accounts within 10 days of your end date.',
        'statement' => 'I have deleted all Syndicate and client information from my devices, email and accounts, and kept no copies. I will keep it confidential as my agreement requires.'];
    $s[] = ['key' => 'off_name', 'phase' => 'end', 'type' => 'tick', 'title' => 'Remove Syndicate from your profiles',
        'help' => 'Bios, email signatures and posts that say you represent or work with Syndicate.'];
    return $s;
}
function team_step_def(array $m, string $key): ?array {
    foreach (team_steps_for($m) as $s) if ($s['key'] === $key) return $s;
    return null;
}
function team_step_rows(int $id): array {
    $st = db()->prepare('SELECT * FROM team_steps WHERE member_id = ?');
    $st->execute([$id]);
    $out = [];
    foreach ($st->fetchAll() as $r) { $r['data'] = json_decode($r['data'], true) ?: []; $out[$r['step']] = $r; }
    return $out;
}
// The steps that apply now: onboarding always, offboarding once they're leaving or have left.
function team_visible_steps(array $m): array {
    $off = in_array($m['status'], ['leaving', 'left'], true);
    return array_values(array_filter(team_steps_for($m), function ($s) use ($off) { return $s['phase'] === 'start' || $off; }));
}
function team_progress(array $m, ?array $rows = null): array {
    $rows = $rows ?? team_step_rows((int)$m['id']);
    $out = [];
    foreach (['start', 'end'] as $ph) {
        $steps = array_filter(team_steps_for($m), function ($s) use ($ph) { return $s['phase'] === $ph; });
        $done = count(array_filter($steps, function ($s) use ($rows) { return !empty($rows[$s['key']]['done_at']); }));
        $out[$ph] = [$done, count($steps)];
    }
    return $out;
}
function team_step_save(int $id, string $key, bool $done, string $by, array $data = [], bool $nudge = false): void {
    $db = db();
    $st = $db->prepare('SELECT data FROM team_steps WHERE member_id = ? AND step = ?');
    $st->execute([$id, $key]);
    $old = $st->fetchColumn();
    $merged = array_merge($old !== false ? (json_decode((string)$old, true) ?: []) : [], $data);
    $db->prepare('INSERT INTO team_steps (member_id, step, done_at, done_by, nudged_at, data) VALUES (?,?,?,?,?,?)
        ON CONFLICT(member_id, step) DO UPDATE SET done_at = excluded.done_at, done_by = excluded.done_by,
        nudged_at = COALESCE(excluded.nudged_at, team_steps.nudged_at), data = excluded.data')
        ->execute([$id, $key, $done ? now() : null, $done ? $by : '', $nudge ? now() : null, json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    if (!$done && !$nudge) $db->prepare('UPDATE team_steps SET nudged_at = NULL WHERE member_id = ? AND step = ?')->execute([$id, $key]);
}
// Admin steps a member has said they've done their part on, still waiting for Grady.
function team_waiting_count(): int {
    return (int)db()->query("SELECT COUNT(*) FROM team_steps s JOIN team_members m ON m.id = s.member_id
        WHERE s.nudged_at IS NOT NULL AND s.done_at IS NULL AND m.status != 'left'")->fetchColumn();
}

/* ---------- Settings and documents ---------- */
function team_setting(string $k, string $d = ''): string {
    $st = db()->prepare('SELECT v FROM team_settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $d : (string)$v;
}
function team_setting_set(string $k, string $v): void {
    db()->prepare('INSERT INTO team_settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $v]);
}
// A document link for this member: their own agreement copy if set, otherwise the shared link from Team settings.
function team_doc_url(array $m, string $doc): string {
    if (in_array($doc, ['agreement_rep', 'agreement_contractor'], true) && $m['agreement_url'] !== '') return $m['agreement_url'];
    return team_setting('doc_' . $doc);
}
function team_clean_url(string $u): string {
    $u = trim($u);
    return ($u !== '' && preg_match('#^https://[^\s<>"]+$#i', $u) && filter_var($u, FILTER_VALIDATE_URL)) ? $u : '';
}

/* ---------- Members and sign-in ---------- */
function team_member(int $id): ?array {
    $st = db()->prepare('SELECT * FROM team_members WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function team_link_create(int $id, int $seconds): string {
    $tok = bin2hex(random_bytes(24));
    $db = db();
    $db->prepare('DELETE FROM team_links WHERE expires_at < ? OR used_at IS NOT NULL')->execute([time() - 86400]);
    $db->prepare('INSERT INTO team_links (member_id, token_hash, expires_at) VALUES (?, ?, ?)')->execute([$id, hash('sha256', $tok), time() + $seconds]);
    return $tok;
}
// Checks a sign-in link. $use = true spends it (links work once).
function team_link_member(string $tok, bool $use): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return null;
    $db = db();
    $st = $db->prepare('SELECT l.id lid, m.* FROM team_links l JOIN team_members m ON m.id = l.member_id
        WHERE l.token_hash = ? AND l.used_at IS NULL AND l.expires_at > ?');
    $st->execute([hash('sha256', $tok), time()]);
    $r = $st->fetch();
    if (!$r || $r['status'] === 'left') return null;
    if ($use) $db->prepare('UPDATE team_links SET used_at = ? WHERE id = ?')->execute([time(), $r['lid']]);
    unset($r['lid']);
    return $r;
}
function team_sign_in(array $m): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['team_id'] = (int)$m['id'];
    db()->prepare('UPDATE team_members SET last_seen_at = ? WHERE id = ?')->execute([now(), $m['id']]);
}
function current_member(): ?array {
    start_session();
    if (empty($_SESSION['team_id'])) return null;
    $m = team_member((int)$_SESSION['team_id']);
    if (!$m || $m['status'] === 'left') { unset($_SESSION['team_id']); return null; }
    return $m;
}
function require_member(): array {
    team_headers();
    $m = current_member();
    if (!$m) redirect('team/login.php');
    if (is_post() && !csrf_ok()) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
    if (!$m['last_seen_at'] || strtotime($m['last_seen_at']) < time() - 300) {
        db()->prepare('UPDATE team_members SET last_seen_at = ? WHERE id = ?')->execute([now(), $m['id']]);
    }
    return $m;
}
function team_headers(): void { header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); }
function team_first(array $m): string { return explode(' ', trim($m['name']))[0] ?: $m['name']; }

/* ---------- Email ---------- */
function team_mail_link(array $m, bool $invite): bool {
    $tok = team_link_create((int)$m['id'], $invite ? TEAM_INVITE_DAYS * 86400 : TEAM_LINK_MINUTES * 60);
    $link = abs_url('team/auth.php?t=' . $tok);
    if ($invite) {
        $subject = 'Welcome to Syndicate: your onboarding checklist';
        $body = 'Hi ' . team_first($m) . ",\n\n"
            . 'Welcome aboard as ' . team_role($m['role']) . ". Your onboarding checklist is ready: your agreement, the handbook, your role guide and a few setup steps. Most people finish it in under an hour.\n\n"
            . "Open your checklist:\n$link\n\n"
            . 'This link works once and expires in ' . TEAM_INVITE_DAYS . ' days. After that, sign in any time at ' . abs_url('team/') . " with this email address and we'll send you a fresh link.\n\n"
            . "Questions? Reply to this email.\n\nGrady Davis\nSyndicate Holdings Group";
        db()->prepare('UPDATE team_members SET invited_at = ? WHERE id = ?')->execute([now(), $m['id']]);
    } else {
        $subject = 'Your Syndicate team sign-in link';
        $body = 'Hi ' . team_first($m) . ",\n\nHere's your link to sign in to the Syndicate team portal:\n$link\n\n"
            . 'It works once and expires in ' . TEAM_LINK_MINUTES . " minutes. If you didn't ask for it, you can ignore this email.\n\nSyndicate Holdings Group";
    }
    return send_mail($m['email'], $subject, $body, (string)config('contact_to'));
}
// Tells Grady when a member signs something or says they've done their part.
function team_notify(array $m, string $what): void {
    $to = (string)config('contact_to');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
    send_mail($to, 'Team: ' . $m['name'] . ' — ' . $what,
        $m['name'] . ' (' . team_role($m['role']) . ', ' . $m['email'] . ") $what.\n\nOpen their checklist:\n" . abs_url('admin/member.php?id=' . (int)$m['id']) . "\n");
}

/* ---------- Layout ---------- */
function team_header(string $title, ?array $member): void { require __DIR__ . '/views/team-top.php'; }
function team_footer(): void { require __DIR__ . '/views/admin-bottom.php'; }
