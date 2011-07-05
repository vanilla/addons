// Enable multicomplete on selected inputs.
jQuery(document).ready(function($) {
   var toggleWhisperForm = function() {
      var whisper = $('#Form_Whisper:checked').val();
      if (whisper)
         $('#WhisperForm').show();
      else
         $('#WhisperForm').hide();
   };
   toggleWhisperForm();

   $('#Form_Whisper').live('click', toggleWhisperForm);



   $('.MultiComplete').livequery(function() {
      $(this).autocomplete(
         gdn.url('/dashboard/user/autocomplete/'),
         {
            minChars: 1,
            multiple: true,
            scrollHeight: 220,
            selectFirst: true
         }
      ); //.autogrow();
   });
});