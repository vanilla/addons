<?php if (!defined('APPLICATION')) exit(); ?>
<div class="FormTitleWrapper">
<?php
   echo $this->Form->open();

   // Normalize no image config setting
   if (c('Plugins.Signatures.MaxNumberImages') === 0 || c('Plugins.Signatures.MaxNumberImages') === '0') {
      saveToConfig('Plugins.Signatures.MaxNumberImages', 'None');
   }


?>
<h1 class="H"><?php echo t('Signatures'); ?></h1>
<h2 class="H"><?php echo t('My Signature'); ?></h2>
   <?php echo $this->Form->errors(); ?>
   <ul>
      <?php
      if (isset($this->Data['Plugin-Signatures-ForceEditing']) && $this->Data['Plugin-Signatures-ForceEditing'] != FALSE) {
         ?>
         <div class="Warning"><?php echo sprintf(t("You are editing %s's signature"),$this->Data['Plugin-Signatures-ForceEditing']); ?></div>
      <?php
      }
      ?>

      <li>
         <?php if (!C('Plugins.Signatures.AllowEmbeds', true)): ?>
            <div class="Info">
               <?php echo t('Video embedding has been disabled.', 'Video embedding has been disabled. URLs will not translate to their embedded equivalent.'); ?>
            </div>
         <?php endif; ?>
      </li>
      <li>
         <?php
            if ($this->data('CanEdit')) {
               if ($this->data('SignatureRules')) {
                  ?>
                  <div class="SignatureRules">
                     <?php echo $this->data('SignatureRules'); ?>
                  </div>
                  <?php
               }

               echo $this->Form->bodyBox('Body');
   //            echo wrap($this->Form->textBox('Plugin.Signatures.Sig', array('MultiLine' => TRUE)), 'div', array('class' => 'TextBoxWrapper'));
            } else {
               echo t("You don't have permission to use a signature.");
            } ?>
      </li>
   </ul>


   <?php
      $this->fireEvent('EditMySignatureAfter');
   ?>
</ul>
<h2 class="H"><?php echo t('Forum Signature Settings'); ?></h2>
<ul>
   <li>
      <?php
      echo $this->Form->checkBox('Plugin.Signatures.HideAll','Hide signatures always');
      if (!C('Plugins.Signatures.HideMobile', TRUE)) {
         echo $this->Form->checkBox('Plugin.Signatures.HideMobile',"Hide signatures on my mobile device");
      }
      echo $this->Form->checkBox('Plugin.Signatures.HideImages','Strip images out of signatures');
      ?>
   </li>
</ul>
<?php echo $this->Form->close('Save', '', array('class' => 'Button Primary')); ?>
</div>
