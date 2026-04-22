jQuery(document).ready(function($){
    // Initialize Color Pickers
    $('.timu-color-field').wpColorPicker();

    // Toggle Visibility Logic
    function timuToggleSettings() {
        // Look for the elements by name attribute if ID is not found
        var shiftingChecked = $('input[name="timu_login_options[enable_shifting]"]').is(':checked');
        var brandingChecked = $('input[name="timu_login_options[enable_branding]"]').is(':checked');

        shiftingChecked ? $('.timu-conditional-shifting').fadeIn() : $('.timu-conditional-shifting').hide();
        brandingChecked ? $('.timu-conditional-branding').fadeIn() : $('.timu-conditional-branding').hide();
    }
    
    timuToggleSettings();
    
    // Bind change event to the name attributes
    $('input[name="timu_login_options[enable_shifting]"], input[name="timu_login_options[enable_branding]"]').on('change', timuToggleSettings);

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