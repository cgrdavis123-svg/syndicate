<?php
// Command-line helpers for the server (cPanel → Terminal). Blocked from the web.
//   php app/cli.php 2fa-off you@example.com   Turns off two-step sign-in (app and passkeys) for a CRM user who lost their phone
//                                             and their recovery codes. Anyone who can run this already controls the server.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
[$cmd, $arg] = [$argv[1] ?? '', $argv[2] ?? ''];
if ($cmd === '2fa-off' && $arg !== '') {
    $n = q("UPDATE users SET totp_secret = '', totp_last = 0, recovery_codes = '[]', passkeys = '[]' WHERE email = ? COLLATE NOCASE", [$arg])->rowCount();
    echo $n ? "Two-step sign-in turned off for $arg. Sign in with the password and set it up again in Settings.\n" : "No CRM user with that email.\n";
    exit($n ? 0 : 1);
}
if ($cmd === 'digest') { // php app/cli.php digest   Prints this week's pipeline summary (add "send" to email it)
    require __DIR__ . '/digest.php';
    if ($arg === 'send') { echo pipeline_digest_send(true), "\n"; exit(0); }
    $d = pipeline_digest(); echo $d['subject'], "\n\n", $d['body']; exit(0);
}
echo "Usage: php app/cli.php 2fa-off you@example.com | digest [send]\n"; exit(1);
