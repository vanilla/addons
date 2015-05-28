<?php if (!defined('APPLICATION')) exit(); ?>

<!-- START GeoIP.php template -->

<h1><?php echo T($this->Data['Title']); ?></h1>


<p>
    <span class="flag-wrap flag-ca">Bla Bla</span>
</p>

<pre>
    My IP: '<?php echo GeoipPlugin::myIP() ?>'
</pre>

<pre>
    <?php print_r($this->Data); ?>
</pre>

<!-- END GeoIP.php template -->
