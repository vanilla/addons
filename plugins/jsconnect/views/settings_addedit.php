<?php if (!defined('APPLICATION')) exit(); ?>
    <div class="Help Aside">
        <?php
        echo '<h2>', T('Need More Help?'), '</h2>';
        echo '<ul>';
        echo '<li>', Anchor(T('jsConnect Documentation'), 'http://vanillaforums.org/docs/jsconnect'), '</li>';
        echo '<li>', Anchor(T('jsConnect Client Libraries'), 'http://vanillaforums.org/docs/jsconnect#libraries'), '</li>';
        echo '</ul>';
        ?>
    </div>
    <h1><?php echo $this->Data('Title'); ?></h1>
<?php
echo $this->Form->Open(), $this->Form->Errors();
echo $this->Form->simple($this->data('_Controls'));
?>

<?php
echo '<div class="Buttons">';
echo $this->Form->Button('Save');
echo $this->Form->Button('Generate Client ID and Secret', array('Name' => 'Generate'));
echo '</div>';

echo $this->Form->Close();
