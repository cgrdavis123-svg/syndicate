<?php
// Ask us a question. Creates a task and emails us; we reply within one business day.
require __DIR__ . '/../app/wiki-boot.php';
$staff = wiki_staff();
$err = ''; $done = false;
$from = wiki_slug(get('from') ?: post('from'));
if (is_post()) {
    if (trim(post('website')) !== '') $done = true; // spam trap
    elseif (!wiki_same_site()) $err = 'Please send the form from this page.';
    elseif (!captcha_ok(post('cf-turnstile-response'))) $err = CAPTCHA_FAIL;
    elseif (too_many('wiki_ask', 5, 60)) $err = 'That’s a lot of questions at once. Wait a little, or email ' . setting('business_email') . '.';
    else { record_attempt('wiki_ask'); $r = wiki_ask(post('name'), post('email'), post('question'), $from ? wiki_url($from) : (post('q') !== '' ? 'Search: ' . post('q') : '')); if ($r['ok']) $done = true; else $err = $r['error']; }
}
wiki_top('Ask us · Syndicate Help', 'Send Syndicate Marketing a question. We reply within one business day.', ['staff' => $staff, 'index' => false]);
?>
<div class="w-wrap w-narrow">
  <?php if ($done): ?><h1>Thanks, we’ve got it</h1><p class="w-lede">We’ll reply by email within one business day. In the meantime, <a href="./">browse the help center</a>.</p>
  <?php else: ?>
  <h1>Ask us a question</h1><p class="w-lede">About a package, your project, your ads, billing, anything. A real person replies within one business day.</p>
  <?php if ($err): ?><p class="w-alert" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="w-form"><input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="q" value="<?= e(get('q') ?: post('q')) ?>">
    <div class="w-hp" aria-hidden="true"><label>Leave empty <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="w-row"><label>Your name<input name="name" required autocomplete="name" value="<?= e(post('name')) ?>"></label><label>Email<input name="email" type="email" required autocomplete="email" value="<?= e(post('email')) ?>"></label></div>
    <label>Your question<textarea name="question" rows="5" required><?= e(post('question') ?: get('q')) ?></textarea></label>
    <?= captcha_widget('wiki_ask') ?>
    <button class="w-btn" type="submit">Send question</button>
    <p class="w-dim">We use your email only to answer you. See our <a href="https://syndicatemarketing.co/privacy.html">privacy policy</a>.</p>
  </form>
  <?= captcha_script() ?>
  <?php endif; ?>
</div>
<?php wiki_bottom(); ?>
