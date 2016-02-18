<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo $this->data('Title') ?></h1>
<div class="">
<?php
echo $this->Form->open();
echo $this->Form->errors();
?>

<div class="P">
   <?php
//   echo $this->Form->Label();
     echo $this->Form->radioList('Type', $this->data('_Types'), array('list' => TRUE));
   ?>
</div>
   
<?php
echo '<div class="Buttons Buttons-Confirm">', 
   $this->Form->button(t('OK')), ' ',
   $this->Form->button(t('Cancel'), array('type' => 'button', 'class' => 'Button Close')),
   '</div>';
echo $this->Form->close();
?>
</div>