<?php
/**
 * Gutenberg block class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Block class
 */
class AUR_Block {

    /**
     * Initialize block hooks
     */
    public function init() {
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'wp', array( $this, 'handle_email_verification_link' ) );
        add_shortcode( 'aur_registration', array( $this, 'registration_shortcode' ) );
    }

    /**
     * Register the Gutenberg block
     */
    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        wp_register_script(
            'aur-block-editor',
            AUR_PLUGIN_URL . 'assets/dist/block-editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ),
            AUR_VERSION,
            true
        );

        register_block_type( 'aur/registration-form', array(
            'editor_script' => 'aur-block-editor',
            'render_callback' => array( $this, 'render_block' ),
            'attributes' => array(
                'selectedFields' => array(
                    'type' => 'array',
                    'default' => array( 'user_login', 'user_email', 'user_pass' ),
                ),
                'showPhoneField' => array(
                    'type' => 'boolean',
                    'default' => true,
                ),
                'requirePhoneVerification' => array(
                    'type' => 'boolean',
                    'default' => false,
                ),
                'formTitle' => array(
                    'type' => 'string',
                    'default' => __( 'Register or Login', 'advanced-user-registration' ),
                ),
                'customCSS' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
        ) );
    }

    /**
     * Render the block
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function render_block( $attributes ) {
        $defaults = array(
            'selectedFields' => array( 'user_login', 'user_email', 'user_pass' ),
            'showPhoneField' => true,
            'requirePhoneVerification' => false,
            'formTitle' => __( 'Register or Login', 'advanced-user-registration' ),
            'customCSS' => '',
        );

        $attributes = wp_parse_args( $attributes, $defaults );

        return $this->render_registration_form( $attributes );
    }

    /**
     * Registration shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function registration_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'fields' => 'user_login,user_email,user_pass',
            'show_phone' => 'true',
            'require_phone' => 'false',
            'title' => __( 'Register or Login', 'advanced-user-registration' ),
        ), $atts );

        $attributes = array(
            'selectedFields' => explode( ',', $atts['fields'] ),
            'showPhoneField' => $atts['show_phone'] === 'true',
            'requirePhoneVerification' => $atts['require_phone'] === 'true',
            'formTitle' => $atts['title'],
        );

        return $this->render_registration_form( $attributes );
    }

    /**
     * Render registration form
     *
     * @param array $attributes Form attributes.
     * @return string
     */
    private function render_registration_form( $attributes ) {
        if ( ! wp_script_is( 'aur-frontend', 'enqueued' ) ) {
            wp_enqueue_script( 'aur-frontend' );
            wp_enqueue_style( 'aur-frontend' );
        }

        $user_status = $this->get_user_status();
        $database = AUR()->get_module( 'database' );
        $available_fields = $database->get_user_fields();

        ob_start();
        ?>
        <div id="aur-registration-form"
             class="aur-form-container max-w-md mx-auto bg-white shadow-lg rounded-lg p-6 space-y-6"
             data-attributes="<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>"
             data-user-status="<?php echo esc_attr( wp_json_encode( $user_status ) ); ?>"
             data-available-fields="<?php echo esc_attr( wp_json_encode( $available_fields ) ); ?>">

            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900 mb-2"><?php echo esc_html( $attributes['formTitle'] ); ?></h2>
                <div id="aur-form-messages" class="hidden"></div>
            </div>

            <!-- Loading State -->
            <div id="aur-loading" class="hidden text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <p class="mt-2 text-gray-600"><?php _e( 'Loading...', 'advanced-user-registration' ); ?></p>
            </div>

            <!-- Login Form -->
            <div id="aur-login-form" class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800"><?php _e( 'Login to Your Account', 'advanced-user-registration' ); ?></h3>

                <div class="space-y-3">
                    <div>
                        <label for="aur-login-username" class="block text-sm font-medium text-gray-700">
                            <?php _e( 'Username or Email', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="text"
                               id="aur-login-username"
                               name="username"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                               required>
                    </div>

                    <div>
                        <label for="aur-login-password" class="block text-sm font-medium text-gray-700">
                            <?php _e( 'Password', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="password"
                               id="aur-login-password"
                               name="password"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                               required>
                    </div>

                    <button type="submit"
                            id="aur-login-submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <?php _e( 'Login', 'advanced-user-registration' ); ?>
                    </button>
                </div>

                <div class="text-center">
                    <button type="button"
                            id="aur-show-register"
                            class="text-blue-600 hover:text-blue-500 text-sm">
                        <?php _e( 'Don\'t have an account? Register here', 'advanced-user-registration' ); ?>
                    </button>
                </div>
            </div>

            <!-- Registration Form -->
            <div id="aur-register-form" class="hidden space-y-4">
                <h3 class="text-lg font-semibold text-gray-800"><?php _e( 'Create New Account', 'advanced-user-registration' ); ?></h3>

                <div id="aur-register-fields" class="space-y-3">
                    <!-- Fields will be dynamically generated -->
                </div>

                <button type="submit"
                        id="aur-register-submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <?php _e( 'Register', 'advanced-user-registration' ); ?>
                </button>

                <div class="text-center">
                    <button type="button"
                            id="aur-show-login"
                            class="text-blue-600 hover:text-blue-500 text-sm">
                        <?php _e( 'Already have an account? Login here', 'advanced-user-registration' ); ?>
                    </button>
                </div>
            </div>

            <!-- Email Verification -->
            <div id="aur-email-verification" class="hidden space-y-4">
                <h3 class="text-lg font-semibold text-gray-800"><?php _e( 'Verify Your Email', 'advanced-user-registration' ); ?></h3>
                <p class="text-gray-600"><?php _e( 'Please verify your email address to continue.', 'advanced-user-registration' ); ?></p>

                <div class="space-y-3">
                    <div>
                        <label for="aur-email-address" class="block text-sm font-medium text-gray-700">
                            <?php _e( 'Email Address', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="email"
                               id="aur-email-address"
                               name="email"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                               required>
                    </div>

                    <button type="button"
                            id="aur-send-email-code"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <?php _e( 'Send Verification Code', 'advanced-user-registration' ); ?>
                    </button>
                </div>

                <div id="aur-email-code-input" class="hidden space-y-3">
                    <div>
                        <label for="aur-email-code" class="block text-sm font-medium text-gray-700">
                            <?php _e( 'Verification Code', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="text"
                               id="aur-email-code"
                               name="email_code"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-center font-mono text-lg"
                               placeholder="000000"
                               maxlength="6">
                    </div>

                    <button type="button"
                            id="aur-verify-email-code"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <?php _e( 'Verify Email', 'advanced-user-registration' ); ?>
                    </button>

                    <button type="button"
                            id="aur-resend-email-code"
                            class="w-full text-center text-blue-600 hover:text-blue-500 text-sm">
                        <?php _e( 'Resend Code', 'advanced-user-registration' ); ?>
                    </button>
                </div>
            </div>

            <!-- Phone Verification -->
            <div id="aur-phone-verification" class="hidden space-y-4">
                <h3 class="text-lg font-semibold text-gray-800"><?php _e( 'Verify Your Phone (Optional)', 'advanced-user-registration' ); ?></h3>
                <p class="text-gray-600"><?php _e( 'You can verify your phone number now or skip this step.', 'advanced-user-registration' ); ?></p>

                <div class="space-y-3">
                    <div>
                        <label for="aur-phone-number" class="block text-sm font-medium text-gray-700">
                            <?php _e( 'Phone Number', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="tel"
                               id="aur-phone-number"
                               name="phone"
                               placeholder="+1234567890"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="flex space-x-3">
                        <button type="button"
                                id="aur-send-phone-code"
                                class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <?php _e( 'Send SMS Code', 'advanced-user-registration' ); ?>
                        </button>

                        <button type="button"
                                id="aur-skip-phone"
                                class="flex-1 flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <?php _e( 'Skip', 'advanced-user-registration' ); ?>
                        </button>
                    </div>
                </div>

                <div id="aur-phone-code-input" class="hidden space-y-3">
                    <div>
                        <label for="aur-phone-code" class="block text-sm font-medium text-gray-700">
                            <?php _e( 'SMS Verification Code', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="text"
                               id="aur-phone-code"
                               name="phone_code"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-center font-mono text-lg"
                               placeholder="000000"
                               maxlength="6">
                    </div>

                    <div class="flex space-x-3">
                        <button type="button"
                                id="aur-verify-phone-code"
                                class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <?php _e( 'Verify Phone', 'advanced-user-registration' ); ?>
                        </button>

                        <button type="button"
                                id="aur-skip-phone-verification"
                                class="flex-1 flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <?php _e( 'Skip', 'advanced-user-registration' ); ?>
                        </button>
                    </div>

                    <button type="button"
                            id="aur-resend-phone-code"
                            class="w-full text-center text-purple-600 hover:text-purple-500 text-sm">
                        <?php _e( 'Resend SMS Code', 'advanced-user-registration' ); ?>
                    </button>
                </div>
            </div>

            <!-- User Profile Edit -->
            <div id="aur-profile-edit" class="hidden space-y-4">
                <h3 class="text-lg font-semibold text-gray-800"><?php _e( 'Edit Your Profile', 'advanced-user-registration' ); ?></h3>

                <div id="aur-profile-fields" class="space-y-3">
                    <!-- Profile fields will be dynamically generated -->
                </div>

                <button type="button"
                        id="aur-update-profile"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php _e( 'Update Profile', 'advanced-user-registration' ); ?>
                </button>
            </div>

            <!-- Success Message -->
            <div id="aur-success" class="hidden text-center py-8">
                <div class="text-green-600 text-6xl mb-4">✓</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2"><?php _e( 'Success!', 'advanced-user-registration' ); ?></h3>
                <p class="text-gray-600"><?php _e( 'Your account has been set up successfully.', 'advanced-user-registration' ); ?></p>
            </div>

            <?php if ( ! empty( $attributes['customCSS'] ) ) : ?>
                <style><?php echo esc_html( $attributes['customCSS'] ); ?></style>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get current user status
     *
     * @return array
     */
    private function get_user_status() {
        if ( ! is_user_logged_in() ) {
            return array(
                'logged_in' => false,
                'step' => 'login',
            );
        }

        $user_id = get_current_user_id();
        $email_confirmed = get_user_meta( $user_id, 'aur_email_confirmed', true );
        $phone_confirmed = get_user_meta( $user_id, 'aur_phone_confirmed', true );
        $needs_email_verification = get_user_meta( $user_id, 'aur_email_verification_required', true );

        return array(
            'logged_in' => true,
            'user_id' => $user_id,
            'email_confirmed' => $email_confirmed === '1',
            'phone_confirmed' => $phone_confirmed === '1',
            'needs_email_verification' => $needs_email_verification === '1',
            'step' => $this->determine_step( $email_confirmed, $phone_confirmed, $needs_email_verification ),
        );
    }

    /**
     * Determine the current step for the user
     *
     * @param string $email_confirmed Email confirmation status.
     * @param string $phone_confirmed Phone confirmation status.
     * @param string $needs_email_verification Whether email verification is required.
     * @return string
     */
    private function determine_step( $email_confirmed, $phone_confirmed, $needs_email_verification ) {
        if ( $needs_email_verification === '1' && $email_confirmed !== '1' ) {
            return 'email_verification';
        }

        if ( $phone_confirmed !== '1' ) {
            return 'phone_verification';
        }

        return 'profile_edit';
    }

    /**
     * Handle email verification link from email
     */
    public function handle_email_verification_link() {
        if ( ! isset( $_GET['aur_verify'] ) || $_GET['aur_verify'] !== 'email' ) {
            return;
        }

        $user_id = absint( $_GET['user_id'] ?? 0 );
        $code = sanitize_text_field( $_GET['code'] ?? '' );

        if ( ! $user_id || ! $code ) {
            return;
        }

        $database = AUR()->get_module( 'database' );
        $verified = $database->verify_code( $user_id, $code, 'email' );

        if ( $verified ) {
            update_user_meta( $user_id, 'aur_email_confirmed', '1' );
            update_user_meta( $user_id, 'aur_email_verification_required', '0' );

            // Log the user in if not already logged in
            if ( ! is_user_logged_in() ) {
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id );
            }

            wp_add_inline_script( 'aur-frontend', 'window.aurEmailVerified = true;' );
        } else {
            wp_add_inline_script( 'aur-frontend', 'window.aurEmailVerificationFailed = true;' );
        }
    }
}