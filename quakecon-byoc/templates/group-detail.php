<?php
/**
 * Template for displaying a single group
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="quakecon-byoc-group-detail" data-group-id="<?php echo esc_attr($group_id); ?>">
    <div class="group-header" style="border-color: <?php echo esc_attr($group['group_color']); ?>">
        <div class="group-info">
            <h2><?php echo esc_html($group['group_name']); ?></h2>
            <?php if (!empty($group['group_description'])): ?>
            <div class="group-description">
                <?php echo wpautop(esc_html($group['group_description'])); ?>
            </div>
            <?php endif; ?>
            <div class="group-meta">
                <span class="created-by">Created by: <?php echo esc_html($group['created_by']); ?></span>
                <span class="created-date">Date: <?php echo date('F j, Y', strtotime($group['date_created'])); ?></span>
                <span class="member-count"><?php echo count(array_filter($members, function($m) { return $m['status'] === 'approved'; })); ?> members</span>
            </div>
        </div>
        
        <div class="group-actions">
            <?php if ($is_owner || $is_admin): ?>
            <button class="edit-group-btn">Edit Group</button>
            <?php endif; ?>
            
            <?php if (!$is_member && empty($user_membership)): ?>
            <button class="join-group-btn">Join Group</button>
            <?php elseif (!$is_member && $user_membership['status'] === 'pending'): ?>
            <span class="pending-badge">Membership Pending</span>
            <?php elseif ($is_member && !$is_owner): ?>
            <button class="leave-group-btn">Leave Group</button>
            <?php endif; ?>
            
            <a href="<?php echo esc_url($groups_page_url); ?>" class="back-to-groups">Back to Groups</a>
        </div>
    </div>
    
    <?php if ($is_admin || $is_owner): ?>
    <div class="admin-section">
        <h3>Administration</h3>
        
        <?php if (!empty($pending_invites)): ?>
        <div class="pending-invites">
            <h4>Pending Seat Requests</h4>
            <table class="invites-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Section</th>
                        <th>Seat</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_invites as $invite): ?>
                    <tr data-invite-id="<?php echo esc_attr($invite['id']); ?>">
                        <td><?php echo esc_html($invite['user_alias']); ?></td>
                        <td><?php echo esc_html($invite['section']); ?></td>
                        <td><?php echo esc_html($invite['seat_number']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($invite['invite_date'])); ?></td>
                        <td>
                            <button class="approve-invite-btn">Approve</button>
                            <button class="decline-invite-btn">Decline</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php 
        // Get pending membership requests
        $pending_members = array_filter($members, function($m) { return $m['status'] === 'pending'; });
        if (!empty($pending_members)): 
        ?>
        <div class="pending-members">
            <h4>Pending Membership Requests</h4>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_members as $member): ?>
                    <tr data-member-id="<?php echo esc_attr($member['id']); ?>">
                        <td><?php echo esc_html($member['user_alias']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($member['joined_date'])); ?></td>
                        <td>
                            <button class="approve-member-btn">Approve</button>
                            <button class="decline-member-btn">Decline</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="members-section">
        <h3>Members</h3>
        
        <?php
        // Get approved members
        $approved_members = array_filter($members, function($m) { return $m['status'] === 'approved'; });
        ?>
        
        <?php if (empty($approved_members)): ?>
        <p>This group has no members yet.</p>
        <?php else: ?>
        <table class="members-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Seats</th>
                    <th>Joined</th>
                    <?php if ($is_owner): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($approved_members as $member): ?>
                <tr data-member-id="<?php echo esc_attr($member['id']); ?>">
                    <td><?php echo esc_html($member['user_alias']); ?></td>
                    <td>
                        <?php if ($member['is_owner'] == 1): ?>
                        <span class="role-badge owner">Owner</span>
                        <?php elseif ($member['is_admin'] == 1): ?>
                        <span class="role-badge admin">Admin</span>
                        <?php else: ?>
                        <span class="role-badge member">Member</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo intval($member['seat_count']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($member['joined_date'])); ?></td>
                    <?php if ($is_owner): ?>
                    <td class="member-actions">
                        <?php if ($member['is_owner'] != 1): ?>
                            <?php if ($member['is_admin'] == 0): ?>
                            <button class="promote-member-btn">Make Admin</button>
                            <?php else: ?>
                            <button class="demote-member-btn">Remove Admin</button>
                            <?php endif; ?>
                            
                            <button class="transfer-ownership-btn">Transfer Ownership</button>
                            <button class="remove-member-btn">Remove</button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <?php if (!$is_member && empty($user_membership)): ?>
    <!-- Join Group Form -->
    <div class="join-form-overlay" style="display: none;">
        <div class="join-form">
            <h3>Join <?php echo esc_html($group['group_name']); ?></h3>
            
            <?php if (empty($user_seats)): ?>
            <p>You don't have any seats to add to this group. <a href="<?php echo esc_url(get_permalink(get_option('quakecon_byoc_seating_page'))); ?>">Claim a seat</a> first!</p>
            <div class="form-actions">
                <button type="button" class="cancel-join-btn">Cancel</button>
            </div>
            <?php else: ?>
            <form id="join-group-form">
                <div class="form-group">
                    <label for="seat-select">Add your seat to this group (optional):</label>
                    <select id="seat-select" name="seat-id">
                        <option value="">None</option>
                        <?php foreach ($user_seats as $seat): ?>
                        <option value="<?php echo esc_attr($seat['id']); ?>">
                            Section <?php echo esc_html($seat['section']); ?>, Seat <?php echo esc_html($seat['seat_number']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-message"></div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-join-btn">Cancel</button>
                    <button type="submit" class="submit-join-btn">Join Group</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($is_owner || $is_admin): ?>
    <!-- Edit Group Form -->
    <div class="edit-group-overlay" style="display: none;">
        <div class="edit-group-form">
            <h3>Edit Group</h3>
            <form id="edit-group-form">
                <div class="form-group">
                    <label for="group-name">Group Name:</label>
                    <input type="text" id="group-name" name="group-name" value="<?php echo esc_attr($group['group_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="group-description">Description:</label>
                    <textarea id="group-description" name="group-description" rows="4"><?php echo esc_textarea($group['group_description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="group-color">Group Color:</label>
                    <input type="color" id="group-color" name="group-color" value="<?php echo esc_attr($group['group_color']); ?>">
                </div>
                
                <div class="form-message"></div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-edit-btn">Cancel</button>
                    <button type="submit" class="update-group-btn">Update Group</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($is_member && !$is_owner): ?>
    <!-- Leave Group Confirmation -->
    <div class="leave-group-overlay" style="display: none;">
        <div class="leave-group-dialog">
            <h3>Confirm Leaving Group</h3>
            <p>Are you sure you want to leave this group? Any seats you have in this group will be removed from the group.</p>
            
            <div class="dialog-actions">
                <button class="cancel-leave-btn">Cancel</button>
                <button class="confirm-leave-btn">Leave Group</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script type="text/javascript">
(function($) {
    $(document).ready(function() {
        console.log("Group detail page script loaded");
        const groupId = $('.quakecon-byoc-group-detail').data('group-id');
        console.log("Group ID:", groupId);
        
        // Join group button
        $(document).on('click', '.join-group-btn', function(e) {
            e.preventDefault();
            console.log("Join group button clicked");
            $('.join-form-overlay').show();
        });
        
        // Cancel join button
        $(document).on('click', '.cancel-join-btn', function(e) {
            e.preventDefault();
            console.log("Cancel join button clicked");
            $('.join-form-overlay').hide();
        });
        
        // Close join form on overlay click
        $('.join-form-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.join-form-overlay').hide();
            }
        });
        
        // Join group form submission
        $('#join-group-form').on('submit', function(e) {
            e.preventDefault();
            console.log("Join group form submitted");
            
            const formMessage = $(this).find('.form-message');
            formMessage.hide();
            
            const seatId = $('#seat-select').val();
            
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'join_group',
                    nonce: quakecon_byoc_ajax.nonce,
                    group_id: groupId,
                    seat_id: seatId
                },
                success: function(response) {
                    if (response.success) {
                        formMessage.text(response.data.message).addClass('success').removeClass('error').show();
                        
                        // Reload after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        formMessage.text(response.data || 'An error occurred.').addClass('error').removeClass('success').show();
                    }
                },
                error: function() {
                    formMessage.text('Connection error. Please try again.').addClass('error').removeClass('success').show();
                }
            });
        });
        
        // Edit group button
        $(document).on('click', '.edit-group-btn', function(e) {
            e.preventDefault();
            console.log("Edit group button clicked");
            $('.edit-group-overlay').show();
        });
        
        // Cancel edit button
        $(document).on('click', '.cancel-edit-btn', function(e) {
            e.preventDefault();
            console.log("Cancel edit button clicked");
            $('.edit-group-overlay').hide();
        });
        
        // Close edit form on overlay click
        $('.edit-group-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.edit-group-overlay').hide();
            }
        });
        
        // Edit group form submission
        $('#edit-group-form').on('submit', function(e) {
            e.preventDefault();
            console.log("Edit group form submitted");
            
            const formMessage = $(this).find('.form-message');
            formMessage.hide();
            
            const groupName = $('#group-name').val();
            const groupDescription = $('#group-description').val();
            const groupColor = $('#group-color').val();
            
            if (!groupName) {
                formMessage.text('Group name is required.').addClass('error').removeClass('success').show();
                return;
            }
            
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_group',
                    nonce: quakecon_byoc_ajax.nonce,
                    group_id: groupId,
                    group_name: groupName,
                    group_description: groupDescription,
                    group_color: groupColor
                },
                success: function(response) {
                    if (response.success) {
                        formMessage.text(response.data.message).addClass('success').removeClass('error').show();
                        
                        // Reload after delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        formMessage.text(response.data || 'An error occurred.').addClass('error').removeClass('success').show();
                    }
                },
                error: function() {
                    formMessage.text('Connection error. Please try again.').addClass('error').removeClass('success').show();
                }
            });
        });
        
        // Leave group button
        $(document).on('click', '.leave-group-btn', function(e) {
            e.preventDefault();
            console.log("Leave group button clicked");
            $('.leave-group-overlay').show();
        });
        
        // Cancel leave button
        $(document).on('click', '.cancel-leave-btn', function(e) {
            e.preventDefault();
            console.log("Cancel leave button clicked");
            $('.leave-group-overlay').hide();
        });
        
        // Close leave dialog on overlay click
        $('.leave-group-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.leave-group-overlay').hide();
            }
        });
        
        // Confirm leave button
        $(document).on('click', '.confirm-leave-btn', function(e) {
            e.preventDefault();
            console.log("Confirm leave button clicked");
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'leave_group',
                    nonce: quakecon_byoc_ajax.nonce,
                    group_id: groupId
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        
                        // Redirect to groups page
                        window.location.href = quakecon_byoc_ajax.groups_page_url;
                    } else {
                        alert(response.data || 'An error occurred.');
                        $('.leave-group-overlay').hide();
                    }
                },
                error: function() {
                    alert('Connection error. Please try again.');
                    $('.leave-group-overlay').hide();
                }
            });
        });
        
        // Approve and decline invite buttons
        $(document).on('click', '.approve-invite-btn, .decline-invite-btn', function(e) {
            e.preventDefault();
            const actionType = $(this).hasClass('approve-invite-btn') ? 'approve' : 'decline';
            const inviteId = $(this).closest('tr').data('invite-id');
            
            console.log("Invite button clicked:", actionType, inviteId);
            
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'handle_group_invite',  // This is the WordPress AJAX action hook
                    nonce: quakecon_byoc_ajax.nonce,
                    invite_id: inviteId,
                    invite_action: actionType     // Use a different parameter name to avoid conflict
                },
                success: function(response) {
                    console.log("Invite AJAX response:", response);
                    if (response.success) {
                        // Remove the row
                        $(`tr[data-invite-id="${inviteId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // If no more rows, hide the section
                            if ($('.pending-invites tbody tr').length === 0) {
                                $('.pending-invites').hide();
                            }
                        });
                    } else {
                        alert(response.data || 'An error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Invite AJAX error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    alert('Connection error. Please try again.');
                }
            });
        });
        
        // Approve and decline member buttons
        $(document).on('click', '.approve-member-btn, .decline-member-btn', function(e) {
            e.preventDefault();
            const memberAction = $(this).hasClass('approve-member-btn') ? 'approve' : 'decline';
            const memberId = $(this).closest('tr').data('member-id');
            
            console.log("Member button clicked:", memberAction, memberId);
            
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'manage_group_member', // WordPress AJAX action hook
                    nonce: quakecon_byoc_ajax.nonce,
                    group_id: groupId,
                    member_id: memberId,
                    member_action: memberAction // Changed from 'action' to 'member_action'
                },
                success: function(response) {
                    console.log("Member AJAX response:", response);
                    if (response.success) {
                        // Remove the row
                        $(`tr[data-member-id="${memberId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            
                            // If no more rows, hide the section
                            if ($('.pending-members tbody tr').length === 0) {
                                $('.pending-members').hide();
                            }
                            
                            // If approved, we need to reload to show in members list
                            if (memberAction === 'approve') {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.data || 'An error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Member AJAX error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    alert('Connection error. Please try again.');
                }
            });
        });
        
        // Promote, demote, transfer ownership, and remove member buttons
        $(document).on('click', '.promote-member-btn, .demote-member-btn, .transfer-ownership-btn, .remove-member-btn', function(e) {
            e.preventDefault();
            let memberAction = '';
            if ($(this).hasClass('promote-member-btn')) memberAction = 'promote';
            else if ($(this).hasClass('demote-member-btn')) memberAction = 'demote';
            else if ($(this).hasClass('transfer-ownership-btn')) memberAction = 'transfer';
            else if ($(this).hasClass('remove-member-btn')) memberAction = 'remove';
            
            const memberId = $(this).closest('tr').data('member-id');
            
            console.log("Admin button clicked:", memberAction, memberId);
            
            // Confirm for critical actions
            if (memberAction === 'transfer' || memberAction === 'remove') {
                if (!confirm(`Are you sure you want to ${memberAction === 'transfer' ? 'transfer ownership to' : 'remove'} this member?`)) {
                    return;
                }
            }
            
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'manage_group_member', // WordPress AJAX action hook
                    nonce: quakecon_byoc_ajax.nonce,
                    group_id: groupId,
                    member_id: memberId,
                    member_action: memberAction // Changed from 'action' to 'member_action'
                },
                success: function(response) {
                    console.log("Admin AJAX response:", response);
                    if (response.success) {
                        // For simplicity, just reload the page
                        location.reload();
                    } else {
                        alert(response.data || 'An error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Admin AJAX error:", status, error);
                    console.log("Response text:", xhr.responseText);
                    alert('Connection error. Please try again.');
                }
            });
        });
    });
})(jQuery);
</script>