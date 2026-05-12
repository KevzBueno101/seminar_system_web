# Seminar and Training Management System

A complete, production-ready system for managing seminars, training sessions, participant registration, and e-certificate generation with email notifications.

## Features

- **Authentication System**: Secure admin login/signup with session management
- **Seminar Management**: Create, edit, and manage seminars with unique registration links
- **Participant Management**: Track registrations, manage slots, and view participant details
- **Certificate Generation**: Automatic PDF certificate creation with professional templates
- **Email Notifications**: Send certificates and updates via email using PHPMailer
- **Public Registration**: Token-based public registration page for participants
- **Responsive Design**: Modern Bootstrap 5 interface with mobile support
- **Modern Dashboard Layout**: Professional flexbox-based admin interface with full-width content utilization

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (via XAMPP/phpMyAdmin)
- Web server (Apache recommended)

## Installation Guide

### 1. Setup XAMPP

1. Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services from XAMPP Control Panel
3. Open phpMyAdmin: `http://localhost/phpmyadmin`

### 2. Database Setup

1. Create a new database named `seminar_system`
2. Import the database schema (automatically created on first run)
3. Verify tables are created: `admins`, `seminars`, `participants`

### 3. Project Setup

1. Clone or extract the project to `C:/xampp/htdocs/web-comission/`
2. Ensure all files and directories are in place

### 4. Install Dependencies

#### Option A: Using Composer (Recommended)
```bash
cd C:/xampp/htdocs/web-comission
composer require phpmailer/phpmailer
composer require fpdf/fpdf
```

#### Option B: Manual Download
1. Download PHPMailer from [GitHub](https://github.com/PHPMailer/PHPMailer)
2. Extract to `vendor/PHPMailer/`
3. Download FPDF from [FPDF.org](http://www.fpdf.org/)
4. Extract to `vendor/fpdf/`

### 5. Email Configuration (Gmail SMTP)

1. Enable 2-Step Verification on your Gmail account
2. Generate an App Password:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a new app password for "Mail"
3. Update email settings in `config/mail.php`:
   ```php
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-app-password');
   define('FROM_EMAIL', 'your-email@gmail.com');
   ```

### 6. Initial Setup

1. Open your browser and navigate to: `http://localhost/web-comission/`
2. You'll be redirected to the login page
3. Register a new admin account or use default credentials:
   - Email: `admin@seminar.com`
   - Password: `admin123`

## Layout and Design

### Modern Dashboard Architecture

The admin interface features a modern, responsive layout built with flexbox architecture:

**Key Layout Features:**
- **Fixed Sidebar**: 240px width with gradient design and smooth scrolling
- **Full-Width Content**: Main content utilizes all available horizontal space
- **Responsive Design**: Mobile-first approach with 768px breakpoint
- **Semantic HTML**: Uses `<aside>` and `<main>` elements for accessibility
- **Consistent Spacing**: Professional visual balance across all pages

**Layout Structure:**
```html
<div class="dashboard-layout">
    <aside class="sidebar p-4">
        <!-- Navigation menu -->
    </aside>
    <main class="main-content">
        <!-- Page content -->
    </main>
</div>
```

**Responsive Behavior:**
- **Desktop (>768px)**: Fixed sidebar, flexible main content
- **Mobile (≤768px)**: Stacked layout, full-width sidebar and content
- **Tablet**: Adaptive scaling with proper component alignment

**Pages with Updated Layout:**
- Dashboard (statistics and quick actions)
- Create/Edit Seminar (form layouts)
- Participants Management (tables and filters)
- Billing System (payment forms and receipts)
- Financial Reports (charts and data tables)
- Certificate Generation (seminar cards and templates)

## File Structure

```
/web-comission/
├── config/
│   ├── database.php          # Database configuration and schema
│   └── mail.php              # Email configuration (PHPMailer)
├── auth/
│   ├── login.php             # Admin login page
│   ├── register.php          # Admin registration page
│   └── logout.php            # Logout functionality
├── admin/
│   ├── dashboard.php          # Main admin dashboard
│   ├── create_seminar.php    # Create/edit seminars
│   ├── participants.php       # Manage participants
│   ├── billings.php           # Billing management and payments
│   ├── financial_reports.php  # Financial reports and analytics
│   └── generate_certificates.php # Certificate generation
├── public/
│   └── register.php          # Public registration page
├── templates/
│   └── certificate_template.jpg # Optional certificate background
├── vendor/                   # Dependencies (PHPMailer, FPDF)
├── certificates/             # Generated certificates (auto-created)
└── index.php                 # Entry point
```

## Usage Guide

### Creating a Seminar

1. Login to admin dashboard
2. Click "Create Seminar" or "Create New Seminar"
3. Fill in seminar details:
   - Title, description, date, time
   - Venue, organization, speaker
   - Maximum slots
4. Save to generate unique registration link
5. Share the link with potential participants

### Managing Participants

1. Go to "Participants" from dashboard
2. View all registered participants
3. Filter by specific seminar
4. Export participant data as CSV
5. Remove participants if needed

### Generating Certificates

1. Navigate to "Generate Certificates"
2. Select a seminar from the dropdown
3. Choose to send via email or just generate
4. Click "Generate Certificates"
5. Certificates are created as PDFs and optionally emailed

### Billing Management

1. Go to "Billings" from the dashboard
2. Generate billing for participants:
   - Select participant and enter amount
   - Set due date and add remarks
   - Click "Generate Billing"
3. Record payments:
   - Find the billing in the list
   - Enter payment details (amount, method, reference)
   - Click "Record Payment" to generate receipt
4. View receipts and billing statements
5. Filter by status or seminar as needed

### Financial Reports

1. Navigate to "Financial Reports"
2. View collection summaries and statistics
3. Check outstanding balances
4. Export reports for accounting purposes
5. Filter by date range or seminar

### Public Registration

1. Share the unique seminar link with participants
2. Participants register with name and email
3. System checks slot availability
4. Registration confirmation is shown

## Security Features

- **Password Hashing**: All passwords use PHP's `password_hash()`
- **SQL Injection Prevention**: Prepared statements with PDO
- **Session Management**: Secure session handling
- **Input Validation**: Server and client-side validation
- **XSS Prevention**: Output escaping with `htmlspecialchars()`
- **CSRF Protection**: Basic token-based protection

## Customization

### Certificate Template

1. Create a certificate background image (A4 landscape: 297x210mm)
2. Save as `templates/certificate_template.jpg`
3. System will automatically use this template

### Email Templates

1. Modify `getCertificateEmailTemplate()` function in `config/mail.php`
2. Customize HTML email design and content

### UI Customization

1. Modify CSS in each PHP file
2. Update Bootstrap theme colors
3. Add custom fonts and icons

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure XAMPP MySQL is running
   - Check database credentials in `config/database.php`

2. **Email Not Sending**
   - Verify Gmail App Password setup
   - Check SMTP settings in `config/mail.php`
   - Ensure PHPMailer is properly installed

3. **Certificate Generation Error**
   - Verify FPDF is installed in `vendor/fpdf/`
   - Check write permissions for `certificates/` directory

4. **Registration Link Not Working**
   - Ensure seminar status is 'open'
   - Check that token is properly generated
   - Verify URL structure is correct

### Error Logs

- PHP errors: Check XAMPP Apache logs
- Database errors: Check MySQL logs
- Application logs: Errors are logged to PHP error log

## Testing Checklist

- [ ] Admin login works correctly
- [ ] Seminar creation saves properly
- [ ] Unique registration links work
- [ ] Slot limit enforcement works
- [ ] Participant registration functions
- [ ] Certificate generation works
- [ ] Email sending with attachments
- [ ] Public registration page accessible
- [ ] Mobile responsive design

## Support

For issues and questions:
1. Check this README for common solutions
2. Verify all requirements are met
3. Review error logs for specific issues
4. Test with default credentials first

## License

This project is open source and available under the MIT License.
