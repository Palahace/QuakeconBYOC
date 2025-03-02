<?php
/**
 * Template for the QuakeCon BYOC Seating Chart
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="quakecon-byoc-container" onselectstart="return false;">
    <h1>QuakeCon BYOC Seating Chart</h1>
    
    <?php if (!is_user_logged_in()): ?>
    <div class="login-notice">
        <p>You must be <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">logged in</a> to claim a seat. 
        Don't have an account? <a href="<?php echo esc_url(wp_registration_url()); ?>">Register here</a>.</p>
    </div>
    <?php endif; ?>
    <div class="zoom-container">
        <div class="zoomable-content">
            <div class="seating-chart">
                <?php
                $sections = str_split("ABCDEFGHIJKLMNOPQRSTUVWXYZ");
                
                foreach ($sections as $letter) {
                    ?>
                    <div class="section">
                        <div class="seats">
                            <?php
                            // Determine seat counts based on section
                            if ($letter === 'A' || $letter === 'B') {
                                $bottomCount = 40;
                                $topCount = 44;
                                $bottomStart = 1;
                                $topStart = 41;
                                $bottomHalf = 20;
                                $topHalf = 22;
                            } else {
                                $bottomCount = 56;
                                $topCount = 64;
                                $bottomStart = 1;
                                $topStart = 57;
                                $bottomHalf = 28;
                                $topHalf = 32;
                            }
                            
                            // Top Seats (Reversed Order)
                            for ($i = $topHalf - 1; $i >= 0; $i--) {
                                ?>
                                <div class="row">
                                    <?php
                                    $seat1 = $topStart + $i;
                                    $seat2 = $topStart + $topHalf + $i;
                                    $seat1_id = $letter . '-' . $seat1;
                                    $seat2_id = $letter . '-' . $seat2;
                                    $seat1_claimed = isset($claimed_seats[$seat1_id]);
                                    $seat2_claimed = isset($claimed_seats[$seat2_id]);
                                    
                                    // Get group colors if seats are in a group
                                    $seat1_style = '';
                                    $seat2_style = '';
                                    
                                    if ($seat1_claimed && isset($claimed_seats[$seat1_id]['group_color']) && $claimed_seats[$seat1_id]['group_status'] === 'approved') {
                                        $seat1_style = 'background-color: ' . esc_attr($claimed_seats[$seat1_id]['group_color']) . ';';
                                    }
                                    
                                    if ($seat2_claimed && isset($claimed_seats[$seat2_id]['group_color']) && $claimed_seats[$seat2_id]['group_status'] === 'approved') {
                                        $seat2_style = 'background-color: ' . esc_attr($claimed_seats[$seat2_id]['group_color']) . ';';
                                    }
                                    ?>
                                    <div class="seat <?php echo $seat1_claimed ? 'claimed' : ''; ?>" 
                                         data-section="<?php echo $letter; ?>" 
                                         data-seat="<?php echo $seat1; ?>"
                                         <?php if ($seat1_style): ?>style="<?php echo $seat1_style; ?>"<?php endif; ?>>
                                        <?php echo $seat1; ?>
                                        <?php if ($seat1_claimed): ?>
                                        <div class="seat-tooltip">
                                            <strong><?php echo esc_html($claimed_seats[$seat1_id]['alias']); ?></strong>
                                            <?php if (!empty($claimed_seats[$seat1_id]['group'])): ?>
                                            <br>Group: <?php echo esc_html($claimed_seats[$seat1_id]['group']); ?>
                                            <?php endif; ?>
                                            <?php if (isset($claimed_seats[$seat1_id]['group_status']) && $claimed_seats[$seat1_id]['group_status'] === 'pending'): ?>
                                            <br><em>(Pending group approval)</em>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="seat <?php echo $seat2_claimed ? 'claimed' : ''; ?>" 
                                         data-section="<?php echo $letter; ?>" 
                                         data-seat="<?php echo $seat2; ?>"
                                         <?php if ($seat2_style): ?>style="<?php echo $seat2_style; ?>"<?php endif; ?>>
                                        <?php echo $seat2; ?>
                                        <?php if ($seat2_claimed): ?>
                                        <div class="seat-tooltip">
                                            <strong><?php echo esc_html($claimed_seats[$seat2_id]['alias']); ?></strong>
                                            <?php if (!empty($claimed_seats[$seat2_id]['group'])): ?>
                                            <br>Group: <?php echo esc_html($claimed_seats[$seat2_id]['group']); ?>
                                            <?php endif; ?>
                                            <?php if (isset($claimed_seats[$seat2_id]['group_status']) && $claimed_seats[$seat2_id]['group_status'] === 'pending'): ?>
                                            <br><em>(Pending group approval)</em>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                            
                            <div class="horizontal-gap"></div>
                            
                            <!-- Section Label -->
                            <div class="section-label"><?php echo $letter; ?></div>
                            
                            <?php if ($letter === 'A' || $letter === 'B'): ?>
                            <div class="ab-bottom-gap"></div>
                            <?php endif; ?>
                            
                            <?php
                            // Bottom Seats (Reversed Order)
                            for ($i = $bottomHalf - 1; $i >= 0; $i--) {
                                ?>
                                <div class="row">
                                    <?php
                                    $seat1 = $bottomStart + $i;
                                    $seat2 = $bottomStart + $bottomHalf + $i;
                                    $seat1_id = $letter . '-' . $seat1;
                                    $seat2_id = $letter . '-' . $seat2;
                                    $seat1_claimed = isset($claimed_seats[$seat1_id]);
                                    $seat2_claimed = isset($claimed_seats[$seat2_id]);
                                    
                                    // Get group colors if seats are in a group
                                    $seat1_style = '';
                                    $seat2_style = '';
                                    
                                    if ($seat1_claimed && isset($claimed_seats[$seat1_id]['group_color']) && $claimed_seats[$seat1_id]['group_status'] === 'approved') {
                                        $seat1_style = 'background-color: ' . esc_attr($claimed_seats[$seat1_id]['group_color']) . ';';
                                    }
                                    
                                    if ($seat2_claimed && isset($claimed_seats[$seat2_id]['group_color']) && $claimed_seats[$seat2_id]['group_status'] === 'approved') {
                                        $seat2_style = 'background-color: ' . esc_attr($claimed_seats[$seat2_id]['group_color']) . ';';
                                    }
                                    ?>
                                    <div class="seat <?php echo $seat1_claimed ? 'claimed' : ''; ?>" 
                                         data-section="<?php echo $letter; ?>" 
                                         data-seat="<?php echo $seat1; ?>"
                                         <?php if ($seat1_style): ?>style="<?php echo $seat1_style; ?>"<?php endif; ?>>
                                        <?php echo $seat1; ?>
                                        <?php if ($seat1_claimed): ?>
                                        <div class="seat-tooltip">
                                            <strong><?php echo esc_html($claimed_seats[$seat1_id]['alias']); ?></strong>
                                            <?php if (!empty($claimed_seats[$seat1_id]['group'])): ?>
                                            <br>Group: <?php echo esc_html($claimed_seats[$seat1_id]['group']); ?>
                                            <?php endif; ?>
                                            <?php if (isset($claimed_seats[$seat1_id]['group_status']) && $claimed_seats[$seat1_id]['group_status'] === 'pending'): ?>
                                            <br><em>(Pending group approval)</em>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="seat <?php echo $seat2_claimed ? 'claimed' : ''; ?>" 
                                         data-section="<?php echo $letter; ?>" 
                                         data-seat="<?php echo $seat2; ?>"
                                         <?php if ($seat2_style): ?>style="<?php echo $seat2_style; ?>"<?php endif; ?>>
                                        <?php echo $seat2; ?>
                                        <?php if ($seat2_claimed): ?>
                                        <div class="seat-tooltip">
                                            <strong><?php echo esc_html($claimed_seats[$seat2_id]['alias']); ?></strong>
                                            <?php if (!empty($claimed_seats[$seat2_id]['group'])): ?>
                                            <br>Group: <?php echo esc_html($claimed_seats[$seat2_id]['group']); ?>
                                            <?php endif; ?>
                                            <?php if (isset($claimed_seats[$seat2_id]['group_status']) && $claimed_seats[$seat2_id]['group_status'] === 'pending'): ?>
                                            <br><em>(Pending group approval)</em>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <div id="zoom-percentage">Zoom: 30%</div>
    
    <!-- Claim Form Overlay -->
    <div class="claim-form-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 9999; display: none;">
        <div class="claim-form" style="background-color: white; padding: 30px; border-radius: 10px; width: 400px; max-width: 90%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            <h2>Claim Seat <span id="claim-seat-label"></span></h2>
            <form>
                <div class="form-group">
                    <label for="user-alias">Your Alias/Gamertag *</label>
                    <input type="text" id="user-alias" name="user-alias" required>
                </div>
                
                <div class="form-group">
                    <label for="user-group">Group/Clan</label>
                    <div class="group-select-container">
                        <input type="text" id="user-group" name="user-group" placeholder="Enter group name or search...">
                        <input type="hidden" id="group-id" name="group-id" value="">
                        <div id="group-search-results" class="group-search-results"></div>
                    </div>
                </div>
                
                <div class="form-message"></div>
                
                <div class="form-actions">
                    <button type="button" class="cancel-btn">Cancel</button>
                    <button type="submit" class="submit-btn">Claim Seat</button>
                </div>
            </form>
        </div>
    </div>
</div>