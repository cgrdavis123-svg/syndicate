<?php
// The client's setup checklist, with plain steps for giving us access to each ad account.
require __DIR__ . '/../app/ads-boot.php';
[$c, $a] = ads_require_client();
$id = (int)$a['id'];
$steps = ads_onboarding($a); $acc = all('SELECT * FROM ad_accounts WHERE client_id = ? ORDER BY platform', [$id]);
$email = setting('ads_access_email') ?: setting('business_email');
$how = [
    'google' => ['Google Ads', setting('ads_google_manager') !== ''
        ? ['Sign in to Google Ads and copy your 10-digit Customer ID (top right, like 123-456-7890).', 'Send it to us in a message below. We’ll send a link request from our manager account (' . setting('ads_google_manager') . ').', 'In Google Ads, open Admin → Access and security → Managers, and accept our request.']
        : ['Sign in to Google Ads and open Admin → Access and security.', 'Press +, enter ' . $email . ', choose Standard access and send the invite.', 'Tell us when it’s sent and we’ll accept it.']],
    'meta' => ['Meta (Facebook & Instagram)', setting('ads_meta_business') !== ''
        ? ['Open Meta Business Settings (business.facebook.com/settings).', 'Go to Users → Partners → Add → Give a partner access to your assets, and enter our Business ID: ' . setting('ads_meta_business') . '.', 'Share your ad account, Facebook Page and pixel (dataset) with us, then let us know.']
        : ['Open Meta Business Settings (business.facebook.com/settings).', 'Go to Users → People → Add, enter ' . $email . ', and give access to your ad account, Facebook Page and pixel (dataset).', 'Tell us when it’s sent and we’ll accept it.']],
    'tiktok' => ['TikTok', setting('ads_tiktok_bc') !== ''
        ? ['Sign in to TikTok Business Center.', 'Go to Users → Partners → Add partner, and enter our Business Center ID: ' . setting('ads_tiktok_bc') . '.', 'Share your ad account (and pixel) with us as a partner, then let us know.']
        : ['Sign in to TikTok Business Center.', 'Go to Users → Members → Invite member, enter ' . $email . ', and give access to your ad account.', 'Tell us when it’s sent and we’ll accept it.']],
];
portal_top('Setup', $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal.php">← Your ads</a><h1>Getting set up</h1><p class="muted"><?= count(array_filter($steps, function ($s) { return $s['done']; })) ?> of <?= count($steps) ?> steps done. We tick these off as we go.</p></div></div>
<section class="card"><ul class="ad-steps"><?php foreach ($steps as $s): ?><li class="<?= $s['done'] ? 'done' : '' ?>"><span class="ad-check" aria-hidden="true"></span><span><b><?= e($s['title']) ?></b><small class="muted"><?= e($s['text']) ?></small></span><span class="sr-only"><?= $s['done'] ? 'Done' : 'Not done yet' ?></span></li><?php endforeach; ?></ul></section>
<?php if ($acc): ?><h2 class="h-sec" style="margin-top:8px">Give us access to your ad accounts</h2>
<p class="muted">You keep ownership of your accounts and your ad spend is billed to you by the platforms. Adding us as a partner or user means you can remove our access any time.</p>
<div class="grid-2"><?php foreach ($acc as $x): [$n, $list] = $how[$x['platform']] ?? [ads_platform($x['platform']), []]; ?>
  <section class="card"><div class="card-h"><h2><?= e($n) ?></h2><span class="tag <?= $x['access'] === 'granted' ? 'lime' : '' ?>"><?= $x['access'] === 'granted' ? 'Access granted' : ($x['access'] === 'create' ? 'We’re setting it up' : 'Waiting for access') ?></span></div>
    <?php if ($x['access'] === 'granted'): ?><p class="muted small">We have access<?= $x['name'] !== '' ? ' to “' . e($x['name']) . '”' : '' ?>. Tracking: <?= $x['pixel'] && $x['conversions'] ? 'installed and checked' : 'we’re setting it up' ?>.</p>
    <?php elseif ($x['access'] === 'create'): ?><p class="muted small">You asked us to create this account. We’ll set it up in your business’s name and send you an invite so you own it.</p>
    <?php else: ?><ol class="ad-howto"><?php foreach ($list as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ol><p class="muted small">Stuck? <a href="portal.php#messages">Message us</a> and we’ll walk you through it on a quick call.</p><?php endif; ?>
  </section><?php endforeach; ?></div><?php endif; ?>
<?php portal_bottom(); ?>
