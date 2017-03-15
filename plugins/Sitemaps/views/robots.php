<?php if (!defined('APPLICATION')) exit;
echo 'Sitemap: '.url('/sitemapindex.xml', true) . PHP_EOL;

// TODO: Make this a settings page
$default = 'User-agent: *
Disallow: /entry/
Disallow: /messages/
Disallow: /profile/comments/
Disallow: /profile/discussions/
Disallow: /search/';

echo c('Sitemap.Robots.Rules', $default);