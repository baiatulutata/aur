# Advanced User Registration Plugin

A comprehensive WordPress plugin for advanced user registration with email and phone verification, built with modern JavaScript (ES6+), Tailwind CSS, and following WordPress coding standards.

## Features

### Core Functionality
- **Modular Architecture** - Clean, organized codebase following WordPress standards
- **Gutenberg Block** - Easy-to-use registration block for the block editor
- **Shortcode Support** - `[aur_registration]` shortcode for theme integration
- **AJAX/REST API** - All operations handled asynchronously for better UX
- **Responsive Design** - Mobile-first design with Tailwind CSS

### User Registration Process
1. **Login/Register** - Users can login or create new accounts
2. **Email Verification** - Required email confirmation with codes or direct links
3. **Phone Verification** - Optional SMS verification (can be skipped)
4. **Profile Management** - Users can edit their information after registration

### Admin Features
- **Field Manager** - Create and manage custom user fields
- **Settings Panel** - Configure email and SMS providers
- **Statistics Dashboard** - View registration analytics
- **User Integration** - Works with existing WordPress users

### Technical Features
- **Webpack Build Process** - Modern JavaScript bundling and optimization
- **Tailwind CSS** - Utility-first styling with no inline CSS in PHP
- **Email Templates** - Beautiful HTML email templates
- **SMS Integration** - Twilio integration (easily extensible)
- **Security** - Nonce verification and input sanitization
- **Accessibility** - WCAG compliant forms

## Installation

1. Upload the plugin files to `/wp-content/plugins/advanced-user-registration/`
2. Run `npm install` to install dependencies
3. Run `npm run build` to build the assets
4. Activate the plugin in WordPress admin
5. Configure settings in **User Registration > Settings**

## Development Setup

### Prerequisites
- Node.js (v14 or higher)
- npm or yarn
- WordPress development environment

### Build Commands
```bash
# Install dependencies
npm install

# Development build with watch
npm run dev

# Production build
npm run build

# Development build (single run)
npm run build:dev

# Code linting
npm run lint:js
npm run lint:css

# Code formatting
npm run format
```

## Configuration

### Email Settings
1. Go to **User Registration > Settings > Email Settings**
2. Configure sender name and email
3. Customize email templates (optional)

### SMS Settings (Twilio)
1. Go to **User Registration > Settings > SMS Settings**
2. Select Twilio as provider
3. Enter your Twilio credentials:
   - Account SID
   - Auth Token
   - Phone Number
4. Test configuration with the built-in test feature

### Field Management
1. Go to **User Registration > Field Manager**
2. Add, edit, or reorder user fields
3. Set field types, requirements, and editability
4. Drag and drop to reorder fields

## Usage

### Gutenberg Block
1. Add the "Advanced User Registration" block to any page/post
2. Configure field selection in the block settings
3. Customize phone verification requirements
4. Add custom CSS if needed

### Shortcode
```php
[aur_registration fields="user_login,user_email,user_pass,first_name,last_name" show_phone="true" require_phone="false" title="Create Account"]
```

**Shortcode Parameters:**
- `fields` - Comma-separated list of field names
- `show_phone` - Show phone field (true/false)
- `require_phone` - Require phone verification (true/false)
- `title` - Form title

### PHP Integration
```php
// Get user verification status
$email_confirmed = get_user_meta($user_id, 'aur_email_confirmed', true);
$phone_confirmed = get_user_meta($user_id, 'aur_phone_confirmed', true);

// Check if user needs email verification
$needs_verification = get_user_meta($user_id, 'aur_email_verification_required', true);
```

## File Structure

```
advanced-user-registration/
├── advanced-user-registration.php     # Main plugin file
├── includes/                          # Core classes
│   ├── class-aur-database.php        # Database operations
│   ├── class-aur-api.php             # AJAX/REST API handlers
│   ├── class-aur-email.php           # Email service
│   ├── class-aur-sms.php             # SMS service
│   ├── class-aur-block.php           # Gutenberg block
│   ├── class-aur-user-fields.php     # User fields management
│   └── class-aur-verification.php    # Verification logic
├── admin/                             # Admin interface
│   ├── class-aur-admin.php           # Admin pages
│   ├── class-aur-settings.php        # Settings management
│   └── class-aur-field-manager.php   # Field management
├── assets/
│   ├── src/                          # Source files
│   │   ├── js/                       # JavaScript sources
│   │   └── css/                      # CSS sources
│   └── dist/                         # Built files
├── webpack.config.js                 # Webpack configuration
├── tailwind.config.js               # Tailwind configuration
├── package.json                     # Dependencies
└── README.md
```

## Customization

### Adding Custom Fields
```php
// Add custom field via code
$database = AUR()->get_module('database');
$database->add_user_field(array(
    'field_name' => 'company_name',
    'field_label' => 'Company Name',
    'field_type' => 'text',
    'is_required' => 0,
    'is_editable' => 1,
    'field_order' => 10
));
```

### Custom Email Templates
Override email templates by placing files in your theme:
```
your-theme/
└── aur-templates/
    ├── email-verification.php
    └── welcome-email.php
```

### Custom SMS Providers
Extend the SMS class to add new providers:
```php
add_filter('aur_sms_providers', function($providers) {
    $providers['my_provider'] = 'My SMS Provider';
    return $providers;
});
```

## Testing

### Development Testing
- Test emails are automatically sent to `ibaldazar@yahoo.com` during development
- SMS codes are logged and emailed for testing without actual SMS costs
- Use mock SMS provider for development

### Production Testing
- Use the built-in SMS test feature in admin settings
- Test email verification flows with real email addresses
- Verify all form fields work correctly

## Security Features

- **Nonce Verification** - All AJAX requests are secured
- **Input Sanitization** - All user inputs are sanitized
- **Code Expiration** - Verification codes expire after 30 minutes (configurable)
- **Rate Limiting** - Built-in protection against spam
- **SQL Injection Protection** - Prepared statements used throughout

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+
- Internet Explorer 11 (basic support)

## Hooks and Filters

### Actions
```php
// After successful registration
do_action('aur_user_registered', $user_id);

// After email verification
do_action('aur_email_verified', $user_id);

// After phone verification
do_action('aur_phone_verified', $user_id);
```

### Filters
```php
// Modify email template
add_filter('aur_email_template', function($template, $type) {
    // Customize template
    return $template;
}, 10, 2);

// Modify verification code length
add_filter('aur_verification_code_length', function($length) {
    return 8; // Change from 6 to 8 digits
});
```

## Troubleshooting

### Common Issues

**Build errors:**
```bash
# Clear node modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run build
```

**Email not sending:**
- Check WordPress email configuration
- Verify SMTP settings if using SMTP plugin
- Check server email logs

**SMS not working:**
- Verify Twilio credentials
- Check phone number format (+1234567890)
- Ensure Twilio account has sufficient credits

**JavaScript errors:**
- Check browser console for errors
- Ensure assets are built and enqueued correctly
- Verify REST API is accessible

## Support

For issues and feature requests, please check:
1. WordPress debug logs
2. Browser console for JavaScript errors
3. Network tab for failed API requests
4. Plugin error logs

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Changelog

### 1.0.0
- Initial release
- Complete registration system with email/phone verification
- Gutenberg block and shortcode support
- Admin interface with field management
- Twilio SMS integration
- Responsive design with Tailwind CSS