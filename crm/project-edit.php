<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
$user = require_user();
$id = (int)get('id');
$p = ['name' => '', 'company_id' => (int)get('company') ?: null, 'contact_id' => (int)get('contact') ?: null, 'deal_id' => (int)get('deal') ?: null, 'brand' => '', 'status' => 'planning', 'progress' => 0, 'start_date' => today(), 'due_date' => '', 'summary' => '', 'shared' => 1];
if ($id) { $p = one('SELECT * FROM projects WHERE id = ?', [$id]); if (!$p) { flash('That project no longer exists.'); redirect('projects.php'); } }
elseif ($p['deal_id'] && ($d = one('SELECT * FROM deals WHERE id = ?', [$p['deal_id']]))) { $p['name'] = preg_replace('/^(Consultation|Website)\s+—\s+/', '', $d['title']); $p['company_id'] = $d['company_id']; $p['contact_id'] = $d['contact_id']; $p['brand'] = $d['brand']; }
elseif ($p['contact_id'] && !$p['company_id']) $p['company_id'] = val('SELECT company_id FROM contacts WHERE id = ?', [$p['contact_id']]) ?: null;
if (!$id && !$p['brand'] && $p['company_id']) $p['brand'] = (string)val('SELECT brand FROM companies WHERE id = ?', [$p['company_id']]);
$error = '';
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        q('DELETE FROM projects WHERE id = ?', [$id]);
        foreach (glob(STORAGE . '/portal/' . $id . '/*') ?: [] as $f) @unlink($f);
        @rmdir(STORAGE . '/portal/' . $id);
        flash('Project deleted.'); redirect('projects.php');
    }
    $p = array_merge($p, ['name' => mb_substr(post('name'), 0, 200), 'company_id' => (int)post('company_id') ?: null, 'contact_id' => (int)post('contact_id') ?: null, 'deal_id' => (int)post('deal_id') ?: null,
        'brand' => in_array(post('brand'), BRANDS, true) ? post('brand') : '', 'status' => isset(PROJECT_STATUSES[post('status')]) ? post('status') : 'planning',
        'progress' => max(0, min(100, (int)post('progress'))), 'start_date' => valid_date(post('start_date')) ? post('start_date') : null, 'due_date' => valid_date(post('due_date')) ? post('due_date') : null,
        'summary' => mb_substr(post('summary'), 0, 5000), 'shared' => post('shared') === '1' ? 1 : 0]);
    if ($p['status'] === 'done') $p['progress'] = 100;
    if (mb_strlen($p['name']) < 2) $error = 'Give the project a name.';
    elseif (!$p['company_id'] && !$p['contact_id']) $error = 'Choose the client: a company, a contact, or both.';
    else {
        $vals = [$p['name'], $p['company_id'], $p['contact_id'], $p['deal_id'], $p['brand'], $p['status'], $p['progress'], $p['start_date'], $p['due_date'], $p['summary'], $p['shared']];
        if ($id) {
            $old = one('SELECT status FROM projects WHERE id = ?', [$id]);
            q('UPDATE projects SET name=?, company_id=?, contact_id=?, deal_id=?, brand=?, status=?, progress=?, start_date=?, due_date=?, summary=?, shared=?, updated_at=? WHERE id=?', array_merge($vals, [now(), $id]));
            if ($old['status'] !== $p['status']) q("INSERT INTO project_posts (project_id, author, kind, body, created_at) VALUES (?, 'staff', 'status', ?, ?)", [$id, 'Status changed to ' . PROJECT_STATUSES[$p['status']] . '.', now()]);
            flash('Project saved.');
        } else {
            q('INSERT INTO projects (name, company_id, contact_id, deal_id, brand, status, progress, start_date, due_date, summary, shared, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [now(), now()]));
            $id = (int)db()->lastInsertId();
            foreach (array_filter(array_map('trim', explode("\n", (string)($_POST['steps'] ?? '')))) as $i => $s) q('INSERT INTO project_steps (project_id, title, position) VALUES (?,?,?)', [$id, mb_substr($s, 0, 200), $i]);
            flash('Project created.');
        }
        redirect('project.php?id=' . $id);
    }
}
$templates = [
    'Website build' => "Kickoff call and goals\nSitemap and content plan\nDesign mockups for approval\nBuild and connect forms\nReview round\nLaunch and handoff",
    'Paid ads launch' => "Kickoff and account access\nAudience and offer plan\nAd creative for approval\nCampaign setup and tracking\nLaunch\nFirst results report",
    'Social media management' => "Brand and content kickoff\nMonthly content calendar for approval\nContent production\nPosting and community management\nMonthly report",
    'TikTok Shop launch' => "Shop audit and setup\nProduct listings\nCreator and affiliate plan\nSample outreach\nLaunch content and live plan\nGMV review",
];
layout_top($id ? 'Edit project' : 'New project', 'projects', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'project.php?id=' . $id : 'projects.php' ?>">← Back</a><h1><?= $id ? 'Edit project' : 'New project' ?></h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <label class="f span2"><span>Project name *</span><input name="name" required value="<?= e($p['name']) ?>" placeholder="e.g. Website rebuild, Fall ads campaign"></label>
  <label class="f"><span>Company</span><select name="company_id"><option value="">— None —</option><?php foreach (company_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $p['company_id']) ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Main contact</span><select name="contact_id"><option value="">— None —</option><?php foreach (contact_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $p['contact_id']) ?>><?= e(person_name($o) . ($o['company'] ? ' — ' . $o['company'] : '')) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Brand</span><select name="brand"><option value="">— Choose —</option><?php foreach (BRANDS as $b): ?><option<?= selected($b, $p['brand']) ?>><?= e($b) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>From deal <small class="muted">(optional)</small></span><select name="deal_id"><option value="">— None —</option><?php foreach (deal_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $p['deal_id']) ?>><?= e($o['title']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Status</span><select name="status"><?php foreach (PROJECT_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $p['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Progress <small class="muted">(%)</small></span><input name="progress" type="number" min="0" max="100" step="1" value="<?= (int)$p['progress'] ?>"></label>
  <label class="f"><span>Start date</span><input name="start_date" type="date" value="<?= e((string)$p['start_date']) ?>"></label>
  <label class="f"><span>Target date</span><input name="due_date" type="date" value="<?= e((string)$p['due_date']) ?>"></label>
  <label class="f span2"><span>Summary <small class="muted">(the client sees this at the top of the project)</small></span><textarea name="summary" rows="4" placeholder="What we’re doing, what’s included, and how we’ll measure it."><?= e($p['summary']) ?></textarea></label>
  <?php if (!$id): ?>
  <div class="f span2"><span>Steps <small class="muted">(one per line; the client sees these as a checklist)</small></span>
    <div class="row wrap"><?php foreach ($templates as $k => $t): ?><button class="btn ghost xs" type="button" data-fill="#stepsIn" data-text="<?= e($t) ?>"><?= e($k) ?></button><?php endforeach; ?></div>
    <textarea name="steps" id="stepsIn" rows="6" placeholder="Kickoff call&#10;Design for approval&#10;Launch"></textarea></div>
  <?php endif; ?>
  <label class="chk span2"><input type="checkbox" name="shared" value="1"<?= $p['shared'] ? ' checked' : '' ?>> <span>Show in the client portal</span></label>
  <div class="save-bar span2">
    <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this project, its updates and files? This can’t be undone.">Delete project</button><?php else: ?><span></span><?php endif; ?>
    <div class="row"><a class="btn ghost sm" href="<?= $id ? 'project.php?id=' . $id : 'projects.php' ?>">Cancel</a><button class="btn primary sm" type="submit" name="action" value="save">Save project</button></div>
  </div>
</form>
<?php layout_bottom(); ?>
