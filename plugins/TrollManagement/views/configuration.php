<?php if (!defined('APPLICATION')) exit(); ?>

<h1><?php echo t($this->Data['Title']); ?></h1>

<?php
echo $this->Form->open();
echo $this->Form->errors();
$fingerprintsEnabled = (bool)c('TrollManagement.PerFingerprint.Enabled');
$fingerprintsChildrenAttributes = [];
if (!$fingerprintsEnabled) {
    $fingerprintsChildrenAttributes['disabled'] = 'disabled';
}
?>
<ul>
    <li class="form-group">
<?php
        echo $this->Form->toggle(
            'TrollManagement.PerfingerPrint.Enabled',
            t('Enable fingerprint checks.'),
            [
                'id' => 'IsFingerprintChecksEnabled',
                'data-children' => 'js-fingerprints-inputs'
            ]
        );
?>
    </li>
    <li class="form-group js-fingerprints-inputs" <?php echo $fingerprintsEnabled ? '' : ' style="display:none;"'?>>
<?php
        echo $this->Form->labelWrap(
            t('Maximum allowed number of user accounts tied to a single fingerprint.'),
            'TrollManagement.PerFingerprint.MaxUserAccounts'
        );
        echo $this->Form->textBoxWrap('TrollManagement.PerFingerprint.MaxUserAccounts', $fingerprintsChildrenAttributes);
?>
    </li>
</ul>
<?php echo $this->Form->close('Save'); ?>
