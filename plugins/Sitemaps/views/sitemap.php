<?php if (!defined('APPLICATION')) { exit; }

echo '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
';


$Total = 0;
foreach ($this->data('Urls') as $Url) {
    $PageCount = val('PageCount', $Url, 1);

    for ($i = 1; $i <= $PageCount; $i++) {
        $Loc = str_replace('{Page}', 'p'.$i, $Url['Loc']);

        echo '<url>';
        echo '<loc>'.$Loc.'</loc>';
        if (val('LastMod', $Url)) {
            echo '<lastmod>'.gmdate('c', strtotime($Url['LastMod'])).'</lastmod>';
        }
        if (val('ChangeFreq', $Url)) {
            echo '<changefreq>'.$Url['ChangeFreq'].'<changefreq>';
        }
        if (val('Priority', $Url)) {
            echo '<priority>'.$Url['Priority'].'</priority>';
        }
        echo "</url>\n";
        $Total++;

        if ($Total >= 50000) {
            break;
        }
    }

    if ($Total >= 50000) {
        break;
    }
}
echo '</urlset>';
