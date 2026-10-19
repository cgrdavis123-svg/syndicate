<?php
// Builds iCalendar (.ics) events for consultations.
function ics_escape(string $s): string { return str_replace(["\r\n", "\n"], '\n', addcslashes($s, ',;\\')); }
function ics_event(array $c): string {
    $tz = config('timezone');
    $start = $c['date'] . ' ' . ($c['start_time'] ?: '09:00');
    $begin = new DateTime($start, new DateTimeZone($tz));
    $end = (clone $begin)->modify('+' . max(15, (int)$c['minutes']) . ' minutes');
    $who = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['company'] ?? 'Client');
    $lines = [
        'BEGIN:VEVENT',
        'UID:consultation-' . $c['id'] . '@' . parse_url((string)config('app_url'), PHP_URL_HOST),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART;TZID=' . $tz . ':' . $begin->format('Ymd\THis'),
        'DTEND;TZID=' . $tz . ':' . $end->format('Ymd\THis'),
        'SUMMARY:' . ics_escape(($c['topic'] ?: 'Consultation') . ': ' . $who),
        'DESCRIPTION:' . ics_escape(trim(($c['email'] ?? '') . "\n" . ($c['notes'] ?? '') . "\n" . abs_url('consultation-edit.php?id=' . $c['id']))),
        'STATUS:' . ($c['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
        'END:VEVENT',
    ];
    return implode("\r\n", $lines);
}
function ics_calendar(array $events, string $name): string {
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Syndicate Holdings Group//CRM//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nX-WR-CALNAME:" . ics_escape($name) . "\r\nX-WR-TIMEZONE:" . config('timezone') . "\r\n"
        . implode("\r\n", $events) . ($events ? "\r\n" : '') . "END:VCALENDAR\r\n";
}
function consult_rows(string $where, array $args): array {
    return all("SELECT s.*, c.first_name, c.last_name, c.email, co.name company FROM consultations s LEFT JOIN contacts c ON c.id = s.contact_id LEFT JOIN companies co ON co.id = s.company_id WHERE $where", $args);
}
