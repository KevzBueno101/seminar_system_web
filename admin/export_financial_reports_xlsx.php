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

// replicate the queries from financial_reports.php
$daily = [];
$seminarRevenue = [];
$outstanding = [];

try {
    $stmt = $db->query("SELECT DATE(payment_date) AS payment_day, SUM(amount_paid) AS total
        FROM payments
        GROUP BY DATE(payment_date)
        ORDER BY payment_day DESC
        LIMIT 14");
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT s.title,
            COALESCE(SUM(pay.amount_paid), 0) AS collected,
            COALESCE(SUM(DISTINCT b.amount), 0) AS billed
        FROM seminars s
        JOIN billings b ON b.seminar_id = s.id
        LEFT JOIN payments pay ON pay.billing_id = b.id
        WHERE b.status <> 'cancelled'
        GROUP BY s.id
        ORDER BY collected DESC");
    $seminarRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query("SELECT b.billing_no, b.balance, p.name AS participant_name,
            s.title AS seminar_title, b.due_date
        FROM billings b
        JOIN participants p ON b.participant_id = p.id
        JOIN seminars s ON b.seminar_id = s.id
        WHERE b.status IN ('pending', 'partial') AND b.balance > 0
        ORDER BY b.due_date IS NULL, b.due_date ASC, b.created_at ASC
        LIMIT 25");
    $outstanding = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Keep export functional even if some queries fail
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($amount) {
    return 'PHP ' . number_format((float)$amount, 2);
}

// Excel-compatible HTML export
$filenameBase = 'financial_reports_' . date('Y-m-d_H-i-s');
// Use .xls filename for better compatibility with Excel/WPS when serving HTML as Excel
$filename = $filenameBase . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>

<h3>Financial Summary</h3>
<table border="1" cellspacing="0" cellpadding="5">
    <tr>
        <th>Total Billed</th>
        <th>Total Collections</th>
        <th>Outstanding</th>
        <th>Today</th>
    </tr>
    <tr>
        <td><?php echo h(money($summary['total_billed'] ?? 0)); ?></td>
        <td><?php echo h(money($summary['total_collected'] ?? 0)); ?></td>
        <td><?php echo h(money($summary['outstanding'] ?? 0)); ?></td>
        <td><?php echo h(money($summary['today_collected'] ?? 0)); ?></td>
    </tr>
</table>
<br>

<h3>Daily Collections (last 14)</h3>
<table border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr><th>Date</th><th>Collected</th></tr>
    </thead>
    <tbody>
    <?php foreach ($daily as $row): ?>
        <tr>
            <td><?php echo h(isset($row['payment_day']) ? date('M d, Y', strtotime($row['payment_day'])) : ''); ?></td>
            <td><?php echo h(money($row['total'] ?? 0)); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($daily)): ?>
        <tr><td colspan="2">No payments yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<br>

<h3>Seminar Revenue</h3>
<table border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr><th>Seminar</th><th>Collected</th><th>Billed</th></tr>
    </thead>
    <tbody>
    <?php foreach ($seminarRevenue as $row): ?>
        <tr>
            <td><?php echo h($row['title'] ?? ''); ?></td>
            <td><?php echo h(money($row['collected'] ?? 0)); ?></td>
            <td><?php echo h(money($row['billed'] ?? 0)); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($seminarRevenue)): ?>
        <tr><td colspan="3">No billed seminars yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<br>

<h3>Outstanding Balances</h3>
<table border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr><th>Billing</th><th>Participant</th><th>Seminar</th><th>Due</th><th>Balance</th></tr>
    </thead>
    <tbody>
    <?php foreach ($outstanding as $row): ?>
        <tr>
            <td><?php echo h($row['billing_no'] ?? ''); ?></td>
            <td><?php echo h($row['participant_name'] ?? ''); ?></td>
            <td><?php echo h($row['seminar_title'] ?? ''); ?></td>
            <td><?php echo h(!empty($row['due_date']) ? date('M d, Y', strtotime($row['due_date'])) : 'N/A'); ?></td>
            <td><?php echo h(money($row['balance'] ?? 0)); ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($outstanding)): ?>
        <tr><td colspan="5">No outstanding balances.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

</body>
</html>

