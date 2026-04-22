jQuery(document).ready(function($){
    // Initialize Color Pickers
    $('.timu-color-field').wpColorPicker();

    // Toggle Visibility Logic
    function timuToggleSettings() {
        $('#timu_enable_shifting').is(':checked') ? $('.timu-conditional-shifting').fadeIn() : $('.timu-conditional-shifting').hide();
        $('#timu_enable_branding').is(':checked') ? $('.timu-conditional-branding').fadeIn() : $('.timu-conditional-branding').hide();
    }
    timuToggleSettings();
    $('#timu_enable_shifting, #timu_enable_branding').on('change', timuToggleSettings);

    // Media Uploader
    $('.media_btn').click(function(e) {
        e.preventDefault();
        var btn = $(this), target = $(btn.data('target')), preview = $(btn.data('preview'));
        var image = wp.media({ title: 'Select Media', multiple: false }).open()
        .on('select', function(e){
            var asset = image.state().get('selection').first().toJSON();
            target.val(asset.url);
            preview.html('<img src="'+asset.url+'">');
        });
    });
});