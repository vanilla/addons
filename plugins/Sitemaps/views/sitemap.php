<?php if (!defined('APPLICATION')) exit;
echo '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
';


$Total = 0;
foreach ($this->Data('Urls') as $Url) {
   $PageCount = GetValue('PageCount', $Url, 1);
   
   for ($i = 1; $i <= $PageCount; $i++) {
      $Loc = str_replace('{Page}', 'p'.$i, $Url['Loc']);
      
      echo '<url>';
      echo '<loc>'.$Loc.'</loc>';
      if (GetValue('LastMod', $Url))
         echo '<lastmod>'.gmdate('c', strtotime($Url['LastMod'])).'</lastmod>';
      if (GetValue('ChangeFreq', $Url))
         echo '<changefreq>'.$Url['ChangeFreq'].'<changefreq>';
      if (GetValue('Priority', $Url))
         echo '<priority>'.$Url['Priority'].'</priority>';
      echo "</url>\n";
      $Total++;
      
      if ($Total >= 50000)
         break;
   }
   
   if ($Total >= 50000) {
      break;
   }
}
echo '</urlset>';