<?php
/**
 * Database Setup Script
 * Run this script to initialize the database and create tables
 */

require_once __DIR__ . '/config/database.php';

echo "<h2>Seminar Management System - Database Setup</h2>";

// Step 1: Create database
echo "<h3>Step 1: Creating Database...</h3>";
if (createDatabase()) {
    echo "<p style='color: green;'>✓ Database 'seminar_system' created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to create database</p>";
    exit();
}

// Step 2: Create tables
echo "<h3>Step 2: Creating Tables...</h3>";
if (createTables()) {
    echo "<p style='color: green;'>✓ Tables created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to create tables</p>";
    exit();
}

// Step 3: Verify setup
echo "<h3>Step 3: Verifying Setup...</h3>";
$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        // Check tables exist
        $tables = ['admins', 'seminars', 'participants'];
        $all_exist = true;
        
        foreach ($tables as $table) {
            $stmt = $db->prepare("SHOW TABLES LIKE :table");
            $stmt->bindParam(':table', $table);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Table '$table' missing</p>";
                $all_exist = false;
            }
        }
        
        if ($all_exist) {
            echo "<h3 style='color: green;'>✓ Setup Complete!</h3>";
            echo "<p>The database has been successfully initialized.</p>";
            echo "<p><a href='auth/login.php'>Proceed to Login</a></p>";
            
            // Display default admin credentials
            echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
            echo "<h4>Default Admin Credentials:</h4>";
            echo "<p><strong>Email:</strong> admin@seminar.com</p>";
            echo "<p><strong>Password:</strong> admin123</p>";
            echo "<small>You can change these after logging in.</small>";
            echo "</div>";
        } else {
            echo "<p style='color: red;'>Setup incomplete. Please check the errors above.</p>";
        }
    } catch(PDOException $exception) {
        echo "<p style='color: red;'>Database error: " . $exception->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>Failed to connect to database</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Install dependencies using Composer:<br>";
echo "<code>composer require phpmailer/phpmailer</code><br>";
echo "<code>composer require fpdf/fpdf</code></li>";
echo "<li>Configure email settings in <code>config/mail.php</code></li>";
echo "<li>Optional: Add certificate template to <code>templates/certificate_template.jpg</code></li>";
echo "<li>Start using the system!</li>";
echo "</ol>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
</body>
</html>
