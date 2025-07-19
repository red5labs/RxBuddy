# RxBuddy - Medication Management System

RxBuddy is a comprehensive web-based medication management system that helps users track their medications, log doses, set reminders, and share information with caregivers and healthcare providers.

## 🌟 Features

- **Medication Management**: Add, edit, and archive medications with detailed information
- **Pill Photos**: Upload photos of medications for easy identification
- **Dose Logging**: Track when medications are taken with optional notes
- **Calendar View**: Visual calendar showing medication schedules and taken doses
- **Email Reminders**: Configurable email reminders for medication doses
- **Profile Sharing**: Share medication information with caregivers, family, or healthcare providers
- **Medication Lookup**: Search for drug information using RxNorm and OpenFDA APIs
- **Mobile Responsive**: Works seamlessly on desktop, tablet, and mobile devices
- **Secure Authentication**: User registration, login, and session management
- **Data Export**: Export medication logs for healthcare providers

## 📋 Requirements

### Server Requirements
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.7 or higher (8.0+ recommended)
- **Web Server**: Apache 2.4+ or Nginx
- **Extensions**: 
  - `pdo_mysql`
  - `gd` (for image processing)
  - `fileinfo` (for file upload validation)
  - `openssl` (for secure connections)
  - `mbstring` (for string handling)

### Client Requirements
- Modern web browser with JavaScript enabled
- Internet connection (for external API calls)

## 🚀 Installation

### 1. Download and Extract
```bash
# Clone or download the RxBuddy files to your web server
# Ensure the files are in your web-accessible directory
```

### 2. Database Setup
1. Create a new MySQL database:
```sql
CREATE DATABASE rxbuddy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Create a MySQL user and grant permissions:
```sql
CREATE USER 'rxbuddy_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON rxbuddy.* TO 'rxbuddy_user'@'localhost';
FLUSH PRIVILEGES;
```

3. Import the database schema:
```bash
# Run the SQL files in the database/ directory
mysql -u rxbuddy_user -p rxbuddy < database/sharing_tables.sql
mysql -u rxbuddy_user -p rxbuddy < database/api_cache_table.sql
```

### 3. Install Dependencies
```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader
```

### 4. Configure the Application

#### Create Configuration File
Copy `config.php` to `config.local.php` and update the settings:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rxbuddy');
define('DB_USER', 'rxbuddy_user');
define('DB_PASS', 'your_secure_password');

// Application Settings
define('APP_NAME', 'RxBuddy');
define('APP_URL', 'https://yourdomain.com'); // Update with your domain
define('TIMEZONE', 'America/New_York'); // Set to your timezone

// Email Configuration (Choose one option)

// Option 1: Gmail SMTP (Recommended)
define('GMAIL_USERNAME', 'your-email@gmail.com');
define('GMAIL_APP_PASSWORD', 'your-gmail-app-password');

// Option 2: Alternative SMTP Server
// define('SMTP_HOST', 'smtp.yourprovider.com');
// define('SMTP_PORT', 587);
// define('SMTP_USERNAME', 'your-email@yourprovider.com');
// define('SMTP_PASSWORD', 'your-password');

// Option 3: Use basic mail() function
// Leave all SMTP settings commented out

// API Keys
define('OPENFDA_API_KEY', 'your-openfda-api-key'); // Get from https://open.fda.gov/apis/authentication/

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour
?>
```

### 5. Set Up File Permissions
```bash
# Create and set permissions for uploads directory
mkdir -p uploads/pills uploads/temp
chmod 755 uploads uploads/pills uploads/temp

# Create and set permissions for logs directory
mkdir -p logs
chmod 755 logs

# Set permissions for configuration files
chmod 644 config.php config.local.php
```

### 6. Configure Web Server

#### Apache Configuration
Create or update your `.htaccess` file in the root directory:

```apache
RewriteEngine On

# Handle routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?page=$1 [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Protect sensitive files
<Files "config.local.php">
    Order Allow,Deny
    Deny from all
</Files>

<Files "composer.json">
    Order Allow,Deny
    Deny from all
</Files>

<Files "composer.lock">
    Order Allow,Deny
    Deny from all
</Files>
```

#### Nginx Configuration
Add this to your Nginx server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?page=$uri&$args;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# Protect sensitive files
location ~ /(config\.local\.php|composer\.(json|lock)) {
    deny all;
}
```

### 7. Set Up Cron Jobs (Optional)
For email reminders, set up a cron job:

```bash
# Add to crontab (crontab -e)
# Run every 5 minutes
*/5 * * * * /usr/bin/php /path/to/your/rxbuddy/cron/check_reminders.php
```

## 🌐 Deployment to Web Host

### Shared Hosting (cPanel, etc.)

1. **Upload Files**:
   - Upload all RxBuddy files to your `public_html` directory
   - Ensure `index.php` is in the root directory

2. **Database Setup**:
   - Create a MySQL database through your hosting control panel
   - Import the SQL files from the `database/` directory
   - Note the database credentials

3. **Configuration**:
   - Edit `config.local.php` with your database credentials
   - Update `APP_URL` to your domain
   - Configure email settings

4. **File Permissions**:
   - Set `uploads/` and `logs/` directories to 755
   - Set `config.local.php` to 644

5. **Install Dependencies**:
   - If your host supports Composer, run `composer install`
   - Otherwise, manually upload the `vendor/` directory

### VPS/Dedicated Server

1. **Server Preparation**:
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade
   
   # Install required packages
   sudo apt install apache2 mysql-server php php-mysql php-gd php-mbstring php-curl php-zip unzip
   
   # Install Composer
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

2. **Follow the Installation Steps Above**

3. **SSL Certificate** (Recommended):
   ```bash
   # Install Let's Encrypt
   sudo apt install certbot python3-certbot-apache
   sudo certbot --apache -d yourdomain.com
   ```

### Cloud Hosting (AWS, Google Cloud, etc.)

1. **Create Instance/Container**
2. **Install LAMP Stack**
3. **Follow VPS Installation Steps**
4. **Configure Load Balancer** (if needed)
5. **Set up Auto-scaling** (if needed)

## 🔧 Configuration Details

### Email Setup

#### Gmail SMTP (Recommended)
1. Enable 2-Factor Authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate password for "Mail"
3. Use the generated password in `config.local.php`

#### Alternative SMTP Providers
Update the SMTP settings in `config.local.php` with your provider's details.

### API Keys

#### OpenFDA API Key
1. Visit https://open.fda.gov/apis/authentication/
2. Register for a free API key
3. Add the key to `config.local.php`

### Security Settings

#### Session Configuration
- `SESSION_TIMEOUT`: How long users stay logged in (in seconds)
- `CSRF_TOKEN_EXPIRY`: How long CSRF tokens are valid (in seconds)

#### File Upload Limits
- Maximum file size: 5MB
- Allowed formats: JPEG, PNG, WebP
- Images are automatically resized to 1200x1200 pixels maximum

## 🛠️ Maintenance

### Regular Tasks
1. **Database Backups**:
   ```bash
   mysqldump -u rxbuddy_user -p rxbuddy > backup_$(date +%Y%m%d).sql
   ```

2. **Log Rotation**:
   ```bash
   # Add to crontab
   0 2 * * 0 /usr/bin/find /path/to/rxbuddy/logs -name "*.log" -mtime +30 -delete
   ```

3. **File Cleanup**:
   ```bash
   # Clean up old temporary files
   0 3 * * 0 /usr/bin/find /path/to/rxbuddy/uploads/temp -mtime +1 -delete
   ```

### Updates
1. Backup your database and files
2. Download the latest version
3. Replace files (except `config.local.php`)
4. Run any database migrations
5. Test the application

## 🔒 Security Considerations

### File Permissions
- Ensure `config.local.php` is not web-accessible
- Set proper permissions on upload directories
- Regularly audit file permissions

### Database Security
- Use strong, unique passwords
- Limit database user permissions
- Regularly backup your database
- Keep MySQL updated

### Application Security
- Keep PHP and all extensions updated
- Use HTTPS in production
- Regularly review and update dependencies
- Monitor error logs for suspicious activity

## 🐛 Troubleshooting

### Common Issues

#### "Headers already sent" Error
- Ensure no whitespace before `<?php` tags
- Check for BOM characters in PHP files
- Verify no output before redirects

#### File Upload Issues
- Check file permissions on `uploads/` directory
- Verify PHP upload limits in `php.ini`
- Check `.htaccess` file configuration

#### Email Not Working
- Verify SMTP credentials
- Check firewall settings
- Test with a simple PHP mail script

#### Database Connection Issues
- Verify database credentials
- Check MySQL service status
- Ensure database exists and user has permissions

### Debug Mode
To enable debug mode, add this to `config.local.php`:
```php
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📞 Support

For support and issues:
1. Check the troubleshooting section above
2. Review error logs in the `logs/` directory
3. Ensure all requirements are met
4. Verify configuration settings

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📝 Changelog

### Version 1.0.0
- Initial release
- Complete medication management system
- Pill photo upload functionality
- Email reminders
- Profile sharing
- Mobile responsive design
- Medication lookup integration 