<?php
// Consultation calendar. ?id=N downloads one event (signed in). ?feed=TOKEN is a private subscription feed.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/ics.php';
$feed = get('feed');
if ($feed !== '') {
    $tok = setting('calendar_token');
    if ($tok === '' || !hash_equals($tok, $feed)) { http_response_code(404); exit('Calendar not found.'); }
    $rows = consult_rows("s.status IN ('scheduled','completed') AND s.date >= ?", [date('Y-m-d', strtotime('-60 days'))]);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Cache-Control: no-cache');
    echo ics_calendar(array_map('ics_event', $rows), 'Syndicate consultations');
    exit;
}
require_user();
$rows = consult_rows('s.id = ?', [(int)get('id')]);
if (!$rows) { http_response_code(404); exit('Consultation not found.'); }
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="consultation-' . $rows[0]['date'] . '.ics"');
echo ics_calendar([ics_event($rows[0])], 'Syndicate consultation');
