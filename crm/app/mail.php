<?php
// The CRM inbox: connects to your cPanel mailboxes, copies new mail in, matches it to contacts, and sends replies.
// Mail always stays on the server too, so phone and webmail keep working as before.
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/mime.php';
require_once __DIR__ . '/smtp.php';

const MAIL_FIRST_DAYS = 60;      // how far back the first sync of a mailbox goes
const MAIL_FIRST_MAX = 300;      // and how many messages it copies at most
const MAIL_MAX_SIZE = 25000000;  // larger messages are kept as headers only
const MAIL_SENT_NAMES = ['sent', 'inbox.sent', 'sent items', 'inbox.sent items', 'sent messages', 'inbox.sent messages', 'inbox/sent'];

/* ---------- Mailbox passwords (encrypted with a key kept in storage/) ---------- */
function mail_key(): string {
    $f = STORAGE . '/mail.key';
    if (!is_file($f)) { file_put_contents($f, base64_encode(random_bytes(32)), LOCK_EX); @chmod($f, 0600); }
    $k = base64_decode((string)file_get_contents($f));
    if (strlen($k) !== 32) throw new RuntimeException('storage/mail.key is damaged. Delete it and re-enter your mailbox passwords.');
    return $k;
}
function mail_encrypt(string $plain): string {
    $iv = random_bytes(12);
    $ct = openssl_encrypt($plain, 'aes-256-gcm', mail_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'g1:' . base64_encode($iv . $tag . $ct);
}
function mail_decrypt(string $s): string {
    $b = base64_decode(substr($s, 3));
    $out = strlen($b) > 28 ? openssl_decrypt(substr($b, 28), 'aes-256-gcm', mail_key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16)) : false;
    if ($out === false) throw new ImapError('The saved password for this mailbox can’t be read. Enter it again in Inbox → Accounts.');
    return $out;
}

/* ---------- Lookups ---------- */
function mailboxes(): array { return all('SELECT * FROM mailboxes ORDER BY id'); }
function mailbox(int $id): ?array { return one('SELECT * FROM mailboxes WHERE id = ?', [$id]); }
function own_addresses(): array { return array_map('strtolower', array_column(mailboxes(), 'email')); }
function mail_unread(): int { return (int)val("SELECT COUNT(DISTINCT thread_id) FROM emails WHERE folder = 'inbox' AND seen = 0 AND archived = 0"); }
function mail_sync_due(): bool { return (bool)val('SELECT 1 FROM mailboxes LIMIT 1') && (setting('mail_last_sync') === '' || strtotime(setting('mail_last_sync')) < time() - 120); }
function addr_line(array $list): string { return implode(', ', array_map(function ($a) { return $a[0] !== '' ? $a[0] . ' <' . $a[1] . '>' : $a[1]; }, $list)); }
function short_name(array $a): string { return $a[0] !== '' ? $a[0] : $a[1]; }

function imap_open_box(array $box): Imap {
    $im = new Imap($box['host'], (int)$box['imap_port'], (bool)$box['verify_tls']);
    $im->login($box['email'], mail_decrypt($box['secret']));
    return $im;
}

/* ---------- Sync ---------- */
// Copies new mail from every mailbox. Returns ['new' => int, 'errors' => [email => message], 'skipped' => bool].
function mail_sync(bool $force = false, int $seconds = 25): array {
    $res = ['new' => 0, 'errors' => [], 'skipped' => false];
    if (!$force && !mail_sync_due()) { $res['skipped'] = true; return $res; }
    $lock = @fopen(STORAGE . '/mail.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { $res['skipped'] = true; return $res; } // another sync is running
    @set_time_limit($seconds + 40);
    $until = microtime(true) + $seconds;
    setting('mail_last_sync', now());
    foreach (mailboxes() as $box) {
        try {
            $im = imap_open_box($box);
            foreach (['inbox', 'sent'] as $role) $res['new'] += sync_folder($im, $box, $role, $until);
            push_seen($im, $box);
            $im->logout();
            q("UPDATE mailboxes SET last_sync = ?, last_error = '' WHERE id = ?", [now(), $box['id']]);
        } catch (Throwable $ex) {
            $res['errors'][$box['email']] = $ex->getMessage();
            q('UPDATE mailboxes SET last_error = ? WHERE id = ?', [mb_substr($ex->getMessage(), 0, 300), $box['id']]);
            error_log('Mail sync failed for ' . $box['email'] . ': ' . $ex->getMessage());
        }
    }
    if ($res['new']) relink_emails();
    flock($lock, LOCK_UN); fclose($lock);
    return $res;
}
function folder_name(Imap $im, array $box, string $role): ?string {
    if ($role === 'inbox') return 'INBOX';
    if ($box['sent_folder'] !== '') return $box['sent_folder'];
    $found = null;
    foreach ($im->folders() as [$name, $flags]) {
        if (in_array('\\sent', $flags, true)) { $found = $name; break; }
        if (!$found && in_array(strtolower($name), MAIL_SENT_NAMES, true)) $found = $name;
    }
    if ($found) q('UPDATE mailboxes SET sent_folder = ? WHERE id = ?', [$found, $box['id']]);
    return $found;
}
function sync_folder(Imap $im, array $box, string $role, float $until): int {
    $name = folder_name($im, $box, $role);
    if (!$name) return 0;
    $info = $im->select($name, $role === 'sent');
    $f = one('SELECT * FROM mail_folders WHERE mailbox_id = ? AND role = ?', [$box['id'], $role]);
    if (!$f || (int)$f['uidvalidity'] !== $info['uidvalidity'] || $f['name'] !== $name) {
        // First look at this folder (or the server renumbered it): copy the recent past only.
        q('INSERT INTO mail_folders (mailbox_id, role, name, uidvalidity, last_uid) VALUES (?,?,?,?,0) ON CONFLICT(mailbox_id, role) DO UPDATE SET name = excluded.name, uidvalidity = excluded.uidvalidity, last_uid = 0', [$box['id'], $role, $name, $info['uidvalidity']]);
        $uids = $info['exists'] ? array_slice($im->search('SINCE ' . date('j-M-Y', strtotime('-' . MAIL_FIRST_DAYS . ' days'))), -MAIL_FIRST_MAX) : [];
        $all = $info['exists'] ? $im->search('ALL') : [];
        $skipTo = $all ? max($all) : 0; // older mail stays in webmail only
        $last = 0;
    } else {
        $last = (int)$f['last_uid']; $skipTo = 0;
        $uids = $info['exists'] ? array_values(array_filter($im->search('UID ' . ($last + 1) . ':*'), function ($u) use ($last) { return $u > $last; })) : [];
    }
    $n = 0;
    foreach (array_chunk($uids, 25) as $chunk) {
        $meta = $im->fetch(implode(',', $chunk), 'UID FLAGS INTERNALDATE RFC822.SIZE');
        foreach ($chunk as $uid) {
            if (microtime(true) > $until) return $n; // carry on next time
            $m = $meta[$uid] ?? null;
            if ($m) {
                $big = (int)($m['RFC822.SIZE'] ?? 0) > MAIL_MAX_SIZE;
                $got = $im->fetch((string)$uid, 'UID ' . ($big ? 'BODY.PEEK[HEADER]' : 'BODY.PEEK[]'));
                $raw = (string)($got[$uid][$big ? 'BODY[HEADER]' : 'BODY[]'] ?? '');
                if ($raw !== '' && store_email($box, $role, $uid, $raw, (array)($m['FLAGS'] ?? []), (string)($m['INTERNALDATE'] ?? ''), $big)) $n++;
            }
            q('UPDATE mail_folders SET last_uid = ? WHERE mailbox_id = ? AND role = ?', [max($uid, $last), $box['id'], $role]);
            $last = max($uid, $last);
        }
    }
    if ($skipTo > $last) q('UPDATE mail_folders SET last_uid = ? WHERE mailbox_id = ? AND role = ?', [$skipTo, $box['id'], $role]);
    return $n;
}
// Read/unread changes made in the CRM go back to the server, so your phone matches.
function push_seen(Imap $im, array $box): void {
    $rows = all("SELECT id, uid, seen FROM emails WHERE mailbox_id = ? AND folder = 'inbox' AND seen_dirty = 1 AND uid IS NOT NULL", [$box['id']]);
    if (!$rows) return;
    $im->select('INBOX');
    foreach ([1 => '+FLAGS.SILENT', 0 => '-FLAGS.SILENT'] as $seen => $change) {
        $uids = array_map('intval', array_column(array_filter($rows, function ($r) use ($seen) { return (int)$r['seen'] === $seen; }), 'uid'));
        foreach (array_chunk($uids, 200) as $c) $im->store($c, $change);
    }
    q("UPDATE emails SET seen_dirty = 0 WHERE mailbox_id = ? AND folder = 'inbox'", [$box['id']]);
}

function clean_id(string $s): string { return trim(preg_replace('/\s+/', '', $s), '<>'); }
function msg_ids(string $s): array { preg_match_all('/<([^<>\s]+)>/', $s, $m); return $m[1]; }
function norm_subject(string $s): string { return mb_strtolower(trim(preg_replace('/^\s*((re|fwd?|aw|wg)\s*(\[\d+\])?\s*:\s*)+/i', '', $s))); }

// Saves one message. Returns the new email id, or 0 if it was already here.
function store_email(array $box, string $role, ?int $uid, string $raw, array $flags, string $internal = '', bool $headersOnly = false): int {
    $p = mime_parse($raw);
    $h = $p['h'];
    $mid = clean_id(mime_h($h, 'message-id')) ?: 'local-' . sha1($raw);
    if ($ex = one('SELECT id, uid FROM emails WHERE mailbox_id = ? AND folder = ? AND message_id = ?', [$box['id'], $role, $mid])) {
        if ($uid && !$ex['uid']) q('UPDATE emails SET uid = ? WHERE id = ?', [$uid, $ex['id']]); // our own sent copy, now on the server
        return 0;
    }
    $from = mime_addresses(mime_h($h, 'from'))[0] ?? ['', ''];
    $replyTo = mime_addresses(mime_h($h, 'reply-to'))[0][1] ?? '';
    $to = mime_addresses(implode(', ', $h['to'] ?? []));
    $cc = mime_addresses(implode(', ', $h['cc'] ?? []));
    $subject = mime_decode(mime_h($h, 'subject'));
    $t = strtotime(mime_h($h, 'date')) ?: (strtotime($internal) ?: time());
    if ($t > time() + 86400) $t = strtotime($internal) ?: time(); // wildly wrong sender clocks
    $refs = array_merge(msg_ids(mime_h($h, 'references')), msg_ids(mime_h($h, 'in-reply-to')));
    $text = $p['text'] !== '' ? $p['text'] : html_to_text($p['html']);
    if ($headersOnly) $text = 'This message is too large to show here. Open it in webmail to see it and its attachments.';

    // Who it's with: the other side of the conversation, never your own mailboxes.
    $own = own_addresses();
    $side = $role === 'sent' ? array_merge($to, $cc) : array_merge([[$from[0], $replyTo ?: $from[1]]], [$from], $to, $cc);
    $others = array_values(array_unique(array_filter(array_column($side, 1), function ($a) use ($own) { return $a !== '' && !in_array($a, $own, true); })));
    $contact = null;
    foreach ($others as $a) if ($contact = one('SELECT id, company_id FROM contacts WHERE email = ? COLLATE NOCASE', [$a])) break;

    $thread = '';
    if ($refs) {
        $in = implode(',', array_fill(0, count($refs), '?'));
        $thread = (string)val("SELECT thread_id FROM emails WHERE message_id IN ($in) ORDER BY id LIMIT 1", $refs);
    }
    if ($thread === '' && $others && norm_subject($subject) !== '' && preg_match('/^\s*(re|fwd?|aw)\s*:/i', $subject)) {
        // Replies from clients that drop the threading headers: same subject, same person, last 60 days.
        $thread = (string)val("SELECT thread_id FROM emails WHERE addrs LIKE ? AND sent_at > ? AND (subject = ? OR subject LIKE ?) ORDER BY id DESC LIMIT 1",
            ['% ' . $others[0] . ' %', date('Y-m-d H:i:s', $t - 86400 * 60), norm_subject($subject), '%: ' . norm_subject($subject)]);
    }
    if ($thread === '') $thread = substr(sha1($mid), 0, 20);

    $dir = STORAGE . '/mail/' . (int)$box['id'];
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = (int)$box['id'] . '/' . substr(sha1($mid . $role), 0, 24) . '.eml.gz';
    file_put_contents(STORAGE . '/mail/' . $file, gzencode($raw, 6));

    $atts = [];
    foreach ($p['attachments'] as $i => $a) $atts[] = ['n' => $i, 'name' => $a['name'], 'type' => $a['type'], 'size' => $a['size'], 'cid' => $a['cid'], 'inline' => $a['inline']];
    $snippet = trim(preg_replace('/\s+/', ' ', strip_quoted($text)));
    $seen = $role === 'sent' || in_array('\\seen', array_map('strtolower', $flags), true) ? 1 : 0;
    q('INSERT INTO emails (mailbox_id, folder, uid, message_id, refs, thread_id, from_addr, from_name, reply_to, to_list, cc_list, addrs, subject, snippet, body_text, body_html, attachments, sent_at, seen, contact_id, company_id, size, raw_path, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $box['id'], $role, $uid, $mid, ' ' . implode(' ', $refs) . ' ', $thread, $from[1], $from[0], $replyTo, json_encode($to), json_encode($cc), ' ' . implode(' ', $others) . ' ',
        mb_substr($subject, 0, 500), mb_substr($snippet, 0, 200), mb_substr($text, 0, 200000), $headersOnly ? '' : mb_substr($p['html'], 0, 2000000), json_encode($atts),
        date('Y-m-d H:i:s', $t), $seen, $contact['id'] ?? null, $contact['company_id'] ?? null, strlen($raw), $file, now(),
    ]);
    $id = (int)db()->lastInsertId();
    // Replies that were copied in before this message join its conversation.
    $kids = array_column(all('SELECT DISTINCT thread_id FROM emails WHERE refs LIKE ? AND thread_id != ?', ['% ' . $mid . ' %', $thread]), 'thread_id');
    if ($kids) q('UPDATE emails SET thread_id = ? WHERE thread_id IN (' . implode(',', array_fill(0, count($kids), '?')) . ')', array_merge([$thread], $kids));
    if ($role === 'inbox') q('UPDATE emails SET archived = 0 WHERE thread_id = ?', [$thread]); // a new reply brings an archived conversation back
    return $id;
}

// Matches unlinked emails to contacts, e.g. after you add someone who already emailed you.
function relink_emails(?array $contact = null): void {
    if ($contact) {
        if ($contact['email'] === '') return;
        q('UPDATE emails SET contact_id = ?, company_id = ? WHERE contact_id IS NULL AND addrs LIKE ?', [$contact['id'], $contact['company_id'], '% ' . strtolower($contact['email']) . ' %']);
        q('UPDATE emails SET company_id = ? WHERE contact_id = ? AND company_id IS NOT ?', [$contact['company_id'], $contact['id'], $contact['company_id']]); // they moved company
        return;
    }
    q("UPDATE emails SET contact_id = (SELECT c.id FROM contacts c WHERE c.email != '' AND emails.addrs LIKE '% ' || lower(c.email) || ' %' ORDER BY c.id LIMIT 1) WHERE contact_id IS NULL");
    q('UPDATE emails SET company_id = (SELECT company_id FROM contacts WHERE contacts.id = emails.contact_id) WHERE contact_id IS NOT NULL AND company_id IS NULL');
}

// The reply text without the quoted history underneath it.
function strip_quoted(string $t): string { return split_quoted($t)[0]; }
function split_quoted(string $t): array {
    $t = str_replace("\r\n", "\n", $t);
    $pats = ['/^On .{4,200}(wrote|écrit):\s*$/m', '/^-{2,}\s*Original Message\s*-{2,}/mi', '/^_{10,}\s*$\n^From:/m', '/^From: .+\n(Sent|Date): /m', '/^>.*(\n>.*)*\s*\z/m'];
    $cut = strlen($t);
    foreach ($pats as $p) if (preg_match($p, $t, $m, PREG_OFFSET_CAPTURE) && $m[0][1] > 0 && $m[0][1] < $cut) $cut = $m[0][1];
    return [rtrim(substr($t, 0, $cut)), ltrim(substr($t, $cut))];
}

function load_raw(array $em): string {
    $f = STORAGE . '/mail/' . $em['raw_path'];
    $gz = $em['raw_path'] !== '' && is_file($f) ? file_get_contents($f) : false;
    return $gz === false ? '' : (string)gzdecode($gz);
}

/* ---------- Showing an email ---------- */
// HTML emails are cleaned, then shown in a sandboxed frame: no scripts, no forms, and pictures from
// the internet stay blocked until you choose to load them (they're how senders track opens).
function email_html_doc(array $em, bool $images, int &$blocked = 0): string {
    $html = $em['body_html'];
    if ($html === '' || !class_exists('DOMDocument')) return '';
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    foreach (['script', 'noscript', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'meta', 'link', 'base', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'title'] as $tag) {
        foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $el) $el->parentNode->removeChild($el);
    }
    $xp = new DOMXPath($doc);
    foreach (iterator_to_array($xp->query('//@*')) as $at) {
        $n = strtolower($at->nodeName); $v = trim($at->nodeValue);
        if (strpos($n, 'on') === 0 || $n === 'srcset' || $n === 'formaction' || preg_match('/^\s*(javascript|vbscript|data):/i', $v) && !($n === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);#i', $v))) $at->ownerElement->removeAttribute($at->nodeName);
    }
    foreach (iterator_to_array($doc->getElementsByTagName('a')) as $a) { $a->setAttribute('target', '_blank'); $a->setAttribute('rel', 'noopener noreferrer'); }
    $cids = [];
    foreach (json_decode($em['attachments'], true) ?: [] as $a) if ($a['cid'] !== '') $cids[strtolower($a['cid'])] = $a['n'];
    foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
        $src = trim($img->getAttribute('src'));
        if (stripos($src, 'cid:') === 0) {
            $n = $cids[strtolower(substr($src, 4))] ?? null;
            $img->setAttribute('src', $n === null ? '' : url('email-file.php?id=' . (int)$em['id'] . '&n=' . $n . '&inline=1'));
        } elseif (preg_match('#^(https?:)?//#i', $src)) {
            if ($images) $img->setAttribute('src', url('email-img.php?u=' . rawurlencode($src) . '&s=' . img_sig($src)));
            else { $img->removeAttribute('src'); $img->setAttribute('data-blocked', '1'); $blocked++; }
        }
    }
    // Background pictures in styles go through the same rule: hidden, or loaded through the CRM.
    $css = function (string $s) use ($images, &$blocked) {
        return preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', function ($m) use ($images, &$blocked) {
            if (stripos($m[2], 'data:image/') === 0 || strpos($m[2], url('email-img.php')) === 0) return $m[0];
            if ($images && preg_match('#^(https?:)?//#i', $m[2])) return 'url("' . url('email-img.php?u=' . rawurlencode($m[2]) . '&s=' . img_sig($m[2])) . '")';
            $blocked++; return 'none';
        }, $s);
    };
    $styles = '';
    foreach (iterator_to_array($doc->getElementsByTagName('style')) as $st) {
        $styles .= '<style>' . str_ireplace(['</style', '@import'], ['', ''], $css($st->textContent)) . '</style>';
        $st->parentNode->removeChild($st);
    }
    foreach (iterator_to_array($xp->query('//*[@style or @background]')) as $el) {
        if ($el->hasAttribute('background')) { $bg = $el->getAttribute('background'); $el->removeAttribute('background'); if ($images && preg_match('#^https?://#i', $bg)) $el->setAttribute('style', 'background-image:url("' . url('email-img.php?u=' . rawurlencode($bg) . '&s=' . img_sig($bg)) . '");' . $el->getAttribute('style')); elseif (!$images) $blocked++; }
        if (stripos($el->getAttribute('style'), 'url(') !== false) $el->setAttribute('style', $css($el->getAttribute('style')));
    }
    $body = $doc->getElementsByTagName('body')->item(0);
    $inner = '';
    if ($body) foreach ($body->childNodes as $c) $inner .= $doc->saveHTML($c);
    return '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;background:#fff;color:#1b1f24}body{padding:18px 20px;font:14px/1.55 -apple-system,"Segoe UI",Helvetica,Arial,sans-serif;overflow-wrap:anywhere}img{max-width:100%;height:auto}img[data-blocked]{display:none}table{max-width:100%}a{color:#2257c9}blockquote{margin:0 0 0 .6em;padding-left:.9em;border-left:3px solid #d6dbe1;color:#555}pre{white-space:pre-wrap}</style>'
        . $styles . '</head><body>' . $inner . '</body></html>';
}
function img_sig(string $u): string { return substr(hash_hmac('sha256', $u, mail_key()), 0, 20); }

// Plain-text email as safe HTML: links clickable, quoted history folded away.
function email_text_html(string $t): string {
    [$main, $quoted] = split_quoted($t);
    $fmt = function (string $s) {
        $s = e($s);
        $s = preg_replace('#\bhttps?://[^\s<>"\']+[^\s<>"\'.,;:!?)\]]#i', '<a href="$0" target="_blank" rel="noopener noreferrer">$0</a>', $s);
        return nl2br(preg_replace('/^(&gt;.*)$/m', '<span class="q">$1</span>', $s));
    };
    return '<div class="mail-text">' . ($main !== '' ? $fmt($main) : '') . ($quoted !== '' ? '<details class="quoted"><summary>Show quoted text</summary>' . $fmt($quoted) . '</details>' : '') . '</div>';
}

/* ---------- Sending ---------- */
// $m as for build_message(), plus 'bcc'. Returns [email id, warning]. Throws on failure.
function send_email(array $box, array $m): array {
    [$raw, $mid] = build_message($m);
    $rcpts = array_values(array_unique(array_column(array_merge($m['to'], $m['cc'] ?? [], $m['bcc'] ?? []), 1)));
    $pass = mail_decrypt($box['secret']);
    $smtp = new Smtp($box['host'], (int)$box['smtp_port'], (bool)$box['verify_tls']);
    $smtp->login($box['email'], $pass);
    $smtp->mail($box['email'], $rcpts, $raw);
    $smtp->quit();
    $id = store_email($box, 'sent', null, $raw, ['\\Seen']);
    $warn = '';
    try { // a copy in Sent, so it shows in webmail and on your phone
        $im = imap_open_box($box);
        if ($folder = folder_name($im, $box, 'sent')) $im->append($folder, $raw);
        $im->logout();
    } catch (Throwable $ex) { $warn = 'Sent, but a copy couldn’t be saved to your Sent folder on the server.'; error_log('Mail append failed: ' . $ex->getMessage()); }
    return [$id, $warn];
}
function test_mailbox(array $box): void {
    $im = imap_open_box($box);
    $im->select('INBOX', true);
    $im->logout();
    $smtp = new Smtp($box['host'], (int)$box['smtp_port'], (bool)$box['verify_tls']);
    $smtp->login($box['email'], mail_decrypt($box['secret']));
    $smtp->quit();
}
function parse_recipients(string $s, array &$bad): array {
    $out = [];
    foreach (mime_addresses(str_replace(';', ',', $s)) as $a) {
        if (filter_var($a[1], FILTER_VALIDATE_EMAIL)) $out[$a[1]] = $a; else $bad[] = $a[1];
    }
    foreach (preg_split('/[,;]/', $s) as $chunk) if (trim($chunk) !== '' && strpos($chunk, '@') === false) $bad[] = trim($chunk);
    return array_values($out);
}
function mail_when(string $ts): string {
    $t = strtotime($ts);
    if (date('Y-m-d', $t) === today()) return date('g:i a', $t);
    return date(date('Y', $t) === date('Y') ? 'M j' : 'M j, Y', $t);
}
