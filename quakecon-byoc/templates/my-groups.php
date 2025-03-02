<?php
/**
 * Template for displaying user's groups
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="quakecon-byoc-my-groups">
    <h2>My QuakeCon BYOC Groups</h2>
    
    <div class="my-groups-header">
        <div class="header-actions">
            <?php if (!empty($create_group_url)): ?>
            <a href="<?php echo esc_url($create_group_url); ?>" class="button create-group-btn">Create New Group</a>
            <?php endif; ?>
            
            <?php if (!empty($groups_page_url)): ?>
            <a href="<?php echo esc_url($groups_page_url); ?>" class="button browse-groups-btn">Browse All Groups</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($pending_invites)): ?>
    <div class="pending-invites-section">
        <h3>Pending Group Invites</h3>
        <table class="invites-table">
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Seat</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_invites as $invite): ?>
                <tr data-invite-id="<?php echo esc_attr($invite['id']); ?>">
                    <td>
                        <a href="<?php echo esc_url($group_page_url . '?id=' . $invite['group_id']); ?>" style="color: <?php echo esc_attr($invite['group_color']); ?>">
                            <?php echo esc_html($invite['group_name']); ?>
                        </a>
                    </td>
                    <td>Section <?php echo esc_html($invite['section']); ?>, Seat <?php echo esc_html($invite['seat_number']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($invite['invite_date'])); ?></td>
                    <td>
                        <button class="cancel-invite-btn">Cancel Request</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="my-groups-section">
        <h3>My Groups</h3>
        
        <?php if (empty($user_groups)): ?>
        <p>You are not a member of any groups yet. <a href="<?php echo esc_url($groups_page_url); ?>">Browse all groups</a> to join one, or <a href="<?php echo esc_url($create_group_url); ?>">create your own group</a>.</p>
        <?php else: ?>
        <div class="my-groups-list">
            <?php 
            // First display pending groups
            $pending_groups = array_filter($user_groups, function($g) { return $g['status'] === 'pending'; });
            if (!empty($pending_groups)):
            ?>
            <div class="pending-groups">
                <h4>Pending Membership Requests</h4>
                <div class="groups-grid">
                    <?php foreach ($pending_groups as $group): ?>
                    <div class="group-card" style="border-color: <?php echo esc_attr($group['group_color']); ?>">
                        <h3 class="group-name"><?php echo esc_html($group['group_name']); ?></h3>
                        <div class="group-meta">
                            <span class="status-badge pending">Pending Approval</span>
                        </div>
                        <div class="group-actions">
                            <a href="<?php echo esc_url($group_page_url . '?id=' . $group['id']); ?>" class="button view-group-btn">View Group</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php 
            // Then display approved groups
            $approved_groups = array_filter($user_groups, function($g) { return $g['status'] === 'approved'; });
            if (!empty($approved_groups)):
            ?>
            <div class="approved-groups">
                <h4>My Active Groups</h4>
                <div class="groups-grid">
                    <?php foreach ($approved_groups as $group): ?>
                    <div class="group-card" style="border-color: <?php echo esc_attr($group['group_color']); ?>">
                        <h3 class="group-name"><?php echo esc_html($group['group_name']); ?></h3>
                        <div class="group-meta">
                            <?php if ($group['is_owner'] == 1): ?>
                            <span class="role-badge owner">Owner</span>
                            <?php elseif ($group['is_admin'] == 1): ?>
                            <span class="role-badge admin">Admin</span>
                            <?php else: ?>
                            <span class="role-badge member">Member</span>
                            <?php endif; ?>
                        </div>
                        <div class="group-actions">
                            <a href="<?php echo esc_url($group_page_url . '?id=' . $group['id']); ?>" class="button view-group-btn">View Group</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Cancel invite button
    $('.cancel-invite-btn').on('click', function() {
        if (!confirm('Are you sure you want to cancel this request?')) {
            return;
        }
        
        const inviteId = $(this).closest('tr').data('invite-id');
        
        $.ajax({
            url: quakecon_byoc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'handle_group_invite',
                nonce: quakecon_byoc_ajax.nonce,
                invite_id: inviteId,
                action: 'cancel'
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row
                    $(`tr[data-invite-id="${inviteId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        
                        // If no more rows, remove the section
                        if ($('.invites-table tbody tr').length === 0) {
                            $('.pending-invites-section').remove();
                        }
                    });
                } else {
                    alert(response.data || 'An error occurred.');
                }
            },
            error: function() {
                alert('Connection error. Please try again.');
            }
        });
    });
});
</script>