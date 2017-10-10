<div class="Attachments">
   <div class="AttachFileContainer">
      <?php
         $CanDownload = $this->data('CanDownload');
         foreach ($this->data('CommentMediaList') as $Media) {
            $IsOwner = (Gdn::session()->isValid() && (Gdn::session()->UserID == getValue('InsertUserID',$Media,NULL)));
            $this->EventArguments['CanDownload'] =& $CanDownload;
            $this->EventArguments['Media'] =& $Media;
            $this->fireEvent('BeforeFile');

      ?>
            <div class="Attachment">
               <div class="FilePreview">
                  <?php
                  $Path = getValue('Path', $Media);
                  $Img = '';

                  if ($CanDownload) {
                     $DownloadUrl = url(FileUploadPlugin::url($Media));
                     $Img = '<a href="'.$DownloadUrl.'">';
                  }

                  $ThumbnailUrl = FileUploadPlugin::thumbnailUrl($Media);
                  $Img .= mediaThumbnail($Media);
                  if ($CanDownload)
                     $Img .= '</a>';

                  echo $Img;
               ?></div>
               <div class="FileHover">
                  <?php echo $Img; ?>
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

                     echo ' <span class="FileSize">', Gdn_Format::bytes($Media->Size, 0), '</span>';
                     echo '</div>';

                     $Actions = '';
                     if (stringBeginsWith($this->ControllerName, 'post', TRUE))
                        $Actions = concatSep(' | ', $Actions, '<a class="InsertImage" href="'.url(FileUploadPlugin::url($Path)).'">'.t('Insert Image').'</a>');

                     if (getValue('ForeignTable', $Media) == 'discussion')
                        $PermissionName = "Vanilla.Discussions.Edit";
                     else
                        $PermissionName = "Vanilla.Comments.Edit";

                     if ($IsOwner || Gdn::session()->checkPermission($PermissionName, TRUE, 'Category', $this->data('Discussion.PermissionCategoryID')))
                        $Actions = concatSep(' | ', $Actions, '<a class="DeleteFile" href="'.url("/plugin/fileupload/delete/{$Media->MediaID}").'"><span>'.t('Delete').'</span></a>');

                     if ($Actions)
                        echo '<div>', $Actions, '</div>';
                     ?>
                  </div>
               </div>
            </div>
      <?php
         }
      ?>
   </div>
</div>
