<?php
// E-signature for a checklist step: the member reads the document, types their full name and agrees to sign electronically.
// Saved with the exact statement, the document link, the time, a hashed IP and the browser, and a receipt is emailed to them.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
$me = require_member();
$id = (int)$me['id'];
$key = get('s') !== '' ? get('s') : post('s');
$def = team_step_def($me, $key);
if (!$def || $def['type'] !== 'sign' || !in_array($def, team_visible_steps($me), true)) redirect('team/');
$rows = team_step_rows($id);
if (!empty($rows[$key]['done_at'])) { flash('You’ve already signed this one.'); redirect('team/#s-' . $key); }

$url = $def['doc'] !== '' ? team_doc_url($me, $def['doc']) : '';
$docTitle = $def['doc'] !== '' ? TEAM_DOCS[$def['doc']] : '';
$isAgreement = $key === 'agreement';
$prev = $rows['agreement']['data'] ?? [];
$v = [
    'legal' => post('legal', (string)($prev['legal'] ?? $me['name'])),
    'business' => post('business', (string)($prev['business'] ?? '')),
    'address' => post('address', (string)($prev['address'] ?? '')),
    'name' => post('name'),
];
$errors = [];
if (is_post()) {
    if ($def['doc'] !== '' && $url === '') $errors[] = 'The document isn’t linked yet. Grady will let you know when it’s ready to sign.';
    if ($isAgreement) {
        if (mb_strlen($v['legal']) < 3 || mb_strlen($v['legal']) > 120) $errors[] = 'Enter your full legal name.';
        if (mb_strlen($v['address']) < 10 || mb_strlen($v['address']) > 300) $errors[] = 'Enter your full mailing address.';
        if (mb_strlen($v['business']) > 120) $errors[] = 'Keep the business name under 120 characters.';
    }
    if (mb_strlen($v['name']) < 3 || mb_strlen($v['name']) > 120 || !preg_match('/\S+\s+\S+/u', $v['name'])) $errors[] = 'Type your first and last name to sign.';
    if (post('esign') !== '1') $errors[] = 'Tick the box to agree to sign electronically.';
    if (post('read') !== '1') $errors[] = $def['doc'] === '' ? 'Tick the box to confirm the statement is true.' : 'Tick the box to confirm you read the whole document.';
    if (!$errors) {
        $data = [
            'name' => $v['name'], 'statement' => $def['statement'], 'doc' => $docTitle, 'doc_url' => $url,
            'at' => now(), 'ip' => ip_hash(), 'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        ];
        if ($isAgreement) $data += ['legal' => $v['legal'], 'business' => $v['business'], 'address' => $v['address']];
        team_step_save($id, $key, true, 'member', $data);
        $receipt = 'Hi ' . team_first($me) . ",\n\nThis confirms you signed \"" . $def['title'] . "\" in the Syndicate team portal.\n\n"
            . ($docTitle ? "Document: $docTitle\n" . ($url ? "Link: $url\n" : '') : '')
            . "Statement: {$def['statement']}\nSigned as: {$v['name']}\nDate and time: " . fmt_date($data['at'], 'F j, Y g:i a T') . "\n\n"
            . "Want a paper copy, or need to change something? Reply to this email.\n\nSyndicate Holdings Group";
        send_mail($me['email'], 'Signed: ' . $def['title'], $receipt, (string)config('contact_to'));
        team_notify($me, 'signed “' . $def['title'] . '”');
        flash('Signed. A receipt is on its way to your email.');
        redirect('team/#s-' . $key);
    }
}
team_header($def['title'], $me);
?>
<div class="adm-head">
  <div><span class="eyebrow"><span class="dot"></span>Sign electronically</span><h1><?= e($def['title']) ?></h1><p class="muted" style="margin:6px 0 0"><?= e($def['help']) ?></p></div>
  <a class="btn btn-ghost btn-sm" href="team/#checklist">Back to checklist</a>
</div>

<form class="panel form t-sign" method="post">
  <?= csrf_field() ?><input type="hidden" name="s" value="<?= e($key) ?>">
  <?php if ($errors): ?><div class="notice warn" role="alert"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

  <?php if ($def['doc'] !== ''): ?>
    <div class="t-docbox">
      <span class="file-ico">DOC</span>
      <div class="grow"><b>1. Read the <?= e($docTitle) ?></b><small class="muted"><?= $key === 'rules' ? 'Exhibit B is at the end of the agreement.' : 'Read it in full before you sign. You can export it to PDF.' ?></small></div>
      <?php if ($url): ?><a class="btn btn-primary btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Open document ↗</a>
      <?php else: ?><span class="tag">NOT LINKED YET</span><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($isAgreement): ?>
    <fieldset class="t-fs">
      <legend>2. Your details for the agreement</legend>
      <div class="f"><label for="g-legal">Full legal name</label><input id="g-legal" name="legal" required maxlength="120" autocomplete="name" value="<?= e($v['legal']) ?>"></div>
      <div class="f"><label for="g-biz">Business name <span class="muted">(optional, if you work through a company)</span></label><input id="g-biz" name="business" maxlength="120" autocomplete="organization" value="<?= e($v['business']) ?>"></div>
      <div class="f"><label for="g-addr">Mailing address</label><textarea id="g-addr" name="address" required maxlength="300" rows="3" autocomplete="street-address"><?= e($v['address']) ?></textarea><p class="form-note">Used for your agreement and tax forms only. Never shared.</p></div>
    </fieldset>
  <?php endif; ?>

  <fieldset class="t-fs">
    <legend><?= $def['doc'] !== '' ? ($isAgreement ? '3' : '2') . '. ' : '' ?>Sign</legend>
    <div class="quote"><?= e($def['statement']) ?></div>
    <label class="t-cb"><input type="checkbox" name="read" value="1"<?= post('read') === '1' ? ' checked' : '' ?>> <?= $def['doc'] === '' ? 'I read the statement above and it’s true.' : 'I read the whole document.' ?></label>
    <label class="t-cb"><input type="checkbox" name="esign" value="1"<?= post('esign') === '1' ? ' checked' : '' ?>> I agree to sign electronically. My typed name is my legal signature, the same as signing on paper. I can ask for a paper copy any time.</label>
    <div class="f"><label for="g-name">Type your full name to sign</label><input id="g-name" name="name" required maxlength="120" autocomplete="name" class="t-sig" value="<?= e($v['name']) ?>"></div>
    <p class="form-note">We record the date and time, the document link and your browser. A receipt goes to <?= e($me['email']) ?>.</p>
    <button class="btn btn-primary" type="submit">Sign <?= ARROW ?></button>
  </fieldset>
</form>
<?php team_footer(); ?>
