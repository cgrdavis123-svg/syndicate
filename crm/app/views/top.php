<?php
// Variables: $title, $active, $user
$GLOBALS['SHELL'] = (bool)$user;
$counts = $user ? [
    'tasks' => (int)val("SELECT COUNT(*) FROM tasks WHERE done_at IS NULL AND due_date IS NOT NULL AND due_date <= ?", [today()]),
    'deals' => (int)val("SELECT COUNT(*) FROM deals WHERE stage = 'new'"),
    'invoices' => (int)val("SELECT COUNT(*) FROM invoices WHERE status = 'sent' AND due_date < ?", [today()]),
    'projects' => (int)val("SELECT COUNT(*) FROM project_posts pp WHERE pp.author = 'client' AND pp.id > COALESCE((SELECT MAX(s.id) FROM project_posts s WHERE s.project_id = pp.project_id AND s.author = 'staff'), 0)"),
    'inbox' => (int)val("SELECT COUNT(DISTINCT thread_id) FROM emails WHERE folder = 'inbox' AND seen = 0 AND archived = 0"),
    'store' => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid' AND seen_at IS NULL"),
    'bookings' => (int)val("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND seen_at IS NULL"),
    'care' => (int)val("SELECT COUNT(*) FROM sites WHERE monitor = 1 AND status = 'down'") + (int)val("SELECT COUNT(*) FROM care_requests WHERE status = 'open'"),
] : [];
$mailDue = !defined('ADS_APP') && !defined('WIKI_APP') && $user && val('SELECT 1 FROM mailboxes LIMIT 1') && (setting('mail_last_sync') === '' || strtotime(setting('mail_last_sync')) < time() - 120);
$nav = [
    'dashboard' => ['./', 'Dashboard', '<path d="M3 3h6v8H3zM11 3h6v5h-6zM11 10h6v7h-6zM3 13h6v4H3z"/>'],
    'inbox' => ['inbox.php', 'Inbox', '<path d="M2.5 11.5 5 4h10l2.5 7.5V16h-15z"/><path d="M2.5 11.5h4l1 2h5l1-2h4"/>', $counts['inbox'] ?? 0],
    'deals' => ['deals.php', 'Pipeline', '<path d="M3 4h4v12H3zM8 4h4v8H8zM13 4h4v5h-4z"/>', $counts['deals'] ?? 0],
    'projects' => ['projects.php', 'Projects', '<path d="M3 6.5h14v10H3z"/><path d="M7 6.5V4h6v2.5M3 10.5h14"/>', $counts['projects'] ?? 0],
    'store' => ['store.php', 'Store', '<path d="M3.5 7.5h13l-1 9.5h-11z"/><path d="M7 7.5V6a3 3 0 0 1 6 0v1.5"/>', $counts['store'] ?? 0],
    'care' => ['care.php', 'Care', '<path d="M10 17s-6.5-3.8-6.5-8.6A3.6 3.6 0 0 1 10 6.2a3.6 3.6 0 0 1 6.5 2.2C16.5 13.2 10 17 10 17z"/><path d="M5.5 10h2l1.2-2 2 4 1.3-2h2.5"/>', $counts['care'] ?? 0],
    'wiki' => [wiki_url('team.php'), 'Wiki ↗', '<path d="M4 3.5h9l3 3v10H4z"/><path d="M13 3.5v3h3M7 10h6M7 13h4"/>'],
    'ads' => [ads_url(), 'Ads ↗', '<path d="M3 8v4l9 4V4zM12 6.5h2.5a2.5 2.5 0 0 1 0 5H12M5 12l1 5h2"/>'],
    'analytics' => ['analytics.php', 'Site stats', '<path d="M3 17h14"/><path d="M5 14V9M9 14V5M13 14v-3M17 14V7"/>'],
    'companies' => ['companies.php', 'Companies', '<path d="M3 17V5l6-2v14M9 7h8v10M5 7h2M5 10h2M5 13h2M12 10h2M12 13h2"/>'],
    'contacts' => ['contacts.php', 'Contacts', '<circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c.8-3 3.3-4.6 6.5-4.6s5.7 1.6 6.5 4.6"/>'],
    'tasks' => ['tasks.php', 'Tasks', '<path d="M4 10l3 3 6-7"/><rect x="2.5" y="2.5" width="15" height="15" rx="3"/>', $counts['tasks'] ?? 0],
    'bookings' => ['bookings.php', 'Bookings', '<rect x="3" y="4" width="14" height="13" rx="2"/><path d="M3 8h14M7 2.5v3M13 2.5v3M7 11.5h2M11 11.5h2M7 14h2"/>', $counts['bookings'] ?? 0],
    'consultations' => ['consultations.php', 'Consultations', '<circle cx="10" cy="10" r="7.5"/><path d="M10 6v4l3 2"/>'],
    'quotes' => ['quotes.php', 'Quotes', '<path d="M5 2.5h7l3 3v12H5z"/><path d="M12 2.5v3h3M8 9h4M8 12h4M8 15h2"/>'],
    'invoices' => ['invoices.php', 'Invoices', '<path d="M5 2.5h10v15l-2.5-1.5L10 17.5 7.5 16 5 17.5z"/><path d="M8 7h4M8 10h4"/>', $counts['invoices'] ?? 0],
    'settings' => ['settings.php', 'Settings', '<circle cx="10" cy="10" r="2.5"/><path d="M10 2.5v2M10 15.5v2M2.5 10h2M15.5 10h2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4"/>'],
];
if (defined('ADS_APP') && $user) {
    // ads.stusyn.com uses the same shell with its own menu.
    $adCounts = ['msgs' => (int)val("SELECT COUNT(*) FROM ad_messages WHERE author = 'client' AND seen_at IS NULL"), 'changes' => (int)val("SELECT COUNT(*) FROM ad_creatives WHERE status = 'changes'")];
    $nav = [
        'dashboard' => ['./', 'Overview', '<path d="M3 3h6v8H3zM11 3h6v5h-6zM11 10h6v7h-6zM3 13h6v4H3z"/>', $adCounts['msgs']],
        'creatives' => ['creatives.php', 'Ads & approvals', '<rect x="2.5" y="4" width="15" height="12" rx="2"/><path d="m2.5 13 4-4 3 3 2.5-2.5 5 5"/><circle cx="13" cy="7.5" r="1.2"/>', $adCounts['changes']],
        'campaigns' => ['campaigns.php', 'Campaigns', '<path d="M3 8v4l9 4V4zM12 6.5h2.5a2.5 2.5 0 0 1 0 5H12M5 12l1 5h2"/>'],
        'import' => ['import.php', 'Import results', '<path d="M10 3v10M6 9l4 4 4-4M3.5 16.5h13"/>'],
        'settings' => ['settings.php', 'Access details', '<circle cx="7" cy="10" r="3.5"/><path d="M10.5 10H17.5M15 10v3M17.5 10v2"/>'],
        'crm' => [rtrim((string)config('app_url'), '/') . '/', 'Back to CRM ↗', '<path d="M8 4H4v12h12v-4M11 3h6v6M17 3l-8 8"/>'],
    ];
}
if (defined('WIKI_APP') && $user) {
    $wq = (int)val('SELECT COUNT(*) FROM wiki_questions WHERE answered_at IS NULL');
    $nav = [
        'team' => ['team.php', 'Team docs', '<path d="M4 3.5h9l3 3v10H4z"/><path d="M13 3.5v3h3M7 10h6M7 13h4"/>'],
        'help' => ['./', 'Help center', '<circle cx="10" cy="10" r="7"/><path d="M8 8a2 2 0 1 1 2.8 1.8c-.5.3-.8.7-.8 1.2v.5M10 14h.01"/>'],
        'articles' => ['articles.php', 'All articles', '<path d="M4 5h12M4 10h12M4 15h8"/>'],
        'insights' => ['insights.php', 'Questions & insights', '<path d="M3 17h14M5 14V9M9 14V5M13 14v-3M17 14V7"/>', $wq],
        'crm' => [rtrim((string)config('app_url'), '/') . '/', 'Back to CRM ↗', '<path d="M8 4H4v12h12v-4M11 3h6v6M17 3l-8 8"/>'],
    ];
}
$appName = defined('ADS_APP') ? 'Syndicate Ads' : (defined('WIKI_APP') ? 'Syndicate Wiki' : 'Syndicate CRM');
$mark = '<svg class="mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg>';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<base href="<?= e(BASE) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07080a">
<?php if ($user): ?><meta name="csrf" content="<?= e(csrf_token()) ?>"><?php endif; ?>
<?php if (!empty($mailDue)): ?><meta name="mail-due" content="1"><?php endif; ?>
<title><?= e($title) ?> · <?= e($appName) ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preload" href="assets/fonts/inter-tight-normal-latin-1bf6b3.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/crm.css">
</head>
<body>
<?php if ($user): ?>
<div class="app">
  <aside class="side" id="side">
    <a class="logo" href="./"><?= $mark ?><span>Syndicate<small><?= defined('ADS_APP') ? 'ADS' : (defined('WIKI_APP') ? 'WIKI' : 'CRM') ?></small></span></a>
    <nav class="nav" aria-label="Main">
      <?php foreach ($nav as $k => $n): ?>
        <a href="<?= e($n[0]) ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $n[2] ?></svg><?= e($n[1]) ?><?php if (!empty($n[3])): ?><span class="badge"><?= (int)$n[3] ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="https://stusyn.com" target="_blank" rel="noopener">stusyn.com ↗</a>
      <span><?= e($user['name']) ?></span>
      <a href="logout.php?t=<?= e(csrf_token()) ?>">Sign out</a>
    </div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="icon-btn menu" type="button" id="menuBtn" aria-label="Open menu"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 6h14M3 14h14"/></svg></button>
      <?php if (!defined('ADS_APP') && !defined('WIKI_APP')): ?>
      <form class="search" action="search.php" method="get" role="search">
        <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.6"/><path d="m11 11 3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <input name="q" id="searchInput" placeholder="Search people, companies, deals, email…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search" autocomplete="off"><kbd>/</kbd>
      </form>
      <div class="quick">
        <button class="btn primary sm" type="button" id="quickBtn" aria-haspopup="true" aria-expanded="false">+ New</button>
        <div class="quick-menu" id="quickMenu" hidden>
          <a href="compose.php">Email</a><a href="deal-edit.php">Deal</a><a href="project-edit.php">Project</a><a href="contact-edit.php">Contact</a><a href="company-edit.php">Company</a>
          <a href="tasks.php#new">Task</a><a href="consultation-edit.php">Consultation</a><a href="quote-edit.php">Quote</a><a href="invoice-edit.php">Invoice</a>
        </div>
      </div>
      <?php else: ?><div class="grow"></div><?= defined('ADS_APP') ? '<a class="btn primary sm" href="campaign.php">+ New campaign</a>' : '<a class="btn primary sm" href="edit.php">+ New article</a>' ?><?php endif; ?>
    </header>
    <main class="content" id="main">
      <?php if ($m = flash()): ?><div class="toast" role="status"><?= e($m) ?></div><?php endif; ?>
<?php else: ?>
<main class="auth" id="main">
<?php endif; ?>
