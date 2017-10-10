<?php

function mediaThumbnail($media, $data = FALSE) {
      $media = (array)$media;

      if (getValue('ThumbPath', $media)) {
         $src = Gdn_Upload::url(ltrim(getValue('ThumbPath', $media), '/'));
      } else {
         $width = getValue('ImageWidth', $media);
         $height = getValue('ImageHeight', $media);

         if (!$width || !$height) {
            $height = FileUploadPlugin::thumbnailHeight();
            if (!$height)
               $height = 100;
               setValue('ThumbHeight', $media, $height);

            return defaultMediaThumbnail($media);
         }

         $requiresThumbnail = FALSE;
         if (FileUploadPlugin::thumbnailHeight() && $height > FileUploadPlugin::thumbnailHeight())
            $requiresThumbnail = TRUE;
         elseif (FileUploadPlugin::thumbnailWidth() && $width > FileUploadPlugin::thumbnailWidth())
            $requiresThumbnail = TRUE;

         $path = ltrim(getValue('Path', $media), '/');
         if ($requiresThumbnail) {
            $src = url('/utility/thumbnail/'.getValue('MediaID', $media, 'x').'/'.$path, TRUE);
         } else {
            $src = Gdn_Upload::url($path);
         }
      }
      if ($data)
         $result = ['src' => $src, 'width' => getValue('ThumbWidth', $media), 'height' => getValue('ThumbHeight', $media)];
      else
         $result = img($src, ['class' => 'ImageThumbnail', 'width' => getValue('ThumbWidth', $media), 'height' => getValue('ThumbHeight', $media)]);

      return $result;

}

function defaultMediaThumbnail($media) {
  $result = '<span class="Thumb-Default">'.
   '<span class="Thumb-Extension">'.pathinfo($media['Name'], PATHINFO_EXTENSION).'</span>'.
   img('/plugins/FileUpload/images/file.png', ['class' => 'ImageThumbnail', 'height' => getValue('ThumbHeight', $media)]).
   '</span>';

   return $result;
}
