<div class="AttachFileWrapper AttachmentWindow">
   <div class="AttachFileLink">
      <a href="javascript:void(0);"><?php echo T('Attach a file'); ?></a>
      <div class="CurrentUploader"></div>
   </div>
   <div class="AttachFileContainer">
      <div class="PrototypicalAttachment" style="display:none;">
         <div class="Attachment">
            <div class="FilePreview"></div>
            <div class="FileHover">
               <div class="FileMeta">
                  <div>
                     <span class="FileName"><?php echo T('FileName'); ?></span>
                     <span class="FileSize"><?php echo T('FileSize'); ?></span>
                  </div>
                  <span class="FileOptions"></span>
                  <a class="InsertImage Hidden"><?php echo T('Insert Image'); ?></a>
               </div>
            </div>
         </div>
         <div class="UploadProgress">
            <div class="Foreground"><strong><?php echo T('Uploading...'); ?></strong></div>
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