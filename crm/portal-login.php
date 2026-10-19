<?php
// Client portal sign-in. Clients enter their email and get a one-time link, or open the invite link you sent.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/portal.php';
header('Cache-Control: no-store');
portal_session();
$dest = get('to') === 'plans' || post('to') === 'plans' ? 'portal-plans.php' : 'portal.php';
if (portal_contact()) redirect($dest);
$sent = false; $error = ''; $email = strtolower(post('email'));
if (get('k') !== '' || get('t') !== '') {
    $c = null;
    if (!too_many('portal_link', 20, 15)) {
        if (preg_match('/^[a-f0-9]{40}$/', get('k'))) $c = one('SELECT * FROM contacts WHERE portal_token = ? AND portal_access = 1', [get('k')]);
        elseif (get('t') !== '') $c = portal_use_link(get('t'));
    }
    if ($c) { portal_sign_in($c); redirect($dest); }
    record_attempt('portal_link');
    $error = 'That sign-in link has expired or was already used. Enter your email and we’ll send a new one.';
}
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (too_many('portal_email', 6, 15)) $error = 'Too many tries. Wait 15 minutes and try again.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter the email address we have for you.';
    else {
        record_attempt('portal_email');
        $c = one('SELECT * FROM contacts WHERE email = ? COLLATE NOCASE AND portal_access = 1 ORDER BY id LIMIT 1', [$email]);
        if ($c) portal_send_link($c, $dest === 'portal-plans.php' ? 'plans' : '');
        $sent = true; // same answer either way, so the page doesn't reveal who's a client
    }
}
portal_top('Sign in', null);
?>
<form class="auth-card card portal-login" method="post">
  <div><h1><?= $sent ? 'Check your email' : 'Client portal' ?></h1><p class="muted"><?= $sent ? 'If ' . e($email) . ' has access, a sign-in link is on its way. It works once and expires in ' . PORTAL_LINK_MINUTES . ' minutes. Check spam if you don’t see it.' : ($dest === 'portal-plans.php' ? 'To manage or cancel a monthly plan, enter the email you ordered with. We’ll send a sign-in link that opens your plans. No password needed.' : 'Your projects, updates, files and invoices in one place. Enter your email and we’ll send you a sign-in link. No password needed.') ?></p></div>
  <?php if ($error): ?><p class="notice bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if (!$sent): ?>
    <?= csrf_field() ?><?php if ($dest === 'portal-plans.php'): ?><input type="hidden" name="to" value="plans"><?php endif; ?>
    <label class="f"><span>Email</span><input name="email" type="email" required value="<?= e($email) ?>" autocomplete="email" autofocus></label>
    <button class="btn primary" type="submit">Email me a sign-in link <?= ARROW ?></button>
  <?php else: ?><a class="btn ghost" href="portal-login.php">Use a different email</a><?php endif; ?>
  <p class="muted small">Don’t have access yet? Email <a href="mailto:<?= e(setting('business_email')) ?>"><?= e(setting('business_email')) ?></a>.</p>
</form>
<?php portal_bottom(); ?>
