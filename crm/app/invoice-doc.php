<?php
// The invoice document, shared by the CRM invoice page and the client's public link.
function invoice_from(array $inv): string { return $inv['brand'] && $inv['brand'] !== 'Syndicate Holdings Group' ? $inv['brand'] : setting('business_name'); }
const INVOICE_SELECT = 'SELECT i.*, co.name company, co.address, c.first_name, c.last_name, c.email FROM invoices i LEFT JOIN companies co ON co.id = i.company_id LEFT JOIN contacts c ON c.id = i.contact_id';
function load_invoice(int $id): ?array { return one(INVOICE_SELECT . ' WHERE i.id = ?', [$id]); }
// {month} and {year} in line items and notes become the invoice's own month, so a recurring
// "Social media management — {month}" reads "… — November 2026" on November's invoice.
function fill_period(string $s, array $inv): string { $t = strtotime($inv['issue_date']); return strtr($s, ['{month}' => date('F Y', $t), '{year}' => date('Y', $t)]); }
function invoice_message(array $inv, float $total, bool $remind): string {
    $first = $inv['first_name'] ?: 'there';
    return $remind
        ? "Hi $first,\n\nA friendly reminder that invoice {$inv['number']} for " . money($total) . ' was due ' . fmt_date($inv['due_date']) . '. You can view and pay it using the link below. Thank you!'
        : "Hi $first,\n\nHere's invoice {$inv['number']} for " . money($total) . ', due ' . fmt_date($inv['due_date']) . '. You can view, print or pay it using the link below. Thank you for your business!';
}
// Emails an invoice (or a reminder) and records it. $inv comes from load_invoice().
function email_invoice(array $inv, string $to, bool $remind, string $message, string $sender): bool {
    $tot = invoice_total((int)$inv['id']);
    $from = invoice_from($inv);
    $subject = ($remind ? 'Reminder: invoice ' : 'Invoice ') . $inv['number'] . ' from ' . $from;
    $body = trim($message) . "\n\n"
        . 'Invoice: ' . $inv['number'] . "\nAmount due: " . money($tot['total']) . "\nDue date: " . fmt_date($inv['due_date']) . "\n\n"
        . 'View and print your invoice: ' . invoice_link($inv) . "\n"
        . ($inv['payment_url'] ? 'Pay online: ' . $inv['payment_url'] . "\n" : '')
        . "\n" . ($sender !== '' ? $sender . "\n" : '') . $from . "\n" . setting('business_email');
    if (!send_mail($to, $subject, $body, setting('business_email'))) return false;
    if ($remind) q('UPDATE invoices SET reminded_at = ?, updated_at = ? WHERE id = ?', [now(), now(), $inv['id']]);
    else q("UPDATE invoices SET status = CASE WHEN status = 'draft' THEN 'sent' ELSE status END, sent_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $inv['id']]);
    $note = ($remind ? 'Payment reminder for ' : 'Invoice ') . $inv['number'] . ' (' . money($tot['total']) . ') ' . ($remind ? 'sent to ' : 'emailed to ') . $to . '.';
    if ($inv['contact_id']) log_activity('contact', (int)$inv['contact_id'], 'email', $note);
    if ($inv['company_id']) log_activity('company', (int)$inv['company_id'], 'email', $note);
    return true;
}
function render_invoice_doc(array $inv, array $items, array $t, bool $public = false): void {
    $fromName = invoice_from($inv); ?>
<article class="invoice-doc">
  <header class="inv-top">
    <div><div class="inv-brand"><svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0b0d10"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.8" stroke-linecap="round"/></svg><b><?= e($fromName) ?></b></div>
      <p><?= $fromName !== setting('business_name') ? e('A ' . setting('business_name') . ' company') . '<br>' : '' ?><?= e(setting('business_location')) ?><br><?= e(setting('business_email')) ?> · <?= e(setting('business_website')) ?></p></div>
    <div class="inv-title"><h2>Invoice</h2><dl><div><dt>Number</dt><dd><?= e($inv['number']) ?></dd></div><div><dt>Issued</dt><dd><?= e(fmt_date($inv['issue_date'])) ?></dd></div><div><dt>Due</dt><dd><?= e(fmt_date($inv['due_date'])) ?></dd></div></dl></div>
  </header>
  <section class="inv-to"><span>Bill to</span><b><?= e($inv['company'] ?: person_name($inv)) ?></b><?php if ($inv['company'] && ($inv['first_name'] || $inv['last_name'])): ?><p>Attn: <?= e(person_name($inv)) ?></p><?php endif; ?><?php if ($inv['address']): ?><p><?= nl2br(e($inv['address'])) ?></p><?php endif; ?><?php if ($inv['email']): ?><p><?= e($inv['email']) ?></p><?php endif; ?></section>
  <table class="inv-items">
    <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Rate</th><th class="r">Amount</th></tr></thead>
    <tbody><?php foreach ($items as $it): ?><tr><td><?= e(fill_period($it['description'], $inv)) ?></td><td class="r"><?= e(rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.')) ?></td><td class="r"><?= money($it['unit_price']) ?></td><td class="r"><?= money($it['quantity'] * $it['unit_price']) ?></td></tr><?php endforeach; ?></tbody>
  </table>
  <div class="inv-sum">
    <div><span>Subtotal</span><b><?= money($t['subtotal']) ?></b></div>
    <?php if ($t['tax'] > 0): ?><div><span>Tax (<?= e((float)$inv['tax_rate']) ?>%)</span><b><?= money($t['tax']) ?></b></div><?php endif; ?>
    <div class="grand"><span>Total due</span><b><?= money($t['total']) ?></b></div>
    <?php if ($inv['status'] === 'paid'): ?><div class="paid-stamp">PAID <?= e(fmt_date($inv['paid_at'])) ?><?= $inv['paid_method'] ? ' · ' . e($inv['paid_method']) : '' ?></div><?php endif; ?>
  </div>
  <?php if ($inv['notes']): ?><section class="inv-notes"><?= nl2br(e(fill_period($inv['notes'], $inv))) ?></section><?php endif; ?>
  <?php if ($inv['payment_url'] && $inv['status'] === 'sent'): ?><div class="inv-pay no-print"><a class="btn primary" href="<?= e($inv['payment_url']) ?>" target="_blank" rel="noopener noreferrer">Pay <?= money($t['total']) ?> online <?= ARROW ?></a><small>Secure payment link</small></div><?php endif; ?>
  <footer class="inv-foot"><div><span>Payment</span><p><?= nl2br(e(setting('payment_instructions'))) ?></p></div><p><?= e(setting('invoice_footer')) ?></p></footer>
</article>
<?php }
