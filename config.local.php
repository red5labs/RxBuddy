<?php
// Local configuration for MedTrackr
// Update these values with your actual settings

// Email Configuration (for testing)
if (!defined('GMAIL_USERNAME')) define('GMAIL_USERNAME', 'user@gmail.com');  // Your Gmail address
if (!defined('GMAIL_APP_PASSWORD')) define('GMAIL_APP_PASSWORD', 'YOU_APP_PASSWORD');     // Replace with your 16-character App Password

// Application Settings
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/rxbuddy');
if (!defined('TIMEZONE')) define('TIMEZONE', 'America/New_York'); // Set to your timezone

// Email Templates
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', 'RxBuddy');
if (!defined('EMAIL_FROM_ADDRESS')) define('EMAIL_FROM_ADDRESS', defined('GMAIL_USERNAME') ? GMAIL_USERNAME : 'noreply@rxbuddy.com');
?> 
