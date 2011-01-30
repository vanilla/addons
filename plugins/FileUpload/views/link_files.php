<div class="Attachments">
   <div class="AttachmentHeader"><?php echo T('Attachments'); ?></div>
   <table class="AttachFileContainer">
      <?php
         $CanDownload = $this->Data('CanDownload');
         foreach ($this->Data('CommentMediaList') as $Media) {
            $IsOwner = (Gdn::Session()->IsValid() && (Gdn::Session()->UserID == GetValue('InsertUserID',$Media,NULL)));
      ?>
            <tr>
               <?php if ($IsOwner || Gdn::Session()->CheckPermission("Garden.Settings.Manage")) { ?>
                  <td><a class="DeleteFile" href="<?php echo Url("/plugin/fileupload/delete/{$Media->MediaID}"); ?>"><span><?php echo T('Delete'); ?></span></a></td>
               <?php } ?>
               <td>
                  <div class="FilePreview"><?php
                  $Path = GetValue('Path', $Media);
                  if (getimagesize(PATH_UPLOADS.'/'.$Path))
                     echo Img('uploads/'.$Path, array('class' => 'ImageThumbnail'));
                  else
                     echo Img('plugins/FileUpload/images/paperclip.png', array('class' => 'ImageThumbnail'));
                  ?></div>
               </td>
               <td>
                  <?php if ($CanDownload) { echo '<a href="'.Url("/discussion/download/{$Media->MediaID}/{$Media->Name}").'">'; } ?>
                  <?php echo $Media->Name; ?>
                  <?php if ($CanDownload) { echo '</a>'; } ?>
               </td>
               <td class="FileSize"><?php echo Gdn_Format::Bytes($Media->Size, 0); ?></td>
            </tr>
      <?php
         }
      ?>
   </table>
</div>