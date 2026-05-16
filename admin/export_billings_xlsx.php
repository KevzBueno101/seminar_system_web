<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/BillingService.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// NOTE: We export as Excel-compatible HTML (BIFF-free) to avoid adding new PHP libraries.
// Excel (and WPS Spreadsheets) can open HTML tables when served with the XLS content type.

$database = new Database();
$db = $database->getConnection();
$service = new BillingService($db);

$selectedStatus = isset($_GET['status']) ? (string)$_GET['status'] : '';
$selectedSeminar = isset($_GET['seminar']) ? (int)$_GET['seminar'] : 0;

$allowedStatuses = ['pending', 'partial', 'paid', 'cancelled'];
if ($selectedStatus !== '' && !in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

$billings = $service->listBillings($selectedStatus, $selectedSeminar);

$filenameBase = 'billings_' . date('Y-m-d_H-i-s');
// File extension: even if we output HTML, Excel will open it fine when MIME type is set.
$filename = $filenameBase . '.xlsx';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Encode helpers
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($amount) {
    return 'PHP ' . number_format((float)$amount, 2);
}

function statusBadgeText($status) {
    return ucfirst((string)$status);
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>

<table border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr>
            <th>Billing No</th>
            <th>Created At</th>
            <th>Participant</th>
            <th>Seminar</th>
            <th>Amount</th>
            <th>Balance</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($billings as $b): ?>
            <tr>
                <td><?php echo h($b['billing_no'] ?? ''); ?></td>
                <td><?php echo h(isset($b['created_at']) ? date('M d, Y', strtotime($b['created_at'])) : ''); ?></td>
                <td><?php echo h($b['participant_name'] ?? ''); ?></td>
                <td><?php echo h($b['seminar_title'] ?? ''); ?></td>
                <td><?php echo h(isset($b['amount']) ? money($b['amount']) : ''); ?></td>
                <td><?php echo h(isset($b['balance']) ? money($b['balance']) : ''); ?></td>
                <td><?php echo h(statusBadgeText($b['status'] ?? '')); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

