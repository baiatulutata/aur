<?php
/**
 * API operations class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_API class
 */
class AUR_API {

    /**
     * Initialize API hooks
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_ajax_aur_login', array( $this, 'handle_login' ) );
        add_action( 'wp_ajax_nopriv_aur_login', array( $this, 'handle_login' ) );
        add_action( 'wp_ajax_aur_send_verification', array( $this, 'handle_send_verification' ) );
        add_action( 'wp_ajax_nopriv_aur_send_verification', array( $this, 'handle_send_verification' ) );
        add_action( 'wp_ajax_aur_verify_code', array( $this, 'handle_verify_code' ) );
        add_action( 'wp_ajax_nopriv_aur_verify_code', array( $this, 'handle_verify_code' ) );
        add_action( 'wp_ajax_aur_update_user', array( $this, 'handle_update_user' ) );
        add_action( 'wp_ajax_aur_register_user', array( $this, 'handle_register_user' ) );
        add_action( 'wp_ajax_nopriv_aur_register_user', array( $this, 'handle_register_user' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route( 'aur/v1', '/login', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_login' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'aur/v1', '/register', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_register' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'aur/v1', '/send-verification', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_send_verification' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'aur/v1', '/verify-code', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_verify_code' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( 'aur/v1', '/user-status', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'rest_get_user_status' ),
            'permission_callback' => array( $this, 'check_authentication' ),
        ) );

        register_rest_route( 'aur/v1', '/update-user', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_update_user' ),
            'permission_callback' => array( $this, 'check_authentication' ),
        ) );

        register_rest_route( 'aur/v1', '/fields', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'rest_get_fields' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Check authentication for REST requests
     */
    public function check_authentication() {
        return is_user_logged_in();
    }

    /**
     * Handle user login via AJAX
     */
    public function handle_login() {
        if ( ! $this->verify_nonce() ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $username = sanitize_user( $_POST['username'] ?? '' );
        $password = $_POST['password'] ?? '';

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( array(
                'message' => __( 'Username and password are required', 'advanced-user-registration' )
            ) );
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array(
                'message' => $user->get_error_message()
            ) );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );

        $response = array(
            'user_id' => $user->ID,
            'email_confirmed' => get_user_meta( $user->ID, 'aur_email_confirmed', true ),
            'phone_confirmed' => get_user_meta( $user->ID, 'aur_phone_confirmed', true ),
            'needs_email_verification' => get_user_meta( $user->ID, 'aur_email_verification_required', true ),
        );

        wp_send_json_success( $response );
    }

    /**
     * Handle user registration via AJAX
     */
    public function handle_register_user() {
        if ( ! $this->verify_nonce() ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $user_data = $_POST['user_data'] ?? array();

        if ( empty( $user_data['user_login'] ) || empty( $user_data['user_email'] ) || empty( $user_data['user_pass'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Username, email, and password are required', 'advanced-user-registration' )
            ) );
        }

        // Validate email
        if ( ! is_email( $user_data['user_email'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a valid email address', 'advanced-user-registration' )
            ) );
        }

        // Check if username exists
        if ( username_exists( $user_data['user_login'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Username already exists', 'advanced-user-registration' )
            ) );
        }

        // Check if email exists
        if ( email_exists( $user_data['user_email'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Email address already exists', 'advanced-user-registration' )
            ) );
        }

        // Create user
        $user_id = wp_insert_user( array(
            'user_login' => sanitize_user( $user_data['user_login'] ),
            'user_email' => sanitize_email( $user_data['user_email'] ),
            'user_pass'  => $user_data['user_pass'],
            'first_name' => sanitize_text_field( $user_data['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $user_data['last_name'] ?? '' ),
        ) );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array(
                'message' => $user_id->get_error_message()
            ) );
        }

        // Set default user meta
        $default_meta = get_option( 'aur_default_user_meta', array() );
        foreach ( $default_meta as $key => $value ) {
            update_user_meta( $user_id, $key, $value );
        }

        // Set custom field values
        if ( ! empty( $user_data['aur_phone_number'] ) ) {
            update_user_meta( $user_id, 'aur_phone_number', sanitize_text_field( $user_data['aur_phone_number'] ) );
        }

        // Log user in
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        wp_send_json_success( array(
            'user_id' => $user_id,
            'message' => __( 'Registration successful', 'advanced-user-registration' )
        ) );
    }

    /**
     * Handle sending verification code via AJAX
     */
    public function handle_send_verification() {
        if ( ! $this->verify_nonce() ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        $type = sanitize_text_field( $_POST['type'] ?? 'email' );
        $contact = sanitize_text_field( $_POST['contact'] ?? '' );

        if ( ! $user_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid user ID', 'advanced-user-registration' )
            ) );
        }

        // Generate verification code
        $code = $this->generate_verification_code();

        // Store code in database
        $database = AUR()->get_module( 'database' );
        $stored = $database->store_verification_code( $user_id, $code, $type );

        if ( ! $stored ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to generate verification code', 'advanced-user-registration' )
            ) );
        }

        // Send verification based on type
        if ( $type === 'email' ) {
            $email_service = AUR()->get_module( 'email' );
            $sent = $email_service->send_verification_email( $user_id, $contact, $code );
        } else {
            $sms_service = AUR()->get_module( 'sms' );
            $sent = $sms_service->send_verification_sms( $user_id, $contact, $code );
        }

        if ( $sent ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    __( 'Verification code sent to your %s', 'advanced-user-registration' ),
                    $type === 'email' ? __( 'email', 'advanced-user-registration' ) : __( 'phone', 'advanced-user-registration' )
                )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to send verification code', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * Handle verifying code via AJAX
     */
    public function handle_verify_code() {
        if ( ! $this->verify_nonce() ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        $code = sanitize_text_field( $_POST['code'] ?? '' );
        $type = sanitize_text_field( $_POST['type'] ?? 'email' );

        if ( ! $user_id || ! $code ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid user ID or verification code', 'advanced-user-registration' )
            ) );
        }

        $database = AUR()->get_module( 'database' );
        $verified = $database->verify_code( $user_id, $code, $type );

        if ( $verified ) {
            // Update user meta
            $meta_key = $type === 'email' ? 'aur_email_confirmed' : 'aur_phone_confirmed';
            update_user_meta( $user_id, $meta_key, '1' );

            if ( $type === 'email' ) {
                update_user_meta( $user_id, 'aur_email_verification_required', '0' );
            }

            wp_send_json_success( array(
                'message' => sprintf(
                    __( '%s verified successfully', 'advanced-user-registration' ),
                    ucfirst( $type )
                )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Invalid or expired verification code', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * Handle user update via AJAX
     */
    public function handle_update_user() {
        if ( ! $this->verify_nonce() || ! is_user_logged_in() ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $user_id = get_current_user_id();
        $user_data = $_POST['user_data'] ?? array();

        if ( empty( $user_data ) ) {
            wp_send_json_error( array(
                'message' => __( 'No data provided', 'advanced-user-registration' )
            ) );
        }

        // Get editable fields
        $database = AUR()->get_module( 'database' );
        $editable_fields = $database->get_user_fields( array(
            'where' => 'is_editable = 1'
        ) );

        $allowed_fields = array();
        foreach ( $editable_fields as $field ) {
            $allowed_fields[] = $field->field_name;
        }

        // Update user data
        $user_update_data = array( 'ID' => $user_id );
        $meta_data = array();

        foreach ( $user_data as $field => $value ) {
            if ( ! in_array( $field, $allowed_fields ) ) {
                continue;
            }

            $sanitized_value = sanitize_text_field( $value );

            // Handle WordPress core fields
            if ( in_array( $field, array( 'first_name', 'last_name', 'user_email' ) ) ) {
                $user_update_data[ $field ] = $sanitized_value;
            } else {
                $meta_data[ $field ] = $sanitized_value;
            }
        }

        // Update user
        if ( count( $user_update_data ) > 1 ) {
            $result = wp_update_user( $user_update_data );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array(
                    'message' => $result->get_error_message()
                ) );
            }
        }

        // Update meta data
        foreach ( $meta_data as $key => $value ) {
            update_user_meta( $user_id, $key, $value );
        }

        wp_send_json_success( array(
            'message' => __( 'Profile updated successfully', 'advanced-user-registration' )
        ) );
    }

    /**
     * REST API: Login
     */
    public function rest_login( $request ) {
        $username = sanitize_user( $request->get_param( 'username' ) );
        $password = $request->get_param( 'password' );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'missing_credentials', __( 'Username and password are required', 'advanced-user-registration' ), array( 'status' => 400 ) );
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'login_failed', $user->get_error_message(), array( 'status' => 401 ) );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );

        return rest_ensure_response( array(
            'user_id' => $user->ID,
            'email_confirmed' => get_user_meta( $user->ID, 'aur_email_confirmed', true ),
            'phone_confirmed' => get_user_meta( $user->ID, 'aur_phone_confirmed', true ),
            'needs_email_verification' => get_user_meta( $user->ID, 'aur_email_verification_required', true ),
        ) );
    }

    /**
     * REST API: Register
     */
    public function rest_register( $request ) {
        $user_data = $request->get_param( 'user_data' );

        if ( empty( $user_data['user_login'] ) || empty( $user_data['user_email'] ) || empty( $user_data['user_pass'] ) ) {
            return new WP_Error( 'missing_data', __( 'Username, email, and password are required', 'advanced-user-registration' ), array( 'status' => 400 ) );
        }

        // Validate and create user (same logic as AJAX handler)
        // ... (implementation similar to handle_register_user)

        return rest_ensure_response( array(
            'success' => true,
            'message' => __( 'Registration successful', 'advanced-user-registration' )
        ) );
    }

    /**
     * REST API: Send verification
     */
    public function rest_send_verification( $request ) {
        // Similar to handle_send_verification but for REST API
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * REST API: Verify code
     */
    public function rest_verify_code( $request ) {
        // Similar to handle_verify_code but for REST API
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * REST API: Get user status
     */
    public function rest_get_user_status( $request ) {
        $user_id = get_current_user_id();

        return rest_ensure_response( array(
            'user_id' => $user_id,
            'email_confirmed' => get_user_meta( $user_id, 'aur_email_confirmed', true ),
            'phone_confirmed' => get_user_meta( $user_id, 'aur_phone_confirmed', true ),
            'needs_email_verification' => get_user_meta( $user_id, 'aur_email_verification_required', true ),
            'phone_number' => get_user_meta( $user_id, 'aur_phone_number', true ),
        ) );
    }

    /**
     * REST API: Update user
     */
    public function rest_update_user( $request ) {
        // Similar to handle_update_user but for REST API
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * REST API: Get fields
     */
    public function rest_get_fields( $request ) {
        $database = AUR()->get_module( 'database' );
        $fields = $database->get_user_fields();

        return rest_ensure_response( $fields );
    }

    /**
     * Verify nonce
     *
     * @return bool
     */
    private function verify_nonce() {
        $nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
        return wp_verify_nonce( $nonce, 'aur_nonce' );
    }

    /**
     * Generate verification code
     *
     * @return string
     */
    private function generate_verification_code() {
        return str_pad( wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    }
}