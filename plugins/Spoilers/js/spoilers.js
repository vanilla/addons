var SpoilersPlugin = {
   FindAndReplace: function() {
      jQuery('div.UserSpoiler').each(function(i, el) {
         SpoilersPlugin.ReplaceSpoiler(el);
      });
   },
   
   ReplaceComment: function(Comment) {
      jQuery(Comment).find('div.UserSpoiler').each(function(i,el){
         SpoilersPlugin.ReplaceSpoiler(el);
      },this);
   },
   
   ReplaceSpoiler: function(Spoiler) {
      // Don't re-event spoilers that are already 'on'
      if (Spoiler.SpoilerFunctioning) return;
      Spoiler.SpoilerFunctioning = true;
      
      // Extend object with jQuery
      Spoiler = jQuery(Spoiler);
      var SpoilerTitle = Spoiler.find('div.SpoilerTitle').first();
      var SpoilerButton = document.createElement('input');
      SpoilerButton.type = 'button';
      SpoilerButton.value = 'show';
      SpoilerButton.className = 'SpoilerToggle';
      SpoilerTitle.append(SpoilerButton);
      Spoiler.on('click', 'input.SpoilerToggle', function(event) {
         event.stopPropagation();
         SpoilersPlugin.ToggleSpoiler(jQuery(event.delegateTarget), jQuery(event.target));
      });
   },
   
   ToggleSpoiler: function(Spoiler, SpoilerButton) {
      var ThisSpoilerText = Spoiler.find('div.SpoilerText').first();
      var ThisSpoilerStatus = ThisSpoilerText.css('display');
      var NewSpoilerStatus = (ThisSpoilerStatus == 'none') ? 'block' : 'none';
      ThisSpoilerText.css('display',NewSpoilerStatus);
      
      if (NewSpoilerStatus == 'none')
         SpoilerButton.val('show');
      else
         SpoilerButton.val('hide');
   }
};

// Events!

jQuery(document).ready(function(){
   SpoilersPlugin.FindAndReplace();
});

jQuery(document).on('CommentPagingComplete CommentAdded MessageAdded popupReveal', function() {
   SpoilersPlugin.FindAndReplace();
});

jQuery(document).on('PreviewLoaded', function() {
   window.setTimeout(SpoilersPlugin.FindAndReplace, 150);
});
