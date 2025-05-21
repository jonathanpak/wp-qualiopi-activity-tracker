<?php
/**
 * Activity Logger Class
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activity Logger class
 */
class UAL_Activity_Logger {
    
    /**
     * Database instance
     *
     * @var UAL_Database
     */
    private $db;
    
    /**
     * Session logger instance
     *
     * @var UAL_Session_Logger
     */
    private $session_logger;
    
    /**
     * Activity types
     *
     * @var array
     */
    private $activity_types = array(
        'page_visit' => 'Page Visit',
        'form_submission' => 'Form Submission',
        'lesson_completed' => 'Lesson Completed',
        'topic_completed' => 'Topic Completed',
        'quiz_completed' => 'Quiz Completed',
        'course_completed' => 'Course Completed',
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new UAL_Database();
        $this->session_logger = user_activity_logger()->session_logger;
    }
    
    /**
     * Log page visit
     *
     * @param int $post_id Post ID
     * @param string $post_title Post title
     * @param string $post_url Post URL
     * @return int|false Activity ID or false on failure
     */
    public function log_page_visit($post_id, $post_title, $post_url) {
        // Only log for logged-in users
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $session_id = $this->session_logger->get_current_session_id();
        
        // Prepare activity data
        $activity_data = array(
            'post_id' => $post_id,
            'post_type' => get_post_type($post_id),
        );
        
        // Log the activity
        return $this->db->insert_activity(
            $session_id,
            $user_id,
            'page_visit',
            $activity_data,
            $post_id,
            $post_title,
            $post_url
        );
    }
    
    /**
     * Log form submission
     *
     * @param int $form_id Form ID
     * @param string $form_name Form name
     * @param array $submission_data Submission data
     * @return int|false Activity ID or false on failure
     */
    public function log_form_submission($form_id, $form_name, $submission_data = array()) {
        // Only log for logged-in users
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $session_id = $this->session_logger->get_current_session_id();
        
        // Prepare activity data - sanitize and limit submission data
        $safe_submission_data = array();
        
        // Only include non-sensitive fields if available
        if (!empty($submission_data) && is_array($submission_data)) {
            // Filter out potentially sensitive data
            $excluded_keys = array(
                'password', 'pass', 'pwd', 'secret', 'credit', 'card', 'cvv', 
                'ssn', 'social', 'security', 'private'
            );
            
            foreach ($submission_data as $key => $value) {
                $include = true;
                
                // Skip if the key contains sensitive information
                foreach ($excluded_keys as $excluded) {
                    if (strpos(strtolower($key), $excluded) !== false) {
                        $include = false;
                        break;
                    }
                }
                
                if ($include) {
                    // For arrays or objects, just note that they existed
                    if (is_array($value) || is_object($value)) {
                        $safe_submission_data[$key] = '[complex data]';
                    } else {
                        // Truncate long values
                        $safe_submission_data[$key] = strlen($value) > 100 ? 
                            substr($value, 0, 97) . '...' : $value;
                    }
                }
            }
        }
        
        $activity_data = array(
            'form_id' => $form_id,
            'submission_summary' => $safe_submission_data,
        );
        
        // Log the activity
        return $this->db->insert_activity(
            $session_id,
            $user_id,
            'form_submission',
            $activity_data,
            $form_id,
            $form_name,
            '' // No specific URL for forms
        );
    }
    
    /**
     * Log LearnDash activity
     *
     * @param string $activity_type Activity type
     * @param int $object_id Object ID
     * @param string $object_name Object name
     * @param string $object_url Object URL
     * @param array $activity_data Additional activity data
     * @return int|false Activity ID or false on failure
     */
    public function log_learndash_activity($activity_type, $object_id, $object_name, $object_url = '', $activity_data = array()) {
        // Only log for logged-in users
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $session_id = $this->session_logger->get_current_session_id();
        
        // Validate activity type
        if (!in_array($activity_type, array('lesson_completed', 'topic_completed', 'quiz_completed', 'course_completed'))) {
            return false;
        }
        
        // Log the activity
        return $this->db->insert_activity(
            $session_id,
            $user_id,
            $activity_type,
            $activity_data,
            $object_id,
            $object_name,
            $object_url
        );
    }
    
    /**
     * Get activity types
     *
     * @return array Activity types
     */
    public function get_activity_types() {
        return apply_filters('ual_activity_types', $this->activity_types);
    }
    
    /**
     * Get activity type label
     *
     * @param string $type Activity type
     * @return string Activity type label
     */
    public function get_activity_type_label($type) {
        $types = $this->get_activity_types();
        return isset($types[$type]) ? $types[$type] : ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * Get activity data formatted for display
     *
     * @param object $activity Activity object
     * @return array Formatted activity data
     */
    public function get_formatted_activity($activity) {
        $data = maybe_unserialize($activity->activity_data);
        $type_label = $this->get_activity_type_label($activity->activity_type);
        
        $formatted = array(
            'id' => $activity->id,
            'session_id' => $activity->session_id,
            'user_id' => $activity->user_id,
            'type' => $activity->activity_type,
            'type_label' => $type_label,
            'time' => $activity->activity_time,
            'time_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($activity->activity_time)),
            'object_id' => $activity->object_id,
            'object_name' => $activity->object_name,
            'object_url' => $activity->object_url,
            'data' => $data,
        );
        
        switch ($activity->activity_type) {
            case 'page_visit':
                $formatted['icon'] = 'dashicons-admin-page';
                $formatted['summary'] = sprintf(__('Visited page: %s', 'user-activity-logger'), $activity->object_name);
                break;
                
            case 'form_submission':
                $formatted['icon'] = 'dashicons-feedback';
                $formatted['summary'] = sprintf(__('Submitted form: %s', 'user-activity-logger'), $activity->object_name);
                break;
                
            case 'lesson_completed':
                $formatted['icon'] = 'dashicons-welcome-learn-more';
                $formatted['summary'] = sprintf(__('Completed lesson: %s', 'user-activity-logger'), $activity->object_name);
                break;
                
            case 'topic_completed':
                $formatted['icon'] = 'dashicons-welcome-learn-more';
                $formatted['summary'] = sprintf(__('Completed topic: %s', 'user-activity-logger'), $activity->object_name);
                break;
                
            case 'quiz_completed':
                $formatted['icon'] = 'dashicons-clipboard';
                $formatted['summary'] = sprintf(__('Completed quiz: %s', 'user-activity-logger'), $activity->object_name);
                
                // Add score info if available
                if (!empty($data['score'])) {
                    $formatted['summary'] .= sprintf(__(', Score: %s', 'user-activity-logger'), $data['score']);
                }
                
                if (isset($data['passed'])) {
                    $formatted['summary'] .= $data['passed'] ? 
                        __(', Passed', 'user-activity-logger') : 
                        __(', Failed', 'user-activity-logger');
                }
                break;
                
            case 'course_completed':
                $formatted['icon'] = 'dashicons-awards';
                $formatted['summary'] = sprintf(__('Completed course: %s', 'user-activity-logger'), $activity->object_name);
                break;
                
            default:
                $formatted['icon'] = 'dashicons-marker';
                $formatted['summary'] = sprintf(__('%s: %s', 'user-activity-logger'), $type_label, $activity->object_name);
                break;
        }
        
        return apply_filters('ual_formatted_activity', $formatted, $activity);
    }
}