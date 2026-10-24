<?php
// Team portal sign-in: enter your email, get a one-time link. Says the same thing whether or not the email is on the team.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
team_headers();
if (current_member()) redirect('team/');

$sent = false; $error = '';
$email = post('email');
if (is_post()) {
    if (!csrf_ok()) $error = 'Your session expired. Please try again.';
    elseif (post('website') !== '' || !form_stamp_ok(post('stamp'), 2)) $error = 'Something went wrong. Please try again.';
    elseif (too_many('team_login', 5, 15)) $error = 'Too many requests. Wait 15 minutes and try again.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter the email address Grady invited.';
    else {
        record_attempt('team_login');
        $st = db()->prepare("SELECT * FROM team_members WHERE email = ? AND status != 'left'");
        $st->execute([$email]);
        if ($m = $st->fetch()) team_mail_link($m, false);
        $sent = true;
    }
}
team_header('Sign in', null);
?>
<form class="auth-card form spot" method="post">
  <a href="./" class="brand"><svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span class="brand-name">Syndicate<small>Team</small></span></a>
  <?php if ($sent): ?>
    <div><h1>Check your email</h1><p class="muted">If <?= e($email) ?> is on the Syndicate team, a sign-in link is on its way. It works once and expires in <?= TEAM_LINK_MINUTES ?> minutes.</p></div>
    <p class="form-note">Nothing after a few minutes? Check spam, or email <a href="mailto:hello@stusyn.com" style="color:var(--text)">hello@stusyn.com</a>.</p>
  <?php else: ?>
    <div><h1>Team sign-in</h1><p class="muted">Your onboarding checklist, documents and tools. We’ll email you a one-time sign-in link.</p></div>
    <?php if ($error): ?><p class="notice warn" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($m = flash()): ?><p class="notice" role="status"><?= e($m) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="stamp" value="<?= e(form_stamp()) ?>">
    <div class="hp" aria-hidden="true"><label for="t-web">Website</label><input id="t-web" name="website" tabindex="-1" autocomplete="off"></div>
    <div class="f"><label for="t-email">Email</label><input id="t-email" name="email" type="email" required value="<?= e($email) ?>" autocomplete="email" autofocus></div>
    <button class="btn btn-primary" type="submit" style="justify-content:center">Email me a sign-in link <?= ARROW ?></button>
    <p class="form-note">Not on the team yet? See open roles at <a href="careers.php" style="color:var(--text)">stusyn.com/careers</a>.</p>
  <?php endif; ?>
</form>
<?php team_footer(); ?>
