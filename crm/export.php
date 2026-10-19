<?php
// CSV exports for contacts, companies, deals, invoices and consultations.
require __DIR__ . '/app/bootstrap.php';
require_user();
$type = get('type');
$sets = [
    'contacts' => ["SELECT c.first_name, c.last_name, c.email, c.phone, c.title, co.name company, c.notes, c.created_at FROM contacts c LEFT JOIN companies co ON co.id = c.company_id ORDER BY c.first_name", ['First name', 'Last name', 'Email', 'Phone', 'Title', 'Company', 'Notes', 'Added']],
    'companies' => ['SELECT name, website, phone, industry, city, address, brand, notes, created_at FROM companies ORDER BY name', ['Name', 'Website', 'Phone', 'Industry', 'City', 'Address', 'Brand', 'Notes', 'Added']],
    'deals' => ['SELECT d.title, d.stage, d.brand, d.value, d.source, d.expected_close, co.name company, c.email, d.created_at, d.closed_at FROM deals d LEFT JOIN companies co ON co.id = d.company_id LEFT JOIN contacts c ON c.id = d.contact_id ORDER BY d.created_at DESC', ['Deal', 'Stage', 'Brand', 'Value', 'Source', 'Expected close', 'Company', 'Contact email', 'Created', 'Closed']],
    'consultations' => ["SELECT s.date, s.start_time, c.first_name || ' ' || c.last_name, co.name, s.topic, s.status, s.minutes / 60.0, s.rate, s.minutes * s.rate / 60.0, i.number FROM consultations s LEFT JOIN contacts c ON c.id = s.contact_id LEFT JOIN companies co ON co.id = s.company_id LEFT JOIN invoices i ON i.id = s.invoice_id ORDER BY s.date DESC", ['Date', 'Start', 'Contact', 'Company', 'Topic', 'Status', 'Hours', 'Rate', 'Amount', 'Invoice']],
];
if ($type === 'invoices') {
    $rows = [];
    foreach (all('SELECT i.*, co.name company FROM invoices i LEFT JOIN companies co ON co.id = i.company_id ORDER BY i.issue_date DESC') as $i) {
        $t = invoice_total((int)$i['id']);
        $rows[] = [$i['number'], $i['brand'], $i['company'], $i['issue_date'], $i['due_date'], $i['status'], $t['subtotal'], $t['tax'], $t['total'], $i['paid_at'], $i['paid_method']];
    }
    $head = ['Number', 'Brand', 'Company', 'Issued', 'Due', 'Status', 'Subtotal', 'Tax', 'Total', 'Paid at', 'Paid by'];
} elseif (isset($sets[$type])) {
    $rows = q($sets[$type][0])->fetchAll(PDO::FETCH_NUM); $head = $sets[$type][1];
} else { http_response_code(404); exit('Unknown export.'); }
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="syndicate-' . $type . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $head);
$safe = function ($v) { $v = (string)$v; return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; };
foreach ($rows as $r) fputcsv($out, array_map($safe, array_values($r)));
fclose($out);
