# Employee Rating System

A comprehensive web-based application for managing employee ratings across an organization. This system allows managers to rate their team members based on department-specific parameters, while administrators can view overall reports and manage the organizational structure.

## Features

- **User Roles**: Admin and Manager
- **Department Management**: Create and manage departments with custom rating parameters
- **Team Management**: Organize employees into teams under specific departments
- **Employee Management**: Add and manage employees per team
- **Rating System**: Rate employees on a 1-5 star scale across multiple parameters
- **Weekly Reminders**: Automatic reminders for managers to rate employees
- **Comprehensive Reports**: View detailed reports and analytics on employee performance
- **Mobile-Friendly Interface**: Responsive design that works on all devices

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- Modern web browser

## Installation Guide

1. **Clone this repository**:
   ```
   git clone https://github.com/cvarad96/employee-rating-system.git
   cd employee-rating-system
   ```

2. **Set up the database**:
   - Create a MySQL database named `employee_rating_system`
   - Import the SQL file `employee_rating_schema.sql` to create the tables

3. **Configure the application**:
   - Copy `config/config.php.template` to `config/config.php`
   - Edit `config/config.php` to set your database connection details and other settings
   - Update `APP_URL` to match your server's URL
   - Configure email settings if you want to use email notifications

4. **Set up the web server**:
   - Place all files in your web server's document root or a subdirectory
   - Ensure the web server has write permissions to the application directories:
     ```
     chmod -R 755 ./
     chmod -R 777 ./logs
     ```

5. **Set up the cron job for weekly reminders**:
   - Add the following cron job to run every Friday at 9 AM:
     ```
     0 9 * * 5 php /path/to/employee-rating-system/cron/weekly_reminders.php
     ```

6. **Create the Admin account**:
   - Add an admin account directly to the database using the following SQL:
     ```sql
     INSERT INTO users (username, password, email, first_name, last_name, role) 
     VALUES ('admin', '$2y$10$YourHashedPassword', 'admin@example.com', 'Admin', 'User', 'admin');
     ```
     Note: Generate a hashed password using PHP's `password_hash()` function or use the provided `tools/password.php` script.

## Initial System Setup

After installation, follow these steps to set up your organization:

1. **Login as Admin**:
   - Use the admin account created during installation

2. **Create Departments**:
   - Go to "Departments" in the Admin dashboard
   - Add departments for your organization (e.g., Engineering, Marketing, Sales)
   - For each department, add relevant rating parameters (e.g., Technical Skills, Communication, Teamwork)

3. **Add Managers**:
   - Go to "Managers" in the Admin dashboard
   - Add managers who will lead teams and rate employees

4. **Create Teams**:
   - Go to "Teams" in the Admin dashboard
   - Create teams under specific departments and assign managers

5. **Managers add Employees**:
   - Managers should log in and go to "Employees"
   - Add team members who will be rated

## License

This project is open-sourced under the MIT license.

## Support

For support or to report issues, please open an issue on the GitHub repository.
