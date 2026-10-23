<?php
// Serves one page or file of a private website draft (see app/builds.php). The .htaccess here sets the sandbox headers.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/builds.php';
$v = build_by_token(get('t'));
if (!$v) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('This draft link isn’t available any more. Ask us for the newest one.'); }
build_serve($v, get('p'));
