<?php
// Shared start for every page on wiki.stusyn.com. The wiki lives in the CRM's wiki/ folder and uses the CRM's
// database and staff logins; wiki.stusyn.com's document root points at that folder.
if (!defined('WIKI_APP')) define('WIKI_APP', true);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wiki.php';
wiki_seed();
