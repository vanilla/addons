<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::session();
?>
<h1><?php echo T('Touch Icon'); ?></h1>
<?php
echo $this->Form->open(array('enctype' => 'multipart/form-data'));
echo $this->Form->errors();
echo wrap(t('TouchIconInfo', 'The touch icon appears when you bookmark a website on the homescreen of an Apple device.
         These are usually 57x57 or 114x114 pixels. Apple adds rounded corners and lighting effect automatically.'),
    'div',
    ['class' => 'alert alert-info padded']);
echo wrap(img(val('Path', $this->Data)), 'div'); ?>
<div class="form-group row">
   <div class="label-wrap">
      <?php
      echo $this->Form->label('Touch Icon', 'TouchIcon');
      echo wrap(t('TouchIconEdit', 'Browse for a new touch icon to change it.'), 'div', ['class' => 'info']); ?>
   </div>
   <?php echo $this->Form->fileUploadWrap('TouchIcon'); ?>
</div>
<div class="js-modal-footer form-footer">
   <?php echo $this->Form->close('Save'); ?>
</div>
