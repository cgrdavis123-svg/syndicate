<?php
// Plans & billing in the client portal: the client's monthly plans, and a two-step online cancel.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
require __DIR__ . '/app/shop.php';
$c = require_portal();
$plans = portal_plans($c);
$find = function (string $key) use ($plans) { foreach ($plans as $p) if ($p['table'] . ':' . $p['id'] === $key) return $p; return null; };
$confirm = null; $error = '';
if (is_post()) {
    $p = $find(post('plan'));
    if (!$p) { flash('That plan isn’t available.'); redirect('portal-plans.php'); }
    if ($p['cancel_at']) { flash('That plan is already cancelled.'); redirect('portal-plans.php'); }
    if (post('action') === 'review') {
        try { $confirm = $p + ['end' => plan_end_date($p, stripe('GET', 'subscriptions/' . rawurlencode($p['stripe_subscription'])))]; }
        catch (Throwable $e) { error_log('Plan cancel preview ' . $p['ref'] . ': ' . $e->getMessage()); $error = 'We couldn’t reach our payment system just now. Please try again in a minute, or email ' . setting('business_email') . ' and we’ll cancel it for you.'; }
    } elseif (post('action') === 'cancel') {
        try { $end = plan_cancel($p, $c); flash('Cancelled. Your plan ends ' . date('F j, Y', $end) . ', and we’ve emailed you a confirmation.'); redirect('portal-plans.php'); }
        catch (Throwable $e) { error_log('Plan cancel ' . $p['ref'] . ': ' . $e->getMessage()); $error = 'We couldn’t cancel it automatically. Please email ' . setting('business_email') . ' and we’ll cancel it for you today, effective from the time of your email.'; }
    }
}
portal_top('Plans & billing', $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal.php">← Overview</a><h1>Plans &amp; billing</h1><p class="muted">Your monthly plans. You can cancel any of them here.</p></div></div>
<?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($confirm): [$end, $periodEnd] = $confirm['end']; ?>
<section class="card attn">
  <h2>Cancel <?= e(implode(', ', array_column($confirm['items'], 'name')) ?: 'this plan') ?>?</h2>
  <p>Your last day of service will be <b><?= e(date('F j, Y', $end)) ?></b>.
    <?= $periodEnd ? (($confirm['sub_status'] ?? '') === 'trialing' ? 'That’s when your free months end, so you won’t be charged at all.' : 'That’s the end of the month you’ve already paid for, so you won’t be charged again.') : 'Ads management has a 30-day notice period, so any monthly charge before that date still applies. You won’t be charged after it.' ?></p>
  <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($confirm['table'] . ':' . $confirm['id']) ?>">
    <button class="btn primary" type="submit" name="action" value="cancel">Yes, cancel my plan</button><a class="btn ghost" href="portal-plans.php">Keep my plan</a></form>
</section>
<?php endif; ?>
<section class="stack">
<?php if (!$plans): ?><div class="card"><p class="empty">You don’t have any monthly plans. One-off purchases don’t need cancelling.</p></div><?php endif; ?>
<?php foreach ($plans as $p): ?>
  <div class="card">
    <div class="row between"><b><?= e(implode(', ', array_column($p['items'], 'name')) ?: 'Monthly plan') ?></b><span class="muted small"><?= e($p['ref']) ?></span></div>
    <p class="num" style="margin:8px 0"><?= money(plan_monthly($p)) ?>/month · <?= e(SUB_STATUSES[$p['sub_status']] ?? $p['sub_status']) ?></p>
    <?php if (!empty($p['trial_end']) && $p['sub_status'] === 'trialing' && !$p['cancel_at']): ?><p class="muted">Free until <?= e(date('F j, Y', (int)$p['trial_end'])) ?>. Your first charge of <?= money(plan_monthly($p)) ?> is on that date. Cancel before then and you won’t be charged.</p><?php endif; ?>
    <?php if ($p['cancel_at']): ?><p class="muted">Cancelled. Service ends <?= e(date('F j, Y', (int)$p['cancel_at'])) ?>. Changed your mind? Email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p>
    <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="plan" value="<?= e($p['table'] . ':' . $p['id']) ?>"><button class="btn ghost sm" type="submit" name="action" value="review">Cancel this plan</button></form><?php endif; ?>
  </div>
<?php endforeach; ?>
</section>
<p class="muted small">Card details and receipts are handled by Stripe. To update your card, reply to any receipt email or contact us.</p>
<?php portal_bottom(); ?>
