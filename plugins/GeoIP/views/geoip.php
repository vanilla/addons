<?php if (!defined('APPLICATION')) exit(); ?>

<!-- START GeoIP.php template -->

<h1><?php echo T($this->Data['Title']); ?></h1>

<div class="Info">
    <?=T($this->Data['PluginDescription'])?>
</div>


<?php
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<ul>
    <li>
        <?php
        echo $this->Form->Label('Log GeoIP information upon login.', 'Plugin.GeoIP.doLogin');
        echo $this->Form->Checkbox('Plugin.GeoIP.doLogin');
        ?>
    </li>
    <li>
        <?php
        echo $this->Form->Label('Display flags in discussions.', 'Plugin.GeoIP.doDiscussions');
        echo $this->Form->Checkbox('Plugin.GeoIP.doDiscussions');
        ?>
    </li>
</ul>
<?php
echo $this->Form->Close('Save');
?>


<pre>
    <?php print_r($this->Data); ?>
</pre>

<!-- END GeoIP.php template -->
