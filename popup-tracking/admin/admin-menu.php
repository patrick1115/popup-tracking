<?php
function popup_trigger_admin_menu() {
    add_menu_page(
        'Popup Views',       
        'Popup Notifications',        
        'manage_options',                  
        'popup-trigger-admin',        
        'popup_trigger_admin_page', 
        'dashicons-bell',                  
        25                                
    );
}

function popup_trigger_admin_page() {
    // Check if we need to force refresh the counts
    if (isset($_GET['refresh_data']) && $_GET['refresh_data'] === '1') {
        $counts = get_option('popup_acknowledged_counts', []);
        update_option('popup_acknowledged_counts_backup', $counts); // Create a backup
        update_option('popup_acknowledged_counts', $counts); // Force refresh
        
        // Redirect to remove the refresh parameter
        wp_redirect(remove_query_arg('refresh_data'));
        exit;
    }

    // Clear specific post data if requested
    if (isset($_GET['clear_post']) && $_GET['clear_post']) {
        $clear_key = sanitize_text_field($_GET['clear_post']);
        $counts = get_option('popup_acknowledged_counts', []);
        if (isset($counts[$clear_key])) {
            // backup, then clear
            $backup = get_option('popup_acknowledged_counts_backup', []);
            $backup[$clear_key] = $counts[$clear_key];
            update_option('popup_acknowledged_counts_backup', $backup);

            unset($counts[$clear_key]);
            update_option('popup_acknowledged_counts', $counts);                                                
        }

        // Redirect to remove the clear_post parameter
        wp_redirect(remove_query_arg('clear_post'));
        exit;
    }

    if (isset($_GET['undo_clear']) && $_GET['undo_clear']) {
        $undo_key = sanitize_text_field($_GET['undo_clear']);
        $backup = get_option('popup_acknowledged_counts_backup', []);
        $counts = get_option('popup_acknowledged_counts', []);
        
        if (isset($backup[$undo_key])) {
            $counts[$undo_key] = $backup[$undo_key];
            update_option('popup_acknowledged_counts', $counts);

            //removed from backup after restore
            unset($backup[$undo_key]);
            update_option('popup_acknowledged_counts_backup', $backup);
        }

        wp_redirect(remove_query_arg('undo_clear'));
        exit;
    }

    $acknowledgments = get_option('popup_acknowledged_counts', []);
    $per_page = 25;
    
    echo '<div class="wrap">';
    echo '<h1>Popup Acknowledged Views</h1>';
    
    /*
    // Add refresh button and debug information
    echo '<div class="notice notice-info inline">';
    echo '<p>If you\'re not seeing the latest data, try refreshing. <a href="' . 
         add_query_arg('refresh_data', '1') . '" class="button button-primary">Refresh Data</a></p>';
    echo '</div>';
    
    // Add debug information section
    echo '<div class="postbox" style="padding: 15px; margin-bottom: 20px;">';
    echo '<h3>Debug Information</h3>';
    echo '<p>Last data update: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>Number of records: ' . (is_array($acknowledgments) ? count($acknowledgments) : 0) . '</p>';
    echo '<p>WordPress version: ' . get_bloginfo('version') . '</p>';
    echo '<p>PHP version: ' . phpversion() . '</p>';
    echo '</div>';
    */

    if (empty($acknowledgments) || !is_array($acknowledgments)) {
        echo '<div class="notice notice-warning"><p>No acknowledgments recorded yet. This could be because:</p>';
        echo '<ul style="list-style-type: disc; margin-left: 20px;">';
        echo '<li>No users have dismissed the popup yet</li>';
        echo '<li>The AJAX request is failing to complete</li>';
        echo '<li>There may be a permission issue with saving the option</li>';
        echo '</ul></div>';
        return;
    }
    
    // Sort acknowledgments by date (newest first)
    krsort($acknowledgments);
    
    foreach ($acknowledgments as $post_date => $data) {
        // Check if we have the new structure or legacy data
        if (isset($data['timestamps']) && is_array($data['timestamps'])) {
            $entries = $data['timestamps'];
            $post_title = isset($data['title']) ? $data['title'] : 'Unknown Post';
            $post_id = isset($data['post_id']) ? $data['post_id'] : 0;
        } else {
            // Legacy data structure
            $entries = is_array($data) ? $data : [];
            $post_title = 'Unknown Post';
            $post_id = 0;
        }
        
        $total_entries = count($entries);
        $total_pages = max(1, ceil($total_entries / $per_page));
        $param_key = "page_num_" . sanitize_key($post_date);
        $current_page = isset($_GET[$param_key]) ? max(1, intval($_GET[$param_key])) : 1;
        $start_index = ($current_page - 1) * $per_page;
        $paged_entries = array_slice($entries, $start_index, $per_page);
        
        echo '<div class="postbox" style="padding: 15px; margin-bottom: 20px;">';
        
        // Add post information with better formatting
        echo '<h2 style="display: flex; justify-content: space-between;">';
        echo '<span>' . esc_html($post_date) . ' - Post: ';
        
        if ($post_id > 0) {
            echo '<a href="' . get_edit_post_link($post_id) . '">' . esc_html($post_title) . '</a>';
        } else {
            echo esc_html($post_title);
        }
        echo '</span>';
        
        echo '<span class="dashicons dashicons-yes-alt" style="color: green;" title="' . 
             esc_attr($total_entries) . ' acknowledgments"></span>';

        $clear_url = add_query_arg('clear_post', rawurlencode($post_date));
        echo '<a href="' . esc_url($clear_url) . '" class="button button-small" onclick="return confirm(\'Are you sure you want to clear this post\'s acknowledgment data?\')">Clear</a>';
        echo '</span>';     
        echo '</h2>';
        
        echo '<p><strong>' . esc_html($total_entries) . ' acknowledgments</strong></p>';
        
        echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
        echo '<thead><tr><th>Time Acknowledged</th><th>User Time (Local)</th></tr></thead>';
        echo '<tbody>';
        foreach ($paged_entries as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html($entry) . '</td>';
            echo '<td><script>document.write(new Date("' . esc_js($entry) . '").toLocaleString());</script></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
        if ($total_pages > 1) {
            echo '<div class="tablenav" style="margin-top: 10px;">';
            echo '<div class="tablenav-pages">';
            echo '<span class="pagination-links">';
            
            for ($i = 1; $i <= $total_pages; $i++) {
                $url = admin_url('admin.php?page=popup-trigger-admin');
                $url = add_query_arg($param_key, $i, $url);
                $is_current = ($i === $current_page);
                
                if ($is_current) {
                    echo '<span class="tablenav-pages-navspan button disabled">' . $i . '</span>';
                } else {
                    echo '<a href="' . esc_url($url) . '" class="button">' . $i . '</a>';
                }
            }
            
            echo '</span>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    $backup = get_option('popup_acknowledged_counts_backup', []);
    foreach ($backup as $post_date => $data) {
        $post_title = isset($data['title']) ? $data['title'] : 'Unknown Post';
        $post_id = isset($data['post_id']) ? $data['post_id'] : 0;
        $total_entries = isset($data['timestamps']) ? count($data['timestamps']) : (is_array($data) ? count($data) : 0);

        echo '<div class="postbox" style="padding: 15px; margin-bottom: 20px; background: #fff8e5;">';
        echo '<h2 style="display: flex; justify-content: space-between; align-items: center;">';
        echo '<span>Cleared: ' . esc_html($post_date) . ' - Post: ';
        
        if ($post_id > 0) {
            echo '<a href="' . get_edit_post_link($post_id) . '">' . esc_html($post_title) . '</a>';
        } else {
            echo esc_html($post_title);
        }

        echo '</span>';

        echo '<span>';
        echo '<span class="dashicons dashicons-dismiss" style="color: orange; margin-right: 10px;" title="Cleared"></span>';
        $undo_url = add_query_arg('undo_clear', rawurlencode($post_date));
        echo '<a href="' . esc_url($undo_url) . '" class="button button-small" style="background: #ffba00; border-color: #ffba00;">Undo Clear</a>';
        echo '</span>';

        echo '</h2>';
        echo '<p><strong>' . esc_html($total_entries) . ' acknowledgments (in backup)</strong></p>';
        echo '</div>';
    }   

    
    echo '</div>';
}

add_action('admin_menu', 'popup_trigger_admin_menu');