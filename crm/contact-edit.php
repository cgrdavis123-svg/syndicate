<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$id = (int)get('id');
$c = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'title' => '', 'company_id' => (int)get('company'), 'notes' => ''];
if ($id) { $c = one('SELECT * FROM contacts WHERE id = ?', [$id]); if (!$c) { flash('That contact no longer exists.'); redirect('contacts.php'); } }
$error = '';
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        q('DELETE FROM contacts WHERE id = ?', [$id]);
        q("DELETE FROM activities WHERE entity_type = 'contact' AND entity_id = ?", [$id]);
        q("DELETE FROM tasks WHERE entity_type = 'contact' AND entity_id = ?", [$id]);
        flash('Contact deleted.'); redirect('contacts.php');
    }
    foreach (array_keys($c) as $k) if (isset($_POST[$k])) $c[$k] = post($k);
    $companyId = (int)$c['company_id'] ?: null;
    if (post('new_company') !== '') {
        $ex = one('SELECT id FROM companies WHERE name = ? COLLATE NOCASE', [post('new_company')]);
        if ($ex) $companyId = (int)$ex['id']; else { q('INSERT INTO companies (name, created_at, updated_at) VALUES (?,?,?)', [post('new_company'), now(), now()]); $companyId = (int)db()->lastInsertId(); }
    }
    if ($c['first_name'] === '' && $c['last_name'] === '' && $c['email'] === '') $error = 'Enter at least a name or an email.';
    elseif ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $error = 'That email address doesn’t look right.';
    elseif ($c['email'] !== '' && one('SELECT id FROM contacts WHERE email = ? COLLATE NOCASE AND id != ?', [$c['email'], $id])) $error = 'Another contact already uses that email.';
    else {
        $vals = [$c['first_name'], $c['last_name'], $c['email'], $c['phone'], $c['title'], $companyId, $c['notes']];
        if ($id) { q('UPDATE contacts SET first_name=?, last_name=?, email=?, phone=?, title=?, company_id=?, notes=?, updated_at=? WHERE id=?', array_merge($vals, [now(), $id])); flash('Contact saved.'); }
        else { q('INSERT INTO contacts (first_name, last_name, email, phone, title, company_id, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($vals, [now(), now()])); $id = (int)db()->lastInsertId(); flash('Contact added.'); }
        redirect('contact.php?id=' . $id);
    }
}
layout_top($id ? 'Edit contact' : 'New contact', 'contacts', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'contact.php?id=' . $id : 'contacts.php' ?>">← Back</a><h1><?= $id ? 'Edit contact' : 'New contact' ?></h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <label class="f"><span>First name</span><input name="first_name" value="<?= e($c['first_name']) ?>" autocomplete="off"></label>
  <label class="f"><span>Last name</span><input name="last_name" value="<?= e($c['last_name']) ?>" autocomplete="off"></label>
  <label class="f"><span>Email</span><input name="email" type="email" value="<?= e($c['email']) ?>" autocomplete="off"></label>
  <label class="f"><span>Phone</span><input name="phone" type="tel" value="<?= e($c['phone']) ?>" autocomplete="off"></label>
  <label class="f"><span>Job title</span><input name="title" value="<?= e($c['title']) ?>" placeholder="e.g. Owner"></label>
  <label class="f"><span>Company</span><select name="company_id"><option value="">— None —</option><?php foreach (company_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $c['company_id']) ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>…or add a new company</span><input name="new_company" placeholder="New company name"></label>
  <span></span>
  <label class="f span2"><span>Notes</span><textarea name="notes" rows="4"><?= e($c['notes']) ?></textarea></label>
  <div class="save-bar span2">
    <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this contact and their timeline? Deals and invoices are kept.">Delete contact</button><?php else: ?><span></span><?php endif; ?>
    <div class="row"><a class="btn ghost sm" href="<?= $id ? 'contact.php?id=' . $id : 'contacts.php' ?>">Cancel</a><button class="btn primary sm" type="submit" name="action" value="save">Save contact</button></div>
  </div>
</form>
<?php layout_bottom(); ?>
