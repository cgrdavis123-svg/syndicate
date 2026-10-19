<?php
// Syndicate CRM settings. Most business details (invoice header, rates, numbering) are edited
// in the app under Settings. These are the server-level settings.
return [
    // Public address of the CRM, with no trailing slash.
    'app_url' => 'https://crm.stusyn.com',

    // Path the CRM is installed under. Use '/' when it's the root of the subdomain.
    'base_path' => '/',

    // Database and backups. Blocked from the web by storage/.htaccess.
    'storage_dir' => __DIR__ . '/../storage',

    // One-time key needed to create the first account at /setup.php.
    'setup_key' => 'CHANGE-ME-to-a-long-random-string',

    // The stusyn.com website database, used to import contact form messages and consultation
    // requests as leads. Leave empty to auto-detect it on the same cPanel account, or set the full
    // path, e.g. '/home/USERNAME/public_html/storage/syndicate.sqlite'.
    'site_db' => '',

    // Cloudflare Turnstile bot check on public forms (dash.cloudflare.com → Turnstile → Add widget).
    // Use the same widget for all three sites. Leave both empty to turn the check off.
    'turnstile_site' => '',
    'turnstile_secret' => '',

    'timezone' => 'America/New_York',

    // Stripe's API address. Leave as is; it's only changed for testing.
    'stripe_api' => 'https://api.stripe.com',

    // Address of the ads app (the ads/ folder in this install). In cPanel, create the subdomain
    // ads.stusyn.com with its document root set to this install's ads folder.
    'ads_url' => 'https://ads.stusyn.com',

    // Address of the help center (the wiki/ folder in this install). Create the subdomain wiki.stusyn.com
    // with its document root set to this install's wiki folder.
    'wiki_url' => 'https://wiki.stusyn.com',
];
