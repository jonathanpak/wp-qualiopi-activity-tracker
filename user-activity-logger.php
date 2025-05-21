<?php
/**
 * Plugin Name: User Activity & Session Logger
 * Plugin URI: https://example.com/plugins/user-activity-logger
 * Description: Logs user sessions and activity, with integration for FluentCRM, LearnDash, and Fluent Forms.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: user-activity-logger
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
* GitHub Plugin URI: https://github.com/jonathanpak/wp-qualiopi-activity-tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UAL_VERSION', '1.0.0');
define('UAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UAL_PLUGIN_FILE', __FILE__);

// Include required files
require_once UAL_PLUGIN_DIR . 'includes/database.php';
require_once UAL_PLUGIN_DIR . 'includes/class-session-logger.php';
require_once UAL_PLUGIN_DIR . 'includes/class-activity-logger.php';
require_once UAL_PLUGIN_DIR . 'includes/integrations/class-fluentcrm.php';
require_once UAL_PLUGIN_DIR . 'includes/integrations/class-learndash.php';
require_once UAL_PLUGIN_DIR . 'includes/integrations/class-fluentforms.php';
require_once UAL_PLUGIN_DIR . 'includes/shortcodes.php';
require_once UAL_PLUGIN_DIR . 'includes/admin/class-admin.php';

/**
 * Main plugin class
 */
class User_Activity_Logger {
    
    /**
     * Instance variable
     *
     * @var User_Activity_Logger
     */
    private static $instance = null;

    /**
     * Session logger instance
     *
     * @var UAL_Session_Logger
     */
    public $session_logger;

    /**
     * Activity logger instance
     *
     * @var UAL_Activity_Logger
     */
    public $activity_logger;

    /**
     * FluentCRM integration instance
     *
     * @var UAL_FluentCRM_Integration
     */
    public $fluentcrm;

    /**
     * LearnDash integration instance
     *
     * @var UAL_LearnDash_Integration
     */
    public $learndash;

    /**
     * Fluent Forms integration instance
     *
     * @var UAL_FluentForms_Integration
     */
    public $fluentforms;

    /**
     * Admin instance
     *
     * @var UAL_Admin
     */
    public $admin;

    /**
     * Get the singleton instance
     *
     * @return User_Activity_Logger
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize the database on plugin activation
        register_activation_hook(UAL_PLUGIN_FILE, array($this, 'activate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $database = new UAL_Database();
        $database->create_tables();
        
        // Set version
        update_option('ual_version', UAL_VERSION);
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize loggers
        $this->session_logger = new UAL_Session_Logger();
        $this->activity_logger = new UAL_Activity_Logger();
        
        // Initialize integrations if the respective plugins are active
        if ($this->is_fluentcrm_active()) {
            $this->fluentcrm = new UAL_FluentCRM_Integration();
        }
        
        if ($this->is_learndash_active()) {
            $this->learndash = new UAL_LearnDash_Integration();
        }
        
        if ($this->is_fluentforms_active()) {
            $this->fluentforms = new UAL_FluentForms_Integration();
        }
        
        // Initialize admin
        $this->admin = new UAL_Admin();
        
        // Initialize shortcodes
        $shortcodes = new UAL_Shortcodes();
        $shortcodes->init();
        
        // Register activity tracking for page visits
        add_action('wp', array($this, 'track_page_visit'));
    }

    /**
     * Track page visit for logged-in users
     */
    public function track_page_visit() {
        if (is_user_logged_in() && !is_admin()) {
            global $post;
            if ($post) {
                $this->activity_logger->log_page_visit($post->ID, $post->post_title, get_permalink($post->ID));
            }
        }
    }

    /**
     * Check if FluentCRM is active
     *
     * @return bool
     */
    private function is_fluentcrm_active() {
        return defined('FLUENTCRM') && FLUENTCRM;
    }

    /**
     * Check if LearnDash is active
     *
     * @return bool
     */
    private function is_learndash_active() {
        return defined('LEARNDASH_VERSION');
    }

    /**
     * Check if Fluent Forms is active
     *
     * @return bool
     */
    private function is_fluentforms_active() {
        return defined('FLUENTFORM') || defined('FLUENTFORM_VERSION');
    }
}

// Initialize the plugin
function user_activity_logger() {
    return User_Activity_Logger::get_instance();
}

// Start the plugin
user_activity_logger();
