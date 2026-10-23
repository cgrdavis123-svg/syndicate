<?php
// Outreach: prospects the outreach agent researched get a first email and two follow-ups from a dedicated mailbox
// (Settings: outreach_mailbox). Sending, follow-ups, opt-outs and bounces are handled here by fixed rules; the agent only
// researches, writes, and answers replies through outreach-api.php. See CLAUDE.md, "Outreach".
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/mime.php';
require_once __DIR__ . '/smtp.php';

const OUT_STATUS = [
    'queued' => ['Waiting to send', '#8a929c'], 'active' => ['In sequence', '#7cc4ff'], 'replied' => ['Replied: agent to answer', '#ffb547'],
    'talking' => ['In conversation', '#7cc4ff'], 'interested' => ['Interested', '#c6ff3d'], 'needs_you' => ['Needs you', '#ff5c8a'],
    'finished' => ['No reply', '#8a929c'], 'declined' => ['Said no', '#8a929c'], 'unsubscribed' => ['Unsubscribed', '#8a929c'],
    'bounced' => ['Bounced', '#ff5c8a'], 'blocked' => ['Blocked as spam', '#ffb547'], 'paused' => ['Paused', '#8a929c'], 'cancelled' => ['Stopped', '#8a929c'],
];
const OUT_FREEMAIL = ['gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com',
    'mac.com', 'att.net', 'bellsouth.net', 'charter.net', 'spectrum.net', 'comcast.net', 'verizon.net', 'protonmail.com', 'proton.me', 'gmx.com', 'mail.com', 'zoho.com'];
const OUT_LINK_HOSTS = ['syndicatemarketing.co', 'crm.stusyn.com', 'stusyn.com', 'wiki.stusyn.com'];

function out_domain(string $email): string { return strtolower(substr(strrchr($email, '@') ?: '', 1)); }
function out_site_domain(string $url): string { return strtolower(preg_replace('/^www\./', '', (string)parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://' . $url, PHP_URL_HOST))); }
function out_business_domain(string $d): bool { return $d !== '' && !in_array($d, OUT_FREEMAIL, true); }
function out_mailbox(): ?array { $id = (int)setting('outreach_mailbox'); return $id ? mailbox($id) : null; }
function out_unsub_url(array $p): string { return abs_url('u.php?t=' . $p['token']); }
function out_log(int $pid, string $kind, string $body = '', ?int $emailId = null): void {
    q('INSERT INTO outreach_log (prospect_id, kind, body, email_id, created_at) VALUES (?,?,?,?,?)', [$pid, $kind, mb_substr($body, 0, 20000), $emailId, now()]);
}
function out_set(int $id, array $f): void {
    $f['updated_at'] = now();
    q('UPDATE outreach_prospects SET ' . implode(', ', array_map(function ($k) { return "$k = ?"; }, array_keys($f))) . ' WHERE id = ?', array_merge(array_values($f), [$id]));
}
function out_notify(string $subject, string $body): void { send_mail(setting('shop_notify') ?: setting('business_email'), $subject, $body, '', 'Syndicate CRM'); }

// Why this address can't be emailed ('' = fine): do-not-contact list, already a prospect, or already a contact/client.
function out_blocked(string $email, int $exceptId = 0): string {
    $d = out_domain($email); $biz = out_business_domain($d);
    if (val('SELECT 1 FROM outreach_optout WHERE email = ?', [$email]) || ($biz && val("SELECT 1 FROM outreach_optout WHERE domain = ?", [$d]))) return 'on the do-not-contact list';
    if (val('SELECT 1 FROM outreach_prospects WHERE email = ? AND id != ?', [$email, $exceptId]) || ($biz && val('SELECT 1 FROM outreach_prospects WHERE domain = ? AND id != ?', [$d, $exceptId]))) return 'already a prospect';
    if (val('SELECT 1 FROM contacts WHERE email = ? COLLATE NOCASE', [$email]) || ($biz && val('SELECT 1 FROM contacts WHERE email LIKE ? COLLATE NOCASE', ['%@' . $d]))) return 'already in the CRM as a contact';
    if (in_array(strtolower($email), own_addresses(), true)) return 'one of our own addresses';
    return '';
}
function out_optout(string $email, string $reason, bool $wholeDomain = false): void {
    if ($email === '' || val('SELECT 1 FROM outreach_optout WHERE email = ? AND domain = ?', [$email, $wholeDomain ? out_domain($email) : ''])) return;
    $d = out_domain($email);
    q('INSERT INTO outreach_optout (email, domain, reason, created_at) VALUES (?,?,?,?)', [$email, $wholeDomain && out_business_domain($d) ? $d : '', $reason, now()]);
}

// Links in an email may only point at our sites or the prospect's own website.
function out_links_error(string $text, string $website): string {
    $own = out_site_domain($website);
    preg_match_all('#https?://[^\s<>()"\']+#i', $text, $m);
    foreach ($m[0] as $u) {
        $h = strtolower(preg_replace('/^www\./', '', (string)parse_url($u, PHP_URL_HOST)));
        $ok = $h !== '' && ($h === $own || in_array($h, OUT_LINK_HOSTS, true));
        if (!$ok) return "Link to $h isn’t allowed. Only our sites and the business’s own website.";
    }
    if (preg_match('/\{\{|\[(MAILING ADDRESS|NAME|BUSINESS|FIRST ?NAME)[^\]]*\]/i', $text)) return 'The email still has a placeholder in it.';
    return '';
}

/* ---------- Sending ---------- */
function out_problems(): array {
    $p = [];
    if (!out_mailbox()) $p[] = 'Choose the mailbox outreach sends from.';
    if (trim(setting('outreach_address')) === '') $p[] = 'Add the postal address for the email footer (required by law).';
    return $p;
}
function out_limit_today(): int {
    $first = val('SELECT MIN(first_sent_at) FROM outreach_prospects');
    $weeks = $first ? (int)floor((time() - strtotime((string)$first)) / 604800) : 0;
    return max(1, min((int)setting('outreach_cap') ?: 30, ((int)setting('outreach_start') ?: 8) + 4 * $weeks));
}
function out_sent_today(): int { return (int)val("SELECT COUNT(*) FROM outreach_log WHERE kind = 'sent' AND created_at >= ?", [today() . ' 00:00:00']); }
function out_in_window(?int $t = null): bool { $t = $t ?? time(); return (int)date('N', $t) <= 5 && (int)date('G', $t) >= 9 && (int)date('G', $t) < 17; }
// $n business days after $from, at a random time between 9:00 and 15:00.
function out_bdays(int $from, int $n): int {
    $d = strtotime(date('Y-m-d', $from));
    while ($n > 0) { $d = strtotime('+1 day', $d); if ((int)date('N', $d) <= 5) $n--; }
    return $d + 9 * 3600 + random_int(0, 6 * 3600);
}
function out_footer(array $p): string {
    return "\n\n" . (setting('outreach_from_name') ?: 'Grady Davis') . "\nSyndicate Marketing · syndicatemarketing.co\n" . trim(setting('outreach_address'))
        . "\n\nIf you’d rather I didn’t email again, just reply “no thanks” or unsubscribe here: " . out_unsub_url($p);
}
// Sends one email to the prospect (a reply in the same conversation when $thread). Returns ['ok', 'bounce', 'blocked', 'error', 'email_id'].
function out_send(array $p, string $subject, string $text, bool $thread): array {
    $box = out_mailbox();
    if (!$box) return ['ok' => false, 'bounce' => false, 'blocked' => false, 'error' => 'No outreach mailbox.'];
    $ids = array_values(array_filter(explode(' ', trim($p['msg_ids']))));
    $from = $box['email'];
    $m = ['from' => [setting('outreach_from_name') ?: 'Grady Davis', $from], 'to' => [[$p['contact_name'], $p['email']]], 'subject' => $subject,
        'text' => rtrim($text) . out_footer($p),
        'headers' => ['List-Unsubscribe' => '<mailto:' . $from . '?subject=unsubscribe>, <' . out_unsub_url($p) . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']];
    if ($thread && $ids) { $m['in_reply_to'] = end($ids); $m['references'] = array_slice($ids, -10); }
    try {
        [$eid] = send_email($box, $m);
        $mid = (string)val('SELECT message_id FROM emails WHERE id = ?', [$eid]);
        $p['msg_ids'] = rtrim($p['msg_ids']) . ' ' . $mid . ' ';
        out_set((int)$p['id'], ['msg_ids' => $p['msg_ids'], 'last_sent_at' => now()]);
        return ['ok' => true, 'bounce' => false, 'blocked' => false, 'error' => '', 'email_id' => (int)$eid];
    } catch (Throwable $ex) {
        $msg = $ex->getMessage();
        $rcpt = strpos($msg, 'won’t send to') !== false || strpos($msg, "won't send to") !== false;
        // A refusal that names spam or policy is about the message or our reputation, not the address.
        $blocked = $ex instanceof SmtpError && out_spam_block($msg);
        return ['ok' => false, 'bounce' => $rcpt && !$blocked, 'blocked' => $blocked, 'error' => $msg];
    }
}
// Sends what's due: follow-ups first, then new first emails, spread through the day within the warm-up limit.
function out_send_due(bool $ignoreWindow = false): int {
    if (setting('outreach_on') !== '1' || out_problems() || (!$ignoreWindow && !out_in_window())) return 0;
    $left = out_limit_today() - out_sent_today();
    if ($left <= 0) return 0;
    $slots = max(1, (int)ceil((strtotime(today() . ' 17:00:00') - time()) / 900));
    $budget = $ignoreWindow ? $left : min($left, max(1, (int)ceil($left / $slots)));
    $sent = 0; $blocks = 0;
    $due = all("SELECT * FROM outreach_prospects WHERE status IN ('queued','active') AND (next_at IS NULL OR next_at <= ?)
        ORDER BY step DESC, CASE priority WHEN 'A' THEN 0 WHEN 'B' THEN 1 ELSE 2 END, id LIMIT ?", [time(), $budget]);
    foreach ($due as $p) {
        if ($why = out_blocked($p['email'], (int)$p['id'])) {
            if ($why === 'on the do-not-contact list' || $why === 'already in the CRM as a contact') { out_set((int)$p['id'], ['status' => 'cancelled', 'next_at' => null]); out_log((int)$p['id'], 'note', "Not sent: $why."); continue; }
        }
        $step = (int)$p['step'];
        $text = [$p['body'], $p['fu1'], $p['fu2']][$step] ?? '';
        if ($step >= 3 || trim($text) === '') { out_set((int)$p['id'], ['status' => 'finished', 'next_at' => null]); continue; }
        $subject = $step === 0 ? $p['subject'] : 'Re: ' . $p['subject'];
        $r = out_send($p, $subject, $text, $step > 0);
        if (!$r['ok']) {
            if ($r['bounce']) { out_set((int)$p['id'], ['status' => 'bounced', 'next_at' => null]); out_optout($p['email'], 'bounced'); out_log((int)$p['id'], 'bounce', $r['error']); continue; }
            if ($r['blocked']) { out_block($p, $r['error']); if (++$blocks >= 3) break; continue; } // three in one run: stop until the next run
            out_log((int)$p['id'], 'error', $r['error']);
            setting('outreach_error', date('M j, g:i A') . ': ' . $r['error']);
            break; // mailbox trouble: stop for this run and try again next time
        }
        setting('outreach_error', '');
        $next = $step === 0 ? out_bdays(time(), 4) : ($step === 1 ? out_bdays(strtotime((string)($p['first_sent_at'] ?: now())), 10) : null);
        $f = ['step' => $step + 1, 'next_at' => $next, 'status' => $next || $step < 2 ? 'active' : 'finished'];
        if ($step === 0) $f['first_sent_at'] = now();
        if ($step === 1 && trim($p['fu2']) === '') { $f['next_at'] = null; $f['status'] = 'finished'; }
        if ($step === 0 && trim($p['fu1']) === '') { $f['next_at'] = null; $f['status'] = 'finished'; }
        out_set((int)$p['id'], $f);
        out_log((int)$p['id'], 'sent', ['First email', 'Follow-up 1', 'Follow-up 2'][$step] . ': ' . $subject, $r['email_id']);
        $sent++;
    }
    if ($sent) out_check_bounces();
    return $sent;
}
// True when a refusal or bounce notice says the message was rejected as spam or by policy, rather than the address
// not existing. Those keep the address usable: no do-not-contact entry, and they don't count toward the bounce limit.
function out_spam_block(string $text): bool {
    $t = mb_substr($text, 0, 4000);
    if (preg_match('/(user unknown|unknown user|no such (user|mailbox|recipient)|does ?n[o’\']?t exist|address (not found|rejected: user)|mailbox (not found|does not exist|unavailable: user)|invalid (recipient|mailbox|address)|recipient (not found|unknown)|account (has been )?(disabled|deactivated|closed)|\b5\.1\.[0-3]\b|\b5\.2\.1\b)/i', $t)) return false;
    return (bool)preg_match('/(spam|junk|bulk mail|black ?list|block ?list|blocked|spamhaus|barracuda|reputation|policy|content (rejected|filter)|rejected (for|due to) content|message discarded|suspicious|phish|unsolicited|\b5\.7\.[0-9]{1,2}\b|\b5\.4\.1\b|dmarc|spf (fail|check)|dkim)/i', $t);
}
// An email to a prospect was refused as spam: stop its sequence (unless it's a conversation), tell Grady, keep the address.
function out_block(array $p, string $why, ?int $emailId = null): void {
    $pid = (int)$p['id'];
    if (in_array($p['status'], ['queued', 'active', 'paused'], true)) out_set($pid, ['status' => 'blocked', 'next_at' => null]);
    elseif ($p['status'] === 'replied') out_set($pid, ['status' => 'needs_you']);
    out_log($pid, 'blocked', mb_substr(trim($why), 0, 2000), $emailId);
    q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Outreach: the email to ' . $p['business'] . ' was blocked as spam — check it', today(), '', null, now()]);
    out_notify('Outreach email blocked as spam: ' . $p['business'],
        'The email to ' . ($p['contact_name'] ?: $p['email']) . " at {$p['business']} was refused as spam, so it wasn't delivered.\n\n"
        . 'What the mail server said: ' . mb_substr(trim(preg_replace('/\s+/', ' ', $why)), 0, 600) . "\n\n"
        . "The address is still good: it isn't on the do-not-contact list and this doesn't count as a bounce. "
        . (in_array($p['status'], ['talking', 'interested', 'replied', 'needs_you'], true) ? 'You are in a conversation with them, so answer from the CRM inbox with a short plain message (no links).' : 'Its follow-ups are on hold. Shorten the email or drop the links, then press Resume on the prospect, or mark it as a bad address.')
        . "\n\n" . abs_url('outreach.php?id=' . $pid));
    out_check_blocks();
}
// Several spam blocks in two weeks usually means the sending mailbox's reputation or the email wording, so warn once a week.
function out_check_blocks(): void {
    $n = (int)val("SELECT COUNT(*) FROM outreach_log WHERE kind = 'blocked' AND created_at >= ?", [date('Y-m-d H:i:s', strtotime('-14 days'))]);
    $last = setting('outreach_block_warned');
    if ($n < 3 || ($last !== '' && strtotime($last) > strtotime('-7 days'))) return;
    setting('outreach_block_warned', now());
    out_notify('Outreach: several emails blocked as spam', "$n outreach emails were refused as spam in the last two weeks. That's usually the wording (links, prices, offer language) or the mailbox's sending reputation, not bad addresses.\n\n"
        . "Worth doing: send one email to mail-tester.com to see its spam score, and ask Namecheap support whether their outgoing filter is stopping mail from the outreach mailbox.\n\n" . abs_url('outreach.php?status=attention'));
}
// One-time repair: bounces recorded before spam blocks were told apart. Turns them back into blocks and frees the address.
function out_fix_old_blocks(): void {
    if (setting('outreach_blocks_fixed') === '1') return;
    foreach (all("SELECT l.id lid, l.body, l.email_id, p.* FROM outreach_log l JOIN outreach_prospects p ON p.id = l.prospect_id WHERE l.kind = 'bounce'") as $r) {
        $text = $r['body'] . "\n" . ($r['email_id'] ? (string)val('SELECT body_text FROM emails WHERE id = ?', [$r['email_id']]) : '');
        if (!out_spam_block($text)) continue;
        q("UPDATE outreach_log SET kind = 'blocked' WHERE id = ?", [$r['lid']]);
        q("DELETE FROM outreach_optout WHERE email = ? AND domain = '' AND reason = 'bounced'", [$r['email']]);
        if ($r['status'] === 'bounced') out_set((int)$r['id'], ['status' => 'blocked']);
        out_log((int)$r['id'], 'note', 'This was a spam block, not a bad address: taken off the do-not-contact list.');
    }
    setting('outreach_blocks_fixed', '1');
}

// Too many bounces means bad addresses or a reputation problem: stop sending until Grady looks.
function out_check_bounces(): void {
    $sent = (int)val("SELECT COUNT(*) FROM outreach_log WHERE kind = 'sent' AND created_at >= ?", [date('Y-m-d H:i:s', strtotime('-14 days'))]);
    $bounced = (int)val("SELECT COUNT(*) FROM outreach_log WHERE kind = 'bounce' AND created_at >= ?", [date('Y-m-d H:i:s', strtotime('-14 days'))]);
    if ($sent >= 20 && $bounced / $sent > 0.08 && setting('outreach_on') === '1') {
        setting('outreach_on', '0');
        setting('outreach_paused', "Paused automatically on " . date('M j') . ": $bounced of $sent emails in the last two weeks bounced. Check the prospect list for bad addresses, then switch outreach back on.");
        out_notify('Outreach paused: too many bounces', setting('outreach_paused') . "\n\n" . abs_url('outreach.php'));
    }
}

/* ---------- Replies, bounces and opt-outs ---------- */
function out_find(array $em): ?array {
    foreach (array_filter(explode(' ', trim((string)$em['refs']))) as $ref) {
        if ($p = one('SELECT * FROM outreach_prospects WHERE msg_ids LIKE ?', ['% ' . $ref . ' %'])) return $p;
    }
    foreach (array_filter([strtolower($em['reply_to']), strtolower($em['from_addr'])]) as $a) {
        if ($p = one('SELECT * FROM outreach_prospects WHERE email = ? AND step > 0 ORDER BY id DESC', [$a])) return $p;
    }
    return null;
}
function out_is_bounce(array $em): bool {
    return (bool)preg_match('/^(mailer-daemon|postmaster)@/i', $em['from_addr'])
        || (bool)preg_match('/(undeliver|delivery status notification|failure notice|returned mail|mail delivery (failed|subsystem)|could not be delivered|delivery has failed)/i', $em['subject']);
}
function out_is_auto(array $em): bool {
    if (preg_match('/^\s*(automatic reply|auto(matic)?[- ]?(reply|response)|out of (the )?office|away from|on vacation|thank you for (your email|contacting))/i', $em['subject'])) return true;
    try { $h = mime_parse(load_raw($em))['h']; } catch (Throwable $e) { return false; }
    $as = strtolower(mime_h($h, 'auto-submitted'));
    return ($as !== '' && $as !== 'no') || mime_h($h, 'x-autoreply') !== '' || mime_h($h, 'x-autorespond') !== '' || in_array(strtolower(mime_h($h, 'precedence')), ['auto_reply', 'bulk', 'junk'], true);
}
// 'unsubscribe', 'decline' or '' for a human reply's newest part.
function out_reply_intent(string $text): string {
    $t = trim(strip_quoted($text));
    $head = mb_substr($t, 0, 500);
    if (preg_match('/\b(unsubscribe|remove me|take (me|us) off|stop (e-?mailing|contacting|sending)|do not (e-?mail|contact)|don[’\']?t (e-?mail|contact) (me|us)|opt[ -]?out)\b/i', $head)) return 'unsubscribe';
    if (mb_strlen($t) < 250 && preg_match('/(^\W*(no|nope|pass)\b|\bnot interested\b|\bno,? thanks?\b|\bno,? thank you\b|\bwe[’\']?re (all )?(set|good)\b|\bnot (right )?now,? thanks\b)/i', $t)) return 'decline';
    return '';
}
// Reads new mail in the outreach mailbox (after mail_sync): stops sequences on replies, records bounces and opt-outs.
function out_process_mail(): array {
    $box = out_mailbox(); $res = ['replies' => 0, 'optouts' => 0, 'bounces' => 0, 'blocks' => 0];
    if (!$box) return $res;
    $last = (int)setting('outreach_last_email');
    $rows = all('SELECT * FROM emails WHERE mailbox_id = ? AND id > ? ORDER BY id LIMIT 300', [$box['id'], $last]);
    foreach ($rows as $em) {
        $last = max($last, (int)$em['id']);
        if ($em['folder'] === 'sent') { // Grady answered a prospect himself from the CRM or webmail
            $p = out_find($em);
            if ($p && strpos($p['msg_ids'], ' ' . $em['message_id'] . ' ') === false && in_array($p['status'], ['replied', 'needs_you', 'active', 'queued'], true)) {
                out_set((int)$p['id'], ['status' => 'talking', 'next_at' => null]); out_log((int)$p['id'], 'you', 'You replied: ' . $em['subject'], (int)$em['id']);
            }
            continue;
        }
        if ($em['folder'] !== 'inbox') continue;
        if (out_is_bounce($em)) {
            $p = out_find($em);
            if (!$p) foreach (all("SELECT * FROM outreach_prospects WHERE step > 0 AND last_sent_at >= ?", [date('Y-m-d H:i:s', strtotime('-10 days'))]) as $c) {
                if (stripos((string)$em['body_text'], (string)$c['email']) !== false) { $p = $c; break; }
            }
            if ($p && out_spam_block((string)$em['body_text'])) { // refused as spam: the address is fine
                if (!in_array($p['status'], ['bounced', 'declined', 'unsubscribed', 'cancelled'], true)) { out_block($p, (string)$em['body_text'], (int)$em['id']); $res['blocks']++; }
                continue; // left unread in the inbox
            }
            if ($p && !in_array($p['status'], ['bounced', 'declined', 'unsubscribed'], true)) {
                out_set((int)$p['id'], ['status' => 'bounced', 'next_at' => null]); out_optout($p['email'], 'bounced');
                out_log((int)$p['id'], 'bounce', $em['subject'], (int)$em['id']); $res['bounces']++;
                q('UPDATE emails SET seen = 1, archived = 1 WHERE id = ?', [$em['id']]);
            }
            continue;
        }
        $p = out_find($em);
        if (!$p) continue;
        $pid = (int)$p['id'];
        if (out_is_auto($em)) { out_log($pid, 'auto_reply', $em['subject'] . "\n\n" . mb_substr(strip_quoted((string)$em['body_text']), 0, 600), (int)$em['id']); q('UPDATE emails SET seen = 1 WHERE id = ?', [$em['id']]); continue; }
        $intent = out_reply_intent((string)$em['body_text']);
        $said = mb_substr(strip_quoted((string)$em['body_text']), 0, 2000);
        if ($intent !== '') {
            out_optout($p['email'], $intent === 'unsubscribe' ? 'asked to unsubscribe' : 'said no thanks', true);
            out_set($pid, ['status' => $intent === 'unsubscribe' ? 'unsubscribed' : 'declined', 'next_at' => null, 'replied_at' => now(), 'reply_email_id' => (int)$em['id']]);
            out_log($pid, $intent === 'unsubscribe' ? 'unsubscribe' : 'declined', $said, (int)$em['id']); $res['optouts']++;
            q('UPDATE emails SET seen = 1 WHERE id = ?', [$em['id']]);
            continue;
        }
        out_set($pid, ['status' => 'replied', 'next_at' => null, 'replied_at' => now(), 'reply_email_id' => (int)$em['id']]);
        out_log($pid, 'reply', $said, (int)$em['id']); $res['replies']++;
        out_notify('Outreach reply: ' . $p['business'], ($p['contact_name'] ?: $p['email']) . " at {$p['business']} replied:\n\n" . mb_substr($said, 0, 1200)
            . "\n\nThe outreach agent answers replies on weekdays. To answer it yourself, reply from the CRM inbox and the agent will leave it to you.\n" . abs_url('outreach.php?id=' . $pid));
    }
    setting('outreach_last_email', (string)$last);
    return $res;
}
function out_unsubscribe(array $p, string $how): void {
    out_optout($p['email'], 'unsubscribed (' . $how . ')', true);
    if (!in_array($p['status'], ['unsubscribed', 'interested', 'talking'], true)) out_set((int)$p['id'], ['status' => 'unsubscribed', 'next_at' => null]);
    out_log((int)$p['id'], 'unsubscribe', 'Unsubscribed with the ' . $how . '.');
}

function outreach_cron(): string {
    out_fix_old_blocks();
    $r = out_process_mail();
    $sent = out_send_due();
    $bits = array_filter([$sent ? "$sent outreach email(s) sent" : '', $r['replies'] ? $r['replies'] . ' outreach repl' . ($r['replies'] === 1 ? 'y' : 'ies') : '', $r['optouts'] ? $r['optouts'] . ' opt-out(s)' : '', $r['bounces'] ? $r['bounces'] . ' bounce(s)' : '', $r['blocks'] ? $r['blocks'] . ' blocked as spam' : '']);
    return implode(' · ', $bits);
}

/* ---------- The agent's side (outreach-api.php) ---------- */
function out_clean(string $s, int $max): string { return mb_substr(trim(str_replace("\r", '', $s)), 0, $max); }
function out_add(array $in, string $batch): array {
    $email = strtolower(trim((string)($in['email'] ?? '')));
    $biz = out_clean((string)($in['business'] ?? ''), 160);
    $p = ['business' => $biz, 'email' => $email];
    if ($biz === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return $p + ['ok' => false, 'reason' => 'needs a business name and a valid email'];
    if ($why = out_blocked($email)) return $p + ['ok' => false, 'reason' => $why];
    $f = ['subject' => out_clean((string)($in['subject'] ?? ''), 140), 'body' => out_clean((string)($in['body'] ?? ''), 3000),
        'fu1' => out_clean((string)($in['followup1'] ?? ''), 2000), 'fu2' => out_clean((string)($in['followup2'] ?? ''), 1500)];
    if ($f['subject'] === '' || mb_strlen($f['body']) < 40) return $p + ['ok' => false, 'reason' => 'needs a subject and a first email'];
    $site = out_clean((string)($in['website'] ?? ''), 300);
    foreach ($f as $k => $t) if ($err = out_links_error($t, $site)) return $p + ['ok' => false, 'reason' => "$k: $err"];
    $pr = strtoupper(substr((string)($in['priority'] ?? 'B'), 0, 1));
    q('INSERT INTO outreach_prospects (business, contact_name, email, domain, website, city, niche, notes, best_fit, sku, priority, batch, status, step, next_at, subject, body, fu1, fu2, token, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,NULL,?,?,?,?,?,?,?)', [$biz, out_clean((string)($in['contact_name'] ?? ''), 120), $email, out_business_domain(out_domain($email)) ? out_domain($email) : '',
        $site, out_clean((string)($in['city'] ?? ''), 80), out_clean((string)($in['niche'] ?? ''), 80), out_clean((string)($in['notes'] ?? ''), 3000),
        out_clean((string)($in['best_fit'] ?? ''), 200), out_clean((string)($in['sku'] ?? ''), 60), in_array($pr, ['A', 'B', 'C'], true) ? $pr : 'B', $batch,
        'queued', $f['subject'], $f['body'], $f['fu1'], $f['fu2'], bin2hex(random_bytes(16)), now(), now()]);
    $id = (int)db()->lastInsertId();
    out_log($id, 'added', 'Added by the outreach agent' . ($batch ? " (batch $batch)" : '') . '.');
    return $p + ['ok' => true, 'id' => $id];
}
// Conversations waiting for the agent: the prospect's notes and every message so far, oldest first.
function out_pending(): array {
    $out = [];
    foreach (all("SELECT * FROM outreach_prospects WHERE status = 'replied' ORDER BY replied_at LIMIT 25") as $p) {
        $ids = array_filter(explode(' ', trim($p['msg_ids'])));
        $thread = $p['reply_email_id'] ? (string)val('SELECT thread_id FROM emails WHERE id = ?', [$p['reply_email_id']]) : '';
        $msgs = $thread !== '' ? all('SELECT id, folder, from_addr, subject, body_text, sent_at FROM emails WHERE thread_id = ? ORDER BY sent_at, id', [$thread]) : [];
        $out[] = ['id' => (int)$p['id'], 'business' => $p['business'], 'contact_name' => $p['contact_name'], 'email' => $p['email'], 'website' => $p['website'], 'city' => $p['city'],
            'niche' => $p['niche'], 'notes' => $p['notes'], 'best_fit' => $p['best_fit'], 'sku' => $p['sku'], 'emails_sent' => count($ids),
            'messages' => array_map(function ($m) { return ['from' => $m['folder'] === 'sent' ? 'us' : 'them', 'subject' => $m['subject'], 'at' => $m['sent_at'], 'text' => mb_substr(strip_quoted((string)$m['body_text']), 0, 4000)]; }, $msgs)];
    }
    return $out;
}
// The agent's answer to a reply: reply | interested | decline | handoff | resume.
function out_respond(int $id, string $action, string $body, string $note): array {
    $p = one('SELECT * FROM outreach_prospects WHERE id = ?', [$id]);
    if (!$p) return ['ok' => false, 'error' => 'Unknown prospect.'];
    if ($p['status'] !== 'replied') return ['ok' => false, 'error' => 'This conversation isn’t waiting on the agent (status: ' . $p['status'] . ').'];
    $note = out_clean($note, 2000); $body = out_clean($body, 3000);
    if (in_array($action, ['reply', 'interested'], true) && $body !== '') {
        if ($err = out_links_error($body, $p['website'])) return ['ok' => false, 'error' => $err];
        $r = out_send($p, 'Re: ' . $p['subject'], $body, true);
        if (!$r['ok']) return ['ok' => false, 'error' => 'Couldn’t send: ' . $r['error']];
        out_log($id, 'sent', 'Agent reply', $r['email_id']);
    } elseif ($action === 'reply') return ['ok' => false, 'error' => 'A reply needs a body.'];
    $link = abs_url('outreach.php?id=' . $id);
    switch ($action) {
        case 'reply':
            out_set($id, ['status' => 'talking']); out_log($id, 'agent', 'Agent answered.' . ($note ? " $note" : ''));
            break;
        case 'interested':
            $c = find_or_create_contact($p['contact_name'], $p['email'], $p['business']);
            $value = $p['sku'] !== '' ? (float)val('SELECT price FROM products WHERE sku = ?', [$p['sku']]) : 0.0;
            q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?, 'Syndicate Marketing', 'qualified', ?, 'Cold outreach', ?, ?, ?)",
                [$p['business'] . ($p['best_fit'] ? ' — ' . $p['best_fit'] : ''), $c['company_id'], $c['id'], $value, trim("What we saw: {$p['notes']}\n\n$note"), now(), now()]);
            $deal = (int)db()->lastInsertId();
            log_activity('deal', $deal, 'email', 'Replied to outreach and is interested. ' . $note);
            q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Outreach: ' . $p['business'] . ' is interested — follow up', today(), 'deal', $deal, now()]);
            out_set($id, ['status' => 'interested', 'contact_id' => $c['id'], 'deal_id' => $deal]); out_log($id, 'agent', 'Interested. Deal created. ' . $note);
            out_notify('Interested: ' . $p['business'], $p['business'] . " is interested.\n\n" . ($note ?: '') . ($body !== '' ? "\n\nThe agent replied:\n" . $body : '') . "\n\nDeal: " . abs_url('deal.php?id=' . $deal) . "\nConversation: $link");
            break;
        case 'decline':
            out_optout($p['email'], 'said no (agent)', true);
            out_set($id, ['status' => 'declined']); out_log($id, 'agent', 'Said no. Added to do-not-contact. ' . $note);
            break;
        case 'handoff':
            out_set($id, ['status' => 'needs_you']); out_log($id, 'agent', 'Handed to Grady. ' . $note . ($body !== '' ? "\n\nSuggested reply:\n" . $body : ''));
            q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Outreach: answer ' . $p['business'] . '’s reply', today(), '', null, now()]);
            out_notify('Needs you: ' . $p['business'] . '’s reply', ($note ?: 'The agent left this reply for you.') . ($body !== '' ? "\n\nSuggested reply:\n" . $body : '') . "\n\n$link");
            break;
        case 'resume':
            $f = ['status' => (int)$p['step'] >= 3 ? 'finished' : 'active'];
            if ((int)$p['step'] < 3) $f['next_at'] = out_bdays(time(), 2);
            out_set($id, $f); out_log($id, 'agent', 'Not a real reply (auto-reply or similar); sequence resumed. ' . $note);
            break;
        default: return ['ok' => false, 'error' => 'Unknown action.'];
    }
    return ['ok' => true, 'status' => one('SELECT status FROM outreach_prospects WHERE id = ?', [$id])['status']];
}
function out_status(): array {
    $counts = [];
    foreach (all('SELECT status, COUNT(*) n FROM outreach_prospects GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
    return ['on' => setting('outreach_on') === '1', 'problems' => out_problems(), 'paused_reason' => setting('outreach_paused'), 'sent_today' => out_sent_today(),
        'limit_today' => out_limit_today(), 'queued' => $counts['queued'] ?? 0, 'waiting_on_agent' => $counts['replied'] ?? 0, 'counts' => $counts];
}
