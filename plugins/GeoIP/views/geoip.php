<?php if (!defined('APPLICATION')) exit(); ?>

<!-- START GeoIP.php template -->

<h1><?php echo T($this->Data['Title']); ?></h1>

<div class="Info">
    <?=T($this->Data['PluginDescription'])?>
</div>


<div class="Info">
    <p style="text-transform: uppercase;">Please take note that this plugin is only supported in Linux (for now).</p>
</div>

<div class="Info">
    <p>
        <a href="/plugin/geoip/import">&rarr; Click here to IMPORT GeoIP2-Lite City CSV file into your database.</a>
        <br/>
        (NOTE: Please only click once. This should take couple minutes)
    </p>
</div>


<?php
    echo $this->Form->Open();
    echo $this->Form->Errors();
?>

    <ul>
        <li>
            <?php
                echo $this->Form->Label("Display flags in discussions.", 'Plugin.GeoIP.doDiscussions');
                echo $this->Form->Checkbox("Plugin.GeoIP.doDiscussions");
            ?>
        </li>
        <li>
            <?php
                echo $this->Form->Label("Log user's GeoIP information upon login.", 'Plugin.GeoIP.doLogin');
                echo $this->Form->Checkbox("Plugin.GeoIP.doLogin");
            ?>
        </li>
    </ul>

<?php
    echo $this->Form->Close('Save');
?>


<!-- END GeoIP.php template -->
