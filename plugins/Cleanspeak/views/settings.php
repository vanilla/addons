<h1>Cleanspeak Settings</h1>


<?php
// Settings
echo $this->Form->Open();
echo $this->Form->Errors();
?>

<ul>
    <li>
        <?php
        echo $this->Form->Label('ApplicationID', 'Plugins.Cleanspeak.ApplicationID');
        echo $this->Form->TextBox('Plugins.Cleanspeak.ApplicationID');
        ?>
    </li>
</ul>

</ul>
<?php
echo $this->Form->Close('Save');
?>
<br />
