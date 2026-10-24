<?php
// Team portal layout (top). Variables: $title, $member (null on sign-in pages)
$GLOBALS['ADMIN_SHELL'] = (bool)$member;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<base href="<?= e(BASE) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Syndicate Team</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preload" href="assets/fonts/inter-tight-normal-latin-1bf6b3.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/site.css">
</head>
<body class="admin team">
<a class="skip" href="#main">Skip to content</a>
<?php if ($member): ?>
<div class="adm">
  <aside class="adm-side">
    <a href="team/" class="brand">
      <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg>
      <span class="brand-name">Syndicate<small>Team</small></span>
    </a>
    <nav class="adm-nav" aria-label="Team portal">
      <a href="team/#checklist"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 5.5l1.5 1.5L8 4.5M4 11l1.5 1.5L8 10M10.5 6h6M10.5 11.5h6M10.5 16h6M4.5 16h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Checklist</a>
      <a href="team/#docs"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 2.5h6.5L15.5 6.5V17.5H5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M11.5 2.5v4h4M7.5 10.5h5M7.5 13.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Documents</a>
      <a href="team/#tools"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 12l4-4M7 9.5L5.5 11a2.5 2.5 0 0 0 3.5 3.5l1.5-1.5M13 10.5l1.5-1.5A2.5 2.5 0 0 0 11 5.5L9.5 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Links &amp; tools</a>
      <a href="team/#help"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.5"/><path d="M8 8a2 2 0 1 1 2.8 1.8c-.5.3-.8.7-.8 1.2v.5M10 14v.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Help</a>
    </nav>
    <div class="adm-foot">
      <span title="<?= e($member['email']) ?>"><?= e($member['name']) ?></span>
      <a href="team/logout.php?t=<?= e(csrf_token()) ?>">Sign out</a>
    </div>
  </aside>
  <main class="adm-main" id="main">
    <?php if ($m = flash()): ?><div class="toast" role="status"><?= e($m) ?></div><?php endif; ?>
<?php else: ?>
<main class="adm-auth" id="main">
<?php endif; ?>
