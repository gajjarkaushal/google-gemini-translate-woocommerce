<?php
// Load WordPress core
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

// Schedule the cron job if not already scheduled
if (!wp_next_scheduled('translate_products_batch')) {
    wp_schedule_event(time(), 'minute', 'translate_products_batch');
}

add_action('translate_products_batch', 'process_translation_batch');

function process_translation_batch() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Check if the translation function exists
    if (!class_exists('GoogleGeminiTranslate')) {
        return;
    }

    $translator = GoogleGeminiTranslate::get_instance();
    $batch_size = 10;

    // Get WooCommerce products that have NOT been translated
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => $batch_size,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => '_translated_gemini',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ];

    $products = get_posts($args);
    if (!$products) {
        wp_clear_scheduled_hook('translate_products_batch');
        return;
    }

    foreach ($products as $product) {
        $post_id = $product->ID;
        $original_title = get_the_title($post_id);
        $translated_title = $translator->translate($original_title);

        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $translated_title,
        ]);

        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                $translated_alt = $translator->translate($alt_text);
                update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $translated_alt);
            }
        }

        $gallery_image_ids = get_post_meta($post_id, '_product_image_gallery', true);
        if (!empty($gallery_image_ids)) {
            $gallery_ids = explode(',', $gallery_image_ids);
            foreach ($gallery_ids as $image_id) {
                $gallery_alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                if (!empty($gallery_alt_text)) {
                    $translated_gallery_alt = $translator->translate($gallery_alt_text);
                    update_post_meta($image_id, '_wp_attachment_image_alt', $translated_gallery_alt);
                }
            }
        }

        if ($translated_title !== $original_title) {
            update_post_meta($post_id, '_translated_gemini', 'yes');
        }
    }
}