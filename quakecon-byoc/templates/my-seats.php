<?php
/**
 * Template for displaying user's claimed seats
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="quakecon-byoc-my-seats">
    <h2>My QuakeCon BYOC Seats</h2>
    
    <?php if (empty($results)): ?>
    
    <div class="no-seats-message">
        <p>You haven't claimed any seats yet. 
        <?php $seating_page_url = get_permalink(get_option('quakecon_byoc_seating_page', 0)); ?>
        <?php if ($seating_page_url && $seating_page_url !== ''): ?>
            <a href="https://qcbyoc.com/">View the seating chart</a> to claim a seat.
        <?php else: ?>
            Contact the site administrator to claim a seat.
        <?php endif; ?>
        </p>
    </div>
    
    <?php else: ?>
    
    <div class="my-seats-list">
        <p>You have claimed the following seats:</p>
        
        <table class="my-seats-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Seat</th>
                    <th>Alias</th>
                    <th>Group</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $seat): ?>
                <tr data-seat-id="<?php echo esc_attr($seat['id']); ?>">
                    <td><?php echo esc_html($seat['section']); ?></td>
                    <td><?php echo esc_html($seat['seat_number']); ?></td>
                    <td class="seat-alias"><?php echo esc_html($seat['user_alias']); ?></td>
                    <td class="seat-group"><?php echo esc_html($seat['user_group']); ?></td>
                    <td class="seat-actions">
                        <button class="edit-seat-btn">Edit</button>
                        <button class="remove-seat-btn">Remove</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Edit Seat Form Overlay -->
    <div class="edit-form-overlay">
        <div class="edit-form">
            <h3>Edit Seat Information</h3>
            <form id="edit-seat-form">
                <input type="hidden" id="edit-seat-id" name="edit-seat-id" value="">
                
                <div class="form-group">
                    <label for="edit-user-alias">Your Alias/Gamertag *</label>
                    <input type="text" id="edit-user-alias" name="edit-user-alias" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-user-group">Group/Clan (Optional)</label>
                    <input type="text" id="edit-user-group" name="edit-user-group">
                </div>
                
                <div class="form-message"></div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-edit-btn">Cancel</button>
                    <button type="submit" class="update-seat-btn">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Remove Seat Confirmation Dialog -->
    <div class="remove-confirm-overlay">
        <div class="remove-confirm-dialog">
            <h3>Confirm Removal</h3>
            <p>Are you sure you want to remove your claim on this seat?</p>
            
            <div class="dialog-actions">
                <button class="cancel-remove-btn">Cancel</button>
                <button class="confirm-remove-btn">Remove Seat</button>
            </div>
            
            <input type="hidden" id="remove-seat-id" value="">
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Edit seat button
    $('.edit-seat-btn').on('click', function() {
        const row = $(this).closest('tr');
        const seatId = row.data('seat-id');
        const alias = row.find('.seat-alias').text();
        const group = row.find('.seat-group').text();
        
        // Fill the form
        $('#edit-seat-id').val(seatId);
        $('#edit-user-alias').val(alias);
        $('#edit-user-group').val(group);
        
        // Show the form
        $('.edit-form-overlay').show();
    });
    
    // Cancel edit button
    $('.cancel-edit-btn').on('click', function() {
        $('.edit-form-overlay').hide();
    });
    
    // Close edit form on overlay click
    $('.edit-form-overlay').on('click', function(e) {
        if (e.target === this) {
            $('.edit-form-overlay').hide();
        }
    });
    
    // Edit form submission
    $('#edit-seat-form').on('submit', function(e) {
        e.preventDefault();
        
        const formMessage = $(this).find('.form-message');
        formMessage.hide();
        
        const seatId = $('#edit-seat-id').val();
        const userAlias = $('#edit-user-alias').val();
        const userGroup = $('#edit-user-group').val();
        
        // Validate
        if (!userAlias) {
            formMessage.text('Please enter your alias.').addClass('error').removeClass('success').show();
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            url: quakecon_byoc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_seat',
                nonce: quakecon_byoc_ajax.nonce,
                seat_id: seatId,
                user_alias: userAlias,
                user_group: userGroup
            },
            success: function(response) {
                if (response.success) {
                    // Update the table
                    const row = $(`tr[data-seat-id="${seatId}"]`);
                    row.find('.seat-alias').text(userAlias);
                    row.find('.seat-group').text(userGroup);
                    
                    // Show success message
                    formMessage.text('Seat updated successfully!').addClass('success').removeClass('error').show();
                    
                    // Close form after delay
                    setTimeout(function() {
                        $('.edit-form-overlay').hide();
                    }, 1500);
                } else {
                    formMessage.text(response.data || 'An error occurred.').addClass('error').removeClass('success').show();
                }
            },
            error: function() {
                formMessage.text('Connection error. Please try again.').addClass('error').removeClass('success').show();
            }
        });
    });
    
    // Remove seat button
    $('.remove-seat-btn').on('click', function() {
        const row = $(this).closest('tr');
        const seatId = row.data('seat-id');
        
        // Set the seat ID in the confirmation dialog
        $('#remove-seat-id').val(seatId);
        
        // Show the confirmation dialog
        $('.remove-confirm-overlay').show();
    });
    
    // Cancel remove button
    $('.cancel-remove-btn').on('click', function() {
        $('.remove-confirm-overlay').hide();
    });
    
    // Close remove confirmation on overlay click
    $('.remove-confirm-overlay').on('click', function(e) {
        if (e.target === this) {
            $('.remove-confirm-overlay').hide();
        }
    });
    
    // Confirm remove button
    $('.confirm-remove-btn').on('click', function() {
        const seatId = $('#remove-seat-id').val();
        
        // Submit via AJAX
        $.ajax({
            url: quakecon_byoc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'remove_seat',
                nonce: quakecon_byoc_ajax.nonce,
                seat_id: seatId
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row from the table
                    $(`tr[data-seat-id="${seatId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        
                        // If no more rows, show "no seats" message
                        if ($('.my-seats-table tbody tr').length === 0) {
                            location.reload(); // Simplest way to show the "no seats" message
                        }
                    });
                    
                    // Close the confirmation dialog
                    $('.remove-confirm-overlay').hide();
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