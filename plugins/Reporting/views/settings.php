<?php if (!defined('APPLICATION')) exit();
$CategoryData = GetValue('CategoryData', $this->Data);
?>
<h1><?php echo T($this->Data('Title')); ?></h1>
<div class="alert alert-info padded">
   <?php echo t('Let your users report bad content and tag awesome content in your community.'); ?>
</div>

<?php
   // Settings

   $Features = array(
      'report'    => array('Reporting Bad Content', "reports bad", "Find out who is posting objectionable content, where they're they're posting it, and take action."),
      'awesome'   => array('Tagging Good Content', "tags awesome", "Get notified when people post great threads. Reward these people, and use their content to promote your site.")
   );
?>
<?php

$Alt = FALSE;
$ActionURL = 'plugin/reporting/feature/%s/%s?TransientKey='.Gdn::Session()->TransientKey();
foreach ($Features as $Feature => $FeatureDesc) {
   $Alt = $Alt ? FALSE : TRUE;
   list($FeatureName, $FeatureVerb, $FeatureDescription) = $FeatureDesc;
   $FeatureKey = ucfirst($Feature).'Enabled';
   $FeatureEnabled = c('Plugins.Reporting.'.$FeatureKey);

   $FeatureActionKey = ucfirst($Feature).'Action';
   $FeatureAction = GetValue($FeatureActionKey, $ReportingData);

   ?>
   <div class="form-group">
      <div class="label-wrap-wide">
         <div class="label">
            <?php echo $FeatureName; ?>
         </div>
         <div class="info">
            <?php echo Gdn_Format::Text($FeatureDescription); ?>
         </div>
      </div>
      <div class="input-wrap-right">
         <strong><?php echo $FeatureEnabled ? 'Enabled' : 'Disabled'; ?></strong>
         <?php
            $ButtonAction = $FeatureEnabled ? 'disable': 'enable';
            $ButtonURL = sprintf($ActionURL, $Feature, $ButtonAction);
            echo Anchor(T(ucfirst($ButtonAction)), $ButtonURL, 'ToggleFeature SmallButton');
         ?>
      </div>
   </div>
<?php } ?>
