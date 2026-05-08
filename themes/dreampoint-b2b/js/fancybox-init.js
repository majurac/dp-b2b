( function () {
    if ( typeof Fancybox === 'undefined' ) return;

    Fancybox.bind( '[data-fancybox]', {
        Thumbs:          false,
        idle:            false,
        animated:        true,
        hideScrollbar:   false,
        compact:         false,
    } );
} )();
