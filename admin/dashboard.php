<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get statistics
$total_seminars = 0;
$total_participants = 0;
$upcoming_seminars = 0;

if ($db) {
    try {
        // Total seminars
        $stmt = $db->query("SELECT COUNT(*) as count FROM seminars");
        $total_seminars = $stmt->fetch()['count'];

        // Total participants
        $stmt = $db->query("SELECT COUNT(*) as count FROM participants");
        $total_participants = $stmt->fetch()['count'];

        // Upcoming seminars
        $stmt = $db->query("SELECT COUNT(*) as count FROM seminars WHERE date >= CURDATE() AND status = 'open'");
        $upcoming_seminars = $stmt->fetch()['count'];
    } catch(PDOException $exception) {
        error_log("Statistics error: " . $exception->getMessage());
    }
}

// Get recent seminars
$recent_seminars = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT s.*, COUNT(p.id) as participant_count 
            FROM seminars s 
            LEFT JOIN participants p ON s.id = p.seminar_id 
            ORDER BY s.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recent_seminars = $stmt->fetchAll();
    } catch(PDOException $exception) {
        error_log("Recent seminars error: " . $exception->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
background-image: none;
            background-size: cover;
background-position: initial;
background-attachment: initial;
            background-repeat: no-repeat;
        }
        
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .sidebar {
background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            z-index: 1000;
            overflow-y: auto;
            display: block !important;
            visibility: visible !important;
            transform: translateX(0) !important;
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
            background-color: rgba(248, 249, 250, 0.95);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        .sidebar::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.25);
            border-radius: 999px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
.stat-icon.seminars { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
.stat-icon.participants { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
.stat-icon.upcoming { background: linear-gradient(135deg, #0b7285 0%, #4aa3ff 100%); }
        
        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .quick-action-card .icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin: 0 auto 20px;
        }
        .quick-action-card.create .icon { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
.quick-action-card.participants .icon { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
        .quick-action-card.certificates .icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .badge {
            font-size: 12px;
            padding: 6px 12px;
        }
        
        .quick-actions-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .quick-action-btn {
            display: block;
            text-decoration: none;
            color: inherit;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px 20px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            flex: 1;
            min-width: 200px;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background: white;
            text-decoration: none;
            color: inherit;
        }
        
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 15px;
            font-size: 16px;
        }
        
.action-icon.create { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
.action-icon.participants { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
.action-icon.billing { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
.action-icon.certificates { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); }
        
        .action-text strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .action-text small {
            color: #6c757d;
            font-size: 12px;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
        
        .dashboard-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .dashboard-title {
            flex: 1;
            min-width: 200px;
        }
        
        .dashboard-status {
            flex-shrink: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        @media (min-width: 1200px) {
            .content-grid {
                grid-template-columns: 2fr 1fr;
            }
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
            .quick-action-btn {
                min-width: 100%;
                margin-bottom: 10px;
            }
            .action-text strong {
                font-size: 13px;
            }
            .action-text small {
                font-size: 11px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .content-grid {
                grid-template-columns: 1fr;
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
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar (fixed) -->
        <aside class="sidebar p-4" aria-label="Admin sidebar">
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
                        <a class="nav-link active" href="dashboard.php">
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
                            <h2 class="fw-bold">Dashboard</h2>
                            <p class="text-muted">Manage your seminars and participants</p>
                        </div>
                        <div class="dashboard-status">
                            <span class="badge bg-success">
                                <i class="fas fa-circle me-1"></i>System Online
                            </span>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon seminars me-3">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?php echo $total_seminars; ?></h3>
                                    <p class="text-muted mb-0">Total Seminars</p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon participants me-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?php echo $total_participants; ?></h3>
                                    <p class="text-muted mb-0">Total Participants</p>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon upcoming me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1"><?php echo $upcoming_seminars; ?></h3>
                                    <p class="text-muted mb-0">Upcoming Seminars</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="quick-actions-container">
                                <h5 class="mb-3">Quick Actions</h5>
                                <div class="d-flex flex-wrap gap-3">
                                    <a href="create_seminar.php" class="quick-action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="action-icon create">
                                                <i class="fas fa-plus"></i>
                                            </div>
                                            <div class="action-text">
                                                <strong>Create Seminar</strong>
                                                <small>Set up new seminar</small>
                                            </div>
                                        </div>
                                    </a>
                                    <a href="participants.php" class="quick-action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="action-icon participants">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="action-text">
                                                <strong>Manage Participants</strong>
                                                <small>View participants</small>
                                            </div>
                                        </div>
                                    </a>
                                    <a href="billings.php" class="quick-action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="action-icon billing">
                                                <i class="fas fa-file-invoice-dollar"></i>
                                            </div>
                                            <div class="action-text">
                                                <strong>Billing</strong>
                                                <small>Manage payments</small>
                                            </div>
                                        </div>
                                    </a>
                                    <a href="generate_certificates.php" class="quick-action-btn">
                                        <div class="d-flex align-items-center">
                                            <div class="action-icon certificates">
                                                <i class="fas fa-certificate"></i>
                                            </div>
                                            <div class="action-text">
                                                <strong>Certificates</strong>
                                                <small>Generate certs</small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Seminars Table -->
                    <div class="table-container">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Recent Seminars</h5>
                            <a href="create_seminar.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Add New Seminar
                            </a>
                        </div>
                        
                        <?php if (empty($recent_seminars)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No seminars yet</h5>
                                <p class="text-muted">Create your first seminar to get started</p>
                                <a href="create_seminar.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Create Seminar
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Date</th>
                                            <th>Participants</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_seminars as $seminar): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($seminar['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($seminar['venue']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($seminar['date'])); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($seminar['time'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo $seminar['participant_count']; ?> / <?php echo $seminar['max_slots']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($seminar['status'] == 'open'): ?>
                                                        <span class="badge bg-success">Open</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Closed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="create_seminar.php?edit=<?php echo $seminar['id']; ?>" class="btn btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="participants.php?seminar=<?php echo $seminar['id']; ?>" class="btn btn-outline-info">
                                                            <i class="fas fa-users"></i>
                                                        </a>
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
</body>
</html>
