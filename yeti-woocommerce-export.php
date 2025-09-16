<?php use PhpOffice\PhpSpreadsheet\Spreadsheet;
use YWCE\YWCE_Data_Helper;
use YWCE\YWCE_Format_Handler;
use YWCE\YWCE_Exporter;
use YWCE\YWCE_Export_Wizard;

/**
 * Plugin Name: Yeti WooCommerce Export
 * Plugin URI: https://yetiweb.se
 * Description: Easy exporter for WooCommerce.
 * Version: 1.1.0
 * Author: Yeti Web
 * Author URI: https://yetiweb.se
 * Text Domain: yeti-woocommerce-export
 * Domain Path: /languages
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'YWCE_VERSION', '1.1.0' );
define( 'YWCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YWCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YWCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'YWCE_PLUGIN_TEXT_DOMAIN', 'yeti-woocommerce-export' );

if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    add_action( 'admin_notices', function () {
        ?>
        <div class="error">
            <p><?php _e( 'Yeti WooCommerce Export requires PHP 8.0 or higher. Please upgrade your PHP version or contact your hosting provider.', 'yeti-woocommerce-export' ); ?></p>
        </div>
        <?php
    } );

    return;
}

$autoloader = YWCE_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
} else {
    add_action( 'admin_notices', function () {
        ?>
        <div class="error">
            <p><?php _e( 'Yeti WooCommerce Export requires Composer dependencies to be installed. Please run the update-dependencies-php8.0 script included with the plugin.', 'yeti-woocommerce-export' ); ?></p>
        </div>
        <?php
    } );
}

/**
 * Main Plugin Class
 */
class Yeti_WooCommerce_Export {
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Return an instance of this class
     */
    public static function get_instance(): ?Yeti_WooCommerce_Export {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->includes();
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );
        add_action( 'init', [ $this, 'load_textdomain' ] );
    }

    /**
     * Include required files
     */
	   	private function includes(): void {
		// Core legacy classes still loaded directly
		require_once YWCE_PLUGIN_DIR . 'includes/helpers/class-data-helper.php';
		require_once YWCE_PLUGIN_DIR . 'includes/helpers/class-format-handler.php';
		require_once YWCE_PLUGIN_DIR . 'includes/exporters/class-exporter.php';
		require_once YWCE_PLUGIN_DIR . 'includes/admin/class-export-wizard.php';

		// Load namespaced classes without relying on Composer (fallback autoload)
		// Support
		require_once YWCE_PLUGIN_DIR . 'includes/Support/Filesystem.php';

		// Data layer
		require_once YWCE_PLUGIN_DIR . 'includes/Data/FieldRegistry.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Data/Mapper/ProductDataMapper.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Data/Mapper/UserDataMapper.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Data/Mapper/OrderDataMapper.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Data/Store/ProductStore.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Data/Store/UserStore.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Data/Store/OrderStore.php';

		// Writers
		require_once YWCE_PLUGIN_DIR . 'includes/Export/Writer/FormatWriterInterface.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Export/Writer/CsvWriter.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Export/Writer/JsonWriter.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Export/Writer/XmlWriter.php';
		require_once YWCE_PLUGIN_DIR . 'includes/Export/Writer/XlsxWriter.php';

		// Backward compatibility aliases for legacy global class names
		if ( class_exists( YWCE_Data_Helper::class ) && !class_exists('YWCE_Data_Helper')) { class_alias( YWCE_Data_Helper::class, 'YWCE_Data_Helper'); }
		if ( class_exists( YWCE_Format_Handler::class ) && !class_exists('YWCE_Format_Handler')) { class_alias( YWCE_Format_Handler::class, 'YWCE_Format_Handler'); }
		if ( class_exists( YWCE_Exporter::class ) && !class_exists('YWCE_Exporter')) { class_alias( YWCE_Exporter::class, 'YWCE_Exporter'); }
		if ( class_exists( YWCE_Export_Wizard::class ) && !class_exists('YWCE_Export_Wizard')) { class_alias( YWCE_Export_Wizard::class, 'YWCE_Export_Wizard'); }
	}

    /**
     * Initialize the plugin
     */
    public function init(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );

            return;
        }

        if ( ! class_exists( Spreadsheet::class ) ) {
            add_action( 'admin_notices', [ $this, 'dependencies_missing_notice' ] );
        }

      		new \YWCE\YWCE_Export_Wizard();
      		new \YWCE\YWCE_Exporter();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
                YWCE_PLUGIN_TEXT_DOMAIN,
                false,
                dirname( YWCE_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Register scripts and styles
     */
    public function register_scripts( $hook ): void {
        if ( 'woocommerce_page_ywce-export' !== $hook ) {
            return;
        }

        wp_register_style( 'ywce-bootstrap', YWCE_PLUGIN_URL . 'assets/lib/bootstrap.min.css', [], YWCE_VERSION );
        wp_register_style( 'ywce-admin', YWCE_PLUGIN_URL . 'assets/css/admin.css', [ 'ywce-bootstrap' ], YWCE_VERSION );
        wp_enqueue_style( 'ywce-admin' );

        wp_register_script( 'ywce-bootstrap', YWCE_PLUGIN_URL . 'assets/lib/bootstrap.bundle.min.js', [ 'jquery' ], YWCE_VERSION, true );
        wp_register_script( 'ywce-admin', YWCE_PLUGIN_URL . 'assets/js/admin.js', [
                'jquery',
                'ywce-bootstrap'
        ], YWCE_VERSION, true );

        $translations = include YWCE_PLUGIN_DIR . 'includes/translations.php';

        wp_localize_script( 'ywce-admin', 'ywce_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ywce_export_nonce' ),
                'i18n'     => $translations
        ] );

        wp_enqueue_script( 'ywce-admin' );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice(): void {
        ?>
        <div class="error">
            <p><?php _e( 'Yeti WooCommerce Export requires WooCommerce to be installed and active.', 'yeti-woocommerce-export' ); ?></p>
        </div>
        <?php
    }

    /**
     * Dependencies missing notice
     */
    public function dependencies_missing_notice(): void {
        ?>
        <div class="error">
            <p><?php _e( 'Yeti WooCommerce Export is missing required dependencies. Some export formats may not be available. Please run the update-dependencies-php8.0 script included with the plugin.', 'yeti-woocommerce-export' ); ?></p>
        </div>
        <?php
    }
}

function ywce_init(): ?Yeti_WooCommerce_Export {
    return Yeti_WooCommerce_Export::get_instance();
}

ywce_init();