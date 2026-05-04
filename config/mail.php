<?php
/**
 * Email Configuration
 * Seminar Management System
 */

// SMTP Configuration for Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Replace with your Gmail
define('SMTP_PASSWORD', 'your-app-password'); // Replace with your App Password
define('SMTP_ENCRYPTION', 'tls');

// Email Settings
define('FROM_EMAIL', 'your-email@gmail.com'); // Replace with your Gmail
define('FROM_NAME', 'Seminar Management System');

/**
 * Send email using PHPMailer
 */
function sendEmail($to, $subject, $body, $attachment = null) {
    // Import PHPMailer
    require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        // Attachments
        if ($attachment && file_exists($attachment)) {
            $mail->addAttachment($attachment);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generate certificate email template
 */
function getCertificateEmailTemplate($participantName, $seminarTitle, $seminarDate, $speakerName) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Certificate of Participation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .highlight { background: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 Certificate of Participation</h1>
                <p>Congratulations on completing the seminar!</p>
            </div>
            <div class='content'>
                <p>Dear <strong>{$participantName}</strong>,</p>
                
                <div class='highlight'>
                    <h3>📋 Seminar Details</h3>
                    <p><strong>Title:</strong> {$seminarTitle}</p>
                    <p><strong>Date:</strong> {$seminarDate}</p>
                    <p><strong>Speaker:</strong> {$speakerName}</p>
                </div>
                
                <p>We are pleased to inform you that you have successfully participated in the seminar mentioned above. Your certificate of participation is attached to this email.</p>
                
                <p>This certificate recognizes your active involvement and commitment to continuous learning and professional development.</p>
                
                <p>Please keep this certificate for your records and feel free to share it on your professional networks.</p>
                
                <p>Thank you for being part of our seminar series!</p>
                
                <p>Best regards,<br>
                Seminar Management Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>&copy; 2026 Seminar Management System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>
