<?php
/**
 * Test Dependencies
 * This script verifies that PHPMailer and FPDF are properly installed
 */

echo "<h2>Testing Dependencies Installation</h2>";

// Test PHPMailer
echo "<h3>Testing PHPMailer...</h3>";
try {
    require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    echo "<p style='color: green;'>✓ PHPMailer loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ PHPMailer error: " . $e->getMessage() . "</p>";
}

// Test FPDF
echo "<h3>Testing FPDF...</h3>";
try {
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
    $pdf = new FPDF();
    echo "<p style='color: green;'>✓ FPDF loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ FPDF error: " . $e->getMessage() . "</p>";
}

// Test Database Connection
echo "<h3>Testing Database Connection...</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p style='color: green;'>✓ Database connection successful!</p>";
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='setup.php'>Run Database Setup</a></li>";
echo "<li><a href='auth/login.php'>Login to Admin Panel</a></li>";
echo "<li>Configure email settings in config/mail.php</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Default Admin Credentials:</strong></p>";
echo "<p>Email: admin@seminar.com</p>";
echo "<p>Password: admin123</p>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dependencies Test - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
    </div>
</body>
</html>
