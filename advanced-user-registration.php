<?php
/**
 * Plugin Name: Advanced User Registration
 * Plugin URI: https://example.com/advanced-user-registration
 * Description: A modular user registration system with email/phone verification and customizable fields
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: advanced-user-registration
 * Domain Path: /languages
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'AUR_VERSION', '1.0.0' );
define( 'AUR_PLUGIN_FILE', __FILE__ );
define( 'AUR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Advanced_User_Registration {

    /**
     * The single instance of the class.
     *
     * @var Advanced_User_Registration
     */
    private static $instance = null;

    /**
     * Plugin modules
     *
     * @var array
     */
    private $modules = array();

    /**
     * Main plugin instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return Advanced_User_Registration
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_modules();
    }

    /**
     * Hook into actions and filters.
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies() {
        // Core classes
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-database.php';
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-api.php';
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-email.php';
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-sms.php';
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-user-fields.php';
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-verification.php';
        require_once AUR_PLUGIN_DIR . 'includes/class-aur-block.php';

        // Admin classes
        if ( is_admin() ) {
            require_once AUR_PLUGIN_DIR . 'admin/class-aur-admin.php';
            require_once AUR_PLUGIN_DIR . 'admin/class-aur-settings.php';
            require_once AUR_PLUGIN_DIR . 'admin/class-aur-field-manager.php';
        }
    }

    /**
     * Initialize plugin modules.
     */
    private function init_modules() {
        $this->modules = array(
            'database'     => new AUR_Database(),
            'api'          => new AUR_API(),
            'email'        => new AUR_Email(),
            'sms'          => new AUR_SMS(),
            'user_fields'  => new AUR_User_Fields(),
            'verification' => new AUR_Verification(),
            'block'        => new AUR_Block(),
        );

        if ( is_admin() ) {
            $this->modules['admin'] = new AUR_Admin();
            $this->modules['settings'] = new AUR_Settings();
            $this->modules['field_manager'] = new AUR_Field_Manager();
        }
    }

    /**
     * Get a module instance.
     *
     * @param string $module Module name.
     * @return object|null
     */
    public function get_module( $module ) {
        return isset( $this->modules[ $module ] ) ? $this->modules[ $module ] : null;
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        $this->get_module( 'database' )->create_tables();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'advanced-user-registration',
            false,
            dirname( AUR_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Initialize plugin.
     */
    public function init() {
        // Initialize modules
        foreach ( $this->modules as $module ) {
            if ( method_exists( $module, 'init' ) ) {
                $module->init();
            }
        }
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'aur-frontend',
            AUR_PLUGIN_URL . 'assets/dist/frontend.js',
            array( 'wp-api-fetch' ),
            AUR_VERSION,
            true
        );

        wp_enqueue_style(
            'aur-frontend',
            AUR_PLUGIN_URL . 'assets/dist/frontend.css',
            array(),
            AUR_VERSION
        );

        wp_localize_script( 'aur-frontend', 'aurAjax', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'restUrl'   => rest_url( 'aur/v1/' ),
            'nonce'     => wp_create_nonce( 'aur_nonce' ),
            'isLoggedIn' => is_user_logged_in(),
            'currentUser' => wp_get_current_user()->ID,
            'strings'   => array(
                'loading'           => __( 'Loading...', 'advanced-user-registration' ),
                'error'             => __( 'An error occurred', 'advanced-user-registration' ),
                'success'           => __( 'Success!', 'advanced-user-registration' ),
                'emailSent'         => __( 'Verification email sent!', 'advanced-user-registration' ),
                'smsSent'           => __( 'Verification SMS sent!', 'advanced-user-registration' ),
                'invalidCode'       => __( 'Invalid verification code', 'advanced-user-registration' ),
                'codeExpired'       => __( 'Verification code has expired', 'advanced-user-registration' ),
            ),
        ) );
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'aur' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'aur-admin',
            AUR_PLUGIN_URL . 'assets/dist/admin.js',
            array( 'jquery', 'wp-api-fetch' ),
            AUR_VERSION,
            true
        );

        wp_enqueue_style(
            'aur-admin',
            AUR_PLUGIN_URL . 'assets/dist/admin.css',
            array(),
            AUR_VERSION
        );

        wp_localize_script( 'aur-admin', 'aurAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url( 'aur/v1/' ),
            'nonce'   => wp_create_nonce( 'aur_admin_nonce' ),
        ) );
    }
}

/**
 * Main instance of plugin.
 *
 * @return Advanced_User_Registration
 */
function AUR() {
    return Advanced_User_Registration::instance();
}

// Initialize the plugin
AUR();