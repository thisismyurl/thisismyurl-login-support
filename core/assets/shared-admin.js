window.timuInitAdminUi = function( $ ) {
    if ( $.fn.wpColorPicker ) {
        $( '.timu-color-field' ).wpColorPicker();
    }

    function toggleSettings() {
        $( '.timu-toggle-trigger' ).each( function() {
            var $trigger = $( this );
            var target   = $trigger.data( 'target' );

            if ( ! target ) {
                return;
            }

            var expanded = $trigger.is( ':checked' );

            // Keep aria-expanded in sync with the conditional rows the toggle
            // reveals (a11y finding P2 / 4.1.2).
            if ( $trigger.is( '[aria-controls]' ) ) {
                $trigger.attr( 'aria-expanded', expanded ? 'true' : 'false' );
            }

            if ( expanded ) {
                $( target ).stop( true, true ).fadeIn( 150 );
            } else {
                $( target ).hide();
            }
        } );
    }

    toggleSettings();
    $( document ).on( 'change', '.timu-toggle-trigger', toggleSettings );

    $( document ).on( 'click', '.media_btn', function( event ) {
        event.preventDefault();

        var $button  = $( this );
        var $target  = $( $button.data( 'target' ) );
        var $preview = $( $button.data( 'preview' ) );
        var frame    = wp.media( { title: 'Select Media', multiple: false } );

        frame.on( 'select', function() {
            var asset = frame.state().get( 'selection' ).first().toJSON();

            $target.val( asset.url );
            $preview.html( '<img src="' + asset.url + '" alt="" />' );
        } );

        frame.open();
    } );
};

jQuery( document ).ready( function( $ ) {
    window.timuInitAdminUi( $ );
} );