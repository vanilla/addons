<?php if (!defined('APPLICATION')) exit(); ?>
    <h1><?php echo $this->Data('Title'); ?></h1>
<?php
echo $this->Form->Open();
echo $this->Form->Errors();
?>
    <div class="alert alert-info padded">
        <?php echo t('This plugin adds ShareThis buttons to the bottom of each post.'); ?>
        <?php echo t('If you are using the <a href="http://vanillaforums.com/features/social-connect" target="_blank">Social Connect</a> plugin to allow your community members to sign in with Facebook or Twitter, the ShareThis plugin will automatically retrieve their information for seamless sharing.'); ?>
    </div>
    <ul>
        <li class="form-group row">
            <div class="label-wrap">
                <?php echo $this->Form->Label("ShareThis Publisher Number", 'Plugin.ShareThis.PublisherNumber'); ?>
                <div class="info">
                    <?php echo t('<a href="http://sharethis.com/register" target="_blank">Register with ShareThis for a publishers account</a>, which gives you support, publishing tools, and analytics. If you do not have, or want a publisher account please leave this field blank.'); ?>
                </div>
            </div>
            <?php echo $this->Form->textBoxWrap('Plugin.ShareThis.PublisherNumber'); ?>
        </li>
        <li class="form-group row">
            <?php
            echo $this->Form->labelWrap("'via' Handle", 'Plugin.ShareThis.ViaHandle');
            echo $this->Form->textBoxWrap('Plugin.ShareThis.ViaHandle');
            ?>
        </li>
        <li class="form-group row">
            <div class="input-wrap no-label">
                <?php echo $this->Form->CheckBox('Plugin.ShareThis.CopyNShare', "Enable 'CopyNShare' functionality"); ?>
            </div>
        </li>
    </ul>
    <div class="form-footer js-modal-footer">
        <?php echo $this->Form->Button('Save'); ?>
    </div>
<?php echo $this->Form->Close();


