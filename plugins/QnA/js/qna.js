jQuery(document).ready(function($) {
   $('a.QnAButton').click(function() {
      $this = $(this);
      $('#Content h1').html($this.html());
      $('label[for=Form_Name]').html(gdn.definition($(this).attr('rel')+'Title', 'Title'));
      $('#Form_PostDiscussion').val(gdn.definition($(this).attr('rel')+'Button', 'Post Discussion'));

      $('a.QnAButton').closest('li').removeClass('Active');
      $this.closest('li').addClass('Active');

      $('input:radio[value='+$this.attr('rel')+']').attr('checked', 'checked');

      return false;
   });

});