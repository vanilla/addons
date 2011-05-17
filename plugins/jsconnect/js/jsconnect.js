jQuery(document).ready(function($) {

var jsUrl = gdn.definition('JsAuthenticateUrl', false);
if (jsUrl) {
   $.ajax({
      url: jsUrl,
      dataType: 'json',
      success: function(data) {
         if (data['error']) {
            $('form').attr('action', gdn.url('/entry/jsconnect/error'));
         } else if (!data['name']) {
            data = {'error': 'unauthorized', 'message': 'You are not signed in.' };
            $('form').attr('action', gdn.url('/entry/jsconnect/error'));
         } else {
            for(var key in data) {
               if (data[key] == null)
                  data[key] = '';
            }
         }

         var connectData = $.param(data);
         $('#Form_JsConnect').val(connectData);
         $('form').submit();
      }
   });
}
   
$.fn.jsconnect = function(options) {
   if (this.length == 0)
      return;
   
   var $elems = this;
   
   // Collect the urls.
   var urls = {};
   $elems.each(function(i, elem) {
      var rel = $(elem).attr('rel');
      
      if (urls[rel] == undefined)
         urls[rel] = [];
      urls[rel].push(elem);
   });
   
   for (var url in urls) {
      var elems = urls[url];

      // Get the client id from the url.
      var re = new RegExp("client_?id=([^&]+)(&Target=([^&]+))?", "g");
      var matches = re.exec(url);
      var client_id = false, target = '/';
      if (matches) {
         if (matches[1])
            client_id = matches[1];
         if (matches[3])
            target = matches[3];
      }
      
      // Make a request to the host page.
      $.ajax({
         url: url,
         dataType: 'json',
         success: function(data, textStatus) {
            var connectUrl = gdn.url('/entry/jsconnect?client_id='+client_id+'&Target='+target);

            var signedIn = data['name'] ? true : false;

            if (signedIn) {
               $(elems).find('.ConnectLink').attr('href', connectUrl);
               $(elems).find('.Username').text(data['name']);
         
               if (data['photourl'])
                  $(elems).find('.UserPhoto').attr('src', data['photourl']);

               $(elems).find('.JsConnect-Connect').show();
               $(elems).find('.JsConnect-Guest').hide();
            } else {
               $(elems).find('.JsConnect-Connect').hide();
               $(elems).find('.JsConnect-Guest').show();
            }
            $(elems).show();
         }
      });
   }
};

$('.JsConnect-Container').livequery(function() { $(this).jsconnect(); });

});