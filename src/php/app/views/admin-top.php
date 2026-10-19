<?php
// Admin layout (top). Variables: $title, $active, $admin
$GLOBALS['ADMIN_SHELL'] = (bool)$admin;
$unread = $admin ? (int)db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn() : 0;
$newApps = $admin ? (int)db()->query("SELECT COUNT(*) FROM applications WHERE status = 'new'")->fetchColumn() : 0;
$nav = [
    'dashboard' => ['admin/', 'Dashboard', '<path d="M3 3h6v8H3zM11 3h6v5h-6zM11 10h6v7h-6zM3 13h6v4H3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'],
    'applications' => ['admin/applications.php', 'Applicants', '<circle cx="10" cy="7" r="3.2" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 17c.8-3 3.3-4.6 6.5-4.6s5.7 1.6 6.5 4.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>', $newApps],
    'jobs' => ['admin/jobs.php', 'Jobs', '<rect x="3" y="6" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7.5 6V4.5A1.5 1.5 0 0 1 9 3h2a1.5 1.5 0 0 1 1.5 1.5V6" stroke="currentColor" stroke-width="1.5"/>'],
    'messages' => ['admin/messages.php', 'Messages', '<path d="M3 5h14v9H8l-4 3v-3H3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>', $unread],
    'account' => ['admin/account.php', 'Account', '<circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M10 2.5v2M10 15.5v2M2.5 10h2M15.5 10h2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<base href="<?= e(BASE) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Syndicate Admin</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preload" href="assets/fonts/inter-tight-normal-latin-1bf6b3.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/site.css">
</head>
<body class="admin">
<?php if ($admin): ?>
<div class="adm">
  <aside class="adm-side">
    <a href="admin/" class="brand">
      <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg>
      <span class="brand-name">Syndicate<small>Hiring HQ</small></span>
    </a>
    <nav class="adm-nav" aria-label="Admin">
      <?php foreach ($nav as $key => $n): ?>
        <a href="<?= e($n[0]) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><?= $n[2] ?></svg><?= e($n[1]) ?>
          <?php if (!empty($n[3])): ?><span class="badge"><?= (int)$n[3] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="adm-foot">
      <a href="careers.php" target="_blank" rel="noopener">View careers site ↗</a>
      <span title="<?= e($admin['email']) ?>"><?= e($admin['email']) ?></span>
      <a href="admin/logout.php?t=<?= e(csrf_token()) ?>">Sign out</a>
    </div>
  </aside>
  <main class="adm-main" id="main">
    <?php if ($m = flash()): ?><div class="toast" role="status"><?= e($m) ?></div><?php endif; ?>
<?php else: ?>
<main class="adm-auth" id="main">
<?php endif; ?>
