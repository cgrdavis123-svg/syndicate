<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/partials.php';
require __DIR__ . '/app/mail.php';
$user = require_user();
$id = (int)get('id');
$c = one('SELECT c.*, co.name company FROM contacts c LEFT JOIN companies co ON co.id = c.company_id WHERE c.id = ?', [$id]);
if (!$c) { flash('That contact no longer exists.'); redirect('contacts.php'); }
if (is_post() && in_array(post('action'), ['portal_on', 'portal_off', 'portal_reset'], true)) {
    require __DIR__ . '/app/portal.php';
    if (post('action') === 'portal_reset') { portal_reset_link($id); flash('New portal link created.'); }
    else { portal_enable($id, post('action') === 'portal_on'); flash(post('action') === 'portal_on' ? 'Portal access on. Copy the link below, or email it from a project.' : 'Portal access removed.'); }
    redirect('contact.php?id=' . $id . '#portal');
}
if (is_post()) handle_entity_post('contact', $id, 'contact.php?id=' . $id);
$deals = all('SELECT * FROM deals WHERE contact_id = ? ORDER BY stage IN (\'won\',\'lost\'), updated_at DESC', [$id]);
$consults = all('SELECT * FROM consultations WHERE contact_id = ? ORDER BY date DESC LIMIT 20', [$id]);
$mins = (int)val("SELECT COALESCE(SUM(minutes),0) FROM consultations WHERE contact_id = ? AND status = 'completed'", [$id]);
$name = person_name($c);
relink_emails($c); // emails that arrived before they were a contact
$canMail = (bool)val('SELECT 1 FROM mailboxes LIMIT 1');
layout_top($name, 'contacts', $user);
?>
<div class="page-head">
  <div class="row-av big"><span class="av lg"><?= e(initials($name)) ?></span><div><a class="muted small" href="contacts.php">← Contacts</a><h1><?= e($name) ?></h1>
    <p class="muted"><?= e($c['title']) ?><?= $c['title'] && $c['company'] ? ' at ' : '' ?><?= $c['company_id'] ? '<a href="company.php?id=' . (int)$c['company_id'] . '">' . e($c['company']) . '</a>' : '' ?></p></div></div>
  <div class="row">
    <?php if ($c['email']): ?><a class="btn ghost sm" href="<?= $canMail ? 'compose.php?contact=' . $id : 'mailto:' . e($c['email']) ?>">Email</a><?php endif; ?>
    <?php if ($c['phone']): ?><a class="btn ghost sm" href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['phone'])) ?>">Call</a><?php endif; ?>
    <a class="btn ghost sm" href="consultation-edit.php?contact=<?= $id ?>">Log consultation</a>
    <a class="btn ghost sm" href="deal-edit.php?contact=<?= $id ?>">+ Deal</a>
    <a class="btn primary sm" href="contact-edit.php?id=<?= $id ?>">Edit</a>
  </div>
</div>
<div class="grid-detail">
  <div class="stack"><?php render_emails('contact', $id, $c['email']); render_timeline('contact', $id); ?></div>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Details</h2></div>
      <dl class="facts">
        <div><dt>Email</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '—' ?></dd></div>
        <div><dt>Phone</dt><dd><?= $c['phone'] ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $c['phone'])) . '">' . e($c['phone']) . '</a>' : '—' ?></dd></div>
        <div><dt>Consulting time</dt><dd><?= hours($mins) ?></dd></div>
        <div><dt>Added</dt><dd><?= e(fmt_date($c['created_at'])) ?></dd></div>
      </dl>
      <?php if ($c['notes']): ?><div class="note-box"><?= nl2br(e($c['notes'])) ?></div><?php endif; ?>
    </section>
    <?php render_tasks('contact', $id); ?>
    <section class="card" id="portal"><div class="card-h"><h2>Client portal</h2><?php if ($c['portal_access']): ?><span class="muted small"><?= $c['portal_seen_at'] ? 'Last visit ' . e(ago($c['portal_seen_at'])) : 'Not signed in yet' ?></span><?php endif; ?></div>
      <?php if (!$c['email']): ?><p class="empty">Add an email address to give <?= e($c['first_name'] ?: 'them') ?> portal access.</p>
      <?php elseif ($c['portal_access']): ?>
        <p class="muted small">They see shared projects<?= $c['company'] ? ' for ' . e($c['company']) : '' ?>, and sent and paid invoices. They sign in with this link, or by entering their email on the sign-in page.</p>
        <div class="copy-row"><input readonly id="portalLink" value="<?= e(abs_url('portal-login.php?k=' . $c['portal_token'])) ?>" aria-label="Invite link"><button class="btn ghost sm" type="button" data-copy="#portalLink">Copy</button></div>
        <form method="post" class="row"><?= csrf_field() ?><button class="link-danger" type="submit" name="action" value="portal_reset" data-confirm="Make a new link? The old one stops working and they’re signed out.">Reset link</button><button class="link-danger" type="submit" name="action" value="portal_off" data-confirm="Remove portal access?">Remove access</button></form>
      <?php else: ?>
        <p class="muted small">Let <?= e($c['first_name'] ?: 'them') ?> follow projects, share files, approve work and see invoices.</p>
        <form method="post"><?= csrf_field() ?><button class="btn ghost sm" type="submit" name="action" value="portal_on">Give portal access</button></form>
      <?php endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>Deals</h2><a class="muted small" href="deal-edit.php?contact=<?= $id ?>">+ Add</a></div>
      <?php if (!$deals): ?><p class="empty">No deals yet.</p><?php else: ?><ul class="list"><?php foreach ($deals as $d): ?><li><a href="deal.php?id=<?= (int)$d['id'] ?>"><span class="grow"><b><?= e($d['title']) ?></b><small><?= e($d['brand']) ?></small></span><?= stage_pill($d['stage']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
    </section>
    <?php if ($consults): ?><section class="card"><div class="card-h"><h2>Consultations</h2></div>
      <ul class="list"><?php foreach ($consults as $s): ?><li><a href="consultation-edit.php?id=<?= (int)$s['id'] ?>"><span class="grow"><b><?= e($s['topic'] ?: 'Consultation') ?></b><small><?= e(fmt_date($s['date'])) ?> · <?= hours($s['minutes']) ?></small></span><?= pill('cs', $s['status'], CONSULT_STATUSES[$s['status']]) ?></a></li><?php endforeach; ?></ul>
    </section><?php endif; ?>
  </div>
</div>
<?php layout_bottom(); ?>
