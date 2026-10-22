<?php
// Public booking pages. book.php lists meeting types, book.php?type=slug books one, book.php?b=TOKEN lets a guest
// add it to their calendar, reschedule or cancel. book.php?type=slug&slots=1 returns open times as JSON.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/booking.php';
header('Cache-Control: private, no-store');
$json = strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$reply = function (int $code, array $d) { http_response_code($code); header('Content-Type: application/json'); exit(json_encode($d)); };
$b = null; $t = null;
if (get('b') !== '') {
    $b = preg_match('/^[a-f0-9]{32}$/', get('b')) ? one('SELECT * FROM bookings WHERE token = ?', [get('b')]) : null;
    if (!$b) { http_response_code(404); }
    else $t = one('SELECT * FROM meeting_types WHERE id = ?', [$b['type_id']]);
} elseif (get('type') !== '') {
    $t = book_type(get('type'));
    if (!$t) http_response_code(404);
}
// Open times as JSON for the calendar
if ($t && get('slots') === '1') {
    header('Content-Type: application/json');
    exit(json_encode(['ok' => true, 'slots' => book_slots($t, $b ? (int)$b['id'] : null), 'minutes' => (int)$t['minutes'], 'tz' => (string)config('timezone')]));
}
if ($b && get('ics') === '1') {
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9]+/', '-', strtolower($b['type_name'])) . '.ics"');
    exit(book_ics($b));
}
if (is_post()) {
    if (too_many('booking', 15, 15)) $reply(429, ['ok' => false, 'error' => 'Too many tries. Please wait a few minutes.']);
    record_attempt('booking');
    $a = post('action');
    if ($a === 'book' && $t && !$b) {
        if (post('website_url') !== '') $reply(200, ['ok' => true, 'url' => book_link()]);
        if (!captcha_ok()) { if ($json) $reply(422, ['ok' => false, 'error' => CAPTCHA_FAIL]); flash(CAPTCHA_FAIL); redirect('book.php?type=' . rawurlencode($t['slug'])); }
        $r = book_create($t, $_POST);
        if ($r['ok'] && $r['url'] === '') $r['url'] = abs_url('book.php?b=' . $r['token'] . '&booked=1');
        if ($json) $reply($r['ok'] ? 200 : 422, $r);
        if ($r['ok']) { header('Location: ' . $r['url'], true, 303); exit; }
        flash($r['error']); redirect('book.php?type=' . rawurlencode($t['slug']));
    }
    if ($a === 'move' && $b) { $r = book_reschedule($b, (int)post('start')); if ($json) $reply($r['ok'] ? 200 : 422, $r + ['url' => abs_url('book.php?b=' . $b['token'] . '&moved=1')]); redirect('book.php?b=' . $b['token'] . ($r['ok'] ? '&moved=1' : '')); }
    if ($a === 'cancel' && $b) { book_cancel($b, post('reason'), 'the guest'); redirect('book.php?b=' . $b['token'] . '&cancelled=1'); }
}
if ($b && get('paid') === '1' && get('session_id') !== '' && hash_equals($b['stripe_session'], get('session_id'))) {
    try { $b = book_sync_session($b); } catch (Throwable $e) { error_log('Booking return ' . $b['id'] . ': ' . $e->getMessage()); }
}
$brand = $t && $t['brand'] ? $t['brand'] : setting('business_name');
$types = (!$t && !$b && http_response_code() !== 404) ? book_types(true) : [];
$tzLabel = str_replace('_', ' ', (string)config('timezone'));
$msg = flash();
$mark = '<svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0b0d10"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.8" stroke-linecap="round"/></svg>';
$title = $b ? $b['type_name'] . ' · ' . $brand : ($t ? 'Book a ' . strtolower($t['name']) . ' · ' . $brand : ($types ? 'Book a time · ' . setting('business_name') : 'Not found'));
$upcoming = $b && $b['starts_at'] > time();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public">
<main class="public-wrap book-wrap">
<?php if (http_response_code() === 404): ?>
  <div class="card" style="max-width:520px;margin:12vh auto 0;text-align:center"><h1 style="font-size:24px">Not found</h1><p class="muted">This booking link may be mistyped or no longer active. <a href="<?= e(book_link()) ?>">See available meetings</a> or email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p></div>

<?php elseif ($types): ?>
  <section class="book-card book-list">
    <div class="book-brand"><?= $mark ?><b><?= e(setting('business_name')) ?></b></div>
    <h1>Book a time</h1>
    <p class="book-muted">Pick the kind of meeting you’d like. You’ll choose a time on the next page.</p>
    <div class="book-types"><?php foreach ($types as $x): ?>
      <a class="book-type" href="book.php?type=<?= e($x['slug']) ?>"><span class="bt-top"><b><?= e($x['name']) ?></b><span class="bt-price"><?= e(book_price_label($x)) ?></span></span><span class="bt-meta"><?= (int)$x['minutes'] ?> min · <?= e(BOOK_LOCATIONS[$x['location']] ?? '') ?> · <?= e($x['brand']) ?></span><span class="bt-desc"><?= e($x['description']) ?></span></a>
    <?php endforeach; ?></div>
  </section>

<?php elseif ($b): ?>
  <section class="book-card book-done">
    <div class="book-brand"><?= $mark ?><b><?= e($brand) ?></b></div>
    <?php if ($b['status'] === 'confirmed'): ?>
      <span class="book-ico ok">✓</span>
      <h1><?= get('moved') === '1' ? 'Your booking has moved.' : ($upcoming ? 'You’re booked.' : 'This meeting has passed.') ?></h1>
      <?php if ($upcoming): ?><p class="book-muted">A confirmation is on its way to <b><?= e($b['email']) ?></b>. We’ll send a reminder the day before.</p><?php endif; ?>
    <?php elseif ($b['status'] === 'pending'): ?>
      <span class="book-ico">…</span><h1>Waiting for your payment.</h1><p class="book-muted">If you’ve just paid, refresh this page in a few seconds. Your time is held for 30 minutes.</p>
    <?php elseif ($b['status'] === 'cancelled'): ?>
      <span class="book-ico no">×</span><h1>This booking is cancelled.</h1><p class="book-muted"><?= $b['paid_total'] > 0 ? 'Your payment of ' . money($b['paid_total']) . ' will be refunded to your card within 5–10 business days. ' : '' ?><?php if ($t): ?><a href="book.php?type=<?= e($t['slug']) ?>">Book a new time</a><?php endif; ?></p>
    <?php else: ?>
      <span class="book-ico no">×</span><h1>This booking wasn’t completed.</h1><p class="book-muted">The payment wasn’t finished, so the time was released. <?php if ($t): ?><a href="book.php?type=<?= e($t['slug']) ?>">Pick a time again</a><?php endif; ?></p>
    <?php endif; ?>
    <dl class="book-facts">
      <div><dt>What</dt><dd><?= e($b['type_name']) ?> · <?= (int)$b['minutes'] ?> minutes</dd></div>
      <div><dt>When</dt><dd data-local-time="<?= (int)$b['starts_at'] ?>"><?= e(book_local((int)$b['starts_at'], 'l, F j, Y · g:i a')) ?> <?= e(book_tz_label()) ?></dd></div>
      <?php if ($t): ?><div><dt>Where</dt><dd><?= e(book_where($t, $b)) ?></dd></div><?php endif; ?>
      <?php if ($b['paid_total'] > 0): ?><div><dt>Paid</dt><dd><?= money($b['paid_total']) ?> · receipt sent by Stripe</dd></div><?php endif; ?>
    </dl>
    <?php if ($b['status'] === 'confirmed' && $upcoming): ?>
      <div class="book-actions"><a class="btn primary" href="book.php?b=<?= e($b['token']) ?>&amp;ics=1">Add to calendar</a><a class="btn ghost" href="book.php?b=<?= e($b['token']) ?>&amp;reschedule=1#pick">Reschedule</a></div>
      <?php if (get('reschedule') === '1' && $t): ?>
        <div class="book-app" id="pick" data-slots="book.php?b=<?= e($b['token']) ?>&amp;type=<?= e($t['slug']) ?>&amp;slots=1" data-post="book.php?b=<?= e($b['token']) ?>" data-mode="move" data-minutes="<?= (int)$t['minutes'] ?>">
          <h2 class="book-h">Pick a new time</h2>
          <div class="book-cal"></div><div class="book-times"></div>
          <form class="book-form" hidden><input type="hidden" name="action" value="move"><input type="hidden" name="start"><p class="book-chosen"></p><p class="notice bad book-err" hidden></p><button class="btn primary" type="submit">Move my booking</button></form>
        </div>
      <?php endif; ?>
      <details class="book-cancel"><summary>Cancel this booking</summary>
        <form method="post" class="stack-sm"><input type="hidden" name="action" value="cancel"><label class="f"><span>Reason <small>(optional)</small></span><textarea name="reason" rows="2"></textarea></label>
          <?php if ($b['paid_total'] > 0): ?><p class="book-muted small">Your payment will be refunded in full.</p><?php endif; ?>
          <button class="btn ghost sm" type="submit">Cancel booking</button></form>
      </details>
    <?php endif; ?>
  </section>

<?php else: ?>
  <?php if ($msg): ?><p class="notice bad"><?= e($msg) ?></p><?php endif; ?>
  <?php if (get('cancelled') !== ''): ?><p class="notice">Payment cancelled, so nothing was booked. Pick a time whenever you’re ready.</p><?php endif; ?>
  <section class="book-card book-main">
    <aside class="book-info">
      <div class="book-brand"><?= $mark ?><b><?= e($brand) ?></b></div>
      <h1><?= e($t['name']) ?></h1>
      <ul class="book-meta">
        <li><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l3 2"/></svg><?= (int)$t['minutes'] ?> minutes</li>
        <li><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="5" width="11" height="10" rx="2"/><path d="m13.5 9 4-2.5v7l-4-2.5"/></svg><?= e(BOOK_LOCATIONS[$t['location']] ?? '') ?><?= $t['location'] === 'in_person' && $t['location_detail'] !== '' ? ' · ' . e($t['location_detail']) : '' ?></li>
        <li><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 2.5v15M13.5 5.5H8.25a2.25 2.25 0 0 0 0 4.5h3.5a2.25 2.25 0 0 1 0 4.5H6"/></svg><?= e(book_price_label($t)) ?><?= (float)$t['price'] > 0 ? ' · paid when you book' : '' ?></li>
      </ul>
      <p class="book-desc"><?= nl2br(e($t['description'])) ?></p>
    </aside>
    <div class="book-app" data-slots="book.php?type=<?= e($t['slug']) ?>&amp;slots=1" data-post="book.php?type=<?= e($t['slug']) ?>" data-mode="book" data-minutes="<?= (int)$t['minutes'] ?>" data-paid="<?= (float)$t['price'] > 0 ? '1' : '' ?>">
      <h2 class="book-h">Pick a day and time</h2>
      <noscript><p class="notice">Booking needs JavaScript. Email <?= e(setting('business_email')) ?> and we’ll find a time.</p></noscript>
      <div class="book-cal"><p class="book-muted">Loading open times…</p></div>
      <div class="book-times"></div>
      <form class="book-form" hidden novalidate>
        <input type="hidden" name="action" value="book"><input type="hidden" name="start"><input type="hidden" name="tz"><input type="hidden" name="source" value="<?= e(mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 120)) ?>"><input type="hidden" name="ref" value="<?= e(preg_match('/^[a-z0-9][a-z0-9-]{2,39}$/i', get('ref')) ? strtolower(get('ref')) : '') ?>">
        <p class="book-chosen"></p>
        <div class="book-fields">
          <label class="f"><span>Your name</span><input name="name" required autocomplete="name"></label>
          <label class="f"><span>Email</span><input name="email" type="email" required autocomplete="email"></label>
          <label class="f"><span>Phone<?= $t['location'] === 'phone' ? '' : ' <small>(optional)</small>' ?></span><input name="phone" type="tel" autocomplete="tel"<?= $t['location'] === 'phone' ? ' required' : '' ?>></label>
          <label class="f"><span>Business <small>(optional)</small></span><input name="business" autocomplete="organization"></label>
          <?php if ($t['question'] !== ''): ?><label class="f full"><span><?= e($t['question']) ?></span><textarea name="answer" rows="3"></textarea></label><?php endif; ?>
          <div class="hp" aria-hidden="true"><label>Leave empty <input name="website_url" tabindex="-1" autocomplete="off"></label></div>
        </div>
        <?= captcha_widget('booking', 'light') ?>
        <p class="notice bad book-err" hidden></p>
        <button class="btn primary book-submit" type="submit"><?= (float)$t['price'] > 0 ? 'Continue to payment · ' . e(book_price_label($t)) : 'Confirm booking' ?> <?= ARROW ?></button>
        <?php if ((float)$t['price'] > 0): ?><small class="book-muted">Card, Apple Pay or Google Pay through Stripe. Your time is held while you pay.</small><?php endif; ?>
      </form>
    </div>
  </section>
<?php endif; ?>
<p class="muted small" style="text-align:center">Questions? Email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a></p>
</main>
<script src="assets/js/crm.js" defer></script>
<?= captcha_script(true) ?>
</body>
</html>
