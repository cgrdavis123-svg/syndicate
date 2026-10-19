<?php
// Recurring invoices: creates each invoice when its date arrives, and emails it if auto-send is on.
// Runs when you open the dashboard or Invoices, and from cron.php if you set up a cPanel cron job.
require_once __DIR__ . '/invoice-doc.php';

// Returns ['created' => int, 'sent' => int]
function run_recurring(): array {
    $made = [];
    db()->exec('BEGIN IMMEDIATE'); // one run at a time, so two page loads can't create the same invoice twice
    try {
        foreach (all("SELECT * FROM invoices WHERE repeat_every != '' AND status != 'void' AND repeat_next <= ? ORDER BY repeat_next, id", [today()]) as $src) {
            $items = all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position, id', [$src['id']]);
            $termDays = max(0, (int)round((strtotime($src['due_date']) - strtotime($src['issue_date'])) / 86400));
            $n = (int)$src['repeat_count']; $date = $src['repeat_next'];
            for ($guard = 0; $date <= today() && $guard < 12; $guard++) { // catch up on missed periods, at most 12 at once
                q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, tax_rate, notes, payment_url, share_token, status, repeat_parent, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'draft',?,?,?)",
                    [next_invoice_number(), $src['brand'], $src['company_id'], $src['contact_id'], $date, date('Y-m-d', strtotime($date . " +$termDays days")), $src['tax_rate'], $src['notes'], $src['payment_url'], bin2hex(random_bytes(16)), $src['id'], now(), now()]);
                $new = (int)db()->lastInsertId();
                foreach ($items as $it) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$new, $it['description'], $it['quantity'], $it['unit_price'], $it['position']]);
                $date = repeat_date($src['repeat_start'], $src['repeat_every'], ++$n);
                // Only the latest invoice is emailed. Older ones missed while nothing ran stay drafts, so the client doesn't get a burst of back-dated invoices.
                $made[] = [$new, $src['repeat_send'] ? ($date > today() ? 'send' : 'missed') : ''];
            }
            q('UPDATE invoices SET repeat_count = ?, repeat_next = ? WHERE id = ?', [$n, $date, $src['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        error_log('Recurring invoices failed: ' . $ex->getMessage());
        return ['created' => 0, 'sent' => 0];
    }
    $sent = 0;
    $sender = (string)val('SELECT name FROM users ORDER BY id LIMIT 1');
    foreach ($made as [$id, $mode]) {
        $autoSend = $mode === 'send';
        $inv = load_invoice($id);
        $total = invoice_total($id)['total'];
        $who = $inv['contact_id'] ? 'contact' : ($inv['company_id'] ? 'company' : '');
        if ($autoSend && filter_var($inv['email'], FILTER_VALIDATE_EMAIL) && email_invoice($inv, $inv['email'], false, invoice_message($inv, $total, false), $sender)) { $sent++; continue; }
        $why = $mode === 'missed' ? ' (created late, so it wasn\'t emailed)' : (!$autoSend ? '' : (filter_var($inv['email'], FILTER_VALIDATE_EMAIL) ? " (the email couldn't be sent)" : ' (no client email on file)'));
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', ['Review and send recurring invoice ' . $inv['number'] . $why, today(), $why ? 1 : 0, $who, $who ? (int)$inv[$who . '_id'] : null, now()]);
        if ($who) log_activity($who, (int)$inv[$who . '_id'], 'note', 'Recurring invoice ' . $inv['number'] . ' (' . money($total) . ') created as a draft.');
    }
    return ['created' => count($made), 'sent' => $sent];
}
function recurring_message(array $r): string {
    if (!$r['created']) return '';
    $drafts = $r['created'] - $r['sent'];
    return 'Recurring invoices: ' . ($r['sent'] ? $r['sent'] . ' sent' : '') . ($r['sent'] && $drafts ? ', ' : '') . ($drafts ? $drafts . ' new draft' . ($drafts === 1 ? '' : 's') . ' to review' : '') . '.';
}
