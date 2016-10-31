// Initiate on our global event.
$(document).on('contentLoad', function(e) {
    /// Author tag token input.
    var $author = $('.MultiComplete', e.target);
    var author = $author.val();

    if (author && author.length) {
        author = author.split(",");
        for (i = 0; i < author.length; i++) {
            author[i] = { id: i, name: author[i] };
        }
    } else {
        author = [];
    }

    $author.tokenInput(gdn.url('/user/tagsearch'), {
        hintText: gdn.definition("TagHint", "Start to type..."),
        tokenValue: 'name',
        searchingText: '', // search text gives flickery ux, don't like
        searchDelay: 300,
        minChars: 1,
        maxLength: 25,
        prePopulate: author,
        animateDropdown: false,
        tokenLimit: 1
    });
});
