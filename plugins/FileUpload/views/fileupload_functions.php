<?php

function MediaThumbnail($media, $data = FALSE) {
      $media = (array)$media;

      if (GetValue('ThumbPath', $media)) {
         $src = Gdn_Upload::Url(ltrim(GetValue('ThumbPath', $media), '/'));
      } else {
         $width = GetValue('ImageWidth', $media);
         $height = GetValue('ImageHeight', $media);

         if (!$width || !$height) {
            $height = FileUploadPlugin::ThumbnailHeight();
            if (!$height)
               $height = 100;
               SetValue('ThumbHeight', $media, $height);

            return DefaultMediaThumbnail($media);
         }

         $requiresThumbnail = FALSE;
         if (FileUploadPlugin::ThumbnailHeight() && $height > FileUploadPlugin::ThumbnailHeight())
            $requiresThumbnail = TRUE;
         elseif (FileUploadPlugin::ThumbnailWidth() && $width > FileUploadPlugin::ThumbnailWidth())
            $requiresThumbnail = TRUE;

         $path = ltrim(GetValue('Path', $media), '/');
         if ($requiresThumbnail) {
            $src = Url('/utility/thumbnail/'.GetValue('MediaID', $media, 'x').'/'.$path, TRUE);
         } else {
            $src = Gdn_Upload::Url($path);
         }
      }
      if ($data)
         $result = ['src' => $src, 'width' => GetValue('ThumbWidth', $media), 'height' => GetValue('ThumbHeight', $media)];
      else
         $result = Img($src, ['class' => 'ImageThumbnail', 'width' => GetValue('ThumbWidth', $media), 'height' => GetValue('ThumbHeight', $media)]);

      return $result;

}

function DefaultMediaThumbnail($media) {
  $result = '<span class="Thumb-Default">'.
   '<span class="Thumb-Extension">'.pathinfo($media['Name'], PATHINFO_EXTENSION).'</span>'.
   Img('/plugins/FileUpload/images/file.png', ['class' => 'ImageThumbnail', 'height' => GetValue('ThumbHeight', $media)]).
   '</span>';

   return $result;
}
