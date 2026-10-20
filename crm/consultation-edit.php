<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();
$id = (int)get('id');
$s = ['contact_id' => (int)get('contact'), 'company_id' => 0, 'deal_id' => (int)get('deal'), 'date' => today(), 'start_time' => '', 'minutes' => 60, 'rate' => setting('default_rate'), 'topic' => '', 'notes' => '', 'status' => 'completed', 'billable' => 1, 'invoice_id' => null];
if ($id) { $s = one('SELECT * FROM consultations WHERE id = ?', [$id]); if (!$s) { flash('That consultation no longer exists.'); redirect('consultations.php'); } }
if (!$id && $s['deal_id'] && ($d = one('SELECT contact_id, company_id FROM deals WHERE id = ?', [$s['deal_id']]))) { $s['contact_id'] = $s['contact_id'] ?: (int)$d['contact_id']; $s['company_id'] = (int)$d['company_id']; }
if (!$id && $s['contact_id'] && !$s['company_id'] && ($c = one('SELECT company_id FROM contacts WHERE id = ?', [$s['contact_id']]))) $s['company_id'] = (int)$c['company_id'];
$error = '';
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        if ($s['invoice_id']) $error = 'This session is on an invoice. Remove it from the invoice first.';
        else { q('DELETE FROM consultations WHERE id = ?', [$id]); flash('Consultation deleted.'); redirect('consultations.php'); }
    } else {
        foreach (['contact_id', 'company_id', 'deal_id', 'date', 'start_time', 'minutes', 'rate', 'topic', 'notes', 'status'] as $k) if (isset($_POST[$k])) $s[$k] = post($k);
        $s['billable'] = post('billable') === '1' ? 1 : 0;
        $s['minutes'] = max(0, (int)round((float)post('hours') * 60));
        $s['rate'] = max(0, (float)$s['rate']);
        if (!isset(CONSULT_STATUSES[$s['status']])) $s['status'] = 'completed';
        if (!(int)$s['company_id'] && (int)$s['contact_id'] && ($c = one('SELECT company_id FROM contacts WHERE id = ?', [(int)$s['contact_id']]))) $s['company_id'] = (int)$c['company_id'];
        if (!(int)$s['contact_id'] && !(int)$s['company_id']) $error = 'Choose who the consultation was with.';
        elseif (!valid_date($s['date'])) $error = 'Enter a valid date.';
        elseif ($s['minutes'] < 1 && $s['status'] === 'completed') $error = 'Enter how long the session was.';
        else {
            $vals = [(int)$s['contact_id'] ?: null, (int)$s['company_id'] ?: null, (int)$s['deal_id'] ?: null, $s['date'], preg_match('/^\d{2}:\d{2}$/', $s['start_time']) ? $s['start_time'] : '', $s['minutes'], $s['rate'], mb_substr($s['topic'], 0, 200), $s['notes'], $s['status'], $s['billable']];
            if ($id) { q('UPDATE consultations SET contact_id=?, company_id=?, deal_id=?, date=?, start_time=?, minutes=?, rate=?, topic=?, notes=?, status=?, billable=?, updated_at=? WHERE id=?', array_merge($vals, [now(), $id])); flash('Consultation saved.'); }
            else {
                q('INSERT INTO consultations (contact_id, company_id, deal_id, date, start_time, minutes, rate, topic, notes, status, billable, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [now(), now()]));
                $id = (int)db()->lastInsertId();
                $summary = ($s['status'] === 'scheduled' ? 'Consultation scheduled for ' : 'Consultation on ') . fmt_date($s['date']) . ' · ' . hours($s['minutes']) . ($s['topic'] ? ' · ' . $s['topic'] : '');
                if ((int)$s['contact_id']) log_activity('contact', (int)$s['contact_id'], 'meeting', $summary);
                if ((int)$s['deal_id']) log_activity('deal', (int)$s['deal_id'], 'meeting', $summary);
                flash($s['status'] === 'scheduled' ? 'Consultation scheduled.' : 'Consultation logged.');
            }
            redirect('consultations.php');
        }
    }
}
layout_top($id ? 'Edit consultation' : 'Log consultation', 'consultations', $user);
?>
<div class="page-head"><div><a class="muted small" href="consultations.php">← Consultations</a><h1><?= $id ? 'Edit consultation' : 'Log a consultation' ?></h1></div><?php if ($id): ?><a class="btn ghost sm" href="calendar.php?id=<?= $id ?>">Add to calendar (.ics)</a><?php endif; ?></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<?php if ($s['invoice_id']): ?><p class="notice">This session is billed on invoice <a href="invoice.php?id=<?= (int)$s['invoice_id'] ?>"><?= e(val('SELECT number FROM invoices WHERE id = ?', [$s['invoice_id']])) ?></a>.</p><?php endif; ?>
<form method="post" class="card form-grid" id="consultForm">
  <?= csrf_field() ?>
  <label class="f"><span>Contact</span><select name="contact_id"><option value="">— Choose —</option><?php foreach (contact_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $s['contact_id']) ?>><?= e(person_name($c)) ?><?= $c['company'] ? ' · ' . e($c['company']) : '' ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Company <small class="muted">(bills to)</small></span><select name="company_id"><option value="">— Same as contact —</option><?php foreach (company_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= selected($c['id'], $s['company_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Date</span><input name="date" type="date" required value="<?= e($s['date']) ?>"></label>
  <label class="f"><span>Start time <small class="muted">(optional)</small></span><input name="start_time" type="time" value="<?= e($s['start_time']) ?>"></label>
  <label class="f"><span>Length (hours)</span><input name="hours" type="number" step="0.25" min="0" value="<?= e(rtrim(rtrim(number_format($s['minutes'] / 60, 2), '0'), '.')) ?>" id="hoursIn"></label>
  <label class="f"><span>Rate ($/hr)</span><input name="rate" type="number" step="1" min="0" value="<?= e((float)$s['rate']) ?>" id="rateIn"></label>
  <label class="f"><span>Status</span><select name="status"><?php foreach (CONSULT_STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $s['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Deal <small class="muted">(optional)</small></span><select name="deal_id"><option value="">— None —</option><?php foreach (deal_options() as $d): ?><option value="<?= (int)$d['id'] ?>"<?= selected($d['id'], $s['deal_id']) ?>><?= e($d['title']) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span>Topic</span><input name="topic" value="<?= e($s['topic']) ?>" placeholder="e.g. TikTok Shop audit, creator program setup"></label>
  <label class="f span2"><span>Session notes</span><textarea name="notes" rows="5" placeholder="What you covered and the next steps you agreed on."><?= e($s['notes']) ?></textarea></label>
  <label class="chk span2"><input type="checkbox" name="billable" value="1"<?= $s['billable'] ? ' checked' : '' ?>> Billable · <b id="amountOut" class="num"><?= money($s['minutes'] * $s['rate'] / 60) ?></b></label>
  <div class="save-bar span2">
    <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this consultation?">Delete</button><?php else: ?><span></span><?php endif; ?>
    <div class="row"><a class="btn ghost sm" href="consultations.php">Cancel</a><button class="btn primary sm" type="submit" name="action" value="save"><?= $id ? 'Save' : 'Save consultation' ?></button></div>
  </div>
</form>
<?php layout_bottom(); ?>
