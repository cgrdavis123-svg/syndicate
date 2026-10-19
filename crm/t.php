<?php
// Receives visits and actions from the a.js script on client websites. Public: no sign-in, sets no cookies.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
an_collect();
