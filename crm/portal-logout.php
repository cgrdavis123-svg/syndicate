<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
portal_session();
if (DEMO) { $_SESSION = []; session_destroy(); demo_cookie(false); redirect('portal-demo.php?bye=1'); } // leaving the demo needs no token
if (hash_equals(csrf_token(), get('t'))) { $_SESSION = []; session_destroy(); }
redirect('portal-login.php');
