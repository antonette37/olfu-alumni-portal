<?php
/**
 * Copy to email_config.php and set SMTP credentials.
 * Do not commit email_config.php (it is in .gitignore).
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'OLFU ALUMNI AFFAIRS');
define('ENVIRONMENT', 'production');

if (!defined('SUPPORT_EMAIL')) {
    define('SUPPORT_EMAIL', SMTP_FROM_EMAIL);
}
