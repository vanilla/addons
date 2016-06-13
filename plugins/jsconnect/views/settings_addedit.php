<?php if (!defined('APPLICATION')) exit(); ?>
<?php Gdn_Theme::assetBegin('Help'); ?>
    <div class="Help Aside">
        <?php
        echo '<h2>', T('Need More Help?'), '</h2>';
        echo '<ul>';
        echo '<li>', Anchor(T('jsConnect Documentation'), 'http://docs.vanillaforums.com/features/sso/jsconnect/'), '</li>';
        echo '<li>', Anchor(T('jsConnect Client Libraries'), 'http://docs.vanillaforums.com/features/sso/jsconnect/overview/#your-endpoint'), '</li>';
        echo '</ul>';
        ?>
    </div>
<?php Gdn_Theme::assetEnd() ?>
    <h1><?php echo $this->Data('Title'); ?></h1>
<?php
echo $this->Form->Open(), $this->Form->Errors();
echo $this->Form->simple($this->data('_Controls'));
?>

<?php
echo '<div class="btn-group">';
echo $this->Form->Button('Save');
echo $this->Form->Button('Generate Client ID and Secret', array('Name' => 'Generate'));
echo '</div>';

echo $this->Form->Close();
