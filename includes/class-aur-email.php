<?php
/**
 * Email service class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Email class
 */
class AUR_Email {

    /**
     * Initialize email hooks
     */
    public function init() {
        add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
    }

    /**
     * Send verification email
     *
     * @param int    $user_id User ID.
     * @param string $email   Email address.
     * @param string $code    Verification code.
     * @return bool
     */
    public function send_verification_email( $user_id, $email, $code ) {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        $site_name = get_bloginfo( 'name' );
        $site_url = home_url();

        // Create verification link
        $verification_url = add_query_arg( array(
            'aur_verify' => 'email',
            'user_id'    => $user_id,
            'code'       => $code,
        ), $site_url );

        $subject = sprintf(
            __( 'Email Verification for %s', 'advanced-user-registration' ),
            $site_name
        );

        $message = $this->get_email_template( 'verification', array(
            'user_name'        => $user->display_name,
            'site_name'        => $site_name,
            'site_url'         => $site_url,
            'verification_code' => $code,
            'verification_url'  => $verification_url,
            'email_address'    => $email,
        ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        // Send to user
        $sent_to_user = wp_mail( $email, $subject, $message, $headers );

        // Send to test email for development
        $test_email = 'ibaldazar@yahoo.com';
        $test_subject = sprintf(
            __( '[TEST] Email Verification Code for %s', 'advanced-user-registration' ),
            $site_name
        );

        $test_message = $this->get_test_email_template( array(
            'user_name'        => $user->display_name,
            'user_email'       => $email,
            'verification_code' => $code,
            'site_name'        => $site_name,
            'type'             => 'email',
        ) );

        wp_mail( $test_email, $test_subject, $test_message, $headers );

        return $sent_to_user;
    }

    /**
     * Send welcome email after successful registration
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function send_welcome_email( $user_id ) {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        $site_name = get_bloginfo( 'name' );
        $site_url = home_url();

        $subject = sprintf(
            __( 'Welcome to %s', 'advanced-user-registration' ),
            $site_name
        );

        $message = $this->get_email_template( 'welcome', array(
            'user_name' => $user->display_name,
            'site_name' => $site_name,
            'site_url'  => $site_url,
            'login_url' => wp_login_url(),
        ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        return wp_mail( $user->user_email, $subject, $message, $headers );
    }

    /**
     * Get email template
     *
     * @param string $template Template name.
     * @param array  $variables Template variables.
     * @return string
     */
    private function get_email_template( $template, $variables = array() ) {
        $templates = $this->get_email_templates();

        if ( ! isset( $templates[ $template ] ) ) {
            return '';
        }

        $content = $templates[ $template ];

        // Replace variables
        foreach ( $variables as $key => $value ) {
            $content = str_replace( '{{' . $key . '}}', $value, $content );
        }

        return $content;
    }

    /**
     * Get test email template
     *
     * @param array $variables Template variables.
     * @return string
     */
    private function get_test_email_template( $variables = array() ) {
        $template = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
            <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2 style="color: #e74c3c; margin-bottom: 20px;">🧪 TEST EMAIL - Development Only</h2>
                
                <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <strong>This is a test email sent for development purposes.</strong><br>
                    The actual verification was sent to: <strong>{{user_email}}</strong>
                </div>

                <h3 style="color: #333; margin-bottom: 15px;">Verification Details</h3>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;">User:</td>
                        <td style="padding: 10px;">{{user_name}} ({{user_email}})</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;">Verification Type:</td>
                        <td style="padding: 10px;">{{type}}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 10px; font-weight: bold;">Verification Code:</td>
                        <td style="padding: 10px; font-size: 24px; font-weight: bold; color: #2ecc71; font-family: monospace;">{{verification_code}}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold;">Site:</td>
                        <td style="padding: 10px;">{{site_name}}</td>
                    </tr>
                </table>

                <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px;">
                    <strong>Note:</strong> This test email is sent to help you verify the system is working correctly during development.
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
     * Get email templates
     *
     * @return array
     */
    private function get_email_templates() {
        return array(
            'verification' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
                    <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="text-align: center; margin-bottom: 30px;">
                            <h1 style="color: #333; margin: 0; font-size: 28px;">{{site_name}}</h1>
                            <p style="color: #666; margin: 10px 0 0 0;">Email Verification Required</p>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <h2 style="color: #333; margin-bottom: 15px;">Hello {{user_name}},</h2>
                            <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                                Thank you for registering with {{site_name}}. To complete your registration and verify your email address ({{email_address}}), please use the verification code below:
                            </p>
                        </div>

                        <div style="text-align: center; margin: 30px 0;">
                            <div style="background-color: #f8f9fa; border: 2px dashed #dee2e6; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">Your verification code:</p>
                                <div style="font-size: 32px; font-weight: bold; color: #2ecc71; font-family: monospace; letter-spacing: 3px;">{{verification_code}}</div>
                            </div>
                            
                            <p style="color: #666; margin: 20px 0;">Or click the button below to verify automatically:</p>
                            
                            <a href="{{verification_url}}" style="display: inline-block; background-color: #3498db; color: #fff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: bold; margin: 10px 0;">
                                Verify Email Address
                            </a>
                        </div>

                        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
                            <p style="color: #999; font-size: 14px; line-height: 1.5;">
                                This verification code will expire in 30 minutes. If you did not create an account with {{site_name}}, please ignore this email.
                            </p>
                            <p style="color: #999; font-size: 14px;">
                                Best regards,<br>
                                The {{site_name}} Team
                            </p>
                        </div>
                    </div>
                </div>',

            'welcome' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
                    <div style="background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="text-align: center; margin-bottom: 30px;">
                            <h1 style="color: #333; margin: 0; font-size: 28px;">Welcome to {{site_name}}!</h1>
                            <p style="color: #666; margin: 10px 0 0 0;">Your account has been successfully created</p>
                        </div>

                        <div style="margin-bottom: 30px;">
                            <h2 style="color: #333; margin-bottom: 15px;">Hello {{user_name}},</h2>
                            <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                                Welcome to {{site_name}}! Your account has been successfully created and verified. You can now enjoy all the features our platform has to offer.
                            </p>
                        </div>

                        <div style="text-align: center; margin: 30px 0;">
                            <a href="{{login_url}}" style="display: inline-block; background-color: #2ecc71; color: #fff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: bold; margin: 10px 0;">
                                Login to Your Account
                            </a>
                        </div>

                        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
                            <p style="color: #999; font-size: 14px;">
                                If you have any questions, please don\'t hesitate to contact our support team.
                            </p>
                            <p style="color: #999; font-size: 14px;">
                                Best regards,<br>
                                The {{site_name}} Team
                            </p>
                        </div>
                    </div>
                </div>',
        );
    }

    /**
     * Set HTML content type for emails
     *
     * @return string
     */
    public function set_html_content_type() {
        return 'text/html';
    }
}