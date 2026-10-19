<?php
// Monday pipeline email: what needs a nudge this week. Sent once a week by cron (Monday from 7 AM, CRM time zone)
// to the store notify address, or any time from the dashboard ("Email me this week's summary").
require_once __DIR__ . '/analytics.php';

function pipeline_digest_due(): bool {
    return date('N') === '1' && (int)date('G') >= 7 && setting('digest_week') !== date('o-W');
}

function pipeline_digest(): array {
    $today = today(); $week = date('Y-m-d', strtotime('+7 days')); $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    $link = function (string $p) { return abs_url($p); };
    $who = function (array $r) { return trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ($r['company'] ?? '') ?: 'No contact'; };
    $s = [];

    // Tasks: overdue first, then due this week.
    $tasks = all("SELECT * FROM tasks WHERE done_at IS NULL AND due_date IS NOT NULL AND due_date <= ? ORDER BY due_date, priority DESC, id LIMIT 15", [$week]);
    $late = (int)val("SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND due_date < ?", [$today]);
    if ($tasks) $s[] = ['Tasks due' . ($late ? " ($late overdue)" : ''), array_map(function ($t) use ($today) {
        return ($t['due_date'] < $today ? 'OVERDUE ' : '') . date('D M j', strtotime($t['due_date'])) . ' · ' . $t['title'];
    }, $tasks), $link('tasks.php')];

    // Open deals nobody has touched in 14 days.
    $stale = all("SELECT d.*, c.first_name, c.last_name, co.name company,
            MAX(d.updated_at, COALESCE((SELECT MAX(created_at) FROM activities a WHERE a.entity_type = 'deal' AND a.entity_id = d.id), '')) touched
        FROM deals d LEFT JOIN contacts c ON c.id = d.contact_id LEFT JOIN companies co ON co.id = d.company_id
        WHERE d.stage IN ('new','contacted','qualified','proposal') GROUP BY d.id HAVING touched < ? ORDER BY touched LIMIT 12", [date('Y-m-d H:i:s', strtotime('-14 days'))]);
    if ($stale) $s[] = ['Deals gone quiet (no activity in 14+ days)', array_map(function ($d) use ($who, $link) {
        return $d['title'] . ' · ' . $who($d) . ' · ' . (STAGES[$d['stage']] ?? $d['stage']) . ($d['value'] > 0 ? ' · ' . money($d['value'], false) : '')
            . ' · last touched ' . ago($d['touched']) . "\n    " . $link('deal.php?id=' . $d['id']);
    }, $stale), null];

    // Quotes out with the client.
    $quotes = all("SELECT q.*, c.first_name, c.last_name FROM quotes q LEFT JOIN contacts c ON c.id = q.contact_id
        WHERE q.status IN ('sent','viewed','accepted') ORDER BY q.valid_until LIMIT 12");
    if ($quotes) $s[] = ['Quotes waiting on the client', array_map(function ($q) use ($who, $today, $week, $link) {
        $exp = $q['valid_until'] ?: '';
        $note = $q['status'] === 'accepted' ? 'accepted, deposit not paid' : ($q['status'] === 'viewed' ? 'viewed ' . ago((string)$q['viewed_at']) : 'not opened yet');
        return $q['number'] . ' · ' . $who($q) . ' · ' . $note . ($exp && $exp <= $week ? ' · EXPIRES ' . date('M j', strtotime($exp)) : '') . "\n    " . $link('quote.php?id=' . $q['id']);
    }, $quotes), null];

    // Invoices past due.
    $inv = all("SELECT i.*, c.first_name, c.last_name, co.name company FROM invoices i LEFT JOIN contacts c ON c.id = i.contact_id LEFT JOIN companies co ON co.id = i.company_id
        WHERE i.status = 'sent' AND i.due_date < ? ORDER BY i.due_date LIMIT 12", [$today]);
    if ($inv) $s[] = ['Overdue invoices', array_map(function ($i) use ($who, $link) {
        return $i['number'] . ' · ' . $who($i) . ' · ' . money(invoice_total((int)$i['id'])['total']) . ' · due ' . date('M j', strtotime($i['due_date'])) . "\n    " . $link('invoice.php?id=' . $i['id']);
    }, $inv), null];

    // Checkouts started but not paid this week (each already gets one reminder email automatically).
    $carts = all("SELECT * FROM orders WHERE status IN ('pending','expired') AND stripe_session != '' AND created_at >= ? AND email != ''
        AND email NOT IN (SELECT email FROM orders WHERE status = 'paid' AND created_at >= ?) GROUP BY email ORDER BY created_at DESC LIMIT 10", [$since, $since]);
    if ($carts) $s[] = ['Started checkout, didn’t pay', array_map(function ($o) use ($link) {
        return ($o['business'] ?: $o['name']) . ' · ' . $o['email'] . ' · ' . money((float)$o['one_time'] + (float)$o['monthly']) . ($o['offer'] ? ' · offer ' . $o['offer'] : '') . ' · ' . ago($o['created_at']) . "\n    " . $link('store-order.php?id=' . $o['id']);
    }, $carts), null];

    // Meetings this week.
    $meet = all("SELECT b.*, t.name type_name FROM bookings b LEFT JOIN meeting_types t ON t.id = b.type_id WHERE b.status = 'confirmed' AND b.starts_at BETWEEN ? AND ? ORDER BY b.starts_at", [time(), strtotime('+7 days')]);
    if ($meet) $s[] = ['Calls booked this week', array_map(function ($b) {
        return date('D M j, g:i A', (int)$b['starts_at']) . ' · ' . ($b['type_name'] ?: 'Meeting') . ' · ' . $b['name'] . ($b['business'] ? ' (' . $b['business'] . ')' : '');
    }, $meet), null];

    // Outreach: conversations waiting on Grady, and the week's numbers.
    $needs = all("SELECT * FROM outreach_prospects WHERE status IN ('needs_you','interested') AND updated_at >= ? ORDER BY updated_at DESC LIMIT 10", [$since]);
    if ($needs) $s[] = ['Outreach: your turn', array_map(function ($p) use ($link) {
        return $p['business'] . ' · ' . ($p['status'] === 'interested' ? 'interested' : 'needs your answer') . "\n    " . $link('outreach.php?id=' . $p['id']);
    }, $needs), null];
    $ok = []; foreach (all('SELECT kind, COUNT(*) n FROM outreach_log WHERE created_at >= ? GROUP BY kind', [$since]) as $r) $ok[$r['kind']] = (int)$r['n'];
    $outLine = !empty($ok['sent']) ? 'Outreach: ' . $ok['sent'] . ' sent, ' . (($ok['reply'] ?? 0) + ($ok['declined'] ?? 0) + ($ok['unsubscribe'] ?? 0)) . ' replies, ' . ($ok['bounce'] ?? 0) . ' bounced'
        . (setting('outreach_on') === '1' ? '' : ' (outreach is switched off)') : '';

    // Last week in numbers.
    $leads = (int)val("SELECT COUNT(*) FROM deals WHERE created_at >= ? AND stage != 'won'", [$since]);
    $won = one("SELECT COUNT(*) n, COALESCE(SUM(value),0) v FROM deals WHERE stage = 'won' AND closed_at >= ?", [$since]);
    $paid = 0.0; foreach (all("SELECT id FROM invoices WHERE status = 'paid' AND paid_at >= ?", [$since]) as $p) $paid += invoice_total((int)$p['id'])['total'];
    $nums = ["New leads: $leads", 'Deals won: ' . (int)$won['n'] . ($won['v'] > 0 ? ' (' . money($won['v'], false) . ')' : ''), 'Money in: ' . money($paid)];
    if ($outLine) $nums[] = $outLine;

    // Campaign pages (Site Stats on the marketing site). Counts only; the pages don't record who visited.
    try {
        $site = one("SELECT id FROM sites WHERE public_id = ?", ['UIOdnDz160w8']);
        if ($site) foreach (['starter-website-offer' => 'Starter offer page', 'gaming-communities' => 'Gaming page'] as $slug => $label) {
            $v = (int)aval("SELECT COUNT(DISTINCT visitor) FROM events WHERE site_id = ? AND ts >= ? AND type = 'pageview' AND path LIKE ?", [$site['id'], strtotime('-7 days'), '%' . $slug . '%']);
            $nums[] = "$label visitors: $v";
        }
    } catch (Throwable $e) { /* analytics is optional */ }
    $s[] = ['Last 7 days', $nums, null];

    $nudges = count($tasks) + count($stale) + count($quotes) + count($inv) + count($carts) + count($needs);
    $body = "Here’s your week, " . date('l, F j') . ".\n";
    foreach ($s as [$title, $lines, $more]) {
        $body .= "\n" . strtoupper($title) . "\n" . implode("\n", array_map(function ($l) { return '- ' . $l; }, $lines)) . "\n" . ($more ? "All: $more\n" : '');
    }
    $body .= "\nOpen the CRM: " . $link('') . "\n";
    $subject = $nudges ? "This week: $nudges thing" . ($nudges === 1 ? '' : 's') . ' to follow up' : 'This week: you’re all caught up';
    return ['subject' => $subject, 'body' => $body, 'count' => $nudges];
}

function pipeline_digest_send(bool $force = false): string {
    if (!$force && !pipeline_digest_due()) return '';
    setting('digest_week', date('o-W'));
    $d = pipeline_digest();
    $to = setting('shop_notify') ?: setting('business_email');
    send_mail($to, $d['subject'], $d['body'], '', 'Syndicate CRM');
    return 'Weekly summary emailed to ' . $to;
}
