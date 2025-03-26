<?php
include '../wp-load.php';
// Hook into WordPress content filter to clean up post content
add_filter('the_content', 'cleanup_wordpress_content');

function cleanup_wordpress_content($content) {
    // Define replacements for unnecessary tags and shortcodes
    $patterns = [
        '/\[\/?et_pb_[^\]]*\]/',        // Match and remove Elementor shortcode tags
        '/@ET-DC@[^@]*@/',             // Match and remove Elementor dynamic attributes
        '/\s{2,}/',                    // Replace multiple spaces with a single space
    ];

    // Perform replacements using regex
    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }

    // Trim extra whitespace and return cleaned content
    return trim($content);
}

// Optional: Bulk update existing posts in the database
function bulk_cleanup_posts() {
    global $wpdb;

    // Get all posts from the database
    $posts = $wpdb->get_results("SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");

    foreach ($posts as $post) {
        // Clean the content using the same function
        echo $cleaned_content = cleanup_wordpress_content($post->post_content);
        die;

        // Update the post with cleaned content
        $wpdb->update(
            $wpdb->posts,
            ['post_content' => $cleaned_content],
            ['ID' => $post->ID],
            ['%s'],
            ['%d']
        );
    }
}

// Uncomment to run bulk cleanup once (then comment it back to avoid rerunning every load)
// bulk_cleanup_posts();

?>