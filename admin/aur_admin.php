<?php
/**
 * Admin functionality class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Admin class
 */
class AUR_Admin {

    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'plugin_action_links_' . AUR_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'User Registration', 'advanced-user-registration' ),
            __( 'User Registration', 'advanced-user-registration' ),
            'manage_options',
            'aur-settings',
            array( $this, 'admin_page' ),
            'dashicons-admin-users',
            30
        );

        add_submenu_page(
            'aur-settings',
            __( 'Settings', 'advanced-user-registration' ),
            __( 'Settings', 'advanced-user-registration' ),
            'manage_options',
            'aur-settings',
            array( $this, 'admin_page' )
        );

        add_submenu_page(
            'aur-settings',
            __( 'Field Manager', 'advanced-user-registration' ),
            __( 'Field Manager', 'advanced-user-registration' ),
            'manage_options',
            'aur-fields',
            array( $this, 'fields_page' )
        );

        add_submenu_page(
            'aur-settings',
            __( 'User Statistics', 'advanced-user-registration' ),
            __( 'Statistics', 'advanced-user-registration' ),
            'manage_options',
            'aur-statistics',
            array( $this, 'statistics_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'aur_settings', 'aur_email_settings' );
        register_setting( 'aur_settings', 'aur_sms_settings' );
        register_setting( 'aur_settings', 'aur_general_settings' );
    }

    /**
     * Add plugin action links
     *
     * @param array $links Action links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=aur-settings' ) . '">' . __( 'Settings', 'advanced-user-registration' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Main admin page
     */
    public function admin_page() {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap aur-admin-container">
            <h1><?php _e( 'Advanced User Registration Settings', 'advanced-user-registration' ); ?></h1>
            
            <nav class="nav-tab-wrapper aur-tab-nav">
                <a href="?page=aur-settings&tab=general" class="nav-tab aur-tab-button <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'General', 'advanced-user-registration' ); ?>
                </a>
                <a href="?page=aur-settings&tab=email" class="nav-tab aur-tab-button <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Email Settings', 'advanced-user-registration' ); ?>
                </a>
                <a href="?page=aur-settings&tab=sms" class="nav-tab aur-tab-button <?php echo $active_tab === 'sms' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'SMS Settings', 'advanced-user-registration' ); ?>
                </a>
            </nav>

            <form method="post" action="options.php" id="aur-settings-form">
                <?php
                switch ( $active_tab ) {
                    case 'email':
                        $this->render_email_settings();
                        break;
                    case 'sms':
                        $this->render_sms_settings();
                        break;
                    default:
                        $this->render_general_settings();
                        break;
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general settings tab
     */
    private function render_general_settings() {
        settings_fields( 'aur_settings' );
        $settings = get_option( 'aur_general_settings', array() );
        ?>
        <div class="aur-tab-content">
            <div class="aur-settings-section">
                <h3><?php _e( 'Registration Settings', 'advanced-user-registration' ); ?></h3>
                
                <div class="aur-form-group">
                    <label class="aur-form-label" for="auto_login_after_registration">
                        <?php _e( 'Auto Login After Registration', 'advanced-user-registration' ); ?>
                    </label>
                    <label class="aur-toggle">
                        <input type="checkbox" name="aur_general_settings[auto_login_after_registration]" 
                               id="auto_login_after_registration" value="1" 
                               <?php checked( $settings['auto_login_after_registration'] ?? 0, 1 ); ?>>
                        <span class="aur-toggle-handle"></span>
                    </label>
                    <p class="aur-help-text"><?php _e( 'Automatically log in users after successful registration', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <label class="aur-form-label" for="require_email_verification">
                        <?php _e( 'Require Email Verification', 'advanced-user-registration' ); ?>
                    </label>
                    <label class="aur-toggle">
                        <input type="checkbox" name="aur_general_settings[require_email_verification]" 
                               id="require_email_verification" value="1" 
                               <?php checked( $settings['require_email_verification'] ?? 1, 1 ); ?>>
                        <span class="aur-toggle-handle"></span>
                    </label>
                    <p class="aur-help-text"><?php _e( 'Users must verify their email address before accessing the site', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <label class="aur-form-label" for="verification_code_expiry">
                        <?php _e( 'Verification Code Expiry (minutes)', 'advanced-user-registration' ); ?>
                    </label>
                    <input type="number" class="aur-form-input" name="aur_general_settings[verification_code_expiry]" 
                           id="verification_code_expiry" value="<?php echo esc_attr( $settings['verification_code_expiry'] ?? 30 ); ?>" 
                           min="1" max="1440">
                    <p class="aur-help-text"><?php _e( 'How long verification codes remain valid (1-1440 minutes)', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <label class="aur-form-label" for="redirect_after_registration">
                        <?php _e( 'Redirect After Registration', 'advanced-user-registration' ); ?>
                    </label>
                    <input type="url" class="aur-form-input" name="aur_general_settings[redirect_after_registration]" 
                           id="redirect_after_registration" value="<?php echo esc_attr( $settings['redirect_after_registration'] ?? '' ); ?>">
                    <p class="aur-help-text"><?php _e( 'URL to redirect users after successful registration (optional)', 'advanced-user-registration' ); ?></p>
                </div>
            </div>

            <?php submit_button( __( 'Save Settings', 'advanced-user-registration' ), 'primary', 'submit', false, array( 'id' => 'aur-save-settings-btn' ) ); ?>
        </div>
        <?php
    }

    /**
     * Render email settings tab
     */
    private function render_email_settings() {
        settings_fields( 'aur_settings' );
        $settings = get_option( 'aur_email_settings', array() );
        ?>
        <div class="aur-tab-content">
            <div class="aur-settings-section">
                <h3><?php _e( 'Email Configuration', 'advanced-user-registration' ); ?></h3>
                
                <div class="aur-form-group">
                    <label class="aur-form-label" for="from_name">
                        <?php _e( 'From Name', 'advanced-user-registration' ); ?>
                    </label>
                    <input type="text" class="aur-form-input" name="aur_email_settings[from_name]" 
                           id="from_name" value="<?php echo esc_attr( $settings['from_name'] ?? get_bloginfo( 'name' ) ); ?>">
                    <p class="aur-help-text"><?php _e( 'Name that appears as the sender of verification emails', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <label class="aur-form-label" for="from_email">
                        <?php _e( 'From Email', 'advanced-user-registration' ); ?>
                    </label>
                    <input type="email" class="aur-form-input" name="aur_email_settings[from_email]" 
                           id="from_email" value="<?php echo esc_attr( $settings['from_email'] ?? get_option( 'admin_email' ) ); ?>">
                    <p class="aur-help-text"><?php _e( 'Email address that appears as the sender', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <label class="aur-form-label" for="email_subject">
                        <?php _e( 'Verification Email Subject', 'advanced-user-registration' ); ?>
                    </label>
                    <input type="text" class="aur-form-input" name="aur_email_settings[email_subject]" 
                           id="email_subject" value="<?php echo esc_attr( $settings['email_subject'] ?? 'Email Verification Required' ); ?>">
                    <p class="aur-help-text"><?php _e( 'Subject line for verification emails', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <label class="aur-form-label" for="custom_email_template">
                        <?php _e( 'Use Custom Email Template', 'advanced-user-registration' ); ?>
                    </label>
                    <label class="aur-toggle">
                        <input type="checkbox" name="aur_email_settings[custom_email_template]" 
                               id="custom_email_template" value="1" 
                               <?php checked( $settings['custom_email_template'] ?? 0, 1 ); ?>>
                        <span class="aur-toggle-handle"></span>
                    </label>
                    <p class="aur-help-text"><?php _e( 'Enable custom email template editing', 'advanced-user-registration' ); ?></p>
                </div>
            </div>

            <?php submit_button( __( 'Save Email Settings', 'advanced-user-registration' ), 'primary', 'submit', false, array( 'id' => 'aur-save-settings-btn' ) ); ?>
        </div>
        <?php
    }

    /**
     * Render SMS settings tab
     */
    private function render_sms_settings() {
        settings_fields( 'aur_settings' );
        $settings = get_option( 'aur_sms_settings', array() );
        ?>
        <div class="aur-tab-content">
            <div class="aur-settings-section">
                <h3><?php _e( 'SMS Configuration', 'advanced-user-registration' ); ?></h3>
                
                <div class="aur-form-group">
                    <label class="aur-form-label" for="sms_provider">
                        <?php _e( 'SMS Provider', 'advanced-user-registration' ); ?>
                    </label>
                    <select class="aur-form-select" name="aur_sms_settings[provider]" id="sms_provider">
                        <option value="twilio" <?php selected( $settings['provider'] ?? 'twilio', 'twilio' ); ?>>
                            <?php _e( 'Twilio', 'advanced-user-registration' ); ?>
                        </option>
                        <option value="mock" <?php selected( $settings['provider'] ?? 'twilio', 'mock' ); ?>>
                            <?php _e( 'Mock (Development)', 'advanced-user-registration' ); ?>
                        </option>
                    </select>
                    <p class="aur-help-text"><?php _e( 'Choose your SMS provider', 'advanced-user-registration' ); ?></p>
                </div>

                <div id="aur-provider-twilio" class="aur-provider-section" style="<?php echo ( $settings['provider'] ?? 'twilio' ) !== 'twilio' ? 'display: none;' : ''; ?>">
                    <h4><?php _e( 'Twilio Settings', 'advanced-user-registration' ); ?></h4>
                    
                    <div class="aur-form-group">
                        <label class="aur-form-label" for="twilio_sid">
                            <?php _e( 'Account SID', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="text" class="aur-form-input" name="aur_sms_settings[twilio_sid]" 
                               id="twilio_sid" value="<?php echo esc_attr( $settings['twilio_sid'] ?? '' ); ?>">
                        <p class="aur-help-text"><?php _e( 'Your Twilio Account SID', 'advanced-user-registration' ); ?></p>
                    </div>

                    <div class="aur-form-group">
                        <label class="aur-form-label" for="twilio_token">
                            <?php _e( 'Auth Token', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="password" class="aur-form-input" name="aur_sms_settings[twilio_token]" 
                               id="twilio_token" value="<?php echo esc_attr( $settings['twilio_token'] ?? '' ); ?>">
                        <p class="aur-help-text"><?php _e( 'Your Twilio Auth Token', 'advanced-user-registration' ); ?></p>
                    </div>

                    <div class="aur-form-group">
                        <label class="aur-form-label" for="twilio_phone">
                            <?php _e( 'Phone Number', 'advanced-user-registration' ); ?>
                        </label>
                        <input type="tel" class="aur-form-input" name="aur_sms_settings[twilio_phone]" 
                               id="twilio_phone" value="<?php echo esc_attr( $settings['twilio_phone'] ?? '' ); ?>" 
                               placeholder="+1234567890">
                        <p class="aur-help-text"><?php _e( 'Your Twilio phone number (include country code)', 'advanced-user-registration' ); ?></p>
                    </div>
                </div>

                <div id="aur-provider-mock" class="aur-provider-section" style="<?php echo ( $settings['provider'] ?? 'twilio' ) !== 'mock' ? 'display: none;' : ''; ?>">
                    <h4><?php _e( 'Mock SMS (Development Only)', 'advanced-user-registration' ); ?></h4>
                    <p><?php _e( 'Mock SMS provider is for development purposes only. SMS messages will be logged but not actually sent. You will receive test emails instead.', 'advanced-user-registration' ); ?></p>
                </div>

                <div class="aur-form-group">
                    <h4><?php _e( 'Test SMS Configuration', 'advanced-user-registration' ); ?></h4>
                    <div style="display: flex; gap: 10px; align-items: end;">
                        <div style="flex: 1;">
                            <label class="aur-form-label" for="aur_test_phone">
                                <?php _e( 'Test Phone Number', 'advanced-user-registration' ); ?>
                            </label>
                            <input type="tel" class="aur-form-input" id="aur_test_phone" 
                                   placeholder="+1234567890">
                        </div>
                        <button type="button" class="aur-btn aur-btn-secondary" id="aur-test-sms-btn">
                            <?php _e( 'Send Test SMS', 'advanced-user-registration' ); ?>
                        </button>
                    </div>
                    <p class="aur-help-text"><?php _e( 'Test your SMS configuration. A test message will be sent to the specified number and ibaldazar@yahoo.com', 'advanced-user-registration' ); ?></p>
                </div>
            </div>

            <?php submit_button( __( 'Save SMS Settings', 'advanced-user-registration' ), 'primary', 'submit', false, array( 'id' => 'aur-save-settings-btn' ) ); ?>
        </div>
        <?php
    }

    /**
     * Fields management page
     */
    public function fields_page() {
        $database = AUR()->get_module( 'database' );
        $fields = $database->get_user_fields();
        ?>
        <div class="wrap aur-admin-container">
            <h1><?php _e( 'User Field Manager', 'advanced-user-registration' ); ?></h1>
            
            <div class="aur-settings-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <p><?php _e( 'Manage user registration fields. Drag and drop to reorder fields.', 'advanced-user-registration' ); ?></p>
                    <button type="button" class="aur-btn aur-btn-primary" id="aur-add-field-btn">
                        <?php _e( 'Add New Field', 'advanced-user-registration' ); ?>
                    </button>
                </div>

                <table class="aur-fields-table" id="aur-fields-table">
                    <thead>
                        <tr>
                            <th width="30"><?php _e( 'Order', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Field Name', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Label', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Type', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Required', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Editable', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Actions', 'advanced-user-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $fields as $field ) : ?>
                            <tr class="aur-field-row" data-field-id="<?php echo esc_attr( $field->id ); ?>" data-field-order="<?php echo esc_attr( $field->field_order ); ?>">
                                <td>
                                    <span class="aur-drag-handle dashicons dashicons-menu"></span>
                                </td>
                                <td>
                                    <input type="text" class="aur-field-name" value="<?php echo esc_attr( $field->field_name ); ?>" 
                                           <?php echo in_array( $field->field_name, array( 'user_login', 'user_email', 'user_pass' ) ) ? 'readonly' : ''; ?>>
                                </td>
                                <td>
                                    <input type="text" class="aur-field-label" value="<?php echo esc_attr( $field->field_label ); ?>">
                                </td>
                                <td>
                                    <select class="aur-field-type">
                                        <option value="text" <?php selected( $field->field_type, 'text' ); ?>><?php _e( 'Text', 'advanced-user-registration' ); ?></option>
                                        <option value="email" <?php selected( $field->field_type, 'email' ); ?>><?php _e( 'Email', 'advanced-user-registration' ); ?></option>
                                        <option value="password" <?php selected( $field->field_type, 'password' ); ?>><?php _e( 'Password', 'advanced-user-registration' ); ?></option>
                                        <option value="tel" <?php selected( $field->field_type, 'tel' ); ?>><?php _e( 'Phone', 'advanced-user-registration' ); ?></option>
                                        <option value="textarea" <?php selected( $field->field_type, 'textarea' ); ?>><?php _e( 'Textarea', 'advanced-user-registration' ); ?></option>
                                        <option value="select" <?php selected( $field->field_type, 'select' ); ?>><?php _e( 'Select', 'advanced-user-registration' ); ?></option>
                                    </select>
                                    <div class="aur-field-options" style="<?php echo in_array( $field->field_type, array( 'select', 'radio', 'checkbox' ) ) ? '' : 'display: none;'; ?>">
                                        <input type="text" class="aur-field-options-input" value="<?php echo esc_attr( $field->field_options ); ?>" 
                                               placeholder="<?php _e( 'Option1,Option2,Option3', 'advanced-user-registration' ); ?>">
                                    </div>
                                </td>
                                <td>
                                    <input type="checkbox" class="aur-field-required" <?php checked( $field->is_required, '1' ); ?>>
                                </td>
                                <td>
                                    <input type="checkbox" class="aur-field-editable" <?php checked( $field->is_editable, '1' ); ?>>
                                </td>
                                <td>
                                    <?php if ( ! in_array( $field->field_name, array( 'user_login', 'user_email', 'user_pass' ) ) ) : ?>
                                        <button type="button" class="aur-btn aur-btn-danger aur-btn-small aur-delete-field" 
                                                data-field-id="<?php echo esc_attr( $field->id ); ?>">
                                            <?php _e( 'Delete', 'advanced-user-registration' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <button type="button" class="aur-btn aur-btn-primary" id="aur-save-fields-btn">
                        <?php _e( 'Save Fields', 'advanced-user-registration' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Statistics page
     */
    public function statistics_page() {
        $stats = $this->get_user_statistics();
        ?>
        <div class="wrap aur-admin-container">
            <h1><?php _e( 'User Registration Statistics', 'advanced-user-registration' ); ?></h1>
            
            <div class="aur-stats-grid">
                <div class="aur-stats-card">
                    <div class="aur-stats-number"><?php echo esc_html( $stats['total_users'] ); ?></div>
                    <div class="aur-stats-label"><?php _e( 'Total Users', 'advanced-user-registration' ); ?></div>
                </div>
                
                <div class="aur-stats-card">
                    <div class="aur-stats-number"><?php echo esc_html( $stats['verified_emails'] ); ?></div>
                    <div class="aur-stats-label"><?php _e( 'Verified Emails', 'advanced-user-registration' ); ?></div>
                </div>
                
                <div class="aur-stats-card">
                    <div class="aur-stats-number"><?php echo esc_html( $stats['verified_phones'] ); ?></div>
                    <div class="aur-stats-label"><?php _e( 'Verified Phones', 'advanced-user-registration' ); ?></div>
                </div>
                
                <div class="aur-stats-card">
                    <div class="aur-stats-number"><?php echo esc_html( $stats['registrations_today'] ); ?></div>
                    <div class="aur-stats-label"><?php _e( 'Today\'s Registrations', 'advanced-user-registration' ); ?></div>
                </div>
            </div>

            <div class="aur-settings-section">
                <h3><?php _e( 'Recent Registrations', 'advanced-user-registration' ); ?></h3>
                <table class="aur-fields-table">
                    <thead>
                        <tr>
                            <th><?php _e( 'Username', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Email', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Email Verified', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Phone Verified', 'advanced-user-registration' ); ?></th>
                            <th><?php _e( 'Registration Date', 'advanced-user-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['recent_users'] as $user ) : ?>
                            <tr>
                                <td><?php echo esc_html( $user->user_login ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td>
                                    <?php echo get_user_meta( $user->ID, 'aur_email_confirmed', true ) === '1' ? 
                                        '<span style="color: green;">✓</span>' : 
                                        '<span style="color: red;">✗</span>'; ?>
                                </td>
                                <td>
                                    <?php echo get_user_meta( $user->ID, 'aur_phone_confirmed', true ) === '1' ? 
                                        '<span style="color: green;">✓</span>' : 
                                        '<span style="color: gray;">-</span>'; ?>
                                </td>
                                <td><?php echo esc_html( $user->user_registered ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Get user statistics
     *
     * @return array
     */
    private function get_user_statistics() {
        global $wpdb;

        $total_users = get_users( array( 'count_total' => true ) );
        
        $verified_emails = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'aur_email_confirmed' 
            AND meta_value = '1'
        " );

        $verified_phones = $wpdb->get_var( "
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'aur_phone_confirmed' 
            AND meta_value = '1'
        " );

        $registrations_today = $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$wpdb->users} 
            WHERE DATE(user_registered) = %s
        ", gmdate( 'Y-m-d' ) ) );

        $recent_users = get_users( array(
            'number' => 10,
            'orderby' => 'registered',
            'order' => 'DESC'
        ) );

        return array(
            'total_users' => $total_users,
            'verified_emails' => intval( $verified_emails ),
            'verified_phones' => intval( $verified_phones ),
            'registrations_today' => intval( $registrations_today ),
            'recent_users' => $recent_users,
        );
    }
}