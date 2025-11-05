<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void {}
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {}
}

if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {
        return ''; // no-op for tests.
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '') {
        return ''; // no-op for tests.
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = null): void {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return esc_html($text);
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null): void {
        echo esc_html($text);
    }
}

if (!function_exists('esc_html_x')) {
    function esc_html_x($text, $context = '', $domain = null) {
        return esc_html($text);
    }
}

if (!isset($GLOBALS['_saeid_scrapper_test_terms'])) {
    $GLOBALS['_saeid_scrapper_test_terms'] = [];
}

if (!isset($GLOBALS['_saeid_scrapper_test_term_parents'])) {
    $GLOBALS['_saeid_scrapper_test_term_parents'] = [];
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post_id, $taxonomy) {
        $key = $post_id . ':' . $taxonomy;
        return $GLOBALS['_saeid_scrapper_test_terms'][$key] ?? [];
    }
}

if (!function_exists('get_term_parents_list')) {
    function get_term_parents_list($term_id, $taxonomy, $args = []) {
        $key = $term_id . ':' . $taxonomy;
        return $GLOBALS['_saeid_scrapper_test_term_parents'][$key] ?? '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return $url;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return strip_tags($text);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        $title = preg_replace('/[\x00-\x1F\x7F]+/u', '', (string) $title);
        $title = trim($title);
        $title = preg_replace('/[\s\-_.]+/u', '-', $title);
        $title = preg_replace('/[^\pL\pN\-]+/u', '', $title);
        $title = trim($title, '-');

        return function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($text) {
        return $text;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim(strip_tags((string) $text));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($text) {
        return trim(strip_tags((string) $text));
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = '…') {
        $words = preg_split('/\s+/u', trim(strip_tags((string) $text)), -1, PREG_SPLIT_NO_EMPTY);

        if (false === $words) {
            return '';
        }

        if (count($words) <= $num_words) {
            return implode(' ', $words);
        }

        return implode(' ', array_slice($words, 0, $num_words)) . $more;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return stripslashes((string) $value);
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string) {
        return rtrim((string) $string, "\\/");
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $errors = array();

        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code) {
                $this->errors[$code] = array($message, $data);
            }
        }
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy) {
        return false;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms() {
        return array();
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta() {
        return true;
    }
}

if (!function_exists('term_exists')) {
    function term_exists() {
        return false;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = array()) {
        return array('term_id' => rand(1000, 9999));
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename((string) $file);
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return '';
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return (string) $path;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'nonce';
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(): void {}
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(): void {}
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script(): void {}
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability = '') {
        return true;
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = ''): void {
        throw new RuntimeException((string) $message);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce = '', $action = -1) {
        return true;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false) {
        return $wp_error ? new WP_Error('not-implemented', 'wp_insert_post stub') : 0;
    }
}

if (!function_exists('wp_unique_post_slug')) {
    function wp_unique_post_slug($slug) {
        return $slug;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(): bool {
        return true;
    }
}

require_once dirname(__DIR__) . '/wp-content/plugins/saeid-scrapper/saeid-scrapper.php';
