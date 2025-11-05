<?php
/**
 * Plugin Name: Saeid Scrapper
 * Description: Provides admin tools for managing scraping targets including bulk URL collection.
 * Version: 1.1.0
 * Author: OpenAI ChatGPT
 * Text Domain: saeid-scrapper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Saeid_Scrapper_Plugin {
    const VERSION = '1.1.0';
    const OPTION_NAME = 'saeid_scrapper_settings';
    const NONCE_ACTION = 'saeid_scrapper_bulk_import';
    const SCRAPE_NONCE_ACTION = 'saeid_scrapper_scrape_urls';
    const TABLE_SUFFIX = 'saeid_scrapper_urls';
    const CATEGORY_NONCE_ACTION = 'saeid_scrapper_sync_categories';

    /**
     * Bootstraps the plugin by registering hooks.
     */
    public static function init() {
        register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade_table' ), 5 );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_bulk_import' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_assets' ) );
        add_action( 'wp_ajax_saeid_scrapper_process_next', array( __CLASS__, 'ajax_process_next_url' ) );
        add_action( 'wp_ajax_saeid_scrapper_sync_category', array( __CLASS__, 'ajax_sync_next_category' ) );
    }

    /**
     * Loads the plugin text domain for translation.
     */
    public static function load_textdomain() {
        load_plugin_textdomain( 'saeid-scrapper', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Returns the name of the custom database table used to store URLs.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     *
     * @return string
     */
    protected static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Creates the database table on activation.
     */
    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = self::get_table_name();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url text NOT NULL,
            url_hash varchar(40) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            product_id bigint(20) unsigned DEFAULT NULL,
            last_error text DEFAULT NULL,
            last_scraped datetime DEFAULT NULL,
            category_status varchar(20) NOT NULL DEFAULT 'pending',
            category_synced_at datetime DEFAULT NULL,
            category_error text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash),
            KEY status (status),
            KEY category_status (category_status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Ensures the database table schema is up to date.
     */
    public static function maybe_upgrade_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $table      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        if ( $table !== $table_name ) {
            return;
        }

        $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
        $found   = array();

        foreach ( (array) $columns as $column ) {
            if ( isset( $column['Field'] ) ) {
                $found[ $column['Field'] ] = true;
            }
        }

        $queries = array();

        if ( ! isset( $found['status'] ) ) {
            $queries[] = "ADD COLUMN status varchar(20) NOT NULL DEFAULT 'pending'";
        }

        if ( ! isset( $found['product_id'] ) ) {
            $queries[] = 'ADD COLUMN product_id bigint(20) unsigned DEFAULT NULL';
        }

        if ( ! isset( $found['last_error'] ) ) {
            $queries[] = 'ADD COLUMN last_error text DEFAULT NULL';
        }

        if ( ! isset( $found['last_scraped'] ) ) {
            $queries[] = 'ADD COLUMN last_scraped datetime DEFAULT NULL';
        }

        if ( ! isset( $found['category_status'] ) ) {
            $queries[] = "ADD COLUMN category_status varchar(20) NOT NULL DEFAULT 'pending'";
        }

        if ( ! isset( $found['category_synced_at'] ) ) {
            $queries[] = 'ADD COLUMN category_synced_at datetime DEFAULT NULL';
        }

        if ( ! isset( $found['category_error'] ) ) {
            $queries[] = 'ADD COLUMN category_error text DEFAULT NULL';
        }

        if ( ! isset( $found['status'] ) || ! isset( $found['product_id'] ) || ! isset( $found['last_error'] ) || ! isset( $found['last_scraped'] ) ) {
            $queries[] = 'ADD INDEX status (status)';
        }

        if ( ! isset( $found['category_status'] ) ) {
            $queries[] = 'ADD INDEX category_status (category_status)';
        }

        if ( ! empty( $queries ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} " . implode( ', ', $queries ) );
        }
    }

    /**
     * Registers the plugin menu and submenus.
     */
    public static function register_admin_menu() {
        add_menu_page(
            __( 'Saeid Scrapper', 'saeid-scrapper' ),
            __( 'Saeid Scrapper', 'saeid-scrapper' ),
            'manage_options',
            'saeid-scrapper',
            array( __CLASS__, 'render_dashboard_page' ),
            'dashicons-filter'
        );

        add_submenu_page(
            'saeid-scrapper',
            __( 'Dashboard', 'saeid-scrapper' ),
            __( 'Dashboard', 'saeid-scrapper' ),
            'manage_options',
            'saeid-scrapper',
            array( __CLASS__, 'render_dashboard_page' )
        );

        add_submenu_page(
            'saeid-scrapper',
            __( 'Bulk URL Import', 'saeid-scrapper' ),
            __( 'Bulk URL Import', 'saeid-scrapper' ),
            'manage_options',
            'saeid-scrapper-bulk-import',
            array( __CLASS__, 'render_bulk_import_page' )
        );

        add_submenu_page(
            'saeid-scrapper',
            __( 'Scraper', 'saeid-scrapper' ),
            __( 'Scraper', 'saeid-scrapper' ),
            'manage_options',
            'saeid-scrapper-scraper',
            array( __CLASS__, 'render_scraper_page' )
        );

        add_submenu_page(
            'saeid-scrapper',
            __( 'همگام‌سازی دسته‌بندی', 'saeid-scrapper' ),
            __( 'همگام‌سازی دسته‌بندی', 'saeid-scrapper' ),
            'manage_options',
            'saeid-scrapper-category-sync',
            array( __CLASS__, 'render_category_sync_page' )
        );

        add_submenu_page(
            'saeid-scrapper',
            __( 'Settings', 'saeid-scrapper' ),
            __( 'Settings', 'saeid-scrapper' ),
            'manage_options',
            'saeid-scrapper-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    /**
     * Enqueues assets for the admin pages if needed.
     */
    public static function admin_enqueue_assets( $hook ) {
        $allowed_hooks = array(
            'toplevel_page_saeid-scrapper',
            'saeid-scrapper_page_saeid-scrapper-bulk-import',
            'saeid-scrapper_page_saeid-scrapper-scraper',
            'saeid-scrapper_page_saeid-scrapper-category-sync',
            'saeid-scrapper_page_saeid-scrapper-settings',
        );

        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'saeid-scrapper-admin',
            plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
            array(),
            self::VERSION
        );

        if ( 'saeid-scrapper_page_saeid-scrapper-scraper' === $hook ) {
            wp_enqueue_script(
                'saeid-scrapper-scraper',
                plugin_dir_url( __FILE__ ) . 'assets/js/scraper-admin.js',
                array( 'jquery' ),
                self::VERSION,
                true
            );

            wp_localize_script(
                'saeid-scrapper-scraper',
                'saeidScrapperScrape',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( self::SCRAPE_NONCE_ACTION ),
                    'strings' => array(
                        'start'            => __( 'شروع اسکرپینگ', 'saeid-scrapper' ),
                        'processing'       => __( 'در حال پردازش...', 'saeid-scrapper' ),
                        'completed'        => __( 'اسکرپینگ کامل شد.', 'saeid-scrapper' ),
                        'noUrls'           => __( 'آدرسی برای پردازش باقی نمانده است.', 'saeid-scrapper' ),
                        'error'            => __( 'خطایی رخ داد: ', 'saeid-scrapper' ),
                        'noProducts'       => __( 'هنوز محصولی ساخته نشده است.', 'saeid-scrapper' ),
                        'noErrors'         => __( 'خطایی ثبت نشده است.', 'saeid-scrapper' ),
                        'remainingTime'    => __( 'زمان باقی‌مانده تخمینی', 'saeid-scrapper' ),
                        'seconds'          => __( 'ثانیه', 'saeid-scrapper' ),
                        'minutes'          => __( 'دقیقه', 'saeid-scrapper' ),
                    ),
                    'overview' => self::get_scraper_overview(),
                )
            );
        }

        if ( 'saeid-scrapper_page_saeid-scrapper-category-sync' === $hook ) {
            wp_enqueue_script(
                'saeid-scrapper-category-sync',
                plugin_dir_url( __FILE__ ) . 'assets/js/category-sync.js',
                array( 'jquery' ),
                self::VERSION,
                true
            );

            wp_localize_script(
                'saeid-scrapper-category-sync',
                'saeidScrapperCategory',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( self::CATEGORY_NONCE_ACTION ),
                    'strings' => array(
                        'start'         => __( 'شروع بررسی دسته‌بندی‌ها', 'saeid-scrapper' ),
                        'processing'    => __( 'در حال بررسی...', 'saeid-scrapper' ),
                        'completed'     => __( 'همه دسته‌بندی‌ها بررسی شد.', 'saeid-scrapper' ),
                        'noItems'       => __( 'موردی برای نمایش وجود ندارد.', 'saeid-scrapper' ),
                        'existingLabel' => __( 'محصول پیدا شد', 'saeid-scrapper' ),
                        'missingLabel'  => __( 'نیازمند ساخت محصول', 'saeid-scrapper' ),
                        'failedLabel'   => __( 'خطا در خواندن دسته‌بندی', 'saeid-scrapper' ),
                        'error'         => __( 'خطا: ', 'saeid-scrapper' ),
                        'remainingTime' => __( 'زمان باقی‌مانده تخمینی', 'saeid-scrapper' ),
                        'seconds'       => __( 'ثانیه', 'saeid-scrapper' ),
                        'minutes'       => __( 'دقیقه', 'saeid-scrapper' ),
                    ),
                    'overview' => self::get_category_overview(),
                )
            );
        }
    }

    /**
     * Handles the bulk import form submission.
     */
    public static function handle_bulk_import() {
        if ( ! isset( $_POST['saeid_scrapper_bulk_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['saeid_scrapper_bulk_nonce'] ) ), self::NONCE_ACTION ) ) {
            wp_die( esc_html__( 'Security check failed.', 'saeid-scrapper' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to perform this action.', 'saeid-scrapper' ) );
        }

        $raw_input = isset( $_POST['saeid_scrapper_bulk_urls'] ) ? wp_unslash( $_POST['saeid_scrapper_bulk_urls'] ) : '';
        $urls      = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_input ) ) );

        $valid_urls   = array();
        $invalid_urls = array();

        foreach ( $urls as $url ) {
            $sanitized = esc_url_raw( $url );
            if ( empty( $sanitized ) || ! filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
                $invalid_urls[] = $url;
                continue;
            }

            $valid_urls[] = $sanitized;
        }

        if ( empty( $valid_urls ) ) {
            add_settings_error( 'saeid_scrapper_bulk_import', 'no_valid_urls', __( 'هیچ آدرس معتبری برای ذخیره وجود ندارد.', 'saeid-scrapper' ), 'error' );
            return;
        }

        global $wpdb;

        $table_name = self::get_table_name();
        $inserted   = 0;
        $duplicates = 0;

        foreach ( $valid_urls as $url ) {
            $hash = sha1( strtolower( $url ) );

            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE url_hash = %s", $hash ) );
            if ( $existing ) {
                $duplicates++;
                continue;
            }

            $result = $wpdb->insert(
                $table_name,
                array(
                    'url'        => $url,
                    'url_hash'   => $hash,
                    'status'     => 'pending',
                    'category_status' => 'pending',
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );

            if ( false !== $result ) {
                $inserted++;
            }
        }

        $message = sprintf(
            /* translators: 1: number of successfully added URLs, 2: number of invalid URLs, 3: number of duplicates */
            __( '%1$d آدرس جدید ذخیره شد. %2$d آدرس نامعتبر بود و %3$d آدرس تکراری نادیده گرفته شد.', 'saeid-scrapper' ),
            $inserted,
            count( $invalid_urls ),
            $duplicates
        );

        add_settings_error( 'saeid_scrapper_bulk_import', 'bulk_import_status', $message, 'updated' );
    }

    /**
     * Renders the dashboard page.
     */
    public static function render_dashboard_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $total_urls = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        $latest_urls = $wpdb->get_results( "SELECT url, created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 5" );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'داشبورد Saeid Scrapper', 'saeid-scrapper' ); ?></h1>
            <p><?php esc_html_e( 'این پلاگین برای مدیریت اهداف اسکرپینگ طراحی شده است.', 'saeid-scrapper' ); ?></p>
            <p><strong><?php esc_html_e( 'تعداد کل آدرس‌های ذخیره شده:', 'saeid-scrapper' ); ?></strong> <?php echo esc_html( $total_urls ); ?></p>
            <h2><?php esc_html_e( 'آخرین آدرس‌های اضافه شده', 'saeid-scrapper' ); ?></h2>
            <ul>
                <?php if ( empty( $latest_urls ) ) : ?>
                    <li><?php esc_html_e( 'هنوز آدرسی ذخیره نشده است.', 'saeid-scrapper' ); ?></li>
                <?php else : ?>
                    <?php foreach ( $latest_urls as $entry ) : ?>
                        <li>
                            <a href="<?php echo esc_url( $entry->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $entry->url ); ?></a>
                            <em><?php echo esc_html( $entry->created_at ); ?></em>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Renders the bulk import page.
     */
    public static function render_bulk_import_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        settings_errors( 'saeid_scrapper_bulk_import' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'وارد کردن گروهی آدرس‌ها', 'saeid-scrapper' ); ?></h1>
            <p><?php esc_html_e( 'آدرس‌های سایت مقصد را خط به خط وارد کنید. آدرس‌های تکراری ذخیره نخواهند شد.', 'saeid-scrapper' ); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field( self::NONCE_ACTION, 'saeid_scrapper_bulk_nonce' ); ?>
                <textarea name="saeid_scrapper_bulk_urls" rows="10" cols="60" class="large-text code" placeholder="https://example.com\nhttps://example.org"></textarea>
                <?php submit_button( __( 'ذخیره آدرس‌ها', 'saeid-scrapper' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the scraping dashboard page.
     */
    public static function render_scraper_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $overview = self::get_scraper_overview();
        ?>
        <div class="wrap saeid-scrapper-wrap">
            <h1><?php esc_html_e( 'مدیریت اسکرپینگ محصولات', 'saeid-scrapper' ); ?></h1>
            <p><?php esc_html_e( 'در این بخش می‌توانید آدرس‌های ذخیره شده را اسکرپ کرده و محصولات ووکامرس را به صورت خودکار ایجاد کنید.', 'saeid-scrapper' ); ?></p>

            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'برای ایجاد محصولات جدید لازم است افزونه ووکامرس فعال باشد.', 'saeid-scrapper' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="saeid-scrapper-progress">
                <div class="saeid-scrapper-progress__bar" id="saeid-scrapper-progress-bar"></div>
            </div>
            <div class="saeid-scrapper-progress__meta">
                <span id="saeid-scrapper-progress-label">0%</span>
                <span id="saeid-scrapper-time-remaining"></span>
            </div>

            <p>
                <button id="saeid-scrapper-start" class="button button-primary" <?php disabled( ! class_exists( 'WooCommerce' ) ); ?>>
                    <?php esc_html_e( 'شروع اسکرپینگ', 'saeid-scrapper' ); ?>
                </button>
            </p>

            <div class="saeid-scrapper-columns">
                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'وضعیت کلی', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e( 'کل آدرس‌ها', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-total-count"><?php echo esc_html( $overview['counts']['total'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'در انتظار پردازش', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-pending-count"><?php echo esc_html( $overview['counts']['pending'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'موفق', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-success-count"><?php echo esc_html( $overview['counts']['success'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'ناموفق', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-failed-count"><?php echo esc_html( $overview['counts']['failed'] ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'صف آدرس‌های در انتظار', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'آدرس', 'saeid-scrapper' ); ?></th>
                                <th><?php esc_html_e( 'وضعیت', 'saeid-scrapper' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saeid-scrapper-pending-list">
                            <?php if ( empty( $overview['pending'] ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'صف خالی است.', 'saeid-scrapper' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $overview['pending'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['url'] ); ?></a></td>
                                        <td><?php echo esc_html( $item['label'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'آخرین محصولات ساخته شده', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'آدرس منبع', 'saeid-scrapper' ); ?></th>
                                <th><?php esc_html_e( 'شناسه محصول', 'saeid-scrapper' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saeid-scrapper-completed-list">
                            <?php if ( empty( $overview['completed'] ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'هنوز محصولی ساخته نشده است.', 'saeid-scrapper' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $overview['completed'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['url'] ); ?></a></td>
                                        <td>
                                            <?php if ( ! empty( $item['product_id'] ) && ! empty( $item['edit_link'] ) ) : ?>
                                                <a href="<?php echo esc_url( $item['edit_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['product_id'] ); ?></a>
                                            <?php else : ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'آخرین خطاها', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'آدرس', 'saeid-scrapper' ); ?></th>
                                <th><?php esc_html_e( 'پیام', 'saeid-scrapper' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saeid-scrapper-failed-list">
                            <?php if ( empty( $overview['failed'] ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'خطایی ثبت نشده است.', 'saeid-scrapper' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $overview['failed'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['url'] ); ?></a></td>
                                        <td><?php echo esc_html( $item['last_error'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="saeid-scrapper-log">
                <h2><?php esc_html_e( 'لاگ زنده', 'saeid-scrapper' ); ?></h2>
                <ul id="saeid-scrapper-log"></ul>
            </div>
        </div>
        <?php
    }

    /**
     * Renders the category synchronization page.
     */
    public static function render_category_sync_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $overview       = self::get_category_overview();
        $taxonomy_ready = taxonomy_exists( 'product_cat' );
        ?>
        <div class="wrap saeid-scrapper-wrap">
            <h1><?php esc_html_e( 'همگام‌سازی دسته‌بندی‌ها و جلوگیری از تکرار محصول', 'saeid-scrapper' ); ?></h1>
            <p><?php esc_html_e( 'این بخش ابتدا بررسی می‌کند که هر آدرس قبلاً محصولی در ووکامرس دارد یا خیر و سپس دسته‌بندی‌های صفحه مقصد را خوانده و در سایت شما ایجاد می‌کند.', 'saeid-scrapper' ); ?></p>

            <?php if ( ! $taxonomy_ready ) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'برای مدیریت دسته‌بندی‌ها لازم است افزونه ووکامرس فعال باشد.', 'saeid-scrapper' ); ?></p>
                </div>
            <?php endif; ?>

            <div class="saeid-scrapper-progress">
                <div class="saeid-scrapper-progress__bar" id="saeid-scrapper-category-progress-bar"></div>
            </div>
            <div class="saeid-scrapper-progress__meta">
                <span id="saeid-scrapper-category-progress-label">0%</span>
                <span id="saeid-scrapper-category-time-remaining"></span>
            </div>

            <p>
                <button id="saeid-scrapper-category-start" class="button button-primary" <?php disabled( ! $taxonomy_ready ); ?>>
                    <?php esc_html_e( 'شروع بررسی دسته‌بندی‌ها', 'saeid-scrapper' ); ?>
                </button>
            </p>

            <div class="saeid-scrapper-columns">
                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'وضعیت دسته‌بندی‌ها', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e( 'کل آدرس‌ها', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-category-total"><?php echo esc_html( $overview['counts']['total'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'در انتظار بررسی', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-category-pending"><?php echo esc_html( $overview['counts']['pending'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'دسته‌بندی‌های ایجاد شده', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-category-success"><?php echo esc_html( $overview['counts']['success'] ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'خطا', 'saeid-scrapper' ); ?></th>
                                <td id="saeid-scrapper-category-failed"><?php echo esc_html( $overview['counts']['failed'] ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'محصولات یافت‌شده', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'آدرس منبع', 'saeid-scrapper' ); ?></th>
                                <th><?php esc_html_e( 'شناسه محصول', 'saeid-scrapper' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saeid-scrapper-category-existing">
                            <?php if ( empty( $overview['existing'] ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'موردی برای نمایش وجود ندارد.', 'saeid-scrapper' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $overview['existing'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['url'] ); ?></a></td>
                                        <td>
                                            <?php if ( ! empty( $item['product_id'] ) && ! empty( $item['edit_link'] ) ) : ?>
                                                <a href="<?php echo esc_url( $item['edit_link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['product_id'] ); ?></a>
                                            <?php else : ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'آدرس‌های بدون محصول', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'آدرس', 'saeid-scrapper' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saeid-scrapper-category-missing">
                            <?php if ( empty( $overview['missing'] ) ) : ?>
                                <tr><td><?php esc_html_e( 'موردی برای نمایش وجود ندارد.', 'saeid-scrapper' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $overview['missing'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['url'] ); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="saeid-scrapper-card">
                    <h2><?php esc_html_e( 'خطاهای دسته‌بندی', 'saeid-scrapper' ); ?></h2>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'آدرس', 'saeid-scrapper' ); ?></th>
                                <th><?php esc_html_e( 'پیام', 'saeid-scrapper' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="saeid-scrapper-category-failed-list">
                            <?php if ( empty( $overview['failed'] ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'موردی برای نمایش وجود ندارد.', 'saeid-scrapper' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $overview['failed'] as $item ) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['url'] ); ?></a></td>
                                        <td><?php echo esc_html( $item['message'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="saeid-scrapper-log">
                <h2><?php esc_html_e( 'گزارش زنده', 'saeid-scrapper' ); ?></h2>
                <ul id="saeid-scrapper-category-log"></ul>
            </div>
        </div>
        <?php
    }

    /**
     * Retrieves aggregated overview data for the scraper dashboard.
     *
     * @return array
     */
    protected static function get_scraper_overview() {
        global $wpdb;

        $table_name = self::get_table_name();
        $limit      = 5;

        $counts = array(
            'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ),
            'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status IN ('pending','processing')" ),
            'success' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'" ),
            'failed'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed'" ),
        );

        $pending_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url, status FROM {$table_name} WHERE status IN ('pending','processing') ORDER BY id ASC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $completed_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url, product_id, last_scraped FROM {$table_name} WHERE status = 'success' ORDER BY last_scraped DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $failed_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url, last_error, last_scraped FROM {$table_name} WHERE status = 'failed' ORDER BY last_scraped DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $pending  = array();
        $completed = array();
        $failed   = array();

        foreach ( (array) $pending_rows as $row ) {
            $pending[] = array(
                'id'     => isset( $row['id'] ) ? (int) $row['id'] : 0,
                'url'    => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
                'status' => isset( $row['status'] ) ? sanitize_text_field( $row['status'] ) : '',
                'label'  => isset( $row['status'] ) ? self::get_status_label( $row['status'] ) : '',
            );
        }

        foreach ( (array) $completed_rows as $row ) {
            $product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            $completed[] = array(
                'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
                'url'        => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
                'product_id' => $product_id,
                'edit_link'  => $product_id ? get_edit_post_link( $product_id ) : '',
            );
        }

        foreach ( (array) $failed_rows as $row ) {
            $failed[] = array(
                'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
                'url'        => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
                'last_error' => isset( $row['last_error'] ) ? sanitize_text_field( $row['last_error'] ) : '',
            );
        }

        return array(
            'counts'   => array(
                'total'   => (int) $counts['total'],
                'pending' => (int) $counts['pending'],
                'success' => (int) $counts['success'],
                'failed'  => (int) $counts['failed'],
            ),
            'pending'   => $pending,
            'completed' => $completed,
            'failed'    => $failed,
        );
    }

    /**
     * Retrieves overview data for the category synchronization dashboard.
     *
     * @return array
     */
    protected static function get_category_overview() {
        global $wpdb;

        $table_name = self::get_table_name();

        $counts = array(
            'total'   => 0,
            'pending' => 0,
            'success' => 0,
            'failed'  => 0,
        );

        $totals = $wpdb->get_results( "SELECT category_status, COUNT(*) as total FROM {$table_name} GROUP BY category_status", ARRAY_A );

        if ( $totals ) {
            foreach ( $totals as $row ) {
                $status = isset( $row['category_status'] ) ? sanitize_text_field( $row['category_status'] ) : 'pending';
                $total  = isset( $row['total'] ) ? (int) $row['total'] : 0;

                switch ( $status ) {
                    case 'success':
                        $counts['success'] += $total;
                        break;
                    case 'failed':
                        $counts['failed'] += $total;
                        break;
                    case 'pending':
                    default:
                        $counts['pending'] += $total;
                        break;
                }
            }
        }

        $counts['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

        $existing_rows = $wpdb->get_results(
            "SELECT url, product_id FROM {$table_name} WHERE product_id > 0 ORDER BY COALESCE(category_synced_at, last_scraped) DESC, id DESC LIMIT 10",
            ARRAY_A
        );

        $existing = array();
        foreach ( (array) $existing_rows as $row ) {
            $product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
            $existing[] = array(
                'url'       => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
                'product_id'=> $product_id,
                'edit_link' => $product_id ? get_edit_post_link( $product_id ) : '',
            );
        }

        $missing_rows = $wpdb->get_results(
            "SELECT url FROM {$table_name} WHERE (product_id IS NULL OR product_id = 0) ORDER BY id DESC LIMIT 10",
            ARRAY_A
        );

        $missing = array();
        foreach ( (array) $missing_rows as $row ) {
            $missing[] = array(
                'url' => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
            );
        }

        $failed_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT url, category_error FROM {$table_name} WHERE category_status = %s ORDER BY COALESCE(category_synced_at, last_scraped) DESC, id DESC LIMIT 10",
                'failed'
            ),
            ARRAY_A
        );

        $failed = array();
        foreach ( (array) $failed_rows as $row ) {
            $failed[] = array(
                'url'     => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
                'message' => isset( $row['category_error'] ) ? sanitize_text_field( $row['category_error'] ) : '',
            );
        }

        return array(
            'counts'  => $counts,
            'existing'=> $existing,
            'missing' => $missing,
            'failed'  => $failed,
        );
    }

    /**
     * Processes the next URL for category synchronization via AJAX.
     */
    public static function ajax_sync_next_category() {
        if ( ! check_ajax_referer( self::CATEGORY_NONCE_ACTION, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'اعتبارسنجی انجام نشد.', 'saeid-scrapper' ) ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'saeid-scrapper' ) ), 403 );
        }

        if ( ! taxonomy_exists( 'product_cat' ) ) {
            wp_send_json_error( array( 'message' => __( 'دسته‌بندی محصولات در دسترس نیست. لطفاً ووکامرس را فعال کنید.', 'saeid-scrapper' ) ), 400 );
        }

        global $wpdb;

        $table_name = self::get_table_name();

        $row = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE category_status = 'pending' ORDER BY id ASC LIMIT 1", ARRAY_A );

        if ( empty( $row ) ) {
            $overview = self::get_category_overview();

            wp_send_json_success(
                array(
                    'status'   => 'done',
                    'message'  => __( 'تمام آدرس‌ها از نظر دسته‌بندی بررسی شدند.', 'saeid-scrapper' ),
                    'counts'   => $overview['counts'],
                    'existing' => $overview['existing'],
                    'missing'  => $overview['missing'],
                    'failed'   => $overview['failed'],
                )
            );
        }

        $product_id = isset( $row['product_id'] ) ? (int) $row['product_id'] : 0;
        $slug_hint  = self::derive_slug_from_url( $row['url'] );

        if ( ! $product_id ) {
            $product_id = self::find_product_by_source_url( $row['url'], $slug_hint );
        }

        $scrape = self::scrape_product_data( $row['url'] );

        if ( is_wp_error( $scrape ) ) {
            $error_message = wp_strip_all_tags( $scrape->get_error_message() );

            $wpdb->update(
                $table_name,
                array(
                    'category_status'    => 'failed',
                    'category_error'     => $error_message,
                    'category_synced_at' => current_time( 'mysql' ),
                    'product_id'         => $product_id,
                ),
                array( 'id' => $row['id'] ),
                array( '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );

            $overview = self::get_category_overview();

            wp_send_json_success(
                array(
                    'status'   => 'failed',
                    'message'  => sprintf( __( 'خواندن دسته‌بندی برای %1$s با خطا مواجه شد: %2$s', 'saeid-scrapper' ), esc_html( $row['url'] ), esc_html( $error_message ) ),
                    'counts'   => $overview['counts'],
                    'existing' => $overview['existing'],
                    'missing'  => $overview['missing'],
                    'failed'   => $overview['failed'],
                )
            );
        }

        $categories = isset( $scrape['categories'] ) ? (array) $scrape['categories'] : array();
        $term_ids   = self::ensure_product_categories( $categories );

        if ( $product_id && ! empty( $term_ids ) ) {
            wp_set_object_terms( $product_id, $term_ids, 'product_cat', false );
        }

        $update_data = array(
            'category_status'    => 'success',
            'category_error'     => '',
            'category_synced_at' => current_time( 'mysql' ),
            'product_id'         => $product_id,
        );

        $update_format = array( '%s', '%s', '%s', '%d' );

        if ( $product_id ) {
            $update_data['status'] = 'success';
            $update_format[]       = '%s';
        }

        $wpdb->update(
            $table_name,
            $update_data,
            array( 'id' => $row['id'] ),
            $update_format,
            array( '%d' )
        );

        $overview = self::get_category_overview();

        $category_names = array();

        foreach ( (array) $categories as $category ) {
            if ( is_array( $category ) && isset( $category['name'] ) ) {
                $name = sanitize_text_field( $category['name'] );
            } else {
                $name = is_string( $category ) ? sanitize_text_field( $category ) : '';
            }

            if ( '' === $name ) {
                continue;
            }

            $category_names[] = $name;
        }

        $label = ! empty( $category_names ) ? implode( '، ', $category_names ) : __( 'دسته‌بندی معتبری یافت نشد.', 'saeid-scrapper' );

        wp_send_json_success(
            array(
                'status'   => 'success',
                'message'  => sprintf( __( 'دسته‌بندی‌های %1$s همگام شد: %2$s', 'saeid-scrapper' ), esc_html( $row['url'] ), esc_html( $label ) ),
                'counts'   => $overview['counts'],
                'existing' => $overview['existing'],
                'missing'  => $overview['missing'],
                'failed'   => $overview['failed'],
            )
        );
    }

    /**
     * Processes the next URL in the queue via AJAX.
     */
    public static function ajax_process_next_url() {
        if ( ! check_ajax_referer( self::SCRAPE_NONCE_ACTION, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'اعتبارسنجی انجام نشد.', 'saeid-scrapper' ) ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'saeid-scrapper' ) ), 403 );
        }

        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'برای اسکرپینگ لازم است ووکامرس فعال باشد.', 'saeid-scrapper' ) ), 400 );
        }

        global $wpdb;

        $table_name = self::get_table_name();

        $row = $wpdb->get_row( "SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY id ASC LIMIT 1", ARRAY_A );

        if ( empty( $row ) ) {
            $overview = self::get_scraper_overview();

            wp_send_json_success(
                array(
                    'status'    => 'done',
                    'message'   => __( 'تمام آدرس‌ها پردازش شدند.', 'saeid-scrapper' ),
                    'counts'    => $overview['counts'],
                    'pending'   => $overview['pending'],
                    'completed' => $overview['completed'],
                    'failed'    => $overview['failed'],
                )
            );
        }

        $updated = $wpdb->update(
            $table_name,
            array( 'status' => 'processing' ),
            array( 'id' => $row['id'], 'status' => 'pending' ),
            array( '%s' ),
            array( '%d', '%s' )
        );

        if ( ! $updated ) {
            $overview = self::get_scraper_overview();

            wp_send_json_success(
                array(
                    'status'    => 'skipped',
                    'message'   => __( 'در انتظار آزاد شدن صف...', 'saeid-scrapper' ),
                    'counts'    => $overview['counts'],
                    'pending'   => $overview['pending'],
                    'completed' => $overview['completed'],
                    'failed'    => $overview['failed'],
                )
            );
        }

        $slug_hint          = self::derive_slug_from_url( $row['url'] );
        $existing_product_id = self::find_product_by_source_url( $row['url'], $slug_hint );

        if ( $existing_product_id ) {
            $wpdb->update(
                $table_name,
                array(
                    'status'       => 'success',
                    'product_id'   => absint( $existing_product_id ),
                    'last_error'   => '',
                    'last_scraped' => current_time( 'mysql' ),
                ),
                array( 'id' => $row['id'] ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            $overview = self::get_scraper_overview();

            wp_send_json_success(
                array(
                    'status'    => 'skipped',
                    'message'   => sprintf( __( 'محصول مرتبط با آدرس %1$s قبلاً با شناسه %2$d وجود دارد.', 'saeid-scrapper' ), esc_html( $row['url'] ), absint( $existing_product_id ) ),
                    'counts'    => $overview['counts'],
                    'pending'   => $overview['pending'],
                    'completed' => $overview['completed'],
                    'failed'    => $overview['failed'],
                )
            );
        }

        $scrape = self::scrape_product_data( $row['url'] );

        if ( is_wp_error( $scrape ) ) {
            $error_message = wp_strip_all_tags( $scrape->get_error_message() );

            $wpdb->update(
                $table_name,
                array(
                    'status'       => 'failed',
                    'product_id'   => 0,
                    'last_error'   => $error_message,
                    'last_scraped' => current_time( 'mysql' ),
                ),
                array( 'id' => $row['id'] ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            $overview = self::get_scraper_overview();

            wp_send_json_success(
                array(
                    'status'    => 'failed',
                    'message'   => sprintf( __( 'آدرس %1$s با خطا متوقف شد: %2$s', 'saeid-scrapper' ), esc_html( $row['url'] ), esc_html( $error_message ) ),
                    'counts'    => $overview['counts'],
                    'pending'   => $overview['pending'],
                    'completed' => $overview['completed'],
                    'failed'    => $overview['failed'],
                )
            );
        }

        $scrape['source_url'] = $row['url'];
        $product_id           = self::create_product_from_scrape( $scrape );

        if ( is_wp_error( $product_id ) ) {
            $error_message = wp_strip_all_tags( $product_id->get_error_message() );

            $wpdb->update(
                $table_name,
                array(
                    'status'       => 'failed',
                    'product_id'   => 0,
                    'last_error'   => $error_message,
                    'last_scraped' => current_time( 'mysql' ),
                ),
                array( 'id' => $row['id'] ),
                array( '%s', '%d', '%s', '%s' ),
                array( '%d' )
            );

            $overview = self::get_scraper_overview();

            wp_send_json_success(
                array(
                    'status'    => 'failed',
                    'message'   => sprintf( __( 'ساخت محصول برای %1$s ناموفق بود: %2$s', 'saeid-scrapper' ), esc_html( $row['url'] ), esc_html( $error_message ) ),
                    'counts'    => $overview['counts'],
                    'pending'   => $overview['pending'],
                    'completed' => $overview['completed'],
                    'failed'    => $overview['failed'],
                )
            );
        }

        $wpdb->update(
            $table_name,
            array(
                'status'       => 'success',
                'product_id'   => absint( $product_id ),
                'last_error'   => '',
                'last_scraped' => current_time( 'mysql' ),
            ),
            array( 'id' => $row['id'] ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%d' )
        );

        $overview = self::get_scraper_overview();

        $message = sprintf(
            __( 'محصول جدیدی با شناسه %1$d از آدرس %2$s ایجاد شد.', 'saeid-scrapper' ),
            absint( $product_id ),
            esc_html( $row['url'] )
        );

        $edit_link = get_edit_post_link( $product_id );

        if ( $edit_link ) {
            $message .= ' <a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'ویرایش', 'saeid-scrapper' ) . '</a>';
        }

        wp_send_json_success(
            array(
                'status'    => 'processed',
                'message'   => $message,
                'counts'    => $overview['counts'],
                'pending'   => $overview['pending'],
                'completed' => $overview['completed'],
                'failed'    => $overview['failed'],
            )
        );
    }

    /**
     * Generates a slug from a URL.
     *
     * @param string $url Source URL.
     * @return string
     */
    protected static function derive_slug_from_url( $url ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );

        if ( empty( $path ) ) {
            return '';
        }

        $slug = basename( untrailingslashit( $path ) );

        return $slug ? sanitize_title( $slug ) : '';
    }

    /**
     * Finds an existing WooCommerce product by stored source URL metadata or slug.
     *
     * @param string $url  Source URL.
     * @param string $slug Optional slug fallback.
     * @return int
     */
    protected static function find_product_by_source_url( $url, $slug = '' ) {
        $url = esc_url_raw( $url );

        if ( empty( $url ) ) {
            return 0;
        }

        $existing = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
                'meta_key'       => '_saeid_scrapper_source_url',
                'meta_value'     => $url,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }

        if ( ! empty( $slug ) ) {
            $product = get_page_by_path( $slug, OBJECT, 'product' );

            if ( $product instanceof WP_Post ) {
                return (int) $product->ID;
            }
        }

        return 0;
    }

    /**
     * Scrapes the remote HTML content and extracts product fields.
     *
     * @param string $url Target URL.
     * @return array|WP_Error
     */
    protected static function scrape_product_data( $url ) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout'   => 30,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'headers'   => array(
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'http_request_failed', sprintf( __( 'دریافت محتوا از %1$s با خطا مواجه شد: %2$s', 'saeid-scrapper' ), esc_url_raw( $url ), $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== (int) $code ) {
            return new WP_Error( 'http_status_error', sprintf( __( 'پاسخ نامعتبر %1$d برای %2$s دریافت شد.', 'saeid-scrapper' ), (int) $code, esc_url_raw( $url ) ) );
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            return new WP_Error( 'empty_body', sprintf( __( 'محتوای معتبری برای %s یافت نشد.', 'saeid-scrapper' ), esc_url_raw( $url ) ) );
        }

        $parsed = self::parse_product_html( $body, $url );

        if ( empty( $parsed['title'] ) ) {
            return new WP_Error( 'missing_title', __( 'عنوان محصول در صفحه مقصد پیدا نشد.', 'saeid-scrapper' ) );
        }

        if ( empty( $parsed['content'] ) ) {
            $parsed['content'] = '<p>' . esc_html__( 'محتوایی برای این محصول یافت نشد.', 'saeid-scrapper' ) . '</p>';
        }

        $parsed['source_url'] = esc_url_raw( $url );

        return $parsed;
    }

    /**
     * Parses HTML and extracts product data.
     *
     * @param string $html Raw HTML.
     * @param string $url  Source URL.
     * @return array
     */
    protected static function parse_product_html( $html, $url ) {
        $data = array(
            'title'      => '',
            'content'    => '',
            'excerpt'    => '',
            'slug'       => '',
            'attributes' => array(),
            'categories' => array(),
        );

        $internal_errors = libxml_use_internal_errors( true );
        $document        = new DOMDocument();

        $encoded_html = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) : $html;

        if ( ! @$document->loadHTML( $encoded_html ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            libxml_clear_errors();
            libxml_use_internal_errors( $internal_errors );

            return $data;
        }

        libxml_clear_errors();
        libxml_use_internal_errors( $internal_errors );

        $xpath = new DOMXPath( $document );

        $title = self::xpath_first_value( $xpath, "//meta[@property='og:title']/@content" );

        if ( empty( $title ) ) {
            $title = self::xpath_first_value( $xpath, "//meta[@name='twitter:title']/@content" );
        }

        if ( empty( $title ) ) {
            $title = self::xpath_first_value( $xpath, '//h1' );
        }

        if ( empty( $title ) ) {
            $title = self::xpath_first_value( $xpath, '//title' );
        }

        $data['title'] = is_string( $title ) ? trim( $title ) : '';

        $og_url = self::xpath_first_value( $xpath, "//meta[@property='og:url']/@content" );

        if ( ! empty( $og_url ) ) {
            $data['slug'] = basename( untrailingslashit( $og_url ) );
        }

        if ( empty( $data['slug'] ) ) {
            $path = wp_parse_url( $url, PHP_URL_PATH );
            if ( $path ) {
                $data['slug'] = basename( untrailingslashit( $path ) );
            }
        }

        $content_html = self::xpath_first_html( $xpath, "//div[contains(@class,'woocommerce-product-details__short-description')]" );

        if ( empty( $content_html ) ) {
            $content_html = self::xpath_first_html( $xpath, "//div[contains(@class,'summary')]" );
        }

        if ( empty( $content_html ) ) {
            $content_html = self::xpath_first_html( $xpath, "//div[@id='tab-description' or contains(@id,'tab-description')]" );
        }

        if ( empty( $content_html ) ) {
            $content_html = self::xpath_first_html( $xpath, "//div[contains(@class,'woocommerce-Tabs-panel') and (contains(@class,'description') or contains(@id,'description'))]" );
        }

        if ( empty( $content_html ) ) {
            $content_html = self::xpath_first_html( $xpath, "//div[contains(@class,'product')]//div[contains(@class,'description')]" );
        }

        if ( empty( $content_html ) ) {
            $content_html = self::xpath_first_html( $xpath, "//div[contains(@class,'elementor-widget-container')]//div[contains(@class,'elementor-text-editor')]" );
        }

        if ( empty( $content_html ) ) {
            $paragraphs = $xpath->query( '//p' );
            $pieces     = array();

            if ( $paragraphs instanceof DOMNodeList ) {
                foreach ( $paragraphs as $paragraph ) {
                    $text = trim( $paragraph->textContent );
                    if ( '' === $text ) {
                        continue;
                    }

                    $pieces[] = esc_html( $text );

                    if ( count( $pieces ) >= 3 ) {
                        break;
                    }
                }
            }

            if ( ! empty( $pieces ) ) {
                $content_html = '<p>' . implode( '</p><p>', $pieces ) . '</p>';
            }
        }

        $data['content'] = $content_html ? wp_kses_post( $content_html ) : '';
        $data['excerpt'] = wp_trim_words( wp_strip_all_tags( $data['content'] ), 55, '…' );

        if ( empty( $data['excerpt'] ) ) {
            $meta_description = self::xpath_first_value( $xpath, "//meta[@name='description']/@content" );
            if ( ! empty( $meta_description ) ) {
                $data['excerpt'] = sanitize_text_field( $meta_description );
            }
        }

        $data['attributes'] = self::parse_product_attributes( $xpath );
        $data['categories'] = self::parse_product_categories( $xpath );

        $json_ld_product = self::extract_json_ld_product_data( $document );

        if ( ! empty( $json_ld_product ) ) {
            if ( empty( $data['title'] ) && ! empty( $json_ld_product['name'] ) ) {
                $data['title'] = sanitize_text_field( $json_ld_product['name'] );
            }

            if ( empty( $data['slug'] ) && ! empty( $json_ld_product['url'] ) ) {
                $data['slug'] = basename( untrailingslashit( $json_ld_product['url'] ) );
            }

            if ( ! empty( $json_ld_product['description'] ) && empty( $data['content'] ) ) {
                $data['content'] = wp_kses_post( wpautop( $json_ld_product['description'] ) );
            }

            if ( ! empty( $json_ld_product['description'] ) && empty( $data['excerpt'] ) ) {
                $data['excerpt'] = wp_trim_words( wp_strip_all_tags( $json_ld_product['description'] ), 55, '…' );
            }

            $attributes_from_json = array();
            $json_categories      = array();

            if ( ! empty( $json_ld_product['sku'] ) ) {
                $attributes_from_json[] = array(
                    'name'  => __( 'SKU', 'saeid-scrapper' ),
                    'value' => sanitize_text_field( $json_ld_product['sku'] ),
                );
            }

            if ( ! empty( $json_ld_product['mpn'] ) ) {
                $attributes_from_json[] = array(
                    'name'  => __( 'MPN', 'saeid-scrapper' ),
                    'value' => sanitize_text_field( $json_ld_product['mpn'] ),
                );
            }

            if ( ! empty( $json_ld_product['additionalProperty'] ) && is_array( $json_ld_product['additionalProperty'] ) ) {
                foreach ( $json_ld_product['additionalProperty'] as $property ) {
                    if ( empty( $property['name'] ) || empty( $property['value'] ) ) {
                        continue;
                    }

                    $attributes_from_json[] = array(
                        'name'  => sanitize_text_field( $property['name'] ),
                        'value' => wp_kses_post( $property['value'] ),
                    );
                }
            }

            if ( ! empty( $json_ld_product['brand'] ) ) {
                if ( is_array( $json_ld_product['brand'] ) && ! empty( $json_ld_product['brand']['name'] ) ) {
                    $brand_value = sanitize_text_field( $json_ld_product['brand']['name'] );
                } elseif ( is_string( $json_ld_product['brand'] ) ) {
                    $brand_value = sanitize_text_field( $json_ld_product['brand'] );
                } else {
                    $brand_value = '';
                }

                if ( ! empty( $brand_value ) ) {
                    $attributes_from_json[] = array(
                        'name'  => __( 'برند', 'saeid-scrapper' ),
                        'value' => $brand_value,
                    );
                }
            }

            if ( ! empty( $attributes_from_json ) ) {
                $data['attributes'] = self::merge_product_attributes( $data['attributes'], $attributes_from_json );
            }

            if ( ! empty( $json_ld_product['category'] ) ) {
                if ( is_array( $json_ld_product['category'] ) ) {
                    foreach ( $json_ld_product['category'] as $category_item ) {
                        if ( is_string( $category_item ) ) {
                            $json_categories = array_merge( $json_categories, explode( '>', $category_item ) );
                        }
                    }
                } elseif ( is_string( $json_ld_product['category'] ) ) {
                    $json_categories = array_merge( $json_categories, explode( '>', $json_ld_product['category'] ) );
                }
            }

            if ( ! empty( $json_ld_product['itemListElement'] ) && is_array( $json_ld_product['itemListElement'] ) ) {
                foreach ( $json_ld_product['itemListElement'] as $element ) {
                    if ( is_array( $element ) ) {
                        if ( ! empty( $element['name'] ) && is_string( $element['name'] ) ) {
                            $json_categories[] = $element['name'];
                        } elseif ( isset( $element['item'] ) && is_array( $element['item'] ) && ! empty( $element['item']['name'] ) ) {
                            $json_categories[] = $element['item']['name'];
                        }
                    }
                }
            }

            if ( ! empty( $json_categories ) ) {
                $data['categories'] = self::normalize_categories( array_merge( $data['categories'], $json_categories ) );
            }
        }

        $data['attributes'] = self::merge_product_attributes( $data['attributes'], self::parse_additional_tables( $xpath ) );
        $data['categories'] = self::normalize_categories( $data['categories'] );

        return $data;
    }

    /**
     * Parses product categories from various DOM structures.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @return array
     */
    protected static function parse_product_categories( DOMXPath $xpath ) {
        $categories = array();

        $queries = array(
            "//nav[contains(@class,'woocommerce-breadcrumb') or contains(@class,'breadcrumb') or contains(@class,'breadcrumbs')]//a",
            "//ul[contains(@class,'breadcrumb')]//a",
            "//div[contains(@class,'product_meta')]//span[contains(@class,'posted_in')]//a",
        );

        foreach ( $queries as $query ) {
            $nodes = $xpath->query( $query );

            if ( $nodes instanceof DOMNodeList && $nodes->length ) {
                foreach ( $nodes as $node ) {
                    $text = trim( $node->textContent );
                    if ( '' === $text ) {
                        continue;
                    }

                    $href = '';
                    if ( $node instanceof DOMElement && $node->hasAttribute( 'href' ) ) {
                        $href = $node->getAttribute( 'href' );
                    }

                    $slug = self::extract_category_slug_from_href( $href );

                    if ( '' !== $slug ) {
                        $categories[] = array(
                            'name' => $text,
                            'slug' => $slug,
                        );
                    } else {
                        $categories[] = $text;
                    }
                }
            }
        }

        return self::normalize_categories( $categories );
    }

    /**
     * Attempts to derive a product category slug from a breadcrumb hyperlink.
     *
     * @param string $href Anchor href attribute.
     * @return string
     */
    protected static function extract_category_slug_from_href( $href ) {
        if ( empty( $href ) ) {
            return '';
        }

        $path = wp_parse_url( $href, PHP_URL_PATH );

        if ( empty( $path ) ) {
            return '';
        }

        $segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );

        if ( empty( $segments ) ) {
            return '';
        }

        $index = array_search( 'product-category', $segments, true );

        if ( false !== $index ) {
            $segments = array_slice( $segments, $index + 1 );
        }

        if ( empty( $segments ) ) {
            return '';
        }

        $slug = end( $segments );

        return $slug ? sanitize_title( $slug ) : '';
    }

    /**
     * Creates a WooCommerce product from scraped data.
     *
     * @param array $data Parsed data.
     * @return int|WP_Error Product ID on success, WP_Error otherwise.
     */
    protected static function create_product_from_scrape( $data ) {
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing_title', __( 'عنوان محصول برای ایجاد پست الزامی است.', 'saeid-scrapper' ) );
        }

        $slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';

        if ( empty( $slug ) ) {
            $slug = sanitize_title( $data['title'] );
        }

        $postarr = array(
            'post_title'   => wp_strip_all_tags( $data['title'] ),
            'post_content' => isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '',
            'post_excerpt' => isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '',
            'post_status'  => 'publish',
            'post_type'    => 'product',
        );

        if ( ! empty( $slug ) ) {
            $postarr['post_name'] = wp_unique_post_slug( $slug, 0, 'publish', 'product', 0 );
        }

        $product_id = wp_insert_post( $postarr, true );

        if ( is_wp_error( $product_id ) ) {
            return $product_id;
        }

        wp_set_object_terms( $product_id, 'simple', 'product_type', false );

        if ( ! empty( $data['attributes'] ) ) {
            $position           = 0;
            $product_attributes = array();

            foreach ( $data['attributes'] as $attribute ) {
                if ( empty( $attribute['name'] ) || empty( $attribute['value'] ) ) {
                    continue;
                }

                $name      = sanitize_text_field( $attribute['name'] );
                $value_raw = wp_kses_post( $attribute['value'] );
                $slug_key  = sanitize_title( $name );

                $product_attributes[ $slug_key ] = array(
                    'name'         => $name,
                    'value'        => $value_raw,
                    'position'     => $position,
                    'is_visible'   => 1,
                    'is_variation' => 0,
                    'is_taxonomy'  => 0,
                );

                update_post_meta( $product_id, 'attribute_' . $slug_key, wp_strip_all_tags( $value_raw ) );

                $position++;
            }

            if ( ! empty( $product_attributes ) ) {
                update_post_meta( $product_id, '_product_attributes', $product_attributes );
            }
        }

        if ( ! empty( $data['source_url'] ) ) {
            update_post_meta( $product_id, '_saeid_scrapper_source_url', esc_url_raw( $data['source_url'] ) );
        }

        if ( ! empty( $data['categories'] ) ) {
            $term_ids = self::ensure_product_categories( $data['categories'] );
            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $product_id, $term_ids, 'product_cat', false );
            }
        }

        return $product_id;
    }

    /**
     * Normalizes category labels by trimming, splitting and removing duplicates.
     *
     * @param array $categories Raw categories.
     * @return array
     */
    protected static function normalize_categories( $categories ) {
        $normalized = array();
        $seen       = array();

        foreach ( (array) $categories as $category ) {
            $pieces = array();

            if ( is_array( $category ) && isset( $category['name'] ) ) {
                $name = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( $category['name'] ) ) );
                $slug = isset( $category['slug'] ) ? sanitize_title( $category['slug'] ) : '';

                if ( false !== strpos( $name, '>' ) || false !== strpos( $name, '›' ) || false !== strpos( $name, '»' ) || false !== strpos( $name, '/' ) || false !== strpos( $name, '|' ) ) {
                    $category = $name;
                } elseif ( '' !== $name ) {
                    if ( '' === $slug ) {
                        $slug = sanitize_title( $name );
                    }

                    $pieces[] = array(
                        'name' => $name,
                        'slug' => $slug,
                    );
                }
            }

            if ( empty( $pieces ) && ( is_string( $category ) || is_numeric( $category ) ) ) {
                $parts = preg_split( '/[>›»\\\/|]+/', (string) $category );

                foreach ( (array) $parts as $part ) {
                    $label = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( $part ) ) );

                    if ( '' === $label ) {
                        continue;
                    }

                    $pieces[] = array(
                        'name' => $label,
                        'slug' => sanitize_title( $label ),
                    );
                }
            }

            foreach ( $pieces as $piece ) {
                $name = isset( $piece['name'] ) ? $piece['name'] : '';
                if ( '' === $name ) {
                    continue;
                }

                $slug = isset( $piece['slug'] ) ? sanitize_title( $piece['slug'] ) : '';
                if ( '' === $slug ) {
                    $slug = sanitize_title( $name );
                }

                $lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );

                $ignored = array( 'خانه', 'home', 'محصولات', 'products' );
                if ( in_array( $lower, $ignored, true ) ) {
                    continue;
                }

                $key = $slug ? $slug : $lower;

                if ( isset( $seen[ $key ] ) ) {
                    continue;
                }

                $seen[ $key ] = true;
                $normalized[] = array(
                    'name' => $name,
                    'slug' => $slug,
                );
            }
        }

        return $normalized;
    }

    /**
     * Ensures product_cat terms exist for provided categories and returns their IDs.
     *
     * @param array $categories Category labels.
     * @return array
     */
    protected static function ensure_product_categories( array $categories ) {
        if ( empty( $categories ) || ! taxonomy_exists( 'product_cat' ) ) {
            return array();
        }

        $normalized = self::normalize_categories( $categories );

        if ( empty( $normalized ) ) {
            return array();
        }

        $term_ids = array();
        $parent   = 0;

        foreach ( $normalized as $category ) {
            $name = '';
            $slug = '';

            if ( is_array( $category ) ) {
                $name = isset( $category['name'] ) ? sanitize_text_field( $category['name'] ) : '';
                $slug = isset( $category['slug'] ) ? sanitize_title( $category['slug'] ) : '';
            } else {
                $name = sanitize_text_field( $category );
            }

            if ( '' === $name ) {
                continue;
            }

            if ( '' === $slug ) {
                $slug = sanitize_title( $name );
            }

            if ( '' === $slug ) {
                continue;
            }

            $term = term_exists( $slug, 'product_cat', $parent );
            if ( ! $term ) {
                $term = term_exists( $name, 'product_cat', $parent );
            }

            if ( ! $term ) {
                $term = wp_insert_term(
                    $name,
                    'product_cat',
                    array(
                        'slug'   => $slug,
                        'parent' => $parent,
                    )
                );
            }

            if ( is_wp_error( $term ) ) {
                continue;
            }

            $term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

            if ( $term_id <= 0 ) {
                continue;
            }

            $term_ids[] = $term_id;
            $parent     = $term_id;
        }

        return array_values( array_unique( array_map( 'absint', $term_ids ) ) );
    }

    /**
     * Returns a translated label for a stored status.
     *
     * @param string $status Status key.
     * @return string
     */
    protected static function get_status_label( $status ) {
        switch ( $status ) {
            case 'pending':
                return __( 'در انتظار', 'saeid-scrapper' );
            case 'processing':
                return __( 'در حال پردازش', 'saeid-scrapper' );
            case 'success':
                return __( 'موفق', 'saeid-scrapper' );
            case 'failed':
                return __( 'ناموفق', 'saeid-scrapper' );
            default:
                return sanitize_text_field( $status );
        }
    }

    /**
     * Parses product attributes from DOM.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @return array
     */
    protected static function parse_product_attributes( DOMXPath $xpath ) {
        $attributes = array();

        $rows = $xpath->query( "//table[contains(@class,'woocommerce-product-attributes')]//tr" );

        if ( $rows instanceof DOMNodeList && $rows->length ) {
            foreach ( $rows as $row ) {
                $label_node = $xpath->query( './/th|.//td[contains(@class,"woocommerce-product-attributes-item__label")]|.//span[contains(@class,"label")]' , $row );
                $value_node = $xpath->query( './/td[contains(@class,"woocommerce-product-attributes-item__value")]|.//td[not(self::th)]|.//span[contains(@class,"value")]' , $row );

                $label = '';
                if ( $label_node instanceof DOMNodeList && $label_node->length ) {
                    $label = trim( $label_node->item( 0 )->textContent );
                }

                $value = '';
                if ( $value_node instanceof DOMNodeList && $value_node->length ) {
                    $value = self::domnode_inner_html( $value_node->item( 0 ) );
                }

                if ( '' === $label || '' === $value ) {
                    continue;
                }

                $attributes[] = array(
                    'name'  => $label,
                    'value' => $value,
                );
            }
        }

        if ( empty( $attributes ) ) {
            $items = $xpath->query( "//dl[contains(@class,'woocommerce-product-attributes')]//div[contains(@class,'woocommerce-product-attributes-item')]" );

            if ( $items instanceof DOMNodeList && $items->length ) {
                foreach ( $items as $item ) {
                    $label_node = $xpath->query( './/dt', $item );
                    $value_node = $xpath->query( './/dd', $item );

                    $label = ( $label_node instanceof DOMNodeList && $label_node->length ) ? trim( $label_node->item( 0 )->textContent ) : '';
                    $value = ( $value_node instanceof DOMNodeList && $value_node->length ) ? self::domnode_inner_html( $value_node->item( 0 ) ) : '';

                    if ( '' === $label || '' === $value ) {
                        continue;
                    }

                    $attributes[] = array(
                        'name'  => $label,
                        'value' => $value,
                    );
                }
            }
        }

        if ( empty( $attributes ) ) {
            $list_items = $xpath->query( "//ul[contains(@class,'product-attributes')]//li" );

            if ( $list_items instanceof DOMNodeList && $list_items->length ) {
                foreach ( $list_items as $item ) {
                    $text = trim( $item->textContent );
                    if ( empty( $text ) || false === strpos( $text, ':' ) ) {
                        continue;
                    }

                    list( $label, $value ) = array_map( 'trim', explode( ':', $text, 2 ) );

                    if ( '' === $label || '' === $value ) {
                        continue;
                    }

                    $attributes[] = array(
                        'name'  => $label,
                        'value' => esc_html( $value ),
                    );
                }
            }
        }

        return $attributes;
    }

    /**
     * Parses additional specification tables that do not use standard WooCommerce markup.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @return array
     */
    protected static function parse_additional_tables( DOMXPath $xpath ) {
        $attributes = array();

        $tables = $xpath->query( "//div[@id='tab-additional_information']//table//tr | //table[contains(@class,'tablepress')]//tr" );

        if ( $tables instanceof DOMNodeList && $tables->length ) {
            foreach ( $tables as $row ) {
                $cells = $xpath->query( './/th|.//td', $row );

                if ( ! ( $cells instanceof DOMNodeList ) || $cells->length < 2 ) {
                    continue;
                }

                $label = trim( $cells->item( 0 )->textContent );
                $value = '';

                $value_node = $cells->item( 1 );
                if ( $value_node instanceof DOMNode ) {
                    $value = self::domnode_inner_html( $value_node );
                }

                if ( '' === $label || '' === $value ) {
                    continue;
                }

                $attributes[] = array(
                    'name'  => $label,
                    'value' => $value,
                );
            }
        }

        return $attributes;
    }

    /**
     * Merges multiple attribute sources while preventing duplicate labels.
     *
     * @param array $existing Existing attribute list.
     * @param array $new      New attribute list.
     * @return array
     */
    protected static function merge_product_attributes( array $existing, array $new ) {
        if ( empty( $new ) ) {
            return $existing;
        }

        $merged       = array();
        $label_lookup = array();

        foreach ( $existing as $attribute ) {
            if ( empty( $attribute['name'] ) || empty( $attribute['value'] ) ) {
                continue;
            }

            $key                 = sanitize_title( $attribute['name'] );
            $label_lookup[ $key ] = true;
            $merged[]            = $attribute;
        }

        foreach ( $new as $attribute ) {
            if ( empty( $attribute['name'] ) || empty( $attribute['value'] ) ) {
                continue;
            }

            $key = sanitize_title( $attribute['name'] );

            if ( isset( $label_lookup[ $key ] ) ) {
                continue;
            }

            $label_lookup[ $key ] = true;
            $merged[]            = $attribute;
        }

        return $merged;
    }

    /**
     * Attempts to extract product details from JSON-LD schema markup.
     *
     * @param DOMDocument $document DOM document.
     * @return array
     */
    protected static function extract_json_ld_product_data( DOMDocument $document ) {
        $xpath   = new DOMXPath( $document );
        $scripts = $xpath->query( "//script[@type='application/ld+json']" );

        if ( ! ( $scripts instanceof DOMNodeList ) || ! $scripts->length ) {
            return array();
        }

        foreach ( $scripts as $script ) {
            $json = trim( $script->textContent );

            if ( '' === $json ) {
                continue;
            }

            $decoded = json_decode( $json, true );

            if ( null === $decoded ) {
                continue;
            }

            $product = self::find_product_in_graph( $decoded );

            if ( ! empty( $product ) ) {
                return $product;
            }
        }

        return array();
    }

    /**
     * Recursively searches JSON-LD graph for a Product node.
     *
     * @param mixed $node JSON-LD node.
     * @return array
     */
    protected static function find_product_in_graph( $node ) {
        if ( is_array( $node ) ) {
            if ( isset( $node['@type'] ) ) {
                $types = is_array( $node['@type'] ) ? $node['@type'] : array( $node['@type'] );

                foreach ( $types as $type ) {
                    if ( is_string( $type ) && 'product' === strtolower( $type ) ) {
                        return $node;
                    }
                }
            }

            foreach ( $node as $value ) {
                $found = self::find_product_in_graph( $value );
                if ( ! empty( $found ) ) {
                    return $found;
                }
            }
        }

        return array();
    }

    /**
     * Helper: returns the first matching XPath text content.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @param string   $query XPath query.
     * @return string
     */
    protected static function xpath_first_value( DOMXPath $xpath, $query ) {
        $nodes = $xpath->query( $query );

        if ( $nodes instanceof DOMNodeList && $nodes->length ) {
            $value = $nodes->item( 0 )->textContent;

            return is_string( $value ) ? trim( $value ) : '';
        }

        return '';
    }

    /**
     * Helper: returns the inner HTML for the first matching XPath node.
     *
     * @param DOMXPath $xpath DOMXPath instance.
     * @param string   $query XPath query.
     * @return string
     */
    protected static function xpath_first_html( DOMXPath $xpath, $query ) {
        $nodes = $xpath->query( $query );

        if ( $nodes instanceof DOMNodeList && $nodes->length ) {
            return self::domnode_inner_html( $nodes->item( 0 ) );
        }

        return '';
    }

    /**
     * Helper: retrieves the inner HTML of a DOMNode.
     *
     * @param DOMNode $node Node instance.
     * @return string
     */
    protected static function domnode_inner_html( DOMNode $node ) {
        $html = '';

        foreach ( $node->childNodes as $child ) {
            $html .= $node->ownerDocument->saveHTML( $child );
        }

        return $html;
    }

    /**
     * Renders the settings page.
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'تنظیمات', 'saeid-scrapper' ); ?></h1>
            <p><?php esc_html_e( 'در این بخش می‌توانید تنظیمات عمومی پلاگین را مدیریت کنید. (در نسخه فعلی تنظیماتی وجود ندارد)', 'saeid-scrapper' ); ?></p>
        </div>
        <?php
    }
}

Saeid_Scrapper_Plugin::init();
