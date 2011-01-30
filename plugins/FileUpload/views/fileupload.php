<?php if (!defined('APPLICATION')) exit(); ?>
<?php
   $ApcAvailable = TRUE;
   if ($ApcAvailable && !ini_get('apc.enabled')) $ApcAvailable = FALSE;
   if ($ApcAvailable && !ini_get('apc.rfc1867')) $ApcAvailable = FALSE;
   
   if ($ApcAvailable) {
      $RealtimeStatus = Wrap(T("available"),'span',array(
         'class' => "FileUploadRealtimeAvailable"
      ));
   } else {
      $RealtimeStatus = Wrap(T("unavailable"),'span',array(
         'class' => "FileUploadRealtimeUnavailable"
      ));
   }
?>
<h1><?php echo T($this->Data['Title']); ?></h1>
<div class="Info">
   <?php echo T('This plugin enables uploading files and attaching them to discussions and comments.'); ?>
   <div class="RealtimeMode">
      <?php echo sprintf(T('Realtime progress bars: %s'),$RealtimeStatus); ?>
      <?php if (!$ApcAvailable) { ?>
         <div><?php echo T('For information on how to enable realtime progress bars, check out <a href="http://ca.php.net/manual/en/book.apc.php">Alternative PHP Cache (APC)</a>'); ?></div>
      <?php } ?>
   </div>
</div>
<?php
   echo $this->Plugin->Slice('toggle');
?>

<h3><?php echo T('Permissions'); ?></h3>
<div class="Info">
   <?php echo T('Define who can upload and manage files on the '.Anchor('Roles & Permissions','/dashboard/role').' page.'); ?>
</div>