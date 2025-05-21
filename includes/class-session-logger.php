<?php
/**
 * Session Logger Class
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session Logger class
 */
class UAL_Session_Logger {
    
    /**
     * Database instance
     *
     * @var UAL_Database
     */
    private $db;
    
    /**
     * Current session ID
     *
     * @var int
     */
    private $current_session_id = 0;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new UAL_Database();
        
        // Register hooks for user login and logout
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        
        // Register hook for tracking last activity
        add_action('wp', array($this, 'track_user_activity'));
        
        // Maybe close abandoned sessions on login
        add_action('wp_login', array($this, 'maybe_close_abandoned_sessions'), 5, 2);
    }
    
    /**
     * Log user login
     *
     * @param string $username Username
     * @param WP_User $user User object
     */
    public function log_user_login($username, $user) {
        // Get user IP address
        $ip_address = $this->get_user_ip();
        
        // Store session in database
        $this->current_session_id = $this->db->insert_session($user->ID, $username, $ip_address);
        
        // Store current session ID in user meta
        update_user_meta($user->ID, '_ual_current_session', $this->current_session_id);
    }
    
    /**
     * Log user logout
     */
    public function log_user_logout() {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            // Get current session from user meta
            $session_id = get_user_meta($user_id, '_ual_current_session', true);
            
            // Update session logout time
            $this->db->update_session_logout($user_id, $session_id);
            
            // Clear current session
            delete_user_meta($user_id, '_ual_current_session');
        }
    }
    
    /**
     * Track user activity to prevent session abandonment
     */
    public function track_user_activity() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $last_activity = get_user_meta($user_id, '_ual_last_activity', true);
        $now = time();
        
        // Update last activity time
        update_user_meta($user_id, '_ual_last_activity', $now);
        
        // If this is a new visit after 30 minutes of inactivity, close previous session and start new one
        if ($last_activity && ($now - $last_activity > 1800)) {
            // Get current session
            $session_id = get_user_meta($user_id, '_ual_current_session', true);
            
            if ($session_id) {
                // Close previous session
                $this->db->update_session_logout($user_id, $session_id);
                
                // Start new session
                $user_data = get_userdata($user_id);
                $ip_address = $this->get_user_ip();
                $new_session_id = $this->db->insert_session($user_id, $user_data->user_login, $ip_address);
                
                // Update current session
                update_user_meta($user_id, '_ual_current_session', $new_session_id);
            }
        }
    }
    
    /**
     * Close abandoned sessions on login
     *
     * @param string $username Username
     * @param WP_User $user User object
     */
    public function maybe_close_abandoned_sessions($username, $user) {
        // Get latest active session
        $session = $this->db->get_latest_active_session($user->ID);
        
        if ($session) {
            // Close session if it's been open for more than 12 hours
            $login_time = new DateTime($session->login_time);
            $now = new DateTime(current_time('mysql', true));
            $diff = $now->getTimestamp() - $login_time->getTimestamp();
            
            if ($diff > 12 * 3600) {
                $this->db->update_session_logout($user->ID, $session->id);
            }
        }
    }
    
    /**
     * Get current session ID for the logged-in user
     *
     * @return int Session ID or 0 if not in a session
     */
    public function get_current_session_id() {
        if (!is_user_logged_in()) {
            return 0;
        }
        
        $user_id = get_current_user_id();
        $session_id = get_user_meta($user_id, '_ual_current_session', true);
        
        if (!$session_id) {
            // No stored session ID, check database for active session
            $session = $this->db->get_latest_active_session($user_id);
            
            if ($session) {
                $session_id = $session->id;
                update_user_meta($user_id, '_ual_current_session', $session_id);
            }
        }
        
        return (int) $session_id;
    }
    
    /**
     * Get user IP address
     *
     * @return string IP address
     */
    private function get_user_ip() {
        $ip = '127.0.0.1';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        
        return $ip;
    }
}