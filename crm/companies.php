<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$q = get('q'); $brand = get('brand');
$where = []; $args = [];
if ($q !== '') { $where[] = '(co.name LIKE ? OR co.industry LIKE ? OR co.city LIKE ? OR co.website LIKE ?)'; array_push($args, ...array_fill(0, 4, "%$q%")); }
if (in_array($brand, BRANDS, true)) { $where[] = 'co.brand = ?'; $args[] = $brand; }
$rows = all("SELECT co.*, (SELECT COUNT(*) FROM contacts c WHERE c.company_id = co.id) people,
    (SELECT COUNT(*) FROM deals d WHERE d.company_id = co.id AND d.stage IN ('new','contacted','qualified','proposal')) open_deals,
    (SELECT COALESCE(SUM(d.value),0) FROM deals d WHERE d.company_id = co.id AND d.stage = 'won') won
    FROM companies co" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY co.name COLLATE NOCASE', $args);
layout_top('Companies', 'companies', $user);
?>
<div class="page-head"><div><p class="eyebrow"><?= count($rows) ?> compan<?= count($rows) === 1 ? 'y' : 'ies' ?></p><h1>Companies</h1></div><div class="row"><a class="btn ghost sm" href="export.php?type=companies">Export CSV</a><a class="btn primary sm" href="company-edit.php">+ New company</a></div></div>
<form class="filters" method="get">
  <select name="brand" data-autosubmit aria-label="Brand"><option value="">All brands</option><?php foreach (BRANDS as $b): ?><option<?= selected($b, $brand) ?>><?= e($b) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Filter companies" aria-label="Filter companies">
  <?php if ($q || $brand): ?><a class="muted small" href="companies.php">Clear</a><?php endif; ?>
</form>
<?php if (!$rows): ?><div class="card"><p class="empty"><?= $q || $brand ? 'No companies match.' : 'No companies yet.' ?> <a href="company-edit.php">Add a company</a></p></div><?php else: ?>
<div class="card flush"><div class="table-wrap"><table class="tbl">
  <thead><tr><th>Company</th><th>Brand</th><th>Location</th><th class="r">People</th><th class="r">Open deals</th><th class="r">Won</th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr data-href="company.php?id=<?= (int)$r['id'] ?>"><td><a class="cell-main row-av" href="company.php?id=<?= (int)$r['id'] ?>"><span class="av sq"><?= e(initials($r['name'])) ?></span><span><b><?= e($r['name']) ?></b><small><?= e($r['industry'] ?: $r['website']) ?></small></span></a></td>
      <td><?= brand_tag($r['brand']) ?></td><td class="muted"><?= e($r['city']) ?></td><td class="r num"><?= (int)$r['people'] ?></td><td class="r num"><?= (int)$r['open_deals'] ?></td><td class="r num"><?= $r['won'] ? money($r['won'], false) : '—' ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; layout_bottom(); ?>
