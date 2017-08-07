<div class="AttachFileWrapper AttachmentWindow">
   <div class="AttachFileLink">
      <a href="javascript:void(0);"><?php echo t('Attach a file'); ?></a>
      <div class="CurrentUploader"></div>
   </div>
   <div class="AttachFileContainer">
      <div class="PrototypicalAttachment" style="display:none;">
         <div class="Attachment">
            <div class="FilePreview"></div>
            <div class="FileHover">
               <div class="FileMeta">
                  <div>
                     <span class="FileName"><?php echo t('Filename'); ?></span>
                     <span class="FileSize"><?php echo t('File Size'); ?></span>
                  </div>
                  <span class="FileOptions"></span>
                  <a class="InsertImage Hidden"><?php echo t('Insert Image'); ?></a>
                  <a class="DeleteFile"><?php echo t('Delete'); ?></a>
               </div>
            </div>
         </div>
         <div class="UploadProgress">
            <div class="Foreground"><strong><?php echo t('Uploading...'); ?></strong></div>
            <div class="Background">&nbsp;</div>
            <div>&nbsp;</div>
         </div>
      </div>
   </div>
</div>
<script type="text/javascript">
   if (GdnUploaders)
      GdnUploaders.Prepare();
</script>