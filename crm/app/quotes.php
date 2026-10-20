<?php
// Quotes: proposals you build in the CRM and send as a link. The client opens it, picks any optional extras,
// accepts with their name, and pays a deposit (or in full) through Stripe. Winning a quote marks the deal won,
// records the payment as a paid invoice, drafts the balance invoice, creates the project and opens their portal.
require_once __DIR__ . '/shop.php';

const QUOTE_DEPOSITS = [100 => 'Pay in full when accepted', 50 => '50% deposit, balance on delivery', 25 => '25% deposit, balance on delivery', 0 => 'Nothing up front, invoice on delivery'];

function quote_link(array $q): string { return abs_url('q.php?t=' . $q['token']); }
function quote_items(int $id): array { return all('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY position, id', [$id]); }
function load_quote(int $id): ?array {
    return one('SELECT q.*, co.name company, co.website, c.first_name, c.last_name, c.email, c.phone FROM quotes q LEFT JOIN companies co ON co.id = q.company_id LEFT JOIN contacts c ON c.id = q.contact_id WHERE q.id = ?', [$id]);
}
function quote_from(array $q): string { return $q['brand'] && $q['brand'] !== 'Syndicate Holdings Group' ? $q['brand'] : setting('business_name'); }
function quote_client(array $q): string { return ($q['company'] ?? '') ?: person_name($q); }
function quote_pill(string $s): string { return pill('qt', $s, QUOTE_STATUSES[$s] ?? $s); }
function next_quote_number(): string {
    $n = max(1, (int)setting('quote_next'));
    do { $num = setting('quote_prefix') . $n; $n++; } while (val('SELECT 1 FROM quotes WHERE number = ?', [$num]));
    setting('quote_next', (string)$n);
    return $num;
}
function quote_expired(array $q): bool { return $q['valid_until'] && $q['valid_until'] < today() && in_array($q['status'], ['draft', 'sent', 'viewed'], true); }
function quote_open(array $q): bool { return in_array($q['status'], ['sent', 'viewed'], true) && !quote_expired($q); }

// Totals for the items the client has selected (optional items count only when ticked).
function quote_totals(array $q, array $items): array {
    $one = 0; $mo = 0;
    foreach ($items as $it) {
        if ($it['optional'] && !$it['selected']) continue;
        $amt = round((float)$it['quantity'] * (float)$it['unit_price'], 2);
        if ($it['interval'] === 'month') $mo += $amt; else $one += $amt;
    }
    $pct = max(0, min(100, (int)$q['deposit_pct']));
    $deposit = round($one * $pct / 100, 2);
    return ['one_time' => round($one, 2), 'monthly' => round($mo, 2), 'pct' => $pct, 'deposit' => $deposit, 'due_now' => round($deposit + $mo, 2), 'balance' => round($one - $deposit, 2)];
}

/* ---------- Sending ---------- */
function quote_message(array $q, array $t): string {
    $first = $q['first_name'] ?: 'there';
    return "Hi $first,\n\nThanks for talking with us. Here's our proposal for " . ($q['title'] ?: 'your project') . ' (' . money($t['one_time'], false) . ($t['monthly'] > 0 ? ' + ' . money($t['monthly'], false) . '/month' : '') . ").\n\n"
        . "You can review it, choose any extras, and accept it online in a couple of minutes using the link below." . ($q['valid_until'] ? ' It’s valid until ' . fmt_date($q['valid_until']) . '.' : '');
}
function email_quote(array $q, string $to, string $message, string $sender, bool $remind = false): bool {
    $t = quote_totals($q, quote_items((int)$q['id']));
    $from = quote_from($q);
    $body = trim($message) . "\n\n"
        . 'Proposal: ' . ($q['title'] ?: $q['number']) . "\n"
        . 'Total: ' . money($t['one_time']) . ($t['monthly'] > 0 ? ' + ' . money($t['monthly']) . '/month' : '') . "\n"
        . ($t['due_now'] > 0 && $t['due_now'] < $t['one_time'] + $t['monthly'] ? 'Due when you accept: ' . money($t['due_now']) . "\n" : '')
        . ($q['valid_until'] ? 'Valid until: ' . fmt_date($q['valid_until']) . "\n" : '')
        . "\nReview and accept: " . quote_link($q) . "\n\n" . ($sender !== '' ? $sender . "\n" : '') . $from . "\n" . setting('business_email');
    $subject = ($remind ? 'Reminder: your proposal from ' : 'Your proposal from ') . $from . ($q['title'] ? ' — ' . $q['title'] : '');
    if (!send_mail($to, $subject, $body, setting('business_email'), $from)) return false;
    if ($remind) q('UPDATE quotes SET reminded_at = ?, updated_at = ? WHERE id = ?', [now(), now(), $q['id']]);
    else q("UPDATE quotes SET status = CASE WHEN status = 'draft' THEN 'sent' ELSE status END, sent_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $q['id']]);
    quote_log($q, ($remind ? 'Reminder for proposal ' : 'Proposal ') . $q['number'] . ' (' . money($t['one_time']) . ($t['monthly'] > 0 ? ' + ' . money($t['monthly']) . '/mo' : '') . ') emailed to ' . $to . '.', 'email');
    return true;
}
function quote_log(array $q, string $msg, string $kind = 'note'): void {
    if ($q['deal_id']) log_activity('deal', (int)$q['deal_id'], $kind, $msg);
    if ($q['contact_id']) log_activity('contact', (int)$q['contact_id'], $kind, $msg);
}
function quote_notify(array $q, string $subject, string $body): void {
    $to = setting('shop_notify') ?: setting('business_email');
    if ($to !== '') send_mail($to, $subject, $body . "\n\nIn the CRM: " . abs_url('quote.php?id=' . $q['id']) . "\n", '', quote_from($q));
}

/* ---------- The client's side ---------- */
function quote_viewed(array $q): void {
    if ($q['viewed_at'] || $q['status'] === 'draft') return;
    q("UPDATE quotes SET viewed_at = ?, status = CASE WHEN status = 'sent' THEN 'viewed' ELSE status END WHERE id = ?", [now(), $q['id']]);
    quote_log($q, 'Client opened proposal ' . $q['number'] . '.', 'email');
    quote_notify($q, '[Proposal opened] ' . quote_client($q) . ' — ' . $q['number'], quote_client($q) . ' just opened your proposal “' . ($q['title'] ?: $q['number']) . '”. A good time to follow up.');
}

// Accepts a quote with the client's chosen extras. Returns ['ok' => true, 'url' => Stripe Checkout or ''] or ['ok' => false, 'error' => ...].
function quote_accept(array $q, string $name, array $picked): array {
    if (!quote_open($q) && $q['status'] !== 'accepted') return ['ok' => false, 'error' => quote_expired($q) ? 'This proposal has expired. Ask us for an updated one.' : 'This proposal can no longer be accepted.'];
    $name = mb_substr(trim($name), 0, 120);
    if (mb_strlen($name) < 3) return ['ok' => false, 'error' => 'Type your full name to accept.', 'field' => 'name'];
    $items = quote_items((int)$q['id']);
    if ($q['status'] !== 'accepted') {
        foreach ($items as $it) if ($it['optional']) q('UPDATE quote_items SET selected = ? WHERE id = ?', [in_array((int)$it['id'], $picked, true) ? 1 : 0, $it['id']]);
        $items = quote_items((int)$q['id']);
    }
    $t = quote_totals($q, $items);
    if ($t['one_time'] + $t['monthly'] <= 0) return ['ok' => false, 'error' => 'Choose at least one item.'];
    if ($t['due_now'] > 0 && !shop_ready()) return ['ok' => false, 'error' => 'Online payment isn’t switched on yet. Email ' . setting('business_email') . ' and we’ll send you a payment link.'];
    if ($q['status'] !== 'accepted') {
        q("UPDATE quotes SET status = 'accepted', accepted_at = ?, accepted_name = ?, accepted_ip = ?, updated_at = ? WHERE id = ?", [now(), $name, ip_hash(), now(), $q['id']]);
        quote_log($q, 'Proposal ' . $q['number'] . ' accepted online by ' . $name . ' (' . money($t['one_time']) . ($t['monthly'] > 0 ? ' + ' . money($t['monthly']) . '/mo' : '') . ').');
    }
    $q = load_quote((int)$q['id']);
    if ($t['due_now'] <= 0) { quote_fulfil($q, null); return ['ok' => true, 'url' => '']; }
    try { $s = quote_checkout($q, $items, $t); }
    catch (Throwable $e) { error_log('Quote checkout ' . $q['number'] . ': ' . $e->getMessage()); return ['ok' => false, 'error' => 'We couldn’t open the secure payment page. Please try again in a minute, or email ' . setting('business_email') . '.']; }
    return ['ok' => true, 'url' => $s['url']];
}

function quote_decline(array $q, string $reason): void {
    if (!quote_open($q)) return;
    q("UPDATE quotes SET status = 'declined', declined_at = ?, decline_reason = ?, updated_at = ? WHERE id = ?", [now(), mb_substr(trim($reason), 0, 1000), now(), $q['id']]);
    quote_log($q, 'Proposal ' . $q['number'] . ' declined' . (trim($reason) !== '' ? ': ' . trim($reason) : '.'));
    if ($q['deal_id']) q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,0,?,?,?)', ['Follow up on declined proposal from ' . quote_client($q), date('Y-m-d', strtotime('+2 days')), 'deal', $q['deal_id'], now()]);
    quote_notify($q, '[Proposal declined] ' . quote_client($q) . ' — ' . $q['number'], quote_client($q) . ' declined “' . ($q['title'] ?: $q['number']) . '”.' . (trim($reason) !== '' ? "\n\nTheir reason: " . trim($reason) : ''));
}

/* ---------- Payment ---------- */
function quote_checkout(array $q, array $items, array $t): array {
    $lines = [];
    $label = $q['title'] ?: 'Proposal ' . $q['number'];
    $chosen = array_filter($items, function ($i) { return !$i['optional'] || $i['selected']; });
    if ($t['deposit'] > 0) {
        if ($t['pct'] >= 100) {
            foreach ($chosen as $it) if ($it['interval'] !== 'month' && (float)$it['unit_price'] * (float)$it['quantity'] > 0) {
                $whole = floor((float)$it['quantity']) == (float)$it['quantity'];
                $lines[] = ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round(($whole ? (float)$it['unit_price'] : (float)$it['unit_price'] * (float)$it['quantity']) * 100), 'product_data' => ['name' => mb_substr($it['description'], 0, 200)]], 'quantity' => $whole ? (int)$it['quantity'] : 1];
            }
        } else {
            $lines[] = ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round($t['deposit'] * 100), 'product_data' => ['name' => $t['pct'] . '% deposit — ' . mb_substr($label, 0, 150), 'description' => 'Balance of ' . money($t['balance']) . ' is invoiced on delivery.']], 'quantity' => 1];
        }
    }
    foreach ($chosen as $it) if ($it['interval'] === 'month' && (float)$it['unit_price'] > 0) {
        $lines[] = ['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round((float)$it['unit_price'] * (float)$it['quantity'] * 100), 'recurring' => ['interval' => 'month'], 'product_data' => ['name' => mb_substr($it['description'], 0, 200)]], 'quantity' => 1];
    }
    $sub = $t['monthly'] > 0;
    $params = [
        'mode' => $sub ? 'subscription' : 'payment', 'line_items' => $lines, 'client_reference_id' => $q['number'],
        'metadata' => ['kind' => 'quote', 'quote' => $q['number']],
        'success_url' => quote_link($q) . '&paid=1&session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => quote_link($q) . '&cancelled=1',
        'custom_text' => ['submit' => ['message' => 'You’ll get a receipt by email, and a link to your ' . quote_from($q) . ' client portal.']],
    ];
    if ($q['email']) $params['customer_email'] = $q['email'];
    if (setting('shop_tax') === '1') { $params['automatic_tax'] = ['enabled' => true]; $params['billing_address_collection'] = 'required'; }
    if ($sub) $params['subscription_data'] = ['metadata' => ['kind' => 'quote', 'quote' => $q['number']], 'description' => quote_from($q) . ' · ' . quote_client($q) . ' · ' . $q['number']];
    else { $params['invoice_creation'] = ['enabled' => true, 'invoice_data' => ['description' => quote_from($q) . ' proposal ' . $q['number']]]; $params['payment_intent_data'] = ['metadata' => ['kind' => 'quote', 'quote' => $q['number']], 'description' => quote_from($q) . ' · ' . quote_client($q) . ' · ' . $q['number']]; $params['customer_creation'] = 'always'; }
    $s = stripe('POST', 'checkout/sessions', $params, 'quote-' . $q['number'] . '-' . substr(md5(json_encode($lines)), 0, 10) . '-' . date('YmdH'));
    q('UPDATE quotes SET stripe_session = ?, live = ?, updated_at = ? WHERE id = ?', [(string)$s['id'], stripe_live() ? 1 : 0, now(), $q['id']]);
    return $s;
}
function quote_sync_session(array $q, ?array $s = null): array {
    if ($q['stripe_session'] === '' || $q['status'] === 'won') return $q;
    if ($s === null) $s = stripe('GET', 'checkout/sessions/' . rawurlencode($q['stripe_session']));
    if (($s['id'] ?? '') === $q['stripe_session'] && ($s['status'] ?? '') === 'complete' && in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) quote_fulfil($q, $s);
    return load_quote((int)$q['id']);
}

/* ---------- Winning ---------- */
// Runs once when a quote is won: paid through Stripe ($s), accepted with nothing due now, or marked won by you ($s = null).
function quote_fulfil(array $q, ?array $s, string $method = ''): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $claim = $pdo->prepare("UPDATE quotes SET status = 'won', paid_at = ?, updated_at = ? WHERE id = ? AND status IN ('sent', 'viewed', 'accepted', 'draft', 'expired')");
        $claim->execute([$s ? now() : null, now(), $q['id']]);
        if ($claim->rowCount() === 0) { $pdo->commit(); return; }
        $items = array_values(array_filter(quote_items((int)$q['id']), function ($i) { return !$i['optional'] || $i['selected']; }));
        $t = quote_totals($q, $items);
        $paid = $s ? round((float)($s['amount_total'] ?? 0) / 100, 2) : 0;
        $tax = $s ? round((float)($s['total_details']['amount_tax'] ?? 0) / 100, 2) : 0;
        $brand = $q['brand'] ?: SHOP_BRAND;
        $client = quote_client($q);
        if (!$q['contact_id'] && $q['email']) { $c = find_or_create_contact(person_name($q), $q['email'], (string)$q['company']); $q['contact_id'] = $c['id']; }
        $contact = $q['contact_id'] ? one('SELECT * FROM contacts WHERE id = ?', [$q['contact_id']]) : null;
        $companyId = $q['company_id'] ?: ($contact['company_id'] ?? null);

        // Deal → won
        $value = $t['one_time'] + $t['monthly'];
        if ($q['deal_id'] && one('SELECT 1 FROM deals WHERE id = ?', [$q['deal_id']])) {
            $dealId = (int)$q['deal_id'];
            q("UPDATE deals SET stage = 'won', value = ?, closed_at = ?, updated_at = ? WHERE id = ?", [$value, now(), now(), $dealId]);
        } else {
            q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at, closed_at) VALUES (?,?,?,?, 'won', ?, 'Proposal', ?, ?, ?, ?)",
                [($q['title'] ?: 'Proposal') . ' — ' . $client, $companyId, $q['contact_id'], $brand, $value, 'Won from proposal ' . $q['number'] . '.', now(), now(), now()]);
            $dealId = (int)db()->lastInsertId();
        }

        // What was paid today, and the balance still to bill
        $invoiceId = null; $balanceId = null; $pos = 0;
        $newInvoice = function (string $status, string $notes, string $due) use ($brand, $companyId, $q, $s) {
            q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, status, tax_rate, notes, paid_at, paid_method, share_token, stripe_invoice, created_at, updated_at) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?,?,?)",
                [next_invoice_number(), $brand, $companyId, $q['contact_id'], today(), $due, $status, $notes, $status === 'paid' ? now() : null, $status === 'paid' ? 'Stripe' : '', bin2hex(random_bytes(16)), $status === 'paid' ? (string)($s['invoice'] ?? '') : '', now(), now()]);
            return (int)db()->lastInsertId();
        };
        $line = function (int $inv, string $d, float $qty, float $price) use (&$pos) { q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$inv, mb_substr($d, 0, 300), $qty, $price, $pos++]); };
        if ($s && $paid > 0) {
            $invoiceId = $newInvoice('paid', 'Paid online · Proposal ' . $q['number'], today());
            if ($t['pct'] >= 100) foreach ($items as $it) { if ($it['interval'] !== 'month') $line($invoiceId, $it['description'], (float)$it['quantity'], (float)$it['unit_price']); }
            elseif ($t['deposit'] > 0) $line($invoiceId, $t['pct'] . '% deposit — ' . ($q['title'] ?: 'Proposal ' . $q['number']), 1, $t['deposit']);
            foreach ($items as $it) if ($it['interval'] === 'month') $line($invoiceId, $it['description'] . ' — first month', (float)$it['quantity'], (float)$it['unit_price']);
            if ($tax > 0) $line($invoiceId, 'Sales tax', 1, $tax);
            $diff = round($paid - invoice_total($invoiceId)['total'], 2);
            if (abs($diff) >= 0.01) $line($invoiceId, $diff < 0 ? 'Discount' : 'Adjustment', 1, $diff);
        }
        $rest = $s ? $t['balance'] : $t['one_time'];
        if ($rest > 0) {
            $pos = 0;
            $balanceId = $newInvoice('draft', ($t['pct'] > 0 && $s ? 'Balance for proposal ' . $q['number'] . '. Send this when the work is delivered.' : 'Proposal ' . $q['number'] . '. Send this when the work is delivered.'), date('Y-m-d', strtotime('+14 days')));
            if ($s && $t['pct'] > 0) $line($balanceId, (100 - $t['pct']) . '% balance — ' . ($q['title'] ?: 'Proposal ' . $q['number']), 1, $rest);
            else foreach ($items as $it) if ($it['interval'] !== 'month') $line($balanceId, $it['description'], (float)$it['quantity'], (float)$it['unit_price']);
        }

        // Project in the client portal
        $projectId = null;
        $steps = product_lines($q['steps']);
        // Leave out steps that only belong to optional products the client didn't choose.
        $stepsOf = function (array $list) { $out = []; foreach ($list as $i) if ($i['product_id']) $out = array_merge($out, product_lines((string)val('SELECT steps FROM products WHERE id = ?', [$i['product_id']]))); return $out; };
        $skipped = array_diff($stepsOf(array_filter(quote_items((int)$q['id']), function ($i) { return $i['optional'] && !$i['selected']; })), $stepsOf($items));
        if ($skipped) $steps = array_values(array_diff($steps, $skipped));
        if ($steps || $items) {
            $days = (int)$q['timeline_days'];
            q("INSERT INTO projects (name, company_id, contact_id, deal_id, brand, status, progress, start_date, due_date, summary, shared, created_at, updated_at) VALUES (?,?,?,?,?, 'planning', 0, ?, ?, ?, 1, ?, ?)",
                [($q['title'] ?: 'Project') . ' — ' . $client, $companyId, $q['contact_id'], $dealId, $brand, today(), $days ? date('Y-m-d', strtotime("+$days days")) : null, 'From proposal ' . $q['number'] . '.', now(), now()]);
            $projectId = (int)db()->lastInsertId();
            foreach ($steps ?: ['Kickoff call', 'Work in progress', 'Review and changes', 'Delivered'] as $i => $st) q('INSERT INTO project_steps (project_id, title, position) VALUES (?,?,?)', [$projectId, mb_substr($st, 0, 200), ($i + 1) * 10]);
            q("INSERT INTO project_posts (project_id, author, kind, body, created_at) VALUES (?, 'staff', 'update', ?, ?)", [$projectId,
                "Welcome! Your proposal is accepted and we’re getting started. We’ll be in touch within one business day to schedule your kickoff call.\n\nThis is where you’ll see progress, review drafts, approve work and send us files.", now()]);
        }

        // Site Stats, when a website or ads product is part of it
        $siteId = null;
        $needsSite = (bool)array_filter($items, function ($i) { return $i['product_id'] && (int)val('SELECT site FROM products WHERE id = ?', [$i['product_id']]) === 1; });
        if ($needsSite && ($dom = clean_domain((string)($q['website'] ?? '')))) {
            $siteId = (int)(val("SELECT id FROM sites WHERE ',' || REPLACE(domains, ' ', '') || ',' LIKE ?", ['%,' . $dom . ',%']) ?: 0) ?: null;
            if (!$siteId) {
                q('INSERT INTO sites (name, company_id, domains, public_id, shared, monthly_report, created_at) VALUES (?,?,?,?,1,1,?)', [$client . ' website', $companyId, $dom, site_key(), now()]);
                $siteId = (int)db()->lastInsertId();
                foreach ([['Form sent', 'Form submit'], ['Phone call', 'Phone call'], ['Email click', 'Email click']] as [$n, $m]) q("INSERT INTO site_goals (site_id, name, kind, match) VALUES (?, ?, 'event', ?)", [$siteId, $n, $m]);
            }
        }

        if ($q['contact_id']) portal_enable((int)$q['contact_id'], true);
        $skus = array_map(function ($i) { return $i['product_id'] ? (string)val('SELECT sku FROM products WHERE id = ?', [$i['product_id']]) : ''; }, $items);
        $built = (bool)array_filter($items, function ($i) { return $i['product_id'] && val('SELECT category FROM products WHERE id = ?', [$i['product_id']]) === 'website'; });
        foreach ($skus as $sku) if (isset(AD_PLANS_SKUS[$sku]) && $companyId) { require_once __DIR__ . '/ads.php'; ads_enable_for((int)$companyId, -(int)$q['id'], $sku, [], $siteId); }
        foreach ($skus as $sku) if (isset(CARE_TIERS[$sku])) { require_once __DIR__ . '/care.php'; care_enable_for($siteId, -(int)$q['id'], $client, $sku, ['built_by_us' => $built, 'care_url' => (string)($q['website'] ?? '')]); }
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Schedule kickoff call with ' . ($contact ? person_name($contact) : $client) . ' (' . $q['number'] . ')', date('Y-m-d', strtotime('+1 day')), 'deal', $dealId, now()]);
        if ($balanceId) q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,0,?,?,?)', ['Send the ' . ($s && $t['pct'] > 0 ? 'balance ' : '') . 'invoice to ' . $client . ' when the work is delivered', $q['timeline_days'] ? date('Y-m-d', strtotime('+' . (int)$q['timeline_days'] . ' days')) : null, 'deal', $dealId, now()]);
        if ($needsSite && !$siteId) q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,0,?,?,?)', ['Add Site Stats for ' . $client . ' once the domain is known', date('Y-m-d', strtotime('+7 days')), 'deal', $dealId, now()]);

        q('UPDATE quotes SET deal_id = ?, contact_id = ?, company_id = ?, invoice_id = ?, balance_invoice_id = ?, project_id = ?, paid_total = ?, stripe_customer = ?, stripe_subscription = ?, stripe_payment = ?, sub_status = ?, updated_at = ? WHERE id = ?',
            [$dealId, $q['contact_id'], $companyId, $invoiceId, $balanceId, $projectId, $paid, (string)($s['customer'] ?? ''), (string)($s['subscription'] ?? ''), (string)($s['payment_intent'] ?? ''), $s && ($s['mode'] ?? '') === 'subscription' ? 'active' : '', now(), $q['id']]);
        $q['deal_id'] = $dealId;
        quote_log($q, 'Proposal ' . $q['number'] . ' won' . ($paid > 0 ? ': paid ' . money($paid) . ' online' : ($method !== '' ? ' (' . $method . ')' : '')) . '. ' . ($balanceId ? 'Invoice for ' . money($rest) . ' drafted for delivery. ' : '') . 'Project and client portal set up.');
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    quote_mail_won(load_quote((int)$q['id']));
}

function quote_mail_won(array $q): void {
    $items = array_values(array_filter(quote_items((int)$q['id']), function ($i) { return !$i['optional'] || $i['selected']; }));
    $t = quote_totals($q, $items);
    $from = quote_from($q);
    $c = $q['contact_id'] ? one('SELECT * FROM contacts WHERE id = ?', [$q['contact_id']]) : null;
    $list = implode("\n", array_map(function ($i) { return '  ' . ((float)$i['quantity'] != 1 ? rtrim(rtrim(number_format((float)$i['quantity'], 2), '0'), '.') . ' × ' : '') . $i['description'] . ' — ' . money((float)$i['quantity'] * (float)$i['unit_price']) . ($i['interval'] === 'month' ? '/mo' : ''); }, $items));
    if ($c && $c['email'] !== '') {
        send_mail($c['email'], 'Accepted: ' . ($q['title'] ?: 'your proposal') . ' — ' . $from,
            'Hi ' . ($c['first_name'] ?: 'there') . ",\n\nThank you! Your proposal is accepted" . ($q['paid_total'] > 0 ? ' and your payment of ' . money($q['paid_total']) . ' went through. Stripe has emailed you a receipt' : '') . ".\n\n"
            . "$list\n\n" . ($t['balance'] > 0 && $q['paid_total'] > 0 ? 'Remaining balance of ' . money($t['balance']) . " is invoiced when the work is delivered.\n" : '') . ($t['monthly'] > 0 ? 'Monthly services: ' . money($t['monthly']) . "/month, billed automatically on the same date each month until you cancel.\nCancel online any time in your client portal (Plans & billing): " . abs_url('portal-login.php?to=plans') . "\n" : '')
            . "\nWhat happens next\n  1. " . shop_kickoff_line() . "\n  2. Your client portal is ready. Follow progress, send files and approve work there:\n     " . portal_invite_url($c) . "\n\nQuestions? Just reply to this email.\n\n$from\n" . setting('business_email'), setting('business_email'), $from);
    }
    quote_notify($q, '[Proposal won] ' . quote_client($q) . ' — ' . $q['number'] . ($q['paid_total'] > 0 ? ' · ' . money($q['paid_total']) . ' paid' : ''),
        quote_client($q) . ' accepted “' . ($q['title'] ?: $q['number']) . '”' . ($q['accepted_name'] ? ' (signed by ' . $q['accepted_name'] . ')' : '') . ".\n\n$list\n\n" . ($q['paid_total'] > 0 ? 'Paid today: ' . money($q['paid_total']) . "\n" : '') . ($t['balance'] > 0 || !$q['paid_total'] ? 'Invoice drafted for the rest, ready to send on delivery.' . "\n" : '') . 'The deal is won and the project and client portal are set up.');
}

/* ---------- Upkeep (cron) ---------- */
function quotes_cleanup(): string {
    $n = 0;
    foreach (all("SELECT * FROM quotes WHERE status IN ('sent','viewed') AND valid_until IS NOT NULL AND valid_until < ?", [today()]) as $q) {
        q("UPDATE quotes SET status = 'expired', updated_at = ? WHERE id = ?", [now(), $q['id']]);
        quote_log($q, 'Proposal ' . $q['number'] . ' expired without a reply.'); $n++;
    }
    // One automatic reminder, three days before a quote expires.
    foreach (all("SELECT * FROM quotes WHERE status IN ('sent','viewed') AND reminded_at IS NULL AND valid_until = ?", [date('Y-m-d', strtotime('+3 days'))]) as $q) {
        $q = load_quote((int)$q['id']);
        if ($q && $q['email']) {
            $first = $q['first_name'] ?: 'there';
            if (email_quote($q, $q['email'], "Hi $first,\n\nA quick reminder that our proposal for " . ($q['title'] ?: 'your project') . ' is valid until ' . fmt_date($q['valid_until']) . ". If you have any questions, just reply to this email.", '', true)) $n++;
        }
    }
    foreach (all("SELECT * FROM quotes WHERE status = 'accepted' AND stripe_session != '' AND updated_at > ?", [date('Y-m-d H:i:s', time() - 3 * 86400)]) as $q) {
        try { quote_sync_session(load_quote((int)$q['id'])); } catch (Throwable $e) { error_log('Quote sync ' . $q['number'] . ': ' . $e->getMessage()); break; }
    }
    return $n ? "$n quote(s) updated" : '';
}
