<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo $this->data('Title'); ?></h1>
<div class="Warning">
   <?php
   echo t('Warning: This is for advanced users.');
   ?>
</div>
<?php
$Cf = $this->ConfigurationModule;

$Cf->render();