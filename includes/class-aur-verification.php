<?php
/**
 * Verification management class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Verification class
 */
class AUR_Verification {

    /**
     * Initialize verification hooks
     */
    public function init() {
        add_action( 'wp_login', array( $this, 'check_user_verification_status' ), 10, 2 );
        add_action( 'init', array( $this, 'handle_verification_cleanup' ) );
        add_filter( 'authenticate', array( $this, 'check_user_before_login' ), 30, 3 );
    }

    /**
     * Check user verification status after login
     *
     * @param string  $user_login Username.
     * @param WP_User $user       User object.
     */
    public function check_user_verification_status( $user_login, $user ) {
        $needs_email_verification = get_user_meta( $user->ID, 'aur_email_verification_required', true );
        $email_confirmed = get_user_meta( $user->ID, 'aur_email_confirmed', true );

        // If email verification is required but not completed
        if ( $needs_email_verification === '1' && $email_confirmed !== '1' ) {
            // Set a flag that can be checked by the frontend
            set_transient( 'aur_user_needs_verification_' . $user->ID, true, 300 );
        }
    }

    /**
     * Check user verification before allowing login
     *
     * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated. WP_Error or null otherwise.
     * @param string                $username Username or email address.
     * @param string                $password User password.
     * @return null|WP_User|WP_Error
     */
    public function check_user_before_login( $user, $username, $password ) {
        // If there's already an error or no user, don't interfere
        if ( is_wp_error( $user ) || ! $user ) {
            return $user;
        }

        // Get plugin settings
        $general_settings = get_option( 'aur_general_settings', array() );
        $require_email_verification = $general_settings['require_email_verification'] ?? true;

        // If email verification is not required, allow login
        if ( ! $require_email_verification ) {
            return $user;
        }

        // Check if user needs email verification
        $needs_verification = get_user_meta( $user->ID, 'aur_email_verification_required', true );
        $email_confirmed = get_user_meta( $user->ID, 'aur_email_confirmed', true );

        // If user was created by AUR and needs verification
        if ( $needs_verification === '1' && $email_confirmed !== '1' ) {
            // Allow login but flag for verification
            // The frontend will handle the verification flow
            return $user;
        }

        return $user;
    }

    /**
     * Generate verification code
     *
     * @param int $length Code length.
     * @return string
     */
    public function generate_verification_code( $length = 6 ) {
        return str_pad( wp_rand( 0, pow( 10, $length ) - 1 ), $length, '0', STR_PAD_LEFT );
    }

    /**
     * Send email verification
     *
     * @param int    $user_id User ID.
     * @param string $email   Email address.
     * @return bool
     */
    public function send_email_verification( $user_id, $email ) {
        $code = $this->generate_verification_code();

        // Store verification code
        $database = AUR()->get_module( 'database' );
        $stored = $database->store_verification_code( $user_id, $code, 'email' );

        if ( ! $stored ) {
            return false;
        }

        // Send email
        $email_service = AUR()->get_module( 'email' );
        return $email_service->send_verification_email( $user_id, $email, $code );
    }

    /**
     * Send phone verification
     *
     * @param int    $user_id User ID.
     * @param string $phone   Phone number.
     * @return bool
     */
    public function send_phone_verification( $user_id, $phone ) {
        $code = $this->generate_verification_code();

        // Store verification code
        $database = AUR()->get_module( 'database' );
        $stored = $database->store_verification_code( $user_id, $code, 'phone' );

        if ( ! $stored ) {
            return false;
        }

        // Send SMS
        $sms_service = AUR()->get_module( 'sms' );
        return $sms_service->send_verification_sms( $user_id, $phone, $code );
    }

    /**
     * Verify email code
     *
     * @param int    $user_id User ID.
     * @param string $code    Verification code.
     * @return bool
     */
    public function verify_email_code( $user_id, $code ) {
        $database = AUR()->get_module( 'database' );
        $verified = $database->verify_code( $user_id, $code, 'email' );

        if ( $verified ) {
            // Update user meta
            update_user_meta( $user_id, 'aur_email_confirmed', '1' );
            update_user_meta( $user_id, 'aur_email_verification_required', '0' );

            // Trigger action
            do_action( 'aur_email_verified', $user_id );

            return true;
        }

        return false;
    }

    /**
     * Verify phone code
     *
     * @param int    $user_id User ID.
     * @param string $code    Verification code.
     * @return bool
     */
    public function verify_phone_code( $user_id, $code ) {
        $database = AUR()->get_module( 'database' );
        $verified = $database->verify_code( $user_id, $code, 'phone' );

        if ( $verified ) {
            // Update user meta
            update_user_meta( $user_id, 'aur_phone_confirmed', '1' );

            // Trigger action
            do_action( 'aur_phone_verified', $user_id );

            return true;
        }

        return false;
    }

    /**
     * Get user verification status
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_user_verification_status( $user_id ) {
        return array(
            'email_confirmed' => get_user_meta( $user_id, 'aur_email_confirmed', true ) === '1',
            'phone_confirmed' => get_user_meta( $user_id, 'aur_phone_confirmed', true ) === '1',
            'needs_email_verification' => get_user_meta( $user_id, 'aur_email_verification_required', true ) === '1',
            'phone_number' => get_user_meta( $user_id, 'aur_phone_number', true ),
        );
    }

    /**
     * Mark user as needing verification
     *
     * @param int $user_id User ID.
     */
    public function mark_user_needs_verification( $user_id ) {
        update_user_meta( $user_id, 'aur_email_verification_required', '1' );
        update_user_meta( $user_id, 'aur_email_confirmed', '0' );
        update_user_meta( $user_id, 'aur_phone_confirmed', '0' );
    }

    /**
     * Check if user can access site
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function can_user_access_site( $user_id ) {
        $general_settings = get_option( 'aur_general_settings', array() );
        $require_email_verification = $general_settings['require_email_verification'] ?? true;

        if ( ! $require_email_verification ) {
            return true;
        }

        $needs_verification = get_user_meta( $user_id, 'aur_email_verification_required', true );
        $email_confirmed = get_user_meta( $user_id, 'aur_email_confirmed', true );

        // If user needs verification but hasn't confirmed email
        if ( $needs_verification === '1' && $email_confirmed !== '1' ) {
            return false;
        }

        return true;
    }

    /**
     * Get verification statistics
     *
     * @return array
     */
    public function get_verification_statistics() {
        global $wpdb;

        $stats = array();

        // Email verification stats
        $stats['total_users'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

        $stats['email_verified'] = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'aur_email_confirmed' 
            AND meta_value = '1'
        " );

        $stats['email_pending'] = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'aur_email_verification_required' 
            AND meta_value = '1'
        " );

        // Phone verification stats
        $stats['phone_verified'] = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'aur_phone_confirmed' 
            AND meta_value = '1'
        " );

        $stats['phone_added'] = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'aur_phone_number' 
            AND meta_value != ''
        " );

        // Recent verification activity
        $verification_table = $wpdb->prefix . 'aur_verification_codes';
        $stats['recent_verifications'] = $wpdb->get_results( "
            SELECT v.*, u.user_login, u.user_email 
            FROM {$verification_table} v
            LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
            WHERE v.verified_at IS NOT NULL
            ORDER BY v.verified_at DESC
            LIMIT 10
        " );

        // Pending verifications
        $stats['pending_codes'] = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$verification_table}
            WHERE verified_at IS NULL 
            AND expires_at > NOW()
        " );

        return $stats;
    }

    /**
     * Handle verification cleanup (remove expired codes)
     */
    public function handle_verification_cleanup() {
        // Only run cleanup occasionally
        if ( wp_rand( 1, 100 ) > 5 ) { // 5% chance
            return;
        }

        $database = AUR()->get_module( 'database' );
        $database->cleanup_expired_codes();
    }

    /**
     * Resend verification code
     *
     * @param int    $user_id User ID.
     * @param string $type    Verification type (email/phone).
     * @param string $contact Contact info (email/phone).
     * @return bool
     */
    public function resend_verification_code( $user_id, $type, $contact ) {
        // Check rate limiting
        $last_sent = get_transient( "aur_last_verification_{$type}_{$user_id}" );
        if ( $last_sent ) {
            return new WP_Error( 'rate_limited', __( 'Please wait before requesting another code.', 'advanced-user-registration' ) );
        }

        // Set rate limit (2 minutes)
        set_transient( "aur_last_verification_{$type}_{$user_id}", time(), 120 );

        if ( $type === 'email' ) {
            return $this->send_email_verification( $user_id, $contact );
        } else {
            return $this->send_phone_verification( $user_id, $contact );
        }
    }

    /**
     * Check if verification is required for registration
     *
     * @return bool
     */
    public function is_verification_required() {
        $general_settings = get_option( 'aur_general_settings', array() );
        return $general_settings['require_email_verification'] ?? true;
    }

    /**
     * Get verification code expiry time in minutes
     *
     * @return int
     */
    public function get_code_expiry_minutes() {
        $general_settings = get_option( 'aur_general_settings', array() );
        return absint( $general_settings['verification_code_expiry'] ?? 30 );
    }

    /**
     * Check if user has pending verification
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function user_has_pending_verification( $user_id ) {
        global $wpdb;

        $verification_table = $wpdb->prefix . 'aur_verification_codes';
        $count = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$verification_table}
            WHERE user_id = %d 
            AND verified_at IS NULL 
            AND expires_at > NOW()
        ", $user_id ) );

        return $count > 0;
    }

    /**
     * Delete all verification codes for user
     *
     * @param int    $user_id User ID.
     * @param string $type    Optional. Verification type to delete.
     */
    public function delete_user_verification_codes( $user_id, $type = '' ) {
        global $wpdb;

        $verification_table = $wpdb->prefix . 'aur_verification_codes';

        if ( $type ) {
            $wpdb->delete(
                $verification_table,
                array(
                    'user_id' => $user_id,
                    'type' => $type
                ),
                array( '%d', '%s' )
            );
        } else {
            $wpdb->delete(
                $verification_table,
                array( 'user_id' => $user_id ),
                array( '%d' )
            );
        }
    }

    /**
     * Force verify user (admin function)
     *
     * @param int    $user_id User ID.
     * @param string $type    Verification type.
     * @return bool
     */
    public function force_verify_user( $user_id, $type ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( $type === 'email' ) {
            update_user_meta( $user_id, 'aur_email_confirmed', '1' );
            update_user_meta( $user_id, 'aur_email_verification_required', '0' );
            do_action( 'aur_email_verified', $user_id );
        } elseif ( $type === 'phone' ) {
            update_user_meta( $user_id, 'aur_phone_confirmed', '1' );
            do_action( 'aur_phone_verified', $user_id );
        }

        // Clean up verification codes
        $this->delete_user_verification_codes( $user_id, $type );

        return true;
    }

    /**
     * Reset user verification status (admin function)
     *
     * @param int    $user_id User ID.
     * @param string $type    Verification type.
     * @return bool
     */
    public function reset_user_verification( $user_id, $type ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( $type === 'email' ) {
            update_user_meta( $user_id, 'aur_email_confirmed', '0' );
            update_user_meta( $user_id, 'aur_email_verification_required', '1' );
        } elseif ( $type === 'phone' ) {
            update_user_meta( $user_id, 'aur_phone_confirmed', '0' );
        }

        // Clean up verification codes
        $this->delete_user_verification_codes( $user_id, $type );

        return true;
    }
}