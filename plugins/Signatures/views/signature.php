<?php if (!defined('APPLICATION')) exit(); ?>
<div class="FormTitleWrapper">
<h1 class="H"><?php echo T('Signature Settings'); ?></h1>
<?php
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<ul>
   <?php
      if (isset($this->Data['Plugin-Signatures-ForceEditing']) && $this->Data['Plugin-Signatures-ForceEditing'] != FALSE) {
   ?>
         <div class="Warning"><?php echo sprintf(T("You are editing %s's signature"),$this->Data['Plugin-Signatures-ForceEditing']); ?></div>
   <?php
      }
   ?>
   <li>
      <?php
         echo $this->Form->Label('Settings');
         echo $this->Form->CheckBox('Plugin.Signatures.HideAll','Hide signatures always');
         echo $this->Form->CheckBox('Plugin.Signatures.HideMobile',"Hide signatures on my mobile device");
         echo $this->Form->CheckBox('Plugin.Signatures.HideImages','Strip images out of signatures');
      ?>
   </li>
   <?php if ($this->Data('CanEdit')): ?>
   <li>
      <?php
         echo $this->Form->Label('Signature Code', 'Plugin.Signatures.Sig');
         echo $this->Form->BodyBox('Body');
//         echo Wrap($this->Form->TextBox('Plugin.Signatures.Sig', array('MultiLine' => TRUE)), 'div', array('class' => 'TextBoxWrapper'));
      ?>
   </li>
   <?php endif; ?>
   
   <?php
      $this->FireEvent('EditMySignatureAfter');
   ?>
</ul>
<?php echo $this->Form->Close('Save', '', array('class' => 'Button Primary')); ?>
</div>