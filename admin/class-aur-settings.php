<?php
/**
 * Settings management class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Settings class
 */
class AUR_Settings {

    /**
     * Initialize settings hooks
     */
    public function init() {
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_aur_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_aur_test_sms', array( $this, 'ajax_test_sms' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // General settings
        register_setting(
            'aur_general_settings',
            'aur_general_settings',
            array( $this, 'sanitize_general_settings' )
        );

        // Email settings
        register_setting(
            'aur_email_settings',
            'aur_email_settings',
            array( $this, 'sanitize_email_settings' )
        );

        // SMS settings
        register_setting(
            'aur_sms_settings',
            'aur_sms_settings',
            array( $this, 'sanitize_sms_settings' )
        );
    }

    /**
     * Register REST routes for settings
     */
    public function register_rest_routes() {
        register_rest_route( 'aur/v1', '/save-settings', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_save_settings' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
        ) );

        register_rest_route( 'aur/v1', '/test-sms', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_test_sms' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
        ) );

        register_rest_route( 'aur/v1', '/get-settings', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'rest_get_settings' ),
            'permission_callback' => array( $this, 'check_admin_permissions' ),
        ) );
    }

    /**
     * Check admin permissions
     *
     * @return bool
     */
    public function check_admin_permissions() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get default general settings
     *
     * @return array
     */
    public function get_default_general_settings() {
        return array(
            'auto_login_after_registration' => 1,
            'require_email_verification' => 1,
            'verification_code_expiry' => 30,
            'redirect_after_registration' => '',
            'allow_phone_skip' => 1,
            'show_progress_indicator' => 1,
            'enable_welcome_email' => 1,
        );
    }

    /**
     * Get default email settings
     *
     * @return array
     */
    public function get_default_email_settings() {
        return array(
            'from_name' => get_bloginfo( 'name' ),
            'from_email' => get_option( 'admin_email' ),
            'email_subject' => __( 'Email Verification Required', 'advanced-user-registration' ),
            'custom_email_template' => 0,
            'welcome_email_subject' => __( 'Welcome!', 'advanced-user-registration' ),
        );
    }

    /**
     * Get default SMS settings
     *
     * @return array
     */
    public function get_default_sms_settings() {
        return array(
            'provider' => 'twilio',
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_phone' => '',
        );
    }

    /**
     * Get general settings
     *
     * @return array
     */
    public function get_general_settings() {
        return wp_parse_args(
            get_option( 'aur_general_settings', array() ),
            $this->get_default_general_settings()
        );
    }

    /**
     * Get email settings
     *
     * @return array
     */
    public function get_email_settings() {
        return wp_parse_args(
            get_option( 'aur_email_settings', array() ),
            $this->get_default_email_settings()
        );
    }

    /**
     * Get SMS settings
     *
     * @return array
     */
    public function get_sms_settings() {
        return wp_parse_args(
            get_option( 'aur_sms_settings', array() ),
            $this->get_default_sms_settings()
        );
    }

    /**
     * Sanitize general settings
     *
     * @param array $input Settings input.
     * @return array
     */
    public function sanitize_general_settings( $input ) {
        $sanitized = array();
        $defaults = $this->get_default_general_settings();

        foreach ( $defaults as $key => $default_value ) {
            switch ( $key ) {
                case 'verification_code_expiry':
                    $sanitized[ $key ] = absint( $input[ $key ] ?? $default_value );
                    // Ensure it's between 1 and 1440 minutes (24 hours)
                    $sanitized[ $key ] = max( 1, min( 1440, $sanitized[ $key ] ) );
                    break;

                case 'redirect_after_registration':
                    $sanitized[ $key ] = esc_url_raw( $input[ $key ] ?? $default_value );
                    break;

                case 'auto_login_after_registration':
                case 'require_email_verification':
                case 'allow_phone_skip':
                case 'show_progress_indicator':
                case 'enable_welcome_email':
                    $sanitized[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
                    break;

                default:
                    $sanitized[ $key ] = sanitize_text_field( $input[ $key ] ?? $default_value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize email settings
     *
     * @param array $input Settings input.
     * @return array
     */
    public function sanitize_email_settings( $input ) {
        $sanitized = array();
        $defaults = $this->get_default_email_settings();

        foreach ( $defaults as $key => $default_value ) {
            switch ( $key ) {
                case 'from_email':
                    $sanitized[ $key ] = sanitize_email( $input[ $key ] ?? $default_value );
                    break;

                case 'custom_email_template':
                    $sanitized[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
                    break;

                default:
                    $sanitized[ $key ] = sanitize_text_field( $input[ $key ] ?? $default_value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize SMS settings
     *
     * @param array $input Settings input.
     * @return array
     */
    public function sanitize_sms_settings( $input ) {
        $sanitized = array();
        $defaults = $this->get_default_sms_settings();

        foreach ( $defaults as $key => $default_value ) {
            switch ( $key ) {
                case 'provider':
                    $allowed_providers = array( 'twilio', 'mock' );
                    $sanitized[ $key ] = in_array( $input[ $key ] ?? $default_value, $allowed_providers )
                        ? $input[ $key ]
                        : $default_value;
                    break;

                case 'twilio_phone':
                    $phone = sanitize_text_field( $input[ $key ] ?? $default_value );
                    // Basic phone validation
                    $sanitized[ $key ] = preg_replace( '/[^\d+\-\(\)\s]/', '', $phone );
                    break;

                default:
                    $sanitized[ $key ] = sanitize_text_field( $input[ $key ] ?? $default_value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'aur_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'advanced-user-registration' ) );
        }

        $settings = $_POST['settings'] ?? array();

        if ( empty( $settings ) ) {
            wp_send_json_error( array(
                'message' => __( 'No settings provided', 'advanced-user-registration' )
            ) );
        }

        $updated = $this->save_settings( $settings );

        if ( $updated ) {
            wp_send_json_success( array(
                'message' => __( 'Settings saved successfully!', 'advanced-user-registration' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to save settings', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * AJAX handler for testing SMS
     */
    public function ajax_test_sms() {
        check_ajax_referer( 'aur_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'advanced-user-registration' ) );
        }

        $phone_number = sanitize_text_field( $_POST['phone_number'] ?? '' );

        if ( empty( $phone_number ) ) {
            wp_send_json_error( array(
                'message' => __( 'Phone number is required', 'advanced-user-registration' )
            ) );
        }

        $sms_service = AUR()->get_module( 'sms' );
        $result = $sms_service->test_sms_config( $phone_number );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message()
            ) );
        } elseif ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Test SMS sent successfully! Check your phone and email.', 'advanced-user-registration' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to send test SMS', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * REST handler for saving settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_save_settings( $request ) {
        $settings = $request->get_param( 'settings' );

        if ( empty( $settings ) ) {
            return new WP_Error( 'no_settings', __( 'No settings provided', 'advanced-user-registration' ), array( 'status' => 400 ) );
        }

        $updated = $this->save_settings( $settings );

        if ( $updated ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => __( 'Settings saved successfully!', 'advanced-user-registration' )
            ) );
        } else {
            return new WP_Error( 'save_failed', __( 'Failed to save settings', 'advanced-user-registration' ), array( 'status' => 500 ) );
        }
    }

    /**
     * REST handler for testing SMS
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_test_sms( $request ) {
        $phone_number = $request->get_param( 'phone_number' );

        if ( empty( $phone_number ) ) {
            return new WP_Error( 'no_phone', __( 'Phone number is required', 'advanced-user-registration' ), array( 'status' => 400 ) );
        }

        $sms_service = AUR()->get_module( 'sms' );
        $result = $sms_service->test_sms_config( sanitize_text_field( $phone_number ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        } elseif ( $result ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => __( 'Test SMS sent successfully!', 'advanced-user-registration' )
            ) );
        } else {
            return new WP_Error( 'sms_failed', __( 'Failed to send test SMS', 'advanced-user-registration' ), array( 'status' => 500 ) );
        }
    }

    /**
     * REST handler for getting settings
     *
     * @return WP_REST_Response
     */
    public function rest_get_settings() {
        return rest_ensure_response( array(
            'general' => $this->get_general_settings(),
            'email' => $this->get_email_settings(),
            'sms' => $this->get_sms_settings(),
        ) );
    }

    /**
     * Save settings
     *
     * @param array $settings Settings array.
     * @return bool
     */
    private function save_settings( $settings ) {
        $updated = false;

        // Save general settings
        if ( isset( $settings['general'] ) ) {
            $general_settings = $this->sanitize_general_settings( $settings['general'] );
            $updated = update_option( 'aur_general_settings', $general_settings ) || $updated;
        }

        // Save email settings
        if ( isset( $settings['email'] ) ) {
            $email_settings = $this->sanitize_email_settings( $settings['email'] );
            $updated = update_option( 'aur_email_settings', $email_settings ) || $updated;
        }

        // Save SMS settings
        if ( isset( $settings['sms'] ) ) {
            $sms_settings = $this->sanitize_sms_settings( $settings['sms'] );
            $updated = update_option( 'aur_sms_settings', $sms_settings ) || $updated;

            // Update SMS service settings
            $sms_service = AUR()->get_module( 'sms' );
            if ( $sms_service ) {
                $sms_service->update_settings( $sms_settings );
            }
        }

        return $updated;
    }

    /**
     * Export settings
     *
     * @return array
     */
    public function export_settings() {
        return array(
            'general' => $this->get_general_settings(),
            'email' => $this->get_email_settings(),
            'sms' => $this->get_sms_settings(),
            'exported_at' => current_time( 'mysql' ),
            'plugin_version' => AUR_VERSION,
        );
    }

    /**
     * Import settings
     *
     * @param array $settings Settings to import.
     * @return bool|WP_Error
     */
    public function import_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return new WP_Error( 'invalid_format', __( 'Invalid settings format', 'advanced-user-registration' ) );
        }

        $updated = false;

        if ( isset( $settings['general'] ) ) {
            $updated = update_option( 'aur_general_settings', $settings['general'] ) || $updated;
        }

        if ( isset( $settings['email'] ) ) {
            $updated = update_option( 'aur_email_settings', $settings['email'] ) || $updated;
        }

        if ( isset( $settings['sms'] ) ) {
            $updated = update_option( 'aur_sms_settings', $settings['sms'] ) || $updated;
        }

        return $updated;
    }

    /**
     * Reset settings to defaults
     *
     * @param string $type Settings type (general, email, sms, or 'all').
     * @return bool
     */
    public function reset_settings( $type = 'all' ) {
        $updated = false;

        if ( $type === 'all' || $type === 'general' ) {
            $updated = update_option( 'aur_general_settings', $this->get_default_general_settings() ) || $updated;
        }

        if ( $type === 'all' || $type === 'email' ) {
            $updated = update_option( 'aur_email_settings', $this->get_default_email_settings() ) || $updated;
        }

        if ( $type === 'all' || $type === 'sms' ) {
            $updated = update_option( 'aur_sms_settings', $this->get_default_sms_settings() ) || $updated;
        }

        return $updated;
    }
}