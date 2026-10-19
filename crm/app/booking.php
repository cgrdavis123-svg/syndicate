<?php
// Online booking: meeting types, your weekly hours and days off, open times, and each booking.
// A confirmed booking becomes a consultation, so it shows on your CRM calendar feed (and your phone).
require_once __DIR__ . '/shop.php';

const BOOK_LOCATIONS = ['video' => 'Video call', 'phone' => 'Phone call', 'in_person' => 'In person'];
const BOOK_KINDS = ['lead' => 'New leads (creates a deal)', 'kickoff' => 'Client kickoff (links to their project)', 'consult' => 'Consultation (paid or billable)'];
const BOOK_DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
const BOOK_HOLD_MINUTES = 35; // a paid booking holds its time while the guest pays

function book_tz(): DateTimeZone { return new DateTimeZone((string)config('timezone')); }
function book_types(bool $publicOnly = false): array { return all('SELECT * FROM meeting_types WHERE active = 1' . ($publicOnly ? ' AND public = 1' : '') . ' ORDER BY position, id'); }
function book_type(string $slug): ?array { return one('SELECT * FROM meeting_types WHERE slug = ? AND active = 1', [$slug]); }
function book_link(string $slug = ''): string { return abs_url('book.php' . ($slug !== '' ? '?type=' . rawurlencode($slug) : '')); }
function book_manage_link(array $b): string { return abs_url('book.php?b=' . $b['token']); }
function book_hours(): array { $h = json_decode(setting('booking_hours'), true); return is_array($h) ? $h : []; }
function book_local(int $ts, string $fmt = 'l, F j · g:i a'): string { return (new DateTime('@' . $ts))->setTimezone(book_tz())->format($fmt); }
function book_tz_label(): string { return (new DateTime('now', book_tz()))->format('T'); }
function book_where(array $t, array $b = []): string {
    if ($t['location'] === 'phone') return 'Phone call. We’ll call you' . (!empty($b['phone']) ? ' at ' . $b['phone'] : '') . '.';
    if ($t['location'] === 'in_person') return 'In person' . ($t['location_detail'] !== '' ? ': ' . $t['location_detail'] : '') . '.';
    $url = $t['location_detail'] !== '' ? $t['location_detail'] : setting('booking_meeting_url');
    return 'Video call. ' . ($url !== '' ? 'Join here: ' . $url : 'We’ll email you the link before the call.');
}
function book_price_label(array $t): string { return (float)$t['price'] > 0 ? money($t['price'], floor($t['price']) != $t['price']) : 'Free'; }

/* ---------- Open times ---------- */
// Busy periods (unix start/end) from your calendar: scheduled consultations, unpaid bookings still on hold, and days off.
function book_busy(int $from, int $to): array {
    $tz = book_tz(); $busy = [];
    $buffer = max(0, (int)setting('booking_buffer')) * 60;
    foreach (all("SELECT date, start_time, minutes FROM consultations WHERE status = 'scheduled' AND start_time != '' AND date BETWEEN ? AND ?", [date('Y-m-d', $from - 86400), date('Y-m-d', $to + 86400)]) as $c) {
        $s = (new DateTime($c['date'] . ' ' . $c['start_time'], $tz))->getTimestamp();
        $busy[] = [$s - $buffer, $s + max(15, (int)$c['minutes']) * 60 + $buffer];
    }
    foreach (all("SELECT starts_at, minutes FROM bookings WHERE status = 'pending' AND created_at > ? AND starts_at BETWEEN ? AND ?", [date('Y-m-d H:i:s', time() - BOOK_HOLD_MINUTES * 60), $from - 86400, $to + 86400]) as $b) {
        $busy[] = [$b['starts_at'] - $buffer, $b['starts_at'] + $b['minutes'] * 60 + $buffer];
    }
    foreach (all('SELECT * FROM booking_blocks WHERE day BETWEEN ? AND ?', [date('Y-m-d', $from - 86400), date('Y-m-d', $to + 86400)]) as $x) {
        $busy[] = [(new DateTime($x['day'] . ' ' . ($x['start_time'] ?: '00:00'), $tz))->getTimestamp(), (new DateTime($x['day'] . ' ' . ($x['end_time'] ?: '23:59'), $tz))->getTimestamp() + 60];
    }
    return $busy;
}
// Every open start time for a meeting type, as unix timestamps.
function book_slots(array $t, ?int $ignoreBooking = null): array {
    $tz = book_tz(); $now = time();
    $earliest = $now + max(0, (int)setting('booking_notice_hours')) * 3600;
    $days = max(1, min(120, (int)setting('booking_window_days')));
    $step = max(10, (int)setting('booking_step')) * 60;
    $len = max(10, (int)$t['minutes']) * 60;
    $hours = book_hours();
    $start = new DateTime('today', $tz);
    $busy = book_busy($now, $now + ($days + 1) * 86400);
    if ($ignoreBooking && ($cid = val('SELECT consultation_id FROM bookings WHERE id = ?', [$ignoreBooking]))) { // when rescheduling, the guest's current time is free for them
        $c = one('SELECT date, start_time, minutes FROM consultations WHERE id = ?', [$cid]);
        if ($c && $c['start_time']) { $s = (new DateTime($c['date'] . ' ' . $c['start_time'], $tz))->getTimestamp(); $buffer = max(0, (int)setting('booking_buffer')) * 60;
            $busy = array_values(array_filter($busy, function ($x) use ($s, $buffer, $c) { return !($x[0] === $s - $buffer && $x[1] === $s + max(15, (int)$c['minutes']) * 60 + $buffer); })); }
    }
    $perDay = [];
    if ((int)$t['max_per_day'] > 0) foreach (all("SELECT starts_at FROM bookings WHERE type_id = ? AND status IN ('confirmed','pending') AND starts_at > ?", [$t['id'], $now - 86400]) as $b) { $k = book_local((int)$b['starts_at'], 'Y-m-d'); $perDay[$k] = ($perDay[$k] ?? 0) + 1; }
    $out = [];
    for ($i = 0; $i <= $days; $i++) {
        $day = (clone $start)->modify("+$i days");
        $dow = $day->format('N'); $key = $day->format('Y-m-d');
        if (empty($hours[$dow]) || count($hours[$dow]) < 2) continue;
        if ((int)$t['max_per_day'] > 0 && ($perDay[$key] ?? 0) >= (int)$t['max_per_day']) continue;
        $open = (new DateTime($key . ' ' . $hours[$dow][0], $tz))->getTimestamp();
        $close = (new DateTime($key . ' ' . $hours[$dow][1], $tz))->getTimestamp();
        for ($s = $open; $s + $len <= $close; $s += $step) {
            if ($s < $earliest) continue;
            foreach ($busy as [$a, $z]) if ($s < $z && $s + $len > $a) continue 2;
            $out[] = $s;
        }
    }
    return $out;
}
function book_slot_open(array $t, int $ts, ?int $ignoreBooking = null): bool { return in_array($ts, book_slots($t, $ignoreBooking), true); }

/* ---------- Booking ---------- */
// Returns ['ok' => true, 'url' => Stripe page or '', 'token' => ...] or ['ok' => false, 'error' => ..., 'field' => ...].
function book_create(array $t, array $in): array {
    $s = function ($k, $max = 200) use ($in) { return mb_substr(trim((string)($in[$k] ?? '')), 0, $max); };
    $name = $s('name', 120); $email = strtolower($s('email', 190)); $phone = $s('phone', 40); $biz = $s('business', 150); $answer = $s('answer', 3000);
    $ts = (int)($in['start'] ?? 0);
    if (mb_strlen($name) < 2) return ['ok' => false, 'error' => 'Enter your name.', 'field' => 'name'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.', 'field' => 'email'];
    if ($t['location'] === 'phone' && strlen(preg_replace('/\D/', '', $phone)) < 7) return ['ok' => false, 'error' => 'Enter the phone number we should call.', 'field' => 'phone'];
    $paid = (float)$t['price'] > 0;
    if ($paid && !shop_ready()) return ['ok' => false, 'error' => 'Online payment isn’t switched on yet. Email ' . setting('business_email') . ' to book.'];
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE'); // one booking at a time, so two people can't take the same slot
    try {
        if (!book_slot_open($t, $ts)) { $pdo->exec('ROLLBACK'); return ['ok' => false, 'error' => 'Sorry, that time was just taken. Please pick another.', 'field' => 'start', 'taken' => true]; }
        $token = bin2hex(random_bytes(16));
        q('INSERT INTO bookings (token, type_id, type_name, starts_at, minutes, status, name, email, phone, business, answer, guest_tz, price, source, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$token, $t['id'], $t['name'], $ts, (int)$t['minutes'], $paid ? 'pending' : 'confirmed', $name, $email, $phone, $biz, $answer, mb_substr($s('tz', 60), 0, 60), (float)$t['price'], mb_substr($s('source', 120), 0, 120), ip_hash(), now(), now()]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
    $b = one('SELECT * FROM bookings WHERE id = ?', [$id]);
    if (!$paid) { book_confirm($b, null); return ['ok' => true, 'url' => '', 'token' => $token]; }
    try {
        $sess = stripe('POST', 'checkout/sessions', [
            'mode' => 'payment', 'customer_email' => $email, 'client_reference_id' => 'BK-' . $id,
            'line_items' => [['price_data' => ['currency' => 'usd', 'unit_amount' => (int)round((float)$t['price'] * 100), 'product_data' => ['name' => $t['name'] . ' · ' . book_local($ts, 'D, M j, g:i a T'), 'description' => (int)$t['minutes'] . ' minutes with ' . ($t['brand'] ?: setting('business_name'))]], 'quantity' => 1]],
            'metadata' => ['kind' => 'booking', 'booking' => (string)$id], 'payment_intent_data' => ['metadata' => ['kind' => 'booking', 'booking' => (string)$id], 'description' => $t['name'] . ' · ' . $name],
            'invoice_creation' => ['enabled' => true], 'customer_creation' => 'always', 'expires_at' => time() + 31 * 60,
            'success_url' => book_manage_link($b) . '&paid=1&session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => book_link($t['slug']) . '&cancelled=' . $b['token'],
        ], 'booking-' . $token);
    } catch (Throwable $e) {
        error_log('Booking checkout ' . $id . ': ' . $e->getMessage());
        q("UPDATE bookings SET status = 'expired', updated_at = ? WHERE id = ?", [now(), $id]);
        return ['ok' => false, 'error' => 'We couldn’t open the payment page. Please try again in a minute.'];
    }
    q('UPDATE bookings SET stripe_session = ?, live = ?, updated_at = ? WHERE id = ?', [(string)$sess['id'], stripe_live() ? 1 : 0, now(), $id]);
    return ['ok' => true, 'url' => (string)$sess['url'], 'token' => $token];
}
function book_sync_session(array $b, ?array $s = null): array {
    if (!in_array($b['status'], ['pending', 'expired'], true) || $b['stripe_session'] === '') return $b;
    if ($s === null) $s = stripe('GET', 'checkout/sessions/' . rawurlencode($b['stripe_session']));
    if (($s['id'] ?? '') !== $b['stripe_session']) return $b;
    if (($s['status'] ?? '') === 'complete' && ($s['payment_status'] ?? '') === 'paid') book_confirm($b, $s);
    elseif (($s['status'] ?? '') === 'expired') q("UPDATE bookings SET status = 'expired', updated_at = ? WHERE id = ? AND status = 'pending'", [now(), $b['id']]);
    return one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
}

// Confirms a booking: CRM contact and deal, a consultation on your calendar, a paid invoice if it was paid, and emails.
function book_confirm(array $b, ?array $s): void {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $claim = $pdo->prepare("UPDATE bookings SET status = 'confirmed', updated_at = ? WHERE id = ? AND (status IN ('pending', 'expired') OR (status = 'confirmed' AND consultation_id IS NULL))");
        $claim->execute([now(), $b['id']]);
        if ($claim->rowCount() === 0) { $pdo->commit(); return; }
        $t = one('SELECT * FROM meeting_types WHERE id = ?', [$b['type_id']]) ?: ['name' => $b['type_name'], 'brand' => '', 'kind' => 'lead', 'price' => $b['price'], 'location' => 'video', 'location_detail' => ''];
        $contact = find_or_create_contact($b['name'], $b['email'], $b['business']);
        if ($b['phone'] !== '' && $contact['phone'] === '') q('UPDATE contacts SET phone = ? WHERE id = ?', [$b['phone'], $contact['id']]);
        $brand = $t['brand'] ?: 'Syndicate Holdings Group';
        $local = (new DateTime('@' . $b['starts_at']))->setTimezone(book_tz());
        // Deal: kickoffs and consultations attach to the client's latest deal; a new lead gets a new deal.
        $deal = one("SELECT id FROM deals WHERE contact_id = ? AND stage != 'lost' ORDER BY stage = 'won' DESC, updated_at DESC LIMIT 1", [$contact['id']]);
        if ($t['kind'] === 'lead' && !one("SELECT id FROM deals WHERE contact_id = ? AND stage IN ('new','contacted','qualified','proposal')", [$contact['id']])) {
            q("INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?,?, 'new', 0, 'Booking', ?, ?, ?)",
                [$t['name'] . ' — ' . ($b['business'] ?: $b['name']), $contact['company_id'], $contact['id'], $brand, $b['answer'], now(), now()]);
            $dealId = (int)$pdo->lastInsertId();
        } else $dealId = $deal ? (int)$deal['id'] : null;
        $paid = $s ? round((float)($s['amount_total'] ?? 0) / 100, 2) : 0;
        q("INSERT INTO consultations (contact_id, company_id, deal_id, date, start_time, minutes, rate, topic, notes, status, billable, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?, 'scheduled', ?, ?, ?)",
            [$contact['id'], $contact['company_id'], $dealId, $local->format('Y-m-d'), $local->format('H:i'), (int)$b['minutes'], $t['kind'] === 'consult' && !$paid ? (float)setting('default_rate') : 0,
             $t['name'], trim(($b['answer'] !== '' ? $b['answer'] . "\n\n" : '') . 'Booked online' . ($paid > 0 ? ', paid ' . money($paid) : '') . '. ' . book_where($t, $b)), $t['kind'] === 'consult' && !$paid ? 1 : 0, now(), now()]);
        $consultId = (int)$pdo->lastInsertId();
        $invoiceId = null;
        if ($paid > 0) {
            q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, status, tax_rate, notes, paid_at, paid_method, share_token, stripe_invoice, created_at, updated_at) VALUES (?,?,?,?,?,?, 'paid', 0, ?, ?, 'Stripe', ?, ?, ?, ?)",
                [next_invoice_number(), $brand, $contact['company_id'], $contact['id'], today(), today(), 'Paid online when booked', now(), bin2hex(random_bytes(16)), (string)($s['invoice'] ?? ''), now(), now()]);
            $invoiceId = (int)$pdo->lastInsertId();
            q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,1,?,0)', [$invoiceId, $t['name'] . ' · ' . $local->format('M j, Y g:i a'), $paid]);
            q('UPDATE consultations SET invoice_id = ? WHERE id = ?', [$invoiceId, $consultId]);
        }
        // A kickoff booking takes care of the "schedule kickoff" to-do.
        if ($t['kind'] === 'kickoff') q("UPDATE tasks SET done_at = ? WHERE done_at IS NULL AND title LIKE 'Schedule kickoff call with%' AND entity_type = 'deal' AND entity_id IN (SELECT id FROM deals WHERE contact_id = ?)", [now(), $contact['id']]);
        q('UPDATE bookings SET contact_id = ?, company_id = ?, deal_id = ?, consultation_id = ?, invoice_id = ?, paid_total = ?, stripe_payment = ?, updated_at = ? WHERE id = ?',
            [$contact['id'], $contact['company_id'], $dealId, $consultId, $invoiceId, $paid, (string)($s['payment_intent'] ?? ''), now(), $b['id']]);
        $note = 'Booked ' . $t['name'] . ' for ' . $local->format('D, M j, g:i a') . ($paid > 0 ? ' (paid ' . money($paid) . ')' : '') . '.' . ($b['answer'] !== '' ? "\n\n" . $b['answer'] : '');
        log_activity('contact', (int)$contact['id'], 'meeting', $note);
        if ($dealId) log_activity('deal', $dealId, 'meeting', $note);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    book_mail(one('SELECT * FROM bookings WHERE id = ?', [$b['id']]), 'confirmed');
}

function book_cancel(array $b, string $reason, string $by): void {
    if ($b['status'] !== 'confirmed' && $b['status'] !== 'pending') return;
    q("UPDATE bookings SET status = 'cancelled', cancelled_at = ?, cancel_reason = ?, updated_at = ? WHERE id = ?", [now(), mb_substr($reason, 0, 500), now(), $b['id']]);
    if ($b['consultation_id']) q("UPDATE consultations SET status = 'cancelled', updated_at = ? WHERE id = ?", [now(), $b['consultation_id']]);
    $b = one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
    if ($b['contact_id']) log_activity('contact', (int)$b['contact_id'], 'meeting', $b['type_name'] . ' on ' . book_local((int)$b['starts_at'], 'M j, g:i a') . ' cancelled by ' . $by . ($reason !== '' ? ': ' . $reason : '.'));
    if ($b['paid_total'] > 0 && $by !== 'you') q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Refund ' . money($b['paid_total']) . ' to ' . $b['name'] . ' in Stripe (cancelled ' . $b['type_name'] . ')', today(), 'contact', $b['contact_id'], now()]);
    if ($b['status'] === 'cancelled') book_mail($b, $by === 'you' ? 'cancelled_by_us' : 'cancelled');
}
function book_reschedule(array $b, int $ts): array {
    $t = one('SELECT * FROM meeting_types WHERE id = ?', [$b['type_id']]);
    if (!$t || $b['status'] !== 'confirmed') return ['ok' => false, 'error' => 'This booking can’t be moved. Book a new time instead.'];
    $pdo = db(); $pdo->exec('BEGIN IMMEDIATE');
    try {
        if (!book_slot_open($t, $ts, (int)$b['id'])) { $pdo->exec('ROLLBACK'); return ['ok' => false, 'error' => 'Sorry, that time was just taken. Please pick another.']; }
        $old = (int)$b['starts_at'];
        $local = (new DateTime('@' . $ts))->setTimezone(book_tz());
        q('UPDATE bookings SET starts_at = ?, reminded_at = NULL, updated_at = ? WHERE id = ?', [$ts, now(), $b['id']]);
        if ($b['consultation_id']) q('UPDATE consultations SET date = ?, start_time = ?, updated_at = ? WHERE id = ?', [$local->format('Y-m-d'), $local->format('H:i'), now(), $b['consultation_id']]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
    $b = one('SELECT * FROM bookings WHERE id = ?', [$b['id']]);
    if ($b['contact_id']) log_activity('contact', (int)$b['contact_id'], 'meeting', $b['type_name'] . ' moved from ' . book_local($old, 'M j, g:i a') . ' to ' . book_local($ts, 'M j, g:i a') . '.');
    book_mail($b, 'moved');
    return ['ok' => true];
}

/* ---------- Calendar file and emails ---------- */
function book_ics(array $b): string {
    $t = one('SELECT * FROM meeting_types WHERE id = ?', [$b['type_id']]) ?: ['brand' => '', 'location' => 'video', 'location_detail' => ''];
    $from = $t['brand'] ?: setting('business_name');
    $ev = implode("\r\n", ['BEGIN:VEVENT', 'UID:booking-' . $b['token'] . '@' . parse_url((string)config('app_url'), PHP_URL_HOST), 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . gmdate('Ymd\THis\Z', (int)$b['starts_at']), 'DTEND:' . gmdate('Ymd\THis\Z', (int)$b['starts_at'] + (int)$b['minutes'] * 60),
        'SUMMARY:' . ics_escape_b($b['type_name'] . ' with ' . $from), 'DESCRIPTION:' . ics_escape_b(book_where($t, $b) . "\n\nChange or cancel: " . book_manage_link($b)),
        'STATUS:' . ($b['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT']);
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Syndicate Holdings Group//Booking//EN\r\nMETHOD:PUBLISH\r\n$ev\r\nEND:VCALENDAR\r\n";
}
function ics_escape_b(string $s): string { return str_replace(["\r\n", "\n"], '\n', addcslashes($s, ',;\\')); }
function book_mail(array $b, string $what): void {
    $t = one('SELECT * FROM meeting_types WHERE id = ?', [$b['type_id']]) ?: ['name' => $b['type_name'], 'brand' => '', 'location' => 'video', 'location_detail' => '', 'slug' => ''];
    $from = $t['brand'] ?: setting('business_name');
    $when = book_local((int)$b['starts_at']) . ' ' . book_tz_label();
    $first = explode(' ', $b['name'])[0] ?: 'there';
    $guestTime = '';
    if ($b['guest_tz'] !== '' && $b['guest_tz'] !== (string)config('timezone')) { try { $guestTime = ' (' . (new DateTime('@' . $b['starts_at']))->setTimezone(new DateTimeZone($b['guest_tz']))->format('g:i a T') . ' your time)'; } catch (Throwable $e) {} }
    $details = "What: {$b['type_name']} ({$b['minutes']} minutes)\nWhen: $when$guestTime\nWhere: " . book_where($t, $b) . "\n\nAdd it to your calendar: " . book_manage_link($b) . "&ics=1\nNeed to change or cancel? " . book_manage_link($b);
    $subjects = ['confirmed' => 'Confirmed: ' . $b['type_name'] . ', ' . book_local((int)$b['starts_at'], 'D M j, g:i a'), 'moved' => 'Moved: ' . $b['type_name'] . ', now ' . book_local((int)$b['starts_at'], 'D M j, g:i a'),
        'reminder' => 'Tomorrow: ' . $b['type_name'] . ' at ' . book_local((int)$b['starts_at'], 'g:i a'), 'cancelled' => 'Cancelled: ' . $b['type_name'], 'cancelled_by_us' => 'Cancelled: ' . $b['type_name']];
    $intro = ['confirmed' => "You’re booked. Here are the details:", 'moved' => 'Your booking has moved. Here are the new details:', 'reminder' => 'A reminder that we’re meeting tomorrow:',
        'cancelled' => 'Your booking is cancelled.' . ($b['paid_total'] > 0 ? ' Your payment of ' . money($b['paid_total']) . ' will be refunded to your card within 5–10 business days.' : '') . ' You can book a new time any time: ' . book_link($t['slug'] ?? ''),
        'cancelled_by_us' => 'We’re sorry, we need to cancel this booking. ' . ($b['cancel_reason'] !== '' ? $b['cancel_reason'] . ' ' : '') . ($b['paid_total'] > 0 ? 'Your payment of ' . money($b['paid_total']) . ' will be refunded. ' : '') . 'Please pick another time here: ' . book_link($t['slug'] ?? '')][$what];
    send_mail($b['email'], $subjects[$what], "Hi $first,\n\n$intro\n\n" . (strpos($what, 'cancelled') === 0 ? "What: {$b['type_name']}\nWhen: $when" : $details) . "\n\n$from\n" . setting('business_email'), setting('business_email'), $from);
    if ($what !== 'reminder' && $what !== 'cancelled_by_us') {
        $to = setting('shop_notify') ?: setting('business_email');
        if ($to !== '') send_mail($to, '[' . ucfirst($what === 'confirmed' ? 'New booking' : ($what === 'moved' ? 'Booking moved' : 'Booking cancelled')) . '] ' . $b['type_name'] . ' · ' . $b['name'] . ' · ' . book_local((int)$b['starts_at'], 'D M j, g:i a'),
            "$b[name]" . ($b['business'] ? " ($b[business])" : '') . "\n$b[email]" . ($b['phone'] ? "\n$b[phone]" : '') . "\n\n$when\n" . ($b['answer'] !== '' ? "\n“$b[answer]”\n" : '') . ($b['paid_total'] > 0 ? "\nPaid: " . money($b['paid_total']) . "\n" : '') . ($b['cancel_reason'] !== '' && $what === 'cancelled' ? "\nReason: $b[cancel_reason]\n" : '') . "\nIn the CRM: " . abs_url('booking.php?id=' . $b['id']) . "\n", $b['email'], $from);
    }
}

/* ---------- Upkeep (cron) ---------- */
function bookings_cleanup(): string {
    $n = 0;
    foreach (all("SELECT * FROM bookings WHERE status = 'confirmed' AND reminded_at IS NULL AND starts_at BETWEEN ? AND ?", [time() + 18 * 3600, time() + 26 * 3600]) as $b) {
        book_mail($b, 'reminder'); q('UPDATE bookings SET reminded_at = ? WHERE id = ?', [now(), $b['id']]); $n++;
    }
    foreach (all("SELECT * FROM bookings WHERE status = 'pending' AND created_at < ?", [date('Y-m-d H:i:s', time() - BOOK_HOLD_MINUTES * 60)]) as $b) {
        try { $b = shop_ready() && $b['stripe_session'] !== '' ? book_sync_session($b) : $b; } catch (Throwable $e) { error_log('Booking sync ' . $b['id'] . ': ' . $e->getMessage()); }
        if ($b['status'] === 'pending') q("UPDATE bookings SET status = 'expired', updated_at = ? WHERE id = ? AND status = 'pending'", [now(), $b['id']]);
    }
    return $n ? "$n booking reminder(s) sent" : '';
}
