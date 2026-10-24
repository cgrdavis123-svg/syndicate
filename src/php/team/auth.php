<?php
// Opens an emailed sign-in link. The link is only spent when the person presses the button,
// so email scanners that open links in advance can't use it up.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
team_headers();

$tok = get('t') !== '' ? get('t') : post('t');
if (is_post()) {
    if (!csrf_ok()) { flash('Your session expired. Open the link from your email again.'); redirect('team/login.php'); }
    $m = team_link_member($tok, true);
    if (!$m) { flash('That link has expired or was already used. Enter your email for a new one.'); redirect('team/login.php'); }
    team_sign_in($m);
    if ($m['status'] === 'onboarding' && !$m['last_seen_at']) flash('Welcome, ' . team_first($m) . '. Here’s your checklist.');
    redirect('team/');
}
$m = team_link_member($tok, false);
team_header('Sign in', null);
?>
<form class="auth-card form spot" method="post">
  <a href="./" class="brand"><svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x=".5" y=".5" width="31" height="31" rx="8.5" fill="#0f1216" stroke="rgba(255,255,255,.14)"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.6" stroke-linecap="round"/></svg><span class="brand-name">Syndicate<small>Team</small></span></a>
  <?php if ($m): ?>
    <div><h1>Hi <?= e(team_first($m)) ?></h1><p class="muted">Press the button to sign in to the Syndicate team portal.</p></div>
    <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($tok) ?>">
    <button class="btn btn-primary" type="submit" style="justify-content:center">Sign in <?= ARROW ?></button>
  <?php else: ?>
    <div><h1>Link expired</h1><p class="muted">This sign-in link has expired or was already used.</p></div>
    <a class="btn btn-primary" href="team/login.php" style="justify-content:center">Get a new link <?= ARROW ?></a>
  <?php endif; ?>
</form>
<?php team_footer(); ?>
