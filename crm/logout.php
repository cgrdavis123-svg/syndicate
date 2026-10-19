<?php
require __DIR__ . '/app/bootstrap.php';
start_session();
if (!empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], get('t'))) { $_SESSION = []; session_destroy(); }
redirect('login.php');
