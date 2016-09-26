<?php if (!defined('APPLICATION')) exit();
echo Wrap($this->Data('Title'), 'h1');
echo $this->Form->Open();
echo $this->Form->Errors();

Gdn_Theme::assetBegin('Help');
echo '<h2>'.sprintf(t('About %s'), $this->Data('Title')).'</h2>';
?>
<p>
    <?php echo T('Civil Tongue lets you make a list of words that are not allowed on the forum and replace them. This plugins also helps to make your forum suitable for younger audiences.'); ?>
</p>
<?php
Gdn_Theme::assetEnd();
?>
    <ul>
        <li class="form-group">
            <div class="label-wrap">
                <?php echo $this->Form->label('Forbidden words', 'Plugins.CivilTongue.Words');
                echo wrap(t('Separate each word with a semi-colon ";"'), 'div', ['class' => 'info']); ?>
            </div>
            <div class="input-wrap">
                <?php echo $this->Form->TextBox('Plugins.CivilTongue.Words', array('MultiLine' => TRUE)); ?>
            </div>
        </li>
        <li class="form-group">
            <div class="label-wrap">
                <?php echo $this->Form->label('Replacement word', 'Plugins.CivilTongue.Replacement');
                echo wrap(t('Enter the word you wish to replace the banned word with.'), 'div', ['class' => 'info']); ?>
            </div>
            <div class="input-wrap">
                <?php echo $this->Form->TextBox('Plugins.CivilTongue.Replacement'); ?>
            </div>
        </li>
    </ul>

<div class="form-footer js-modal-footer">
    <?php echo $this->Form->Close('Save'); ?>
</div>
