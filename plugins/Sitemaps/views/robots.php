<?php if (!defined('APPLICATION')) exit;
echo 'Sitemap: '.Url('/sitemapindex.xml', TRUE)."\n";

// TODO: Make this a settings page
$Default = 'User-agent: *
Disallow: /entry/
Disallow: /messages/
Disallow: /profile/comments/
Disallow: /profile/discussions/
Disallow: /search/';

echo C('Sitemap.Robots.Rules', $Default);