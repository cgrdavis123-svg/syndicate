<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$id = (int)get('id');
$deal = ['title' => '', 'company_id' => (int)get('company'), 'contact_id' => (int)get('contact'), 'brand' => '', 'stage' => 'new', 'value' => '', 'expected_close' => '', 'source' => '', 'notes' => '', 'lost_reason' => ''];
if ($id) { $deal = one('SELECT * FROM deals WHERE id = ?', [$id]); if (!$deal) { flash('That deal no longer exists.'); redirect('deals.php'); } }
if ($deal['contact_id'] && !$deal['company_id'] && ($c = one('SELECT company_id FROM contacts WHERE id = ?', [$deal['contact_id']]))) $deal['company_id'] = $c['company_id'];
$errors = [];

if (is_post()) {
    if (post('action') === 'delete' && $id) {
        q('DELETE FROM deals WHERE id = ?', [$id]);
        q("DELETE FROM activities WHERE entity_type = 'deal' AND entity_id = ?", [$id]);
        q("DELETE FROM tasks WHERE entity_type = 'deal' AND entity_id = ?", [$id]);
        flash('Deal deleted.'); redirect('deals.php');
    }
    foreach (array_keys($deal) as $k) if (isset($_POST[$k])) $deal[$k] = post($k);
    if (mb_strlen($deal['title']) < 2) $errors['title'] = 'Give the deal a name.';
    if (!isset(STAGES[$deal['stage']])) $deal['stage'] = 'new';
    if (!in_array($deal['brand'], BRANDS, true)) $deal['brand'] = '';
    $deal['value'] = max(0, (float)str_replace([',', '$'], '', (string)$deal['value']));
    $deal['expected_close'] = valid_date((string)$deal['expected_close']) ? $deal['expected_close'] : null;
    $companyId = (int)$deal['company_id'] ?: null;
    if (post('new_company') !== '') {
        $ex = one('SELECT id FROM companies WHERE name = ? COLLATE NOCASE', [post('new_company')]);
        if ($ex) $companyId = (int)$ex['id'];
        else { q('INSERT INTO companies (name, created_at, updated_at) VALUES (?,?,?)', [post('new_company'), now(), now()]); $companyId = (int)db()->lastInsertId(); }
    }
    $contactId = (int)$deal['contact_id'] ?: null;
    if (!$companyId && $contactId && ($cc = one('SELECT company_id FROM contacts WHERE id = ?', [$contactId]))) $companyId = $cc['company_id'] ? (int)$cc['company_id'] : null;
    if (!$errors) {
        $vals = [$deal['title'], $companyId, $contactId, $deal['brand'], $deal['stage'], $deal['value'], $deal['expected_close'], $deal['source'], $deal['notes'], $deal['lost_reason']];
        if ($id) {
            $old = one('SELECT stage FROM deals WHERE id = ?', [$id]);
            $closed = in_array($deal['stage'], ['won', 'lost'], true) ? ($old['stage'] === $deal['stage'] ? val('SELECT closed_at FROM deals WHERE id = ?', [$id]) : now()) : null;
            q('UPDATE deals SET title=?, company_id=?, contact_id=?, brand=?, stage=?, value=?, expected_close=?, source=?, notes=?, lost_reason=?, closed_at=?, updated_at=? WHERE id=?', array_merge($vals, [$closed, now(), $id]));
            if ($old['stage'] !== $deal['stage']) log_activity('deal', $id, 'stage', 'Moved from ' . STAGES[$old['stage']] . ' to ' . STAGES[$deal['stage']] . '.');
            flash('Deal saved.');
        } else {
            $closed = in_array($deal['stage'], ['won', 'lost'], true) ? now() : null;
            q('INSERT INTO deals (title, company_id, contact_id, brand, stage, value, expected_close, source, notes, lost_reason, closed_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$closed, now(), now()]));
            $id = (int)db()->lastInsertId();
            log_activity('deal', $id, 'stage', 'Deal created in ' . STAGES[$deal['stage']] . '.');
            flash('Deal created.');
        }
        redirect('deal.php?id=' . $id);
    }
}
layout_top($id ? 'Edit deal' : 'New deal', 'deals', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'deal.php?id=' . $id : 'deals.php' ?>">← <?= $id ? 'Back to deal' : 'Pipeline' ?></a><h1><?= $id ? 'Edit deal' : 'New deal' ?></h1></div></div>
<?php if ($errors): ?><p class="notice bad">Please fix the highlighted fields.</p><?php endif; ?>
<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <label class="f span2"><span>Deal name *</span><input name="title" required value="<?= e($deal['title']) ?>" placeholder="e.g. Upstate Coffee — social media retainer"><?= isset($errors['title']) ? '<em class="err">' . e($errors['title']) . '</em>' : '' ?></label>
  <label class="f"><span>Company</span><select name="company_id"><option value="">— None —</option><?php foreach (company_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $deal['company_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>…or add a new company</span><input name="new_company" placeholder="New company name"></label>
  <label class="f"><span>Contact</span><select name="contact_id"><option value="">— None —</option><?php foreach (contact_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $deal['contact_id']) ?>><?= e(person_name($c)) ?><?= $c['company'] ? ' · ' . e($c['company']) : '' ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Brand</span><select name="brand"><option value="">— Choose —</option><?php foreach (BRANDS as $b): ?><option<?= selected($b, $deal['brand']) ?>><?= e($b) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Stage</span><select name="stage"><?php foreach (STAGES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $deal['stage']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Value ($)</span><input name="value" inputmode="decimal" value="<?= e($deal['value'] === '' ? '' : (float)$deal['value']) ?>" placeholder="0"></label>
  <label class="f"><span>Expected close</span><input name="expected_close" type="date" value="<?= e($deal['expected_close'] ?? '') ?>"></label>
  <label class="f"><span>Source</span><select name="source"><option value="">— Choose —</option><?php foreach (SOURCES as $s): ?><option<?= selected($s, $deal['source']) ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span>Notes</span><textarea name="notes" rows="4" placeholder="What they need, budget, timing…"><?= e($deal['notes']) ?></textarea></label>
  <label class="f span2"><span>Lost reason <small class="muted">(if lost)</small></span><input name="lost_reason" value="<?= e($deal['lost_reason']) ?>" placeholder="e.g. Went with another agency"></label>
  <div class="save-bar span2">
    <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this deal and its timeline? This can't be undone.">Delete deal</button><?php else: ?><span></span><?php endif; ?>
    <div class="row"><a class="btn ghost sm" href="<?= $id ? 'deal.php?id=' . $id : 'deals.php' ?>">Cancel</a><button class="btn primary sm" type="submit" name="action" value="save"><?= $id ? 'Save deal' : 'Create deal' ?></button></div>
  </div>
</form>
<?php layout_bottom(); ?>
