<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$q = get('q');
$args = []; $where = '';
if ($q !== '') { $where = ' WHERE (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR co.name LIKE ?)'; $args = array_fill(0, 5, "%$q%"); }
$rows = all("SELECT c.*, co.name company,
    (SELECT MAX(created_at) FROM activities a WHERE a.entity_type = 'contact' AND a.entity_id = c.id) last_activity,
    (SELECT COUNT(*) FROM deals d WHERE d.contact_id = c.id AND d.stage IN ('new','contacted','qualified','proposal')) open_deals
    FROM contacts c LEFT JOIN companies co ON co.id = c.company_id$where ORDER BY c.first_name COLLATE NOCASE, c.last_name COLLATE NOCASE", $args);
layout_top('Contacts', 'contacts', $user);
?>
<div class="page-head"><div><p class="eyebrow"><?= count($rows) ?> contact<?= count($rows) === 1 ? '' : 's' ?></p><h1>Contacts</h1></div><div class="row"><a class="btn ghost sm" href="export.php?type=contacts">Export CSV</a><a class="btn primary sm" href="contact-edit.php">+ New contact</a></div></div>
<form class="filters" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Filter by name, email, phone or company" aria-label="Filter contacts"><?php if ($q): ?><a class="muted small" href="contacts.php">Clear</a><?php endif; ?></form>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= $q ? 'No contacts match.' : 'No contacts yet. Website leads are added here automatically.' ?> <a href="contact-edit.php">Add a contact</a></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th class="r">Open deals</th><th>Last activity</th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr data-href="contact.php?id=<?= (int)$r['id'] ?>"><td><a class="cell-main row-av" href="contact.php?id=<?= (int)$r['id'] ?>"><span class="av"><?= e(initials(person_name($r))) ?></span><span><b><?= e(person_name($r)) ?></b><small><?= e($r['title']) ?></small></span></a></td>
      <td><?= $r['company_id'] ? '<a href="company.php?id=' . (int)$r['company_id'] . '">' . e($r['company']) . '</a>' : '<span class="muted">—</span>' ?></td>
      <td><?= $r['email'] ? '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>' : '' ?></td><td class="num"><?= e($r['phone']) ?></td><td class="r num"><?= (int)$r['open_deals'] ?></td><td class="muted"><?= e(ago($r['last_activity'] ?: $r['created_at'])) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
