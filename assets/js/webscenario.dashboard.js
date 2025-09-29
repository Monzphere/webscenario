jQuery(document).ready(function($) {
    
    $('.webscenario-expandable').each(function() {
        const $row = $(this);
        const $detailsRow = $row.next('.webscenario-details');
        
        
        $row.find('td:first').prepend(
            $('<span>', {
                class: 'arrow-right',
                css: {
                    display: 'inline-block',
                    margin: '0 5px',
                    transition: 'transform 0.2s',
                    cursor: 'pointer'
                }
            }).html('')
        );

        
        $row.css('cursor', 'pointer').click(function() {
            $detailsRow.toggle();
            const $arrow = $row.find('.arrow-right');
            $arrow.css('transform', $detailsRow.is(':visible') ? 'rotate(90deg)' : 'none');
        });

        
        $detailsRow.hide();
    });
});