<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
require_user();
$f = one('SELECT * FROM project_files WHERE id = ?', [(int)get('id')]);
if (!$f) { http_response_code(404); exit('That file is no longer available.'); }
send_project_file($f);
