<?php if (!defined('APPLICATION')) exit;
$SPACE = '  ';

echo '<?xml version="1.0" encoding="UTF-8"?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
$total = 0;
foreach ($this->Data('Urls') as $url) {
   $PageCount = val('PageCount', $url, 1);
   
   for ($i = 1; $i <= $PageCount; $i++) {
      $loc = str_replace('{Page}', 'p'.$i, $url['Loc']);
      echo PHP_EOL . $SPACE . '<url>' . PHP_EOL;
      echo str_repeat($SPACE, 2) . '<loc>'.$loc.'</loc>' . PHP_EOL;

      if( val('LastMod', $url) ){
         echo str_repeat($SPACE, 2) . '<lastmod>'.gmdate('c', strtotime($url['LastMod'])).'</lastmod>' . PHP_EOL;
      }

      if( val('ChangeFreq', $url) ){
         echo str_repeat($SPACE, 2) . '<changefreq>'.$url['ChangeFreq'].'<changefreq>' . PHP_EOL;
      }
      if( val('Priority', $url) ) {
         echo str_repeat($SPACE, 2) . '<priority>'.$url['Priority'].'</priority>' . PHP_EOL;
      }
      echo $SPACE . "</url>" . PHP_EOL;
      $total++;
      
      if( $total >= 50000 ) {
         break;
      }
   }
   
   if( $total >= 50000 ) {
      break;
   }
}
echo '</urlset>' . PHP_EOL;
