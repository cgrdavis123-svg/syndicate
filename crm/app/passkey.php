<?php
// Passkeys (WebAuthn) as a second sign-in step: Face ID / Touch ID on an iPhone, iPad or Mac, a Windows Hello PC,
// an Android phone, or a security key. No libraries: a small CBOR reader, COSE keys turned into PEM, and openssl_verify.
// Supports ES256 (what Apple, Google and most keys use) and RS256 (Windows Hello). Attestation isn't asked for.
// The same file is used by the CRM and by Hiring HQ on stusyn.com. Credentials are kept as a JSON list on the account
// (public keys only, nothing secret): [{id, pk, alg, n, name, at, used}].

// The site a passkey belongs to. stusyn.com covers crm., ads. and wiki.stusyn.com (and Hiring HQ on stusyn.com),
// so one passkey works on all of them. Set passkey_rp_id in config.php to override.
function pk_rp_id(): string {
    if ((string)config('passkey_rp_id') !== '') return strtolower((string)config('passkey_rp_id'));
    $h = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($h === '' || filter_var(trim($h, '[]'), FILTER_VALIDATE_IP) || strpos($h, '.') === false) return $h;
    return implode('.', array_slice(explode('.', $h), -2));
}
function pk_b64u(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function pk_unb64u(string $s): string { $r = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); return $r === false ? '' : $r; }
function pk_list(array $account): array { $l = json_decode((string)($account['passkeys'] ?? '[]'), true); return is_array($l) ? $l : []; }
// Two-step sign-in is on when the account has an authenticator app or at least one passkey.
function tfa_on(array $account): bool { return (string)($account['totp_secret'] ?? '') !== '' || pk_list($account) !== []; }

// One challenge per purpose, kept in the session for 5 minutes and usable once.
function pk_challenge(string $purpose): string { $c = pk_b64u(random_bytes(32)); $_SESSION['pk_' . $purpose] = [$c, time()]; return $c; }
function pk_take_challenge(string $purpose): string {
    [$c, $t] = array_pad((array)($_SESSION['pk_' . $purpose] ?? []), 2, 0);
    unset($_SESSION['pk_' . $purpose]);
    return is_string($c) && (int)$t > time() - 300 ? $c : '';
}

// Options for navigator.credentials.create(). Binary fields are base64url; passkey.js decodes them.
function pk_register_options(string $userHandle, string $name, string $display, array $list, string $rpName): array {
    return ['challenge' => pk_challenge('reg'), 'rp' => ['id' => pk_rp_id(), 'name' => $rpName],
        'user' => ['id' => pk_b64u($userHandle), 'name' => $name, 'displayName' => $display],
        'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
        'timeout' => 120000, 'attestation' => 'none',
        'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'preferred'],
        'excludeCredentials' => array_map(function ($p) { return ['type' => 'public-key', 'id' => $p['id']]; }, $list)];
}
function pk_login_options(array $list): array {
    return ['challenge' => pk_challenge('login'), 'rpId' => pk_rp_id(), 'timeout' => 120000, 'userVerification' => 'preferred',
        'allowCredentials' => array_map(function ($p) { return ['type' => 'public-key', 'id' => $p['id']]; }, $list)];
}

// Checks clientDataJSON: the right ceremony, our challenge, and this exact page's origin.
function pk_client_ok(string $json, string $type, string $challenge): void {
    $c = json_decode($json, true);
    if (!is_array($c) || ($c['type'] ?? '') !== $type) throw new RuntimeException('That passkey response was for something else.');
    if ($challenge === '' || !hash_equals($challenge, (string)($c['challenge'] ?? ''))) throw new RuntimeException('That took too long. Try again.');
    if (!empty($c['crossOrigin'])) throw new RuntimeException('Passkeys can’t be used from inside another site.');
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $bare = preg_replace('/:\d+$/', '', $host);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $want = ($https || !in_array($bare, ['localhost', '127.0.0.1', '[::1]'], true) ? 'https' : 'http') . '://' . $host;
    $rp = pk_rp_id();
    if (!hash_equals($want, strtolower((string)($c['origin'] ?? ''))) || ($bare !== $rp && substr($bare, -strlen($rp) - 1) !== '.' . $rp)) throw new RuntimeException('That passkey response came from the wrong site.');
}
// authenticatorData: rpIdHash(32) flags(1) signCount(4) [attested credential data] [extensions].
function pk_auth_data(string $ad): array {
    if (strlen($ad) < 37) throw new RuntimeException('That passkey response was cut short.');
    if (!hash_equals(hash('sha256', pk_rp_id(), true), substr($ad, 0, 32))) throw new RuntimeException('That passkey belongs to a different site.');
    $flags = ord($ad[32]);
    if (!($flags & 0x01)) throw new RuntimeException('The passkey didn’t confirm you were there. Try again.');
    return ['flags' => $flags, 'n' => unpack('N', substr($ad, 33, 4))[1], 'rest' => substr($ad, 37)];
}

// New passkey: returns the record to store. Throws RuntimeException with a message to show.
function pk_register_verify(string $clientB64, string $attB64, string $label): array {
    $client = pk_unb64u($clientB64);
    pk_client_ok($client, 'webauthn.create', pk_take_challenge('reg'));
    $off = 0; $att = pk_cbor(pk_unb64u($attB64), $off);
    if (!is_array($att) || !isset($att['authData']) || !is_string($att['authData'])) throw new RuntimeException('That passkey response couldn’t be read.');
    $d = pk_auth_data($att['authData']);
    if (!($d['flags'] & 0x40) || strlen($d['rest']) < 18) throw new RuntimeException('The passkey didn’t send its key.');
    $len = unpack('n', substr($d['rest'], 16, 2))[1];
    $id = substr($d['rest'], 18, $len);
    if (strlen($id) !== $len || $len < 16 || $len > 1023) throw new RuntimeException('That passkey response couldn’t be read.');
    $o = 18 + $len; $cose = pk_cbor($d['rest'], $o);
    [$pem, $alg] = pk_cose_pem(is_array($cose) ? $cose : []);
    $label = trim(preg_replace('/\s+/', ' ', $label));
    return ['id' => pk_b64u($id), 'pk' => $pem, 'alg' => $alg, 'n' => $d['n'], 'name' => mb_substr($label !== '' ? $label : 'Passkey', 0, 60), 'at' => date('Y-m-d H:i:s'), 'used' => ''];
}
// Sign-in: returns the account's updated list (sign count, last used). Throws RuntimeException on failure.
function pk_login_verify(array $list, string $idB64, string $clientB64, string $authB64, string $sigB64): array {
    $client = pk_unb64u($clientB64);
    pk_client_ok($client, 'webauthn.get', pk_take_challenge('login'));
    $i = null; foreach ($list as $k => $p) if (hash_equals((string)$p['id'], $idB64)) { $i = $k; break; }
    if ($i === null) throw new RuntimeException('That passkey isn’t set up for this account.');
    $ad = pk_unb64u($authB64);
    $d = pk_auth_data($ad);
    $ok = openssl_verify($ad . hash('sha256', $client, true), pk_unb64u($sigB64), $list[$i]['pk'], OPENSSL_ALGO_SHA256);
    if ($ok !== 1) throw new RuntimeException('That passkey didn’t check out. Try again.');
    // Counters only ever go up. Apple and Google synced passkeys always send 0, which skips this.
    if (($d['n'] || $list[$i]['n']) && $d['n'] <= (int)$list[$i]['n']) throw new RuntimeException('That passkey looks like a copy. Remove it in settings and add it again.');
    $list[$i]['n'] = $d['n']; $list[$i]['used'] = date('Y-m-d H:i:s');
    return $list;
}

// Minimal CBOR reader (RFC 8949): enough for attestation objects and COSE keys.
function pk_cbor(string $b, int &$o, int $depth = 0) {
    if ($o >= strlen($b) || $depth > 16) throw new RuntimeException('That passkey response couldn’t be read.');
    $ib = ord($b[$o++]); $mt = $ib >> 5; $ai = $ib & 31;
    if ($mt === 7) {
        if ($ai === 20) return false; if ($ai === 21) return true; if ($ai === 22 || $ai === 23) return null;
        $sz = [25 => 2, 26 => 4, 27 => 8][$ai] ?? 0; if (!$sz) throw new RuntimeException('That passkey response couldn’t be read.');
        $o += $sz; return 0.0; // floats never matter here
    }
    if ($ai < 24) $n = $ai;
    elseif ($ai <= 27) { $sz = 1 << ($ai - 24); if ($o + $sz > strlen($b)) throw new RuntimeException('That passkey response couldn’t be read.'); $n = 0; for ($k = 0; $k < $sz; $k++) $n = $n * 256 + ord($b[$o + $k]); $o += $sz; }
    else throw new RuntimeException('That passkey response couldn’t be read.');
    switch ($mt) {
        case 0: return $n;
        case 1: return -1 - $n;
        case 2: case 3: if ($o + $n > strlen($b)) throw new RuntimeException('That passkey response couldn’t be read.'); $s = substr($b, $o, $n); $o += $n; return $s;
        case 4: $a = []; for ($k = 0; $k < $n; $k++) $a[] = pk_cbor($b, $o, $depth + 1); return $a;
        case 5: $m = []; for ($k = 0; $k < $n; $k++) { $key = pk_cbor($b, $o, $depth + 1); $m[$key] = pk_cbor($b, $o, $depth + 1); } return $m;
        default: return pk_cbor($b, $o, $depth + 1); // tag: read the tagged item
    }
}
// COSE public key → [PEM, alg]. EC2 P-256 (-7) or RSA (-257).
function pk_cose_pem(array $k): array {
    if (($k[1] ?? 0) === 2 && ($k[3] ?? 0) === -7 && ($k[-1] ?? 0) === 1 && strlen((string)($k[-2] ?? '')) === 32 && strlen((string)($k[-3] ?? '')) === 32) {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $k[-2] . $k[-3];
        $alg = -7;
    } elseif (($k[1] ?? 0) === 3 && ($k[3] ?? 0) === -257 && is_string($k[-1] ?? null) && is_string($k[-2] ?? null) && strlen($k[-1]) >= 256) {
        $int = function (string $v) { $v = ltrim($v, "\0"); if ($v === '' || ord($v[0]) & 0x80) $v = "\0" . $v; return pk_der("\x02", $v); };
        $der = pk_der("\x30", pk_der("\x30", hex2bin('06092a864886f70d0101010500')) . pk_der("\x03", "\0" . pk_der("\x30", $int($k[-1]) . $int($k[-2]))));
        $alg = -257;
    } else throw new RuntimeException('That kind of passkey isn’t supported. Try Face ID, Touch ID or a newer security key.');
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    if (!openssl_pkey_get_public($pem)) throw new RuntimeException('That passkey’s key couldn’t be read.');
    return [$pem, $alg];
}
function pk_der(string $tag, string $body): string {
    $n = strlen($body);
    if ($n < 128) $len = chr($n); else { $l = ltrim(pack('N', $n), "\0"); $len = chr(0x80 | strlen($l)) . $l; }
    return $tag . $len . $body;
}
// "Added Oct 20, 2026 · last used 2 days ago" style description for a settings list.
function pk_when(array $p): string { return 'Added ' . date('M j, Y', strtotime($p['at'])) . ($p['used'] !== '' ? ' · last used ' . date('M j, Y', strtotime($p['used'])) : ' · not used yet'); }
