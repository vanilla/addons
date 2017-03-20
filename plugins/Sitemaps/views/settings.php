<?php if (!defined('APPLICATION')) exit; ?>
<h1><?php echo $this->data('Title'); ?></h1>
<div class="padded">
   <p>
      A site map is an <a href="http://en.wikipedia.org/wiki/XML" target="_blank">xml</a> file that search engines can use to help index your site.
   </p>
   <p>
      <ul>
         <li>Your main site map is located here: <?php echo Gdn_Format::links(url('/sitemapindex.xml', true)); ?>.</li>
         <li>Your <?php echo anchor('robots.txt', url('/robots.txt', true)) ?> file contains the location of this site map so that search engines that are aware of your site will know where to look.</li>
      </ul>
   </p>
   <?php
      echo '<p>', sprintf(t('Learm more about %s from the following sites:'), t('sitemaps')), '</p>';
      echo '<ul>';
      echo '<li>', anchor('sitemaps.org', 'http://www.sitemaps.org/', '', ['target' => '_blank']), '</li>';
      echo '<li>', anchor('Google webmaster center', 'http://www.google.com/webmasters/',  '', ['target' => '_blank']), '</li>';
      echo '</ul>';
   ?>
</div>
