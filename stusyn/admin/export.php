<?php
// Downloads applicants as CSV, using the same filters as the Applicants page.
require __DIR__ . '/../app/bootstrap.php';
require_admin();

$where = []; $args = [];
if (isset(APP_STATUSES[get('status')])) { $where[] = 'status = ?'; $args[] = get('status'); }
if (get('job') === 'open') $where[] = 'job_id IS NULL';
elseif (get('job') !== '') { $where[] = 'job_id = ?'; $args[] = (int)get('job'); }
if (get('q') !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR location LIKE ? OR job_title LIKE ? OR why LIKE ?)'; array_push($args, ...array_fill(0, 5, '%' . get('q') . '%')); }
$st = db()->prepare('SELECT * FROM applications ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC');
$st->execute($args);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="syndicate-applicants-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8
fputcsv($out, ['Applied', 'Name', 'Email', 'Phone', 'Location', 'Role', 'Stage', 'Rating', 'LinkedIn', 'Portfolio', 'US work authorized', 'Source', 'Why', 'Notes', 'Profile']);
// Prefix cells that spreadsheet apps would treat as formulas.
$safe = function ($v) { $v = (string)$v; return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; };
foreach ($st as $r) {
    fputcsv($out, array_map($safe, [$r['created_at'], $r['name'], $r['email'], $r['phone'], $r['location'], $r['job_title'], APP_STATUSES[$r['status']] ?? $r['status'],
        $r['rating'], $r['linkedin'], $r['portfolio'], $r['work_authorized'], $r['source'], $r['why'], $r['notes'], abs_url('admin/application.php?id=' . $r['id'])]));
}
fclose($out);
