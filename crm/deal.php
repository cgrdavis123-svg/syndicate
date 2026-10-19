<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/partials.php';
$user = require_user();
$id = (int)get('id');
$deal = one('SELECT d.*, co.name company, co.website, c.first_name, c.last_name, c.email, c.phone, c.title ctitle FROM deals d LEFT JOIN companies co ON co.id = d.company_id LEFT JOIN contacts c ON c.id = d.contact_id WHERE d.id = ?', [$id]);
if (!$deal) { flash('That deal no longer exists.'); redirect('deals.php'); }
$back = 'deal.php?id=' . $id;
if (is_post()) {
    if (post('action') === 'stage' && isset(STAGES[post('stage')]) && post('stage') !== $deal['stage']) {
        $s = post('stage');
        q('UPDATE deals SET stage = ?, closed_at = ?, updated_at = ? WHERE id = ?', [$s, in_array($s, ['won', 'lost'], true) ? now() : null, now(), $id]);
        log_activity('deal', $id, 'stage', 'Moved from ' . STAGES[$deal['stage']] . ' to ' . STAGES[$s] . '.');
        flash('Moved to ' . STAGES[$s] . '.'); redirect($back);
    }
    handle_entity_post('deal', $id, $back);
}
$consults = all('SELECT * FROM consultations WHERE deal_id = ? ORDER BY date DESC', [$id]);
layout_top($deal['title'], 'deals', $user);
?>
<div class="page-head">
  <div><a class="muted small" href="deals.php">← Pipeline</a><h1><?= e($deal['title']) ?></h1>
    <p class="row muted"><?= stage_pill($deal['stage']) ?> <?= brand_tag($deal['brand']) ?> <span class="num"><?= money($deal['value'], false) ?></span><?php if ($deal['source']): ?> · <?= e($deal['source']) ?><?php endif; ?></p></div>
  <div class="row">
    <a class="btn ghost sm" href="consultation-edit.php?deal=<?= $id ?>">Log consultation</a>
    <a class="btn ghost sm" href="quote-edit.php?deal=<?= $id ?>">Create quote</a>
    <a class="btn ghost sm" href="invoice-edit.php?deal=<?= $id ?>">Create invoice</a>
    <?php $proj = val('SELECT id FROM projects WHERE deal_id = ? LIMIT 1', [$id]); ?><a class="btn ghost sm" href="<?= $proj ? 'project.php?id=' . (int)$proj : 'project-edit.php?deal=' . $id ?>"><?= $proj ? 'Project' : '+ Project' ?></a>
    <a class="btn primary sm" href="deal-edit.php?id=<?= $id ?>">Edit</a>
  </div>
</div>

<form method="post" class="stepper"><?= csrf_field() ?><input type="hidden" name="action" value="stage">
  <?php $reached = true; foreach (STAGES as $k => $l): $cur = $k === $deal['stage']; ?>
    <button type="submit" name="stage" value="<?= $k ?>" class="step-btn<?= $cur ? ' on' : '' ?> st-<?= $k ?>"<?= $cur ? ' aria-current="step"' : '' ?>><?= e($l) ?></button>
  <?php endforeach; ?>
</form>

<div class="grid-detail">
  <div class="stack">
    <?php render_timeline('deal', $id); ?>
  </div>
  <div class="stack">
    <section class="card">
      <div class="card-h"><h2>Details</h2></div>
      <dl class="facts">
        <div><dt>Value</dt><dd class="num"><?= money($deal['value']) ?></dd></div>
        <div><dt>Expected close</dt><dd><?= e($deal['expected_close'] ? fmt_date($deal['expected_close']) : '—') ?></dd></div>
        <div><dt>Created</dt><dd><?= e(fmt_date($deal['created_at'])) ?></dd></div>
        <?php if ($deal['closed_at']): ?><div><dt>Closed</dt><dd><?= e(fmt_date($deal['closed_at'])) ?></dd></div><?php endif; ?>
        <?php if ($deal['lost_reason']): ?><div><dt>Lost reason</dt><dd><?= e($deal['lost_reason']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($deal['notes']): ?><div class="note-box"><?= nl2br(e($deal['notes'])) ?></div><?php endif; ?>
    </section>
    <?php if ($deal['contact_id'] || $deal['company_id']): ?>
    <section class="card">
      <div class="card-h"><h2>People</h2></div>
      <?php if ($deal['contact_id']): ?><a class="person" href="contact.php?id=<?= (int)$deal['contact_id'] ?>"><span class="av"><?= e(initials(person_name($deal))) ?></span><span class="grow"><b><?= e(person_name($deal)) ?></b><small><?= e($deal['ctitle'] ?: $deal['email']) ?></small></span></a>
        <div class="row small"><?php if ($deal['email']): ?><a class="btn ghost xs" href="mailto:<?= e($deal['email']) ?>">Email</a><?php endif; ?><?php if ($deal['phone']): ?><a class="btn ghost xs" href="tel:<?= e(preg_replace('/[^\d+]/', '', $deal['phone'])) ?>">Call</a><?php endif; ?></div><?php endif; ?>
      <?php if ($deal['company_id']): ?><a class="person" href="company.php?id=<?= (int)$deal['company_id'] ?>"><span class="av sq"><?= e(initials($deal['company'])) ?></span><span class="grow"><b><?= e($deal['company']) ?></b><small><?= e($deal['website'] ?: 'Company') ?></small></span></a><?php endif; ?>
    </section>
    <?php endif; ?>
    <?php render_tasks('deal', $id); ?>
    <?php if ($consults): ?>
    <section class="card"><div class="card-h"><h2>Consultations</h2></div>
      <ul class="list"><?php foreach ($consults as $c): ?><li><a href="consultation-edit.php?id=<?= (int)$c['id'] ?>"><span class="grow"><b><?= e($c['topic'] ?: 'Consultation') ?></b><small><?= e(fmt_date($c['date'])) ?> · <?= hours($c['minutes']) ?></small></span><?= pill('cs', $c['status'], CONSULT_STATUSES[$c['status']]) ?></a></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
  </div>
</div>
<?php layout_bottom(); ?>
