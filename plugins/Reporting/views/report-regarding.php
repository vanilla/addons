<div class="RegardingEvent">
   <span class="InformSprite Skull"/>&nbsp;</span> <?php 
      echo formatString(t("{ReportingUser} reported this {EntityType} written by {ReportedUser}"), [
         'ReportingUser'      => userAnchor(getValue('ReportingUser', $this->data('ReportInfo')), 'ReportingUser'),
         'EntityType'         => getValue('EntityType', $this->data('ReportInfo')),
         'ReportedUser'       => userAnchor(getValue('ReportedUser', $this->data('ReportInfo')), 'ReportedUser')
      ]);
   ?>
   <div class="RegardingTime"><?php 
      $ReportedDate = getValue('ReportedTime', $this->data('ReportInfo'));
      echo Gdn_Format::fuzzyTime($ReportedDate);
   ?></div>
   <?php
      $ReportedReason = getValue('ReportedReason', $this->data('ReportInfo'), NULL);
      if (!is_null($ReportedReason)) {?>
         <div class="ReportedReason">"<?php echo $ReportedReason; ?>"</div>
         <?php
      }
   ?>
</div>
<div class="RegardingActions">
   <?php 
      $ForeignURL = getValue('ForeignURL', $this->data('RegardingData'), NULL);
      if (!is_null($ForeignURL)) {
         ?><div class="ActionButton"><a href="<?php echo $ForeignURL; ?>" title="<?php echo t("Visit reported content location"); ?>"><?php echo t("Visit"); ?></a></div><?php
      }
   ?>
   <?php $this->data('RegardingSender')->fireEvent("RegardingActions"); ?>
</div>