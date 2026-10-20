<?php
// Demo client portal: builds the made-up client "Ridgeline Heating & Air" in storage/demo/, the databases the
// portal reads when DEMO is on (see bootstrap.php). It's rebuilt once a day so the dates always look current.
// Everything is sample data for a fictional business (.example domain, 555-01xx phone), labelled as a demo on every page.
const DEMO_VERSION = '1';
const DEMO_EMAIL = 'dana@ridgelineair.example';

// Makes sure today's demo data exists. Cheap when it does: one small file read.
function demo_ensure(): void {
    if (!DEMO) return;
    $mark = DATA . '/seeded';
    $want = DEMO_VERSION . '|' . date('Y-m-d');
    if (is_file($mark) && trim((string)@file_get_contents($mark)) === $want) return;
    if (!is_dir(DATA)) @mkdir(DATA, 0700, true);
    $lock = fopen(DATA . '/.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) return;
    clearstatcache();
    if (!is_file($mark) || trim((string)@file_get_contents($mark)) !== $want) {
        foreach (array_merge(glob(DATA . '/*.sqlite*') ?: [], glob(DATA . '/portal/*/*') ?: [], glob(DATA . '/ads/*') ?: []) as $f) @unlink($f);
        demo_seed();
        file_put_contents($mark, $want);
    }
    flock($lock, LOCK_UN); fclose($lock);
}
// portal-demo.php on either site: build today's data if needed, then sign the visitor in as the demo client.
function demo_enter(string $dest): void {
    header('Cache-Control: no-store');
    if (get('bye') === '1') { demo_bye(); exit; }
    demo_ensure();
    $c = demo_contact();
    if (!$c) { http_response_code(503); exit('The demo is being set up. Refresh in a moment.'); }
    demo_cookie(true);
    portal_sign_in($c);
    redirect($dest);
}
function demo_bye(): void {
    $shop = setting('shop_site_url') ?: 'https://syndicatemarketing.co';
    portal_top('Thanks for looking', null); ?>
<section class="auth-card card portal-login">
  <div><h1>That was the demo</h1><p class="muted">Every client gets their own portal like this one: projects and approvals, files, invoices, plans, website stats<?= defined('ADS_APP') ? '' : ', website care' ?> and ad results, all in one place.</p></div>
  <a class="btn primary" href="<?= e(abs_url('book.php?type=discovery-call')) ?>">Book a free discovery call <?= ARROW ?></a>
  <a class="btn ghost" href="<?= e(rtrim($shop, '/')) ?>/pricing.html">See packages and prices</a>
  <p class="muted small"><a href="portal-demo.php">Look around the demo again</a></p>
</section>
<?php portal_bottom();
}
// The demo contact's sign-in token stays the same across rebuilds, so a visitor stays signed in.
function demo_token(): string { return substr(hash('sha256', 'syn-demo|' . (string)config('setup_key')), 0, 40); }
function demo_contact(): ?array { return one('SELECT * FROM contacts WHERE email = ? AND portal_access = 1', [DEMO_EMAIL]); }

function demo_seed(): void {
    require_once __DIR__ . '/analytics.php';
    require_once __DIR__ . '/ads.php';
    mt_srand(crc32(date('Y-m-d')));
    $day = function (int $ago, string $time = '10:00:00'): string { return date('Y-m-d', strtotime("-$ago days")) . ' ' . $time; };
    $d = function (int $ago): string { return date('Y-m-d', strtotime(($ago >= 0 ? '-' : '+') . abs($ago) . ' days')); };
    $tok = function (): string { return bin2hex(random_bytes(16)); };
    $price = function (string $sku): float { return (float)val('SELECT price FROM products WHERE sku = ?', [$sku]); };
    $pdo = db();
    $pdo->beginTransaction();

    /* ---------- Who ---------- */
    q("INSERT INTO companies (name, website, phone, industry, city, brand, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)",
        ['Ridgeline Heating & Air', 'https://ridgelineair.example', '(864) 555-0142', 'HVAC', 'Greer, SC', 'Syndicate Marketing', 'Demo client (fictional).', $day(160), $day(1)]);
    $co = (int)$pdo->lastInsertId();
    q("INSERT INTO contacts (company_id, first_name, last_name, email, phone, title, created_at, updated_at, portal_access, portal_token, portal_seen_at) VALUES (?,?,?,?,?,?,?,?,1,?,?)",
        [$co, 'Dana', 'Reyes', DEMO_EMAIL, '(864) 555-0142', 'Office manager', $day(160), $day(1), demo_token(), $day(1)]);
    $ct = (int)$pdo->lastInsertId();

    /* ---------- Website, stats and care ---------- */
    $scan = ['at' => strtotime('-2 days'), 'ok' => true, 'code' => 200, 'ms' => 286, 'kb' => 41, 'https' => true, 'redirect' => true,
        'headers' => ['hsts' => true, 'nosniff' => true, 'frames' => true, 'csp' => true, 'referrer' => true], 'exposed' => [], 'software' => '', 'server' => '',
        'title' => 'Ridgeline Heating & Air · AC repair and heating in the Upstate', 'description' => true, 'viewport' => true, 'mixed' => [], 'links' => 34, 'broken' => []];
    $psi = ['at' => strtotime('-4 days'), 'scores' => ['performance' => 96, 'accessibility' => 100, 'best-practices' => 100, 'seo' => 100],
        'metrics' => ['first-contentful-paint' => ['v' => 1100, 'show' => '1.1 s', 'score' => 0.98], 'largest-contentful-paint' => ['v' => 1900, 'show' => '1.9 s', 'score' => 0.95],
            'total-blocking-time' => ['v' => 40, 'show' => '40 ms', 'score' => 1], 'cumulative-layout-shift' => ['v' => 0.01, 'show' => '0.01', 'score' => 1], 'speed-index' => ['v' => 1500, 'show' => '1.5 s', 'score' => 0.99]],
        'fixes' => [['title' => 'Serve images in next-gen formats', 'ms' => 150, 'show' => 'Potential savings of 38 KiB']], 'prev' => ['performance' => 93, 'accessibility' => 100, 'best-practices' => 100, 'seo' => 100]];
    q("INSERT INTO sites (name, company_id, domains, public_id, shared, monthly_report, created_at, care, care_minutes, backup_days, monitor, status, status_since, last_check, last_ms,
        ssl_expires, ssl_issuer, ssl_checked, domain_expires, domain_checked, scan, scan_checked, care_sku, care_platform, care_built, care_since, report_key, psi, psi_checked)
        VALUES (?,?,?,?,1,1,?,1,120,7,0,'up',?,?,?,?,?,?,?,?,?,?,'care-plus','custom',1,?,?,?,?)",
        ['Ridgeline Heating & Air website', $co, 'ridgelineair.example', 'demoRidgeline', $day(158), strtotime('-23 days'), time() - 7 * 60, 241,
         strtotime('+71 days'), 'Let’s Encrypt', time() - 3600, strtotime('+243 days'), strtotime('-3 days'), json_encode($scan), $scan['at'], $day(128), $tok(), json_encode($psi), $psi['at']]);
    $site = (int)$pdo->lastInsertId();
    foreach ([['Phone call', 'event', 'Phone call'], ['Quote form sent', 'event', 'Quote form sent'], ['Financing page visit', 'page', '/financing']] as $g)
        q('INSERT INTO site_goals (site_id, name, kind, match) VALUES (?,?,?,?)', array_merge([$site], $g));

    // Uptime checks every 15 minutes for 90 days, with one short outage 23 days ago.
    $ins = $pdo->prepare('INSERT INTO site_checks (site_id, ts, ok, code, ms) VALUES (?,?,?,?,?)');
    $out0 = strtotime('-23 days 03:10'); $out1 = $out0 + 25 * 60;
    for ($t = strtotime('-90 days') - (time() % 900); $t < time(); $t += 900) { $down = $t >= $out0 && $t < $out1; $ins->execute([$site, $t, $down ? 0 : 1, $down ? 503 : 200, $down ? 0 : mt_rand(170, 420)]); }
    q('INSERT INTO site_incidents (site_id, started, ended, cause) VALUES (?,?,?,?)', [$site, $out0, $out1, 'The hosting company restarted its server (503 errors)']);

    $log = function (int $ago, string $kind, string $title, string $details = '', int $min = 0, ?int $req = null) use ($site, $day) {
        q('INSERT INTO care_log (site_id, kind, title, details, minutes, request_id, shared, created_at) VALUES (?,?,?,?,?,?,1,?)', [$site, $kind, $title, $details, $min, $req, $day($ago, '15:20:00')]);
    };
    for ($w = 59; $w >= 0; $w -= 7) if ($w >= 3) $log($w - 3, 'backup', 'Weekly backup', 'Full site and form settings, kept off-site for 90 days.');
    foreach ([118, 88, 58, 28] as $a) $log($a, 'update', 'Monthly software and plugin updates', 'Everything updated and checked on phone and desktop.');
    $log(96, 'speed', 'Quarterly speed and search tune-up', 'Compressed the new photos and tidied the page titles. PageSpeed (phone) went from 88 to 93.');
    $log(6, 'speed', 'Quarterly speed and search tune-up', 'Switched images to a lighter format and added the new towns to the service area page. PageSpeed (phone) is now 96.');
    $log(23, 'fix', 'Site back up after a hosting restart', 'The host restarted its server at 3:10 AM. We were alerted, checked everything once it came back, and it’s been steady since.');
    $log(40, 'security', 'Security headers checked', 'All five browser security settings in place.');

    $req = function (int $ago, string $title, string $details, string $status, string $reply = '', int $min = 0) use ($site, $ct, $day, $log) {
        q('INSERT INTO care_requests (site_id, contact_id, title, details, status, reply, minutes, created_at, updated_at, done_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$site, $ct, $title, $details, $status, $reply, $min, $day($ago, '09:12:00'), $day(max(0, $ago - 1), '14:00:00'), $status === 'done' ? $day(max(0, $ago - 1), '14:00:00') : null]);
        $id = (int)db()->lastInsertId();
        if ($status === 'done') $log(max(0, $ago - 1), 'edit', $title, $reply, $min, $id);
    };
    $req(44, 'Add our new financing offer to the Financing page', '0% for 12 months on new systems, through the end of the year.', 'done', 'Added near the top of the Financing page, with the end date.', 20);
    $req(15, 'Update our holiday hours', 'Closed Thanksgiving Day. Emergency line stays open.', 'done', 'Updated on the Contact page and in the footer.', 15);
    $req(9, 'Add Marcus to the team photos', 'New technician, photo attached to the website project.', 'done', 'Added to the About section with his name and years of experience.', 20);
    $req(1, 'Swap the summer banner for a heating one', 'Something about furnace tune-ups now that it’s getting cold.', 'in_progress', 'On it. We’ll use the new furnace tune-up photo.');

    /* ---------- Visitor stats (90+ days of sample visits) ---------- */
    demo_seed_visits($site);

    /* ---------- Projects ---------- */
    $proj = function (string $name, string $status, int $progress, int $start, int $due, string $summary) use ($co, $ct, $day, $d) {
        q("INSERT INTO projects (name, company_id, contact_id, brand, status, progress, start_date, due_date, summary, shared, created_at, updated_at) VALUES (?,?,?,'Syndicate Marketing',?,?,?,?,?,1,?,?)",
            [$name, $co, $ct, $status, $progress, $d($start), $d($due), $summary, $day($start), $day(max(0, min($start, $due)))]);
        return (int)db()->lastInsertId();
    };
    $steps = function (int $pid, array $list) use ($d, $day) {
        foreach ($list as $i => [$title, $doneAgo, $dueAgo]) q('INSERT INTO project_steps (project_id, title, due_date, done_at, position) VALUES (?,?,?,?,?)', [$pid, $title, $d($dueAgo), $doneAgo === null ? null : $day($doneAgo, '16:00:00'), $i]);
    };
    $post = function (int $pid, int $ago, string $author, string $kind, string $body, string $approval = '', string $note = '', ?int $decidedAgo = null, string $time = '11:30:00') use ($ct, $day) {
        q('INSERT INTO project_posts (project_id, author, contact_id, kind, body, approval, decision_note, decided_by, decided_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$pid, $author, $author === 'client' ? $ct : null, $kind, $body, $approval, $note, $decidedAgo === null ? null : $ct, $decidedAgo === null ? null : $day($decidedAgo, '17:05:00'), $day($ago, $time)]);
        return (int)db()->lastInsertId();
    };
    $file = function (int $pid, ?int $postId, int $ago, string $author, string $src, string $name) use ($ct, $day) {
        $rel = $pid . '/' . bin2hex(random_bytes(16)); $to = DATA . '/portal/' . $rel;
        if (!is_dir(dirname($to))) @mkdir(dirname($to), 0700, true);
        if (is_file(__DIR__ . '/demo-files/' . $src)) copy(__DIR__ . '/demo-files/' . $src, $to); else file_put_contents($to, $src); // a file, or the text itself
        $type = function_exists('mime_content_type') ? (string)@mime_content_type($to) : '';
        q('INSERT INTO project_files (project_id, post_id, author, contact_id, name, type, size, path, created_at) VALUES (?,?,?,?,?,?,?,?,?)', [$pid, $postId, $author, $author === 'client' ? $ct : null, $name, $type, filesize($to), $rel, $day($ago)]);
    };

    // 1. The website (finished)
    $p1 = $proj('Starter website', 'done', 100, 156, 128, "A five-page site built around one action, calling: Home, Services, Financing, Service area and Contact.\nTap-to-call on every phone screen, a quote form, and calls and forms counted in Site Stats.");
    $steps($p1, [['Kickoff call', 154, 153], ['Sitemap and page outline approved', 150, 150], ['Logo, photos and text collected', 147, 146], ['Homepage design shared in your portal', 143, 143],
        ['Remaining pages built', 136, 136], ['Changes (2 rounds)', 132, 133], ['Launch on your domain', 129, 129], ['Site Stats and goals live', 129, 128]]);
    $post($p1, 155, 'staff', 'update', "Welcome to your portal, Dana! This is where we’ll share designs, ask for approvals and keep you posted. Thanks for the kickoff call. Here’s what we agreed:\n\n• Five pages: Home, Services, Financing, Service area, Contact\n• Calling is the main goal, then quote requests\n• Launch in about three weeks");
    $p = $post($p1, 151, 'staff', 'approval', 'Here’s the sitemap and what goes on each page. Does this look right?', 'approved', 'Looks great. Can we list Duncan and Lyman in the service area too?', 150);
    $file($p1, $p, 151, 'staff', "Ridgeline Heating & Air: sitemap and page outline\n\n1. Home: same-day service, tap to call, services, reviews section left out until you have real ones\n2. Services: AC repair, heating, new systems, tune-ups\n3. Financing: how it works, apply link\n4. Service area: a section for each town\n5. Contact: quote form, hours, map\n", 'Sitemap and page outline.txt');
    $p = $post($p1, 148, 'client', 'message', 'Here are our logo and a few photos of the trucks and the team.');
    $file($p1, $p, 148, 'client', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 60"><rect width="240" height="60" fill="#0f2a4a"/><text x="16" y="38" font-family="Arial" font-weight="700" font-size="22" fill="#fff">RIDGELINE</text><text x="150" y="38" font-family="Arial" font-size="14" fill="#ff7a1a">HEATING &amp; AIR</text></svg>', 'ridgeline-logo.svg');
    $p = $post($p1, 143, 'staff', 'approval', "The homepage design is ready, on desktop and phone. The orange call button stays on screen as people scroll on their phones. Approve it, or tell us what you’d change.", 'approved', 'Love it. Go ahead with the other pages.', 142);
    $file($p1, $p, 143, 'staff', 'homepage-design.jpg', 'Homepage design (desktop).jpg');
    $file($p1, $p, 143, 'staff', 'homepage-phone.jpg', 'Homepage design (phone).jpg');
    $post($p1, 136, 'staff', 'update', 'All five pages are built and ready for your first round of changes. Open the preview link we emailed and send any notes here.');
    $post($p1, 129, 'staff', 'update', "You’re live at ridgelineair.example! 🎉\n\nCalls, quote forms and visits to the Financing page are now counted in Site Stats. You can see them under “Your website” on your portal home.");
    $post($p1, 129, 'staff', 'status', 'Status changed to Complete', '', '', null, '16:30:00');

    // 2. Ads setup (finished)
    $p2 = $proj('Google & Meta ads', 'done', 100, 114, 95, "Two platforms: Google Search for people who need help now, and Meta (Facebook and Instagram) for seasonal tune-ups.\nAd spend is paid by you straight to Google and Meta. Results are in your ads portal.");
    $steps($p2, [['Kickoff call', 112, 112], ['Ad account access granted', 109, 108], ['Conversion tracking installed', 106, 105], ['Campaign plan approved', 102, 101], ['First ads live', 98, 98], ['First monthly report', 80, 80]]);
    $post($p2, 102, 'staff', 'approval', "Here’s the campaign plan:\n\n• Emergency AC repair (Google): about 55% of the budget, call ads 7 AM to 9 PM\n• System replacement (Google): about 25%, sends people to the quote form\n• Summer tune-up (Meta): about 20%, homeowners in your service area\n\nThe first ads are in your ads portal to approve.", 'approved', '', 101);
    $post($p2, 98, 'staff', 'update', 'Your first ads are live on Google and Meta. We’ll check them every weekday and send your first monthly report at the start of next month.');

    // 3. A new page, waiting on the client
    $p3 = $proj('Fall furnace tune-up page', 'review', 60, 12, -4, "A landing page for the fall furnace tune-up ads, so people who click land on exactly what they saw.\nExtra page and copywriting add-ons.");
    $steps($p3, [['Outline and offer agreed', 10, 10], ['Copy written', 7, 7], ['Design shared in your portal', 2, 2], ['Your approval', null, -1], ['Live on your site and linked from the ads', null, -4]]);
    $post($p3, 11, 'staff', 'update', "Quick plan for the new page: one clear offer ($89 furnace tune-up), what’s included, how fast you can come out, and two ways to book. We’ll point the fall Meta ads at it once it’s live.");
    $post($p3, 6, 'client', 'message', 'Can we mention that we service every brand of furnace? People ask us that a lot.', '', '', null, '08:41:00');
    $post($p3, 6, 'staff', 'update', 'Good idea. It’s in the “What we check” section now.', '', '', null, '10:02:00');
    $p = $post($p3, 2, 'staff', 'approval', "The draft of the furnace tune-up page is ready. Have a look, then approve it or tell us what to change. Once it’s approved we’ll put it live and switch the ads over the same day.", 'pending');
    $file($p3, $p, 2, 'staff', 'furnace-page-draft.jpg', 'Furnace tune-up page draft.jpg');

    /* ---------- Invoices and plans ---------- */
    $n = 1001;
    $inv = function (int $issueAgo, string $status, array $items, string $notes = '') use (&$n, $co, $ct, $day, $d, $tok) {
        q("INSERT INTO invoices (number, brand, company_id, contact_id, issue_date, due_date, status, tax_rate, notes, paid_at, paid_method, sent_at, share_token, created_at, updated_at) VALUES (?,?,?,?,?,?,?,0,?,?,?,?,?,?,?)",
            ['SYN-' . $n++, 'Syndicate Marketing', $co, $ct, $d($issueAgo), $d($issueAgo - 14), $status, $notes, $status === 'paid' ? $day($issueAgo) : null, $status === 'paid' ? 'Stripe' : '', $day($issueAgo), $tok(), $day($issueAgo), $day($issueAgo)]);
        $id = (int)db()->lastInsertId();
        foreach ($items as $i => [$desc, $qty, $unit]) q('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, position) VALUES (?,?,?,?,?)', [$id, $desc, $qty, $unit, $i]);
    };
    $inv(157, 'paid', [['Starter website', 1, $price('web-starter')]], 'Paid online at checkout. Thank you!');
    $inv(115, 'paid', [['Paid ads, two platforms (first month)', 1, $price('ads-two')], ['Ad account setup', 1, $price('add-ads-setup')], ['Care Plus (first month)', 1, $price('care-plus')]], 'Paid online at checkout. Monthly plans renew automatically.');
    foreach ([85, 54, 24] as $a) $inv($a, 'paid', [['Paid ads, two platforms (monthly)', 1, $price('ads-two')], ['Care Plus (monthly)', 1, $price('care-plus')]], 'Renewal, paid automatically.');
    $inv(3, 'sent', [['Extra page: Fall furnace tune-up', 1, $price('add-page')], ['Copywriting', 1, $price('add-copy')]]);

    q("INSERT INTO orders (ref, access_key, status, name, email, business, website, one_time, monthly, paid_total, live, stripe_subscription, sub_status, contact_id, company_id, site_id, source, created_at, paid_at, updated_at)
        VALUES ('SM-DEMO2', ?, 'paid', 'Dana Reyes', ?, 'Ridgeline Heating & Air', 'https://ridgelineair.example', ?, ?, ?, 0, 'sub_demo', 'active', ?, ?, ?, 'Marketing site — checkout', ?, ?, ?)",
        [$tok(), DEMO_EMAIL, $price('add-ads-setup'), $price('ads-two') + $price('care-plus'), $price('add-ads-setup') + $price('ads-two') + $price('care-plus'), $ct, $co, $site, $day(115), $day(115), $day(24)]);
    $order = (int)$pdo->lastInsertId();
    foreach ([['ads-two', 'ads', 'month'], ['care-plus', 'care', 'month'], ['add-ads-setup', 'addon', '']] as [$sku, $cat, $int])
        q('INSERT INTO order_items (order_id, sku, name, category, price, interval, quantity) VALUES (?,?,?,?,?,?,1)', [$order, $sku, (string)val('SELECT name FROM products WHERE sku = ?', [$sku]), $cat, $price($sku), $int]);
    q('UPDATE sites SET care_order_id = ? WHERE id = ?', [$order, $site]);

    /* ---------- Ads ---------- */
    demo_seed_ads($co, $site, $order);
    $pdo->commit();
}

// Sample campaigns. Dates are days ago (end null = still live); each one sends paid visits to the site with its UTM campaign.
function demo_campaigns(): array {
    return [
        ['google', 'Emergency AC repair', 'leads', 'ac-repair', 26.0, 104, null, 'People searching “ac repair near me” or “ac not cooling” within 25 miles, call ads 7 AM to 9 PM', '/'],
        ['google', 'System replacement', 'leads', 'hvac-replacement', 12.0, 104, null, 'Searches like “new ac unit cost” and “hvac replacement” in the service area', '/services'],
        ['meta', 'Summer AC tune-up', 'leads', 'summer-tune-up', 11.0, 104, 50, 'Homeowners aged 30–65 within 25 miles of Greer', '/services'],
        ['meta', 'Fall furnace tune-up', 'leads', 'fall-tune-up', 11.0, 28, null, 'Homeowners aged 30–65 within 25 miles of Greer, plus people who visited the site in the last 90 days', '/services'],
    ];
}

function demo_seed_visits(int $site): void {
    $adb = an_db();
    $adb->beginTransaction();
    $ins = $adb->prepare('INSERT INTO events (site_id, ts, day, hour, type, pid, visitor, session, entry, name, path, title, hostname, referrer, source, channel, medium, campaign, content, term, device, browser, os, screen, lang, country, region, city, duration, scroll, lcp, fcp, cls, inp, ttfb, props)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $titles = ['/' => 'Ridgeline Heating & Air · AC repair and heating in the Upstate', '/services' => 'Services · Ridgeline Heating & Air', '/financing' => 'Financing · Ridgeline Heating & Air',
        '/service-area' => 'Service area · Ridgeline Heating & Air', '/contact' => 'Contact · Ridgeline Heating & Air'];
    $pick = function (array $w) { $r = mt_rand(1, (int)array_sum($w)); foreach ($w as $k => $n) { $r -= $n; if ($r <= 0) return $k; } return array_key_first($w); };
    $organic = ['Google|Organic search|https://www.google.com/' => 40, 'Direct|Direct|' => 22, 'Google Business Profile|Local listing|' => 16, 'Facebook|Organic social|https://facebook.com/' => 6,
        'Nextdoor|Referral|https://nextdoor.com/' => 5, 'Bing|Organic search|https://www.bing.com/' => 4, 'ChatGPT|AI assistants|https://chatgpt.com/' => 2];
    $cities = ['Greenville' => 34, 'Spartanburg' => 22, 'Greer' => 20, 'Simpsonville' => 9, 'Taylors' => 7, 'Duncan' => 5, 'Lyman' => 3];
    $camps = demo_campaigns();
    for ($ago = 120; $ago >= 0; $ago--) {
        $date = strtotime("-$ago days 00:00");
        $wd = (int)date('w', $date);
        $season = 1 + 0.35 * cos(($ago - 75) / 40); // busiest in the late-summer heat, a second rise as it cools
        $n = (int)round((34 + mt_rand(-6, 6)) * $season * ($wd === 0 || $wd === 6 ? 0.72 : 1));
        $sessions = []; $pool = [];
        for ($i = 0; $i < $n; $i++) $sessions[] = ['src' => $pick($organic), 'camp' => null];
        foreach ($camps as [$plat, , , $utm, $budget, $from, $to]) {
            if ($ago > $from || ($to !== null && $ago < $to)) continue;
            $clicks = $plat === 'google' ? $budget / 4.6 : $budget / 1.15;
            for ($i = 0, $m = (int)round($clicks * 0.85 * (mt_rand(80, 120) / 100)); $i < $m; $i++) $sessions[] = ['src' => $plat === 'google' ? 'Google|Paid search|' : (mt_rand(0, 2) ? 'Facebook|Paid social|' : 'Instagram|Paid social|'), 'camp' => [$plat, $utm]];
        }
        foreach ($sessions as $s) {
            [$source, $channel, $ref] = explode('|', $s['src']);
            $mobile = mt_rand(1, 100) <= 68; $tablet = !$mobile && mt_rand(1, 100) <= 8;
            $device = $mobile ? 'Mobile' : ($tablet ? 'Tablet' : 'Desktop');
            [$browser, $os] = $mobile ? (mt_rand(1, 100) <= 58 ? ['Safari', 'iOS'] : ['Chrome', 'Android']) : (mt_rand(1, 100) <= 60 ? ['Chrome', 'Windows'] : ['Safari', 'macOS']);
            $hour = $pick([7 => 4, 8 => 7, 9 => 9, 10 => 9, 11 => 8, 12 => 8, 13 => 7, 14 => 7, 15 => 7, 16 => 7, 17 => 7, 18 => 6, 19 => 6, 20 => 5, 21 => 4, 22 => 2, 6 => 2]);
            $ts = $date + $hour * 3600 + mt_rand(0, 3500);
            if ($ts > time()) continue;
            $visitor = $pool && mt_rand(1, 100) <= 18 ? $pool[array_rand($pool)] : substr(hash('sha256', "v$ts" . mt_rand()), 0, 16); // some come back the same day
            $pool[] = $visitor; $session = substr(hash('sha256', "s$ts" . mt_rand()), 0, 16);
            $medium = $s['camp'] ? ($s['camp'][0] === 'google' ? 'cpc' : 'paid') : '';
            $campaign = $s['camp'] ? $s['camp'][1] : '';
            $land = $s['camp'] ? ($s['camp'][1] === 'ac-repair' ? '/' : '/services') : $pick(['/' => 62, '/services' => 18, '/financing' => 3, '/service-area' => 12, '/contact' => 5]);
            $path = [$land];
            for ($k = 0, $more = $pick([0 => 46, 1 => 30, 2 => 16, 3 => 8]); $k < $more; $k++) $path[] = $pick(['/services' => 38, '/contact' => 30, '/financing' => 8, '/service-area' => 16, '/' => 8]);
            $city = $pick($cities);
            $row = function (int $t, string $type, string $p, int $entry, string $name = '', string $props = '') use ($ins, $site, $visitor, $session, $source, $channel, $medium, $campaign, $ref, $device, $browser, $os, $mobile, $city, $titles) {
                $pv = $type === 'pageview';
                $ins->execute([$site, $t, date('Y-m-d', $t), (int)date('G', $t), $type, $pv ? substr(md5($t . $p . mt_rand()), 0, 12) : '', $visitor, $session, $entry, $name, $p, $titles[$p] ?? '', 'ridgelineair.example',
                    $entry ? $ref : '', $source, $channel, $medium, $campaign, '', '', $device, $browser, $os, $mobile ? 390 : 1440, 'en-us', 'US', 'South Carolina', $city,
                    $pv ? mt_rand(8, 140) : 0, $pv ? mt_rand(30, 100) : 0, $pv ? mt_rand($mobile ? 1300 : 900, $mobile ? 2900 : 2100) : null, $pv ? mt_rand(700, 1500) : null, $pv ? mt_rand(0, 60) : null,
                    $pv ? mt_rand(40, 220) : null, $pv ? mt_rand(120, 420) : null, $props]);
            };
            $t = $ts;
            foreach ($path as $k => $p) { $row($t, 'pageview', $p, $k === 0 ? 1 : 0); $t += mt_rand(15, 120); }
            // Goals: calls, mostly on phones; quote forms. Paid search visitors call most.
            $callRate = $s['camp'] ? ($s['camp'][0] === 'google' ? 8 : 3) : ($channel === 'Local listing' ? 8 : 3);
            if ($mobile && mt_rand(1, 100) <= $callRate) $row($t, 'event', end($path), 0, 'Phone call', '{"from":"' . (end($path) === '/' ? 'Header button' : 'Sticky call button') . '"}');
            elseif (mt_rand(1, 100) <= ($s['camp'] ? 3 : 2)) $row($t, 'event', '/contact', 0, 'Quote form sent', '{"service":"' . ['AC repair', 'New system', 'Tune-up', 'Heating repair'][mt_rand(0, 3)] . '"}');
        }
    }
    $adb->commit();
}

function demo_seed_ads(int $co, int $site, int $order): void {
    $day = function (int $ago, string $time = '10:00:00'): string { return date('Y-m-d', strtotime("-$ago days")) . ' ' . $time; };
    $d = function (int $ago): string { return date('Y-m-d', strtotime("-$ago days")); };
    $steps = []; foreach (array_keys(AD_STEPS) as $i => $k) $steps[$k] = $d(112 - $i * 3);
    q("INSERT INTO ad_clients (company_id, status, sku, platforms, order_id, site_id, monthly_budget, target_cpl, goal, area, onboarding, report_key, since, created_at, updated_at)
        VALUES (?, 'active', 'ads-two', 'google,meta', ?, ?, 1500, 45, ?, ?, ?, ?, ?, ?, ?)",
        [$co, $order, $site, 'Phone calls and quote requests', 'Greenville, Spartanburg and Greer, SC (about 25 miles)', json_encode($steps), bin2hex(random_bytes(16)), $d(104), $day(115), $day(1)]);
    $cl = (int)db()->lastInsertId();
    foreach ([['google', 'Ridgeline Heating & Air', '000-555-0142'], ['meta', 'Ridgeline Heating & Air', '']] as [$pl, $nm, $ext])
        q("INSERT INTO ad_accounts (client_id, platform, name, external_id, access, pixel, conversions, created_at) VALUES (?,?,?,?, 'granted', 1, 1, ?)", [$cl, $pl, $nm, $ext, $day(110)]);

    $ids = [];
    $st = db()->prepare("INSERT INTO ad_stats (campaign_id, day, impressions, clicks, spend, conversions, value, source) VALUES (?,?,?,?,?,?,0,'import')");
    foreach (demo_campaigns() as [$plat, $name, $obj, $utm, $budget, $from, $to, $aud, $land]) {
        q('INSERT INTO ad_campaigns (client_id, platform, name, objective, status, daily_budget, start_date, end_date, landing_url, utm_campaign, audience, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$cl, $plat, $name, $obj, $to === null ? 'live' : 'ended', $budget, $d($from), $to === null ? null : $d($to), 'https://ridgelineair.example' . $land, $utm, $aud, $day($from + 2), $day($to ?? 1)]);
        $ids[$utm] = $cid = (int)db()->lastInsertId();
        for ($ago = $from; $ago >= max(1, $to ?? 1); $ago--) {
            $spend = round($budget * mt_rand(86, 106) / 100, 2);
            $clicks = (int)round($spend / ($plat === 'google' ? mt_rand(400, 520) / 100 : mt_rand(95, 135) / 100));
            $imp = (int)round($clicks / ($plat === 'google' ? mt_rand(55, 75) / 1000 : mt_rand(10, 15) / 1000));
            $st->execute([$cid, $d($ago), $imp, $clicks, $spend, floor($clicks * ($plat === 'google' ? 0.09 : 0.035) + mt_rand(0, 99) / 100)]);
        }
    }
    $cr = function (?string $utm, string $plat, string $format, string $title, string $headline, string $text, string $cta, string $file, string $status, int $ago, string $note = '', ?int $decided = null) use ($cl, $ids, $day) {
        $name = '';
        if ($file !== '') { $name = 'demo-' . bin2hex(random_bytes(8)) . '.jpg'; copy(__DIR__ . '/demo-files/' . $file, ads_storage() . '/' . $name); }
        q('INSERT INTO ad_creatives (client_id, campaign_id, platform, format, title, headline, primary_text, cta, url, file, status, client_note, decided_by, decided_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$cl, $utm ? $ids[$utm] : null, $plat, $format, $title, $headline, $text, $cta, 'https://ridgelineair.example/', $name, $status, $note, $decided === null ? '' : 'Dana Reyes', $decided === null ? null : $day($decided), $day($ago), $day($decided ?? $ago)]);
    };
    $cr('ac-repair', 'google', 'text', 'AC repair search ad', 'AC Repair Today | Ridgeline Heating & Air', 'Local, licensed technicians. Call now and we’ll be there today. Repairs, tune-ups and new systems with financing available.', 'Call', '', 'live', 103, '', 102);
    $cr('summer-tune-up', 'meta', 'image', 'AC out? We’ll be there today', 'Same-day AC repair', 'Don’t sweat it out. Local technicians across the Upstate, with upfront pricing before we start.', 'Call now', 'ad-ac-repair.jpg', 'retired', 103, 'Looks good!', 102);
    $cr('fall-tune-up', 'meta', 'image', 'Furnace tune-up, $89', 'Book your tune-up in 60 seconds', 'Be ready before the first cold night. 21-point furnace check and carbon monoxide test, with same-week visits.', 'Book now', 'ad-furnace.jpg', 'live', 31, '', 30);
    $cr('fall-tune-up', 'meta', 'image', 'Heat pump blowing cold air?', 'Same-day heating repair', 'Heat pump blowing cold air? Our technicians can usually come out the same day, with upfront pricing before we start.', 'Get help today', 'ad-heat-pump.jpg', 'review', 1);
    $cr('ac-repair', 'google', 'text', 'Furnace repair search ad', 'Furnace Repair Today | Ridgeline Heating & Air', 'No heat? Local, licensed technicians across the Upstate. Same-day service and upfront pricing. Call now.', 'Call', '', 'review', 1);

    $log = function (int $ago, string $kind, string $title, string $details = '') use ($cl, $day) { q('INSERT INTO ad_log (client_id, kind, title, details, shared, created_at) VALUES (?,?,?,?,1,?)', [$cl, $kind, $title, $details, $day($ago, '14:30:00')]); };
    $log(104, 'launch', 'Launched Emergency AC repair, System replacement and Summer AC tune-up');
    $log(90, 'optimize', 'Added 38 negative keywords', 'Stopped ads showing for DIY and job searches like “how to fix ac yourself” and “hvac jobs”.');
    $log(76, 'targeting', 'Call ads limited to 7 AM–9 PM', 'Calls outside those hours rarely booked, so that money now goes to daytime.');
    $log(50, 'budget', 'Ended Summer AC tune-up for the season', 'Moved its budget into the AC repair campaign until the fall campaign starts.');
    $log(28, 'launch', 'Launched Fall furnace tune-up on Facebook and Instagram');
    $log(14, 'creative', 'New furnace tune-up ad is live', 'Square image for the feed, same offer as the landing page draft.');
    $log(4, 'optimize', 'Shifted 15% of AC budget toward heating searches', 'Searches for furnace and heat pump repair are climbing as nights get colder.');
    $log(1, 'creative', 'Two new ads sent for your approval');
    $last = date('Y-m', strtotime('first day of last month'));
    q('INSERT INTO ad_months (client_id, month, summary, next_steps, sent_at) VALUES (?,?,?,?,?)', [$cl, $last,
        "September was steady: calls from search ads held up as the heat broke, and cost per lead stayed under your $45 target. The fall furnace campaign went live late in the month and its first week looks good.",
        "Shift budget from AC repair toward heating searches as it gets colder, launch the furnace tune-up landing page, and test a second fall ad.", date('Y-m-02 09:00:00')]);
    foreach ([[9, 'staff', 'Grady', 'Quick heads-up: we’re seeing more “furnace repair” searches this week. Want us to start a small heating campaign now, or wait for the landing page?'],
        [9, 'client', 'Dana Reyes', 'Start now, the phones are already picking up for heating calls.'],
        [8, 'staff', 'Grady', 'Done. It’s running under the AC repair campaign for now, and we’ll split it out once the new page is live.']] as $i => [$ago, $who, $name, $body])
        q('INSERT INTO ad_messages (client_id, author, name, body, seen_at, created_at) VALUES (?,?,?,?,?,?)', [$cl, $who, $name, $body, $day($ago, '17:00:00'), $day($ago, ['10:15:00', '11:02:00', '13:40:00'][$i])]);
}
