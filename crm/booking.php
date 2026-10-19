<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/booking.php';
$user = require_user();
$id = (int)get('id');
$b = one('SELECT * FROM bookings WHERE id = ?', [$id]);
if (!$b) { flash('That booking no longer exists.'); redirect('bookings.php'); }
$back = 'booking.php?id=' . $id;
if (is_post()) {
    $a = post('action');
    if ($a === 'cancel') { book_cancel($b, post('reason'), 'you'); flash('Cancelled. ' . $b['name'] . ' has been emailed.' . ($b['paid_total'] > 0 ? ' Refund the payment in Stripe.' : '')); redirect($back); }
    if (in_array($a, ['completed', 'no_show', 'scheduled'], true) && $b['consultation_id']) { q('UPDATE consultations SET status = ?, updated_at = ? WHERE id = ?', [$a, now(), $b['consultation_id']]); flash('Updated.'); redirect($back); }
    if ($a === 'check' && $b['stripe_session']) { try { $n = book_sync_session($b); flash($n['status'] === 'confirmed' ? 'Payment found. The booking is confirmed.' : 'No payment yet.'); } catch (Throwable $e) { flash('Couldn’t check with Stripe: ' . $e->getMessage()); } redirect($back); }
}
$t = one('SELECT * FROM meeting_types WHERE id = ?', [$b['type_id']]);
$c = $b['consultation_id'] ? one('SELECT * FROM consultations WHERE id = ?', [$b['consultation_id']]) : null;
$upcoming = $b['starts_at'] > time();
layout_top($b['type_name'] . ' · ' . $b['name'], 'bookings', $user);
?>
<div class="page-head">
  <div><a class="muted small" href="bookings.php">← Bookings</a><h1><?= e($b['name']) ?></h1>
    <p class="row muted"><?= pill('bk', $b['status'], BOOKING_STATUSES[$b['status']] ?? $b['status']) ?> <?= e($b['type_name']) ?> · <b class="num"><?= e(book_local((int)$b['starts_at'], 'D, M j, Y · g:i a')) ?></b> <?= e(book_tz_label()) ?> · <?= (int)$b['minutes'] ?> min</p></div>
  <div class="row"><?php if ($c): ?><a class="btn ghost sm" href="calendar.php?id=<?= (int)$c['id'] ?>">Add to my calendar</a><?php endif; ?><a class="btn ghost sm" href="<?= e(book_manage_link($b)) ?>" target="_blank" rel="noopener">Guest’s page ↗</a></div>
</div>
<div class="grid-detail">
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Details</h2></div>
      <dl class="facts">
        <div><dt>Guest</dt><dd><?= $b['contact_id'] ? '<a href="contact.php?id=' . (int)$b['contact_id'] . '">' . e($b['name']) . '</a>' : e($b['name']) ?><?= $b['business'] ? ' · ' . e($b['business']) : '' ?></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?= e($b['email']) ?>"><?= e($b['email']) ?></a></dd></div>
        <?php if ($b['phone']): ?><div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $b['phone'])) ?>"><?= e($b['phone']) ?></a></dd></div><?php endif; ?>
        <?php if ($t): ?><div><dt>Where</dt><dd><?= e(book_where($t, $b)) ?></dd></div><?php endif; ?>
        <?php if ($b['guest_tz'] && $b['guest_tz'] !== config('timezone')): ?><div><dt>Their time</dt><dd><?php try { echo e((new DateTime('@' . $b['starts_at']))->setTimezone(new DateTimeZone($b['guest_tz']))->format('D, M j · g:i a T')) . ' (' . e(str_replace('_', ' ', $b['guest_tz'])) . ')'; } catch (Throwable $e) { echo e($b['guest_tz']); } ?></dd></div><?php endif; ?>
        <?php if ($b['paid_total'] > 0): ?><div><dt>Paid</dt><dd><?= money($b['paid_total']) ?><?= $b['invoice_id'] ? ' · <a href="invoice.php?id=' . (int)$b['invoice_id'] . '">invoice</a>' : '' ?><?= $b['stripe_payment'] ? ' · <a href="' . e(stripe_dashboard('payments', $b['stripe_payment'], (bool)$b['live'])) . '" target="_blank" rel="noopener">Stripe ↗</a>' : '' ?></dd></div><?php endif; ?>
        <div><dt>Booked</dt><dd><?= e(fmt_date($b['created_at'], 'M j, Y g:i a')) ?><?= $b['reminded_at'] ? ' · reminder sent' : '' ?></dd></div>
        <?php if ($b['status'] === 'cancelled'): ?><div><dt>Cancelled</dt><dd><?= e(fmt_date($b['cancelled_at'], 'M j, g:i a')) ?><?= $b['cancel_reason'] ? ' · “' . e($b['cancel_reason']) . '”' : '' ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($b['answer']): ?><div class="note-box"><?= nl2br(e($b['answer'])) ?></div><?php endif; ?>
    </section>
  </div>
  <div class="stack">
    <?php if ($b['status'] === 'pending'): ?><section class="card"><p class="muted small">Waiting for payment. The time is held for <?= BOOK_HOLD_MINUTES ?> minutes.</p><form method="post"><?= csrf_field() ?><button class="btn ghost sm" name="action" value="check">Check payment with Stripe</button></form></section><?php endif; ?>
    <?php if ($c && $b['status'] === 'confirmed'): ?>
    <section class="card"><div class="card-h"><h2>How it went</h2></div>
      <form method="post" class="seg" role="group"><?= csrf_field() ?><?php foreach (['scheduled' => 'Scheduled', 'completed' => 'Done', 'no_show' => 'No-show'] as $k => $l): ?><button class="btn <?= $c['status'] === $k ? 'primary' : 'ghost' ?> xs" name="action" value="<?= $k ?>"><?= e($l) ?></button><?php endforeach; ?></form>
      <p class="muted small"><a href="consultation-edit.php?id=<?= (int)$c['id'] ?>">Open the consultation</a> to add notes<?= $c['billable'] ? ' or bill the time' : '' ?>.</p>
    </section>
    <?php endif; ?>
    <?php if ($b['deal_id']): ?><section class="card"><ul class="list"><li><a href="deal.php?id=<?= (int)$b['deal_id'] ?>"><span class="grow"><b>Deal</b><small><?= e((string)val('SELECT title FROM deals WHERE id = ?', [$b['deal_id']])) ?></small></span>→</a></li></ul></section><?php endif; ?>
    <?php if (in_array($b['status'], ['confirmed', 'pending'], true) && $upcoming): ?>
    <section class="card"><div class="card-h"><h2>Cancel</h2></div>
      <form method="post" class="stack-sm" data-confirm="Cancel this booking and email <?= e($b['name']) ?>?"><?= csrf_field() ?><label class="f"><span>Message to the guest <small class="muted">(optional, included in the email)</small></span><textarea name="reason" rows="2" placeholder="Something came up. Could we find another time?"></textarea></label>
        <button class="link-danger" name="action" value="cancel">Cancel booking</button></form>
      <p class="muted small">They’ll get a link to pick a new time<?= $b['paid_total'] > 0 ? '. Refund the payment in Stripe.' : '.' ?></p>
    </section>
    <?php endif; ?>
  </div>
</div>
<?php layout_bottom(); ?>
