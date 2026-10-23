<?php
// Review requests: one email asking for a Google review, a few days after a project is marked Complete.
// Rules (Google's review policy and the FTC's rule on consumer reviews, 16 CFR 465):
//   - everyone gets the same email with the same link; we never ask only happy clients first ("review gating");
//   - no discounts, credit or anything else in return, and no suggested wording or star rating;
//   - one ask per business a year, never to anyone on the do-not-contact list, never while an invoice is overdue
//     or after a refund. Staff can send one early or skip it from the project page or Reviews.
// Cron (rv_cron) schedules, sends and tidies up. Clicks go through rv.php so we can see who opened the link.
// Staff page: reviews.php. Migration v26.

const RV_STATUSES = ['scheduled' => 'Scheduled', 'sent' => 'Sent', 'skipped' => 'Not sent'];
const RV_BRAND = 'Syndicate Marketing'; // the Google profile the link points to

function rv_on(): bool { return setting('review_on') === '1'; }
function rv_url(): string { $u = setting('review_url'); return preg_match('#^https://#i', $u) ? $u : ''; }
function rv_delay(): int { return max(0, min(30, (int)setting('review_delay_days'))); }
function rv_link(array $r): string { return abs_url('rv.php?t=' . $r['token']); }
function rv_for_project(int $projectId): ?array { return one('SELECT * FROM review_requests WHERE project_id = ?', [$projectId]); }

// Who to ask for a project: its own contact, or the first person at the company with portal access.
function rv_person(array $p): ?array {
    $c = $p['contact_id'] ? one("SELECT * FROM contacts WHERE id = ? AND email != ''", [(int)$p['contact_id']]) : null;
    if (!$c && $p['company_id']) $c = one("SELECT * FROM contacts WHERE company_id = ? AND email != '' ORDER BY portal_access DESC, id LIMIT 1", [(int)$p['company_id']]);
    return $c;
}
// Why we shouldn't ask right now: '' (fine), 'wait: …' (try again later) or a reason to skip for good.
function rv_blocker(array $r): string {
    $email = strtolower((string)$r['email']); $dom = substr(strrchr($email, '@') ?: '', 1);
    if ($email === '') return 'No email address for this client';
    if (one('SELECT 1 FROM outreach_optout WHERE email = ? OR (domain != \'\' AND domain = ?)', [$email, $dom])) return 'On the do-not-contact list';
    $year = time() - 365 * 86400;
    if (one("SELECT 1 FROM review_requests WHERE status = 'sent' AND sent_at > ? AND id != ? AND (email = ? OR (company_id IS NOT NULL AND company_id = ?))", [$year, $r['id'], $email, (int)$r['company_id']])) return 'Already asked in the last year';
    if (one("SELECT 1 FROM orders WHERE status = 'refunded' AND email = ? COLLATE NOCASE AND updated_at > ?", [$email, date('Y-m-d H:i:s', time() - 180 * 86400)])) return 'They had a refund';
    if (one("SELECT 1 FROM invoices WHERE status = 'sent' AND due_date < ? AND ((company_id IS NOT NULL AND company_id = ?) OR contact_id = ?)", [today(), (int)$r['company_id'], (int)$r['contact_id']]))
        return (int)$r['created_at'] < time() - 30 * 86400 ? 'An invoice was overdue for a month' : 'wait: an invoice is overdue';
    return '';
}

// Every cron run: schedule newly finished projects, drop ones reopened before sending, send what's due.
function rv_cron(): string {
    if (!rv_on()) return '';
    // Projects reopened before their request went out go back to waiting (they'll be scheduled again when finished).
    q("DELETE FROM review_requests WHERE status = 'scheduled' AND project_id IN (SELECT id FROM projects WHERE status != 'done')");
    foreach (all("SELECT p.* FROM projects p LEFT JOIN review_requests r ON r.project_id = p.id WHERE p.status = 'done' AND r.id IS NULL LIMIT 50") as $p) {
        $c = rv_person($p);
        $skip = !$p['company_id'] && !$p['contact_id'] ? 'Not linked to a client' : (!in_array($p['brand'], ['', RV_BRAND], true) ? 'Not a ' . RV_BRAND . ' project' : ($p['shared'] ? ($c ? '' : 'No email address for this client') : 'Hidden from the client portal'));
        q('INSERT INTO review_requests (project_id, company_id, contact_id, email, status, reason, token, due_at, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$p['id'], $p['company_id'], $c['id'] ?? $p['contact_id'], $c['email'] ?? '', $skip === '' ? 'scheduled' : 'skipped', $skip, bin2hex(random_bytes(16)), time() + rv_delay() * 86400, time()]);
    }
    if (rv_url() === '') return (int)val("SELECT COUNT(*) FROM review_requests WHERE status = 'scheduled'") ? 'Review requests waiting: add your Google review link in Reviews' : '';
    $sent = 0;
    foreach (all("SELECT * FROM review_requests WHERE status = 'scheduled' AND due_at <= ? ORDER BY due_at LIMIT 10", [time()]) as $r) {
        $r = one('SELECT * FROM review_requests WHERE id = ?', [$r['id']]); // an earlier send this run may have covered it
        if (!$r || $r['status'] !== 'scheduled') continue;
        $res = rv_send($r);
        if ($res === 'sent') $sent++;
    }
    return $sent ? "$sent review request" . ($sent === 1 ? '' : 's') . ' sent' : '';
}
// Sends one request if nothing stands in the way. → 'sent', 'waiting' or the reason it was skipped.
function rv_send(array $r, bool $early = false): string {
    if ($r['status'] !== 'scheduled') return 'Already ' . strtolower(RV_STATUSES[$r['status']]);
    if (rv_url() === '') return 'Add your Google review link first';
    $why = rv_blocker($r);
    if (strpos($why, 'wait: ') === 0) { q('UPDATE review_requests SET due_at = ? WHERE id = ?', [time() + 3 * 86400, $r['id']]); return 'Waiting: ' . substr($why, 6); }
    if ($why !== '') { q("UPDATE review_requests SET status = 'skipped', reason = ? WHERE id = ?", [$why, $r['id']]); return $why; }
    $p = $r['project_id'] ? one('SELECT * FROM projects WHERE id = ?', [(int)$r['project_id']]) : null;
    $c = $r['contact_id'] ? one('SELECT * FROM contacts WHERE id = ?', [(int)$r['contact_id']]) : null;
    $first = trim((string)($c['first_name'] ?? '')) ?: 'there';
    $what = $p ? 'your ' . preg_replace('/\s+—.*$/u', '', $p['name']) : 'your project';
    $body = "Hi $first,\n\nThanks again for working with us on $what. It was good to get it finished.\n\n"
        . "If you have two minutes, a short Google review would help us a lot. Other small business owners read them when they’re deciding who to work with. Write whatever you honestly think, good or bad.\n\n"
        . 'Leave a review: ' . rv_link($r) . "\n\n"
        . "And if anything isn’t right, just reply to this email and we’ll sort it out either way.\n\n"
        . "This is the only time we’ll ask.\n\nGrady Davis\n" . RV_BRAND . "\n" . setting('business_email');
    if (!send_mail($r['email'], 'Would you leave us a quick review?', $body, setting('business_email'), RV_BRAND)) return 'The email didn’t send. Try again later.';
    q("UPDATE review_requests SET status = 'sent', sent_at = ?, reason = ? WHERE id = ?", [time(), $early ? 'Sent early by hand' : '', $r['id']]);
    // Any other project for the same business waiting to ask counts as asked.
    q("UPDATE review_requests SET status = 'skipped', reason = 'Asked with another project' WHERE status = 'scheduled' AND id != ? AND (email = ? OR (company_id IS NOT NULL AND company_id = ?))", [$r['id'], $r['email'], (int)$r['company_id']]);
    $note = 'Asked for a Google review' . ($p ? ' after “' . $p['name'] . '”' : '') . '.';
    if ($r['contact_id']) log_activity('contact', (int)$r['contact_id'], 'email', $note);
    if ($r['company_id']) log_activity('company', (int)$r['company_id'], 'email', $note);
    return 'sent';
}
function rv_skip(array $r, string $why = 'Skipped by hand'): void {
    if ($r['status'] === 'scheduled') q("UPDATE review_requests SET status = 'skipped', reason = ? WHERE id = ?", [mb_substr($why, 0, 200), $r['id']]);
}
// One line for the project page.
function rv_line(array $r): string {
    if ($r['status'] === 'scheduled') return 'Review request goes out ' . date('M j', (int)$r['due_at']) . ($r['email'] !== '' ? ' to ' . $r['email'] : '');
    if ($r['status'] === 'sent') return 'Review requested ' . date('M j', (int)$r['sent_at']) . ($r['clicked_at'] ? ' · they opened the link ' . date('M j', (int)$r['clicked_at']) : ' · link not opened yet');
    return 'No review request: ' . $r['reason'];
}
