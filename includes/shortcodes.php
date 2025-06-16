<?php
/**
 * Shortcodes functionality
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes class
 */
class UAL_Shortcodes {
    
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
    }
    
    /**
     * Initialize shortcodes
     */
    public function init() {
        add_shortcode('user_activity_overview', array($this, 'user_activity_overview_shortcode'));
        add_shortcode('user_activity_detail', array($this, 'user_activity_detail_shortcode'));
        
        // Add shortcode assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue assets for shortcodes
     */
    public function enqueue_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        // Check if either shortcode is present in the post content
        if (has_shortcode($post->post_content, 'user_activity_overview') || 
            has_shortcode($post->post_content, 'user_activity_detail')) {
            
            // Register and enqueue styles
            wp_register_style(
                'ual-shortcodes', 
                UAL_PLUGIN_URL . 'assets/css/shortcodes.css',
                array(),
                UAL_VERSION
            );
            wp_enqueue_style('ual-shortcodes');
            
            // Register and enqueue scripts
            wp_register_script(
                'ual-shortcodes',
                UAL_PLUGIN_URL . 'assets/js/shortcodes.js',
                array('jquery'),
                UAL_VERSION,
                true
            );
            wp_enqueue_script('ual-shortcodes');
        }
    }
    
    /**
     * User activity overview shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function user_activity_overview_shortcode($atts) {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return '<div class="ual-error">' . __('You do not have permission to view this content.', 'user-activity-logger') . '</div>';
        }
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => 20,
            'page' => 1,
        ), $atts, 'user_activity_overview');
        
        $limit = intval($atts['limit']);
        $page = intval($atts['page']);
        $offset = ($page - 1) * $limit;
        
        // Get user summary data
        $users = $this->db->get_user_summary($limit, $offset);
        $total_users = $this->db->count_users_with_sessions();
        $total_pages = ceil($total_users / $limit);
        
        // Start output buffer
        ob_start();
        
        echo '<div class="ual-overview-container">';
        echo '<h2>' . __('User Activity Overview', 'user-activity-logger') . '</h2>';
        
        if (empty($users)) {
            echo '<div class="ual-no-data">' . __('No user activity data found.', 'user-activity-logger') . '</div>';
        } else {
            echo '<table class="ual-overview-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Username', 'user-activity-logger') . '</th>';
            echo '<th>' . __('Display Name', 'user-activity-logger') . '</th>';
            echo '<th>' . __('Last Login', 'user-activity-logger') . '</th>';
            echo '<th>' . __('Last Session Duration', 'user-activity-logger') . '</th>';
            echo '<th>' . __('Total Sessions', 'user-activity-logger') . '</th>';
            echo '<th>' . __('Actions', 'user-activity-logger') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($users as $user) {
                $last_login = !empty($user->last_login) ? 
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($user->last_login)) : 
                    __('Never', 'user-activity-logger');
                
                $duration = !empty($user->last_session_duration) ? 
                    $this->format_duration($user->last_session_duration) : 
                    __('N/A', 'user-activity-logger');
                
                echo '<tr>';
                echo '<td>' . esc_html($user->username) . '</td>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . esc_html($last_login) . '</td>';
                echo '<td>' . esc_html($duration) . '</td>';
                echo '<td>' . intval($user->total_sessions) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(add_query_arg(array('user_id' => $user->user_id), get_permalink())) . '" class="ual-view-details-btn">' . __('View Details', 'user-activity-logger') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            // Pagination
            if ($total_pages > 1) {
                echo '<div class="ual-pagination">';
                
                for ($i = 1; $i <= $total_pages; $i++) {
                    $active_class = ($i == $page) ? ' ual-pagination-active' : '';
                    echo '<a href="' . esc_url(add_query_arg(array('page' => $i), get_permalink())) . '" class="ual-pagination-link' . $active_class . '">' . $i . '</a>';
                }
                
                echo '</div>';
            }
        }
        
        echo '</div>';
        
        // Get output buffer contents and clean buffer
        $output = ob_get_clean();
        
        return $output;
    }
    
    /**
     * User activity detail shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function user_activity_detail_shortcode($atts) {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return '<div class="ual-error">' . __('You do not have permission to view this content.', 'user-activity-logger') . '</div>';
        }
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'user_id' => 0,
            'user_email' => '',
            'limit' => 10,
        ), $atts, 'user_activity_detail');
        
        // Check for user_id in URL parameter (overrides shortcode attribute)
        if (isset($_GET['user_id'])) {
            $atts['user_id'] = intval($_GET['user_id']);
        }
        
        $user_id = intval($atts['user_id']);
        $user_email = sanitize_email($atts['user_email']);
        $limit = intval($atts['limit']);
        
        // Get user ID from email if provided
        if (!$user_id && !empty($user_email)) {
            $user = get_user_by('email', $user_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        // Check if we have a valid user ID
        if (!$user_id) {
            return '<div class="ual-error">' . __('No valid user specified.', 'user-activity-logger') . '</div>';
        }
        
        // Get user data
        $user = get_userdata($user_id);
        
        if (!$user) {
            return '<div class="ual-error">' . __('User not found.', 'user-activity-logger') . '</div>';
        }
        
        // Get sessions
        $sessions = $this->db->get_user_sessions($user_id, $limit, 0);
        
        // Start output buffer
        ob_start();
        
        echo '<div class="ual-detail-container">';
        
        // Add back link if we came from overview
        if (isset($_GET['user_id'])) {
            echo '<p><a href="' . esc_url(remove_query_arg('user_id', get_permalink())) . '" class="ual-back-link">';
            echo '<span class="dashicons dashicons-arrow-left-alt"></span> ' . __('Back to Overview', 'user-activity-logger');
            echo '</a></p>';
        }
        
        echo '<div class="ual-user-summary">';
        echo '<h2>' . sprintf(__('Activity Log for %s', 'user-activity-logger'), $user->display_name) . '</h2>';
        echo '<p>' . sprintf(__('Username: %s', 'user-activity-logger'), $user->user_login) . '</p>';
        echo '<p>' . sprintf(__('Email: %s', 'user-activity-logger'), $user->user_email) . '</p>';
        $export_url = esc_url(admin_url('admin-post.php?action=ual_export_pdf&user_id=' . $user_id));
        echo '<p><a href="' . $export_url . '" class="ual-view-details-btn">' . __('Export PDF', 'user-activity-logger') . '</a></p>';
        echo '</div>';
        
        if (empty($sessions)) {
            echo '<div class="ual-no-data">' . __('No sessions found for this user.', 'user-activity-logger') . '</div>';
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
                    echo '<table class="ual-activities-table">';
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
        }
        
        echo '</div>'; // .ual-detail-container
        
        // Add inline JavaScript
        echo '<script>
            jQuery(document).ready(function($) {
                $(".ual-session-header").on("click", function() {
                    var activities = $(this).next(".ual-session-activities");
                    activities.slideToggle();
                    $(this).find(".ual-session-toggle .dashicons").toggleClass("dashicons-arrow-down-alt2 dashicons-arrow-up-alt2");
                });
            });
        </script>';
        
        // Get output buffer contents and clean buffer
        $output = ob_get_clean();
        
        return $output;
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