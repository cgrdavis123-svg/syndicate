<?php
// Daily totals for one site as a spreadsheet (CSV), for the selected period and filters.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/analytics-view.php';
require_user();
$site = one('SELECT * FROM sites WHERE id = ?', [(int)get('id')]);
if (!$site) { http_response_code(404); exit('Not found'); }
$p = an_params(); $range = an_range($p['range'], $p['from'], $p['to']); $goals = site_goals((int)$site['id']);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9]+/i', '-', $site['name']) . '-' . date('Y-m-d', $range['from']) . '-to-' . date('Y-m-d', $range['to'] - 1) . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Visitors', 'Visits', 'Pageviews', 'Pages per visit', 'Bounce rate %', 'Avg engaged seconds', 'Conversions', 'Conversion rate %']);
for ($t = $range['from']; $t < $range['to']; $t = strtotime('+1 day', $t)) {
    [$w, $a] = an_where((int)$site['id'], $t, strtotime('+1 day', $t), $p['f'], $goals);
    $x = an_totals($w, $a, $goals);
    fputcsv($out, [date('Y-m-d', $t), $x['visitors'], $x['visits'], $x['pageviews'], round($x['ppv'], 2), round($x['bounce'], 1), round($x['duration']), $x['conversions'], round($x['cr'], 2)]);
}
