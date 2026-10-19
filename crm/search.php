<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$q = get('q'); $like = "%$q%";
$res = ['contacts' => [], 'companies' => [], 'deals' => [], 'invoices' => [], 'emails' => [], 'projects' => []];
if (mb_strlen($q) >= 2) {
    $res['contacts'] = all("SELECT c.*, co.name company FROM contacts c LEFT JOIN companies co ON co.id = c.company_id WHERE c.first_name LIKE ? OR c.last_name LIKE ? OR (c.first_name || ' ' || c.last_name) LIKE ? OR c.email LIKE ? OR c.phone LIKE ? LIMIT 20", array_fill(0, 5, $like));
    $res['companies'] = all('SELECT * FROM companies WHERE name LIKE ? OR website LIKE ? OR industry LIKE ? OR city LIKE ? LIMIT 20', array_fill(0, 4, $like));
    $res['deals'] = all('SELECT * FROM deals WHERE title LIKE ? OR notes LIKE ? OR source LIKE ? LIMIT 20', array_fill(0, 3, $like));
    $res['projects'] = all('SELECT p.*, co.name company FROM projects p LEFT JOIN companies co ON co.id = p.company_id WHERE p.name LIKE ? OR p.summary LIKE ? OR co.name LIKE ? LIMIT 20', array_fill(0, 3, $like));
    $res['emails'] = all('SELECT thread_id, MAX(sent_at) last_at, subject, from_name, from_addr FROM emails WHERE subject LIKE ? OR from_name LIKE ? OR addrs LIKE ? OR body_text LIKE ? GROUP BY thread_id ORDER BY last_at DESC LIMIT 20', array_fill(0, 4, $like));
    $res['invoices'] = all('SELECT i.*, co.name company FROM invoices i LEFT JOIN companies co ON co.id = i.company_id WHERE i.number LIKE ? OR co.name LIKE ? LIMIT 20', [$like, $like]);
}
$total = array_sum(array_map('count', $res));
layout_top('Search', '', $user);
?>
<div class="page-head"><div><p class="eyebrow"><?= mb_strlen($q) >= 2 ? $total . ' result' . ($total === 1 ? '' : 's') : 'Search' ?></p><h1><?= $q !== '' ? '“' . e($q) . '”' : 'Search' ?></h1></div></div>
<?php if (mb_strlen($q) < 2): ?><div class="card"><p class="empty">Type at least two letters in the search bar. Press <kbd>/</kbd> from anywhere to jump to it.</p></div>
<?php elseif (!$total): ?><div class="card"><p class="empty">Nothing matches “<?= e($q) ?>”. <a href="contact-edit.php">Add a contact</a> or <a href="deal-edit.php">a deal</a>.</p></div>
<?php else: ?><div class="grid-2">
  <?php if ($res['contacts']): ?><section class="card"><div class="card-h"><h2>Contacts</h2></div><ul class="list"><?php foreach ($res['contacts'] as $r): ?><li><a href="contact.php?id=<?= (int)$r['id'] ?>"><span class="av"><?= e(initials(person_name($r))) ?></span><span class="grow"><b><?= e(person_name($r)) ?></b><small><?= e(implode(' · ', array_filter([$r['company'], $r['email']]))) ?></small></span></a></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['companies']): ?><section class="card"><div class="card-h"><h2>Companies</h2></div><ul class="list"><?php foreach ($res['companies'] as $r): ?><li><a href="company.php?id=<?= (int)$r['id'] ?>"><span class="av sq"><?= e(initials($r['name'])) ?></span><span class="grow"><b><?= e($r['name']) ?></b><small><?= e($r['industry'] ?: $r['city']) ?></small></span><?= brand_tag($r['brand']) ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['deals']): ?><section class="card"><div class="card-h"><h2>Deals</h2></div><ul class="list"><?php foreach ($res['deals'] as $r): ?><li><a href="deal.php?id=<?= (int)$r['id'] ?>"><span class="grow"><b><?= e($r['title']) ?></b><small><?= e($r['brand']) ?></small></span><?= stage_pill($r['stage']) ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['projects']): ?><section class="card"><div class="card-h"><h2>Projects</h2></div><ul class="list"><?php foreach ($res['projects'] as $r): ?><li><a href="project.php?id=<?= (int)$r['id'] ?>"><span class="grow"><b><?= e($r['name']) ?></b><small><?= e($r['company']) ?></small></span><?= pill('pj', $r['status'], PROJECT_STATUSES[$r['status']] ?? $r['status']) ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['emails']): ?><section class="card"><div class="card-h"><h2>Email</h2><a class="muted small" href="inbox.php?q=<?= e(rawurlencode($q)) ?>">All in inbox →</a></div><ul class="list"><?php foreach ($res['emails'] as $r): ?><li><a href="email.php?t=<?= e(rawurlencode($r['thread_id'])) ?>"><span class="grow"><b><?= e($r['subject'] ?: '(no subject)') ?></b><small><?= e($r['from_name'] ?: $r['from_addr']) ?> · <?= e(fmt_date($r['last_at'])) ?></small></span></a></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($res['invoices']): ?><section class="card"><div class="card-h"><h2>Invoices</h2></div><ul class="list"><?php foreach ($res['invoices'] as $r): ?><li><a href="invoice.php?id=<?= (int)$r['id'] ?>"><span class="grow"><b><?= e($r['number']) ?></b><small><?= e($r['company']) ?></small></span><?= pill('inv', $r['status'], INVOICE_STATUSES[$r['status']]) ?></a></li><?php endforeach; ?></ul></section><?php endif; ?>
</div><?php endif; ?>
<?php layout_bottom(); ?>
