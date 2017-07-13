<?php

function MediaThumbnail($Media, $Data = FALSE) {
      $Media = (array)$Media;

      if (GetValue('ThumbPath', $Media)) {
         $Src = Gdn_Upload::Url(ltrim(GetValue('ThumbPath', $Media), '/'));
      } else {
         $Width = GetValue('ImageWidth', $Media);
         $Height = GetValue('ImageHeight', $Media);

         if (!$Width || !$Height) {
            $Height = FileUploadPlugin::ThumbnailHeight();
            if (!$Height)
               $Height = 100;
               SetValue('ThumbHeight', $Media, $Height);

            return DefaultMediaThumbnail($Media);
         }

         $RequiresThumbnail = FALSE;
         if (FileUploadPlugin::ThumbnailHeight() && $Height > FileUploadPlugin::ThumbnailHeight())
            $RequiresThumbnail = TRUE;
         elseif (FileUploadPlugin::ThumbnailWidth() && $Width > FileUploadPlugin::ThumbnailWidth())
            $RequiresThumbnail = TRUE;

         $Path = ltrim(GetValue('Path', $Media), '/');
         if ($RequiresThumbnail) {
            $Src = Url('/utility/thumbnail/'.GetValue('MediaID', $Media, 'x').'/'.$Path, TRUE);
         } else {
            $Src = Gdn_Upload::Url($Path);
         }
      }
      if ($Data)
         $Result = ['src' => $Src, 'width' => GetValue('ThumbWidth', $Media), 'height' => GetValue('ThumbHeight', $Media)];
      else
         $Result = Img($Src, ['class' => 'ImageThumbnail', 'width' => GetValue('ThumbWidth', $Media), 'height' => GetValue('ThumbHeight', $Media)]);

      return $Result;

}

function DefaultMediaThumbnail($Media) {
  $Result = '<span class="Thumb-Default">'.
   '<span class="Thumb-Extension">'.pathinfo($Media['Name'], PATHINFO_EXTENSION).'</span>'.
   Img('/plugins/FileUpload/images/file.png', ['class' => 'ImageThumbnail', 'height' => GetValue('ThumbHeight', $Media)]).
   '</span>';

   return $Result;
}
