<?php if (!defined('APPLICATION')) exit;
echo '<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
';

foreach ($this->Data('SiteMaps') as $SiteMap) {
   echo '<sitemap>';
   echo '<loc>'.$SiteMap['Loc'].'</loc>';
   if (GetValue('LastMod', $SiteMap))
      echo '<lastmod>'.date('c', strtotime($SiteMap['LastMod'])).'</lastmod>';
   if (GetValue('ChangeFreq', $SiteMap))
      echo '<changefreq>'.$SiteMap['ChangeFreq'].'<changefreq>';
   if (GetValue('Priority', $SiteMap))
      echo '<priority>'.$SiteMap['Priority'].'</priority>';
   echo "</sitemap>\n";
}
echo '
</sitemapindex>';