<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get seminars for dropdown
$seminars = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT s.*, COUNT(p.id) as participant_count 
            FROM seminars s 
            LEFT JOIN participants p ON s.id = p.seminar_id 
            ORDER BY s.date DESC
        ");
        $stmt->execute();
        $seminars = $stmt->fetchAll();
    } catch(PDOException $exception) {
        error_log("Seminars error: " . $exception->getMessage());
    }
}

// Handle certificate generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_certificates'])) {
    $seminar_id = (int)$_POST['seminar_id'];
    $send_emails = isset($_POST['send_emails']);
    
    if ($seminar_id) {
        try {
            // Get seminar details
            $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
            $stmt->bindParam(':id', $seminar_id);
            $stmt->execute();
            $seminar = $stmt->fetch();
            
            if ($seminar) {
                // Get participants
                $stmt = $db->prepare("SELECT * FROM participants WHERE seminar_id = :id");
                $stmt->bindParam(':id', $seminar_id);
                $stmt->execute();
                $participants = $stmt->fetchAll();
                
                if (!empty($participants)) {
                    $certificates_generated = 0;
                    $emails_sent = 0;
                    
                    foreach ($participants as $participant) {
                        // Generate certificate for each participant
                        $certificate_path = generateCertificate($participant, $seminar);
                        
                        if ($certificate_path && $send_emails) {
                            // Send email with certificate
                            $email_body = getCertificateEmailTemplate(
                                $participant['name'],
                                $seminar['title'],
                                date('F d, Y', strtotime($seminar['date'])),
                                $seminar['speaker']
                            );
                            
                            if (sendEmail($participant['email'], "Certificate of Participation - {$seminar['title']}", $email_body, $certificate_path)) {
                                $emails_sent++;
                            }
                        }
                        
                        if ($certificate_path) {
                            $certificates_generated++;
                        }
                    }
                    
                    $success = "Generated {$certificates_generated} certificates";
                    if ($send_emails) {
                        $success .= " and sent {$emails_sent} emails";
                    }
                    $success .= " successfully!";
                } else {
                    $error = 'No participants found for this seminar';
                }
            } else {
                $error = 'Seminar not found';
            }
        } catch(PDOException $exception) {
            $error = 'Database error: ' . $exception->getMessage();
        }
    } else {
        $error = 'Please select a seminar';
    }
}

// Handle single certificate generation
if (isset($_GET['participant']) && isset($_GET['seminar'])) {
    $participant_id = (int)$_GET['participant'];
    $seminar_id = (int)$_GET['seminar'];
    
    try {
        // Get participant details
        $stmt = $db->prepare("SELECT * FROM participants WHERE id = :id");
        $stmt->bindParam(':id', $participant_id);
        $stmt->execute();
        $participant = $stmt->fetch();
        
        // Get seminar details
        $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
        $stmt->bindParam(':id', $seminar_id);
        $stmt->execute();
        $seminar = $stmt->fetch();
        
        if ($participant && $seminar) {
            $certificate_path = generateCertificate($participant, $seminar);
            
            if ($certificate_path) {
                // Download the certificate
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="certificate_' . str_replace(' ', '_', $participant['name']) . '.pdf"');
                readfile($certificate_path);
                exit();
            } else {
                $error = 'Failed to generate certificate';
            }
        } else {
            $error = 'Participant or seminar not found';
        }
    } catch(PDOException $exception) {
        $error = 'Database error: ' . $exception->getMessage();
    }
}

/**
 * Generate certificate PDF using FPDF
 */
function generateCertificate($participant, $seminar) {
    // Create certificates directory if not exists
    $cert_dir = __DIR__ . '/../certificates';
    if (!file_exists($cert_dir)) {
        mkdir($cert_dir, 0777, true);
    }
    
    // Include FPDF library (assuming it's in vendor directory)
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
    
    try {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        
        // Set background (if certificate template exists)
        $template_path = __DIR__ . '/../templates/certificate_template.jpg';
        if (file_exists($template_path)) {
            $pdf->Image($template_path, 0, 0, 297, 210);
        } else {
            // Create a simple certificate design if no template
            $pdf->SetFillColor(102, 126, 234);
            $pdf->Rect(0, 0, 297, 210, 'F');
            
            // White content area
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect(20, 20, 257, 170, 'F');
        }
        
        // Add certificate content using built-in fonts
        $pdf->SetFont('Times', 'B', 36);
        $pdf->SetTextColor(102, 126, 234);
        $pdf->Cell(0, 30, 'Certificate of Participation', 0, 1, 'C');
        
        $pdf->SetFont('Times', 'I', 16);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
        
        $pdf->SetFont('Times', 'B', 28);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 20, strtoupper($participant['name']), 0, 1, 'C');
        
        $pdf->SetFont('Times', '', 16);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 10, 'has successfully participated in', 0, 1, 'C');
        
        $pdf->SetFont('Times', 'B', 22);
        $pdf->SetTextColor(102, 126, 234);
        $pdf->Cell(0, 15, $seminar['title'], 0, 1, 'C');
        
        $pdf->SetFont('Times', '', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, 'Date: ' . date('F d, Y', strtotime($seminar['date'])), 0, 1, 'C');
        $pdf->Cell(0, 8, 'Speaker: ' . $seminar['speaker'], 0, 1, 'C');
        $pdf->Cell(0, 8, 'Venue: ' . $seminar['venue'], 0, 1, 'C');
        
        // Add signature lines
        $pdf->Ln(20);
        $pdf->SetFont('Times', '', 12);
        $pdf->Cell(80, 0, '', 0, 0, 'C');
        $pdf->Cell(80, 0, '', 0, 0, 'C');
        $pdf->Cell(80, 0, '', 0, 1, 'C');
        
        $pdf->Cell(80, 30, '_________________', 0, 0, 'C');
        $pdf->Cell(80, 30, '', 0, 0, 'C');
        $pdf->Cell(80, 30, '_________________', 0, 1, 'C');
        
        $pdf->SetFont('Times', '', 11);
        $pdf->Cell(80, 0, $seminar['speaker'], 0, 0, 'C');
        $pdf->Cell(80, 0, '', 0, 0, 'C');
        $pdf->Cell(80, 0, 'Seminar Coordinator', 0, 1, 'C');
        
        // Save certificate
        $filename = 'certificate_' . $seminar['id'] . '_' . $participant['id'] . '.pdf';
        $filepath = $cert_dir . '/' . $filename;
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    } catch (Exception $e) {
        error_log('Certificate generation error: ' . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Certificates - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 240px;
            width: calc(100% - 240px);
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .dashboard-title {
            flex: 1;
            min-width: 200px;
        }
        
        .dashboard-actions {
            flex-shrink: 0;
        }
        
        .certificate-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            max-width: none;
            width: 100%;
        }
        
        .seminar-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
            max-width: none;
            width: 100%;
        }
        .seminar-card:hover {
            transform: translateY(-3px);
        }
        .participant-count {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 14px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .template-preview {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
                width: 100%;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .certificate-container {
                padding: 20px;
            }
            .seminar-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-graduation-cap me-2"></i>SeminarMS</h4>
                    </div>
                    
                    <div class="mb-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-user-circle fa-3x"></i>
                            </div>
                            <h6><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h6>
                            <small><?php echo htmlspecialchars($_SESSION['admin_email']); ?></small>
                        </div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="create_seminar.php">
                            <i class="fas fa-plus-circle me-2"></i>Create Seminar
                        </a>
                        <a class="nav-link" href="participants.php">
                            <i class="fas fa-users me-2"></i>Participants
                        </a>
                        <a class="nav-link" href="billings.php">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Billings
                        </a>
                        <a class="nav-link" href="financial_reports.php">
                            <i class="fas fa-chart-line me-2"></i>Financial Reports
                        </a>
                        <a class="nav-link active" href="generate_certificates.php">
                            <i class="fas fa-certificate me-2"></i>Generate Certificates
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
                    <div class="dashboard-header">
                        <div class="dashboard-title">
                            <h2 class="fw-bold">Generate Certificates</h2>
                            <p class="text-muted">Create and send certificates to participants</p>
                        </div>
                        <div class="dashboard-actions">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Features Overview -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <div class="feature-icon mx-auto">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <h6>PDF Generation</h6>
                                <small class="text-muted">Professional certificates in PDF format</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <div class="feature-icon mx-auto">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <h6>Email Delivery</h6>
                                <small class="text-muted">Automatic email sending with attachments</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-center">
                                <div class="feature-icon mx-auto">
                                    <i class="fas fa-palette"></i>
                                </div>
                                <h6>Custom Templates</h6>
                                <small class="text-muted">Professional certificate designs</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="certificate-container">
                        <!-- Certificate Generation Form -->
                        <h5 class="mb-4">Generate Certificates for Seminar</h5>
                        
                        <form method="POST" action="">
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="seminar_id" class="form-label">
                                        <i class="fas fa-calendar-alt me-2"></i>Select Seminar
                                    </label>
                                    <select class="form-select" id="seminar_id" name="seminar_id" required>
                                        <option value="">Choose a seminar...</option>
                                        <?php foreach ($seminars as $seminar): ?>
                                            <option value="<?php echo $seminar['id']; ?>">
                                                <?php echo htmlspecialchars($seminar['title']); ?>
                                                (<?php echo $seminar['participant_count']; ?> participants)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="send_emails" name="send_emails" checked>
                                        <label class="form-check-label" for="send_emails">
                                            Send certificates via email
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" name="generate_certificates" class="btn btn-primary">
                                    <i class="fas fa-certificate me-1"></i>Generate Certificates
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-5">
                        
                        <!-- Available Seminars -->
                        <h5 class="mb-4">Available Seminars</h5>
                        
                        <?php if (empty($seminars)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No seminars available</h6>
                                <p class="text-muted">Create a seminar first to generate certificates</p>
                                <a href="create_seminar.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create Seminar
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($seminars as $seminar): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="seminar-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($seminar['title']); ?></h6>
                                                    <small><?php echo date('M d, Y', strtotime($seminar['date'])); ?></small>
                                                </div>
                                                <span class="participant-count">
                                                    <i class="fas fa-users me-1"></i><?php echo $seminar['participant_count']; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <?php if ($seminar['participant_count'] > 0): ?>
                                                    <form method="POST" action="" class="flex-grow-1">
                                                        <input type="hidden" name="seminar_id" value="<?php echo $seminar['id']; ?>">
                                                        <input type="hidden" name="send_emails" value="1">
                                                        <button type="submit" name="generate_certificates" 
                                                                class="btn btn-light btn-sm w-100">
                                                            <i class="fas fa-certificate me-1"></i>Generate All
                                                        </button>
                                                    </form>
                                                    <a href="participants.php?seminar=<?php echo $seminar['id']; ?>" 
                                                       class="btn btn-outline-light btn-sm">
                                                        <i class="fas fa-users me-1"></i>View
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-light btn-sm w-100" disabled>
                                                        <i class="fas fa-info-circle me-1"></i>No Participants
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Template Information -->
                        <div class="template-preview mt-5">
                            <h6><i class="fas fa-info-circle me-2"></i>Certificate Template</h6>
                            <p class="text-muted mb-3">
                                Certificates are generated using a professional template. You can customize the design by 
                                placing a certificate_template.jpg file in the /templates directory.
                            </p>
                            <small class="text-muted">
                                <i class="fas fa-lightbulb me-1"></i>
                                Template dimensions: 297x210mm (A4 Landscape)
                            </small>
                        </div>
                    </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
