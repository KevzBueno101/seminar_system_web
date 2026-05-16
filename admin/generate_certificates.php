<?php
/**
 * generate_certificates.php
 * PDF download is intercepted at the very top before any output,
 * then the page renders normally for all other requests.
 */

ob_start(); // Catch any stray whitespace from includes

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

// ── Auth check ────────────────────────────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ═════════════════════════════════════════════════════════════════
//  SINGLE CERTIFICATE DOWNLOAD
//  Must be handled here — before ANY HTML is sent to the browser.
// ═════════════════════════════════════════════════════════════════
if (isset($_GET['participant'], $_GET['seminar'])) {
    $participant_id = (int)$_GET['participant'];
    $seminar_id     = (int)$_GET['seminar'];

    try {
        $stmt = $db->prepare("SELECT * FROM participants WHERE id = :id");
        $stmt->bindParam(':id', $participant_id);
        $stmt->execute();
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
        $stmt->bindParam(':id', $seminar_id);
        $stmt->execute();
        $seminar = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($participant && $seminar) {
            $pdf = buildCertificatePDF($participant, $seminar);

            ob_end_clean(); // Wipe buffer — nothing must precede the PDF binary

            $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $participant['name']);
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="certificate_' . $safe_name . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            $pdf->Output('', 'I'); // Stream directly to browser
            exit();
        }
    } catch (PDOException $e) {
        error_log('Certificate download error: ' . $e->getMessage());
    }

    // If we reach here something went wrong — fall through to page with error
    $download_error = 'The certificate could not be generated. Please try again.';
}

// ═════════════════════════════════════════════════════════════════
//  PAGE LOGIC  (only runs for normal page requests)
// ═════════════════════════════════════════════════════════════════
$error   = $download_error ?? '';
$success = '';

// Get seminars for dropdown
$seminars = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT
                s.*, 
                COUNT(p.id) as participant_count
            FROM seminars s
            LEFT JOIN participants p ON s.id = p.seminar_id
            GROUP BY s.id
            ORDER BY s.date DESC
        ");
        $stmt->execute();
        $seminars = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Seminars error: " . $e->getMessage());
    }
}

// Handle bulk certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificates'])) {
    $seminar_id  = (int)$_POST['seminar_id'];
    $send_emails = isset($_POST['send_emails']);

    if ($seminar_id) {
        try {
            $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
            $stmt->bindParam(':id', $seminar_id);
            $stmt->execute();
            $seminar = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($seminar) {
                $stmt = $db->prepare("SELECT * FROM participants WHERE seminar_id = :id");
                $stmt->bindParam(':id', $seminar_id);
                $stmt->execute();
                $participants = $stmt->fetchAll();

                if (!empty($participants)) {
                    $certificates_generated = 0;
                    $emails_sent = 0;

                    foreach ($participants as $participant) {
                        $certificate_path = saveCertificateToDisk($participant, $seminar);

                        if ($certificate_path && $send_emails) {
                            $email_body = getCertificateEmailTemplate(
                                $participant['name'],
                                $seminar['title'],
                                date('F d, Y', strtotime($seminar['date'])),
                                $seminar['speaker']
                            );
                            if (sendEmail(
                                $participant['email'],
                                "Certificate of Participation - {$seminar['title']}",
                                $email_body,
                                $certificate_path
                            )) {
                                $emails_sent++;
                            }
                        }

                        if ($certificate_path) $certificates_generated++;
                    }

                    $success = "Successfully generated {$certificates_generated} certificate(s)";
                    if ($send_emails) {
                        $success .= " and delivered {$emails_sent} email notification(s)";
                    }
                    $success .= ".";
                } else {
                    $error = 'No registered participants were found for the selected seminar.';
                }
            } else {
                $error = 'The selected seminar could not be found.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred: ' . $e->getMessage();
        }
    } else {
        $error = 'Please select a seminar before proceeding.';
    }
}

// ═════════════════════════════════════════════════════════════════
//  CERTIFICATE BUILDERS
// ═════════════════════════════════════════════════════════════════

/**
 * Build and return an FPDF object with the certificate rendered.
 * Used for both streaming (download) and saving to disk.
 */
function buildCertificatePDF(array $participant, array $seminar): FPDF {
    // Color Palette
    $navy      = [15,  32,  86];
    $blue      = [102, 126, 234];
    $darkGray  = [45,  45,  45];
    $midGray   = [90,  90,  90];
    $lightGray = [180, 180, 180];

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();

    $pageW   = 297;
    $pageH   = 210;
    // Safe zone centered: 30mm margins on each side
    $safeX   = 35; 
    $safeW   = 157;
    $centerX = $pageW / 2;

    // 1. Background Template
    $template_path = __DIR__ . '/../templates/certificate_template.jpg';
    if (file_exists($template_path)) {
        $pdf->Image($template_path, 0, 0, $pageW, $pageH);
    } else {
        // Fallback border if image is missing
        $pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
        $pdf->SetLineWidth(2);
        $pdf->Rect(5, 5, $pageW - 10, $pageH - 10);
    }

    // Helper for centered text
    $draw = function ($text, $y, $size, $style = '', $family = 'Times', $rgb = [0,0,0]) use ($pdf, $safeX, $safeW) {
        $pdf->SetFont($family, $style, $size);
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->SetXY($safeX, $y);
        $pdf->Cell($safeW, 0, $text, 0, 0, 'L');
    };

    // 2. Main Title
    $draw('CERTIFICATE OF PARTICIPATION', 45, 38, 'B', 'Times', $navy);

    // // 3. Accent Line
    // $lineLength = 140; // Longer line for better balance
    // $startX = $centerX - $lineLength/2;
    // $endX = $centerX + $lineLength/2;
    // $pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
    // $pdf->SetLineWidth(1.2);
    // $pdf->Line($startX, 47, $endX, 47);

    // 4. Intro Phrase
    $draw('This is to certify that', 58, 15, 'I', 'Times', $midGray);

    // 5. Participant Name (The focal point)
    $draw(strtoupper($participant['name']), 73, 30, 'B', 'Arial', $navy);

    // 6. Name Underline
    $pdf->SetDrawColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($safeX, 82, $safeX + $safeW, 82);

    // 7. Context Phrase
    $draw('has successfully participated in the seminar entitled', 92, 11, 'I', 'Times', $midGray);

    // 8. Seminar Title (Handled with MultiCell for long titles)
    $pdf->SetFont('Times', 'B', 20);
    $pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
    $pdf->SetXY($safeX, 108);
    $pdf->MultiCell($safeW, 10, strtoupper($seminar['title']), 0, 'L');

    // 9. Meta Row (Date, Speaker, Venue)
    $metaY = 135;
    $colW  = $safeW / 3;
    $meta  = [
        ['label' => 'DATE',    'value' => date('F d, Y', strtotime($seminar['date']))],
        ['label' => 'SPEAKER', 'value' => $seminar['speaker']],
        ['label' => 'VENUE',   'value' => $seminar['venue']],
    ];

    foreach ($meta as $i => $m) {
        $colX = $safeX + ($i * $colW);
        // Label (Modern Sans-Serif)
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor($lightGray[0], $lightGray[1], $lightGray[2]);
        $pdf->SetXY($colX, $metaY);
        $pdf->Cell($colW, 5, $m['label'], 0, 0, 'L');
        // Value
        $pdf->SetFont('Times', 'B', 11);
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        $pdf->SetXY($colX, $metaY + 6);
        $pdf->Cell($colW, 5, $m['value'], 0, 0, 'L');
    }

    // 10. Signature Block (Adjusted higher to avoid bottom-right graphic)
    $sigY      = 170;
    $lineLen   = 40;
    $leftSigX  = $safeX ;
    $rightSigX = $safeX + $safeW - $lineLen - 10;
    $orgName   = !empty($seminar['organization']) ? $seminar['organization'] : 'Event Coordinator';

    $signers = [
        ['x' => $leftSigX,  'name' => $seminar['speaker'], 'role' => 'Resource Person'],
        ['x' => $rightSigX, 'name' => $orgName,            'role' => 'Organizer'],
    ];

    foreach ($signers as $s) {
        $pdf->SetDrawColor($navy[0], $navy[1], $navy[2]);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($s['x'], $sigY, $s['x'] + $lineLen, $sigY);

        $pdf->SetFont('Times', 'B', 10);
        $pdf->SetTextColor($darkGray[0], $darkGray[1], $darkGray[2]);
        $pdf->SetXY($s['x'], $sigY + 2);
        $pdf->Cell(0, 5, $s['name'], 0, 1, 'L');

        $pdf->SetFont('Times', 'I', 8);
        $pdf->SetTextColor($midGray[0], $midGray[1], $midGray[2]);
        $pdf->SetXY($s['x'], $sigY + 10);
        $pdf->Cell(0, 5, $s['role'], 0, 0, 'L');
    }


    return $pdf;
}

/**
 * Save a certificate PDF to disk and return the file path.
 * Used for bulk generation and email attachment.
 */
function saveCertificateToDisk(array $participant, array $seminar): string|false {
    $cert_dir = __DIR__ . '/../certificates';
    if (!file_exists($cert_dir)) {
        mkdir($cert_dir, 0777, true);
    }

    try {
        $pdf      = buildCertificatePDF($participant, $seminar);
        $filename = 'certificate_' . $seminar['id'] . '_' . $participant['id'] . '.pdf';
        $filepath = $cert_dir . '/' . $filename;
        $pdf->Output($filepath, 'F');
        return $filepath;
    } catch (Exception $e) {
        error_log('Certificate save error: ' . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Certificates – Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; margin: 0; padding: 0; min-height: 100vh; }
        .dashboard-layout { display: flex; min-height: 100vh; width: 100%; }
        .sidebar {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            min-height: 100vh; color: white;
            position: fixed; top: 0; left: 0;
            width: 240px; z-index: 1000; overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8); padding: 12px 20px;
            margin: 5px 0; border-radius: 8px; transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { background: rgba(255,255,255,0.2); color: white; }
        .main-content {
            flex: 1; padding: 30px;
            margin-left: 240px; width: calc(100% - 240px);
            min-height: 100vh; overflow-x: hidden;
        }
        .dashboard-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 2rem;
            flex-wrap: wrap; gap: 1rem;
        }
        .dashboard-title { flex: 1; min-width: 200px; }
        .dashboard-actions { flex-shrink: 0; }
        .certificate-container {
            background: white; border-radius: 15px;
            padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); width: 100%;
        }
.seminar-card {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            color: white; border-radius: 12px; padding: 20px;
            margin-bottom: 20px; transition: transform 0.3s ease;
        }
        .seminar-card:hover { transform: translateY(-3px); }
        .participant-count {
            background: rgba(255,255,255,0.2); border-radius: 20px;
            padding: 5px 15px; font-size: 14px; white-space: nowrap;
        }
.btn-primary {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            border: none; padding: 12px 30px; font-weight: 600; transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
box-shadow: 0 5px 15px rgba(42,157,143,0.4);
        }
        .feature-icon {
            width: 60px; height: 60px; border-radius: 50%;
background: rgba(42,157,143,0.15);
            display: flex; align-items: center; justify-content: center;
color: #2a9d8f;
        }
        .template-preview {
            border: 2px dashed #dee2e6; border-radius: 10px;
            padding: 20px; text-align: center; background: #f8f9fa;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-left: 0; padding: 20px; width: 100%; }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .certificate-container { padding: 20px; }
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
        <div class="mb-4 text-center">
            <i class="fas fa-user-circle fa-3x mb-2"></i>
            <h6><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h6>
            <small><?php echo htmlspecialchars($_SESSION['admin_email']); ?></small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
            <a class="nav-link" href="create_seminar.php"><i class="fas fa-plus-circle me-2"></i>Create Seminar</a>
            <a class="nav-link" href="participants.php"><i class="fas fa-users me-2"></i>Participants</a>
            <a class="nav-link" href="billings.php"><i class="fas fa-file-invoice-dollar me-2"></i>Billings</a>
            <a class="nav-link" href="financial_reports.php"><i class="fas fa-chart-line me-2"></i>Financial Reports</a>
            <a class="nav-link active" href="generate_certificates.php"><i class="fas fa-certificate me-2"></i>Generate Certificates</a>
            <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <div class="dashboard-header">
            <div class="dashboard-title">
                <h2 class="fw-bold">Generate Certificates</h2>
                <p class="text-muted">Issue and distribute certificates of participation to seminar attendees</p>
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
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Feature Highlights -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="text-center">
                    <div class="feature-icon mx-auto"><i class="fas fa-file-pdf"></i></div>
                    <h6>PDF Generation</h6>
                    <small class="text-muted">Professional certificates exported in PDF format</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="text-center">
                    <div class="feature-icon mx-auto"><i class="fas fa-envelope"></i></div>
                    <h6>Email Delivery</h6>
                    <small class="text-muted">Automated email dispatch with certificate attachments</small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="text-center">
                    <div class="feature-icon mx-auto"><i class="fas fa-palette"></i></div>
                    <h6>Custom Templates</h6>
                    <small class="text-muted">Professionally designed, print-ready certificate layouts</small>
                </div>
            </div>
        </div>

        <div class="certificate-container">

            <h5 class="mb-1">Generate Certificates for a Seminar</h5>
            <p class="text-muted mb-4 small">Select a seminar below to generate certificates for all registered participants.</p>

            <form method="POST" action="">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <label for="seminar_id" class="form-label">
                            <i class="fas fa-calendar-alt me-2"></i>Select Seminar
                        </label>
                        <select class="form-select" id="seminar_id" name="seminar_id" required>
                            <option value="">Choose a seminar…</option>
                            <?php foreach ($seminars as $s): ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['title']); ?>
                                    (<?php echo $s['participant_count']; ?> participant<?php echo $s['participant_count'] != 1 ? 's' : ''; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="send_emails" name="send_emails" checked>
                            <label class="form-check-label" for="send_emails">
                                Deliver certificates via email
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

            <h5 class="mb-1">Available Seminars</h5>
            <p class="text-muted mb-4 small">Quickly generate certificates directly from any seminar listed below.</p>

            <?php if (empty($seminars)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No seminars on record</h6>
                    <p class="text-muted">Please create a seminar first before generating certificates.</p>
                    <a href="create_seminar.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Seminar
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($seminars as $s): ?>
                        <div class="col-md-6 mb-3">
                            <div class="seminar-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($s['title']); ?></h6>
                                        <small><?php echo date('F d, Y', strtotime($s['date'])); ?></small>
                                    </div>
                                    <span class="participant-count">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo $s['participant_count']; ?>
                                        <?php echo $s['participant_count'] != 1 ? 'Participants' : 'Participant'; ?>
                                    </span>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($s['participant_count'] > 0): ?>
                                        <form method="POST" action="" class="flex-grow-1">
                                            <input type="hidden" name="seminar_id" value="<?php echo $s['id']; ?>">
                                            <input type="hidden" name="send_emails" value="1">
                                            <button type="submit" name="generate_certificates" class="btn btn-light btn-sm w-100">
                                                <i class="fas fa-certificate me-1"></i>Generate All Certificates
                                            </button>
                                        </form>
                                        <a href="participants.php?seminar=<?php echo $s['id']; ?>"
                                           class="btn btn-outline-light btn-sm">
                                            <i class="fas fa-users me-1"></i>View Participants
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-light btn-sm w-100" disabled>
                                            <i class="fas fa-info-circle me-1"></i>No Registered Participants
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>