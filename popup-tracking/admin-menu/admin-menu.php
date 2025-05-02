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
    $acknowledgments = get_option('popup_acknowledged_counts', []);
    $per_page = 25;
    
    echo '<div class="wrap">';
    echo '<h1>Popup Acknowledged Views</h1>';
    
    if (empty($acknowledgments) || !is_array($acknowledgments)) {
        echo '<p>No acknowledgments recorded yet.</p>';
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
        echo '<h2>' . esc_html($post_date) . ' - Post: <a href="' . 
             get_edit_post_link($post_id) . '">' . esc_html($post_title) . '</a></h2>';
        echo '<p>' . esc_html($total_entries) . ' acknowledgments</p>';
        
        echo '<ul style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
        foreach ($paged_entries as $entry) {
            echo '<li>' . esc_html($entry) . '</li>';
        }
        echo '</ul>';
        
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
    
    echo '</div>';
}

add_action('admin_menu', 'popup_trigger_admin_menu');