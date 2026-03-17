<?php
/**
 * Band Manager — local configuration template.
 *
 * Copy this file to config.local.php and fill in your values.
 * config.local.php is listed in .gitignore and must NEVER be committed.
 *
 * Usage:
 *   cp config.example.php config.local.php
 */

define('APP_NAME',       'Band Manager');

// From-address for magic link emails
define('MAIL_FROM',      'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'Band Manager');

// SMTP credentials — leave SMTP_HOST blank to fall back to PHP's mail()
define('SMTP_HOST',      'smtp.gmail.com');   // or 'smtp-relay.brevo.com' for Brevo
define('SMTP_PORT',      587);                // 587 = STARTTLS, 465 = SSL
define('SMTP_USER',      'you@gmail.com');
define('SMTP_PASS',      'your-app-password');
