<?php if (!defined('APPLICATION')) exit(); ?>

<!-- START GeoIP.php template -->

<h1><?php echo T($this->Data['Title']); ?></h1>

<div class="Info">
    <?=T($this->Data['PluginDescription'])?>
</div>


<div class="Info">
    <p style="text-transform: uppercase;">* NOTE: This plugin is very much in Alpha release. It has only been tested on Ubuntu 14.04LTS and is only supported on Linux systems (for now).</p>
    <p style="text-transform: uppercase;">* NOTE: Please IMPORT CSV file from GeoIP before enabling anything. Not doing so will break pages.</p>
    <p style="text-transform: uppercase;">* NOTE: Please use on MemCache enabled systems or page loads will take a couple seconds.</p>
</div>

<div class="Info">
    <p>
        <a href="/plugin/geoip/import"><b>&rarr; Click here to IMPORT GeoIP2-Lite City CSV file into your database. &larr;</b></a>
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
