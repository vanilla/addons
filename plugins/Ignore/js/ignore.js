jQuery(document).ready(function($){

   $('.Ignored').each(function(i,el){
      $(el).addClass('IgnoreHide');
   });

   $(document).on('click', '.Ignored', function(event) {
      var el = $(event.target);
      if (!el.hasClass('Ignored'))
         el = el.closest('.Ignored');

      if (el.hasClass('IgnoreHide'))
         el.removeClass('IgnoreHide');
      else
         el.addClass('IgnoreHide');
   });

//   $('.Profile a.Ignore').click(function(event){
//      var RequestURL = $(this).attr('href');
//      var IgnoreButton = $(this);
//
//      RequestURL = gdn.url(RequestURL);
//      jQuery.ajax({
//         dataType: 'json',
//         type: 'post',
//         url: RequestURL,
//         success: function(json) {
//            gdn.inform(json);
//
//            if (json.Status == 200) {
//               if (json.Rename)
//                  IgnoreButton.html(json.Rename);
//
//               if (json.Reload)
//                  window.location.reload();
//
//               if (IgnoreButton.closest('table.IgnoreList').length)
//                  window.location.reload();
//            }
//         }
//      });
//
//      return false;
//   });

})