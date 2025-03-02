<?php
/**
 * Admin page for QuakeCon BYOC Seating Chart
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Process form submissions
if (isset($_POST['quakecon_byoc_action'])) {
    $action = $_POST['quakecon_byoc_action'];
    
    if ($action === 'reset_seats' && current_user_can('manage_options')) {
        // Verify nonce
        if (isset($_POST['quakecon_byoc_admin_nonce']) && wp_verify_nonce($_POST['quakecon_byoc_admin_nonce'], 'quakecon_byoc_admin')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
            
            // Truncate table
            $wpdb->query("TRUNCATE TABLE $table_name");
            
            echo '<div class="notice notice-success"><p>All seat claims have been reset.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    } elseif ($action === 'delete_seat' && current_user_can('manage_options')) {
        // Verify nonce
        if (isset($_POST['quakecon_byoc_admin_nonce']) && wp_verify_nonce($_POST['quakecon_byoc_admin_nonce'], 'quakecon_byoc_admin')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'quakecon_byoc_seats';
            
            $seat_id = intval($_POST['seat_id']);
            
            // Delete seat
            $wpdb->delete(
                $table_name,
                array('id' => $seat_id),
                array('%d')
            );
            
            echo '<div class="notice notice-success"><p>Seat claim has been deleted.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    } elseif ($action === 'save_settings' && current_user_can('manage_options')) {
        // Verify nonce
        if (isset($_POST['quakecon_byoc_admin_nonce']) && wp_verify_nonce($_POST['quakecon_byoc_admin_nonce'], 'quakecon_byoc_admin')) {
            // Save seating chart page ID
            update_option('quakecon_byoc_seating_page', intval($_POST['seating_page_id']));
            
            // Save groups page ID
            update_option('quakecon_byoc_groups_page', intval($_POST['groups_page_id']));
            
            // Save group detail page ID
            update_option('quakecon_byoc_group_page', intval($_POST['group_page_id']));
            
            // Save create group page ID
            update_option('quakecon_byoc_create_group_page', intval($_POST['create_group_page_id']));
            
            // Save my groups page ID
            update_option('quakecon_byoc_my_groups_page', intval($_POST['my_groups_page_id']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    }
}

// Get all claimed seats
global $wpdb;
$table_name = $wpdb->prefix . 'quakecon_byoc_seats';
$seats = $wpdb->get_results("SELECT * FROM $table_name ORDER BY section, seat_number", ARRAY_A);

// Get current settings
$seating_page_id = get_option('quakecon_byoc_seating_page', 0);
?>

<div class="wrap">
    <h1>QuakeCon BYOC Seating Chart</h1>
    
    <div class="card">
        <h2>Shortcodes</h2>
        <p>Use the following shortcodes to display the plugin features on your site:</p>
        <ul>
            <li><code>[quakecon_byoc_seating]</code> - Displays the full seating chart where users can claim seats</li>
            <li><code>[quakecon_byoc_my_seats]</code> - Displays a user's claimed seats with options to edit or remove them</li>
        </ul>
    </div>
    
    <h2>Settings</h2>
    <form method="post">
        <?php wp_nonce_field('quakecon_byoc_admin', 'quakecon_byoc_admin_nonce'); ?>
        <input type="hidden" name="quakecon_byoc_action" value="save_settings">
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="seating_page_id">Seating Chart Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'seating_page_id',
                        'id' => 'seating_page_id',
                        'echo' => 1,
                        'show_option_none' => '— Select —',
                        'option_none_value' => '0',
                        'selected' => $seating_page_id
                    ));
                    ?>
                    <p class="description">Select the page where you have added the <code>[quakecon_byoc_seating]</code> shortcode.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="groups_page_id">Groups List Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'groups_page_id',
                        'id' => 'groups_page_id',
                        'echo' => 1,
                        'show_option_none' => '— Select —',
                        'option_none_value' => '0',
                        'selected' => get_option('quakecon_byoc_groups_page', 0)
                    ));
                    ?>
                    <p class="description">Select the page where you have added the <code>[quakecon_byoc_groups]</code> shortcode.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="group_page_id">Group Detail Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'group_page_id',
                        'id' => 'group_page_id',
                        'echo' => 1,
                        'show_option_none' => '— Select —',
                        'option_none_value' => '0',
                        'selected' => get_option('quakecon_byoc_group_page', 0)
                    ));
                    ?>
                    <p class="description">Select the page where you have added the <code>[quakecon_byoc_group]</code> shortcode.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="create_group_page_id">Create Group Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'create_group_page_id',
                        'id' => 'create_group_page_id',
                        'echo' => 1,
                        'show_option_none' => '— Select —',
                        'option_none_value' => '0',
                        'selected' => get_option('quakecon_byoc_create_group_page', 0)
                    ));
                    ?>
                    <p class="description">Select the page where you have added the <code>[quakecon_byoc_create_group]</code> shortcode.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="my_groups_page_id">My Groups Page</label></th>
                <td>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'my_groups_page_id',
                        'id' => 'my_groups_page_id',
                        'echo' => 1,
                        'show_option_none' => '— Select —',
                        'option_none_value' => '0',
                        'selected' => get_option('quakecon_byoc_my_groups_page', 0)
                    ));
                    ?>
                    <p class="description">Select the page where you have added the <code>[quakecon_byoc_my_groups]</code> shortcode.</p>
                </td>
            </tr>
                    <?php
                    wp_dropdown_pages(array(
                        'name' => 'seating_page_id',
                        'id' => 'seating_page_id',
                        'echo' => 1,
                        'show_option_none' => '— Select —',
                        'option_none_value' => '0',
                        'selected' => $seating_page_id
                    ));
                    ?>
                    <p class="description">Select the page where you have added the <code>[quakecon_byoc_seating]</code> shortcode.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" class="button button-primary" value="Save Settings">
        </p>
    </form>
    
    <h2>Claimed Seats</h2>
    
    <?php if (empty($seats)): ?>
        <p>No seats have been claimed yet.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Seat Number</th>
                    <th>User Alias</th>
                    <th>Group</th>
                    <th>Email</th>
                    <th>Claimed Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($seats as $seat): ?>
                <tr>
                    <td><?php echo esc_html($seat['section']); ?></td>
                    <td><?php echo esc_html($seat['seat_number']); ?></td>
                    <td><?php echo esc_html($seat['user_alias']); ?></td>
                    <td><?php echo esc_html($seat['user_group']); ?></td>
                    <td><?php echo esc_html($seat['user_email']); ?></td>
                    <td><?php echo esc_html($seat['claimed_time']); ?></td>
                    <td>
                        <form method="post" style="display: inline-block;">
                            <?php wp_nonce_field('quakecon_byoc_admin', 'quakecon_byoc_admin_nonce'); ?>
                            <input type="hidden" name="quakecon_byoc_action" value="delete_seat">
                            <input type="hidden" name="seat_id" value="<?php echo esc_attr($seat['id']); ?>">
                            <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this seat claim?');">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h2>Reset All Seats</h2>
    <p>This action will remove all claimed seats and cannot be undone.</p>
    <form method="post">
        <?php wp_nonce_field('quakecon_byoc_admin', 'quakecon_byoc_admin_nonce'); ?>
        <input type="hidden" name="quakecon_byoc_action" value="reset_seats">
        <button type="submit" class="button button-primary" onclick="return confirm('Are you sure you want to reset all seat claims? This cannot be undone.');">Reset All Seats</button>
    </form>
</div>