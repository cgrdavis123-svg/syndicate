<?php
// From the client portal to the ads portal (ads.stusyn.com) without signing in again: a one-time, 2-minute link.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$c = require_portal();
$tok = bin2hex(random_bytes(24));
q('INSERT INTO portal_links (token_hash, contact_id, expires_at) VALUES (?,?,?)', [hash('sha256', $tok), $c['id'], time() + 120]);
header('Location: ' . ads_url('portal-login.php?t=' . $tok), true, 303);
exit;
