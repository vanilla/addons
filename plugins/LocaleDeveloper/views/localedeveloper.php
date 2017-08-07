<?php if (!defined('APPLICATION')) exit();

echo '<h1>', $this->data('Title'), '</h1>';

$Form = $this->Form; //new gdn_Form();
echo $Form->open();
echo $Form->errors();
?>
<div class="Info">
   <?php echo sprintf(t('This plugin helps locale package development.', 'This plugin helps locale package development. The plugin keeps a working locale pack at <code>%s</code>.'),
      $this->data('LocalePath'));
      echo ' ';
      echo sprintf(t('For more help on localization check out the page <a href="%s">here</a>.'), 'http://vanillaforums.org/page/localization');
   ?>
</div>
<h3><?php echo t('Settings'); ?></h3>
<ul>
   <li>
      <?php echo sprintf(t('Locale info file settings.', '<p>When you generate the zip file you can set the information for the locale below.</p> <p>You can download a zip of the locale pack by clicking <a href="%s">here</a>.</p>'), url("/settings/localedeveloper/download")); ?>
   </li>
   <li>
      <?php
      echo $this->Form->label('Locale Key (Folder)', 'Key'),
         $this->Form->textBox('Key');
      ?>
   </li>
   <li>
      <?php
      echo $this->Form->label('Locale Name', 'Name'),
         $this->Form->textBox('Name');
      ?>
   </li>
   <li>
      <?php
      echo $this->Form->label('_Locale', 'Locale'),
         $this->Form->textBox('Locale', ['Class' => 'SmallInput']);
      ?>
   </li>
   <li>
      <?php
      echo $this->Form->checkBox('CaptureDefinitions', 'Capture definitions throughout the site. You must visit the pages in the site in order for the definitions to be captured. The captured definitions will be put in the <code>captured.php</code> and <code>captured_admin.php</code>.');
      ?>
   </li>
</ul>
<?php echo $Form->button('Save'); ?>
<h3><?php echo t('Tools'); ?></h3>
<ul>
   <li>
      <?php
         echo t('Copy locale pack.', 'Copy the definitions from a locale pack to the Locale Developer. The definitions will be put in the <code>copied.php</code> file.');
         echo $Form->label('Choose a locale pack', 'LocalePackForCopy');
         echo $Form->dropDown('LocalePackForCopy', $this->data('LocalePacks'));
         echo $Form->button('Copy');
      ?>
   </li>
   <li>
      <?php
         echo t('Capture locale pack changes.', 'Capture the changes between one of your locale packs and the Locale Developer. It will be put in the <code>changes.php</code> file.');
         echo $Form->label('Choose a locale pack', 'LocalePackForChanges');
         echo $Form->dropDown('LocalePackForChanges', $this->data('LocalePacks'));
         echo $Form->button('Generate', ['Name' => 'Form/GenerateChanges']);
      ?>
   </li>
   <li>
      <?php
         echo '<div>', t('Remove locale developer files.', 'Remove the locale deveoper files and reset your changes.'), '</div>';
         echo $Form->button('Remove');
      ?>
   </li>
</ul>
<?php echo $Form->close(); ?>