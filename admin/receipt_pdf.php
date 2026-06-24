<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../templates/ReceiptPdf.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$receiptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($receiptId <= 0) {
    http_response_code(400);
    echo 'Invalid receipt.';
    exit();
}

$database = new Database();
$db = $database->getConnection();
$receipt = ReceiptPdf::getReceiptData($db, $receiptId);

if (!$receipt) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit();
}

$relativePath = $receipt['pdf_path'];
$absolutePath = $relativePath ? realpath(__DIR__ . '/../' . $relativePath) : false;

if (!$absolutePath || !file_exists($absolutePath)) {
    $relativePath = ReceiptPdf::generate($db, $receiptId);
    $stmt = $db->prepare("UPDATE receipts SET pdf_path = :pdf_path WHERE id = :id");
    $stmt->execute([':pdf_path' => $relativePath, ':id' => $receiptId]);
    $absolutePath = realpath(__DIR__ . '/../' . $relativePath);
}

$download = isset($_GET['download']);
$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $receipt['receipt_no']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
readfile($absolutePath);
exit();
