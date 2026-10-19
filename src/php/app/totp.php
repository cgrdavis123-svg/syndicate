<?php
// Two-step sign-in with an authenticator app (TOTP, RFC 6238: 6 digits, 30-second steps, SHA-1),
// plus one-time recovery codes. The same file is used by the CRM and by Hiring HQ on stusyn.com.
// Secrets are stored encrypted (AES-256-GCM) with a key file in storage/.

function totp_key(): string {
    $f = STORAGE . '/totp.key';
    if (!is_file($f)) { file_put_contents($f, base64_encode(random_bytes(32)), LOCK_EX); @chmod($f, 0600); }
    $k = base64_decode((string)file_get_contents($f));
    if (strlen($k) !== 32) throw new RuntimeException('storage/totp.key is damaged.');
    return $k;
}
function totp_seal(string $plain): string { $iv = random_bytes(12); $ct = openssl_encrypt($plain, 'aes-256-gcm', totp_key(), OPENSSL_RAW_DATA, $iv, $tag); return 't1:' . base64_encode($iv . $tag . $ct); }
function totp_open(string $s): string {
    if ($s === '') return '';
    $b = base64_decode(substr($s, 3));
    $out = strlen($b) > 28 ? openssl_decrypt(substr($b, 28), 'aes-256-gcm', totp_key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16)) : false;
    if ($out === false) throw new RuntimeException('The saved two-step secret can’t be read (storage/totp.key changed?).');
    return $out;
}
function totp_new_secret(): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = '';
    for ($i = 0; $i < 32; $i++) $s .= $a[random_int(0, 31)];
    return $s; // 32 base32 characters (160 bits)
}
function totp_b32decode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}
function totp_code(string $secret, int $step): string {
    $h = hash_hmac('sha1', pack('N*', 0, $step), totp_b32decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
// Returns the matching time step (allowing one step of clock drift each way), or 0. A step at or below
// $lastStep is refused, so a code can't be used twice.
function totp_check(string $secret, string $code, int $lastStep = 0): int {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return 0;
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) { $st = $now + $d; if ($st > $lastStep && hash_equals(totp_code($secret, $st), $code)) return $st; }
    return 0;
}
function totp_uri(string $secret, string $account, string $issuer): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
// Ten recovery codes like 4f2a-9c1e-77b0. Returns [plain codes to show once, JSON of their hashes to store].
function totp_recovery_codes(): array {
    $plain = []; for ($i = 0; $i < 10; $i++) $plain[] = implode('-', str_split(bin2hex(random_bytes(6)), 4));
    return [$plain, json_encode(array_map(function ($c) { return hash('sha256', $c); }, $plain))];
}
// Uses up a recovery code. Returns the updated JSON, or null if the code doesn't match.
function totp_use_recovery(string $json, string $code): ?string {
    $code = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $code));
    if (strlen($code) !== 12) return null;
    $h = hash('sha256', implode('-', str_split($code, 4)));
    $list = json_decode($json ?: '[]', true) ?: [];
    foreach ($list as $i => $x) if (hash_equals($x, $h)) { unset($list[$i]); return json_encode(array_values($list)); }
    return null;
}
