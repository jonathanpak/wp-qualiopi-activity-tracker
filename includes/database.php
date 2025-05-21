<?php
/**
 * Database functionality
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class for User Activity Logger
 */
class UAL_Database {
    
    /**
     * Sessions table name
     *
     * @var string
     */
    private $sessions_table;
    
    /**
     * Activities table name
     *
     * @var string
     */
    private $activities_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->sessions_table = $wpdb->prefix . 'ual_sessions';
        $this->activities_table = $wpdb->prefix . 'ual_activities';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sessions table
        $sql_sessions = "CREATE TABLE {$this->sessions_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            username varchar(60) NOT NULL,
            ip_address varchar(45) NOT NULL,
            login_time datetime NOT NULL,
            logout_time datetime DEFAULT NULL,
            session_duration int(11) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY login_time (login_time),
            KEY logout_time (logout_time)
        ) $charset_collate;";
        
        // Activities table
        $sql_activities = "CREATE TABLE {$this->activities_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_data longtext NOT NULL,
            object_id bigint(20) DEFAULT NULL,
            object_name varchar(255) DEFAULT NULL,
            object_url varchar(255) DEFAULT NULL,
            activity_time datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            KEY activity_time (activity_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_activities);
    }
    
    /**
     * Get tables
     *
     * @return array Tables names
     */
    public function get_tables() {
        return array(
            'sessions' => $this->sessions_table,
            'activities' => $this->activities_table,
        );
    }
    
    /**
     * Insert a new session
     *
     * @param int $user_id User ID
     * @param string $username Username
     * @param string $ip_address IP address
     * @return int|false Session ID or false on failure
     */
    public function insert_session($user_id, $username, $ip_address) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->sessions_table,
            array(
                'user_id' => $user_id,
                'username' => $username,
                'ip_address' => $ip_address,
                'login_time' => current_time('mysql', true),
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update session logout time
     *
     * @param int $user_id User ID
     * @param int $session_id Session ID (optional)
     * @return bool Success or failure
     */
    public function update_session_logout($user_id, $session_id = 0) {
        global $wpdb;
        
        $now = current_time('mysql', true);
        
        if ($session_id) {
            // Update specific session
            $session = $this->get_session($session_id);
            if ($session) {
                $login_time = new DateTime($session->login_time);
                $logout_time = new DateTime($now);
                $diff = $logout_time->getTimestamp() - $login_time->getTimestamp();
                
                return $wpdb->update(
                    $this->sessions_table,
                    array(
                        'logout_time' => $now,
                        'session_duration' => $diff,
                    ),
                    array(
                        'id' => $session_id,
                        'user_id' => $user_id,
                    ),
                    array('%s', '%d'),
                    array('%d', '%d')
                );
            }
        } else {
            // Update the most recent session without a logout time
            $session = $this->get_latest_active_session($user_id);
            
            if ($session) {
                $login_time = new DateTime($session->login_time);
                $logout_time = new DateTime($now);
                $diff = $logout_time->getTimestamp() - $login_time->getTimestamp();
                
                return $wpdb->update(
                    $this->sessions_table,
                    array(
                        'logout_time' => $now,
                        'session_duration' => $diff,
                    ),
                    array(
                        'id' => $session->id,
                    ),
                    array('%s', '%d'),
                    array('%d')
                );
            }
        }
        
        return false;
    }
    
    /**
     * Get session by ID
     *
     * @param int $session_id Session ID
     * @return object|null Session object or null
     */
    public function get_session($session_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sessions_table} WHERE id = %d",
            $session_id
        ));
    }
    
    /**
     * Get latest active session for a user
     *
     * @param int $user_id User ID
     * @return object|null Session object or null
     */
    public function get_latest_active_session($user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->sessions_table} 
            WHERE user_id = %d AND logout_time IS NULL 
            ORDER BY login_time DESC 
            LIMIT 1",
            $user_id
        ));
    }
    
    /**
     * Insert an activity
     *
     * @param int $session_id Session ID
     * @param int $user_id User ID
     * @param string $activity_type Activity type
     * @param array $activity_data Activity data
     * @param int $object_id Object ID (optional)
     * @param string $object_name Object name (optional)
     * @param string $object_url Object URL (optional)
     * @return int|false Activity ID or false on failure
     */
    public function insert_activity($session_id, $user_id, $activity_type, $activity_data, $object_id = null, $object_name = null, $object_url = null) {
        global $wpdb;
        
        // Ensure we have a session ID
        if (!$session_id) {
            $session = $this->get_latest_active_session($user_id);
            $session_id = $session ? $session->id : 0;
            
            // If no active session found, create a new one
            if (!$session_id) {
                $user = get_userdata($user_id);
                if ($user) {
                    $ip = $this->get_user_ip();
                    $session_id = $this->insert_session($user_id, $user->user_login, $ip);
                }
            }
        }
        
        // Ensure we have a valid session
        if (!$session_id) {
            return false;
        }
        
        // Serialize activity data
        $serialized_data = maybe_serialize($activity_data);
        
        $result = $wpdb->insert(
            $this->activities_table,
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
                'activity_type' => $activity_type,
                'activity_data' => $serialized_data,
                'object_id' => $object_id,
                'object_name' => $object_name,
                'object_url' => $object_url,
                'activity_time' => current_time('mysql', true),
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
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
    
    /**
     * Get all sessions for a user
     *
     * @param int $user_id User ID
     * @param int $limit Limit number of results (optional)
     * @param int $offset Offset for results (optional)
     * @return array Sessions
     */
    public function get_user_sessions($user_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->sessions_table} 
            WHERE user_id = %d 
            ORDER BY login_time DESC 
            LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }
    
    /**
     * Get activities for a session
     *
     * @param int $session_id Session ID
     * @return array Activities
     */
    public function get_session_activities($session_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->activities_table} 
            WHERE session_id = %d 
            ORDER BY activity_time ASC",
            $session_id
        ));
    }
    
    /**
     * Get activities for a user
     *
     * @param int $user_id User ID
     * @param int $limit Limit number of results (optional)
     * @param int $offset Offset for results (optional)
     * @return array Activities
     */
    public function get_user_activities($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->activities_table} 
            WHERE user_id = %d 
            ORDER BY activity_time DESC 
            LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }
    
    /**
     * Get user summary
     *
     * @param int $limit Limit number of results (optional)
     * @param int $offset Offset for results (optional)
     * @return array User summaries
     */
    public function get_user_summary($limit = 20, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                u.ID as user_id,
                u.user_login as username,
                u.user_email as email,
                u.display_name as display_name,
                MAX(s.login_time) as last_login,
                (SELECT s2.session_duration FROM {$this->sessions_table} s2 
                 WHERE s2.user_id = u.ID 
                 ORDER BY s2.login_time DESC LIMIT 1) as last_session_duration,
                COUNT(DISTINCT s.id) as total_sessions
            FROM 
                {$wpdb->users} u
            LEFT JOIN 
                {$this->sessions_table} s ON u.ID = s.user_id
            GROUP BY 
                u.ID, u.user_login, u.user_email, u.display_name
            ORDER BY 
                last_login DESC
            LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    /**
     * Count total users with sessions
     *
     * @return int Total users
     */
    public function count_users_with_sessions() {
        global $wpdb;
        
        return $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->sessions_table}"
        );
    }
}