# Employee Rating System - Installation Guide

This guide provides detailed steps to install and configure the Employee Rating System on a LAMP stack.

## Prerequisites

- Linux server (Ubuntu/Debian recommended)
- Apache web server (2.4+)
- MySQL (5.7+) or MariaDB (10.2+)
- PHP (7.4+)
- Git (for cloning the repository)

## Step 1: Clone the Repository

```bash
# Clone the repository
git clone https://github.com/cvarad96/employee-rating-system.git

# Navigate to the project directory
cd employee-rating-system
```

## Step 2: Database Setup

```bash
# Log in to MySQL
mysql -u root -p

# Create a database and user (in MySQL prompt)
CREATE DATABASE employee_rating_system;
CREATE USER 'eratinguser'@'localhost' IDENTIFIED BY 'choose_a_secure_password';
GRANT ALL PRIVILEGES ON employee_rating_system.* TO 'eratinguser'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import the database schema
mysql -u eratinguser -p employee_rating_system < employee_rating_schema.sql
```

## Step 3: Configure Application

```bash
# Copy the template configuration
cp config/config.php.template config/config.php

# Edit the configuration file
nano config/config.php
```

Update the following settings in `config.php`:

1. `APP_URL`: Set to your server URL (e.g., 'http://your-domain.com/employee-rating-system')
2. Database credentials:
   - `DB_HOST`: Usually 'localhost'
   - `DB_USER`: 'eratinguser' (or your chosen username)
   - `DB_PASS`: Your chosen password
   - `DB_NAME`: 'employee_rating_system'
3. Email settings (if using email notifications):
   - `MAIL_HOST`: Your SMTP server
   - `MAIL_PORT`: Usually 465 (SSL) or 587 (TLS)
   - `MAIL_USERNAME`: Your email username
   - `MAIL_PASSWORD`: Your email password
   - `MAIL_FROM_ADDRESS`: Sender email address
   - `MAIL_FROM_NAME`: Sender name

## Step 4: Web Server Configuration

### For Apache:

Create or modify the virtual host configuration:

```bash
sudo nano /etc/apache2/sites-available/employee-rating.conf
```

Add the following configuration (adjust paths as needed):

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/employee-rating-system
    
    <Directory /var/www/html/employee-rating-system>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/employee-rating-error.log
    CustomLog ${APACHE_LOG_DIR}/employee-rating-access.log combined
</VirtualHost>
```

Enable the site and required modules:

```bash
sudo a2ensite employee-rating
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## Step 5: File Permissions

```bash
# Set correct permissions
sudo chown -R www-data:www-data /path/to/employee-rating-system
sudo find /path/to/employee-rating-system -type d -exec chmod 755 {} \;
sudo find /path/to/employee-rating-system -type f -exec chmod 644 {} \;
sudo chmod -R 777 /path/to/employee-rating-system/logs
```

## Step 6: Create Admin User

Create a PHP file called `create_admin.php` in the root directory:

```php
<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/User.php';

// Create admin user
$userData = [
    'username' => 'admin',
    'password' => 'your-secure-password', // This will be hashed automatically
    'email' => 'admin@example.com',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'role' => 'admin'
];

$user = new User();
$result = $user->create($userData);

if ($result) {
    echo "Admin user created successfully with ID: $result";
} else {
    echo "Failed to create admin user. Check if a user with this username or email already exists.";
}
```

Run the script:

```bash
php create_admin.php
```

After running the script, **delete it immediately** for security:

```bash
rm create_admin.php
```

## Step 7: Set Up Cron Jobs

```bash
# Edit crontab
crontab -e
```

Add the following entries:

```
# Send weekly reminders every Friday at 9 AM
0 9 * * 5 php /path/to/employee-rating-system/cron/weekly_reminders.php

# Send weekly reports every Saturday at 9 AM
0 9 * * 6 php /path/to/employee-rating-system/cron/send_weekly_reports.php
```

## Step 8: Verify Installation

1. Navigate to your application URL: `http://your-domain.com/employee-rating-system/`
2. Log in with the admin credentials created in Step 6
3. Set up your organization structure (departments, teams, etc.)

## Troubleshooting

### Common Issues:

1. **500 Internal Server Error**:
   - Check PHP error logs: `sudo tail -f /var/log/apache2/error.log`
   - Verify file permissions
   - Ensure config.php has correct database credentials

2. **Database Connection Issues**:
   - Verify MySQL is running: `sudo systemctl status mysql`
   - Check database credentials
   - Ensure the database and tables exist

3. **Email Sending Fails**:
   - Verify SMTP server details
   - Check if outbound SMTP connections are allowed by your firewall
   - Test with a simpler mail function

### Security Recommendations:

1. Use HTTPS with a valid SSL certificate
2. Regularly update PHP, MySQL, and Apache to the latest secure versions
3. Implement a proper backup strategy for the database
4. Consider implementing additional authentication mechanisms (e.g., 2FA)
5. Regularly audit user accounts and permissions
