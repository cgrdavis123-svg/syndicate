<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/booking.php';
$user = require_user();
$f = get('show', 'upcoming');
$filters = ['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled & unpaid', 'all' => 'All'];
if (!isset($filters[$f])) $f = 'upcoming';
$now = time();
$where = ['upcoming' => "b.status IN ('confirmed','pending') AND b.starts_at >= $now", 'past' => "b.status = 'confirmed' AND b.starts_at < $now", 'cancelled' => "b.status IN ('cancelled','expired')", 'all' => '1=1'][$f];
$rows = all("SELECT b.*, s.status cstatus FROM bookings b LEFT JOIN consultations s ON s.id = b.consultation_id WHERE $where ORDER BY b.starts_at " . ($f === 'upcoming' ? 'ASC' : 'DESC') . ' LIMIT 300');
$week = (int)val("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND starts_at BETWEEN ? AND ?", [$now, $now + 7 * 86400]);
$monthStart = (new DateTime('first day of this month 00:00', book_tz()))->getTimestamp();
$month = (int)val("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND created_at >= ?", [date('Y-m-01')]);
$paid = (float)val("SELECT COALESCE(SUM(paid_total), 0) FROM bookings WHERE status = 'confirmed' AND created_at >= ?", [date('Y-m-01')]);
q("UPDATE bookings SET seen_at = ? WHERE seen_at IS NULL AND status = 'confirmed'", [now()]);
$types = book_types();
layout_top('Bookings', 'bookings', $user);
?>
<div class="page-head">
  <div><p class="eyebrow">Times are <?= e(str_replace('_', ' ', (string)config('timezone'))) ?></p><h1>Bookings</h1></div>
  <div class="row"><a class="btn ghost sm" href="booking-setup.php">Hours &amp; meeting types</a><a class="btn primary sm" href="<?= e(book_link()) ?>" target="_blank" rel="noopener">Booking page ↗</a></div>
</div>
<div class="mini-kpis">
  <div><span>Next 7 days</span><b class="num"><?= $week ?></b><small>confirmed</small></div>
  <div><span>Booked in <?= date('F') ?></span><b class="num"><?= $month ?></b></div>
  <div><span>Paid bookings in <?= date('F') ?></span><b class="num"><?= money($paid, false) ?></b></div>
</div>
<section class="card">
  <div class="card-h"><h2>Your booking links</h2><span class="muted small">Share these anywhere: email signature, website, social bios</span></div>
  <ul class="list"><?php foreach ($types as $t): $link = book_link($t['slug']); ?>
    <li><div class="li-row"><span class="grow"><b><?= e($t['name']) ?></b><small><?= (int)$t['minutes'] ?> min · <?= e(book_price_label($t)) ?> · <?= $t['public'] ? 'Listed on the booking page' : 'Private link only' ?></small></span>
      <input class="link-in" readonly value="<?= e($link) ?>" id="bl<?= (int)$t['id'] ?>" aria-label="Link for <?= e($t['name']) ?>"><button class="btn ghost xs" type="button" data-copy="#bl<?= (int)$t['id'] ?>">Copy</button></div></li>
  <?php endforeach; ?></ul>
</section>
<div class="seg-links" style="margin-bottom:4px"><?php foreach ($filters as $k => $l): ?><a href="bookings.php?show=<?= $k ?>"<?= $f === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></div>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= $f === 'upcoming' ? 'Nothing booked yet. Share a booking link and new bookings appear here and on your calendar.' : 'Nothing here.' ?></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>When</th><th>Who</th><th>Meeting</th><th>Status</th><th class="r">Paid</th></tr></thead>
  <tbody><?php $lastDay = ''; foreach ($rows as $r): $day = book_local((int)$r['starts_at'], 'l, M j'); ?>
    <tr data-href="booking.php?id=<?= (int)$r['id'] ?>"><td><a class="cell-main" href="booking.php?id=<?= (int)$r['id'] ?>"><b class="num"><?= e(book_local((int)$r['starts_at'], 'g:i a')) ?></b><small><?= e($day) ?></small></a></td>
      <td><span class="cell-main"><b><?= e($r['name']) ?></b><small><?= e($r['business'] ?: $r['email']) ?></small></span></td>
      <td><?= e($r['type_name']) ?> <small class="muted">· <?= (int)$r['minutes'] ?> min</small></td>
      <td><?= pill('bk', $r['status'], BOOKING_STATUSES[$r['status']] ?? $r['status']) ?><?= $r['cstatus'] === 'no_show' ? ' <small class="warn">· no-show</small>' : ($r['cstatus'] === 'completed' ? ' <small class="muted">· done</small>' : '') ?></td>
      <td class="r num"><?= $r['paid_total'] > 0 ? money($r['paid_total']) : '<span class="muted">—</span>' ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
