$(function() {

  function checkAge() {
    if ($('#Form_Day').val() > 0 && $('#Form_Month').val() > 0 && $('#Form_Year').val() > 0) {
      var userDob = new Date($('#Form_Year').val(), $('#Form_Month').val()-1, $('#Form_Day').val(), 0, 0, 0, 0).getTime();
      var minAge = $('#Form_MinimumAge').val();
      var now = new Date();
      var currentYear = now.getFullYear();
      var maxDob = now.setFullYear(currentYear - minAge);
      if (userDob > maxDob) {
        $('.js-agegate-confirmation').removeClass('Hidden');
      } else {
        $('.js-agegate-confirmation').addClass('Hidden');
      }
    }
  }

  checkAge();

  $('.AgeGate').change(function() {
    checkAge();
  });
});
