<?php
// The online kickoff form: the client tells us about their business, picks what to show on the site and adds their
// logo and photos. Sending it starts their website draft (app/builds.php), unless they'd also like a call first.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/builds.php';
$c = require_portal();
$p = portal_project($c, (int)get('id'));
$b = $p ? build_for_project((int)$p['id']) : null;
if (!$p || !$b || $b['status'] === 'cancelled') { flash('That kickoff form isn’t available.'); redirect('portal.php'); }
$back = 'portal-kickoff.php?id=' . (int)$p['id'];
$open = kickoff_open($b);
$errors = [];
if (is_post() && $open) {
    $call = post('call') === '1';
    [$errors, $started] = kickoff_save($b, $c, $_POST, $call);
    if (!$errors) {
        [$n, $fileErrors] = save_project_files((int)$p['id'], 'files', 'client', (int)$c['id']);
        q('UPDATE projects SET updated_at = ? WHERE id = ?', [now(), $p['id']]);
        flash(($started ? 'Thank you! We’re starting on your website. You’ll see the first draft here in your portal.' : ($call ? 'Thank you! Book your kickoff call next, and we’ll start right after it.' : 'Thank you! Your answers are saved.'))
            . ($fileErrors ? ' Some files didn’t upload: ' . implode(' ', $fileErrors) : ($n ? ' ' . $n . ' file' . ($n === 1 ? '' : 's') . ' added.' : '')));
        redirect('portal-project.php?id=' . (int)$p['id']);
    }
}
$a = $errors ? array_map('trim', array_intersect_key($_POST, KICKOFF_FIELDS)) : (kickoff_answers($b) ?: []);
$brief = json_decode($b['brief'], true) ?: [];
$a += ['business' => $b['business'], 'domain' => (string)($brief['domain'] ?? ''), 'pages' => (string)($brief['pages'] ?? '')];
$prod = one('SELECT name, features FROM products WHERE sku = ?', [$b['sku']]);
$pagesNote = implode(' · ', array_filter(product_lines((string)($prod['features'] ?? '')), function ($f) { return (bool)preg_match('/\b(up to|one|\d+)\b[^.]*\bpages?\b/i', $f); }));
$slug = (string)val("SELECT slug FROM meeting_types WHERE kind = 'kickoff' AND active = 1 ORDER BY position LIMIT 1");
portal_top('Kickoff form', $c);
?>
<div class="page-head"><div class="grow-head"><a class="muted small" href="portal-project.php?id=<?= (int)$p['id'] ?>">← <?= e($p['name']) ?></a><h1>Tell us about your business</h1>
  <p class="muted">About 10 minutes. Your answers become the starting point for your <?= e($prod['name'] ?? 'website') ?>, and we start as soon as you send them. Write the way you’d talk to a customer; we polish the wording.</p></div></div>
<?php if ($errors): ?><div class="notice bad" role="alert"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<?php if (!$open): ?>
  <div class="notice">We’ve already started on your website with these answers. To change something, send us a message on your project and we’ll include it.</div>
  <section class="card"><div class="note-box summary"><?= nl2br(e(kickoff_text($a))) ?></div></section>
<?php else: ?>
<?php if ($b['intake_at']): ?><div class="notice">You sent this on <?= e(date('F j', (int)$b['intake_at'])) ?>. You can still change your answers until we start building.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card stack"><?= csrf_field() ?>
  <h2>Your business</h2>
  <?php foreach (['business', 'about', 'services', 'customers', 'area', 'hours', 'why', 'goal'] as $k) kickoff_field($k, $a); ?>
  <h2>Your website</h2>
  <?php if ($pagesNote !== ''): ?><p class="muted small">Your package: <?= e($pagesNote) ?>.</p><?php endif; ?>
  <?php foreach (['pages', 'style', 'avoid', 'domain', 'current'] as $k) kickoff_field($k, $a); ?>
  <h2>What to show on the site</h2>
  <p class="muted small">Only what you fill in here goes on your website. Leave anything empty to keep it off.</p>
  <?php foreach (['phone', 'email', 'address', 'social'] as $k) kickoff_field($k, $a); ?>
  <h2>Your logo and photos</h2>
  <label class="f"><span>Add your logo, photos of your work, your team or your place <small>(optional; you can add more later)</small></span><input type="file" name="files[]" multiple accept="image/*,.pdf,.doc,.docx,.txt,.zip" data-max="<?= upload_limit() ?>"></label>
  <p class="muted small">Only send photos you took or have the rights to use. No photos yet? That’s fine: we design around clearly marked spaces you can fill later.</p>
  <label class="chk"><input type="checkbox" name="call" value="1"<?= $b['intake_call'] ? ' checked' : '' ?>> I’d also like a kickoff call before you start<?= $slug !== '' ? ' (<a href="' . e(abs_url('book.php?type=' . rawurlencode($slug))) . '" target="_blank" rel="noopener">book a time</a>)' : '' ?></label>
  <div class="save-bar"><span class="muted small">We’ll show you a draft before anything goes live.</span><button class="btn primary"><?= $b['intake_at'] ? 'Save my answers' : 'Send and start my website' ?></button></div>
</form>
<?php endif; ?>
<?php portal_bottom();

function kickoff_field(string $k, array $a): void {
    [$label, $hint, $kind, $req] = KICKOFF_FIELDS[$k];
    $v = (string)($a[$k] ?? '');
    $type = $k === 'email' ? 'email' : ($k === 'phone' ? 'tel' : 'text'); ?>
  <label class="f"><span><?= e($label) ?><?= $req ? '' : ' <small>(optional)</small>' ?></span>
    <?php if ($kind === 'text'): ?><input name="<?= $k ?>" type="<?= $type ?>" value="<?= e($v) ?>"<?= $req ? ' required' : '' ?> maxlength="300"<?= $k === 'email' ? ' autocomplete="email"' : ($k === 'phone' ? ' autocomplete="tel"' : '') ?>>
    <?php else: ?><textarea name="<?= $k ?>" rows="<?= $kind === 'lines' ? 4 : 3 ?>"<?= $req ? ' required' : '' ?> maxlength="4000"><?= e($v) ?></textarea><?php endif; ?>
    <?php if ($hint !== ''): ?><small class="muted"><?= e($hint) ?></small><?php endif; ?>
  </label>
<?php }
