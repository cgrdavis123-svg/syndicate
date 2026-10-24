<?php
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
start_session();
if (hash_equals((string)($_SESSION['csrf'] ?? ''), get('t'))) { unset($_SESSION['team_id']); session_regenerate_id(true); flash('You’re signed out.'); }
redirect('team/login.php');
