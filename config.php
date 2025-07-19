<?php
// MedTrackr Configuration File
// Copy this to config.local.php and update with your settings

// Load local configuration first (if it exists)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rxbuddy');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email Configuration Options

// Option 1: Gmail SMTP (requires App Password)
if (!defined('GMAIL_USERNAME')) define('GMAIL_USERNAME', 'user@gmail.com');  // Your Gmail address
if (!defined('GMAIL_APP_PASSWORD')) define('GMAIL_APP_PASSWORD', 'YOUR_PASSWORD_HERE'); // Gmail App Password (not regular password)

// Option 2: Alternative SMTP Server (uncomment and configure as needed)
// if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');        // SMTP server hostname
// if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);                     // SMTP port (587 for TLS, 465 for SSL)
// if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email username
// if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'your-password');     // Your email password
// if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'your-email@gmail.com'); // From email address

// Option 3: Use basic mail() function (works with local mail server)
// Leave all SMTP settings commented out to use basic mail() function

// Application Settings
if (!defined('APP_NAME')) define('APP_NAME', 'RxBuddy');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/rxbuddy'); // Update with your domain
if (!defined('TIMEZONE')) define('TIMEZONE', 'UTC'); // Set to your timezone

// API Keys
if (!defined('YOUR_OPEN_FDA_API_KEY')) define('YOUR_OPEN_FDA_API_KEY', ''); // Get from https://open.fda.gov/apis/authentication/

// Security Settings
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour
if (!defined('CSRF_TOKEN_EXPIRY')) define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour

// Email Templates
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', 'RxBuddy');
if (!defined('EMAIL_FROM_ADDRESS')) define('EMAIL_FROM_ADDRESS', defined('GMAIL_USERNAME') ? GMAIL_USERNAME : 'noreply@rxbuddy.com');

// Logging
define('LOG_DIR', __DIR__ . '/logs');
define('ERROR_LOG', LOG_DIR . '/errors.log');
define('SECURITY_LOG', LOG_DIR . '/security.log');
define('CRON_LOG', LOG_DIR . '/cron_execution.log');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Create logs directory if it doesn't exist
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
?> 
