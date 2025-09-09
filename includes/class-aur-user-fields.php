<?php
/**
 * User fields management class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_User_Fields class
 */
class AUR_User_Fields {

    /**
     * Initialize user fields hooks
     */
    public function init() {
        add_action( 'user_register', array( $this, 'save_custom_fields' ), 10, 1 );
        add_action( 'personal_options_update', array( $this, 'save_custom_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_custom_fields' ) );
        add_action( 'show_user_profile', array( $this, 'show_custom_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'show_custom_fields' ) );
        add_filter( 'user_contactmethods', array( $this, 'add_contact_methods' ) );
    }

    /**
     * Get available field types
     *
     * @return array
     */
    public function get_field_types() {
        return apply_filters( 'aur_field_types', array(
            'text'      => __( 'Text', 'advanced-user-registration' ),
            'email'     => __( 'Email', 'advanced-user-registration' ),
            'password'  => __( 'Password', 'advanced-user-registration' ),
            'tel'       => __( 'Phone', 'advanced-user-registration' ),
            'url'       => __( 'URL', 'advanced-user-registration' ),
            'number'    => __( 'Number', 'advanced-user-registration' ),
            'date'      => __( 'Date', 'advanced-user-registration' ),
            'textarea'  => __( 'Textarea', 'advanced-user-registration' ),
            'select'    => __( 'Select Dropdown', 'advanced-user-registration' ),
            'radio'     => __( 'Radio Buttons', 'advanced-user-registration' ),
            'checkbox'  => __( 'Checkbox', 'advanced-user-registration' ),
            'file'      => __( 'File Upload', 'advanced-user-registration' ),
        ) );
    }

    /**
     * Get WordPress core user fields
     *
     * @return array
     */
    public function get_core_fields() {
        return array(
            'user_login'    => __( 'Username', 'advanced-user-registration' ),
            'user_email'    => __( 'Email', 'advanced-user-registration' ),
            'user_pass'     => __( 'Password', 'advanced-user-registration' ),
            'first_name'    => __( 'First Name', 'advanced-user-registration' ),
            'last_name'     => __( 'Last Name', 'advanced-user-registration' ),
            'display_name'  => __( 'Display Name', 'advanced-user-registration' ),
            'user_url'      => __( 'Website', 'advanced-user-registration' ),
            'description'   => __( 'Bio', 'advanced-user-registration' ),
        );
    }

    /**
     * Get custom AUR fields
     *
     * @return array
     */
    public function get_aur_fields() {
        return array(
            'aur_phone_number'       => __( 'Phone Number', 'advanced-user-registration' ),
            'aur_email_confirmed'    => __( 'Email Confirmed', 'advanced-user-registration' ),
            'aur_phone_confirmed'    => __( 'Phone Confirmed', 'advanced-user-registration' ),
        );
    }

    /**
     * Get fields from other plugins
     *
     * @return array
     */
    public function get_plugin_fields() {
        $plugin_fields = array();

        // WooCommerce fields
        if ( class_exists( 'WooCommerce' ) ) {
            $plugin_fields = array_merge( $plugin_fields, array(
                'billing_first_name'   => __( 'Billing First Name (WooCommerce)', 'advanced-user-registration' ),
                'billing_last_name'    => __( 'Billing Last Name (WooCommerce)', 'advanced-user-registration' ),
                'billing_company'      => __( 'Billing Company (WooCommerce)', 'advanced-user-registration' ),
                'billing_address_1'    => __( 'Billing Address 1 (WooCommerce)', 'advanced-user-registration' ),
                'billing_address_2'    => __( 'Billing Address 2 (WooCommerce)', 'advanced-user-registration' ),
                'billing_city'         => __( 'Billing City (WooCommerce)', 'advanced-user-registration' ),
                'billing_postcode'     => __( 'Billing Postcode (WooCommerce)', 'advanced-user-registration' ),
                'billing_country'      => __( 'Billing Country (WooCommerce)', 'advanced-user-registration' ),
                'billing_state'        => __( 'Billing State (WooCommerce)', 'advanced-user-registration' ),
                'billing_phone'        => __( 'Billing Phone (WooCommerce)', 'advanced-user-registration' ),
                'billing_email'        => __( 'Billing Email (WooCommerce)', 'advanced-user-registration' ),
            ) );
        }

        // BuddyPress fields
        if ( function_exists( 'bp_is_active' ) ) {
            $plugin_fields = array_merge( $plugin_fields, array(
                'bp_location'     => __( 'Location (BuddyPress)', 'advanced-user-registration' ),
                'bp_activity'     => __( 'Activity (BuddyPress)', 'advanced-user-registration' ),
                'bp_interests'    => __( 'Interests (BuddyPress)', 'advanced-user-registration' ),
            ) );
        }

        // Ultimate Member fields
        if ( class_exists( 'UM' ) ) {
            $plugin_fields = array_merge( $plugin_fields, array(
                'um_user_profile_url_slug_base'    => __( 'Profile URL (Ultimate Member)', 'advanced-user-registration' ),
                'um_member_directory_data'         => __( 'Member Directory (Ultimate Member)', 'advanced-user-registration' ),
            ) );
        }

        return apply_filters( 'aur_plugin_fields', $plugin_fields );
    }

    /**
     * Get all available fields
     *
     * @return array
     */
    public function get_all_available_fields() {
        return array_merge(
            $this->get_core_fields(),
            $this->get_aur_fields(),
            $this->get_plugin_fields()
        );
    }

    /**
     * Validate field value
     *
     * @param mixed  $value      Field value.
     * @param string $field_type Field type.
     * @param array  $field_config Field configuration.
     * @return bool|WP_Error
     */
    public function validate_field( $value, $field_type, $field_config = array() ) {
        $value = trim( $value );

        // Check if required field is empty
        if ( ! empty( $field_config['is_required'] ) && empty( $value ) ) {
            return new WP_Error( 'field_required',
                sprintf( __( '%s is required.', 'advanced-user-registration' ),
                    $field_config['field_label'] ?? __( 'Field', 'advanced-user-registration' )
                )
            );
        }

        // Skip validation if field is empty and not required
        if ( empty( $value ) ) {
            return true;
        }

        // Type-specific validation
        switch ( $field_type ) {
            case 'email':
                if ( ! is_email( $value ) ) {
                    return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'advanced-user-registration' ) );
                }
                break;

            case 'url':
                if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return new WP_Error( 'invalid_url', __( 'Please enter a valid URL.', 'advanced-user-registration' ) );
                }
                break;

            case 'tel':
                if ( ! $this->validate_phone_number( $value ) ) {
                    return new WP_Error( 'invalid_phone', __( 'Please enter a valid phone number.', 'advanced-user-registration' ) );
                }
                break;

            case 'number':
                if ( ! is_numeric( $value ) ) {
                    return new WP_Error( 'invalid_number', __( 'Please enter a valid number.', 'advanced-user-registration' ) );
                }
                break;

            case 'date':
                if ( ! $this->validate_date( $value ) ) {
                    return new WP_Error( 'invalid_date', __( 'Please enter a valid date.', 'advanced-user-registration' ) );
                }
                break;

            case 'password':
                if ( strlen( $value ) < 6 ) {
                    return new WP_Error( 'weak_password', __( 'Password must be at least 6 characters long.', 'advanced-user-registration' ) );
                }
                break;

            case 'file':
                // File validation would be handled separately during upload
                break;
        }

        return true;
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number.
     * @return bool
     */
    private function validate_phone_number( $phone ) {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace( '/[^\d+]/', '', $phone );

        // Check for international format
        if ( preg_match( '/^\+[1-9]\d{10,14}$/', $cleaned ) ) {
            return true;
        }

        // Check for US format without country code
        if ( preg_match( '/^\d{10}$/', $cleaned ) ) {
            return true;
        }

        return false;
    }

    /**
     * Validate date format
     *
     * @param string $date Date string.
     * @return bool
     */
    private function validate_date( $date ) {
        $formats = array( 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y-m-d H:i:s' );

        foreach ( $formats as $format ) {
            $d = DateTime::createFromFormat( $format, $date );
            if ( $d && $d->format( $format ) === $date ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize field value based on type
     *
     * @param mixed  $value      Field value.
     * @param string $field_type Field type.
     * @return mixed
     */
    public function sanitize_field( $value, $field_type ) {
        switch ( $field_type ) {
            case 'email':
                return sanitize_email( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'tel':
                return preg_replace( '/[^\d+\-\(\)\s]/', '', $value );

            case 'number':
                return floatval( $value );

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'password':
                return $value; // Don't sanitize passwords

            case 'file':
                return sanitize_file_name( $value );

            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Save custom user fields
     *
     * @param int $user_id User ID.
     */
    public function save_custom_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) && get_current_user_id() !== $user_id ) {
            return;
        }

        $database = AUR()->get_module( 'database' );
        $custom_fields = $database->get_user_fields( array(
            'where' => "field_name LIKE 'aur_%' OR source_plugin IS NOT NULL"
        ) );

        foreach ( $custom_fields as $field ) {
            if ( isset( $_POST[ $field->field_name ] ) ) {
                $value = $_POST[ $field->field_name ];

                // Validate field
                $validation = $this->validate_field( $value, $field->field_type, (array) $field );
                if ( is_wp_error( $validation ) ) {
                    continue;
                }

                // Sanitize field
                $value = $this->sanitize_field( $value, $field->field_type );

                // Save field
                update_user_meta( $user_id, $field->field_name, $value );
            }
        }
    }

    /**
     * Show custom fields in user profile
     *
     * @param WP_User $user User object.
     */
    public function show_custom_fields( $user ) {
        $database = AUR()->get_module( 'database' );
        $custom_fields = $database->get_user_fields( array(
            'where' => "field_name LIKE 'aur_%' AND is_editable = 1"
        ) );

        if ( empty( $custom_fields ) ) {
            return;
        }
        ?>
        <h3><?php _e( 'Advanced User Registration Fields', 'advanced-user-registration' ); ?></h3>
        <table class="form-table">
            <?php foreach ( $custom_fields as $field ) : ?>
                <?php if ( $field->field_name === 'aur_email_confirmed' || $field->field_name === 'aur_phone_confirmed' ) : ?>
                    <!-- Skip confirmation fields in profile -->
                    <?php continue; ?>
                <?php endif; ?>

                <tr>
                    <th>
                        <label for="<?php echo esc_attr( $field->field_name ); ?>">
                            <?php echo esc_html( $field->field_label ); ?>
                            <?php if ( $field->is_required ) : ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                    </th>
                    <td>
                        <?php $this->render_field( $field, get_user_meta( $user->ID, $field->field_name, true ) ); ?>
                        <?php if ( ! empty( $field->field_description ) ) : ?>
                            <p class="description"><?php echo esc_html( $field->field_description ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <?php
    }

    /**
     * Render a field based on its type
     *
     * @param object $field Field configuration.
     * @param mixed  $value Current field value.
     */
    public function render_field( $field, $value = '' ) {
        $field_id = esc_attr( $field->field_name );
        $field_name = esc_attr( $field->field_name );
        $field_value = esc_attr( $value );
        $required = $field->is_required ? 'required' : '';

        switch ( $field->field_type ) {
            case 'textarea':
                ?>
                <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                          rows="5" cols="30" <?php echo $required; ?>><?php echo esc_textarea( $value ); ?></textarea>
                <?php
                break;

            case 'select':
                $options = $this->parse_field_options( $field->field_options );
                ?>
                <select id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" <?php echo $required; ?>>
                    <option value=""><?php _e( 'Choose an option', 'advanced-user-registration' ); ?></option>
                    <?php foreach ( $options as $option_value => $option_label ) : ?>
                        <option value="<?php echo esc_attr( $option_value ); ?>"
                            <?php selected( $value, $option_value ); ?>>
                            <?php echo esc_html( $option_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case 'radio':
                $options = $this->parse_field_options( $field->field_options );
                ?>
                <fieldset>
                    <?php foreach ( $options as $option_value => $option_label ) : ?>
                        <label>
                            <input type="radio" name="<?php echo $field_name; ?>"
                                   value="<?php echo esc_attr( $option_value ); ?>"
                                <?php checked( $value, $option_value ); ?> <?php echo $required; ?>>
                            <?php echo esc_html( $option_label ); ?>
                        </label><br>
                    <?php endforeach; ?>
                </fieldset>
                <?php
                break;

            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                           value="1" <?php checked( $value, '1' ); ?> <?php echo $required; ?>>
                    <?php echo esc_html( $field->field_label ); ?>
                </label>
                <?php
                break;

            case 'file':
                ?>
                <input type="file" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" <?php echo $required; ?>>
                <?php if ( $value ) : ?>
                <p><?php _e( 'Current file:', 'advanced-user-registration' ); ?>
                    <a href="<?php echo esc_url( $value ); ?>" target="_blank"><?php echo basename( $value ); ?></a>
                </p>
            <?php endif; ?>
                <?php
                break;

            default:
                ?>
                <input type="<?php echo esc_attr( $field->field_type ); ?>"
                       id="<?php echo $field_id; ?>"
                       name="<?php echo $field_name; ?>"
                       value="<?php echo $field_value; ?>"
                       class="regular-text" <?php echo $required; ?>>
                <?php
                break;
        }
    }

    /**
     * Parse field options from string
     *
     * @param string $options_string Options string.
     * @return array
     */
    private function parse_field_options( $options_string ) {
        $options = array();

        if ( empty( $options_string ) ) {
            return $options;
        }

        // Try to decode as JSON first
        $json_options = json_decode( $options_string, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_options ) ) {
            return $json_options;
        }

        // Parse as comma-separated values
        $option_list = explode( ',', $options_string );
        foreach ( $option_list as $option ) {
            $option = trim( $option );
            if ( ! empty( $option ) ) {
                // Check if it's key:value format
                if ( strpos( $option, ':' ) !== false ) {
                    list( $key, $value ) = explode( ':', $option, 2 );
                    $options[ trim( $key ) ] = trim( $value );
                } else {
                    $options[ $option ] = $option;
                }
            }
        }

        return $options;
    }

    /**
     * Add custom contact methods
     *
     * @param array $methods Contact methods.
     * @return array
     */
    public function add_contact_methods( $methods ) {
        $methods['aur_phone_number'] = __( 'Phone Number', 'advanced-user-registration' );
        return $methods;
    }
}