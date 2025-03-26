<?php
// Load WordPress core
require_once('../../wp-load.php');

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    exit('WooCommerce is not installed or activated.');
}

// Check if the translation function exists
if (!class_exists('GoogleGeminiTranslate')) {
    exit('Google Gemini Translate plugin is not installed.');
}

// Get instance of the GoogleGeminiTranslate class
$translator = GoogleGeminiTranslate::get_instance();

// Define batch size
$batch_size = 10;

// Get WooCommerce products that have NOT been translated
$args = [
    'post_type'      => 'product',
    'posts_per_page' => $batch_size,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => '_translated_gemini',
            'compare' => 'NOT EXISTS', // Skip already translated products
        ],
    ],
];

$products = get_posts($args);
if (!$products) {
    exit('<h3>✅ All products have been translated. No products left to process.</h3>');
}

echo '<h3>🔄 Processing Batch of ' . count($products) . ' Products...</h3>';
flush();

foreach ($products as $product) {
    $post_id = $product->ID;

    // Get original title
    $original_title = get_the_title($post_id);

    // Translate title using the plugin function
    $translated_title = $translator->translate($original_title);

    // Update product title
    wp_update_post([
        'ID'         => $post_id,
        'post_title' => $translated_title,
    ]);

    echo "✅ Translated Product: <strong>{$original_title}</strong> → <strong>{$translated_title}</strong><br>";
    flush();

    // Process Featured Image Alt Text
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id) {
        $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
        if (!empty($alt_text)) {
            // Translate alt text
            $translated_alt = $translator->translate($alt_text);
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $translated_alt);
            
            echo "🌍 Translated Featured Image Alt: <em>{$alt_text}</em> → <em>{$translated_alt}</em><br>";
            flush();
        }
    }

    // Process WooCommerce Gallery Images
    $gallery_image_ids = get_post_meta($post_id, '_product_image_gallery', true);
    if (!empty($gallery_image_ids)) {
        $gallery_ids = explode(',', $gallery_image_ids);
        foreach ($gallery_ids as $image_id) {
            $gallery_alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
            if (!empty($gallery_alt_text)) {
                // Translate gallery alt text
                $translated_gallery_alt = $translator->translate($gallery_alt_text);
                update_post_meta($image_id, '_wp_attachment_image_alt', $translated_gallery_alt);
                
                echo "📸 Translated Gallery Image Alt: <em>{$gallery_alt_text}</em> → <em>{$translated_gallery_alt}</em><br>";
                flush();
            }
        }
    }

    // Mark product as translated
    update_post_meta($post_id, '_translated_gemini', 'yes');

    echo "<hr>";
    flush();
    die;
}

// Redirect to process next batch
// echo '<script>setTimeout(() => { window.location.reload(); }, 3000);</script>';
?>
    