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

$error = '';
$success = '';

// Get filter parameters
$selected_seminar = isset($_GET['seminar']) ? (int)$_GET['seminar'] : '';

// Handle participant deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM participants WHERE id = :id");
        $stmt->bindParam(':id', $delete_id);
        if ($stmt->execute()) {
            $success = 'Participant removed successfully!';
        }
    } catch(PDOException $exception) {
        $error = 'Failed to remove participant: ' . $exception->getMessage();
    }
}

// Get seminars for filter dropdown
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

// Get participants
$participants = [];
if ($db) {
    try {
        $sql = "
            SELECT p.*, s.title, s.date, s.max_slots 
            FROM participants p 
            JOIN seminars s ON p.seminar_id = s.id
        ";
        
        if ($selected_seminar) {
            $sql .= " WHERE p.seminar_id = :seminar_id";
        }
        
        $sql .= " ORDER BY p.registered_at DESC";
        
        $stmt = $db->prepare($sql);
        if ($selected_seminar) {
            $stmt->bindParam(':seminar_id', $selected_seminar);
        }
        $stmt->execute();
        $participants = $stmt->fetchAll();
    } catch(PDOException $exception) {
        error_log("Participants error: " . $exception->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participants - Seminar Management System</title>
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
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            max-width: none;
            width: 100%;
        }
        
        .table {
            width: 100%;
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding: 12px 16px;
        }
        
        .table td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f5;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        .seminar-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .badge {
            font-size: 12px;
            padding: 6px 12px;
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
            .table-container {
                padding: 15px;
            }
            .table th,
            .table td {
                padding: 8px 12px;
            }
        }
        .action-btn {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
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
                        <a class="nav-link active" href="participants.php">
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
                            <h2 class="fw-bold">Participants Management</h2>
                            <p class="text-muted">View and manage seminar participants</p>
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
                    
                    <!-- Seminar Statistics -->
                    <div class="seminar-stats">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h5><?php echo count($seminars); ?></h5>
                                <small>Total Seminars</small>
                            </div>
                            <div class="col-md-3">
                                <h5><?php echo count($participants); ?></h5>
                                <small>Total Participants</small>
                            </div>
                            <div class="col-md-3">
                                <h5><?php 
                                    $open_seminars = array_filter($seminars, function($s) { return $s['status'] == 'open'; });
                                    echo count($open_seminars);
                                ?></h5>
                                <small>Open Seminars</small>
                            </div>
                            <div class="col-md-3">
                                <h5><?php 
                                    $total_slots = array_sum(array_column($seminars, 'max_slots'));
                                    echo $total_slots;
                                ?></h5>
                                <small>Total Slots</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Card -->
                    <div class="filter-card">
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <label for="seminar_filter" class="form-label">
                                    <i class="fas fa-filter me-2"></i>Filter by Seminar
                                </label>
                                <select class="form-select" id="seminar_filter" onchange="filterBySeminar()">
                                    <option value="">All Seminars</option>
                                    <?php foreach ($seminars as $seminar): ?>
                                        <option value="<?php echo $seminar['id']; ?>" <?php echo $selected_seminar == $seminar['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($seminar['title']); ?> 
                                            (<?php echo $seminar['participant_count']; ?>/<?php echo $seminar['max_slots']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary" onclick="exportData()">
                                    <i class="fas fa-download me-1"></i>Export Data
                                </button>
                                <button class="btn btn-outline-secondary ms-2" onclick="clearFilter()">
                                    <i class="fas fa-times me-1"></i>Clear Filter
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Participants Table -->
                    <div class="table-container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                Participants Attendance List 
                                <?php if ($selected_seminar): ?>
                                    <small class="text-muted">(Filtered)</small>
                                <?php endif; ?>
                            </h5>
                            <span class="badge bg-info">
                                <?php echo count($participants); ?> Participants
                            </span>
                        </div>
                        
                        <?php if (empty($participants)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No participants found</h5>
                                <p class="text-muted">
                                    <?php if ($selected_seminar): ?>
                                        No participants registered for this seminar yet.
                                    <?php else: ?>
                                        No participants registered for any seminar yet.
                                    <?php endif; ?>
                                </p>
                                <a href="create_seminar.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create Seminar
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Seminar</th>
                                            <th>Date</th>
                                            <th>Registered</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($participants as $participant): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($participant['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($participant['email']); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($participant['email']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($participant['title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($participant['date'])); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($participant['date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y h:i A', strtotime($participant['registered_at'])); ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $seminar_participants = array_filter($participants, function($p) use ($participant) {
                                                            return $p['seminar_id'] == $participant['seminar_id'];
                                                        });
                                                        $registered_count = count($seminar_participants);
                                                        
                                                        if ($registered_count >= $participant['max_slots']): ?>
                                                            <span class="badge bg-danger">Full</span>
                                                        <?php elseif ($registered_count >= $participant['max_slots'] * 0.8): ?>
                                                            <span class="badge bg-warning">Limited</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Available</span>
                                                        <?php endif; ?>
                                                    
                                                    <div class="progress mt-1" style="height: 4px;">
                                                        <?php $percentage = ($registered_count / $participant['max_slots']) * 100; ?>
                                                        <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-danger' : ($percentage >= 60 ? 'bg-warning' : 'bg-success'); ?>" 
                                                             style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo $registered_count; ?>/<?php echo $participant['max_slots']; ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="generate_certificates.php?seminar=<?php echo $participant['seminar_id']; ?>&participant=<?php echo $participant['id']; ?>" 
                                                           class="btn btn-outline-success action-btn" title="Generate Certificate" target="_blank">
                                                            <i class="fas fa-certificate"></i>
                                                        </a>
                                                        <a href="mailto:<?php echo htmlspecialchars($participant['email']); ?>" 
                                                           class="btn btn-outline-info action-btn" title="Send Email">
                                                            <i class="fas fa-envelope"></i>
                                                        </a>
                                                        <button class="btn btn-outline-danger action-btn" 
                                                                onclick="confirmDelete(<?php echo $participant['id']; ?>, '<?php echo htmlspecialchars($participant['name']); ?>')" 
                                                                title="Remove Participant">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterBySeminar() {
            const seminarId = document.getElementById('seminar_filter').value;
            window.location.href = 'participants.php?seminar=' + seminarId;
        }
        
        function clearFilter() {
            window.location.href = 'participants.php';
        }
        
        function exportData() {
            // Simple CSV export
            let csv = 'Name,Email,Seminar,Date,Registered\n';
            <?php foreach ($participants as $participant): ?>
                csv += '<?php echo addslashes($participant['name']); ?>,<?php echo addslashes($participant['email']); ?>,<?php echo addslashes($participant['title']); ?>,<?php echo $participant['date']; ?>,<?php echo $participant['registered_at']; ?>\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'participants.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to remove ${name} from this seminar?`)) {
                window.location.href = 'participants.php?delete=' + id;
            }
        }
    </script>
</body>
</html>
