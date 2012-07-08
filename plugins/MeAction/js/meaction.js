jQuery(document).ready(function($) {
   $.each($('.MeName'), function(i, NameTag) {
      var MeNameText = $(NameTag).closest('.Comment, .Discussion').find('.Author a').text();
      $(NameTag).contents().replaceWith('* ' + MeNameText);
   });
});