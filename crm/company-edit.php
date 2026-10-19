<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$id = (int)get('id');
$co = ['name' => '', 'website' => '', 'phone' => '', 'industry' => '', 'city' => '', 'address' => '', 'brand' => '', 'notes' => ''];
if ($id) { $co = one('SELECT * FROM companies WHERE id = ?', [$id]); if (!$co) { flash('That company no longer exists.'); redirect('companies.php'); } }
$error = '';
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        q('DELETE FROM companies WHERE id = ?', [$id]);
        q("DELETE FROM activities WHERE entity_type = 'company' AND entity_id = ?", [$id]);
        q("DELETE FROM tasks WHERE entity_type = 'company' AND entity_id = ?", [$id]);
        flash('Company deleted. Its contacts and deals were kept.'); redirect('companies.php');
    }
    foreach (array_keys($co) as $k) if (isset($_POST[$k])) $co[$k] = post($k);
    if (!in_array($co['brand'], BRANDS, true)) $co['brand'] = '';
    if ($co['website'] !== '' && !preg_match('#^https?://#i', $co['website'])) $co['website'] = 'https://' . $co['website'];
    if (mb_strlen($co['name']) < 2) $error = 'Enter the company name.';
    elseif (($dup = one('SELECT id FROM companies WHERE name = ? COLLATE NOCASE AND id != ?', [$co['name'], $id]))) $error = 'A company with that name already exists.';
    else {
        $vals = [$co['name'], $co['website'], $co['phone'], $co['industry'], $co['city'], $co['address'], $co['brand'], $co['notes']];
        if ($id) { q('UPDATE companies SET name=?, website=?, phone=?, industry=?, city=?, address=?, brand=?, notes=?, updated_at=? WHERE id=?', array_merge($vals, [now(), $id])); flash('Company saved.'); }
        else { q('INSERT INTO companies (name, website, phone, industry, city, address, brand, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [now(), now()])); $id = (int)db()->lastInsertId(); flash('Company added.'); }
        redirect('company.php?id=' . $id);
    }
}
layout_top($id ? 'Edit company' : 'New company', 'companies', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'company.php?id=' . $id : 'companies.php' ?>">← Back</a><h1><?= $id ? 'Edit company' : 'New company' ?></h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <label class="f span2"><span>Company name *</span><input name="name" required value="<?= e($co['name']) ?>"></label>
  <label class="f"><span>Website</span><input name="website" value="<?= e($co['website']) ?>" placeholder="example.com"></label>
  <label class="f"><span>Phone</span><input name="phone" type="tel" value="<?= e($co['phone']) ?>"></label>
  <label class="f"><span>Industry</span><input name="industry" value="<?= e($co['industry']) ?>" placeholder="e.g. Restaurant, CPG brand"></label>
  <label class="f"><span>City</span><input name="city" value="<?= e($co['city']) ?>" placeholder="e.g. Spartanburg, SC"></label>
  <label class="f span2"><span>Billing address <small class="muted">(shown on invoices)</small></span><textarea name="address" rows="3"><?= e($co['address']) ?></textarea></label>
  <label class="f"><span>Works with</span><select name="brand"><option value="">— Choose —</option><?php foreach (BRANDS as $b): ?><option<?= selected($b, $co['brand']) ?>><?= e($b) ?></option><?php endforeach; ?></select></label>
  <span></span>
  <label class="f span2"><span>Notes</span><textarea name="notes" rows="4"><?= e($co['notes']) ?></textarea></label>
  <div class="save-bar span2">
    <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this company? Its contacts, deals and invoices are kept.">Delete company</button><?php else: ?><span></span><?php endif; ?>
    <div class="row"><a class="btn ghost sm" href="<?= $id ? 'company.php?id=' . $id : 'companies.php' ?>">Cancel</a><button class="btn primary sm" type="submit" name="action" value="save">Save company</button></div>
  </div>
</form>
<?php layout_bottom(); ?>
