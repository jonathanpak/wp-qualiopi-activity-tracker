<?php
/**
 * FluentCRM Integration
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FluentCRM Integration class
 */
class UAL_FluentCRM_Integration {
    
    /**
     * Database instance
     *
     * @var UAL_Database
     */
    private $db;
    
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
        $this->db = new UAL_Database();
        $this->activity_logger = user_activity_logger()->activity_logger;
        
        // Add hooks for FluentCRM integration
        add_filter('fluentcrm_profile_tabs', array($this, 'add_profile_tab'));
        add_action('fluentcrm_profile_tab_content_user_activity', array($this, 'profile_tab_content'));
    }
    
    /**
     * Add profile tab to FluentCRM
     *
     * @param array $tabs Profile tabs
     * @return array Modified tabs
     */
    public function add_profile_tab($tabs) {
        $tabs['user_activity'] = array(
            'name' => __('User Activity Log', 'user-activity-logger'),
            'slug' => 'user_activity',
            'icon' => 'dashicons dashicons-backup',
        );
        
        return $tabs;
    }
    
    /**
     * Profile tab content
     *
     * @param object $subscriber FluentCRM subscriber object
     */
    public function profile_tab_content($subscriber) {
        // Check if subscriber has a WordPress user
        $user_id = $this->get_wp_user_id_from_subscriber($subscriber);
        
        if (!$user_id) {
            echo '<div class="notice notice-info"><p>';
            _e('This contact does not have an associated WordPress user account.', 'user-activity-logger');
            echo '</p></div>';
            return;
        }
        
        // Get user data
        $user = get_userdata($user_id);
        
        // Get sessions
        $sessions = $this->db->get_user_sessions($user_id, 10, 0);
        
        echo '<div class="ual-fluentcrm-tab">';
        
        echo '<div class="ual-user-summary">';
        echo '<h2>' . sprintf(__('Activity Log for %s', 'user-activity-logger'), $user->display_name) . '</h2>';
        echo '<p>' . sprintf(__('Username: %s', 'user-activity-logger'), $user->user_login) . '</p>';
        echo '<p>' . sprintf(__('Email: %s', 'user-activity-logger'), $user->user_email) . '</p>';
        echo '</div>';
        
        if (empty($sessions)) {
            echo '<div class="notice notice-info"><p>';
            _e('No sessions found for this user.', 'user-activity-logger');
            echo '</p></div>';
        } else {
            echo '<div class="ual-sessions-list">';
            
            foreach ($sessions as $session) {
                $login_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->login_time));
                $logout_time = $session->logout_time ? 
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->logout_time)) : 
                    __('Active Session', 'user-activity-logger');
                
                $duration = $session->session_duration ? 
                    $this->format_duration($session->session_duration) : 
                    __('Active', 'user-activity-logger');
                
                echo '<div class="ual-session-item">';
                echo '<div class="ual-session-header" data-session-id="' . esc_attr($session->id) . '">';
                echo '<div class="ual-session-icon"><span class="dashicons dashicons-admin-users"></span></div>';
                echo '<div class="ual-session-info">';
                echo '<h3>' . sprintf(__('Session: %s', 'user-activity-logger'), $login_time) . '</h3>';
                echo '<div class="ual-session-meta">';
                echo '<span>' . sprintf(__('IP: %s', 'user-activity-logger'), esc_html($session->ip_address)) . '</span>';
                echo '<span>' . sprintf(__('Duration: %s', 'user-activity-logger'), $duration) . '</span>';
                echo '<span>' . sprintf(__('Ended: %s', 'user-activity-logger'), $logout_time) . '</span>';
                echo '</div>'; // .ual-session-meta
                echo '</div>'; // .ual-session-info
                echo '<div class="ual-session-toggle"><span class="dashicons dashicons-arrow-down-alt2"></span></div>';
                echo '</div>'; // .ual-session-header
                
                // Get session activities
                $activities = $this->db->get_session_activities($session->id);
                
                echo '<div class="ual-session-activities" style="display: none;">';
                
                if (empty($activities)) {
                    echo '<p class="ual-no-activities">' . __('No activities recorded in this session.', 'user-activity-logger') . '</p>';
                } else {
                    echo '<table class="ual-activities-table widefat">';
                    echo '<thead><tr>';
                    echo '<th>' . __('Time', 'user-activity-logger') . '</th>';
                    echo '<th>' . __('Activity', 'user-activity-logger') . '</th>';
                    echo '<th>' . __('Details', 'user-activity-logger') . '</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($activities as $activity) {
                        $formatted = $this->activity_logger->get_formatted_activity($activity);
                        
                        echo '<tr class="ual-activity-' . esc_attr($activity->activity_type) . '">';
                        echo '<td>' . esc_html($formatted['time_formatted']) . '</td>';
                        echo '<td><span class="dashicons ' . esc_attr($formatted['icon']) . '"></span> ' . esc_html($formatted['type_label']) . '</td>';
                        echo '<td>' . esc_html($formatted['summary']);
                        
                        // Add URL if available
                        if (!empty($formatted['object_url'])) {
                            echo ' <a href="' . esc_url($formatted['object_url']) . '" target="_blank"><span class="dashicons dashicons-external"></span></a>';
                        }
                        
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                }
                
                echo '</div>'; // .ual-session-activities
                echo '</div>'; // .ual-session-item
            }
            
            echo '</div>'; // .ual-sessions-list
            
            // Add JavaScript for toggling session activities
            echo '<script>
                jQuery(document).ready(function($) {
                    $(".ual-session-header").on("click", function() {
                        var activities = $(this).next(".ual-session-activities");
                        activities.slideToggle();
                        $(this).find(".ual-session-toggle .dashicons").toggleClass("dashicons-arrow-down-alt2 dashicons-arrow-up-alt2");
                    });
                });
            </script>';
            
            // Add inline styles
            echo '<style>
                .ual-fluentcrm-tab {
                    padding: 15px;
                }
                .ual-user-summary {
                    margin-bottom: 20px;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 4px;
                }
                .ual-sessions-list {
                    margin-top: 20px;
                }
                .ual-session-item {
                    margin-bottom: 15px;
                    border: 1px solid #e5e5e5;
                    border-radius: 4px;
                    overflow: hidden;
                }
                .ual-session-header {
                    display: flex;
                    align-items: center;
                    padding: 10px 15px;
                    background: #f5f5f5;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .ual-session-header:hover {
                    background: #efefef;
                }
                .ual-session-icon {
                    margin-right: 15px;
                }
                .ual-session-info {
                    flex: 1;
                }
                .ual-session-info h3 {
                    margin: 0 0 5px;
                }
                .ual-session-meta {
                    display: flex;
                    font-size: 13px;
                    color: #666;
                }
                .ual-session-meta span {
                    margin-right: 15px;
                }
                .ual-session-activities {
                    padding: 15px;
                    background: #fff;
                }
                .ual-no-activities {
                    font-style: italic;
                    color: #666;
                }
                .ual-activities-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .ual-activities-table th {
                    text-align: left;
                    padding: 8px;
                    border-bottom: 1px solid #e5e5e5;
                }
                .ual-activities-table td {
                    padding: 8px;
                    border-bottom: 1px solid #f0f0f0;
                }
                .ual-activity-page_visit {
                    background-color: #f9f9f9;
                }
                .ual-activity-form_submission {
                    background-color: #f0f7fd;
                }
                .ual-activity-lesson_completed,
                .ual-activity-topic_completed,
                .ual-activity-quiz_completed,
                .ual-activity-course_completed {
                    background-color: #f0fdf0;
                }
            </style>';
        }
        
        echo '</div>'; // .ual-fluentcrm-tab
    }
    
    /**
     * Get WordPress user ID from FluentCRM subscriber
     *
     * @param object $subscriber FluentCRM subscriber object
     * @return int|false User ID or false
     */
    private function get_wp_user_id_from_subscriber($subscriber) {
        if (empty($subscriber) || !is_object($subscriber)) {
            return false;
        }
        
        // Check for user_id property (in case it's already available)
        if (!empty($subscriber->user_id)) {
            return $subscriber->user_id;
        }
        
        // Check via email
        if (!empty($subscriber->email)) {
            $user = get_user_by('email', $subscriber->email);
            if ($user) {
                return $user->ID;
            }
        }
        
        return false;
    }
    
    /**
     * Format duration in seconds to human-readable format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        $output = '';
        
        if ($hours > 0) {
            $output .= $hours . 'h ';
        }
        
        if ($minutes > 0 || $hours > 0) {
            $output .= $minutes . 'm ';
        }
        
        $output .= $seconds . 's';
        
        return $output;
    }
}