<?php
/**
 * Admin functionality
 *
 * @package User_Activity_Logger
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class UAL_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('User Activity Logger', 'user-activity-logger'),
            __('User Activity', 'user-activity-logger'),
            'manage_options',
            'user-activity-logger',
            array($this, 'main_page'),
            'dashicons-backup',
            30
        );
        
        add_submenu_page(
            'user-activity-logger',
            __('Overview', 'user-activity-logger'),
            __('Overview', 'user-activity-logger'),
            'manage_options',
            'user-activity-logger',
            array($this, 'main_page')
        );
        
        add_submenu_page(
            'user-activity-logger',
            __('Settings', 'user-activity-logger'),
            __('Settings', 'user-activity-logger'),
            'manage_options',
            'user-activity-logger-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('ual_settings', 'ual_settings');
        
        add_settings_section(
            'ual_general_settings',
            __('General Settings', 'user-activity-logger'),
            array($this, 'general_settings_section_callback'),
            'ual_settings'
        );
        
        add_settings_field(
            'ual_data_retention',
            __('Data Retention Period', 'user-activity-logger'),
            array($this, 'data_retention_callback'),
            'ual_settings',
            'ual_general_settings'
        );
        
        add_settings_field(
            'ual_user_roles',
            __('User Roles to Track', 'user-activity-logger'),
            array($this, 'user_roles_callback'),
            'ual_settings',
            'ual_general_settings'
        );

        // Company info section
        add_settings_section(
            'ual_company_info',
            __('PDF Header Information', 'user-activity-logger'),
            array($this, 'company_info_section_callback'),
            'ual_settings'
        );

        $fields = array(
            'company_name' => __('Company Name', 'user-activity-logger'),
            'representative' => __('Representative', 'user-activity-logger'),
            'siret' => __('SIRET', 'user-activity-logger'),
            'phone' => __('Phone', 'user-activity-logger'),
            'email' => __('Email', 'user-activity-logger'),
            'address' => __('Address', 'user-activity-logger'),
            'training_name' => __('Training Name', 'user-activity-logger'),
        );
        foreach ($fields as $key => $label) {
            add_settings_field(
                'ual_company_' . $key,
                $label,
                array($this, 'company_field_callback'),
                'ual_settings',
                'ual_company_info',
                array('key' => $key)
            );
        }
    }
    
    /**
     * General settings section callback
     */
    public function general_settings_section_callback() {
        echo '<p>' . __('Configure general settings for User Activity Logger.', 'user-activity-logger') . '</p>';
    }
    
    /**
     * Data retention callback
     */
    public function data_retention_callback() {
        $settings = get_option('ual_settings', array());
        $retention = isset($settings['data_retention']) ? $settings['data_retention'] : 90;
        
        echo '<select name="ual_settings[data_retention]" id="ual_data_retention">';
        $options = array(
            30 => __('30 days', 'user-activity-logger'),
            60 => __('60 days', 'user-activity-logger'),
            90 => __('90 days', 'user-activity-logger'),
            180 => __('180 days', 'user-activity-logger'),
            365 => __('1 year', 'user-activity-logger'),
            0 => __('Forever', 'user-activity-logger'),
        );
        
        foreach ($options as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($retention, $value, false) . '>' . $label . '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">' . __('How long to keep activity logs before deleting them automatically.', 'user-activity-logger') . '</p>';
    }
    
    /**
     * User roles callback
     */
    public function user_roles_callback() {
        $settings = get_option('ual_settings', array());
        $tracked_roles = isset($settings['tracked_roles']) ? $settings['tracked_roles'] : array();
        
        $roles = get_editable_roles();
        
        foreach ($roles as $role_key => $role) {
            $checked = in_array($role_key, $tracked_roles) || empty($tracked_roles);
            echo '<label>';
            echo '<input type="checkbox" name="ual_settings[tracked_roles][]" value="' . $role_key . '" ' . checked($checked, true, false) . '>';
            echo ' ' . $role['name'];
            echo '</label><br>';
        }
        
        echo '<p class="description">' . __('Select which user roles to track activity for. If none selected, all roles will be tracked.', 'user-activity-logger') . '</p>';
    }

    /**
     * Company info section callback
     */
    public function company_info_section_callback() {
        echo '<p>' . __('Details used in exported PDFs.', 'user-activity-logger') . '</p>';
    }

    /**
     * Company field callback
     */
    public function company_field_callback($args) {
        $key = $args['key'];
        $settings = get_option('ual_settings', array());
        $company = isset($settings['company']) ? $settings['company'] : array();
        $value = isset($company[$key]) ? $company[$key] : '';
        echo '<input type="text" class="regular-text" name="ual_settings[company][' . esc_attr($key) . ']" value="' . esc_attr($value) . '" />';
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets($hook) {
        if ('toplevel_page_user-activity-logger' === $hook || 'user-activity_page_user-activity-logger-settings' === $hook) {
            wp_enqueue_style(
                'ual-admin',
                UAL_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                UAL_VERSION
            );
            
            wp_enqueue_script(
                'ual-admin',
                UAL_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                UAL_VERSION,
                true
            );
        }
    }
    
    /**
     * Main admin page
     */
    public function main_page() {
        // Check if we're viewing details for a specific user
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        if ($user_id) {
            $this->user_detail_page($user_id);
            return;
        }
        
        $db = new UAL_Database();
        
        // Get pagination parameters
        $limit = 20;
        $page = isset($_GET['page_num']) ? intval($_GET['page_num']) : 1;
        $offset = ($page - 1) * $limit;
        
        // Get user summary data
        $users = $db->get_user_summary($limit, $offset);
        $total_users = $db->count_users_with_sessions();
        $total_pages = ceil($total_users / $limit);
        
        ?>
        <div class="wrap">
            <h1><?php _e('User Activity Logger', 'user-activity-logger'); ?></h1>
            
            <div class="ual-overview-container">
                <h2><?php _e('User Activity Overview', 'user-activity-logger'); ?></h2>
                
                <?php if (empty($users)): ?>
                    <div class="ual-no-data"><?php _e('No user activity data found.', 'user-activity-logger'); ?></div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Username', 'user-activity-logger'); ?></th>
                                <th><?php _e('Display Name', 'user-activity-logger'); ?></th>
                                <th><?php _e('Email', 'user-activity-logger'); ?></th>
                                <th><?php _e('Last Login', 'user-activity-logger'); ?></th>
                                <th><?php _e('Last Session Duration', 'user-activity-logger'); ?></th>
                                <th><?php _e('Total Sessions', 'user-activity-logger'); ?></th>
                                <th><?php _e('Actions', 'user-activity-logger'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): 
                                $last_login = !empty($user->last_login) ? 
                                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($user->last_login)) : 
                                    __('Never', 'user-activity-logger');
                                
                                $duration = !empty($user->last_session_duration) ? 
                                    $this->format_duration($user->last_session_duration) : 
                                    __('N/A', 'user-activity-logger');
                            ?>
                                <tr>
                                    <td><?php echo esc_html($user->username); ?></td>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><?php echo esc_html($user->email); ?></td>
                                    <td><?php echo esc_html($last_login); ?></td>
                                    <td><?php echo esc_html($duration); ?></td>
                                    <td><?php echo intval($user->total_sessions); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg(array('user_id' => $user->user_id))); ?>" class="button">
                                            <?php _e('View Details', 'user-activity-logger'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(_n('%s user', '%s users', $total_users, 'user-activity-logger'), number_format_i18n($total_users)); ?>
                                </span>
                                <span class="pagination-links">
                                    <?php
                                    echo paginate_links(array(
                                        'base' => add_query_arg('page_num', '%#%'),
                                        'format' => '',
                                        'prev_text' => '&laquo;',
                                        'next_text' => '&raquo;',
                                        'total' => $total_pages,
                                        'current' => $page,
                                    ));
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * User detail page
     *
     * @param int $user_id User ID
     */
    public function user_detail_page($user_id) {
        $db = new UAL_Database();
        $activity_logger = user_activity_logger()->activity_logger;
        
        // Get user data
        $user = get_userdata($user_id);
        
        if (!$user) {
            echo '<div class="error"><p>' . __('User not found.', 'user-activity-logger') . '</p></div>';
            return;
        }
        
        // Get sessions
        $limit = 10;
        $page = isset($_GET['session_page']) ? intval($_GET['session_page']) : 1;
        $offset = ($page - 1) * $limit;
        
        $sessions = $db->get_user_sessions($user_id, $limit, $offset);
        
        // Count total sessions for pagination
        $total_sessions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ual_sessions WHERE user_id = %d",
            $user_id
        ));
        
        $total_pages = ceil($total_sessions / $limit);
        
        ?>
        <div class="wrap">
            <h1>
                <?php printf(__('User Activity for %s', 'user-activity-logger'), $user->display_name); ?>
                <a href="<?php echo esc_url(remove_query_arg('user_id')); ?>" class="page-title-action"><?php _e('Back to Overview', 'user-activity-logger'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=ual_export_pdf&user_id=' . $user_id)); ?>" class="page-title-action"><?php _e('Export PDF', 'user-activity-logger'); ?></a>
            </h1>
            
            <div class="ual-user-info">
                <div class="ual-user-details">
                    <p><strong><?php _e('Username:', 'user-activity-logger'); ?></strong> <?php echo esc_html($user->user_login); ?></p>
                    <p><strong><?php _e('Email:', 'user-activity-logger'); ?></strong> <?php echo esc_html($user->user_email); ?></p>
                    <p><strong><?php _e('Role:', 'user-activity-logger'); ?></strong> <?php echo esc_html(implode(', ', $user->roles)); ?></p>
                </div>
            </div>
            
            <div class="ual-sessions-container">
                <h2><?php _e('User Sessions', 'user-activity-logger'); ?></h2>
                
                <?php if (empty($sessions)): ?>
                    <div class="ual-no-data"><?php _e('No sessions found for this user.', 'user-activity-logger'); ?></div>
                <?php else: ?>
                    <?php foreach ($sessions as $session): 
                        $login_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->login_time));
                        $logout_time = $session->logout_time ? 
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->logout_time)) : 
                            __('Active Session', 'user-activity-logger');
                        
                        $duration = $session->session_duration ? 
                            $this->format_duration($session->session_duration) : 
                            __('Active', 'user-activity-logger');
                        
                        // Get session activities
                        $activities = $db->get_session_activities($session->id);
                    ?>
                        <div class="ual-session-box">
                            <div class="ual-session-header">
                                <div class="ual-session-title">
                                    <h3><?php printf(__('Session: %s', 'user-activity-logger'), $login_time); ?></h3>
                                    <div class="ual-session-meta">
                                        <span><strong><?php _e('IP:', 'user-activity-logger'); ?></strong> <?php echo esc_html($session->ip_address); ?></span>
                                        <span><strong><?php _e('Duration:', 'user-activity-logger'); ?></strong> <?php echo esc_html($duration); ?></span>
                                        <span><strong><?php _e('Ended:', 'user-activity-logger'); ?></strong> <?php echo esc_html($logout_time); ?></span>
                                    </div>
                                </div>
                                <div class="ual-session-toggle">
                                    <button type="button" class="button">
                                        <span class="ual-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                                        <span class="screen-reader-text"><?php _e('Toggle activities', 'user-activity-logger'); ?></span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="ual-session-activities" style="display: none;">
                                <?php if (empty($activities)): ?>
                                    <p class="ual-no-activities"><?php _e('No activities recorded in this session.', 'user-activity-logger'); ?></p>
                                <?php else: ?>
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Time', 'user-activity-logger'); ?></th>
                                                <th><?php _e('Activity', 'user-activity-logger'); ?></th>
                                                <th><?php _e('Details', 'user-activity-logger'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activities as $activity): 
                                                $formatted = $activity_logger->get_formatted_activity($activity);
                                            ?>
                                                <tr>
                                                    <td><?php echo esc_html($formatted['time_formatted']); ?></td>
                                                    <td>
                                                        <span class="dashicons <?php echo esc_attr($formatted['icon']); ?>"></span>
                                                        <?php echo esc_html($formatted['type_label']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo esc_html($formatted['summary']); ?>
                                                        <?php if (!empty($formatted['object_url'])): ?>
                                                            <a href="<?php echo esc_url($formatted['object_url']); ?>" target="_blank">
                                                                <span class="dashicons dashicons-external"></span>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num">
                                    <?php printf(_n('%s session', '%s sessions', $total_sessions, 'user-activity-logger'), number_format_i18n($total_sessions)); ?>
                                </span>
                                <span class="pagination-links">
                                    <?php
                                    echo paginate_links(array(
                                        'base' => add_query_arg('session_page', '%#%'),
                                        'format' => '',
                                        'prev_text' => '&laquo;',
                                        'next_text' => '&raquo;',
                                        'total' => $total_pages,
                                        'current' => $page,
                                    ));
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('.ual-session-toggle button').on('click', function() {
                    var $container = $(this).closest('.ual-session-box');
                    var $activities = $container.find('.ual-session-activities');
                    var $icon = $(this).find('.ual-toggle-icon');
                    
                    $activities.slideToggle();
                    $icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                });
            });
        </script>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('User Activity Logger Settings', 'user-activity-logger'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                    settings_fields('ual_settings');
                    do_settings_sections('ual_settings');
                    submit_button();
                ?>
            </form>
        </div>
        <?php
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