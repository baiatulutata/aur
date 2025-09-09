<?php
/**
 * Field management class for admin
 *
 * @package AdvancedUserRegistration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AUR_Field_Manager class
 */
class AUR_Field_Manager {

    /**
     * Initialize field manager hooks
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'wp_ajax_aur_save_fields', array( $this, 'save_fields' ) );
        add_action( 'wp_ajax_aur_delete_field', array( $this, 'delete_field' ) );
        add_action( 'wp_ajax_aur_add_field', array( $this, 'add_field' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_sortable_script' ) );
    }

    /**
     * Register REST API routes for field management
     */
    public function register_rest_routes() {
        register_rest_route( 'aur/v1', '/save-fields', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_save_fields' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( 'aur/v1', '/delete-field', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_delete_field' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( 'aur/v1', '/add-field', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_add_field' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( 'aur/v1', '/get-plugin-fields', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'rest_get_plugin_fields' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Enqueue sortable script for field ordering
     */
    public function enqueue_sortable_script( $hook ) {
        if ( strpos( $hook, 'aur-fields' ) === false ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_add_inline_script( 'aur-admin', '
            jQuery(document).ready(function($) {
                if (typeof Sortable === "undefined" && $("#aur-fields-table tbody").length) {
                    $("#aur-fields-table tbody").sortable({
                        handle: ".aur-drag-handle",
                        axis: "y",
                        update: function(event, ui) {
                            // Update field order when dragging is complete
                            $("#aur-fields-table tbody tr").each(function(index) {
                                $(this).attr("data-field-order", index + 1);
                            });
                        }
                    });
                }
            });
        ' );
    }

    /**
     * Save fields via AJAX
     */
    public function save_fields() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'aur_admin_nonce' ) ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $fields = $_POST['fields'] ?? array();
        $result = $this->update_fields( $fields );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => __( 'Fields saved successfully', 'advanced-user-registration' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'] ?? __( 'Failed to save fields', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * Delete field via AJAX
     */
    public function delete_field() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'aur_admin_nonce' ) ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $field_id = absint( $_POST['field_id'] ?? 0 );

        if ( ! $field_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid field ID', 'advanced-user-registration' )
            ) );
        }

        $database = AUR()->get_module( 'database' );
        $field = $database->get_user_field_by_id( $field_id );

        // Prevent deletion of core fields
        if ( $field && in_array( $field->field_name, array( 'user_login', 'user_email', 'user_pass' ) ) ) {
            wp_send_json_error( array(
                'message' => __( 'Cannot delete core fields', 'advanced-user-registration' )
            ) );
        }

        $result = $database->delete_user_field( $field_id );

        if ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Field deleted successfully', 'advanced-user-registration' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to delete field', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * Add new field via AJAX
     */
    public function add_field() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'aur_admin_nonce' ) ) {
            wp_die( __( 'Security check failed', 'advanced-user-registration' ), 403 );
        }

        $field_data = $_POST['field_data'] ?? array();
        $result = $this->create_field( $field_data );

        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => __( 'Field added successfully', 'advanced-user-registration' ),
                'field_id' => $result['field_id']
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'] ?? __( 'Failed to add field', 'advanced-user-registration' )
            ) );
        }
    }

    /**
     * Update multiple fields
     *
     * @param array $fields Fields data.
     * @return array
     */
    public function update_fields( $fields ) {
        $database = AUR()->get_module( 'database' );
        $updated = 0;
        $errors = array();

        foreach ( $fields as $field_data ) {
            $field_id = absint( $field_data['id'] ?? 0 );

            if ( ! $field_id ) {
                continue;
            }

            // Sanitize field data
            $sanitized_data = $this->sanitize_field_data( $field_data );

            // Validate field data
            $validation = $this->validate_field_data( $sanitized_data );
            if ( is_wp_error( $validation ) ) {
                $errors[] = $validation->get_error_message();
                continue;
            }

            // Update field
            $result = $database->update_user_field( $field_id, $sanitized_data );
            if ( $result ) {
                $updated++;
            } else {
                $errors[] = sprintf( __( 'Failed to update field: %s', 'advanced-user-registration' ), $sanitized_data['field_label'] );
            }
        }

        return array(
            'success' => $updated > 0,
            'updated' => $updated,
            'errors' => $errors,
            'message' => $updated > 0 ? sprintf( __( '%d fields updated successfully', 'advanced-user-registration' ), $updated ) : __( 'No fields were updated', 'advanced-user-registration' )
        );
    }

    /**
     * Create new field
     *
     * @param array $field_data Field data.
     * @return array
     */
    public function create_field( $field_data ) {
        $database = AUR()->get_module( 'database' );

        // Sanitize field data
        $sanitized_data = $this->sanitize_field_data( $field_data );

        // Validate field data
        $validation = $this->validate_field_data( $sanitized_data );
        if ( is_wp_error( $validation ) ) {
            return array(
                'success' => false,
                'message' => $validation->get_error_message()
            );
        }

        // Check if field name already exists
        $existing_field = $database->get_user_field( $sanitized_data['field_name'] );
        if ( $existing_field ) {
            return array(
                'success' => false,
                'message' => __( 'Field name already exists', 'advanced-user-registration' )
            );
        }

        // Create field
        $field_id = $database->add_user_field( $sanitized_data );

        if ( $field_id ) {
            return array(
                'success' => true,
                'field_id' => $field_id,
                'message' => __( 'Field created successfully', 'advanced-user-registration' )
            );
        } else {
            return array(
                'success' => false,
                'message' => __( 'Failed to create field', 'advanced-user-registration' )
            );
        }
    }

    /**
     * Sanitize field data
     *
     * @param array $field_data Raw field data.
     * @return array
     */
    private function sanitize_field_data( $field_data ) {
        return array(
            'field_name'    => sanitize_key( $field_data['field_name'] ?? '' ),
            'field_label'   => sanitize_text_field( $field_data['field_label'] ?? '' ),
            'field_type'    => sanitize_text_field( $field_data['field_type'] ?? 'text' ),
            'field_options' => sanitize_textarea_field( $field_data['field_options'] ?? '' ),
            'is_required'   => absint( $field_data['is_required'] ?? 0 ),
            'is_editable'   => absint( $field_data['is_editable'] ?? 1 ),
            'field_order'   => absint( $field_data['field_order'] ?? 999 ),
            'source_plugin' => sanitize_text_field( $field_data['source_plugin'] ?? '' ),
        );
    }

    /**
     * Validate field data
     *
     * @param array $field_data Sanitized field data.
     * @return bool|WP_Error
     */
    private function validate_field_data( $field_data ) {
        // Required fields
        if ( empty( $field_data['field_name'] ) ) {
            return new WP_Error( 'missing_field_name', __( 'Field name is required', 'advanced-user-registration' ) );
        }

        if ( empty( $field_data['field_label'] ) ) {
            return new WP_Error( 'missing_field_label', __( 'Field label is required', 'advanced-user-registration' ) );
        }

        // Validate field name format
        if ( ! preg_match( '/^[a-z0-9_]+$/', $field_data['field_name'] ) ) {
            return new WP_Error( 'invalid_field_name', __( 'Field name can only contain lowercase letters, numbers, and underscores', 'advanced-user-registration' ) );
        }

        // Validate field type
        $user_fields = AUR()->get_module( 'user_fields' );
        $valid_types = array_keys( $user_fields->get_field_types() );

        if ( ! in_array( $field_data['field_type'], $valid_types ) ) {
            return new WP_Error( 'invalid_field_type', __( 'Invalid field type', 'advanced-user-registration' ) );
        }

        // Validate field options for select/radio/checkbox types
        if ( in_array( $field_data['field_type'], array( 'select', 'radio', 'checkbox' ) ) ) {
            if ( empty( $field_data['field_options'] ) ) {
                return new WP_Error( 'missing_field_options', __( 'Field options are required for this field type', 'advanced-user-registration' ) );
            }
        }

        return true;
    }

    /**
     * Get available plugin fields
     *
     * @return array
     */
    public function get_available_plugin_fields() {
        $user_fields = AUR()->get_module( 'user_fields' );
        return $user_fields->get_plugin_fields();
    }

    /**
     * Import field from plugin
     *
     * @param string $field_name Field name from plugin.
     * @param string $source_plugin Source plugin name.
     * @return array
     */
    public function import_plugin_field( $field_name, $source_plugin ) {
        $available_fields = $this->get_available_plugin_fields();

        if ( ! isset( $available_fields[ $field_name ] ) ) {
            return array(
                'success' => false,
                'message' => __( 'Field not found in available plugin fields', 'advanced-user-registration' )
            );
        }

        $field_data = array(
            'field_name' => $field_name,
            'field_label' => $available_fields[ $field_name ],
            'field_type' => $this->guess_field_type_from_name( $field_name ),
            'source_plugin' => $source_plugin,
            'is_editable' => 1,
            'is_required' => 0,
        );

        return $this->create_field( $field_data );
    }

    /**
     * Guess field type from field name
     *
     * @param string $field_name Field name.
     * @return string
     */
    private function guess_field_type_from_name( $field_name ) {
        $field_name_lower = strtolower( $field_name );

        if ( strpos( $field_name_lower, 'email' ) !== false ) {
            return 'email';
        }

        if ( strpos( $field_name_lower, 'phone' ) !== false || strpos( $field_name_lower, 'tel' ) !== false ) {
            return 'tel';
        }

        if ( strpos( $field_name_lower, 'url' ) !== false || strpos( $field_name_lower, 'website' ) !== false ) {
            return 'url';
        }

        if ( strpos( $field_name_lower, 'date' ) !== false || strpos( $field_name_lower, 'birth' ) !== false ) {
            return 'date';
        }

        if ( strpos( $field_name_lower, 'number' ) !== false || strpos( $field_name_lower, 'age' ) !== false ) {
            return 'number';
        }

        if ( strpos( $field_name_lower, 'address' ) !== false ||
            strpos( $field_name_lower, 'description' ) !== false ||
            strpos( $field_name_lower, 'bio' ) !== false ) {
            return 'textarea';
        }

        if ( strpos( $field_name_lower, 'country' ) !== false ||
            strpos( $field_name_lower, 'state' ) !== false ||
            strpos( $field_name_lower, 'gender' ) !== false ) {
            return 'select';
        }

        return 'text';
    }

    /**
     * REST: Save fields
     */
    public function rest_save_fields( $request ) {
        $fields = $request->get_param( 'fields' );
        $result = $this->update_fields( $fields );

        if ( $result['success'] ) {
            return rest_ensure_response( $result );
        } else {
            return new WP_Error( 'save_failed', $result['message'], array( 'status' => 400 ) );
        }
    }

    /**
     * REST: Delete field
     */
    public function rest_delete_field( $request ) {
        $field_id = $request->get_param( 'field_id' );

        if ( ! $field_id ) {
            return new WP_Error( 'missing_field_id', __( 'Field ID is required', 'advanced-user-registration' ), array( 'status' => 400 ) );
        }

        $database = AUR()->get_module( 'database' );
        $result = $database->delete_user_field( $field_id );

        if ( $result ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => __( 'Field deleted successfully', 'advanced-user-registration' )
            ) );
        } else {
            return new WP_Error( 'delete_failed', __( 'Failed to delete field', 'advanced-user-registration' ), array( 'status' => 500 ) );
        }
    }

    /**
     * REST: Add field
     */
    public function rest_add_field( $request ) {
        $field_data = $request->get_param( 'field_data' );
        $result = $this->create_field( $field_data );

        if ( $result['success'] ) {
            return rest_ensure_response( $result );
        } else {
            return new WP_Error( 'create_failed', $result['message'], array( 'status' => 400 ) );
        }
    }

    /**
     * REST: Get plugin fields
     */
    public function rest_get_plugin_fields( $request ) {
        return rest_ensure_response( $this->get_available_plugin_fields() );
    }

    /**
     * Get field statistics
     *
     * @return array
     */
    public function get_field_statistics() {
        $database = AUR()->get_module( 'database' );
        $fields = $database->get_user_fields();

        $stats = array(
            'total_fields' => count( $fields ),
            'required_fields' => 0,
            'editable_fields' => 0,
            'custom_fields' => 0,
            'plugin_fields' => 0,
            'field_types' => array(),
        );

        foreach ( $fields as $field ) {
            if ( $field->is_required ) {
                $stats['required_fields']++;
            }

            if ( $field->is_editable ) {
                $stats['editable_fields']++;
            }

            if ( strpos( $field->field_name, 'aur_' ) === 0 ) {
                $stats['custom_fields']++;
            }

            if ( ! empty( $field->source_plugin ) ) {
                $stats['plugin_fields']++;
            }

            if ( ! isset( $stats['field_types'][ $field->field_type ] ) ) {
                $stats['field_types'][ $field->field_type ] = 0;
            }
            $stats['field_types'][ $field->field_type ]++;
        }

        return $stats;
    }
}