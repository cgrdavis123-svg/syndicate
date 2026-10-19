<?php
// The access details clients need to add us to their ad accounts (shown on their Setup page).
require __DIR__ . '/../app/ads-boot.php';
$user = require_user();
$keys = ['ads_access_email' => 'Email to add as a user', 'ads_google_manager' => 'Google Ads manager account ID', 'ads_meta_business' => 'Meta Business ID', 'ads_tiktok_bc' => 'TikTok Business Center ID'];
if (is_post()) {
    foreach ($keys as $k => $l) setting($k, mb_substr(trim(post($k)), 0, 120));
    flash('Saved. Clients see these on their Setup page.'); redirect('settings.php');
}
layout_top('Access details', 'settings', $user);
?>
<div class="page-head"><div><h1>Access details</h1><p class="muted">Clients follow these to give us access to their ad accounts. Leave any blank you don’t use; the steps adjust.</p></div></div>
<form method="post" class="card form-grid"><?= csrf_field() ?>
  <label class="f span2"><span>Email to add as a user</span><input name="ads_access_email" type="email" value="<?= e(setting('ads_access_email') ?: setting('business_email')) ?>"></label>
  <label class="f"><span>Google Ads manager account ID <small class="muted">(123-456-7890)</small></span><input name="ads_google_manager" value="<?= e(setting('ads_google_manager')) ?>"></label>
  <label class="f"><span>Meta Business ID</span><input name="ads_meta_business" value="<?= e(setting('ads_meta_business')) ?>"></label>
  <label class="f"><span>TikTok Business Center ID</span><input name="ads_tiktok_bc" value="<?= e(setting('ads_tiktok_bc')) ?>"></label>
  <button class="btn primary span2" type="submit">Save</button>
</form>
<?php layout_bottom(); ?>
