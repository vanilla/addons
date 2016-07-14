$(document).on('click', '.js-generate', function(e) {
   e.preventDefault();
   var $parent = $(this).closest('form');
   $.ajax({
      type: 'POST',
      url: gdn.url('/settings/jsconnect/addedit'),
      data: {
         DeliveryType: 'VIEW',
         DeliveryMethod: 'JSON',
         TransientKey: gdn.definition('TransientKey'),
         Generate: true
      },
      dataType: 'json',
      error: function(xhr) {
         gdn.informError(xhr);
      },
      success: function(json) {
         console.log(json);
         $('#Form_AuthenticationKey', $parent).val(json.AuthenticationKey);
         $('#Form_AssociationSecret', $parent).val(json.AssociationSecret);
         if ($('.modal-body').length) {
            $('.modal-body').scrollTop(0);
         } else {
            $(window).scrollTop(0);
         }
      }
   });
});
