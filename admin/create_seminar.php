<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$seminar = null;
$error = '';
$success = '';

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
        $stmt->bindParam(':id', $edit_id);
        $stmt->execute();
        $seminar = $stmt->fetch();
        
        if (!$seminar) {
            $error = 'Seminar not found';
        }
    } catch(PDOException $exception) {
        $error = 'Database error: ' . $exception->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $venue = trim($_POST['venue']);
    $organization = trim($_POST['organization']);
    $speaker = trim($_POST['speaker']);
    $max_slots = (int)$_POST['max_slots'];
    $status = $_POST['status'];
    
    // Validation
    if (empty($title) || empty($date) || empty($time) || empty($venue) || 
        empty($organization) || empty($speaker) || empty($max_slots)) {
        $error = 'All required fields must be filled';
    } elseif ($max_slots < 1) {
        $error = 'Maximum slots must be at least 1';
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = 'Seminar date cannot be in the past';
    } else {
        try {
            if ($seminar) {
                // Update existing seminar
                $stmt = $db->prepare("
                    UPDATE seminars 
                    SET title = :title, description = :description, date = :date, 
                        time = :time, venue = :venue, organization = :organization, 
                        speaker = :speaker, max_slots = :max_slots, status = :status
                    WHERE id = :id
                ");
                $stmt->bindParam(':id', $seminar['id']);
            } else {
                // Create new seminar
                $unique_token = bin2hex(random_bytes(32));
                $stmt = $db->prepare("
                    INSERT INTO seminars 
                    (title, description, date, time, venue, organization, speaker, max_slots, unique_token, status)
                    VALUES (:title, :description, :date, :time, :venue, :organization, :speaker, :max_slots, :unique_token, :status)
                ");
                $stmt->bindParam(':unique_token', $unique_token);
            }
            
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':time', $time);
            $stmt->bindParam(':venue', $venue);
            $stmt->bindParam(':organization', $organization);
            $stmt->bindParam(':speaker', $speaker);
            $stmt->bindParam(':max_slots', $max_slots);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $success = $seminar ? 'Seminar updated successfully!' : 'Seminar created successfully!';
                
                if (!$seminar) {
                    // Get the newly created seminar for display
                    $seminar_id = $db->lastInsertId();
                    $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
                    $stmt->bindParam(':id', $seminar_id);
                    $stmt->execute();
                    $seminar = $stmt->fetch();
                }
            } else {
                $error = 'Failed to save seminar. Please try again.';
            }
        } catch(PDOException $exception) {
            $error = 'Database error: ' . $exception->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seminar ? 'Edit' : 'Create'; ?> Seminar - Seminar Management System</title>
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
        
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            max-width: none;
            width: 100%;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
        .registration-link {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .copy-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.4);
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
            .form-container {
                padding: 20px;
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
                        <a class="nav-link active" href="create_seminar.php">
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
                        <a class="nav-link" href="generate_certificates.php">
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
                            <h2 class="fw-bold"><?php echo $seminar ? 'Edit' : 'Create'; ?> Seminar</h2>
                            <p class="text-muted"><?php echo $seminar ? 'Update seminar details' : 'Set up a new seminar or training session'; ?></p>
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
                    
                    <div class="form-container">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">
                                        <i class="fas fa-heading me-2"></i>Seminar Title *
                                    </label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo $seminar ? htmlspecialchars($seminar['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="organization" class="form-label">
                                        <i class="fas fa-building me-2"></i>Organization Name *
                                    </label>
                                    <input type="text" class="form-control" id="organization" name="organization" 
                                           value="<?php echo $seminar ? htmlspecialchars($seminar['organization']) : (isset($_POST['organization']) ? htmlspecialchars($_POST['organization']) : ''); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left me-2"></i>Theme / Description
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo $seminar ? htmlspecialchars($seminar['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="date" class="form-label">
                                        <i class="fas fa-calendar me-2"></i>Date *
                                    </label>
                                    <input type="date" class="form-control" id="date" name="date" 
                                           value="<?php echo $seminar ? $seminar['date'] : (isset($_POST['date']) ? $_POST['date'] : ''); ?>"
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="time" class="form-label">
                                        <i class="fas fa-clock me-2"></i>Time *
                                    </label>
                                    <input type="time" class="form-control" id="time" name="time" 
                                           value="<?php echo $seminar ? $seminar['time'] : (isset($_POST['time']) ? $_POST['time'] : ''); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="max_slots" class="form-label">
                                        <i class="fas fa-users me-2"></i>Maximum Slots *
                                    </label>
                                    <input type="number" class="form-control" id="max_slots" name="max_slots" 
                                           value="<?php echo $seminar ? $seminar['max_slots'] : (isset($_POST['max_slots']) ? $_POST['max_slots'] : '50'); ?>"
                                           min="1" max="1000" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="venue" class="form-label">
                                        <i class="fas fa-map-marker-alt me-2"></i>Venue *
                                    </label>
                                    <input type="text" class="form-control" id="venue" name="venue" 
                                           value="<?php echo $seminar ? htmlspecialchars($seminar['venue']) : (isset($_POST['venue']) ? htmlspecialchars($_POST['venue']) : ''); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="speaker" class="form-label">
                                        <i class="fas fa-microphone me-2"></i>Speaker Name *
                                    </label>
                                    <input type="text" class="form-control" id="speaker" name="speaker" 
                                           value="<?php echo $seminar ? htmlspecialchars($seminar['speaker']) : (isset($_POST['speaker']) ? htmlspecialchars($_POST['speaker']) : ''); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">
                                        <i class="fas fa-toggle-on me-2"></i>Status
                                    </label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="open" <?php echo ($seminar && $seminar['status'] == 'open') || (!$seminar && !isset($_POST['status'])) ? 'selected' : ''; ?>>Open</option>
                                        <option value="closed" <?php echo ($seminar && $seminar['status'] == 'closed') || (isset($_POST['status']) && $_POST['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i><?php echo $seminar ? 'Update' : 'Create'; ?> Seminar
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($seminar && $seminar['unique_token']): ?>
                            <div class="registration-link mt-4">
                                <h6><i class="fas fa-link me-2"></i>Registration Link</h6>
                                <div class="input-group mt-2">
                                    <input type="text" class="form-control" readonly 
                                           value="<?php echo "http://localhost/web-comission/public/register.php?token=" . $seminar['unique_token']; ?>"
                                           id="regLink">
                                    <button class="copy-btn" onclick="copyLink()">
                                        <i class="fas fa-copy me-1"></i>Copy
                                    </button>
                                </div>
                                <small class="text-muted">Share this link with participants for registration</small>
                            </div>
                        <?php endif; ?>
                    </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyLink() {
            const copyText = document.getElementById("regLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            // Show feedback
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        }
    </script>
</body>
</html>
