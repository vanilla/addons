<?php if (!defined('APPLICATION')) exit(); ?>
<div class="jsConnect-Connecting" style="margin-top: 25%">
   <h1 style="text-align: center;"><?php echo $this->Data('Title'); ?></h1>
   <?php
   echo $this->Form->Open(), $this->Form->Errors();

   //echo '<div><div class="Info">',
   //   T('Verifying your credentials...'),
   //   '<div class="Progress"></div>',
   //   '</div></div>';
   echo '<div class="Progress"></div>';

   echo $this->Form->Close();
   ?>
</div>