<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::session();
$TrackingCodes = c('Plugins.TrackingCodes.All');
if (!is_array($TrackingCodes))
   $TrackingCodes = [];

?>
<h1><?php echo t('Tracking Codes'); ?></h1>
<div class="Info"><?php echo t('Tracking codes are added to every page just above the closing &lt;/body&gt; tag. Useful for common tracking code generators like Google Analytics, Hubspot, etc. Add, edit and enable/disable them below.'); ?></div>
<div class="FilterMenu"><?php echo anchor(t('Add Tracking Code'), 'settings/trackingcodes/edit', 'AddTrackingCode SmallButton'); ?></div>
<?php if (count($TrackingCodes) > 0) { ?>
<table id="MessageTable" border="0" cellpadding="0" cellspacing="0" class="AltColumns Sortable">
   <thead>
      <tr id="0">
         <th><?php echo t('Tracking Code'); ?></th>
         <th class="Alt"><?php echo t('State'); ?></th>
         <th><?php echo t('Options'); ?></th>
      </tr>
   </thead>
   <tbody>
<?php
$Alt = FALSE;
foreach ($TrackingCodes as $Index => $Code) {
   $Key = getValue('Key', $Code, '');
   $Alt = $Alt ? FALSE : TRUE;
   ?>
   <tr id="<?php
      echo $Index;
      echo $Alt ? '" class="Alt' : '';
   ?>">
      <td class="Info nowrap"><strong><?php echo getValue('Name', $Code, 'Undefined'); ?></strong></td>
      <td class="Alt"><?php echo getValue('Enabled', $Code) == '1' ? 'Enabled' : 'Disabled'; ?></td>
      <td>
         <?php
         echo anchor(t(getValue('Enabled', $Code) == '1' ? 'Disable' : 'Enable'), '/settings/trackingcodes/toggle/'.$Key.'/'.$Session->transientKey(), 'ToggleCode SmallButton');
         echo anchor(t('Edit'), '/settings/trackingcodes/edit/'.$Key, 'EditCode SmallButton');
         echo anchor(t('Delete'), '/settings/trackingcodes/delete/'.$Key.'/'.$Session->transientKey(), 'PopConfirm SmallButton');
         ?>
         </div>
      </td>
   </tr>
<?php } ?>
   </tbody>
</table>
<?php } ?>