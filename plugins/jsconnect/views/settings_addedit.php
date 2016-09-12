<?php if (!defined('APPLICATION')) exit(); ?>

<?php Gdn_Theme::assetBegin('Help'); ?>
    <div class="Help Aside">
        <?php
        echo '<h2>', t('Need More Help?'), '</h2>';
        echo '<ul>';
        echo '<li>'.anchor(t('jsConnect Documentation'), 'http://docs.vanillaforums.com/features/sso/jsconnect/').'</li>';
        echo '<li>'.anchor(t('jsConnect Client Libraries'), 'http://docs.vanillaforums.com/features/sso/jsconnect/overview/#your-endpoint').'</li>';
        echo '</ul>';
        ?>
    </div>
<?php Gdn_Theme::assetEnd() ?>
    <h1><?php echo $this->data('Title'); ?></h1>
<?php
echo $this->Form->open(), $this->Form->errors();
echo $this->Form->simple($this->data('_Controls'));

echo '<div class="js-modal-footer form-footer buttons">';
echo $this->Form->button('Generate Client ID and Secret', ['Name' => 'Generate', 'class' => 'btn btn-secondary js-generate']);
echo $this->Form->button('Save');
echo '</div>';

echo $this->Form->close();
