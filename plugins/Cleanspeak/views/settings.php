<h1>Cleanspeak Settings</h1>


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
