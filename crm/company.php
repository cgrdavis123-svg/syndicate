<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/partials.php';
require __DIR__ . '/app/mail.php';
$user = require_user();
$id = (int)get('id');
$co = one('SELECT * FROM companies WHERE id = ?', [$id]);
if (!$co) { flash('That company no longer exists.'); redirect('companies.php'); }
if (is_post()) handle_entity_post('company', $id, 'company.php?id=' . $id);
$people = all('SELECT * FROM contacts WHERE company_id = ? ORDER BY first_name', [$id]);
$deals = all('SELECT * FROM deals WHERE company_id = ? ORDER BY stage IN (\'won\',\'lost\'), updated_at DESC', [$id]);
$invoices = all('SELECT * FROM invoices WHERE company_id = ? ORDER BY issue_date DESC', [$id]);
$won = array_sum(array_map(function ($d) { return $d['stage'] === 'won' ? (float)$d['value'] : 0; }, $deals));
$paid = 0.0; foreach ($invoices as $i) if ($i['status'] === 'paid') $paid += invoice_total((int)$i['id'])['total'];
layout_top($co['name'], 'companies', $user);
?>
<div class="page-head">
  <div class="row-av big"><span class="av sq lg"><?= e(initials($co['name'])) ?></span><div><a class="muted small" href="companies.php">← Companies</a><h1><?= e($co['name']) ?></h1><p class="row muted"><?= brand_tag($co['brand']) ?> <?= e(implode(' · ', array_filter([$co['industry'], $co['city']]))) ?></p></div></div>
  <div class="row"><a class="btn ghost sm" href="contact-edit.php?company=<?= $id ?>">+ Contact</a><a class="btn ghost sm" href="deal-edit.php?company=<?= $id ?>">+ Deal</a><a class="btn ghost sm" href="invoice-edit.php?company=<?= $id ?>">+ Invoice</a><a class="btn primary sm" href="company-edit.php?id=<?= $id ?>">Edit</a></div>
</div>
<div class="mini-kpis"><div><span>Won deals</span><b class="num"><?= money($won, false) ?></b></div><div><span>Paid invoices</span><b class="num"><?= money($paid, false) ?></b></div><div><span>People</span><b class="num"><?= count($people) ?></b></div><div><span>Customer since</span><b><?= e(fmt_date($co['created_at'], 'M Y')) ?></b></div></div>
<div class="grid-detail">
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Deals</h2><a class="muted small" href="deal-edit.php?company=<?= $id ?>">+ Add</a></div>
      <?php if (!$deals): ?><p class="empty">No deals yet.</p><?php else: ?><ul class="list"><?php foreach ($deals as $d): ?><li><a href="deal.php?id=<?= (int)$d['id'] ?>"><span class="grow"><b><?= e($d['title']) ?></b><small><?= e($d['brand']) ?></small></span><?= stage_pill($d['stage']) ?><span class="num"><?= money($d['value'], false) ?></span></a></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php render_emails('company', $id); render_timeline('company', $id); ?>
  </div>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Details</h2></div>
      <dl class="facts">
        <?php if ($co['website']): ?><div><dt>Website</dt><dd><a href="<?= e($co['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://(www\.)?#', '', $co['website'])) ?> ↗</a></dd></div><?php endif; ?>
        <?php if ($co['phone']): ?><div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $co['phone'])) ?>"><?= e($co['phone']) ?></a></dd></div><?php endif; ?>
        <?php if ($co['address']): ?><div><dt>Billing address</dt><dd><?= nl2br(e($co['address'])) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($co['notes']): ?><div class="note-box"><?= nl2br(e($co['notes'])) ?></div><?php endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>People</h2><a class="muted small" href="contact-edit.php?company=<?= $id ?>">+ Add</a></div>
      <?php if (!$people): ?><p class="empty">No contacts yet.</p><?php else: foreach ($people as $p): ?><a class="person" href="contact.php?id=<?= (int)$p['id'] ?>"><span class="av"><?= e(initials(person_name($p))) ?></span><span class="grow"><b><?= e(person_name($p)) ?></b><small><?= e($p['title'] ?: $p['email']) ?></small></span></a><?php endforeach; endif; ?>
    </section>
    <?php render_tasks('company', $id); ?>
    <?php $csites = all('SELECT * FROM sites WHERE company_id = ? ORDER BY name', [$id]); ?>
    <section class="card"><div class="card-h"><h2>Website stats</h2><a class="muted small" href="site-edit.php?company=<?= $id ?>">+ Add site</a></div>
      <?php if (!$csites): ?><p class="empty">Not tracking their website yet. Add it to see visitors, sources, calls and form sends.</p><?php else: require_once __DIR__ . '/app/analytics.php'; foreach ($csites as $cs): $d = an_daily((int)$cs['id'], 30); ?>
        <a class="site-mini" href="site.php?id=<?= (int)$cs['id'] ?>"><span class="grow"><b><?= e($cs['name']) ?></b><small><?= an_num(array_sum($d)) ?> visitors in 30 days</small></span><?= sparkline($d, 110, 30) ?></a>
      <?php endforeach; endif; ?>
    </section>
    <?php $projs = all('SELECT * FROM projects WHERE company_id = ? ORDER BY status = \'done\', updated_at DESC', [$id]); $portal = (int)val('SELECT COUNT(*) FROM contacts WHERE company_id = ? AND portal_access = 1', [$id]); ?>
    <section class="card"><div class="card-h"><h2>Projects</h2><a class="muted small" href="project-edit.php?company=<?= $id ?>">+ New</a></div>
      <?php if (!$projs): ?><p class="empty">No projects yet. Projects show in the client portal with updates, files and approvals.</p><?php else: ?><ul class="list"><?php foreach ($projs as $pj): ?><li><a href="project.php?id=<?= (int)$pj['id'] ?>"><span class="grow"><b><?= e($pj['name']) ?></b><small><?= (int)$pj['progress'] ?>% done</small></span><?= pill('pj', $pj['status'], PROJECT_STATUSES[$pj['status']]) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
      <p class="muted small"><?= $portal ? $portal . ' ' . ($portal === 1 ? 'person has' : 'people have') . ' portal access.' : 'Nobody here has portal access yet. Give access from a project or a contact.' ?></p>
    </section>
    <section class="card"><div class="card-h"><h2>Invoices</h2><a class="muted small" href="invoice-edit.php?company=<?= $id ?>">+ New</a></div>
      <?php if (!$invoices): ?><p class="empty">No invoices yet.</p><?php else: ?><ul class="list"><?php foreach ($invoices as $i): ?><li><a href="invoice.php?id=<?= (int)$i['id'] ?>"><span class="grow"><b><?= e($i['number']) ?></b><small><?= e(fmt_date($i['issue_date'])) ?></small></span><?= pill('inv', $i['status'], INVOICE_STATUSES[$i['status']]) ?><span class="num"><?= money(invoice_total((int)$i['id'])['total']) ?></span></a></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
