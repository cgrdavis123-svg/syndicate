<?php
// Referral program. Anyone can sign up for a link (syndicatemarketing.co/referrals.html → shop-api.php?a=refer).
// A new client who arrives through a link, or who a referrer is added to by hand, is tied to that referrer, and every
// payment the client makes in their first `months` (12) earns the referrer `rate`% (5% by default, set per person, so
// sales reps can have 10%). Ad spend never passes through us, so it never counts; sales tax and refunds don't either.
// Each earning is held `ref_hold_days` (30) for refunds, then becomes ready, and is paid as account credit (a Stripe
// customer balance when the referrer has a monthly plan with us, otherwise credit on file) or as cash.
// Referrers sign in to refer.php with an emailed one-time link. Staff pages: referrals.php, referrer.php.
require_once __DIR__ . '/shop.php';

const REF_STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed'];
const REF_KINDS = ['referrer' => 'Referrer', 'creator' => 'Creator partner', 'rep' => 'Sales rep'];
const REF_PAYOUTS = ['credit' => 'Account credit', 'cash' => 'Cash'];
const REF_EARN_STATUSES = ['held' => 'Waiting', 'ready' => 'Ready to pay', 'paid' => 'Paid', 'void' => 'Cancelled'];
const REF_REFERRAL_STATUSES = ['lead' => 'Not a client yet', 'client' => 'Client', 'void' => 'Not counted'];
const REF_LINK_DAYS = 60;      // how long the website remembers a referral link in the visitor's browser
const REF_SIGNIN_MINUTES = 30; // emailed sign-in links
const REF_WELCOME_DAYS = 7;    // the link in the welcome email
const REF_FREEMAIL = ['gmail.com', 'googlemail.com', 'yahoo.com', 'ymail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'aol.com', 'icloud.com', 'me.com', 'mac.com', 'proton.me', 'protonmail.com', 'gmx.com', 'zoho.com', 'yandex.com', 'mail.com'];

function ref_on(): bool { return setting('ref_on') === '1'; }
function ref_rate_default(): float { $r = (float)setting('ref_rate'); return $r > 0 ? $r : 5; }
function ref_months_default(): int { $m = (int)setting('ref_months'); return $m > 0 ? $m : 12; }
function ref_hold_days(): int { $d = setting('ref_hold_days'); return $d === '' ? 30 : max(0, (int)$d); }
function ref_min_cash(): float { $m = setting('ref_min_cash'); return $m === '' ? 25 : max(0, (float)$m); }
// Creator partners (syndicatemarketing.co/creators.html): their own easy code, a higher rate, and a discount for their audience.
function ref_creator_rate(): float { $r = setting('ref_creator_rate'); return $r === '' ? 10 : max(0, min(50, (float)$r)); }
function ref_creator_discount(): float { $d = setting('ref_creator_discount'); return $d === '' ? 10 : max(0, min(50, (float)$d)); }
const REF_RESERVED = ['admin', 'syndicate', 'syndicatemarketing', 'stusyn', 'grady', 'support', 'hello', 'help', 'staff', 'team', 'test', 'free', 'discount', 'promo', 'code', 'offer', 'sale', 'stripe', 'tiktok', 'discord', 'google', 'meta'];
// A code a creator picks: 3–20 letters and numbers, easy to say out loud. Returns an error, or '' if it's fine.
function ref_code_problem(string $code): string {
    if (!preg_match('/^[a-z0-9]{3,20}$/', $code)) return 'Pick a code of 3 to 20 letters and numbers, with no spaces or symbols.';
    if (in_array($code, REF_RESERVED, true) || preg_match('/^(syn|sm)\d*$/', $code)) return 'That code is reserved. Try another, like your handle.';
    if (val('SELECT 1 FROM referrers WHERE code = ?', [$code])) return 'Someone already has that code. Try another, like your handle with a number.';
    return '';
}
// Can this code take money off this person's order? Only for creator codes, only for someone new to us, never for the creator.
// Returns ['ok' => true, 'referrer' => ..., 'pct' => 10] or ['ok' => false, 'error' => ...].
function ref_discount_check(string $code, string $email = ''): array {
    $no = function ($m) { return ['ok' => false, 'error' => $m]; };
    if (!ref_on()) return $no('Codes aren’t being taken right now.');
    $r = ref_by_code($code);
    if (!$r || (float)$r['discount'] <= 0) return $no('We don’t recognise that code. Check the spelling, or leave it blank.');
    $email = strtolower(trim($email));
    if ($email !== '') {
        $c = one('SELECT * FROM contacts WHERE email = ? COLLATE NOCASE ORDER BY id LIMIT 1', [$email]) ?: ['id' => 0, 'email' => $email, 'company_id' => null];
        if (ref_same_party($r, $c)) return $no('That’s your own code, so it can’t be used on your order.');
        if ($c['id'] && ref_already_known($c) !== '') return $no('Codes are for a first order from someone new to us, and you’ve worked with us before. Remove the code to continue.');
        if (one("SELECT 1 FROM orders WHERE email = ? COLLATE NOCASE AND status = 'paid'", [$email])) return $no('Codes are for a first order, and you’ve ordered from us before. Remove the code to continue.');
    }
    return ['ok' => true, 'referrer' => $r, 'pct' => (float)$r['discount']];
}
// Money with a proper minus sign (a refund after a payout leaves a negative balance).
function ref_money($n, bool $cents = true): string { return (float)$n < -0.004 ? '−' . money(abs((float)$n), $cents) : money(abs((float)$n) < 0.005 ? 0 : $n, $cents); }
function ref_pct($r): string { return rtrim(rtrim(number_format((float)$r, 2), '0'), '.') . '%'; }

function ref_clean_code(string $s): string { $s = strtolower(trim($s)); return preg_match('/^[a-z0-9][a-z0-9-]{2,39}$/', $s) ? $s : ''; }
function ref_new_code(string $name): string {
    $base = substr(preg_replace('/[^a-z]/', '', strtolower(function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', explode(' ', trim($name))[0]) : explode(' ', trim($name))[0])), 0, 12) ?: 'friend';
    $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
    do { $c = $base . '-'; for ($i = 0; $i < 4; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)]; } while (val('SELECT 1 FROM referrers WHERE code = ?', [$c]));
    return $c;
}
function ref_get(int $id): ?array { return one('SELECT * FROM referrers WHERE id = ?', [$id]); }
function ref_by_code(string $code): ?array { $code = ref_clean_code($code); return $code === '' ? null : one("SELECT * FROM referrers WHERE code = ? AND status = 'active'", [$code]); }
function ref_site(): string { return shop_site_url() ?: 'https://syndicatemarketing.co'; }
function ref_link(array $r): string { return ref_site() . '/?ref=' . rawurlencode($r['code']); }
function ref_dashboard_url(): string { return abs_url('refer.php'); }
function ref_domain(string $email): string { $d = strtolower((string)substr(strrchr($email, '@') ?: '', 1)); return in_array($d, REF_FREEMAIL, true) ? '' : $d; }

/* ---------- Tying a new client to a referrer ---------- */
// True if the contact is the referrer, works with them, or shares their business email domain: no paying yourself.
function ref_same_party(array $r, array $c): bool {
    if (strcasecmp($r['email'], (string)$c['email']) === 0) return true;
    if ($r['contact_id'] && (int)$r['contact_id'] === (int)$c['id']) return true;
    $rc = $r['contact_id'] ? one('SELECT company_id FROM contacts WHERE id = ?', [$r['contact_id']]) : null;
    if ($rc && $rc['company_id'] && (int)$rc['company_id'] === (int)($c['company_id'] ?? 0)) return true;
    $d = ref_domain($r['email']);
    return $d !== '' && $d === ref_domain((string)$c['email']);
}
// Someone who has already paid us, or whose business is already tied to a referrer, isn't a new referral.
function ref_already_known(array $c): string {
    if (one('SELECT 1 FROM referrals WHERE contact_id = ?', [$c['id']]) || ($c['company_id'] && one("SELECT 1 FROM referrals WHERE company_id = ? AND status != 'void'", [$c['company_id']]))) return 'already referred';
    $w = $c['company_id'] ? '(contact_id = ? OR company_id = ?)' : 'contact_id = ?';
    $a = $c['company_id'] ? [(int)$c['id'], (int)$c['company_id']] : [(int)$c['id']];
    if (one("SELECT 1 FROM invoices WHERE status = 'paid' AND $w", $a)) return 'already a client';
    // Already talking to us (a deal from before today, other than an unfinished checkout of their own).
    if (one("SELECT 1 FROM deals WHERE $w AND stage != 'lost' AND source != 'Marketing site — checkout' AND created_at < ?", array_merge($a, [date('Y-m-d H:i:s', time() - 86400)]))) return 'already talking to us';
    return '';
}
// Ties a contact to the referrer whose code they used. Returns the referral, or null if it doesn't count.
// $how is 'link' (came through the website) or 'manual' (added by staff); $where says where it happened, for the log.
function ref_attach(string $code, array $c, string $how = 'link', string $where = '', bool $force = false): ?array {
    if (!ref_on() && !$force) return null;
    $r = $force ? one('SELECT * FROM referrers WHERE code = ?', [ref_clean_code($code)]) : ref_by_code($code);
    if (!$r || empty($c['id'])) return null;
    if (ref_same_party($r, $c)) { log_activity('contact', (int)$c['id'], 'note', 'Used their own referral link (' . $r['code'] . '), so it doesn’t count.'); return null; }
    $known = ref_already_known($c);
    if ($force && $known === 'already talking to us') $known = ''; // staff adding someone who named the referrer when they got in touch
    if ($known !== '') { if ($known !== 'already referred') log_activity('contact', (int)$c['id'], 'note', 'Arrived through ' . $r['name'] . '’s referral link (' . $r['code'] . '), but is ' . $known . ', so it doesn’t count.'); return null; }
    $biz = $c['company_id'] ? (string)val('SELECT name FROM companies WHERE id = ?', [$c['company_id']]) : '';
    q('INSERT INTO referrals (referrer_id, contact_id, company_id, name, business, how, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$r['id'], $c['id'], $c['company_id'] ?: null, person_name($c), $biz, $how, 'lead', now(), now()]);
    $ref = one('SELECT * FROM referrals WHERE id = ?', [(int)db()->lastInsertId()]);
    log_activity('contact', (int)$c['id'], 'note', 'Referred by ' . $r['name'] . ' (' . $r['code'] . ')' . ($where !== '' ? ' · ' . $where : '') . '. They earn ' . ref_pct($r['rate']) . ' of what this client pays in their first ' . (int)$r['months'] . ' months.');
    if ($r['contact_id']) log_activity('contact', (int)$r['contact_id'], 'note', 'Referred ' . ($biz ?: person_name($c)) . '.');
    return $ref;
}

/* ---------- Earnings ---------- */
// What a paid invoice counts for: everything on it except sales tax (discounts and credits lower it).
function ref_invoice_base(int $invoiceId): float {
    $sum = (float)val("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM invoice_items WHERE invoice_id = ? AND LOWER(description) NOT IN ('sales tax', 'tax')", [$invoiceId]);
    return max(0, round($sum, 2));
}
// Finds payments from referred clients that haven't earned anything yet, and records the earnings.
// Safe to run any number of times (each invoice earns once). Returns how many earnings were added.
function ref_sweep(): int {
    $added = 0;
    $hold = ref_hold_days();
    foreach (all("SELECT f.*, r.rate, r.months, r.status rstatus, r.name rname, r.email remail FROM referrals f JOIN referrers r ON r.id = f.referrer_id WHERE f.status IN ('lead','client') AND r.status != 'closed'") as $f) {
        $w = $f['company_id'] ? '(i.contact_id = ? OR i.company_id = ?)' : 'i.contact_id = ?';
        $a = $f['company_id'] ? [(int)$f['contact_id'], (int)$f['company_id']] : [(int)$f['contact_id']];
        if (!$f['contact_id'] && !$f['company_id']) continue;
        $invs = all("SELECT i.id, i.number, i.paid_at FROM invoices i WHERE i.status = 'paid' AND i.paid_at IS NOT NULL AND SUBSTR(i.paid_at, 1, 10) >= ? AND $w
            AND NOT EXISTS (SELECT 1 FROM referral_earnings e WHERE e.invoice_id = i.id) ORDER BY i.paid_at, i.id", array_merge([substr($f['created_at'], 0, 10)], $a));
        $first = null;
        foreach ($invs as $i) {
            $day = substr((string)$i['paid_at'], 0, 10);
            if ($f['ends_at'] && $day >= $f['ends_at']) continue; // past their first 12 months
            $base = ref_invoice_base((int)$i['id']);
            if ($base <= 0) continue;
            if (!$f['first_paid_at']) {
                $f['first_paid_at'] = $day;
                $f['ends_at'] = date('Y-m-d', strtotime($day . ' +' . max(1, (int)$f['months']) . ' months'));
                q("UPDATE referrals SET status = 'client', first_paid_at = ?, ends_at = ?, updated_at = ? WHERE id = ?", [$f['first_paid_at'], $f['ends_at'], now(), $f['id']]);
                $first = ['invoice' => $i, 'base' => $base];
                if ($day >= $f['ends_at']) continue;
            }
            $amount = round($base * (float)$f['rate'] / 100, 2);
            if ($amount <= 0) continue;
            $ins = q('INSERT OR IGNORE INTO referral_earnings (referral_id, referrer_id, invoice_id, paid_on, base, rate, amount, status, ready_on, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$f['id'], $f['referrer_id'], $i['id'], $day, $base, (float)$f['rate'], $amount, 'held', date('Y-m-d', strtotime($day . " +$hold days")), now(), now()]);
            if ($ins->rowCount()) { $added++; if ($first && $first['invoice']['id'] === $i['id']) $first['amount'] = $amount; }
        }
        if ($first && !empty($first['amount'])) ref_mail_new_client($f, $first['amount']);
    }
    // A voided invoice takes back what it earned, unless it was already paid out.
    q("UPDATE referral_earnings SET status = 'void', note = 'The invoice was voided.', updated_at = ? WHERE status IN ('held','ready') AND invoice_id IN (SELECT id FROM invoices WHERE status = 'void')", [now()]);
    // After the hold, earnings are ready to pay.
    q("UPDATE referral_earnings SET status = 'ready', updated_at = ? WHERE status = 'held' AND ready_on <= ?", [now(), today()]);
    return $added;
}
// A refund in Stripe. Before payout the earning shrinks (or goes); after payout, the difference comes off the next payout.
function ref_refund(int $invoiceId, float $refunded, bool $full): void {
    $e = one('SELECT * FROM referral_earnings WHERE invoice_id = ?', [$invoiceId]);
    if (!$e || $e['status'] === 'void' || $refunded <= 0) return;
    $num = (string)val('SELECT number FROM invoices WHERE id = ?', [$invoiceId]);
    $newBase = $full ? 0 : max(0, round((float)$e['base'] - $refunded, 2));
    $newAmt = round($newBase * (float)$e['rate'] / 100, 2);
    if (in_array($e['status'], ['held', 'ready'], true)) {
        if ($newAmt <= 0) q("UPDATE referral_earnings SET status = 'void', amount = 0, base = 0, note = ?, updated_at = ? WHERE id = ?", ['Refunded in full (' . $num . ').', now(), $e['id']]);
        else q('UPDATE referral_earnings SET base = ?, amount = ?, note = ?, updated_at = ? WHERE id = ?', [$newBase, $newAmt, 'Partly refunded (' . $num . ').', now(), $e['id']]);
    } else {
        $back = round((float)$e['amount'] - $newAmt, 2);
        if ($back > 0) q('INSERT INTO referral_earnings (referral_id, referrer_id, invoice_id, paid_on, base, rate, amount, status, ready_on, note, created_at, updated_at) VALUES (?,?,NULL,?,?,?,?,?,?,?,?,?)',
            [$e['referral_id'], $e['referrer_id'], today(), -round((float)$e['base'] - $newBase, 2), (float)$e['rate'], -$back, 'ready', today(), 'Refund on ' . $num . ', after this was paid out', now(), now()]);
    }
}
function ref_totals(int $rid): array {
    $s = function ($st) use ($rid) { return round((float)val('SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE referrer_id = ? AND status = ?', [$rid, $st]), 2); };
    return ['held' => $s('held'), 'ready' => $s('ready'), 'paid' => round((float)val('SELECT COALESCE(SUM(amount), 0) FROM referral_payouts WHERE referrer_id = ?', [$rid]), 2),
        'clients' => (int)val("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'client'", [$rid]), 'leads' => (int)val("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'lead'", [$rid])];
}
// Whether a payout can go out now, and why not if it can't.
function ref_payout_block(array $r, string $method): string {
    $ready = ref_totals((int)$r['id'])['ready'];
    if ($ready <= 0) return 'Nothing is ready to pay yet.';
    if ($method === 'cash') {
        if (!(int)$r['w9']) return 'Get a W-9 from them before the first cash payout, then tick “W-9 on file”.';
        if ($ready < ref_min_cash()) return 'Cash payouts start at ' . money(ref_min_cash()) . '; they have ' . money($ready) . ' ready.';
    }
    return '';
}
// The Stripe customer of the referrer's live monthly plan, if they have one (credits come off their next bill automatically).
function ref_stripe_customer(array $r): string {
    if (!$r['contact_id']) return '';
    $c = one('SELECT id, company_id FROM contacts WHERE id = ?', [$r['contact_id']]);
    if (!$c) return '';
    $w = $c['company_id'] ? '(contact_id = ? OR company_id = ?)' : 'contact_id = ?';
    $a = $c['company_id'] ? [(int)$c['id'], (int)$c['company_id']] : [(int)$c['id']];
    $in = "'" . implode("','", PLAN_LIVE) . "'";
    foreach (['orders', 'quotes'] as $t) {
        $cus = (string)val("SELECT stripe_customer FROM $t WHERE stripe_customer != '' AND stripe_subscription != '' AND sub_status IN ($in) AND $w ORDER BY id DESC LIMIT 1", $a);
        if ($cus !== '') return $cus;
    }
    return '';
}
// Pays out everything that's ready. Returns ['ok' => bool, 'message' => string].
function ref_payout(array $r, string $method, string $reference, string $note, string $by): array {
    $method = isset(REF_PAYOUTS[$method]) ? $method : 'credit';
    if ($why = ref_payout_block($r, $method)) return ['ok' => false, 'message' => $why];
    $rows = all("SELECT id, amount FROM referral_earnings WHERE referrer_id = ? AND status = 'ready'", [$r['id']]);
    $total = round(array_sum(array_column($rows, 'amount')), 2);
    if ($total <= 0) return ['ok' => false, 'message' => 'Nothing is ready to pay yet.'];
    $txn = ''; $where = '';
    if ($method === 'credit' && ($cus = ref_stripe_customer($r)) !== '' && shop_ready()) {
        try {
            $t = stripe('POST', 'customers/' . rawurlencode($cus) . '/balance_transactions', ['amount' => -(int)round($total * 100), 'currency' => 'usd', 'description' => 'Referral credit',
                'metadata' => ['referrer' => $r['code']]], 'refcredit-' . $r['id'] . '-' . max(array_column($rows, 'id')));
            $txn = (string)($t['id'] ?? ''); $where = 'stripe';
        } catch (Throwable $e) { return ['ok' => false, 'message' => 'Stripe didn’t take the credit: ' . $e->getMessage()]; }
    }
    $pdo = db(); $pdo->beginTransaction();
    try {
        q('INSERT INTO referral_payouts (referrer_id, method, amount, reference, note, stripe_txn, created_by, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$r['id'], $method, $total, mb_substr($reference, 0, 200), mb_substr($note, 0, 1000), $txn, $by, now()]);
        $pid = (int)$pdo->lastInsertId();
        foreach ($rows as $e) q("UPDATE referral_earnings SET status = 'paid', payout_id = ?, updated_at = ? WHERE id = ? AND status = 'ready'", [$pid, now(), $e['id']]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    if ($r['contact_id']) log_activity('contact', (int)$r['contact_id'], 'note', 'Referral payout: ' . money($total) . ' as ' . strtolower(REF_PAYOUTS[$method]) . ($reference !== '' ? ' (' . $reference . ')' : '') . '.');
    $first = trim(explode(' ', $r['name'])[0]) ?: 'there';
    $how = $method === 'cash' ? 'We’ve sent you ' . money($total) . ($reference !== '' ? ' (' . $reference . ')' : '') . '.'
        : ($where === 'stripe' ? 'We’ve added ' . money($total) . ' of credit to your account. It comes off your next monthly bill automatically.'
            : 'We’ve added ' . money($total) . ' of credit to your account. It comes off your next invoice or order with us; just mention it if we forget.');
    send_mail($r['email'], 'Your referral payout: ' . money($total), "Hi $first,\n\n$how\n\nThank you for sending people our way. You can see every referral and payout here:\n" . ref_dashboard_url()
        . "\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
    return ['ok' => true, 'message' => money($total) . ' paid as ' . strtolower(REF_PAYOUTS[$method]) . ($where === 'stripe' ? ' (credited on their Stripe customer, so it comes off their next bill).' : '.')];
}

/* ---------- Referrer sign-up and sign-in ---------- */
function ref_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('syn_ref');
    session_set_cookie_params(['lifetime' => 2592000, 'path' => BASE, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $dir = STORAGE . '/ref-sessions';
    if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', '2592000');
    session_start();
}
function ref_current(): ?array {
    ref_session();
    if (empty($_SESSION['rid'])) return null;
    $r = one("SELECT * FROM referrers WHERE id = ? AND status != 'closed'", [$_SESSION['rid']]);
    if (!$r) unset($_SESSION['rid']);
    return $r;
}
function ref_sign_in(array $r): void { ref_session(); session_regenerate_id(true); $_SESSION['rid'] = (int)$r['id']; q('UPDATE referrers SET seen_at = ? WHERE id = ?', [now(), $r['id']]); }
function ref_make_link(array $r, int $minutes): string {
    $tok = bin2hex(random_bytes(24));
    q('DELETE FROM referrer_links WHERE expires_at < ?', [time()]);
    q('INSERT INTO referrer_links (token_hash, referrer_id, expires_at) VALUES (?,?,?)', [hash('sha256', $tok), $r['id'], time() + $minutes * 60]);
    return abs_url('refer.php?t=' . $tok);
}
function ref_use_link(string $tok): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return null;
    $row = one('SELECT * FROM referrer_links WHERE token_hash = ?', [hash('sha256', $tok)]);
    if (!$row) return null;
    q('DELETE FROM referrer_links WHERE token_hash = ?', [$row['token_hash']]); // one use only
    if ($row['expires_at'] < time()) return null;
    return one("SELECT * FROM referrers WHERE id = ? AND status != 'closed'", [$row['referrer_id']]);
}
function ref_send_signin(array $r): bool {
    $first = trim(explode(' ', $r['name'])[0]) ?: 'there';
    return send_mail($r['email'], 'Your sign-in link for ' . SHOP_BRAND . ' referrals', "Hi $first,\n\nUse this link to see your referrals and earnings. It works once and expires in " . REF_SIGNIN_MINUTES . " minutes:\n\n"
        . ref_make_link($r, REF_SIGNIN_MINUTES) . "\n\nYour referral link, to share: " . ref_link($r) . "\n\nIf you didn't ask for this, you can ignore this email.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
}
// The sign-up form on the website. Same answer whether or not the email is already signed up.
function ref_signup(array $in): array {
    if (!ref_on()) return ['ok' => false, 'error' => 'The referral program isn’t taking new sign-ups right now. Email ' . setting('business_email') . '.'];
    $s = function ($k, $max = 200) use ($in) { return mb_substr(trim((string)($in[$k] ?? '')), 0, $max); };
    $name = $s('name', 120); $email = strtolower($s('email', 190)); $biz = $s('business', 150); $details = $s('payout_details', 300);
    $payout = $s('payout', 10) === 'cash' ? 'cash' : 'credit';
    $creator = !empty($in['creator']);
    $code = strtolower(preg_replace('/\s+/', '', $s('code', 40))); $audience = $s('audience', 200);
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Enter your name.', 'field' => 'name'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.', 'field' => 'email'];
    if ($creator && mb_strlen($audience) < 3) return ['ok' => false, 'error' => 'Tell us where you post, like “TikTok @yourname”.', 'field' => 'audience'];
    if (empty($in['agree'])) return ['ok' => false, 'error' => $creator ? 'Please confirm you’re 18 or older and agree to the creator terms.' : 'Please confirm you’re 18 or older and agree to the referral terms.', 'field' => 'agree'];
    $r = one('SELECT * FROM referrers WHERE email = ?', [$email]);
    if ($r) { if ($r['status'] !== 'closed') ref_send_signin($r); return ['ok' => true]; }
    if ($creator && ($why = ref_code_problem($code)) !== '') return ['ok' => false, 'error' => $why, 'field' => 'code'];
    $contact = one('SELECT id FROM contacts WHERE email = ? COLLATE NOCASE ORDER BY id LIMIT 1', [$email]);
    q('INSERT INTO referrers (code, name, email, business, contact_id, kind, rate, months, discount, audience, payout, payout_details, agreed_at, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$creator ? $code : ref_new_code($name), $name, $email, $biz, $contact ? (int)$contact['id'] : null, $creator ? 'creator' : 'referrer', $creator ? ref_creator_rate() : ref_rate_default(), ref_months_default(),
         $creator ? ref_creator_discount() : 0, $audience, $payout, $details, now(), ip_hash(), now(), now()]);
    $r = ref_get((int)db()->lastInsertId());
    if ($contact) log_activity('contact', (int)$contact['id'], 'note', 'Joined the ' . ($creator ? 'creator' : 'referral') . ' program (' . $r['code'] . ').');
    $first = trim(explode(' ', $name)[0]) ?: 'there';
    if ($creator) { ref_mail_creator_welcome($r); ref_mail_staff_signup($r); return ['ok' => true]; }
    send_mail($email, 'Your ' . SHOP_BRAND . ' referral link', "Hi $first,\n\nThanks for joining the " . SHOP_BRAND . " referral program. Here’s your link:\n\n" . ref_link($r) . "\n\n"
        . "How it works\n  • Share the link with a business that needs a website, ads, website care or community management.\n"
        . '  • If they become a client within ' . REF_LINK_DAYS . " days of opening it, you earn " . ref_pct($r['rate']) . ' of what they pay us in their first ' . (int)$r['months'] . " months. Ad spend and sales tax don’t count.\n"
        . "  • They can also just mention your name or email when they get in touch.\n"
        . '  • Each earning is held ' . ref_hold_days() . " days in case of a refund, then paid as account credit or cash, whichever you choose.\n"
        . "  • If you post your link publicly, say you earn a commission, for example “I get a referral bonus if you sign up.”\n\n"
        . "See your referrals and earnings (this link works once and lasts " . REF_WELCOME_DAYS . " days; you can always get a new one from the same page):\n" . ref_make_link($r, REF_WELCOME_DAYS * 1440)
        . "\n\nThe full rules: " . ref_site() . "/referrals.html#terms\n\nQuestions? Just reply.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
    ref_mail_staff_signup($r);
    return ['ok' => true];
}
function ref_code_upper(array $r): string { return strtoupper($r['code']); }
function ref_mail_staff_signup(array $r): void {
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to === '') return;
    $creator = $r['kind'] === 'creator';
    send_mail($to, '[Referrals] New ' . ($creator ? 'creator partner' : 'referral partner') . ' — ' . $r['name'], $r['name'] . ' (' . $r['email'] . ($r['business'] !== '' ? ', ' . $r['business'] : '') . ') joined the ' . ($creator ? 'creator' : 'referral') . " program.\n"
        . ($creator ? 'Where they post: ' . $r['audience'] . "\nCode: " . ref_code_upper($r) . ' (' . ref_pct($r['discount']) . ' off for their audience, ' . ref_pct($r['rate']) . " for them)\n" : '')
        . 'Payout choice: ' . REF_PAYOUTS[$r['payout']] . "\nLink: " . ref_link($r) . "\n\nIn the CRM: " . abs_url('referrer.php?id=' . $r['id']) . "\n", $r['email'], SHOP_BRAND);
}
function ref_mail_creator_welcome(array $r): void {
    $first = trim(explode(' ', $r['name'])[0]) ?: 'there';
    $code = ref_code_upper($r);
    send_mail($r['email'], 'Your ' . SHOP_BRAND . ' creator code: ' . $code, "Hi $first,\n\nThanks for joining the " . SHOP_BRAND . " creator program. You’re set up:\n\n"
        . "  Your code: $code\n  Your link: " . ref_link($r) . "\n\n"
        . "How it works\n"
        . '  • Your audience types ' . $code . ' at checkout (or uses your link) and gets ' . ref_pct($r['discount']) . " off the one-time items in their first order: websites, add-ons, audits and setups. Monthly plans are full price.\n"
        . '  • When someone becomes a client through your code or link (within ' . REF_LINK_DAYS . ' days of opening the link), you earn ' . ref_pct($r['rate']) . ' of what they pay us in their first ' . (int)$r['months'] . " months. Ad spend and sales tax don’t count.\n"
        . '  • Each earning is held ' . ref_hold_days() . ' days in case of a refund, then paid as account credit or cash (from ' . money(ref_min_cash()) . ", W-9 needed), whichever you choose.\n\n"
        . "Every time you mention us, say it’s a paid partnership. The law requires it (FTC), and it’s fair to your audience. Easiest ways:\n"
        . "  • Turn on the platform’s paid-partnership or branded-content label (TikTok’s “Content disclosure”, Instagram’s “Paid partnership”, YouTube’s “paid promotion”).\n"
        . "  • And say it out loud or in the caption, near the start: “#ad — I earn a commission if you use my code.”\n"
        . "Only say what’s true: use the prices on our site, and don’t promise rankings, sales or results.\n\n"
        . 'See your referrals and earnings (this link works once and lasts ' . REF_WELCOME_DAYS . " days; you can always get a new one from the same page):\n" . ref_make_link($r, REF_WELCOME_DAYS * 1440)
        . "\n\nThe full rules: " . ref_site() . "/creators.html#terms\n\nQuestions, or want talking points or a demo for a video? Just reply.\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
}

/* ---------- Emails and cron ---------- */
function ref_mail_new_client(array $f, float $amount): void {
    $r = ref_get((int)$f['referrer_id']);
    if (!$r || $r['status'] === 'closed') return;
    $first = trim(explode(' ', $r['name'])[0]) ?: 'there';
    $who = $f['business'] ?: $f['name'];
    send_mail($r['email'], 'Your referral became a client', "Hi $first,\n\nGood news: " . ($who !== '' ? $who : 'someone you referred') . " just became a " . SHOP_BRAND . " client through your link. "
        . 'You earned ' . money($amount) . " on their first payment, and you’ll keep earning " . ref_pct($r['rate']) . ' of what they pay until ' . date('F j, Y', strtotime((string)$f['ends_at'])) . ".\n\n"
        . 'Each earning is held ' . ref_hold_days() . " days in case of a refund, then it’s ready to pay. Your referrals and earnings:\n" . ref_dashboard_url() . "\n\nThank you!\n\n" . SHOP_BRAND . "\n" . setting('business_email'), setting('business_email'), SHOP_BRAND);
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') send_mail($to, '[Referrals] ' . $who . ' became a client — ' . $r['name'] . ' earns ' . ref_pct($r['rate']), 'First payment earned ' . $r['name'] . ' ' . money($amount) . '. Their share runs until ' . fmt_date($f['ends_at']) . ".\n\n" . abs_url('referrer.php?id=' . $r['id']) . "\n", '', SHOP_BRAND);
}
// Every cron run: record new earnings and release held ones. On days 1–5, one task a month when payouts are due.
function ref_cron(): string {
    $added = ref_sweep();
    $msg = $added ? "$added referral earning(s) recorded" : '';
    $month = date('Y-m');
    if ((int)date('j') <= 5 && setting('ref_task_month') !== $month) {
        $due = 0; $sum = 0;
        foreach (all("SELECT r.*, (SELECT COALESCE(SUM(amount), 0) FROM referral_earnings e WHERE e.referrer_id = r.id AND e.status = 'ready') ready FROM referrers r WHERE r.status != 'closed'") as $r) {
            if ((float)$r['ready'] <= 0 || ($r['payout'] === 'cash' && (float)$r['ready'] < ref_min_cash())) continue;
            $due++; $sum += (float)$r['ready'];
        }
        setting('ref_task_month', $month);
        if ($due) {
            q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Pay referral earnings: ' . $due . ' ' . ($due === 1 ? 'person' : 'people') . ', ' . money($sum) . ' ready (Referrals)', today(), '', null, now()]);
            $msg = trim($msg . " · referral payouts task added", ' ·');
        }
    }
    return $msg;
}

/* ---------- Referrer page frame (refer.php) ---------- */
function ref_top(string $title, ?array $r): void { ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<base href="<?= e(BASE) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07080a">
<title><?= e($title) ?> · <?= e(SHOP_BRAND) ?> referrals</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public portal">
<header class="portal-bar">
  <a class="logo" href="refer.php"><svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span><?= e(SHOP_BRAND) ?><small>REFERRALS</small></span></a>
  <?php if ($r): ?><nav class="row"><span class="muted small hide-sm"><?= e($r['name']) ?></span><form method="post" action="refer.php"><?= csrf_field() ?><button class="btn ghost xs" name="action" value="signout">Sign out</button></form></nav><?php endif; ?>
</header>
<main class="public-wrap portal-wrap" id="main">
<?php if ($m = flash()): ?><div class="toast" role="status"><?= e($m) ?></div><?php endif;
}
function ref_bottom(): void { ?>
  <p class="muted small portal-foot"><a href="<?= e(ref_site()) ?>/referrals.html#terms">Referral rules</a> · Questions? Email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a> · <?= e(setting('business_name')) ?></p>
</main>
<script src="assets/js/crm.js" defer></script>
</body>
</html>
<?php }
