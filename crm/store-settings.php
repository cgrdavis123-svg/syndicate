<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/shop.php';
$user = require_user();
$error = '';
if (is_post()) {
    $a = post('action');
    if ($a === 'save') {
        $site = rtrim(post('shop_site_url'), '/');
        if ($site !== '' && !preg_match('#^https?://#i', $site)) $site = 'https://' . $site;
        $u = parse_url($site);
        if ($site !== '' && (empty($u['host']) || !empty($u['path']) && $u['path'] !== '/')) $error = 'Enter just the website address, like https://syndicatemarketing.co';
        else {
            setting('shop_site_url', $site === '' ? '' : strtolower($u['scheme']) . '://' . strtolower($u['host']) . (isset($u['port']) ? ':' . $u['port'] : ''));
            setting('shop_promo', post('shop_promo') === '1' ? '1' : '0');
            setting('shop_tax', post('shop_tax') === '1' ? '1' : '0');
            setting('shop_notify', filter_var(post('shop_notify'), FILTER_VALIDATE_EMAIL) ? post('shop_notify') : '');
            $sk = post('stripe_secret'); $wh = post('stripe_webhook_secret');
            if ($sk !== '' && !preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]{10,}$/', $sk)) $error = 'That doesn’t look like a Stripe secret key. It starts with sk_live_ (or sk_test_ for testing).';
            elseif ($wh !== '' && !preg_match('/^whsec_[A-Za-z0-9]{10,}$/', $wh)) $error = 'That doesn’t look like a webhook signing secret. It starts with whsec_.';
            else {
                if ($sk !== '') setting('stripe_secret', mail_encrypt($sk));
                if ($wh !== '') setting('stripe_webhook_secret', mail_encrypt($wh));
                if ($sk !== '') {
                    try { $acct = stripe('GET', 'account'); flash('Saved. Connected to Stripe' . (!empty($acct['settings']['dashboard']['display_name']) ? ' as ' . $acct['settings']['dashboard']['display_name'] : '') . (stripe_live() ? '.' : ' in test mode.')); }
                    catch (Throwable $e) { flash('Saved, but Stripe said: ' . $e->getMessage()); }
                } else flash('Store settings saved.');
                redirect('store-settings.php');
            }
        }
    }
    if ($a === 'disconnect') { setting('stripe_secret', ''); setting('stripe_webhook_secret', ''); flash('Stripe keys removed. The store stops taking payments.'); redirect('store-settings.php'); }
}
$hasKey = stripe_key() !== ''; $hasHook = stripe_key('stripe_webhook_secret') !== '';
$lastEvent = one('SELECT * FROM stripe_events ORDER BY created_at DESC LIMIT 1');
$hook = abs_url('stripe-webhook.php');
$events = ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'checkout.session.expired', 'invoice.paid', 'invoice.payment_failed', 'customer.subscription.updated', 'customer.subscription.deleted', 'charge.refunded'];
layout_top('Store settings', 'store', $user);
?>
<div class="page-head"><div><a class="muted small" href="store.php">← Store</a><h1>Store settings</h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<div class="grid-detail">
  <form method="post" class="card form-grid" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="card-h span2"><h2>Store</h2><span class="live-pill<?= shop_ready() ? ' on' : '' ?>"><i></i><?= shop_ready() ? (stripe_live() ? 'Taking payments' : 'Test mode') : 'Not taking payments' ?></span></div>
    <label class="f span2"><span>Store website <small class="muted">(the <?= e(SHOP_BRAND) ?> site’s address; only this site can use the checkout)</small></span><input name="shop_site_url" type="text" inputmode="url" value="<?= e(setting('shop_site_url')) ?>" placeholder="https://syndicatemarketing.co"></label>
    <label class="f span2"><span>Stripe secret key <small class="muted"><?= $hasKey ? '(saved' . (stripe_live() ? ', live' : ', test mode') . ' — leave empty to keep it)' : '(Stripe → Developers → API keys → Secret key)' ?></small></span><input name="stripe_secret" type="password" placeholder="<?= $hasKey ? '•••••••••••• saved' : 'sk_live_…' ?>" spellcheck="false"></label>
    <label class="f span2"><span>Webhook signing secret <small class="muted"><?= $hasHook ? '(saved — leave empty to keep it)' : '(from step 2 on the right)' ?></small></span><input name="stripe_webhook_secret" type="password" placeholder="<?= $hasHook ? '•••••••••••• saved' : 'whsec_…' ?>" spellcheck="false"></label>
    <label class="f span2"><span>Email new orders to</span><input name="shop_notify" type="email" value="<?= e(setting('shop_notify')) ?>" placeholder="hello@stusyn.com"></label>
    <label class="chk span2"><input type="checkbox" name="shop_promo" value="1"<?= setting('shop_promo') === '1' ? ' checked' : '' ?>> <span>Let customers enter promo codes at checkout <small class="muted">(create them in Stripe → Products → Coupons)</small></span></label>
    <label class="chk span2"><input type="checkbox" name="shop_tax" value="1"<?= setting('shop_tax') === '1' ? ' checked' : '' ?>> <span>Charge sales tax with Stripe Tax <small class="muted">(turn on Stripe Tax and add your registrations first)</small></span></label>
    <div class="save-bar span2"><?php if ($hasKey): ?><button class="link-danger" type="submit" name="action" value="disconnect" formnovalidate data-confirm="Remove the Stripe keys? The website stops taking payments until you add them again.">Disconnect Stripe</button><?php else: ?><span></span><?php endif; ?><button class="btn primary sm" type="submit" name="action" value="save">Save</button></div>
  </form>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>Connect Stripe</h2></div>
      <ol class="steps-list small">
        <li><b>Secret key.</b> In Stripe, open <b>Developers → API keys</b>, reveal the <b>Secret key</b> and paste it on the left. Use the <code>sk_test_…</code> key first if you want to try a test order.</li>
        <li><b>Webhook.</b> In <b>Developers → Webhooks → Add endpoint</b>, paste this address:
          <div class="code-box"><pre id="hookUrl"><?= e($hook) ?></pre><button class="btn ghost xs" type="button" data-copy-text="#hookUrl">Copy</button></div>
          Select these events, save, then copy the <b>Signing secret</b> to the left:
          <div class="code-box"><pre id="hookEvents"><?= e(implode("\n", $events)) ?></pre><button class="btn ghost xs" type="button" data-copy-text="#hookEvents">Copy</button></div></li>
        <li><b>Receipts.</b> In <b>Settings → Customer emails</b>, turn on <b>Successful payments</b> so customers get a Stripe receipt.</li>
        <li><b>Customer portal (optional).</b> In <b>Settings → Billing → Customer portal</b>, turn it on so monthly customers can update their card.</li>
      </ol>
      <p class="muted small"><?= $hasHook ? ($lastEvent ? '<span class="dot ok"></span> Last event from Stripe ' . e(ago($lastEvent['created_at'])) . ' (' . e($lastEvent['type']) . ').' : '<span class="dot off"></span> Waiting for the first event from Stripe.') : 'Without the webhook, payments are still recorded when customers return to the website, and the 15-minute cron job checks any it missed. Monthly renewals need the webhook.' ?></p>
    </section>
    <section class="card"><div class="card-h"><h2>On the website</h2></div>
      <p class="muted small">The website loads products and prices from this CRM and sends checkouts here. Edit what’s for sale in <a href="store-products.php">Products</a>. The store API is <code><?= e(abs_url('shop-api.php')) ?></code>.</p>
    </section>
  </div>
</div>
<?php layout_bottom(); ?>
