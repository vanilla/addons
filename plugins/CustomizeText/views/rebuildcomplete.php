<?php if (!defined('APPLICATION')) exit();
$Locale = Gdn::locale();
$Definitions = $Locale->getDeveloperDefinitions();
$CountDefinitions = count($Definitions);
?>
<h1><?php echo t('Customize Text'); ?></h1>
<div class="padded">
   <?php
		echo 'Search complete. There are <strong>'. $CountDefinitions . '</strong> text definitions available for editing.';
		echo wrap(anchor('Go edit them now!', 'settings/customizetext'), 'p');
   ?>
</div>
