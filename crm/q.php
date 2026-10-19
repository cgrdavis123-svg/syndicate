<?php
// Public proposal page for clients: crm.stusyn.com/q.php?t=TOKEN. Shows one quote; the client can pick extras, accept and pay.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/quotes.php';
header('Cache-Control: private, no-store');
$token = get('t');
$q = null;
if (preg_match('/^[a-f0-9]{32}$/', $token) && !too_many('quote_link', 60, 15)) { $row = one('SELECT id FROM quotes WHERE token = ?', [$token]); $q = $row ? load_quote((int)$row['id']) : null; }
$staff = current_user();
if (!$q || ($q['status'] === 'draft' && !$staff)) {
    if (preg_match('/^[a-f0-9]{32}$/', $token)) record_attempt('quote_link');
    http_response_code(404); $q = null;
}
$json = strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$self = 'q.php?t=' . $token;
$pending = null;
if ($q && is_post()) {
    if (too_many('quote_post', 20, 15)) { http_response_code(429); if ($json) { header('Content-Type: application/json'); exit(json_encode(['ok' => false, 'error' => 'Too many tries. Please wait a few minutes.'])); } exit('Too many tries. Please wait a few minutes.'); }
    record_attempt('quote_post');
    if (post('action') === 'accept') {
        $r = post('agree') !== '1' ? ['ok' => false, 'error' => 'Tick the box to agree to the terms.', 'field' => 'agree'] : quote_accept($q, post('name'), array_map('intval', (array)($_POST['opt'] ?? [])));
        if ($json) { header('Content-Type: application/json'); http_response_code($r['ok'] ? 200 : 422); exit(json_encode($r)); }
        if ($r['ok'] && $r['url'] === '') redirect($self . '&accepted=1');
        if ($r['ok']) $pending = $r['url']; else flash($r['error']);
        if (!$r['ok']) redirect($self . '#accept');
    }
    if (post('action') === 'decline') { quote_decline($q, post('reason')); redirect($self . '&declined=1'); }
}
if ($q && get('paid') === '1' && get('session_id') !== '' && hash_equals($q['stripe_session'], get('session_id'))) {
    try { $q = quote_sync_session($q); } catch (Throwable $e) { error_log('Quote return ' . $q['number'] . ': ' . $e->getMessage()); }
}
if ($q && !$staff) quote_viewed($q);
if ($q) $q = load_quote((int)$q['id']);
$items = $q ? quote_items((int)$q['id']) : [];
$t = $q ? quote_totals($q, $items) : null;
$open = $q && (quote_open($q) || $q['status'] === 'accepted');
$choosing = $q && quote_open($q);
$from = $q ? quote_from($q) : '';
$amt = function ($n) { return money($n, floor($n) != $n); };
$msg = flash();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $q ? e(($q['title'] ?: 'Proposal') . ' · ' . $from) : 'Proposal not found' ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body class="public">
<main class="public-wrap">
<?php if (!$q): ?>
  <div class="card" style="max-width:520px;margin:12vh auto 0;text-align:center"><h1 style="font-size:24px">Proposal not found</h1><p class="muted">This link may be mistyped or no longer active. Please contact <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p></div>
<?php else: ?>
  <?php if ($staff && $q['status'] === 'draft'): ?><p class="notice">Draft preview · only you can see this until you send it.</p><?php elseif ($staff): ?><p class="notice">You’re signed in to the CRM, so this visit isn’t counted as the client opening it.</p><?php endif; ?>
  <?php if ($msg): ?><p class="notice bad"><?= e($msg) ?></p><?php endif; ?>
  <?php if ($pending): ?><div class="q-banner"><b>Accepted. One more step.</b><span>Continue to the secure payment page to pay <?= $amt($t['due_now']) ?>.</span><a class="btn primary" href="<?= e($pending) ?>">Continue to secure payment <?= ARROW ?></a></div><?php endif; ?>
  <?php if (get('cancelled') === '1' && $q['status'] === 'accepted'): ?><p class="notice">Payment cancelled, nothing was charged. You can pay whenever you’re ready below.</p><?php endif; ?>
  <?php if ($q['status'] === 'won'): ?><div class="q-banner won"><b>Thank you<?= $q['accepted_name'] ? ', ' . e(explode(' ', $q['accepted_name'])[0]) : '' ?>. This proposal is accepted.</b><span><?= $q['paid_total'] > 0 ? 'Your payment of ' . $amt($q['paid_total']) . ' went through and Stripe has emailed your receipt. ' : '' ?>Your client portal link is in your inbox.</span><span class="row"><?php $ko = (string)val("SELECT slug FROM meeting_types WHERE kind = 'kickoff' AND active = 1 ORDER BY position LIMIT 1"); if ($ko !== ''): ?><a class="btn primary" href="book.php?type=<?= e($ko) ?>">Book your kickoff call <?= ARROW ?></a><?php endif; ?><a class="btn ghost" href="<?= e(abs_url('portal-login.php')) ?>">Open your client portal</a></span></div><?php endif; ?>
  <?php if ($q['status'] === 'declined'): ?><p class="notice">This proposal was declined<?= get('declined') === '1' ? '. Thanks for letting us know; we’ll be in touch' : '' ?>. If you change your mind, email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p><?php endif; ?>
  <?php if (quote_expired($q) || $q['status'] === 'expired'): ?><p class="notice">This proposal expired on <?= e(fmt_date($q['valid_until'])) ?>. Email <a href="mailto:<?= e(setting('business_email')) ?>?subject=<?= rawurlencode('Updated proposal ' . $q['number']) ?>"><?= e(setting('business_email')) ?></a> and we’ll send an updated one.</p><?php endif; ?>

  <form method="post" action="<?= e($self) ?>" class="invoice-doc proposal" id="acceptForm" data-deposit="<?= (int)$t['pct'] ?>"<?= $open ? '' : ' data-closed' ?>>
    <header class="inv-top">
      <div><div class="inv-brand"><svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0b0d10"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.8" stroke-linecap="round"/></svg><b><?= e($from) ?></b></div>
        <p><?= $from !== setting('business_name') ? e('A ' . setting('business_name') . ' company') . '<br>' : '' ?><?= e(setting('business_location')) ?><br><?= e(setting('business_email')) ?></p></div>
      <div class="inv-title"><h2>Proposal</h2><dl><div><dt>Number</dt><dd><?= e($q['number']) ?></dd></div><div><dt>Date</dt><dd><?= e(fmt_date($q['sent_at'] ?: $q['created_at'])) ?></dd></div><?php if ($q['valid_until']): ?><div><dt>Valid until</dt><dd><?= e(fmt_date($q['valid_until'])) ?></dd></div><?php endif; ?></dl></div>
    </header>
    <section class="q-hero">
      <span class="q-for">Prepared for <?= e(quote_client($q)) ?><?= $q['company'] && ($q['first_name'] || $q['last_name']) ? ' · ' . e(person_name($q)) : '' ?></span>
      <h1><?= e($q['title'] ?: 'Proposal') ?></h1>
      <?php if ($q['intro']): ?><div class="q-intro"><?= nl2br(e($q['intro'])) ?></div><?php endif; ?>
    </section>
    <section>
      <h3 class="q-h">What’s included</h3>
      <div class="q-items">
        <?php foreach ($items as $it): $line = (float)$it['quantity'] * (float)$it['unit_price']; if ($it['optional'] && !$choosing && !$it['selected']) continue; ?>
        <div class="q-item<?= $it['optional'] ? ' opt' : '' ?>">
          <?php if ($it['optional'] && $choosing): ?><label class="q-check"><input type="checkbox" name="opt[]" value="<?= (int)$it['id'] ?>" data-amt="<?= e($line) ?>" data-int="<?= e($it['interval']) ?>"<?= $it['selected'] ? ' checked' : '' ?>><span></span></label><?php else: ?><span class="q-dot" aria-hidden="true"></span><?php endif; ?>
          <div class="q-desc"><b><?= e($it['description']) ?><?= $it['optional'] ? ' <em>' . ($choosing ? 'Optional' : 'Added') . '</em>' : '' ?></b><?php if ($it['details']): ?><p><?= e($it['details']) ?></p><?php endif; ?><?php if ((float)$it['quantity'] != 1): ?><small><?= e(rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.')) ?> × <?= $amt((float)$it['unit_price']) ?></small><?php endif; ?></div>
          <span class="q-amt" <?= !$it['optional'] ? 'data-amt="' . e($line) . '" data-int="' . e($it['interval']) . '"' : '' ?>><?= $amt($line) ?><?= $it['interval'] === 'month' ? '<small>/month</small>' : '' ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="inv-sum q-sum">
        <div><span>One-time</span><b id="qsOne"><?= $amt($t['one_time']) ?></b></div>
        <div id="qsMoRow"<?= $t['monthly'] > 0 ? '' : ' hidden' ?>><span>Monthly</span><b id="qsMo"><?= $amt($t['monthly']) ?>/mo</b></div>
        <?php if ($t['pct'] > 0 && $t['pct'] < 100): ?><div><span><?= (int)$t['pct'] ?>% deposit</span><b id="qsDep"><?= $amt($t['deposit']) ?></b></div><?php endif; ?>
        <div class="grand"><span><?= $q['status'] === 'won' ? 'Paid when accepted' : 'Due when you accept' ?></span><b id="qsDue"><?= $amt($q['status'] === 'won' ? $q['paid_total'] : $t['due_now']) ?></b></div>
        <?php if ($t['pct'] < 100): ?><div class="q-later" id="qsLaterRow"<?= $t['balance'] > 0 ? '' : ' hidden' ?>><span>Invoiced on delivery</span><b id="qsLater"><?= $amt($t['balance']) ?></b></div><?php endif; ?>
      </div>
      <?php if ($q['timeline_days']): ?><p class="q-note">Estimated time to complete: about <?= (int)$q['timeline_days'] ?> days from your kickoff call.</p><?php endif; ?>
    </section>
    <?php if ($q['terms']): ?><details class="q-terms"<?= $open ? '' : ' open' ?>><summary>Terms</summary><div><?= nl2br(e($q['terms'])) ?></div></details><?php endif; ?>

    <?php if ($open): ?>
    <section class="q-accept no-print" id="accept">
      <input type="hidden" name="action" value="accept">
      <?php if ($q['status'] === 'accepted'): ?>
        <p><b>Accepted by <?= e($q['accepted_name']) ?></b> on <?= e(fmt_date($q['accepted_at'], 'M j, Y')) ?>. Pay <?= $amt($t['due_now']) ?> to get started.</p>
        <input type="hidden" name="name" value="<?= e($q['accepted_name']) ?>"><input type="hidden" name="agree" value="1">
      <?php else: ?>
        <h3 class="q-h">Accept this proposal</h3>
        <label class="f"><span>Your full name</span><input name="name" id="qName" required autocomplete="name" placeholder="<?= e(person_name($q)) ?>"></label>
        <label class="chk"><input type="checkbox" name="agree" value="1" required> <span>I accept this proposal and its terms<?= $t['monthly'] > 0 ? ', including monthly billing until cancelled' : '' ?>. Typing my name counts as my signature.</span></label>
      <?php endif; ?>
      <p class="notice bad" id="qErr" hidden></p>
      <button class="btn primary q-pay" type="submit" id="qPay"><?= $t['due_now'] > 0 ? 'Accept and pay <span id="qPayAmt">' . $amt($t['due_now']) . '</span> securely' : 'Accept proposal' ?> <?= ARROW ?></button>
      <?php if ($t['due_now'] > 0): ?><small class="q-fine">Card, Apple Pay or Google Pay through Stripe. We never see your card details.</small><?php endif; ?>
    </section>
    <?php endif; ?>
  </form>
  <?php if ($choosing): ?>
  <details class="card q-decline no-print"><summary>Not the right fit? Let us know</summary>
    <form method="post" action="<?= e($self) ?>" class="stack-sm"><input type="hidden" name="action" value="decline"><label class="f"><span>What would you change? <small class="muted">(optional)</small></span><textarea name="reason" rows="3"></textarea></label><button class="btn ghost sm" type="submit">Decline proposal</button></form>
  </details>
  <?php endif; ?>
  <p class="muted small no-print" style="text-align:center">Questions? Email <a href="mailto:<?= e(setting('business_email')) ?>?subject=<?= rawurlencode('Proposal ' . $q['number']) ?>"><?= e(setting('business_email')) ?></a> · <button class="link-btn" type="button" data-print>Print or save as PDF</button></p>
<?php endif; ?>
</main>
<script src="assets/js/crm.js" defer></script>
</body>
</html>
