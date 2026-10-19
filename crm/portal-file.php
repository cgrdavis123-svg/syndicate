<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$c = require_portal();
$f = one('SELECT * FROM project_files WHERE id = ?', [(int)get('id')]);
if (!$f || !portal_project($c, (int)$f['project_id'])) { http_response_code(404); exit('That file isn’t available.'); }
send_project_file($f);
