<?php
// Stripe sends payment events here. In Stripe → Developers → Webhooks, add this address
// (https://crm.stusyn.com/stripe-webhook.php) and copy its signing secret into Store → Settings.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
header('Content-Type: application/json');
if (!is_post()) { http_response_code(405); exit('{"ok":false}'); }
$payload = (string)file_get_contents('php://input', false, null, 0, 1048576);
$ev = stripe_verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), stripe_key('stripe_webhook_secret'));
if (!$ev || empty($ev['id'])) { http_response_code(400); exit('{"ok":false,"error":"Signature check failed"}'); }

// Each event is handled once, even when Stripe sends it again.
try { q('INSERT INTO stripe_events (id, type, created_at) VALUES (?,?,?)', [$ev['id'], (string)($ev['type'] ?? ''), now()]); }
catch (PDOException $e) { exit('{"ok":true,"result":"already handled"}'); }
try {
    $result = shop_handle_event($ev);
    q('DELETE FROM stripe_events WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 60 * 86400)]);
    echo json_encode(['ok' => true, 'result' => $result]);
} catch (Throwable $e) {
    q('DELETE FROM stripe_events WHERE id = ?', [$ev['id']]); // let Stripe retry
    error_log('Stripe webhook ' . ($ev['type'] ?? '') . ' failed: ' . $e->getMessage());
    http_response_code(500);
    echo '{"ok":false}';
}
