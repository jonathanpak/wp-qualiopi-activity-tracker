/**
 * User Activity Logger Admin Scripts
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Session toggle
        $('.ual-session-toggle button').on('click', function() {
            var $container = $(this).closest('.ual-session-box');
            var $activities = $container.find('.ual-session-activities');
            var $icon = $(this).find('.ual-toggle-icon');
            
            $activities.slideToggle(200);
            $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });
    });
    
})(jQuery);