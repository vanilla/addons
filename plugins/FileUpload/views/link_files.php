<div class="Attachments">
   <div class="AttachmentHeader"><?php echo T('Attachments'); ?></div>
   <table class="AttachFileContainer">
      <?php
         $CanDownload = $this->Data('CanDownload');
         
         $FileLinkTemplate = CombinePaths(array(
            'plugin',
            'FileUpload',
            '%s', // download type [preview | download]
            '%d', // media ID
            '%s'  // filename
         ));
         
         foreach ($this->Data('CommentMediaList') as $Media) {
            $IsOwner = (Gdn::Session()->IsValid() && (Gdn::Session()->UserID == GetValue('InsertUserID',$Media,NULL)));
            $FileLink = sprintf($FileLinkTemplate, '%s', GetValue('MediaID', $Media, ''), GetValue('Name', $Media, ''));
      ?>
            <tr>
               <?php if ($IsOwner || Gdn::Session()->CheckPermission("Garden.Settings.Manage")) { ?>
                  <td><a class="DeleteFile" href="<?php echo Url("/plugin/fileupload/delete/{$Media->MediaID}"); ?>"><span><?php echo T('Delete'); ?></span></a></td>
               <?php } ?>
               <td>
                  <div class="FilePreview"><?php
                     $PreviewLocation = sprintf($FileLink, 'preview');
                     echo Img($PreviewLocation, array('class' => 'ImageThumbnail'));
                  ?></div>
               </td>
               <td>
                  <?php if ($CanDownload) { echo '<a href="'.Url(sprintf($FileLink, 'download')).'">'; } ?>
                  <?php echo GetValue('Name', $Media); ?>
                  <?php if ($CanDownload) { echo '</a>'; } ?>
               </td>
               <td class="FileSize"><?php echo Gdn_Format::Bytes($Media->Size, 0); ?></td>
               <td class="FileInsert"><?php
                  if (get_class($this) == 'PostController' && $this->Data("FileUploadCommitting") !== TRUE) {
                     if ($this->Plugin->SupportedImageType(GetValue('Type', $Media))) {
                        echo sprintf('<a class="InsertImage" href="%s">%s</a>', Url(sprintf($FileLink, 'download')), T('Insert Image'));
                     }
                  }
                  
               ?></td>
            </tr>
      <?php
         }
      ?>
   </table>
</div>