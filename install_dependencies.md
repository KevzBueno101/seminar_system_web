# Installing Dependencies - Step by Step Guide

## Method 1: Using Composer (Recommended)

### Step 1: Install Composer
1. Download Composer from: https://getcomposer.org/Composer-Setup.exe
2. Run the installer and follow the wizard
3. Restart your command prompt/PowerShell

### Step 2: Install Dependencies
Open PowerShell in your project directory and run:
```bash
cd C:/xampp/htdocs/web-comission
composer require phpmailer/phpmailer
composer require fpdf/fpdf
```

## Method 2: Manual Download (Alternative)

### Step 1: Download PHPMailer
1. Go to: https://github.com/PHPMailer/PHPMailer/releases
2. Download the latest ZIP file
3. Extract to: `vendor/PHPMailer/`
4. Ensure the structure is: `vendor/PHPMailer/src/PHPMailer.php`

### Step 2: Download FPDF
1. Go to: http://www.fpdf.org/en/download.php
2. Download the latest ZIP file
3. Extract to: `vendor/fpdf/`
4. Ensure the structure is: `vendor/fpdf/fpdf.php`

### Step 3: Create composer.json (Optional)
Create this file to enable future Composer usage:
```json
{
    "require": {
        "phpmailer/phpmailer": "^6.8",
        "fpdf/fpdf": "^1.8"
    }
}
```

## Verification

After installation, check that these files exist:
- `vendor/PHPMailer/src/PHPMailer.php`
- `vendor/PHPMailer/src/SMTP.php`
- `vendor/PHPMailer/src/Exception.php`
- `vendor/fpdf/fpdf.php`

## Test the System

1. Run the database setup: `http://localhost/web-comission/setup.php`
2. Login with: admin@seminar.com / admin123
3. Try creating a seminar and generating certificates

## Troubleshooting

If you get "Class not found" errors:
1. Check file paths in `config/mail.php`
2. Ensure all required files are in the correct directories
3. Verify file permissions on vendor folder

## Email Configuration

After installing dependencies, update `config/mail.php`:
- Replace `your-email@gmail.com` with your Gmail
- Replace `your-app-password` with your Gmail App Password
- Enable 2-Step Verification in Gmail to generate App Password
