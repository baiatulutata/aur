<?php
/**
 * Database operations class
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Database class
 */
class AUR_Database {

    /**
     * Create plugin tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // User verification codes table
        $table_name = $wpdb->prefix . 'aur_verification_codes';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            code varchar(10) NOT NULL,
            type enum('email','phone') NOT NULL,
            expires_at datetime NOT NULL,
            verified_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY code (code),
            KEY type (type),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // User custom fields table
        $table_name = $wpdb->prefix . 'aur_user_fields';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            field_name varchar(100) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type varchar(50) NOT NULL DEFAULT 'text',
            field_options text DEFAULT NULL,
            is_required tinyint(1) DEFAULT 0,
            is_editable tinyint(1) DEFAULT 1,
            field_order int(11) DEFAULT 0,
            source_plugin varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_name (field_name),
            KEY field_type (field_type),
            KEY is_required (is_required),
            KEY field_order (field_order)
        ) $charset_collate;";

        dbDelta( $sql );

        // Insert default fields
        $this->insert_default_fields();

        // Add custom user meta fields
        $this->add_user_meta_fields();
    }

    /**
     * Insert default user fields
     */
    private function insert_default_fields() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_user_fields';

        // Check if default fields already exist
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        if ( $count > 0 ) {
            return;
        }

        $default_fields = array(
            array(
                'field_name'  => 'user_login',
                'field_label' => __( 'Username', 'advanced-user-registration' ),
                'field_type'  => 'text',
                'is_required' => 1,
                'is_editable' => 0,
                'field_order' => 1,
            ),
            array(
                'field_name'  => 'user_email',
                'field_label' => __( 'Email Address', 'advanced-user-registration' ),
                'field_type'  => 'email',
                'is_required' => 1,
                'is_editable' => 1,
                'field_order' => 2,
            ),
            array(
                'field_name'  => 'user_pass',
                'field_label' => __( 'Password', 'advanced-user-registration' ),
                'field_type'  => 'password',
                'is_required' => 1,
                'is_editable' => 1,
                'field_order' => 3,
            ),
            array(
                'field_name'  => 'first_name',
                'field_label' => __( 'First Name', 'advanced-user-registration' ),
                'field_type'  => 'text',
                'is_required' => 0,
                'is_editable' => 1,
                'field_order' => 4,
            ),
            array(
                'field_name'  => 'last_name',
                'field_label' => __( 'Last Name', 'advanced-user-registration' ),
                'field_type'  => 'text',
                'is_required' => 0,
                'is_editable' => 1,
                'field_order' => 5,
            ),
            array(
                'field_name'  => 'aur_phone_number',
                'field_label' => __( 'Phone Number', 'advanced-user-registration' ),
                'field_type'  => 'tel',
                'is_required' => 0,
                'is_editable' => 1,
                'field_order' => 6,
            ),
        );

        foreach ( $default_fields as $field ) {
            $wpdb->insert( $table_name, $field );
        }
    }

    /**
     * Add custom user meta fields
     */
    private function add_user_meta_fields() {
        // These will be added to user meta when users are created
        $custom_meta_fields = array(
            'aur_email_confirmed'       => '0',
            'aur_phone_confirmed'       => '0',
            'aur_phone_number'          => '',
            'aur_email_verification_required' => '1',
        );

        // Store default values for new users
        update_option( 'aur_default_user_meta', $custom_meta_fields );
    }

    /**
     * Get user fields
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_user_fields( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'orderby' => 'field_order',
            'order'   => 'ASC',
            'where'   => '',
        );

        $args = wp_parse_args( $args, $defaults );

        $table_name = $wpdb->prefix . 'aur_user_fields';

        $sql = "SELECT * FROM $table_name";

        if ( ! empty( $args['where'] ) ) {
            $sql .= " WHERE " . $args['where'];
        }

        $sql .= " ORDER BY " . sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        return $wpdb->get_results( $sql );
    }

    /**
     * Get user field by name
     *
     * @param string $field_name Field name.
     * @return object|null
     */
    public function get_user_field( $field_name ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_user_fields';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE field_name = %s",
            $field_name
        ) );
    }

    /**
     * Add user field
     *
     * @param array $field_data Field data.
     * @return int|false
     */
    public function add_user_field( $field_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_user_fields';

        $defaults = array(
            'field_type'  => 'text',
            'is_required' => 0,
            'is_editable' => 1,
            'field_order' => 999,
        );

        $field_data = wp_parse_args( $field_data, $defaults );

        $result = $wpdb->insert( $table_name, $field_data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update user field
     *
     * @param int   $field_id   Field ID.
     * @param array $field_data Field data.
     * @return bool
     */
    public function update_user_field( $field_id, $field_data ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_user_fields';

        return $wpdb->update(
            $table_name,
            $field_data,
            array( 'id' => $field_id ),
            null,
            array( '%d' )
        );
    }

    /**
     * Delete user field
     *
     * @param int $field_id Field ID.
     * @return bool
     */
    public function delete_user_field( $field_id ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_user_fields';

        return $wpdb->delete(
            $table_name,
            array( 'id' => $field_id ),
            array( '%d' )
        );
    }

    /**
     * Store verification code
     *
     * @param int    $user_id User ID.
     * @param string $code    Verification code.
     * @param string $type    Code type (email/phone).
     * @param int    $expires_in Expiry time in minutes.
     * @return bool
     */
    public function store_verification_code( $user_id, $code, $type, $expires_in = 30 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_verification_codes';

        // Delete existing codes for this user and type
        $wpdb->delete(
            $table_name,
            array(
                'user_id' => $user_id,
                'type'    => $type,
            ),
            array( '%d', '%s' )
        );

        // Insert new code
        return $wpdb->insert(
            $table_name,
            array(
                'user_id'    => $user_id,
                'code'       => $code,
                'type'       => $type,
                'expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $expires_in * 60 ) ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );
    }

    /**
     * Verify code
     *
     * @param int    $user_id User ID.
     * @param string $code    Verification code.
     * @param string $type    Code type.
     * @return bool
     */
    public function verify_code( $user_id, $code, $type ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_verification_codes';

        // Check if code exists and is not expired
        $verification = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d 
             AND code = %s 
             AND type = %s 
             AND expires_at > %s 
             AND verified_at IS NULL",
            $user_id,
            $code,
            $type,
            gmdate( 'Y-m-d H:i:s' )
        ) );

        if ( ! $verification ) {
            return false;
        }

        // Mark as verified
        $wpdb->update(
            $table_name,
            array( 'verified_at' => gmdate( 'Y-m-d H:i:s' ) ),
            array( 'id' => $verification->id ),
            array( '%s' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * Clean expired verification codes
     */
    public function cleanup_expired_codes() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aur_verification_codes';

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE expires_at < %s",
            gmdate( 'Y-m-d H:i:s' )
        ) );
    }
}