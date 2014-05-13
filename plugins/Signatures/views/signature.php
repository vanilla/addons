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
        <?php if (!C('Plugins.Signatures.AllowEmbeds', true)): ?>
            <div class="Info">
                <?php echo T('Signatures embed disabled notice', 'Video embedding has been disabled. URLs will not translate to their embedded equivalent.'); ?>
            </div>
        <?php endif; ?>
    </li>

   <li>
      <?php
         echo $this->Form->Label('Settings');
         echo $this->Form->CheckBox('Plugin.Signatures.HideAll','Hide signatures always');
         echo $this->Form->CheckBox('Plugin.Signatures.HideMobile',"Hide signatures on my mobile device");
         echo $this->Form->CheckBox('Plugin.Signatures.HideImages','Strip images out of signatures');
      ?>
   </li>

   <li>
      <?php
         echo $this->Form->Label('Signature Code', 'Plugin.Signatures.Sig');
         if ($this->Data('CanEdit')) {
            echo $this->Form->BodyBox('Body');
//            echo Wrap($this->Form->TextBox('Plugin.Signatures.Sig', array('MultiLine' => TRUE)), 'div', array('class' => 'TextBoxWrapper'));
         } else {
            echo T("You don't have permission to use a signature.");
         } ?>
   </li>


   <?php
      $this->FireEvent('EditMySignatureAfter');
   ?>
</ul>
<?php echo $this->Form->Close('Save', '', array('class' => 'Button Primary')); ?>
</div>