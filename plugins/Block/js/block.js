jQuery(document).ready(function($){
   
   $('.UserBlocked').each(function(i,el){
      $(el).addClass('UserBlock');
   });

   $('.UserBlocked .Meta').live('click', function(event) {
      console.log('click!');
      var el = $(event.target);
      if (!el.hasClass('UserBlocked'))
         el = el.parents('.UserBlocked');
      
      if (el.hasClass('UserBlock'))
         el.removeClass('UserBlock');
      else
         el.addClass('UserBlock');
   });
   
   $('span.Author a.ProfileLink').live('hover',function(event){
      var el = $(event.target);
      if (!el.hasClass('ProfileLink'))
         el = el.parents('.ProfileLink');
      
      if (event.type == 'mouseover') {
         var overlay = document.createElement('div');
         overlay.className = 'UserBlockOption';
         //overlay
         if (el.parents('.UserBlocked').length) {
            // Unblock
            $(overlay).html('UNBLOCK');
         } else {
            // Block
            $(overlay).html('BLOCK');
         }
         el.append(overlay);
      } else {
         el.find('.UserBlockOption').remove();
      }
   });
   
   $('span.Author a.ProfileLink').live('click',function(event){
      var el = $(event.target);
      if (!el.hasClass('ProfileLink'))
         el = el.parents('.ProfileLink');
      
      var ProfileURL = el.attr('href');
      if (el.parents('.UserBlocked').length) {
         // Unblock
         var RequestURL = ProfileURL.replace('/profile','/profile/unblock');
      } else {
         // Block
         var RequestURL = ProfileURL.replace('/profile','/profile/block');
      }
      
      RequestURL = gdn.url(RequestURL);
      jQuery.ajax({
         dataType: 'json',
         type: 'post',
         url: RequestURL,
         success: function(json) {
            gdn.inform(json);
            
            if (json.Status == 200)
               window.location.reload();
         }
      });
      
      return false;
   });
   
})