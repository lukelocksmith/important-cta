jQuery(function ($) {

    // WordPress color picker
    $('.icta-color-picker').wpColorPicker();

    // Media library picker
    $(document).on('click', '.icta-media-btn', function (e) {
        e.preventDefault();
        var btn     = $(this);
        var targetId = btn.data('target');
        var input   = $('#' + targetId);
        var preview = btn.closest('.icta-media-group').find('.icta-img-preview');

        var frame = wp.media({
            title:    'Wybierz obraz',
            button:   { text: 'Użyj tego obrazu' },
            multiple: false,
            library:  { type: 'image' },
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            input.val(attachment.url);
            preview.attr('src', attachment.url).show();
        });

        frame.open();
    });

});
