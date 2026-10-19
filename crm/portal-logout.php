<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
portal_session();
if (hash_equals(csrf_token(), get('t'))) { $_SESSION = []; session_destroy(); }
redirect('portal-login.php');
