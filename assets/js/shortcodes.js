/**
 * User Activity Logger Shortcode Scripts
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Session toggle
        $('.ual-session-header').on('click', function() {
            var activities = $(this).next('.ual-session-activities');
            activities.slideToggle(200);
            $(this).find('.ual-session-toggle .dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });
        
        // Add smooth transitions
        $('.ual-session-header, .ual-view-details-btn, .ual-pagination-link, .ual-back-link').on('mouseenter', function() {
            $(this).css('transition', 'all 0.2s ease');
        });
    });
    
})(jQuery);