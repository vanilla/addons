$(function() {

  function checkAge() {
    if ($('#Form_DateOfBirth_Day').val() > 0 && $('#Form_DateOfBirth_Month').val() > 0 && $('#Form_DateOfBirth_Year').val() > 0) {
      var userDob = new Date($('#Form_DateOfBirth_Year').val(), $('#Form_DateOfBirth_Month').val()-1, $('#Form_DateOfBirth_Day').val(), 0, 0, 0, 0).getTime();
      var minAge = $('#Form_MinimumAge').val();
      var minAgeWithConsent = $('#Form_MinimumAgeWithConsent').val();
      var now = new Date();
      var currentYear = now.getFullYear();
      var minDob = now.setFullYear(currentYear - minAge);
      var maxDobWithConsent = now.setFullYear(currentYear - minAgeWithConsent);
      if (minAgeWithConsent) { // Hidden config setting is set
        if (userDob < minDob && userDob > maxDobWithConsent) {
          $('.js-agegate-confirmation').removeClass('Hidden');
        } else {
          $('.js-agegate-confirmation').addClass('Hidden');
        }
      } else {
        if (userDob > minDob) {
          $('.js-agegate-confirmation').removeClass('Hidden');
        } else {
          $('.js-agegate-confirmation').addClass('Hidden');
        }
      }
    }
  }

  checkAge();

  $('.AgeGate').change(function() {
    checkAge();
  });

});
