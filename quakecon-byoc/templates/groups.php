<?php
/**
 * Template for displaying all groups
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="quakecon-byoc-groups">
    <h2>QuakeCon BYOC Groups</h2>
    
    <div class="groups-header">
        <div class="search-form">
            <input type="text" id="group-search" placeholder="Search groups...">
            <button id="search-button" class="search-btn">Search</button>
        </div>
        
        <?php if (!empty($create_group_url)): ?>
        <div class="create-group">
            <a href="<?php echo esc_url($create_group_url); ?>" class="button create-group-btn">Create New Group</a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="groups-list">
        <?php if (empty($groups)): ?>
        <p>No groups found. <a href="<?php echo esc_url($create_group_url); ?>">Create your own group</a> to get started!</p>
        <?php else: ?>
        <div class="groups-grid">
            <?php foreach ($groups as $group): ?>
            <div class="group-card" style="border-color: <?php echo esc_attr($group['group_color']); ?>">
                <h3 class="group-name"><?php echo esc_html($group['group_name']); ?></h3>
                <div class="group-meta">
                    <span class="member-count"><?php echo intval($group['member_count']); ?> member<?php echo intval($group['member_count']) !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="group-actions">
                    <a href="<?php echo esc_url($group_page_url . '?id=' . $group['id']); ?>" class="button view-group-btn">View Group</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#search-button').on('click', function() {
        searchGroups();
    });
    
    $('#group-search').on('keypress', function(e) {
        if (e.which === 13) {
            searchGroups();
            return false;
        }
    });
    
    function searchGroups() {
        const searchTerm = $('#group-search').val();
        
        $.ajax({
            url: quakecon_byoc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_groups',
                nonce: quakecon_byoc_ajax.nonce,
                search: searchTerm
            },
            beforeSend: function() {
                $('.groups-list').html('<p>Searching...</p>');
            },
            success: function(response) {
                if (response.success) {
                    const groups = response.data.groups;
                    const groupPageUrl = response.data.group_page_url;
                    
                    if (groups.length === 0) {
                        $('.groups-list').html('<p>No groups found matching your search.</p>');
                        return;
                    }
                    
                    let html = '<div class="groups-grid">';
                    
                    groups.forEach(function(group) {
                        html += `
                            <div class="group-card" style="border-color: ${group.group_color}">
                                <h3 class="group-name">${group.group_name}</h3>
                                <div class="group-meta">
                                    <span class="member-count">${group.member_count} member${group.member_count !== 1 ? 's' : ''}</span>
                                </div>
                                <div class="group-actions">
                                    <a href="${groupPageUrl}?id=${group.id}" class="button view-group-btn">View Group</a>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    $('.groups-list').html(html);
                } else {
                    $('.groups-list').html('<p>Error: ' + (response.data || 'Failed to search groups') + '</p>');
                }
            },
            error: function() {
                $('.groups-list').html('<p>Error: Could not connect to server. Please try again.</p>');
            }
        });
    }
});
</script>