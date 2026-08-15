<?php
define('WP_USE_THEMES', false);
require_once __DIR__ . '/../../wp-load.php';

$js = get_option('vamtam_additional_js');
$js['footer'] = <<<'JS'
jQuery(document).ready(function($) {
    $('.elementor-widget-button').each(function() {
        var $widget = $(this);
        var $button = $widget.find('.elementor-button');
        
        // Simplify button structure
        $button.removeClass('elementor-button-link elementor-size-sm')
               .addClass('elementor-button-minimal');
        
        // Preserve text correctly across vamtam-button.js transformations
        var text = $button.find('.vamtam-btn-text').text() || $button.find('.elementor-button-text').text() || $button.text();
        if (text && text.trim().length > 0) {
            $button.html(text.trim());
        }
    });
});
JS;

update_option('vamtam_additional_js', $js);
echo "Updated vamtam_additional_js footer script cleanly!\n";
