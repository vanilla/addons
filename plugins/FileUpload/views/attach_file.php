<div class="AttachFileWrapper AttachmentWindow">
   <div class="AttachFileContainer">
      <div class="FileAttachment PrototypicalAttachment" style="display:none;">
         <div class="FilePreview"></div>
         <div class="FileOptions"></div>
         <div class="FileName"><?php echo T('FileName'); ?></div>
         <div class="FileSize"><?php echo T('FileSize'); ?></div>
         <div class="UploadProgress">
            <div class="Foreground"><strong><?php echo T('Uploading...'); ?></strong></div>
            <div class="Background">&#160;</div>
            <div>&#160;</div>
         </div>
         <a class="InsertImage Hidden"><?php echo T('Insert Image'); ?></a>
      </div>
   </div>
   <div class="AttachFileLink">
      <a href="javascript:void(0);"><?php echo T('Attach a file'); ?></a>
      <div class="CurrentUploader"></div>
   </div>
</div>
<script type="text/javascript">
   if (GdnUploaders)
      GdnUploaders.Prepare();
</script>