<?php
// Passkey requests from assets/js/passkey.js: adding one in Settings, and using one at the second sign-in step.
// ads/ and wiki/ have small copies that load this file, so passkeys work on every staff sign-in.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/passkey.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
function pk_out(array $r, int $code = 200): void { http_response_code($code); echo json_encode($r); exit; }
if (!is_post()) pk_out(['error' => 'Not found.'], 404);
start_session();
if (!csrf_ok()) pk_out(['error' => 'Your session expired. Refresh the page and try again.'], 400);
$a = post('a');

try {
    if ($a === 'reg_options' || $a === 'reg_finish') {
        $user = current_user();
        if (!$user) pk_out(['error' => 'Please sign in again.'], 401);
        $me = one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        $list = pk_list($me);
        if ($a === 'reg_options') {
            if (count($list) >= 10) pk_out(['error' => 'You already have 10 passkeys. Remove one first.']);
            pk_out(pk_register_options(hash('sha256', 'syn-crm-user|' . $me['id'], true), $me['email'], $me['name'] . ' · Syndicate CRM', $list, 'Syndicate CRM'));
        }
        $new = pk_register_verify(post('client'), post('att'), post('name'));
        foreach ($list as $p) if (hash_equals($p['id'], $new['id'])) pk_out(['error' => 'That passkey is already on your account.']);
        $list[] = $new;
        q('UPDATE users SET passkeys = ? WHERE id = ?', [json_encode($list), $me['id']]);
        // First way to sign in a second step: give them recovery codes too, in case the device is lost.
        if (count(json_decode($me['recovery_codes'] ?: '[]', true) ?: []) === 0 && $me['totp_secret'] === '') {
            require __DIR__ . '/app/totp.php';
            [$plain, $hashes] = totp_recovery_codes();
            q('UPDATE users SET recovery_codes = ? WHERE id = ?', [$hashes, $me['id']]);
            $_SESSION['tfa_codes'] = $plain;
        }
        flash('Passkey added. You can use it at the second sign-in step on the CRM, ads and wiki.');
        pk_out(['ok' => true, 'to' => url('settings.php#tfa')]);
    }

    if ($a === 'login_options' || $a === 'login_finish') {
        $uid = (int)($_SESSION['tfa_uid'] ?? 0);
        if (!$uid || (int)($_SESSION['tfa_at'] ?? 0) < time() - 300) pk_out(['error' => 'That took a while. Start over and sign in again.'], 401);
        $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
        $list = $u ? pk_list($u) : [];
        if (!$list) pk_out(['error' => 'There are no passkeys on this account. Use a code instead.']);
        if (too_many('tfa', 6, 15)) pk_out(['error' => 'Too many tries. Wait 15 minutes and sign in again.'], 429);
        if ($a === 'login_options') pk_out(pk_login_options($list));
        try { $list = pk_login_verify($list, post('id'), post('client'), post('auth'), post('sig')); }
        catch (RuntimeException $ex) { record_attempt('tfa'); throw $ex; }
        q('UPDATE users SET passkeys = ?, last_login_at = ? WHERE id = ?', [json_encode($list), now(), $uid]);
        unset($_SESSION['tfa_uid'], $_SESSION['tfa_at']);
        sign_in_user($u);
        pk_out(['ok' => true, 'to' => url('./')]);
    }
} catch (RuntimeException $ex) {
    pk_out(['error' => $ex->getMessage()]);
}
pk_out(['error' => 'Not found.'], 404);
