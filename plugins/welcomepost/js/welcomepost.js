$(function() {
    $.popup({}, gdn.getMeta('WelcomePostPopupMessage'));

    // Patch for Scope / Bootstrap 3
    $(document).trigger('popupLoading');
    setTimeout(function () {
        $(document).trigger('popupReveal');
    }, 150);
});
