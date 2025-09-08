const { registerBlockType } = wp.blocks;
const { createElement: el, Fragment } = wp.element;
const { 
    InspectorControls, 
    useBlockProps 
} = wp.blockEditor;
const { 
    PanelBody, 
    CheckboxControl, 
    TextControl,
    SelectControl,
    TextareaControl,
    Button,
    Notice
} = wp.components;
const { useState, useEffect } = wp.element;

registerBlockType('aur/registration-form', {
    title: 'Advanced User Registration',
    icon: 'admin-users',
    category: 'widgets',
    description: 'A customizable user registration and login form with verification.',
    
    attributes: {
        selectedFields: {
            type: 'array',
            default: ['user_login', 'user_email', 'user_pass']
        },
        showPhoneField: {
            type: 'boolean',
            default: true
        },
        requirePhoneVerification: {
            type: 'boolean',
            default: false
        },
        formTitle: {
            type: 'string',
            default: 'Register or Login'
        },
        customCSS: {
            type: 'string',
            default: ''
        }
    },

    edit: function({ attributes, setAttributes }) {
        const { 
            selectedFields, 
            showPhoneField, 
            requirePhoneVerification, 
            formTitle, 
            customCSS 
        } = attributes;

        const [availableFields, setAvailableFields] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState('');

        useEffect(() => {
            fetchAvailableFields();
        }, []);

        const fetchAvailableFields = async () => {
            try {
                const response = await wp.apiFetch({
                    path: '/aur/v1/fields'
                });
                setAvailableFields(response || []);
                setLoading(false);
            } catch (err) {
                setError('Failed to load available fields');
                setLoading(false);
            }
        };

        const handleFieldToggle = (fieldName, isChecked) => {
            const newSelectedFields = isChecked 
                ? [...selectedFields, fieldName]
                : selectedFields.filter(field => field !== fieldName);
            setAttributes({ selectedFields: newSelectedFields });
        };

        const blockProps = useBlockProps({
            className: 'aur-block-editor'
        });

        if (loading) {
            return el('div', blockProps, 
                el('p', {}, 'Loading form fields...')
            );
        }

        if (error) {
            return el('div', blockProps,
                el(Notice, {
                    status: 'error',
                    isDismissible: false
                }, error)
            );
        }

        return el(Fragment, {},
            el('div', blockProps,
                el('div', {
                    style: {
                        border: '2px dashed #ddd',
                        padding: '20px',
                        textAlign: 'center',
                        backgroundColor: '#f9f9f9'
                    }
                },
                    el('h3', { style: { margin: 0, color: '#666' } }, 
                        formTitle || 'Register or Login'
                    ),
                    el('p', { style: { margin: '10px 0 0 0', color: '#999', fontSize: '14px' } },
                        `Advanced User Registration Form (${selectedFields.length} fields selected)`
                    ),
                    el('div', { 
                        style: { 
                            marginTop: '15px',
                            padding: '10px',
                            backgroundColor: '#fff',
                            borderRadius: '4px',
                            fontSize: '12px',
                            color: '#666'
                        }
                    },
                        'Selected fields: ' + selectedFields.join(', ')
                    )
                )
            ),

            el(InspectorControls, {},
                el(PanelBody, {
                    title: 'Form Settings',
                    initialOpen: true
                },
                    el(TextControl, {
                        label: 'Form Title',
                        value: formTitle,
                        onChange: (value) => setAttributes({ formTitle: value }),
                        help: 'The title displayed above the form'
                    })
                ),

                el(PanelBody, {
                    title: 'Field Selection',
                    initialOpen: true
                },
                    el('p', { style: { marginBottom: '15px', fontSize: '13px', color: '#666' } },
                        'Select which fields to include in the registration form:'
                    ),

                    availableFields.map(field => 
                        el(CheckboxControl, {
                            key: field.field_name,
                            label: field.field_label + (field.is_required === '1' ? ' *' : ''),
                            checked: selectedFields.includes(field.field_name),
                            onChange: (isChecked) => handleFieldToggle(field.field_name, isChecked),
                            help: field.field_name === 'user_pass' ? 'Required for registration' : 
                                  field.is_required === '1' ? 'This field is required' : ''
                        })
                    )
                ),

                el(PanelBody, {
                    title: 'Phone Verification',
                    initialOpen: false
                },
                    el(CheckboxControl, {
                        label: 'Show Phone Number Field',
                        checked: showPhoneField,
                        onChange: (value) => setAttributes({ showPhoneField: value }),
                        help: 'Display phone number field in registration form'
                    }),

                    showPhoneField && el(CheckboxControl, {
                        label: 'Require Phone Verification',
                        checked: requirePhoneVerification,
                        onChange: (value) => setAttributes({ requirePhoneVerification: value }),
                        help: 'Make phone number verification mandatory'
                    })
                ),

                el(PanelBody, {
                    title: 'Advanced Settings',
                    initialOpen: false
                },
                    el(TextareaControl, {
                        label: 'Custom CSS',
                        value: customCSS,
                        onChange: (value) => setAttributes({ customCSS: value }),
                        help: 'Add custom CSS styles for this form',
                        rows: 6
                    }),

                    el('div', { style: { marginTop: '15px' } },
                        el('h4', { style: { margin: '0 0 10px 0', fontSize: '13px' } }, 'Quick Actions'),
                        
                        el(Button, {
                            isSecondary: true,
                            onClick: () => setAttributes({
                                selectedFields: ['user_login', 'user_email', 'user_pass', 'first_name', 'last_name'],
                                showPhoneField: true,
                                requirePhoneVerification: false
                            }),
                            style: { marginRight: '10px', marginBottom: '10px' }
                        }, 'Load Default Fields'),

                        el(Button, {
                            isSecondary: true,
                            onClick: () => setAttributes({
                                selectedFields: ['user_login', 'user_email', 'user_pass'],
                                showPhoneField: false,
                                requirePhoneVerification: false
                            }),
                            style: { marginBottom: '10px' }
                        }, 'Minimal Setup'),

                        el('div', { style: { marginTop: '15px', fontSize: '12px', color: '#666' } },
                            el('strong', {}, 'Shortcode equivalent:'), 
                            el('br'),
                            `[aur_registration fields="${selectedFields.join(',')}" show_phone="${showPhoneField}" require_phone="${requirePhoneVerification}" title="${formTitle}"]`
                        )
                    )
                )
            )
        );
    },

    save: function() {
        // Return null since this is a dynamic block rendered by PHP
        return null;
    }
});