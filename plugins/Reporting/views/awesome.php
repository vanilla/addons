<?php if (!defined('APPLICATION')) exit(); ?>
<?php
   $ReportingData = getValue('Plugin.Reporting.Data', $this->Data);
   
   $Context = getValue('Context', $ReportingData);
   $UpperContext = ucfirst($Context);
   $ElementID = getValue('ElementID', $ReportingData);
   $ElementAuthorID = getValue('ElementAuthorID', $ReportingData);
   $ElementAuthor = getValue('ElementAuthor', $ReportingData);
   $ElementTitle = getValue('ElementTitle', $ReportingData);
   $ElementExcerpt = getValue('ElementExcerpt', $ReportingData);
   $URL = getValue('URL', $ReportingData);
   $Title = sprintf(t("Report this %s"), $Context);
?>
<h2><?php echo t($Title); ?></h2>
<?php
echo $this->Form->open();
echo $this->Form->errors();
?>
<div class="AwesomePost">
   <ul>
      <li>
         <div class="Excerpt">
            <div><?php echo sprintf(t("%s said:"), userAnchor($ElementAuthor)); ?></div>
            <div>"<?php echo $ElementExcerpt; ?>"</div>
         </div>
         <div class="Warning">
            <?php echo sprintf(t("You consider this <b>%s</b> to be awesome, and you want us to take a look. If you're sure you want to do this, please enter a brief reason/explanation below."), $Context); ?>
         </div>
      </li>
      <li>
         <?php
            echo $this->Form->label('Reason', 'Plugin.Reporting.Reason');
            echo wrap($this->Form->textBox('Plugin.Reporting.Reason', ['MultiLine' => TRUE]), 'div', ['class' => 'TextBoxWrapper']);
         ?>
      </li>
      <?php
         $this->fireEvent('ReportContentAfter');
      ?>
   </ul>
   <?php echo $this->Form->close('This is awesome!'); ?>
</div>