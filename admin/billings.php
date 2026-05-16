<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/BillingService.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

createFinancialTables();

$database = new Database();
$db = $database->getConnection();
$service = new BillingService($db);

$error = '';
$success = '';
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';
$selectedSeminar = isset($_GET['seminar']) ? (int)$_GET['seminar'] : 0;
$selectedBillingId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['generate_billing'])) {
            $billing = $service->generateBilling(
                (int)$_POST['participant_id'],
                $_POST['amount'],
                $_POST['due_date'],
                trim($_POST['remarks']),
                (int)$_SESSION['admin_id']
            );
            $success = 'Billing ' . htmlspecialchars($billing['billing_no']) . ' generated successfully.';
            $selectedBillingId = (int)$billing['id'];
        }

        if (isset($_POST['record_payment'])) {
            $receipt = $service->recordPayment(
                (int)$_POST['billing_id'],
                $_POST['amount_paid'],
                $_POST['payment_method'],
                trim($_POST['reference_number']),
                $_POST['payment_date'],
                trim($_POST['notes']),
                (int)$_SESSION['admin_id']
            );
            $success = 'Payment recorded and receipt ' . htmlspecialchars($receipt['receipt_no']) . ' generated.';
            $selectedBillingId = (int)$receipt['billing_id'];
        }

        if (isset($_POST['void_receipt'])) {
            $service->voidReceipt((int)$_POST['receipt_id'], trim($_POST['void_reason']), (int)$_SESSION['admin_id']);
            $success = 'Receipt voided successfully.';
            $selectedBillingId = (int)$_POST['billing_id'];
        }

        if (isset($_POST['cancel_billing'])) {
            $service->cancelBilling((int)$_POST['billing_id'], (int)$_SESSION['admin_id']);
            $success = 'Billing cancelled successfully.';
            $selectedBillingId = (int)$_POST['billing_id'];
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$seminars = $service->listSeminars();
$unbilledParticipants = $service->listUnbilledParticipants();
$billings = $service->listBillings($selectedStatus, $selectedSeminar);
$summary = $service->financialSummary();
$billingDetails = $selectedBillingId ? $service->getBillingDetails($selectedBillingId) : null;

function money($amount) {
    return 'PHP ' . number_format((float)$amount, 2);
}

function statusBadge($status) {
    $classes = [
        'pending' => 'bg-warning text-dark',
        'partial' => 'bg-info text-dark',
        'paid' => 'bg-success',
        'cancelled' => 'bg-secondary',
    ];
    $class = isset($classes[$status]) ? $classes[$status] : 'bg-light text-dark';
    return '<span class="badge ' . $class . '">' . ucfirst(htmlspecialchars($status)) . '</span>';
}

function methodLabel($method) {
    $labels = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'bank_transfer' => 'Bank Transfer',
        'online' => 'Online',
    ];
    return isset($labels[$method]) ? $labels[$method] : $method;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; margin: 0; padding: 0; min-height: 100vh; }
        
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
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
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
        
        .panel, .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            max-width: none;
            width: 100%;
        }
        .stat-card { height: 100%; }
        .stat-card .label { color: #6c757d; font-size: 13px; }
        .stat-card .value { font-size: 24px; font-weight: 700; margin-top: 4px; }
        .btn-primary {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            border: none;
        }
        .table td, .table th { vertical-align: middle; }
        .receipt-row { background: #fbfbfd; }
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
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-graduation-cap me-2"></i>SeminarMS</h4>
                    </div>
                    <div class="mb-4 text-center">
                        <i class="fas fa-user-circle fa-3x mb-3"></i>
                        <h6><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h6>
                        <small><?php echo htmlspecialchars($_SESSION['admin_email']); ?></small>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                        <a class="nav-link" href="create_seminar.php"><i class="fas fa-plus-circle me-2"></i>Create Seminar</a>
                        <a class="nav-link" href="participants.php"><i class="fas fa-users me-2"></i>Participants</a>
                        <a class="nav-link active" href="billings.php"><i class="fas fa-file-invoice-dollar me-2"></i>Billings</a>
                        <a class="nav-link" href="financial_reports.php"><i class="fas fa-chart-line me-2"></i>Financial Reports</a>
                        <a class="nav-link" href="generate_certificates.php"><i class="fas fa-certificate me-2"></i>Generate Certificates</a>
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                        <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                    </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" style="padding-top: 20px;">
                    <div class="dashboard-header">
                        <div class="dashboard-title">
                            <h2 class="fw-bold">Billing Management</h2>
                            <p class="text-muted mb-0">Generate billings, record payments, and manage receipts</p>
                        </div>
                        <div class="dashboard-actions">
                            <a href="financial_reports.php" class="btn btn-outline-secondary">
                                <i class="fas fa-chart-line me-1"></i>Reports
                            </a>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3"><div class="stat-card"><div class="label">Total Billed</div><div class="value"><?php echo money($summary['total_billed']); ?></div></div></div>
                        <div class="col-md-3"><div class="stat-card"><div class="label">Collected</div><div class="value text-success"><?php echo money($summary['total_collected']); ?></div></div></div>
                        <div class="col-md-3"><div class="stat-card"><div class="label">Outstanding</div><div class="value text-danger"><?php echo money($summary['outstanding']); ?></div></div></div>
                        <div class="col-md-3"><div class="stat-card"><div class="label">Today</div><div class="value"><?php echo money($summary['today_collected']); ?></div></div></div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="panel mb-4">
                                <h5 class="mb-3"><i class="fas fa-file-invoice me-2"></i>Generate Billing</h5>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Participant Registration</label>
                                        <select name="participant_id" class="form-select" required>
                                            <option value="">Select unbilled participant...</option>
                                            <?php foreach ($unbilledParticipants as $participant): ?>
                                                <option value="<?php echo $participant['id']; ?>">
                                                    <?php echo htmlspecialchars($participant['name']); ?> - <?php echo htmlspecialchars($participant['seminar_title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Amount</label>
                                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Due Date</label>
                                            <input type="date" name="due_date" class="form-control">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Remarks</label>
                                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                                    </div>
                                    <button type="submit" name="generate_billing" class="btn btn-primary w-100">
                                        <i class="fas fa-plus me-1"></i>Generate Billing
                                    </button>
                                </form>
                            </div>

                            <?php if ($billingDetails): ?>
                                <div class="panel">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($billingDetails['billing_no']); ?></h5>
                                            <?php echo statusBadge($billingDetails['status']); ?>
                                        </div>
                                        <a href="billing_statement.php?id=<?php echo $billingDetails['id']; ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                            <i class="fas fa-print me-1"></i>Print Billing
                                        </a>
                                    </div>
                                    <p class="mb-1"><strong><?php echo htmlspecialchars($billingDetails['participant_name']); ?></strong></p>
                                    <p class="text-muted"><?php echo htmlspecialchars($billingDetails['seminar_title']); ?></p>
                                    <div class="row g-2 mb-3">
                                        <div class="col-4"><small class="text-muted">Amount</small><div><?php echo money($billingDetails['amount']); ?></div></div>
                                        <div class="col-4"><small class="text-muted">Paid</small><div><?php echo money($billingDetails['amount'] - $billingDetails['balance']); ?></div></div>
                                        <div class="col-4"><small class="text-muted">Balance</small><div><?php echo money($billingDetails['balance']); ?></div></div>
                                    </div>

                                    <?php if (!in_array($billingDetails['status'], ['paid', 'cancelled'], true)): ?>
                                        <form method="POST" class="border-top pt-3">
                                            <input type="hidden" name="billing_id" value="<?php echo $billingDetails['id']; ?>">
                                            <h6>Record Payment</h6>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <input type="number" name="amount_paid" class="form-control" min="0.01" max="<?php echo htmlspecialchars($billingDetails['balance']); ?>" step="0.01" placeholder="Amount paid" required>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <select name="payment_method" class="form-select" required>
                                                        <option value="cash">Cash</option>
                                                        <option value="gcash">GCash</option>
                                                        <option value="bank_transfer">Bank Transfer</option>
                                                        <option value="online">Online</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <input type="text" name="reference_number" class="form-control" placeholder="Reference number">
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <input type="datetime-local" name="payment_date" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                                </div>
                                            </div>
                                            <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Payment notes"></textarea>
                                            <button type="submit" name="record_payment" class="btn btn-success w-100">
                                                <i class="fas fa-cash-register me-1"></i>Record Payment and Generate Receipt
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($billingDetails['status'] === 'pending'): ?>
                                        <form method="POST" class="mt-2" onsubmit="return confirm('Cancel this unpaid billing?');">
                                            <input type="hidden" name="billing_id" value="<?php echo $billingDetails['id']; ?>">
                                            <button type="submit" name="cancel_billing" class="btn btn-outline-secondary btn-sm w-100">Cancel Billing</button>
                                        </form>
                                    <?php endif; ?>

                                    <hr>
                                    <h6>Payment History</h6>
                                    <?php if (empty($billingDetails['payments'])): ?>
                                        <p class="text-muted small mb-0">No payments recorded yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($billingDetails['payments'] as $payment): ?>
                                            <div class="receipt-row border rounded p-3 mb-2">
                                                <div class="d-flex justify-content-between gap-2">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($payment['payment_no']); ?></strong>
                                                        <div class="small text-muted"><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?> via <?php echo methodLabel($payment['payment_method']); ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong><?php echo money($payment['amount_paid']); ?></strong>
                                                        <div><?php echo statusBadge($payment['receipt_status']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="mt-2 d-flex flex-wrap gap-2">
                                                    <a class="btn btn-outline-primary btn-sm" href="receipt_pdf.php?id=<?php echo $payment['receipt_id']; ?>" target="_blank">
                                                        <i class="fas fa-file-pdf me-1"></i>Preview
                                                    </a>
                                                    <a class="btn btn-outline-secondary btn-sm" href="receipt_pdf.php?id=<?php echo $payment['receipt_id']; ?>&download=1">
                                                        <i class="fas fa-download me-1"></i>Download
                                                    </a>
                                                    <?php if ($payment['receipt_status'] === 'active'): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#void-<?php echo $payment['receipt_id']; ?>">
                                                            Void
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                <div id="void-<?php echo $payment['receipt_id']; ?>" class="collapse mt-2">
                                                    <form method="POST">
                                                        <input type="hidden" name="receipt_id" value="<?php echo $payment['receipt_id']; ?>">
                                                        <input type="hidden" name="billing_id" value="<?php echo $billingDetails['id']; ?>">
                                                        <textarea name="void_reason" class="form-control mb-2" rows="2" placeholder="Required void reason" required></textarea>
                                                        <button type="submit" name="void_receipt" class="btn btn-danger btn-sm">Void Receipt</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-7">
                            <div class="panel mb-4">
                                <form class="row g-2 align-items-end" method="GET">
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All</option>
                                            <?php foreach (['pending', 'partial', 'paid', 'cancelled'] as $status): ?>
                                                <option value="<?php echo $status; ?>" <?php echo $selectedStatus === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Seminar</label>
                                        <select name="seminar" class="form-select">
                                            <option value="">All seminars</option>
                                            <?php foreach ($seminars as $seminar): ?>
                                                <option value="<?php echo $seminar['id']; ?>" <?php echo $selectedSeminar === (int)$seminar['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($seminar['title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                                    </div>
                                </form>
                            </div>

                            <div class="panel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Billings</h5>
                                    <span class="badge bg-info"><?php echo count($billings); ?> records</span>
                                </div>
                                <?php if (empty($billings)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">No billing records found</h6>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Billing</th>
                                                    <th>Participant</th>
                                                    <th>Amount</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($billings as $billing): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($billing['billing_no']); ?></strong>
                                                            <br><small class="text-muted"><?php echo date('M d, Y', strtotime($billing['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($billing['participant_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($billing['seminar_title']); ?></small>
                                                        </td>
                                                        <td><?php echo money($billing['amount']); ?></td>
                                                        <td><?php echo money($billing['balance']); ?></td>
                                                        <td><?php echo statusBadge($billing['status']); ?></td>
                                                        <td class="text-end">
                                                            <a href="billings.php?view=<?php echo $billing['id']; ?>" class="btn btn-outline-primary btn-sm">View</a>
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
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
