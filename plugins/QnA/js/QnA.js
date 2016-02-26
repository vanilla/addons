(function($) {
    $(function() {
        $('#IsPointsAwardEnabled').change(function() {
            if ($(this).prop('checked')) {
                $('.PointAwardsInputs').show().find('input').prop('disabled', false);
            } else {
                $('.PointAwardsInputs').hide().find('input').prop('disabled', true);
            }
        }).change();
    });
})(jQuery);
