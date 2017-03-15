<?php if (!defined('APPLICATION')) exit;
$SPACE = '  ';

echo '<?xml version="1.0" encoding="UTF-8"?>

<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

';

foreach ($this->Data('SiteMaps') as $siteMap) {

   echo $SPACE . '<sitemap>' . PHP_EOL;
   echo str_repeat($SPACE, 2) . '<loc>'.$siteMap['Loc'].'</loc>' . PHP_EOL;
   if (val('LastMod', $siteMap)) {
      echo str_repeat($SPACE, 2) . '<lastmod>'.date('c', strtotime($siteMap['LastMod'])).'</lastmod>' . PHP_EOL;
   }
   if (val('ChangeFreq', $siteMap)) {
      echo str_repeat($SPACE, 2) . '<changefreq>'.$siteMap['ChangeFreq'].'<changefreq>' . PHP_EOL;
   }
   if (val('Priority', $siteMap)) {
      echo str_repeat($SPACE, 2) . '<priority>'.$siteMap['Priority'].'</priority>' . PHP_EOL;
   }
   echo $SPACE . '</sitemap>' . PHP_EOL . PHP_EOL;
}
echo '</sitemapindex>
';