<?php if (!defined('APPLICATION')) { exit; } ?>

<h1><?php echo $this->data('Title'); ?></h1>
<div class="padded">
    A site map is an <a href="http://en.wikipedia.org/wiki/XML">xml</a> file that search engines can use to help index
    your site.
    <ul>
        <li>Your main site map is located here: <?php echo Gdn_Format::links(Url('/sitemapindex.xml', true)); ?>.</li>
        <li>Your <?php echo anchor('robots.txt', url('/robots.txt', true)) ?> file contains the location of this site
            map so that search engines that are aware of your site will know where to look.
        </li>
    </ul>
</div>
<div class="alert alert-info">
    <?php
    echo '<p>', sprintf(t('Learm more about %s from the following sites:'), t('sitemaps')), '</p>';
    echo '<ul>';
    echo '<li>', anchor('sitemaps.org', 'http://www.sitemaps.org/'), '</li>';
    echo '<li>', anchor('Google webmaster center', 'http://www.google.com/webmasters/'), '</li>';
    echo '</ul>';
    ?>
</div>
