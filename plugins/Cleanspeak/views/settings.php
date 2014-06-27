<h1>Cleanspeak</h1>

<script>
    $(document).ready(function(){
        $("#toggle").click(function(){
            window.location = '<?php echo Url('/settings/cleanspeaktoggle'); ?>';
        });
    });
</script>

<?php if (!$this->Data['Enabled'] && !$this->Data['IsConfigured']) { ?>

<div class="Wrap Warning"><?php echo T('Your Cleanspeak Integration is NOT complete.  Enabling the plugin before it has
been configured will force all new content to go into the premoderation queue.'); ?>
</div>

<?php } ?>


<div class="Wrap">
    <button id="toggle" class="Button">
        <?php
        if($this->Data['Enabled']) {
            echo T('Disable');
        } else {
            echo T('Enable');
        }
        ?>
    </button>
    <span class="Wrap"><?php echo T('Send new discussions, comments, activity posts and comments to Cleanspeak for premoderation.'); ?></span>

</div>

<h1><?php echo T('Settings'); ?></h1>

<?php
// Settings
echo $this->Form->Open();
echo $this->Form->Errors();
?>

<ul>

    <li>
        <?php
        echo $this->Form->Label('Cleanspeak API Url', 'ApiUrl');
        echo $this->Form->TextBox('ApiUrl');
        ?>
    </li>

    <li>
        <?php
        echo $this->Form->Label('Application ID', 'ApplicationID');
        echo $this->Form->TextBox('ApplicationID');
        ?>
    </li>

</ul>

</ul>
<?php
echo $this->Form->Close('Save');
?>
<br />

