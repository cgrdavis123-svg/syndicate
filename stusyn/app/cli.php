<?php
// Command-line helpers for the server (cPanel → Terminal). Blocked from the web.
//   php app/cli.php 2fa-off you@example.com   Turns off two-step sign-in for a Hiring HQ admin who lost their phone and recovery codes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
[$cmd, $arg] = [$argv[1] ?? '', $argv[2] ?? ''];
if ($cmd === '2fa-off' && $arg !== '') {
    $st = db()->prepare("UPDATE admins SET totp_secret = '', totp_last = 0, recovery_codes = '[]' WHERE email = ? COLLATE NOCASE"); $st->execute([$arg]);
    echo $st->rowCount() ? "Two-step sign-in turned off for $arg.\n" : "No admin with that email.\n";
    exit($st->rowCount() ? 0 : 1);
}
echo "Usage: php app/cli.php 2fa-off you@example.com\n"; exit(1);
