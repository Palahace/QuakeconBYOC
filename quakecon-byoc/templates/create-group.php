<?php
/**
 * Template for creating a new group
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure groups page URL is available
$groups_page_id = get_option('quakecon_byoc_groups_page', 0);
$groups_page_url = $groups_page_id ? get_permalink($groups_page_id) : home_url();
?>
<div class="quakecon-byoc-create-group">
    <h2>Create New QuakeCon BYOC Group</h2>
    
    <form id="create-group-form">
        <div class="form-group">
            <label for="group-name">Group Name *</label>
            <input type="text" id="group-name" name="group-name" required maxlength="255">
            <div class="help-text">Choose a unique name for your group (max 255 characters).</div>
        </div>
        
        <div class="form-group">
            <label for="group-description">Description</label>
            <textarea id="group-description" name="group-description" rows="4" maxlength="1000"></textarea>
            <div class="help-text">Describe your group (optional, max 1000 characters).</div>
        </div>
        
        <div class="form-group">
            <label for="group-color">Group Color</label>
            <input type="color" id="group-color" name="group-color" value="#FF9999">
            <div class="help-text">Choose a color for your group. This will be used to highlight your group's seats.</div>
        </div>
        
        <div class="form-message"></div>
        
        <div class="form-actions">
            <a href="<?php echo esc_url($groups_page_url); ?>" class="cancel-btn">Cancel</a>
            <button type="submit" class="submit-btn">Create Group</button>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#create-group-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const formMessage = $form.find('.form-message');
        const submitBtn = $form.find('.submit-btn');
        
        // Clear previous messages and disable submit button
        formMessage.hide().empty().removeClass('error success');
        submitBtn.prop('disabled', true).text('Creating...');
        
        const groupName = $('#group-name').val().trim();
        const groupDescription = $('#group-description').val().trim();
        const groupColor = $('#group-color').val() || '#FF9999';
        
        // Validate group name
        if (!groupName) {
            formMessage.text('Group name is required.').addClass('error').show();
            submitBtn.prop('disabled', false).text('Create Group');
            return;
        }
        
        // Check group name length
        if (groupName.length > 255) {
            formMessage.text('Group name must be 255 characters or less.').addClass('error').show();
            submitBtn.prop('disabled', false).text('Create Group');
            return;
        }
        
        $.ajax({
            url: quakecon_byoc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'create_group',
                nonce: quakecon_byoc_ajax.nonce,
                group_name: groupName,
                group_description: groupDescription,
                group_color: groupColor
            },
            success: function(response) {
                // Re-enable submit button
                submitBtn.prop('disabled', false).text('Create Group');
                
                if (response.success) {
                    // Show success message
                    formMessage
                        .text('Group created successfully! Redirecting...')
                        .addClass('success')
                        .removeClass('error')
                        .show();
                    
                    // Redirect to the new group page or groups list
                    setTimeout(function() {
                        // Use groups page URL with group ID if available, otherwise default groups page
                        const redirectUrl = response.data.group_url || 
                            (quakecon_byoc_ajax.groups_page_url + '?group_id=' + response.data.group_id);
                        window.location.href = redirectUrl;
                    }, 2000);
                } else {
                    // Show error message
                    formMessage
                        .text(response.data || 'An error occurred.')
                        .addClass('error')
                        .removeClass('success')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                // Re-enable submit button
                submitBtn.prop('disabled', false).text('Create Group');
                
                // Show connection error
                formMessage
                    .text('Connection error. Please try again.')
                    .addClass('error')
                    .removeClass('success')
                    .show();
                
                // Log detailed error for debugging
                console.error('AJAX Error:', status, error);
            }
        });
    });
});
</script>