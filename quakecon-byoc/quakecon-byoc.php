<?php
/**
 * Plugin Name: QuakeCon BYOC Seating Chart
 * Plugin URI: http://yourwebsite.com/
 * Description: A plugin that displays a seating chart for QuakeCon BYOC with the ability for users to claim seats and join groups.
 * Version: 1.0
 * Author: Your Name
 * Author URI: http://yourwebsite.com/
 * Text Domain: quakecon-byoc
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class QuakeCon_BYOC_Plugin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add shortcodes
        add_shortcode('quakecon_byoc_seating', array($this, 'seating_chart_shortcode'));
        add_shortcode('quakecon_byoc_my_seats', array($this, 'my_seats_shortcode'));
        add_shortcode('quakecon_byoc_groups', array($this, 'groups_shortcode'));
        add_shortcode('quakecon_byoc_group', array($this, 'group_detail_shortcode'));
        add_shortcode('quakecon_byoc_create_group', array($this, 'create_group_shortcode'));
        add_shortcode('quakecon_byoc_my_groups', array($this, 'my_groups_shortcode'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register AJAX handlers (only for logged-in users)
        add_action('wp_ajax_claim_seat', array($this, 'claim_seat'));
        add_action('wp_ajax_update_seat', array($this, 'update_seat'));
        add_action('wp_ajax_remove_seat', array($this, 'remove_seat'));
        
        // Group management AJAX handlers
        add_action('wp_ajax_create_group', array($this, 'create_group'));
        add_action('wp_ajax_join_group', array($this, 'join_group'));
        add_action('wp_ajax_leave_group', array($this, 'leave_group'));
        add_action('wp_ajax_search_groups', array($this, 'search_groups'));
        add_action('wp_ajax_manage_group_member', array($this, 'manage_group_member'));
        add_action('wp_ajax_update_group', array($this, 'update_group'));
        add_action('wp_ajax_handle_group_invite', array($this, 'handle_group_invite'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            section varchar(1) NOT NULL,
            seat_number int NOT NULL,
            user_alias varchar(255) NOT NULL,
            user_group varchar(255),
            user_email varchar(255),
            group_id mediumint(9) DEFAULT NULL,
            group_status enum('none', 'pending', 'approved') DEFAULT 'none',
            claimed_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY section_seat (section, seat_number)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add default options
        add_option('quakecon_byoc_seating_page', 0);
        add_option('quakecon_byoc_groups_page', 0);
        add_option('quakecon_byoc_group_page', 0);
        add_option('quakecon_byoc_create_group_page', 0);
        add_option('quakecon_byoc_my_groups_page', 0);
        
        // Set up group management tables
        $this->setup_group_tables();
    }
    
    /**
     * AJAX handler for updating a group
     */
    public function update_group() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to update a group');
        }

        // Get group ID and validate
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        if (empty($group_id)) {
            wp_send_json_error('Group ID is required');
        }

        // Sanitize and validate inputs
        $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
        $group_description = isset($_POST['group_description']) ? sanitize_textarea_field($_POST['group_description']) : '';
        $group_color = isset($_POST['group_color']) ? sanitize_hex_color($_POST['group_color']) : '#FF9999';

        // Validate group name
        if (empty($group_name)) {
            wp_send_json_error('Group name is required');
        }

        // Check group name length
        if (strlen($group_name) > 255) {
            wp_send_json_error('Group name must be 255 characters or less');
        }

        // Get current user details
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        global $wpdb;
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';

        // Verify user is admin or owner of the group
        $user_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s AND (is_admin = 1 OR is_owner = 1)",
            $group_id,
            $user_email
        ));

        if (!$user_membership) {
            wp_send_json_error('You do not have permission to edit this group');
        }

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        // Check if group name is already in use (excluding current group)
        $existing_group = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $groups_table WHERE group_name = %s AND id != %d",
            $group_name,
            $group_id
        ));

        if ($existing_group) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('A group with this name already exists');
        }

        // Update group
        $result = $wpdb->update(
            $groups_table,
            array(
                'group_name' => $group_name,
                'group_description' => $group_description,
                'group_color' => $group_color
            ),
            array('id' => $group_id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Failed to update group');
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        // Prepare response
        wp_send_json_success([
            'message' => 'Group updated successfully',
            'group_id' => $group_id,
            'group_name' => $group_name,
            'group_description' => $group_description,
            'group_color' => $group_color
        ]);
    }
    
    /**
     * AJAX handler for creating a group
     */
    public function create_group() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to create a group');
        }

        // Sanitize and validate inputs
        $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
        $group_description = isset($_POST['group_description']) ? sanitize_textarea_field($_POST['group_description']) : '';
        $group_color = isset($_POST['group_color']) ? sanitize_hex_color($_POST['group_color']) : '#FF9999';

        // Validate group name
        if (empty($group_name)) {
            wp_send_json_error('Group name is required');
        }

        // Check group name length
        if (strlen($group_name) > 255) {
            wp_send_json_error('Group name must be 255 characters or less');
        }

        // Get current user details
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_alias = $current_user->display_name;

        global $wpdb;
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        // Check if group name already exists
        $existing_group = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $groups_table WHERE group_name = %s",
            $group_name
        ));

        if ($existing_group) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('A group with this name already exists');
        }

        // Insert new group
        $result = $wpdb->insert(
            $groups_table,
            array(
                'group_name' => $group_name,
                'group_description' => $group_description,
                'group_color' => $group_color,
                'created_by' => $user_email
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Failed to create group');
        }

        // Get the new group ID
        $group_id = $wpdb->insert_id;

        // Add current user as group owner/admin
        $member_result = $wpdb->insert(
            $members_table,
            array(
                'group_id' => $group_id,
                'user_email' => $user_email,
                'user_alias' => $user_alias,
                'is_admin' => 1,
                'is_owner' => 1,
                'status' => 'approved'
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s')
        );

        if ($member_result === false) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Failed to add group owner');
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        // Get group page URL
        $group_page_id = get_option('quakecon_byoc_group_page', 0);
        $group_page_url = $group_page_id ? get_permalink($group_page_id) . '?id=' . $group_id : '';

        // Prepare response
        wp_send_json_success([
            'message' => 'Group created successfully',
            'group_id' => $group_id,
            'group_name' => $group_name,
            'group_url' => $group_page_url
        ]);
    }
    
    /**
     * Set up group management tables
     */
    public function setup_group_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Groups table
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $sql_groups = "CREATE TABLE $groups_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_name varchar(255) NOT NULL,
            group_description text,
            group_color varchar(7) DEFAULT '#FF9999',
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            created_by varchar(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_name (group_name)
        ) $charset_collate;";
        
        // Group memberships table
        $memberships_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        $sql_memberships = "CREATE TABLE $memberships_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_id mediumint(9) NOT NULL,
            user_email varchar(255) NOT NULL,
            user_alias varchar(255) NOT NULL,
            is_admin tinyint(1) DEFAULT 0,
            is_owner tinyint(1) DEFAULT 0,
            status enum('pending', 'approved', 'declined') DEFAULT 'pending',
            joined_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY group_user (group_id, user_email),
            KEY group_id (group_id),
            KEY user_email (user_email)
        ) $charset_collate;";
        
        // Group invites table
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        $sql_invites = "CREATE TABLE $invites_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_id mediumint(9) NOT NULL,
            seat_id mediumint(9) NOT NULL,
            user_email varchar(255) NOT NULL,
            invite_date datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('pending', 'approved', 'declined') DEFAULT 'pending',
            PRIMARY KEY  (id),
            UNIQUE KEY group_seat (group_id, seat_id),
            KEY group_id (group_id),
            KEY seat_id (seat_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_groups);
        dbDelta($sql_memberships);
        dbDelta($sql_invites);
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Do nothing for now
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('quakecon-byoc-style', plugin_dir_url(__FILE__) . 'css/quakecon-byoc.css', array(), '1.1');
        wp_enqueue_style('quakecon-byoc-groups-style', plugin_dir_url(__FILE__) . 'css/quakecon-byoc-groups.css', array(), '1.1');
        wp_enqueue_script('quakecon-byoc-script', plugin_dir_url(__FILE__) . 'js/quakecon-byoc.js', array('jquery'), '1.1', true);
        
        // Add AJAX URL and user status
        wp_localize_script('quakecon-byoc-script', 'quakecon_byoc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quakecon_byoc_nonce'),
            'is_user_logged_in' => is_user_logged_in(),
            'groups_page_url' => get_permalink(get_option('quakecon_byoc_groups_page', 0)),
            'seating_page_url' => get_permalink(get_option('quakecon_byoc_seating_page', 0))
        ));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'QuakeCon BYOC Settings',
            'QuakeCon BYOC',
            'manage_options',
            'quakecon-byoc',
            array($this, 'admin_page'),
            'dashicons-groups',
            30
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include(plugin_dir_path(__FILE__) . 'admin/admin-page.php');
    }
    
    /**
     * Shortcode for the seating chart
     */
    public function seating_chart_shortcode($atts) {
        $claimed_seats = $this->get_claimed_seats();
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/seating-chart.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode for displaying user's claimed seats
     */
    public function my_seats_shortcode($atts) {
        // If user is not logged in, show login message
        if (!is_user_logged_in()) {
            return '<div class="quakecon-byoc-login-notice">
                <p>You must be <a href="' . esc_url(wp_login_url(get_permalink())) . '">logged in</a> to view your claimed seats.</p>
            </div>';
        }
        
        // Get current user email
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Get user's claimed seats
        global $wpdb;
        $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_email = %s ORDER BY section, seat_number",
            $user_email
        ), ARRAY_A);
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/my-seats.php');
        return ob_get_clean();
    }
    
    /**
     * Get claimed seats from database
     */
    public function get_claimed_seats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        
        $results = $wpdb->get_results("
            SELECT s.section, s.seat_number, s.user_alias, s.user_group, s.group_id, s.group_status, g.group_color
            FROM $table_name s
            LEFT JOIN $groups_table g ON s.group_id = g.id
        ", ARRAY_A);
        
        $claimed_seats = [];
        
        foreach ($results as $result) {
            $claimed_seats[$result['section'] . '-' . $result['seat_number']] = [
                'alias' => $result['user_alias'],
                'group' => $result['user_group'],
                'group_id' => $result['group_id'],
                'group_status' => $result['group_status'],
                'group_color' => $result['group_color']
            ];
        }
        
        return $claimed_seats;
    }

    /**
     * Shortcode for displaying all groups
     */
    public function groups_shortcode($atts) {
        // If user is not logged in, show login message
        if (!is_user_logged_in()) {
            return '<div class="quakecon-byoc-login-notice">
                <p>You must be <a href="' . esc_url(wp_login_url(get_permalink())) . '">logged in</a> to view groups.</p>
            </div>';
        }
        
        // Get all groups from the database
        global $wpdb;
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        
        // Get all approved members for member count
        $groups = $wpdb->get_results("
            SELECT g.*, 
                   COUNT(DISTINCT m.id) as member_count
            FROM $groups_table g
            LEFT JOIN $members_table m ON g.id = m.group_id AND m.status = 'approved'
            GROUP BY g.id
            ORDER BY g.group_name ASC
        ", ARRAY_A);
        
        // Get group page URL
        $group_page_id = get_option('quakecon_byoc_group_page', 0);
        $group_page_url = $group_page_id ? get_permalink($group_page_id) : '';
        
        $create_group_page_id = get_option('quakecon_byoc_create_group_page', 0);
        $create_group_url = $create_group_page_id ? get_permalink($create_group_page_id) : '';
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/groups.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode for displaying a single group
     */
    public function group_detail_shortcode($atts) {
        // If user is not logged in, show login message
        if (!is_user_logged_in()) {
            return '<div class="quakecon-byoc-login-notice">
                <p>You must be <a href="' . esc_url(wp_login_url(get_permalink())) . '">logged in</a> to view group details.</p>
            </div>';
        }
        
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'quakecon_byoc_group');
        
        // Get group ID from URL if not provided in shortcode
        $group_id = $atts['id'];
        if (empty($group_id) && isset($_GET['id'])) {
            $group_id = intval($_GET['id']);
        }
        
        if (empty($group_id)) {
            return '<p>No group ID specified.</p>';
        }
        
        // Get group details
        global $wpdb;
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        $seats_table = $wpdb->prefix . 'quakecon_byoc_seats';
        
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $groups_table WHERE id = %d",
            $group_id
        ), ARRAY_A);
        
        if (!$group) {
            return '<p>Group not found.</p>';
        }
        
        // Get group members
        $members = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, COUNT(s.id) as seat_count 
             FROM $members_table m
             LEFT JOIN $seats_table s ON m.user_email = s.user_email AND s.group_id = m.group_id AND s.group_status = 'approved'
             WHERE m.group_id = %d
             GROUP BY m.id
             ORDER BY m.is_owner DESC, m.is_admin DESC, m.user_alias ASC",
            $group_id
        ), ARRAY_A);
        
        // Check if current user is member/admin/owner
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        $user_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s",
            $group_id,
            $user_email
        ), ARRAY_A);
        
        $is_member = !empty($user_membership) && $user_membership['status'] == 'approved';
        $is_admin = $is_member && $user_membership['is_admin'] == 1;
        $is_owner = $is_member && $user_membership['is_owner'] == 1;
        
        // Get pending invites if user is admin or owner
        $pending_invites = [];
        if ($is_admin || $is_owner) {
            $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
            
            $pending_invites = $wpdb->get_results($wpdb->prepare(
                "SELECT i.*, s.section, s.seat_number, s.user_alias
                 FROM $invites_table i
                 JOIN $seats_table s ON i.seat_id = s.id
                 WHERE i.group_id = %d AND i.status = 'pending'
                 ORDER BY i.invite_date ASC",
                $group_id
            ), ARRAY_A);
        }
        
        // Get user's seats for join request
        $user_seats = [];
        if (!$is_member) {
            $user_seats = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $seats_table WHERE user_email = %s AND (group_id IS NULL OR group_status = 'none')",
                $user_email
            ), ARRAY_A);
        }
        
        // Get the groups page URL
        $groups_page_id = get_option('quakecon_byoc_groups_page', 0);
        $groups_page_url = $groups_page_id ? get_permalink($groups_page_id) : '';
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/group-detail.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode for creating a group
     */
    public function create_group_shortcode($atts) {
        // If user is not logged in, show login message
        if (!is_user_logged_in()) {
            return '<div class="quakecon-byoc-login-notice">
                <p>You must be <a href="' . esc_url(wp_login_url(get_permalink())) . '">logged in</a> to create a group.</p>
            </div>';
        }
        
        // Get the groups page URL
        $groups_page_id = get_option('quakecon_byoc_groups_page', 0);
        $groups_page_url = $groups_page_id ? get_permalink($groups_page_id) : '';
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/create-group.php');
        return ob_get_clean();
    }
    
    /**
     * Shortcode for displaying user's groups
     */
    public function my_groups_shortcode($atts) {
        // If user is not logged in, show login message
        if (!is_user_logged_in()) {
            return '<div class="quakecon-byoc-login-notice">
                <p>You must be <a href="' . esc_url(wp_login_url(get_permalink())) . '">logged in</a> to view your groups.</p>
            </div>';
        }
        
        // Get current user's groups
        global $wpdb;
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        
        // Get groups where user is a member
        $user_groups = $wpdb->get_results($wpdb->prepare(
            "SELECT g.*, m.is_admin, m.is_owner, m.status
             FROM $groups_table g
             JOIN $members_table m ON g.id = m.group_id
             WHERE m.user_email = %s
             ORDER BY m.status ASC, g.group_name ASC",
            $user_email
        ), ARRAY_A);
        
        // Get pending group invites
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        $seats_table = $wpdb->prefix . 'quakecon_byoc_seats';
        
        $pending_invites = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, g.group_name, g.group_color, s.section, s.seat_number
             FROM $invites_table i
             JOIN $groups_table g ON i.group_id = g.id
             JOIN $seats_table s ON i.seat_id = s.id
             WHERE i.user_email = %s AND i.status = 'pending'
             ORDER BY i.invite_date ASC",
            $user_email
        ), ARRAY_A);
        
        // Get group page URL
        $group_page_id = get_option('quakecon_byoc_group_page', 0);
        $group_page_url = $group_page_id ? get_permalink($group_page_id) : '';
        
        $create_group_page_id = get_option('quakecon_byoc_create_group_page', 0);
        $create_group_url = $create_group_page_id ? get_permalink($create_group_page_id) : '';
        
        $groups_page_id = get_option('quakecon_byoc_groups_page', 0);
        $groups_page_url = $groups_page_id ? get_permalink($groups_page_id) : '';
        
        ob_start();
        include(plugin_dir_path(__FILE__) . 'templates/my-groups.php');
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for claiming seats
     */
    public function claim_seat() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
// Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to claim a seat');
        }
        
        // Get data
        $section = sanitize_text_field($_POST['section']);
        $seat_number = intval($_POST['seat_number']);
        $user_alias = sanitize_text_field($_POST['user_alias']);
        $user_group = sanitize_text_field($_POST['user_group']);
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        // Always use the current user's email
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Validate data
        if (empty($section) || empty($seat_number) || empty($user_alias)) {
            wp_send_json_error('Missing required fields');
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        
        // Check if seat is already claimed
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE section = %s AND seat_number = %d",
            $section,
            $seat_number
        ));
        
        if ($existing) {
            wp_send_json_error('Seat already claimed');
        }
        
        // Check if group exists (if specified)
        $group_status = 'none';
        if (!empty($group_id)) {
            $group = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $groups_table WHERE id = %d",
                $group_id
            ));
            
            if (!$group) {
                wp_send_json_error('Selected group does not exist');
            }
            
            // Check if user is a member of the group
            $membership = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s AND status = 'approved'",
                $group_id,
                $user_email
            ));
            
            if ($membership) {
                // User is already a member, no invite needed
                $group_status = 'approved';
            } else {
                // User needs to be invited or is pending
                $group_status = 'pending';
            }
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        // Insert new claim
        $result = $wpdb->insert(
            $table_name,
            array(
                'section' => $section,
                'seat_number' => $seat_number,
                'user_alias' => $user_alias,
                'user_group' => $user_group,
                'user_email' => $user_email,
                'group_id' => !empty($group_id) ? $group_id : null,
                'group_status' => $group_status
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Database error when claiming seat');
        }
        
        $seat_id = $wpdb->insert_id;
        
        // If group was specified and user is not a member, create invite
        if (!empty($group_id) && $group_status === 'pending') {
            $result = $wpdb->insert(
                $invites_table,
                array(
                    'group_id' => $group_id,
                    'seat_id' => $seat_id,
                    'user_email' => $user_email,
                    'status' => 'pending'
                ),
                array('%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Database error when creating group invite');
            }
        }
        
        $wpdb->query('COMMIT');
        
        wp_send_json_success([
            'message' => 'Seat claimed successfully' . (!empty($group_id) && $group_status === 'pending' ? '. A request has been sent to join the selected group.' : ''),
            'seat_id' => $section . '-' . $seat_number,
            'user_alias' => $user_alias,
            'user_group' => $user_group,
            'group_id' => !empty($group_id) ? $group_id : null,
            'group_status' => $group_status
        ]);
    }
    
    /**
     * AJAX handler for updating a seat
     */
    public function update_seat() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to update a seat');
        }
        
        // Get data
        $seat_id = intval($_POST['seat_id']);
        $user_alias = sanitize_text_field($_POST['user_alias']);
        $user_group = sanitize_text_field($_POST['user_group']);
        
        // Validate data
        if (empty($seat_id) || empty($user_alias)) {
            wp_send_json_error('Missing required fields');
        }
        
        // Verify ownership (only update seats that belong to the current user)
        global $wpdb;
        $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
        $current_user = wp_get_current_user();
        
        $seat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_email = %s",
            $seat_id,
            $current_user->user_email
        ));
        
        if (!$seat) {
            wp_send_json_error('You do not have permission to update this seat');
        }
        
        // Update seat
        $result = $wpdb->update(
            $table_name,
            array(
                'user_alias' => $user_alias,
                'user_group' => $user_group
            ),
            array('id' => $seat_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Database error');
        }
        
        wp_send_json_success([
            'message' => 'Seat updated successfully',
            'user_alias' => $user_alias,
            'user_group' => $user_group
        ]);
    }
    
    /**
     * AJAX handler for removing a seat
     */
    public function remove_seat() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to remove a seat');
        }
        
        // Get data
        $seat_id = intval($_POST['seat_id']);
        
        // Validate data
        if (empty($seat_id)) {
            wp_send_json_error('Missing required fields');
        }
        
        // Verify ownership (only remove seats that belong to the current user)
        global $wpdb;
        $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
        $current_user = wp_get_current_user();
        
        $seat = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_email = %s",
            $seat_id,
            $current_user->user_email
        ));
        
        if (!$seat) {
            wp_send_json_error('You do not have permission to remove this seat');
        }
        
        // Delete seat
        $result = $wpdb->delete(
            $table_name,
            array('id' => $seat_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Database error');
        }
        
        wp_send_json_success([
            'message' => 'Seat removed successfully'
        ]);
    }
    
    /**
     * AJAX handler for searching groups
     */
    public function search_groups() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }
        
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to search groups');
        }
        
        // Get search term
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        global $wpdb;
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        
        // Perform search
        if (!empty($search)) {
            $groups = $wpdb->get_results($wpdb->prepare(
                "SELECT g.*, 
                       COUNT(DISTINCT m.id) as member_count
                FROM $groups_table g
                LEFT JOIN $members_table m ON g.id = m.group_id AND m.status = 'approved'
                WHERE g.group_name LIKE %s
                GROUP BY g.id
                ORDER BY g.group_name ASC
                LIMIT 10",
                '%' . $wpdb->esc_like($search) . '%'
            ), ARRAY_A);
        } else {
            // If no search term, return first 10 groups
            $groups = $wpdb->get_results(
                "SELECT g.*, 
                       COUNT(DISTINCT m.id) as member_count
                FROM $groups_table g
                LEFT JOIN $members_table m ON g.id = m.group_id AND m.status = 'approved'
                GROUP BY g.id
                ORDER BY g.group_name ASC
                LIMIT 10"
            , ARRAY_A);
        }
        
        // Get group page URL
        $group_page_id = get_option('quakecon_byoc_group_page', 0);
        $group_page_url = $group_page_id ? get_permalink($group_page_id) : '';
        
        // Prepare response
        wp_send_json_success([
            'groups' => $groups,
            'group_page_url' => $group_page_url
        ]);
    }
    
    /**
     * AJAX handler for joining a group
     */
    public function join_group() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }
        
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to join a group');
        }
        
        // Get data
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $seat_id = isset($_POST['seat_id']) ? intval($_POST['seat_id']) : 0;
        
        if (empty($group_id)) {
            wp_send_json_error('Group ID is required');
        }
        
        // Get current user details
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_alias = $current_user->display_name;
        
        global $wpdb;
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        $seats_table = $wpdb->prefix . 'quakecon_byoc_seats';
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        
        // Verify group exists
        $group = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $groups_table WHERE id = %d",
            $group_id
        ));
        
        if (!$group) {
            wp_send_json_error('Group does not exist');
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        // Check if user is already a member or has a pending request
        $existing_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s",
            $group_id,
            $user_email
        ));
        
        if ($existing_membership) {
            $wpdb->query('ROLLBACK');
            if ($existing_membership['status'] === 'approved') {
                wp_send_json_error('You are already a member of this group');
            } else {
                wp_send_json_error('You already have a pending membership request for this group');
            }
        }
        
        // Insert member record (pending)
        $result = $wpdb->insert(
            $members_table,
            array(
                'group_id' => $group_id,
                'user_email' => $user_email,
                'user_alias' => $user_alias,
                'is_admin' => 0,
                'is_owner' => 0,
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s')
        );
        
        if ($result === false) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Failed to create membership request');
        }
        
        // If seat ID is provided, add it to the group
        if (!empty($seat_id)) {
            // Verify seat belongs to user
            $seat = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $seats_table WHERE id = %d AND user_email = %s",
                $seat_id,
                $user_email
            ));
            
            if (!$seat) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('You do not have permission to add this seat to the group');
            }
            
            // Update seat with group info
            $result = $wpdb->update(
                $seats_table,
                array(
                    'group_id' => $group_id,
                    'group_status' => 'pending'
                ),
                array('id' => $seat_id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to add seat to group');
            }
            
            // Create invite record for seat
            $result = $wpdb->insert(
                $invites_table,
                array(
                    'group_id' => $group_id,
                    'seat_id' => $seat_id,
                    'user_email' => $user_email,
                    'status' => 'pending'
                ),
                array('%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to create seat invite');
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Prepare response
        wp_send_json_success([
            'message' => 'Your membership request has been submitted. An admin will review it shortly.',
            'group_id' => $group_id,
            'seat_id' => $seat_id
        ]);
    }
    
    /**
     * AJAX handler for leaving a group
     */
    public function leave_group() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }
        
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to leave a group');
        }
        
        // Get data
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if (empty($group_id)) {
            wp_send_json_error('Group ID is required');
        }
        
        // Get current user details
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        $seats_table = $wpdb->prefix . 'quakecon_byoc_seats';
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        // Check if user is a member of the group
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s",
            $group_id,
            $user_email
        ));
        
        if (!$membership) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('You are not a member of this group');
        }
        
        // Check if user is the owner
        if ($membership['is_owner'] == 1) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Group owners cannot leave. Please transfer ownership first or delete the group.');
        }
        
        // Delete membership
        $result = $wpdb->delete(
            $members_table,
            array('id' => $membership['id']),
            array('%d')
        );
        
        if ($result === false) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Failed to leave group');
        }
        
        // Update user's seats in this group
        $wpdb->update(
            $seats_table,
            array(
                'group_id' => null,
                'group_status' => 'none'
            ),
            array(
                'user_email' => $user_email,
                'group_id' => $group_id
            ),
            array('%d', '%s'),
            array('%s', '%d')
        );
        
        // Delete any pending invites
        $wpdb->delete(
            $invites_table,
            array(
                'user_email' => $user_email,
                'group_id' => $group_id,
                'status' => 'pending'
            ),
            array('%s', '%d', '%s')
        );
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Prepare response
        wp_send_json_success([
            'message' => 'You have left the group successfully.',
            'group_id' => $group_id
        ]);
    }
    
    /**
     * AJAX handler for handling group invites (approve, decline, cancel)
     */
    public function handle_group_invite() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }
        
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to handle group invites');
        }
        
        // Get data
        $invite_id = isset($_POST['invite_id']) ? intval($_POST['invite_id']) : 0;
        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
        
        if (empty($invite_id) || empty($action)) {
            wp_send_json_error('Missing required fields');
        }
        
        if (!in_array($action, ['approve', 'decline', 'cancel'])) {
            wp_send_json_error('Invalid action');
        }
        
        global $wpdb;
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        $groups_table = $wpdb->prefix . 'quakecon_byoc_groups';
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        $seats_table = $wpdb->prefix . 'quakecon_byoc_seats';
        
        // Get current user
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Get the invite and related data
        $invite = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, g.group_name, s.section, s.seat_number, s.user_alias, s.user_email as seat_user
             FROM $invites_table i
             JOIN $groups_table g ON i.group_id = g.id
             JOIN $seats_table s ON i.seat_id = s.id
             WHERE i.id = %d",
            $invite_id
        ));
        
        if (!$invite) {
            wp_send_json_error('Invite not found');
        }
        
        // Verify permissions
        $is_seat_owner = ($invite->seat_user === $user_email);
        
        // For cancel, user must be seat owner
        if ($action === 'cancel' && !$is_seat_owner) {
            wp_send_json_error('You do not have permission to cancel this invite');
        }
        
        // For approve/decline, user must be admin or owner of group
        if (($action === 'approve' || $action === 'decline') && !$is_seat_owner) {
            $user_membership = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $members_table 
                 WHERE group_id = %d AND user_email = %s AND status = 'approved' AND (is_admin = 1 OR is_owner = 1)",
                $invite->group_id,
                $user_email
            ));
            
            if (!$user_membership) {
                wp_send_json_error('You do not have permission to manage invites for this group');
            }
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        if ($action === 'approve') {
            // Update invite status
            $result = $wpdb->update(
                $invites_table,
                array('status' => 'approved'),
                array('id' => $invite_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to approve invite');
            }
            
            // Update seat group status
            $result = $wpdb->update(
                $seats_table,
                array('group_status' => 'approved'),
                array('id' => $invite->seat_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to update seat status');
            }
            
            // Ensure user is a member of the group
            $member = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s",
                $invite->group_id,
                $invite->seat_user
            ));
            
            if (!$member) {
                // Add user as a member
                $result = $wpdb->insert(
                    $members_table,
                    array(
                        'group_id' => $invite->group_id,
                        'user_email' => $invite->seat_user,
                        'user_alias' => $invite->user_alias,
                        'is_admin' => 0,
                        'is_owner' => 0,
                        'status' => 'approved'
                    ),
                    array('%d', '%s', '%s', '%d', '%d', '%s')
                );
                
                if ($result === false) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Failed to add member to group');
                }
            } else if ($member->status !== 'approved') {
                // Update existing member to approved
                $result = $wpdb->update(
                    $members_table,
                    array('status' => 'approved'),
                    array('id' => $member->id),
                    array('%s'),
                    array('%d')
                );
                
                if ($result === false) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error('Failed to update member status');
                }
            }
        } else if ($action === 'decline' || $action === 'cancel') {
            // Update invite status
            $result = $wpdb->update(
                $invites_table,
                array('status' => 'declined'),
                array('id' => $invite_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to ' . $action . ' invite');
            }
            
            // Remove seat from group
            $result = $wpdb->update(
                $seats_table,
                array(
                    'group_id' => null,
                    'group_status' => 'none'
                ),
                array('id' => $invite->seat_id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to update seat status');
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Prepare response
        $message = 'Invitation ';
        if ($action === 'approve') {
            $message .= 'approved successfully';
        } else if ($action === 'decline') {
            $message .= 'declined successfully';
        } else {
            $message .= 'canceled successfully';
        }
        
        wp_send_json_success([
            'message' => $message,
            'invite_id' => $invite_id,
            'action' => $action
        ]);
    }
    
    /**
     * AJAX handler for managing group members
     */
    public function manage_group_member() {
        // Check if this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error('Invalid request');
        }
        
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quakecon_byoc_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to manage group members');
        }
        
        // Get data
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
        
        if (empty($group_id) || empty($member_id) || empty($action)) {
            wp_send_json_error('Missing required fields');
        }
        
        if (!in_array($action, ['approve', 'decline', 'promote', 'demote', 'transfer', 'remove'])) {
            wp_send_json_error('Invalid action');
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'quakecon_byoc_group_members';
        $seats_table = $wpdb->prefix . 'quakecon_byoc_seats';
        $invites_table = $wpdb->prefix . 'quakecon_byoc_group_invites';
        
        // Get current user
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Verify user is admin or owner of the group
        $user_membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE group_id = %d AND user_email = %s AND status = 'approved'",
            $group_id,
            $user_email
        ));
        
        if (!$user_membership || ($user_membership['is_admin'] != 1 && $user_membership['is_owner'] != 1)) {
            wp_send_json_error('You do not have permission to manage group members');
        }
        
        // Get the member being managed
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $members_table WHERE id = %d AND group_id = %d",
            $member_id,
            $group_id
        ));
        
        if (!$member) {
            wp_send_json_error('Member not found');
        }
        
        // Verify ownership for certain actions
        if (($action === 'transfer' || ($action === 'promote' || $action === 'demote' || $action === 'remove') && $member['is_owner'] == 1) && $user_membership['is_owner'] != 1) {
            wp_send_json_error('Only the group owner can perform this action');
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        if ($action === 'approve') {
            // Approve membership
            $result = $wpdb->update(
                $members_table,
                array('status' => 'approved'),
                array('id' => $member_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to approve member');
            }
            
            // Approve any pending seat invites for this user
            $wpdb->query($wpdb->prepare(
                "UPDATE $seats_table s 
                JOIN $invites_table i ON s.id = i.seat_id
                SET s.group_status = 'approved', i.status = 'approved'
                WHERE s.user_email = %s AND s.group_id = %d AND s.group_status = 'pending'",
                $member['user_email'],
                $group_id
            ));
        } elseif ($action === 'decline') {
            // Decline membership
            $result = $wpdb->update(
                $members_table,
                array('status' => 'declined'),
                array('id' => $member_id),
                array('%s'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to decline member');
            }
            
            // Decline any pending seat invites for this user
            $wpdb->query($wpdb->prepare(
                "UPDATE $seats_table s 
                JOIN $invites_table i ON s.id = i.seat_id
                SET s.group_id = NULL, s.group_status = 'none', i.status = 'declined'
                WHERE s.user_email = %s AND s.group_id = %d AND s.group_status = 'pending'",
                $member['user_email'],
                $group_id
            ));
        } elseif ($action === 'promote') {
            // Promote to admin
            $result = $wpdb->update(
                $members_table,
                array('is_admin' => 1),
                array('id' => $member_id),
                array('%d'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to promote member');
            }
        } elseif ($action === 'demote') {
            // Demote from admin
            $result = $wpdb->update(
                $members_table,
                array('is_admin' => 0),
                array('id' => $member_id),
                array('%d'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to demote member');
            }
        } elseif ($action === 'transfer') {
            // Transfer ownership
            
            // Update current owner
            $result = $wpdb->update(
                $members_table,
                array('is_owner' => 0),
                array('id' => $user_membership['id']),
                array('%d'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to transfer ownership (1)');
            }
            
            // Update new owner
            $result = $wpdb->update(
                $members_table,
                array(
                    'is_owner' => 1,
                    'is_admin' => 1
                ),
                array('id' => $member_id),
                array('%d', '%d'),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to transfer ownership (2)');
            }
        } elseif ($action === 'remove') {
            // Remove member
            $result = $wpdb->delete(
                $members_table,
                array('id' => $member_id),
                array('%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error('Failed to remove member');
            }
            
            // Update their seats
            $wpdb->update(
                $seats_table,
                array(
                    'group_id' => null,
                    'group_status' => 'none'
                ),
                array(
                    'user_email' => $member['user_email'],
                    'group_id' => $group_id
                ),
                array('%d', '%s'),
                array('%s', '%d')
            );
            
            // Remove any invites
            $wpdb->query($wpdb->prepare(
                "DELETE i FROM $invites_table i
                JOIN $seats_table s ON i.seat_id = s.id
                WHERE s.user_email = %s AND i.group_id = %d",
                $member['user_email'],
                $group_id
            ));
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Prepare response
        $message = '';
        switch ($action) {
            case 'approve':
                $message = 'Member approved successfully';
                break;
            case 'decline':
                $message = 'Member declined successfully';
                break;
            case 'promote':
                $message = 'Member promoted to admin successfully';
                break;
            case 'demote':
                $message = 'Member demoted from admin successfully';
                break;
            case 'transfer':
                $message = 'Group ownership transferred successfully';
                break;
            case 'remove':
                $message = 'Member removed successfully';
                break;
        }
        
        wp_send_json_success([
            'message' => $message,
            'member_id' => $member_id,
            'action' => $action
        ]);
    }
    
}

// Initialize the plugin
$quakecon_byoc_plugin = new QuakeCon_BYOC_Plugin();