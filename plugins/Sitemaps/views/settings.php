<?php if (!defined('APPLICATION')) exit; ?>
<div class="Help Aside">
   <?php
   echo '<h2>', T('Need More Help?'), '</h2>';
   echo '<ul>';
   echo '<li>', Anchor('sitemaps.org', 'http://www.sitemaps.org/'), '</li>';
   echo '<li>', Anchor('Google webmaster center', 'http://www.google.com/webmasters/'), '</li>';
   echo '</ul>';
   ?>
</div>
<h1><?php echo $this->Data('Title'); ?></h1>
<div class="Info">
   A site map is an <a href="http://en.wikipedia.org/wiki/XML">xml</a> file that search engines can use to help index your site.
   <ul class="NormalList">
      <li>Your main site map is located here: <?php echo Gdn_Format::Links(Url('/sitemapindex.xml', TRUE)); ?>.</li>
      <li>Your <?php echo Anchor('robots.txt', Url('/robots.txt', TRUE)) ?> file contains the location of this site map so that search engines that are aware of your site will know where to look.</li>
   </ul>
</div>
