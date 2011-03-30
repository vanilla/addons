<div class="Attachments">
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
                  
                  if ($CanDownload) {
                     $DownloadUrl = FileUploadPlugin::Url($Path);
                     echo '<a href="'.$DownloadUrl.'">';
                  }

                  $ThumbnailUrl = MediaModel::ThumbnailUrl($Media);
                  echo Img($ThumbnailUrl, array('class' => 'ImageThumbnail'));

                  if ($CanDownload)
                     echo '</a>';

                  ?></div>

               <div class="FileMeta">
                  <?php
                  echo '<div class="FileName">';

                  if (isset($DownloadUrl)) {
                     echo '<a href="'.$DownloadUrl.'">'.htmlspecialchars($Media->Name).'</a>';
                  } else {
                     echo htmlspecialchars($Media->Name);
                  }


                  echo '</div>';

                  echo '<div class="FileAttributes">';
                  if ($Media->ImageWidth && $Media->ImageHeight) {
                     echo ' <span class="FileSize">'.$Media->ImageWidth.'&#160;x&#160;'.$Media->ImageHeight.'</span> - ';
                  }

                  echo ' <span class="FileSize">', Gdn_Format::Bytes($Media->Size, 0), '</span>';
                  echo '</div>';

                  $Actions = '';
                  if (StringBeginsWith($this->ControllerName, 'post', TRUE))
                     $Actions = ConcatSep(' | ', $Actions, '<a class="InsertImage" href="'.FileUploadPlugin::Url($Path).'">'.T('Insert Image').'</a>');

                  if ($IsOwner || Gdn::Session()->CheckPermission("Garden.Settings.Manage"))
                     $Actions = ConcatSep(' | ', $Actions, '<a class="DeleteFile" href="'.Url("/plugin/fileupload/delete/{$Media->MediaID}").'"><span>'.T('Delete').'</span></a>');

                  if ($Actions)
                     echo '<div>', $Actions, '</div>';
                  ?>
               </div>
            </div>
      <?php
         }
      ?>
   </div>
</div>