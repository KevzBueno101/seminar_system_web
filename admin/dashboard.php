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
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
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
            padding: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
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
        .stat-icon.seminars { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.participants { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.upcoming { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
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
        .quick-action-card.create .icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .quick-action-card.participants .icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 p-0">
                <div class="sidebar p-4">
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
                        <a class="nav-link" href="generate_certificates.php">
                            <i class="fas fa-certificate me-2"></i>Generate Certificates
                        </a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="main-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="fw-bold">Dashboard</h2>
                            <p class="text-muted">Manage your seminars and participants</p>
                        </div>
                        <div>
                            <span class="badge bg-success">
                                <i class="fas fa-circle me-1"></i>System Online
                            </span>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
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
                        </div>
                        <div class="col-md-4 mb-3">
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
                        </div>
                        <div class="col-md-4 mb-3">
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
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <a href="create_seminar.php" class="text-decoration-none">
                                <div class="quick-action-card create">
                                    <div class="icon">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <h5>Create New Seminar</h5>
                                    <p class="text-muted">Set up a new seminar or training session</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="participants.php" class="text-decoration-none">
                                <div class="quick-action-card participants">
                                    <div class="icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h5>Manage Participants</h5>
                                    <p class="text-muted">View and manage seminar participants</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="generate_certificates.php" class="text-decoration-none">
                                <div class="quick-action-card certificates">
                                    <div class="icon">
                                        <i class="fas fa-certificate"></i>
                                    </div>
                                    <h5>Generate Certificates</h5>
                                    <p class="text-muted">Create and send certificates to participants</p>
                                </div>
                            </a>
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
