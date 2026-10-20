<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/booking.php';
$user = require_user();
$error = '';
if (is_post()) {
    $a = post('action');
    if ($a === 'hours') {
        $hours = [];
        foreach (BOOK_DAYS as $n => $l) {
            if (($_POST['on'][$n] ?? '') !== '1') continue;
            $from = (string)($_POST['from'][$n] ?? ''); $to = (string)($_POST['to'][$n] ?? '');
            if (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to) || $to <= $from) { $error = 'Check the hours for ' . $l . ': the end must be after the start.'; break; }
            $hours[(string)$n] = [$from, $to];
        }
        if (!$error) {
            setting('booking_hours', json_encode($hours));
            setting('booking_notice_hours', (string)max(0, min(336, (int)post('notice'))));
            setting('booking_window_days', (string)max(1, min(120, (int)post('window'))));
            setting('booking_buffer', (string)max(0, min(120, (int)post('buffer'))));
            setting('booking_step', (string)(in_array((int)post('step'), [15, 20, 30, 60], true) ? (int)post('step') : 30));
            $url = post('meeting_url'); if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
            setting('booking_meeting_url', filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 300) : '');
            flash('Availability saved.'); redirect('booking-setup.php');
        }
    }
    if ($a === 'block') {
        $day = post('day'); $from = post('from'); $to = post('to');
        if (!valid_date($day)) $error = 'Choose the date you’re away.';
        elseif (($from !== '' || $to !== '') && (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to) || $to <= $from)) $error = 'For part of a day, enter both times, with the end after the start. Leave both empty for the whole day.';
        else {
            $until = valid_date(post('until')) && post('until') > $day ? post('until') : $day;
            for ($d = $day; $d <= $until && $d <= date('Y-m-d', strtotime($day . ' +60 days')); $d = date('Y-m-d', strtotime($d . ' +1 day'))) q('INSERT INTO booking_blocks (day, start_time, end_time, reason, created_at) VALUES (?,?,?,?,?)', [$d, $from, $to, mb_substr(post('reason'), 0, 120), now()]);
            flash('Time off added. Those times can’t be booked.'); redirect('booking-setup.php#off');
        }
    }
    if ($a === 'unblock') { q('DELETE FROM booking_blocks WHERE id = ?', [(int)post('id')]); flash('Removed.'); redirect('booking-setup.php#off'); }
}
$hours = book_hours();
$blocks = all('SELECT * FROM booking_blocks WHERE day >= ? ORDER BY day, start_time', [today()]);
$types = all('SELECT * FROM meeting_types ORDER BY position, id');
layout_top('Hours & meeting types', 'bookings', $user);
?>
<div class="page-head"><div><a class="muted small" href="bookings.php">← Bookings</a><h1>Hours &amp; meeting types</h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<div class="grid-detail">
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="hours">
    <div class="card-h"><h2>When people can book</h2><span class="muted small"><?= e(str_replace('_', ' ', (string)config('timezone'))) ?></span></div>
    <div class="hours-grid">
      <?php foreach (BOOK_DAYS as $n => $l): $h = $hours[(string)$n] ?? null; ?>
        <label class="chk"><input type="checkbox" name="on[<?= $n ?>]" value="1"<?= $h ? ' checked' : '' ?>> <span><?= e($l) ?></span></label>
        <input type="time" name="from[<?= $n ?>]" value="<?= e($h[0] ?? '09:00') ?>" aria-label="<?= e($l) ?> start" step="900">
        <input type="time" name="to[<?= $n ?>]" value="<?= e($h[1] ?? '17:00') ?>" aria-label="<?= e($l) ?> end" step="900">
      <?php endforeach; ?>
    </div>
    <div class="form-grid" style="margin-top:18px">
      <label class="f"><span>Minimum notice (hours)</span><input type="number" name="notice" min="0" max="336" value="<?= e(setting('booking_notice_hours')) ?>"></label>
      <label class="f"><span>Book up to (days ahead)</span><input type="number" name="window" min="1" max="120" value="<?= e(setting('booking_window_days')) ?>"></label>
      <label class="f"><span>Break between meetings (minutes)</span><input type="number" name="buffer" min="0" max="120" value="<?= e(setting('booking_buffer')) ?>"></label>
      <label class="f"><span>Start times every</span><select name="step"><?php foreach ([15, 20, 30, 60] as $m): ?><option value="<?= $m ?>"<?= selected($m, setting('booking_step')) ?>><?= $m ?> minutes</option><?php endforeach; ?></select></label>
      <label class="f span2"><span>Video call link <small class="muted">(your Zoom or Google Meet room; sent with video bookings. Leave empty to send links yourself)</small></span><input name="meeting_url" type="text" inputmode="url" value="<?= e(setting('booking_meeting_url')) ?>" placeholder="https://meet.google.com/abc-defg-hij"></label>
    </div>
    <p class="muted small">Anything already on your calendar as a scheduled consultation blocks those times automatically.</p>
    <div class="save-bar"><span></span><button class="btn primary sm" type="submit">Save hours</button></div>
  </form>
  <div class="stack">
    <section class="card" id="off"><div class="card-h"><h2>Time off</h2></div>
      <?php if ($blocks): ?><ul class="list"><?php foreach ($blocks as $x): ?><li class="li-row"><span class="grow"><b><?= e(fmt_date($x['day'], 'D, M j')) ?></b><small><?= $x['start_time'] ? e(date('g:i a', strtotime($x['start_time'])) . ' – ' . date('g:i a', strtotime($x['end_time']))) : 'All day' ?><?= $x['reason'] ? ' · ' . e($x['reason']) : '' ?></small></span>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="unblock"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="x" type="submit" aria-label="Remove">×</button></form></li><?php endforeach; ?></ul><?php else: ?><p class="empty">No time off coming up.</p><?php endif; ?>
      <form method="post" class="form-grid" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="block">
        <label class="f"><span>From</span><input type="date" name="day" min="<?= today() ?>" required></label>
        <label class="f"><span>Until <small class="muted">(optional)</small></span><input type="date" name="until" min="<?= today() ?>"></label>
        <label class="f"><span>Start <small class="muted">(part of a day)</small></span><input type="time" name="from"></label>
        <label class="f"><span>End</span><input type="time" name="to"></label>
        <label class="f span2"><span>Note <small class="muted">(only you see it)</small></span><input name="reason" placeholder="Vacation"></label>
        <div class="span2 row-end"><button class="btn ghost sm" type="submit">Add time off</button></div>
      </form>
    </section>
  </div>
</div>
<section class="card flush" style="margin-top:16px">
  <div class="card-h" style="padding:18px 20px 0"><h2>Meeting types</h2><a class="btn primary xs" href="booking-type.php">+ New meeting type</a></div>
  <div class="table-wrap"><table class="tbl">
    <thead><tr><th>Meeting</th><th>Length</th><th class="r">Price</th><th>Shown</th><th>Link</th></tr></thead>
    <tbody><?php foreach ($types as $t): ?><tr data-href="booking-type.php?id=<?= (int)$t['id'] ?>"><td><a class="cell-main" href="booking-type.php?id=<?= (int)$t['id'] ?>"><b><?= e($t['name']) ?></b><small><?= e($t['brand']) ?> · <?= e(BOOK_KINDS[$t['kind']] ?? '') ?></small></a></td>
      <td class="num"><?= (int)$t['minutes'] ?> min</td><td class="r num"><?= e(book_price_label($t)) ?></td>
      <td><?= !$t['active'] ? pill('bk', 'expired', 'Off') : ($t['public'] ? pill('bk', 'confirmed', 'On booking page') : pill('bk', 'pending', 'Link only')) ?></td>
      <td class="small muted">book.php?type=<?= e($t['slug']) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php layout_bottom(); ?>
