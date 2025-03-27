<?php
/**
 * Plugin Name: Google Gemini Translate for WooCommerce
 * Description: WooCommerce product titles and image alt tags using Google Gemini AI.
 * Author URI: https://gajjarkaushal.com
 * Version: 1.2
 * Author: Kaushal Gajjar
 * Author URI: https://gajjarkaushal.com
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GoogleGeminiTranslate {
    
    private static $instance = null;
    private $api_key;
    private $target_language;

    /**
     * Gets the singleton instance of the plugin.
     *
     * @return GoogleGeminiTranslate
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Constructor method.
     *
     * Sets up the plugin by initializing variables and hooks.
     *
     * @private
     */
    private function __construct() {
        $this->api_key = get_option('ggt_api_key');
        $this->target_language = get_option('ggt_target_language', 'es'); // Default to Spanish

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'schedule_translation_cron']);
        add_action('translate_products_batch', [$this, 'process_translation_batch']);
        add_filter('cron_schedules', [$this,'ggt_cron_schedules']);
    }
    /**
     * Adds the plugin settings page to the WordPress Admin menu.
     *
     * The page is added as an option page and is accessible to users with the
     * 'manage_options' capability.
     *
     * @since 1.0.0
     */
    public function add_settings_page() {
        add_options_page(
            'Google Gemini Translate',
            'Gemini Translate',
            'manage_options',
            'ggt-settings',
            [$this, 'render_settings_page']
        );
    }
    /**
     * Renders the plugin settings page in the WordPress Admin interface.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Google Gemini Translate Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ggt_settings_group');
                do_settings_sections('ggt-settings');
                submit_button();
                ?>
            </form>
            <form method="post">
                <input type="hidden" name="start_translation" value="1">
                <button type="submit" class="button button-primary"><?php _e('Start Translation Schedule', 'ggt'); ?></button>
                <?php
                    $last_schedule_time = get_option('translate_products_batch_time');
                    if ($last_schedule_time) {
                        echo '<p>Last schedule: ' . date('Y-m-d H:i:s', $last_schedule_time) . '</p>';
                    }
                    $error_message = get_option('gemini_translate_error');
                    if ($error_message) {
                        add_action('admin_notices', function () use ($error_message) {
                            echo '<div class="notice notice-error"><p>Error: ' . esc_html($error_message) . '</p></div>';
                        });
                    }
                ?>
            </form>
        </div>
        <?php
    }
    /**
     * Registers the plugin's settings fields and sections in the WordPress Admin interface.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        
        if(isset($_POST) && !empty($_POST)){
            if( 'Test Api key' === $this->translate_text('Test Api key')){
                add_settings_error('ggt_settings_group', 'invalid_api_key', __('Invalid Gemini API key', 'ggt'), 'error');
            }
        }
        
        register_setting('ggt_settings_group', 'ggt_api_key');
        register_setting('ggt_settings_group', 'ggt_target_language');

        add_settings_section(
            'ggt_main_section',
            'API Settings',
            null,
            'ggt-settings'
        );

        add_settings_field(
            'ggt_api_key',
            'Google Gemini API Key',
            [$this, 'api_key_callback'],
            'ggt-settings',
            'ggt_main_section'
        );

        add_settings_field(
            'ggt_target_language',
            'Select Target Language',
            [$this, 'language_dropdown_callback'],
            'ggt-settings',
            'ggt_main_section'
        );
    }
    /**
     * Outputs the input field for the Google Gemini API Key.
     *
     * Retrieves the current API key from the WordPress options and displays it
     * in an input field on the settings page, allowing the user to update the key.
     *
     * @since 1.0.0
     */

    public function api_key_callback() {
        $api_key = get_option('ggt_api_key');
        echo '<input type="text" name="ggt_api_key" value="' . esc_attr($api_key) . '" size="50">';
    }
    /**
     * Outputs a dropdown list of languages for the user to select from.
     *
     * The currently selected language is retrieved from the WordPress options
     * and used to pre-select the corresponding option in the dropdown list.
     *
     * @since 1.0.0
     */
    public function language_dropdown_callback() {
        $selected_language = get_option('ggt_target_language', 'es');
        $languages = [
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'zh' => 'Chinese (Simplified)',
            'ja' => 'Japanese',
            'ru' => 'Russian',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'hi' => 'Hindi',
            'ar' => 'Arabic',
            'sv' => 'Swedish',
        ];

        echo '<select name="ggt_target_language">';
        foreach ($languages as $code => $name) {
            $selected = ($selected_language === $code) ? 'selected' : '';
            echo "<option value='$code' $selected>$name</option>";
        }
        echo '</select>';
    }
    /**
     * Translate a given text to the target language using the Google Gemini AI
     *
     * @param string $text The text to translate
     * @return string The translated text or the original text if translation fails
     */
    private function translate_text($text) {
        if (!$this->api_key) { 
            return $text;
        }

        $target_language = get_option('ggt_target_language', 'sv'); // Default to Swedish
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->api_key;
        
        $body = json_encode([
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => "translate en to {$target_language}\n\n{$text}\n"]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'       => 1,
                'topK'             => 40,
                'topP'             => 0.95,
                'maxOutputTokens'   => 8192,
                'responseMimeType'  => 'text/plain'
            ]
        ]);

        $response = wp_remote_post($url, [
            'method'    => 'POST',
            'body'      => $body,
            'headers'   => [
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            return $text; // Return original text if request fails
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if(isset($data['error']) ){
            update_option('gemini_translate_error', $data['error']['message']);
            add_action('admin_notices', function() use ($data) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html($data['error']['message']) . '</p>';
                echo '</div>';
            });
            return $text; // Return
        }

        if(get_option('gemini_translate_error')) {
            delete_option('gemini_translate_error');
        }
        
        // Extract the first response text if multiple options exist
        if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            $translated_text = $data['candidates'][0]['content']['parts'][0]['text'];

            // Pick the first **bold** option if available
            preg_match('/\*\*([^*]+)\*\*/', $translated_text, $matches);
            if (!empty($matches[1])) {
                return trim($matches[1]); // Return first bolded translation
            }

            return trim($translated_text); // Return as is if no formatting
        }

        return $text;
    }
    public function translate($text) {
        return $this->translate_text($text);
    }
    /**
     * Schedule a cron job to translate a batch of products hourly.
     *
     * This function should be called on plugin activation to schedule
     * the cron job. If the cron job is already scheduled, this function
     * will not override it.
     */
    public function schedule_translation_cron() {
        if (!wp_next_scheduled('translate_products_batch') || isset($_POST['start_translation'])) {
            $time = time();
            wp_schedule_event($time, 'every_three_two', 'translate_products_batch');
            update_option('translate_products_batch_time', $time);
        }
    }
    /**
     * Add a custom cron schedule for every 3 minutes.
     *
     * This function extends the existing WordPress cron schedules by adding
     * a new interval that runs every 3 minutes. This schedule can be used
     * to trigger events at a higher frequency than the default WordPress
     * cron schedules.
     *
     * @param array $schedules An array of existing cron schedules.
     * @return array The modified array of cron schedules including the new
     *               3-minute interval.
     */
    function ggt_cron_schedules($schedules) {
        // Add a new schedule for every 3 minutes
        $schedules['every_three_two'] = array(
            'interval' => 120, // 180 seconds = 3 minutes
            'display'  => __('Every 2 Minutes','ggt'),
        );
        return $schedules;
    }
    /**
     * Process a batch of WooCommerce products for translation
     *
     * This function is designed to be called by a cron job to translate
     * WooCommerce product titles and alt tags using Google Gemini Translate
     *
     * @since 1.0
     * @return void
     */
    public function process_translation_batch() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        if (!class_exists('GoogleGeminiTranslate')) {
            return;
        }

        $batch_size = 10;
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
            $translated_title = $this->translate($original_title);
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $translated_title,
            ]);

            error_log("Translated Product: {$original_title} -> {$translated_title}");

            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                if (!empty($alt_text)) {
                    $translated_alt = $this->translate($alt_text);
                    update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $translated_alt);
                    error_log("Translated Featured Image Alt: {$alt_text} -> {$translated_alt}");
                }
            }

            $gallery_image_ids = get_post_meta($post_id, '_product_image_gallery', true);
            if (!empty($gallery_image_ids)) {
                $gallery_ids = explode(',', $gallery_image_ids);
                foreach ($gallery_ids as $image_id) {
                    $gallery_alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                    if (!empty($gallery_alt_text)) {
                        $translated_gallery_alt = $this->translate($gallery_alt_text);
                        update_post_meta($image_id, '_wp_attachment_image_alt', $translated_gallery_alt);
                        error_log("Translated Gallery Image Alt: {$gallery_alt_text} -> {$translated_gallery_alt}");
                    }
                }
            }

            if ($translated_title !== $original_title) {
                update_post_meta($post_id, '_translated_gemini', 'yes');
            }
        }
    }
}
// Initialize the plugin
GoogleGeminiTranslate::get_instance();
?>