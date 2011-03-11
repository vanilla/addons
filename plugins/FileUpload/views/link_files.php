<div class="Attachments">
   <div class="AttachmentHeader"><?php echo T('Attachments'); ?></div>
   <div class="AttachFileContainer">
      <?php
         $CanDownload = $this->Data('CanDownload');
         foreach ($this->Data('CommentMediaList') as $Media) {
            $IsOwner = (Gdn::Session()->IsValid() && (Gdn::Session()->UserID == GetValue('InsertUserID',$Media,NULL)));
      ?>
            <div class="Attachment">
               <div class="FilePreview">
                  <?php
                  $Path = GetValue('Path', $Media);
                  $DownloadUrl = Url("/discussion/download/{$Media->MediaID}/{$Media->Name}");

                  if ($CanDownload)
                     echo '<a href="'.$DownloadUrl.'">';

                  $ThumbnailUrl = MediaModel::ThumbnailUrl($Media);
                  echo Img($ThumbnailUrl, array('class' => 'ImageThumbnail'));

                  if ($CanDownload)
                     echo '</a>';

                  ?></div><div class="FileMeta">
                     <?php
                     echo '<div>';
                     echo '<span class="FileName">', htmlspecialchars($Media->Name), '</span>';

                     if ($Media->ImageWidth && $Media->ImageHeight) {
                        echo ' <span class="FileSize">'.$Media->ImageWidth.'&#160;x&#160;'.$Media->ImageHeight.'</span>';
                     }

                     echo ' <span class="FileSize">', Gdn_Format::Bytes($Media->Size, 0), '</span>';
                     echo '</div>';

                     if (StringBeginsWith($this->ControllerName, 'post', TRUE)) {
                        echo '<div>';

                        echo '<a class="InsertImage" href="'.Asset("/uploads/$Path").'">'.T('Insert Image').'</a>';

                        if ($IsOwner || Gdn::Session()->CheckPermission("Garden.Settings.Manage")) {
                           echo ' | <a class="DeleteFile" href="'.Url("/plugin/fileupload/delete/{$Media->MediaID}").'"><span>'.T('Delete').'</span></a>';
                        }
                        echo '</div>';
                     }
                     ?>
                  </div>
            </div>
      <?php
         }
      ?>
   </div>
</div>