<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/BillingService.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$service = new BillingService($db);
$summary = $service->financialSummary();

$daily = [];
$seminarRevenue = [];
$outstanding = [];

try {
    $stmt = $db->query("
        SELECT DATE(payment_date) AS payment_day, SUM(amount_paid) AS total
        FROM payments
        GROUP BY DATE(payment_date)
        ORDER BY payment_day DESC
        LIMIT 14
    ");
    $daily = $stmt->fetchAll();

    $stmt = $db->query("
        SELECT s.title, COALESCE(SUM(pay.amount_paid), 0) AS collected, COALESCE(SUM(DISTINCT b.amount), 0) AS billed
        FROM seminars s
        JOIN billings b ON b.seminar_id = s.id
        LEFT JOIN payments pay ON pay.billing_id = b.id
        WHERE b.status <> 'cancelled'
        GROUP BY s.id
        ORDER BY collected DESC
    ");
    $seminarRevenue = $stmt->fetchAll();

    $stmt = $db->query("
        SELECT b.billing_no, b.balance, p.name AS participant_name, s.title AS seminar_title, b.due_date
        FROM billings b
        JOIN participants p ON b.participant_id = p.id
        JOIN seminars s ON b.seminar_id = s.id
        WHERE b.status IN ('pending', 'partial') AND b.balance > 0
        ORDER BY b.due_date IS NULL, b.due_date ASC, b.created_at ASC
        LIMIT 25
    ");
    $outstanding = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Financial report error: ' . $e->getMessage());
}

function reportMoney($amount) {
    return 'PHP ' . number_format((float)$amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; margin: 0; padding: 0; min-height: 100vh; }
        
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .sidebar { background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%); min-height: 100vh; color: white; position: fixed; top: 0; left: 0; width: 240px; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-link { color: rgba(255,255,255,.8); padding: 12px 20px; margin: 5px 0; border-radius: 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.2); color: white; }
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
        
        .panel, .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            max-width: none;
            width: 100%;
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
        .stat-card .label { color: #6c757d; font-size: 13px; }
        .stat-card .value { font-size: 24px; font-weight: 700; }
        
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
            .panel, .stat-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar p-4">
                <div class="text-center mb-4"><h4><i class="fas fa-graduation-cap me-2"></i>SeminarMS</h4></div>
                <div class="mb-4 text-center">
                    <i class="fas fa-user-circle fa-3x mb-3"></i>
                    <h6><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h6>
                    <small><?php echo htmlspecialchars($_SESSION['admin_email']); ?></small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a class="nav-link" href="create_seminar.php"><i class="fas fa-plus-circle me-2"></i>Create Seminar</a>
                    <a class="nav-link" href="participants.php"><i class="fas fa-users me-2"></i>Participants</a>
                    <a class="nav-link" href="billings.php"><i class="fas fa-file-invoice-dollar me-2"></i>Billings</a>
                    <a class="nav-link active" href="financial_reports.php"><i class="fas fa-chart-line me-2"></i>Financial Reports</a>
                    <a class="nav-link" href="generate_certificates.php"><i class="fas fa-certificate me-2"></i>Generate Certificates</a>
                    <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                    <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" style="padding-top: 20px;">
<div class="dashboard-header">
                    <div class="dashboard-title">
                        <h2 class="fw-bold">Financial Reports</h2>
                        <p class="text-muted mb-0">Collection summaries and outstanding balances</p>
                    </div>
                    <div class="dashboard-actions d-flex align-items-center gap-2">
                        <a href="billings.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Billings</a>
                        <a
                            href="export_financial_reports_xlsx.php"
                            class="btn btn-outline-success"
                            title="Export reports to Excel (.xlsx compatible)"
                        >
                            <i class="fas fa-file-excel me-1"></i>Export XLSX
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3"><div class="stat-card"><div class="label">Total Billed</div><div class="value"><?php echo reportMoney($summary['total_billed']); ?></div></div></div>
                    <div class="col-md-3"><div class="stat-card"><div class="label">Total Collections</div><div class="value text-success"><?php echo reportMoney($summary['total_collected']); ?></div></div></div>
                    <div class="col-md-3"><div class="stat-card"><div class="label">Outstanding</div><div class="value text-danger"><?php echo reportMoney($summary['outstanding']); ?></div></div></div>
                    <div class="col-md-3"><div class="stat-card"><div class="label">Today</div><div class="value"><?php echo reportMoney($summary['today_collected']); ?></div></div></div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="panel">
                            <h5 class="mb-3">Daily Collections</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Date</th><th class="text-end">Collected</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($daily as $row): ?>
                                        <tr><td><?php echo date('M d, Y', strtotime($row['payment_day'])); ?></td><td class="text-end"><?php echo reportMoney($row['total']); ?></td></tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($daily)): ?><tr><td colspan="2" class="text-muted text-center">No payments yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="panel">
                            <h5 class="mb-3">Seminar Revenue</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Seminar</th><th class="text-end">Collected</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($seminarRevenue as $row): ?>
                                        <tr><td><?php echo htmlspecialchars($row['title']); ?></td><td class="text-end"><?php echo reportMoney($row['collected']); ?></td></tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($seminarRevenue)): ?><tr><td colspan="2" class="text-muted text-center">No billed seminars yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="panel">
                            <h5 class="mb-3">Outstanding Balances</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Billing</th><th>Participant</th><th>Seminar</th><th>Due</th><th class="text-end">Balance</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($outstanding as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['billing_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['participant_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['seminar_title']); ?></td>
                                            <td><?php echo $row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'N/A'; ?></td>
                                            <td class="text-end"><?php echo reportMoney($row['balance']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($outstanding)): ?><tr><td colspan="5" class="text-muted text-center">No outstanding balances.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
        </main>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
