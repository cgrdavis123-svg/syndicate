<?php
// Demo client portal: crm.stusyn.com/portal-demo.php signs anyone in as a made-up client (Ridgeline Heating & Air)
// so prospects can see everything the portal does. Separate databases, nothing is saved or sent (app/demo.php).
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
require __DIR__ . '/app/demo.php';
demo_enter('portal.php');
