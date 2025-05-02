<?php
/**
 * Plugin Name: Popup Tracking
 * Description: Checks for recent posts and triggers (Elementor Pro) popup. Added section that will post data from determined post category. 
 * Version: 1.01
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'admin/admin-menu.php'; 

function popup_shortcode_handler() {
    $recent_posts = new WP_Query([
        'category_name'  => 'operations-notifications',
        'posts_per_page' => 1,
        'date_query'     => [
            [
                'after' => '7 days ago',
                'inclusive' => true,
            ],
        ],
    ]);

    ob_start();
    if ($recent_posts->have_posts()) {
        ?>
        <script>
        jQuery(window).on('elementor/frontend/init', function () {
            if (typeof elementorProFrontend !== 'undefined') {
                elementorProFrontend.modules.popup.showPopup({ id: 12961 });
            }
        });
        </script>
        <?php
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('popup_check', 'popup_shortcode_handler');

function operations_notifications_shortcode() {
    $query = new WP_Query([
        'category_name'  => 'operations-notifications',
        'posts_per_page' => 5, 
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    ob_start();

    echo '<div class="operations-notifications">';
    echo '<h3>Operations Notifications — ' . esc_html(date_i18n('F j, Y')) . '</h3>';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            echo '<p><strong>' . esc_html(get_the_title()) . '</strong> — <em>' . esc_html(get_the_date()) . '</em></p>';
        }
    } else {
        echo '<p>No recent notifications.</p>';
    }

    echo '</div>';
    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('operations_notifications_list', 'operations_notifications_shortcode');

function operations_notifications_recent_posts() {
	operations_notifications_enqueue_scripts(); 
    $category_slug = 'operations-notifications';
    $days_limit = 5;
    $popup_id = 12961;

    $recent_posts = get_posts([
        'category_name' => $category_slug,
        'posts_per_page' => 5,
        'date_query' => [
            [
                'after' => "$days_limit days ago",
                'inclusive' => true,
            ]
        ],
        'post_status' => 'publish'
    ]);

    $has_recent_posts = !empty($recent_posts);
    ob_start();

    if ($has_recent_posts) {
	$latest_post_date = get_the_date('Y-m-d', $recent_posts[0]->ID);

    	echo '<div class="has-category-' . esc_attr($category_slug) . '" data-latest-post="' . esc_attr($latest_post_date) . '">';
    
        foreach ($recent_posts as $post) {
            $title = esc_html(get_the_title($post));
            $date = esc_html(get_the_date('', $post));
        }
        echo '</div>';
    }

    return ob_get_clean();
}
add_shortcode('operations_notifications_recent', 'operations_notifications_recent_posts');

function operations_notifications_enqueue_scripts() {
    wp_enqueue_script('operations-notifications-js', plugin_dir_url(__FILE__) . 'operations-notifications.js', array('jquery'), null, true);

    wp_localize_script('operations-notifications-js', 'operations_notifications_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'operations_notifications_enqueue_scripts');

function record_popup_dismissal() {
    // Enable error logging
    ini_set('display_errors', 1);
    error_log('Popup dismissal function called: ' . print_r($_POST, true));
    
    // Handle potential missing post_date
    if (!isset($_POST['post_date'])) {
        error_log('No post date provided in request');
        wp_send_json_error('No post date provided.');
        return;
    }

    $post_date = sanitize_text_field($_POST['post_date']);
    error_log('Processing post date: ' . $post_date);
    
    // Get existing data with error handling
    $counts = get_option('popup_acknowledged_counts', []);
    if (!is_array($counts)) {
        error_log('Invalid popup_acknowledged_counts format, resetting');
        $counts = [];
    }
    
    // Get post ID and title based on the date
    $args = array(
        'category_name'  => 'operations-notifications',
        'posts_per_page' => 1,
        'date_query'     => array(
            array(
                'year'  => date('Y', strtotime($post_date)),
                'month' => date('m', strtotime($post_date)),
                'day'   => date('d', strtotime($post_date)),
            ),
        ),
        'post_status' => 'publish'
    );
    
    error_log('Searching for post with args: ' . print_r($args, true));
    
    $query = new WP_Query($args);
    $post_title = 'Unknown Post';
    $post_id = 0;
    
    if ($query->have_posts()) {
        $query->the_post();
        $post_title = get_the_title();
        $post_id = get_the_ID();
        error_log("Found post: ID=$post_id, Title=$post_title");
        wp_reset_postdata();
    } else {
        error_log('No matching post found for date: ' . $post_date);
    }

    // Create new entry if needed
    if (!isset($counts[$post_date])) {
        $counts[$post_date] = [
            'title' => $post_title,
            'post_id' => $post_id,
            'timestamps' => []
        ];
        error_log('Created new entry for post date: ' . $post_date);
    } else if (!isset($counts[$post_date]['timestamps'])) {
        // Handle legacy data format
        $old_data = $counts[$post_date];
        $counts[$post_date] = [
            'title' => $post_title,
            'post_id' => $post_id,
            'timestamps' => is_array($old_data) ? $old_data : []
        ];
        error_log('Converted legacy data format for date: ' . $post_date);
    }

    // Add new timestamp
    $timestamp = current_time('mysql');
    $counts[$post_date]['timestamps'][] = $timestamp;
    
    // Force the data to be updated by using a unique option name if necessary
    $update_result = update_option('popup_acknowledged_counts', $counts);
    error_log('Update option result: ' . ($update_result ? 'success' : 'failed or unchanged'));
    
    // If we're in fallback mode, just exit
    if (isset($_POST['fallback'])) {
        error_log('Fallback mode detected: ' . $_POST['fallback']);
        if ($_POST['fallback'] === 'xhr') {
            echo json_encode(['success' => true, 'message' => 'XHR fallback recorded']);
        }
        exit;
    }

    // Return success response
    wp_send_json_success([
        'message' => 'Dismissal recorded with post title: ' . $post_title,
        'post_id' => $post_id,
        'timestamp' => $timestamp
    ]);
}
add_action('wp_ajax_record_popup_dismissal', 'record_popup_dismissal');
add_action('wp_ajax_nopriv_record_popup_dismissal', 'record_popup_dismissal'); 

function popup_debug_scripts() {
    if (current_user_can('manage_options')) {
        wp_enqueue_script('popup-debug', plugin_dir_url(__FILE__) . 'debug.js', array('jquery'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'popup_debug_scripts');

