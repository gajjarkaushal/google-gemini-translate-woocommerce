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

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_key = get_option('ggt_api_key');
        $this->target_language = get_option('ggt_target_language', 'es'); // Default to Spanish

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('save_post', [$this, 'translate_product_meta']);
        add_action('init',function(){
            //   echo $this->translate_text('Forever FM transmitter Bluetooth TR-320 black');
            //   die;
        });
    }

    // Add settings page
    public function add_settings_page() {
        add_options_page(
            'Google Gemini Translate',
            'Gemini Translate',
            'manage_options',
            'ggt-settings',
            [$this, 'render_settings_page']
        );
    }

    // Render settings page
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
        </div>
        <?php
    }

    // Register settings
    public function register_settings() {
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

    public function api_key_callback() {
        $api_key = get_option('ggt_api_key');
        echo '<input type="text" name="ggt_api_key" value="' . esc_attr($api_key) . '" size="50">';
    }

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

    // Function to translate text using Google Gemini API
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
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? $text;
    }



    // Hook to translate product title and alt tags on save
    public function translate_product_meta($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }

        $title = get_the_title($post_id);
        $translated_title = $this->translate_text($title);

        // Update product title
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $translated_title,
        ]);

        // Update image alt tags
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
            $translated_alt = $this->translate_text($alt_text);
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', $translated_alt);
        }
    }
    public function translate($text) {
            return $this->translate_text($text);
        }

}

// Initialize the plugin
GoogleGeminiTranslate::get_instance();
?>