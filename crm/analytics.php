<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
$user = require_user();
$sites = all('SELECT s.*, co.name company FROM sites s LEFT JOIN companies co ON co.id = s.company_id ORDER BY co.name, s.name');
layout_top('Site stats', 'analytics', $user);
?>
<div class="page-head">
  <div><p class="eyebrow">Cookie-free analytics for your clients’ websites</p><h1>Site stats</h1></div>
  <a class="btn primary sm" href="site-edit.php">+ Add a website</a>
</div>
<?php if (!$sites): ?>
  <div class="card an-empty"><h2>Track a client’s website</h2><p class="muted">Add the site, paste one line of code into its header, and you’ll see visitors, where they came from, which pages work, calls and form sends, and site speed. Clients can see it in their portal. No cookies and no cookie banner needed.</p><a class="btn primary sm" href="site-edit.php">+ Add a website</a></div>
<?php else: ?>
<div class="proj-grid">
  <?php foreach ($sites as $s): $d = an_daily((int)$s['id'], 30); $sum = array_sum($d); $prev = array_sum(array_slice(an_daily((int)$s['id'], 60), 0, 30)); $live = an_live((int)$s['id']); ?>
    <a class="card proj-card site-card" href="site.php?id=<?= (int)$s['id'] ?>">
      <div class="row between"><span class="row"><span class="dot <?= $live['last'] > time() - 172800 ? 'ok' : 'off' ?>"></span><b class="proj-name"><?= e($s['name']) ?></b></span><?php if ($live['now']): ?><span class="tag lime"><?= $live['now'] ?> online</span><?php endif; ?></div>
      <small class="muted"><?= e(implode(', ', site_domains($s))) ?><?= $s['company'] ? ' · ' . e($s['company']) : '' ?></small>
      <?= sparkline($d, 300, 46) ?>
      <div class="row between small"><span><b class="num"><?= an_num($sum) ?></b> <span class="muted">visitors, 30 days</span> <?= an_delta($sum, $prev) ?></span><span class="muted"><?= $live['last'] ? 'Last visit ' . e(ago(date('Y-m-d H:i:s', $live['last']))) : 'Waiting for the first visit' ?></span></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_bottom(); ?>
