<?php
/**
 * Fluent Forms Integration
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fluent Forms Integration class
 */
class UAL_FluentForms_Integration {
    
    /**
     * Activity logger instance
     *
     * @var UAL_Activity_Logger
     */
    private $activity_logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->activity_logger = user_activity_logger()->activity_logger;
        
        // Add hooks for Fluent Forms integration
        add_action('fluentform_submission_inserted', array($this, 'log_form_submission'), 10, 3);
    }
    
    /**
     * Log form submission
     *
     * @param int $entry_id Entry ID
     * @param array $form_data Form data
     * @param object $form Form object
     */
    public function log_form_submission($entry_id, $form_data, $form) {
        // Only log for logged-in users
        if (!is_user_logged_in()) {
            return;
        }
        
        // Get form ID and title
        $form_id = isset($form->id) ? $form->id : 0;
        $form_title = isset($form->title) ? $form->title : sprintf(__('Form #%d', 'user-activity-logger'), $form_id);
        
        // Extract relevant submission data
        $submission_data = array();
        
        if (!empty($form_data) && is_array($form_data)) {
            // Extract only form fields, excluding internal fields
            $excluded_fields = array('_wp_http_referer', '_fluentform_1_fluentformnonce');
            
            foreach ($form_data as $key => $value) {
                if (!in_array($key, $excluded_fields) && !is_array($value) && !is_object($value)) {
                    // Truncate long values
                    if (is_string($value) && strlen($value) > 100) {
                        $value = substr($value, 0, 97) . '...';
                    }
                    
                    $submission_data[$key] = $value;
                } elseif (is_array($value)) {
                    // For arrays, just indicate there was data
                    $submission_data[$key] = '[array data]';
                }
            }
        }
        
        // Add entry ID
        $submission_data['entry_id'] = $entry_id;
        
        // Log the form submission
        $this->activity_logger->log_form_submission($form_id, $form_title, $submission_data);
    }
}