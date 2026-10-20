<?php
// Served at /sitemap.xml (see .htaccess). Lists the static pages plus every published job.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/xml; charset=UTF-8');
$pages = ['', 'about.html', 'portfolio.html', 'creator-ops-media.html', 'syndicate-marketing.html', 'one-page-website.html', 'ideas.html', 'contact.html', 'privacy.html', 'cookies.html', 'terms.html', 'applicant-privacy.html', 'accessibility.html', 'careers.php', job_path('open-application')];
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n", '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($pages as $p) echo '  <url><loc>', e(abs_url($p)), "</loc></url>\n";
foreach (published_jobs() as $j) echo '  <url><loc>', e(abs_url(job_path($j['slug']))), '</loc><lastmod>', date('Y-m-d', strtotime($j['updated_at'])), "</lastmod></url>\n";
echo '</urlset>', "\n";
