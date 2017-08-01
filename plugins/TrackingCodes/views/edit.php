<?php if (!defined('APPLICATION')) exit();?>
<h1><?php
   if (($this->Code))
      echo t('Edit Tracking Code');
   else
      echo t('Add Tracking Code');
?></h1>
<?php
echo $this->Form->open();
echo $this->Form->hidden('Key');
echo $this->Form->errors();
?>
<ul>
   <li>
      <?php
         echo $this->Form->label('Name', 'Name');
         echo $this->Form->textBox('Name');
      ?>
   </li>
   <li>
      <?php
         echo $this->Form->label('Code', 'Code');
         echo $this->Form->textBox('Code', ['MultiLine' => TRUE]);
      ?>
   </li>
   <li>
      <?php
         echo $this->Form->checkBox('Enabled', 'Enable this tracking code', ['value' => '1']);
      ?>
   </li>
</ul>
<?php echo $this->Form->close('Save');