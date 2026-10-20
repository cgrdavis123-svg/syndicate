<?php
// Shared start for every page on ads.stusyn.com. The ads app lives in the CRM's ads/ folder and uses the CRM's
// database, logins and helpers; ads.stusyn.com's document root points at that folder.
if (!defined('ADS_APP')) define('ADS_APP', true);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/ads.php';
// Signed-in client's ads account, or send them to sign in.
function ads_require_client(): array {
    header('Cache-Control: no-store');
    $c = portal_contact();
    if (!$c) redirect('portal-login.php');
    if (is_post() && !csrf_ok()) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
    demo_read_only();
    $a =$c['company_id'] ? one('SELECT a.*, co.name company, co.brand FROM ad_clients a JOIN companies co ON co.id = a.company_id WHERE a.company_id = ?', [(int)$c['company_id']]) : null;
    if (!$a) { portal_top('No ads plan', $c); echo '<section class="card" style="max-width:560px;margin:8vh auto;text-align:center"><h1 style="font-size:22px">No ads plan on this account</h1><p class="muted">Your ads results show here once you’re on one of our ads management plans. Your other projects are in your <a href="' . e(rtrim((string)config('app_url'), '/')) . '/portal.php">client portal</a>.</p></section>'; portal_bottom(); exit; }
    return [$c, $a];
}
