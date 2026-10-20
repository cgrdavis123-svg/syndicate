<?php
// sitemap.xml for the public help center.
require __DIR__ . '/../app/wiki-boot.php';
header_remove('X-Robots-Tag');
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>' . e(wiki_url()) . "</loc></url>\n";
foreach (array_keys(WIKI_CATEGORIES) as $k) echo '  <url><loc>' . e(wiki_url('c/' . $k)) . "</loc></url>\n";
foreach (all("SELECT slug, updated_at FROM wiki_articles WHERE audience = 'public' AND status = 'published' ORDER BY slug") as $a) echo '  <url><loc>' . e(wiki_url($a['slug'])) . '</loc><lastmod>' . e(substr($a['updated_at'], 0, 10)) . "</lastmod></url>\n";
echo "</urlset>\n";
