/**
 * QuakeCon BYOC Seating Chart JS
 */
(function($) {
    'use strict';

    // Variables
    let isDragging = false;
    let startX, startY;
    const zoomContainer = $('.zoom-container');
    const zoomableContent = $('.zoomable-content');
    const zoomPercentageDisplay = $('#zoom-percentage');
    let currentSection = '';
    let currentSeatNumber = 0;
    
    // Initialize
    function init() {
        // Prevent text selection on seating chart elements
        $('.seating-chart, .seat, .section-label, .seat-tooltip').on('selectstart dragstart', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable browser's default selection behavior for the entire container
        $('.quakecon-byoc-container').on('mousedown', function(e) {
            if ($(e.target).is('input, textarea')) {
                return true; // Allow selection in form fields
            }
            return false; // Prevent in all other elements
        });
        
        setupZoomAndPan();
        setupSeatClicking();
        setupClaimForm();
        setupGroupCreation();
    }
    
    // Setup zoom and pan functionality
    function setupZoomAndPan() {
        // Pan functionality
        zoomContainer.on('mousedown', function(e) {
            isDragging = true;
            startX = e.pageX - zoomContainer.offset().left;
            startY = e.pageY - zoomContainer.offset().top;
            zoomContainer.addClass('dragging');
        });

        $(document).on('mouseup', function() {
            isDragging = false;
            zoomContainer.removeClass('dragging');
        });

        zoomContainer.on('mousemove', function(e) {
            if (!isDragging) return;
            e.preventDefault();
            const x = e.pageX - zoomContainer.offset().left;
            const y = e.pageY - zoomContainer.offset().top;
            const walkX = (x - startX);
            const walkY = (y - startY);
            zoomContainer.scrollLeft(zoomContainer.scrollLeft() - walkX);
            zoomContainer.scrollTop(zoomContainer.scrollTop() - walkY);
            startX = x;
            startY = y;
        });

        // Zoom functionality
        zoomContainer.on('wheel', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                let zoomLevel = parseFloat(zoomableContent.css('transform').split('(')[1]);
                const oldZoom = zoomLevel;
                zoomLevel += e.originalEvent.deltaY * -0.0005;
                zoomLevel = Math.min(Math.max(0.1, zoomLevel), 1);

                const rect = zoomableContent[0].getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;

                const deltaZoom = zoomLevel / oldZoom;

                zoomContainer.scrollLeft((zoomContainer.scrollLeft() + mouseX) * deltaZoom - mouseX);
                zoomContainer.scrollTop((zoomContainer.scrollTop() + mouseY) * deltaZoom - mouseY);

                zoomableContent.css('transform', `scale(${zoomLevel})`);

                // Update zoom percentage display
                const zoomPercentage = Math.round(zoomLevel * 100);
                zoomPercentageDisplay.text(`Zoom: ${zoomPercentage}%`);
            }
        });

        // Initial Zoom
        const initialZoom = 0.3;
        zoomableContent.css({
            'transform': `scale(${initialZoom})`,
            'width': '4000px'
        });
        zoomPercentageDisplay.text(`Zoom: ${Math.round(initialZoom * 100)}%`);
    }
    
    // Setup seat clicking
    function setupSeatClicking() {
        $(document).on('click', '.seat:not(.claimed)', function() {
            // Check if user is logged in
            if (!quakecon_byoc_ajax.is_user_logged_in) {
                alert('You must be logged in to claim a seat. Please login or register.');
                return;
            }
            
            currentSection = $(this).data('section');
            currentSeatNumber = $(this).data('seat');
            
            // Update form
            $('#claim-seat-label').text(`Section ${currentSection}, Seat ${currentSeatNumber}`);
            
            // Show form
            $('.claim-form-overlay').show();
        });
    }
    
    // Setup claim form
    function setupClaimForm() {
        // Group search
        let searchTimeout;
        $('#user-group').on('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val();
            
            // Clear group ID if search field is emptied
            if (!searchTerm) {
                $('#group-id').val('');
                $('#group-search-results').empty().hide();
                return;
            }
            
            // Perform search after typing stops for 500ms
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: quakecon_byoc_ajax.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'search_groups',
                        nonce: quakecon_byoc_ajax.nonce,
                        search: searchTerm.trim()
                    },
                    success: function(response) {
                        if (response.success && response.data.groups && response.data.groups.length > 0) {
                            let html = '';
                            response.data.groups.forEach(function(group) {
                                html += `<div class="group-result" data-id="${group.id}" data-name="${group.group_name}" style="border-left: 4px solid ${group.group_color};">
                                    <span class="group-result-name">${group.group_name}</span>
                                    <span class="group-result-count">${group.member_count} member${group.member_count !== 1 ? 's' : ''}</span>
                                </div>`;
                            });
                            $('#group-search-results').html(html).show();
                        } else {
                            $('#group-search-results').html('<div class="no-results">No groups found</div>').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Group Search Error:', error, xhr.responseText);
                        $('#group-search-results').html('<div class="no-results">Error retrieving groups.</div>').show();
                    }
                });
            }, 500);
        });
        
        // Group selection
        $(document).on('click', '.group-result', function() {
            const groupId = $(this).data('id');
            const groupName = $(this).data('name');
            
            $('#user-group').val(groupName);
            $('#group-id').val(groupId);
            $('#group-search-results').hide();
        });
        
        // Hide results when clicking elsewhere
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.group-select-container').length) {
                $('#group-search-results').hide();
            }
        });
        
        // Cancel button
        $('.cancel-btn').on('click', function() {
            $('.claim-form-overlay').hide();
            $('.claim-form')[0].reset();
            $('.form-message').hide();
        });
        
        // Close on overlay click
        $('.claim-form-overlay').on('click', function(e) {
            if (e.target === this) {
                $('.claim-form-overlay').hide();
                $('.claim-form')[0].reset();
                $('.form-message').hide();
            }
        });
        
        // Form submission
        $('.claim-form').on('submit', function(e) {
            e.preventDefault();
            
            const formMessage = $('.form-message');
            formMessage.hide();
            
            // Get form data
            const userAlias = $('#user-alias').val();
            const userGroup = $('#user-group').val();
            const userEmail = $('#user-email').val();
            
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
                    action: 'claim_seat',
                    nonce: quakecon_byoc_ajax.nonce,
                    section: currentSection,
                    seat_number: currentSeatNumber,
                    user_alias: userAlias,
                    user_group: userGroup,
                    user_email: userEmail
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        const seatElement = $(`.seat[data-section="${currentSection}"][data-seat="${currentSeatNumber}"]`);
                        seatElement.addClass('claimed');
                        
                        // Add tooltip
                        const tooltipHtml = `
                            <div class="seat-tooltip">
                                <strong>${userAlias}</strong>
                                ${userGroup ? `<br>Group: ${userGroup}` : ''}
                            </div>
                        `;
                        seatElement.append(tooltipHtml);
                        
                        // Show success message
                        formMessage.text('Seat claimed successfully!').addClass('success').removeClass('error').show();
                        
                        // Close form after delay
                        setTimeout(function() {
                            $('.claim-form-overlay').hide();
                            $('.claim-form')[0].reset();
                            formMessage.hide();
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
    }
    
    // Setup group creation form
    function setupGroupCreation() {
        $('#create-group-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formMessage = $form.find('.form-message');
            const submitBtn = $form.find('button[type="submit"]');
            
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
            
            // Log debugging information
            console.log('Submitting group creation:', {
                action: 'create_group',
                groupName: groupName,
                groupDescription: groupDescription,
                groupColor: groupColor,
                ajaxUrl: quakecon_byoc_ajax.ajax_url,
                nonce: quakecon_byoc_ajax.nonce
            });
            
            // Comprehensive AJAX call with detailed error handling
            $.ajax({
                url: quakecon_byoc_ajax.ajax_url,
                type: 'POST',
                dataType: 'json', // Explicitly expect JSON
                data: {
                    action: 'create_group',
                    nonce: quakecon_byoc_ajax.nonce,
                    group_name: groupName,
                    group_description: groupDescription,
                    group_color: groupColor
                },
                beforeSend: function(xhr) {
                    // Additional logging before send
                    console.log('AJAX Request - Before Send:', {
                        url: quakecon_byoc_ajax.ajax_url,
                        data: xhr.data
                    });
                },
                success: function(response) {
                    // Re-enable submit button
                    submitBtn.prop('disabled', false).text('Create Group');
                    
                    // Log full response for debugging
                    console.log('AJAX Success Response:', response);
                    
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
                            const redirectUrl = (response.data && response.data.group_url) || 
                                (quakecon_byoc_ajax.groups_page_url + '?group_id=' + (response.data ? response.data.group_id : ''));
                            window.location.href = redirectUrl;
                        }, 2000);
                    } else {
                        // Show error message
                        const errorMessage = response.data || 'An unknown error occurred.';
                        formMessage
                            .text(errorMessage)
                            .addClass('error')
                            .removeClass('success')
                            .show();
                        
                        console.error('Group Creation Error:', errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    // Re-enable submit button
                    submitBtn.prop('disabled', false).text('Create Group');
                    
                    // Comprehensive error logging
                    console.error('AJAX Error Details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        readyState: xhr.readyState,
                        responseJSON: xhr.responseJSON
                    });
                    
                    // Try to parse error message
                    let errorMessage = 'Connection error. Please try again.';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMessage = response.data || errorMessage;
                    } catch (e) {
                        // If parsing fails, use default message
                    }
                    
                    // Show connection error
                    formMessage
                        .text(errorMessage)
                        .addClass('error')
                        .removeClass('success')
                        .show();
                },
                complete: function(xhr, status) {
                    // Log complete status
                    console.log('AJAX Request Complete:', {
                        status: status,
                        responseText: xhr.responseText
                    });
                }
            });
        });
    }
    
    // Initialize on document ready
    $(document).ready(init);
    
})(jQuery);