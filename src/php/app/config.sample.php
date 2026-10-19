<?php
// Site configuration. These defaults are set for stusyn.com on cPanel.
return [
    // Public address of the site, with no trailing slash. Used in emails, the sitemap and job SEO data.
    'site_url' => 'https://stusyn.com',

    // Path the site is installed under. Use '/' when it lives directly in public_html.
    'base_path' => '/',

    // Who gets notified.
    'careers_to' => 'careers@stusyn.com',   // new job applications
    'contact_to' => 'hello@stusyn.com',     // contact form messages

    // Addresses emails are sent from. Use addresses on your own domain so mail isn't flagged as spam.
    'mail_from'              => 'hello@stusyn.com',    // orders, contact form and everything else
    'mail_from_name'         => 'Syndicate Holdings Group',
    'careers_mail_from'      => 'careers@stusyn.com',  // job application emails only
    'careers_mail_from_name' => 'Syndicate Careers',

    // Where the database and uploaded resumes are stored. The folder is blocked from the web by
    // storage/.htaccess. For extra safety you can move it above public_html and update this path.
    'storage_dir' => __DIR__ . '/../storage',

    // One-time key needed to create the first admin account at /admin/setup.php.
    // After the first account exists, setup is disabled.
    'setup_key' => 'CHANGE-ME-to-a-long-random-string',

    // Applications (with resumes) and contact messages are deleted automatically after this many days.
    // Keep this in line with the Privacy Policy and Applicant Privacy Notice (2 years).
    'retention_days' => 730,

    // Resume uploads.
    'max_upload_mb' => 8,

    // Job links use the real files (careers.php, job.php?slug=…), which work on every host.
    // Set to true only if your server's rewrites are confirmed working, to use /careers/job-name links instead.
    'pretty_urls' => false,


    // Cloudflare Turnstile bot check on public forms (dash.cloudflare.com → Turnstile → Add widget).
    // Use the same widget for all three sites. Leave both empty to turn the check off.
    'turnstile_site' => '',
    'turnstile_secret' => '',

    'timezone' => 'America/New_York',
];
