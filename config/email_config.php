<?php
/**
 * Email Configuration
 * Configure your SMTP settings here
 */

class EmailConfig {
    // SMTP Configuration
    // Choose your email provider and update the settings below
    
    // Option 1: Gmail (Recommended for development)
    // Note: You need to use an "App Password" if 2FA is enabled
    // Create one at: https://myaccount.google.com/apppasswords
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls'; // 'tls' or 'ssl'
    const SMTP_USERNAME = 'kingbambz99@gmail.com'; // Change this
    const SMTP_PASSWORD = 'wpoj vegx zsoz fxpt';    // Change this
    const FROM_EMAIL = 'kingbambz99@gmail.com';    // Change this
    const FROM_NAME = 'ParkaLot System';
    
    /* 
    // Option 2: Outlook/Hotmail
    const SMTP_HOST = 'smtp-mail.outlook.com';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_USERNAME = 'your-email@outlook.com';
    const SMTP_PASSWORD = 'your-password';
    const FROM_EMAIL = 'your-email@outlook.com';
    const FROM_NAME = 'ParkaLot System';
    */
    
    /* 
    // Option 3: Yahoo Mail
    const SMTP_HOST = 'smtp.mail.yahoo.com';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_USERNAME = 'your-email@yahoo.com';
    const SMTP_PASSWORD = 'your-app-password';
    const FROM_EMAIL = 'your-email@yahoo.com';
    const FROM_NAME = 'ParkaLot System';
    */
    
    /* 
    // Option 4: SendGrid (Recommended for production)
    const SMTP_HOST = 'smtp.sendgrid.net';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls';
    const SMTP_USERNAME = 'apikey';
    const SMTP_PASSWORD = 'your-sendgrid-api-key';
    const FROM_EMAIL = 'noreply@yourdomain.com';
    const FROM_NAME = 'ParkaLot System';
    */
    
    // Email Settings
    const DEBUG = true; // Set to false in production
    const CHARSET = 'UTF-8';
    
    // For development/testing: Use Mailtrap.io (fake SMTP)
    // Sign up at https://mailtrap.io for free testing inbox
    /*
    const SMTP_HOST = 'sandbox.smtp.mailtrap.io';
    const SMTP_PORT = 2525;
    const SMTP_SECURE = 'tls';
    const SMTP_USERNAME = 'your-mailtrap-username';
    const SMTP_PASSWORD = 'your-mailtrap-password';
    const FROM_EMAIL = 'noreply@parkalot.com';
    const FROM_NAME = 'ParkaLot System';
    */
}
?>
