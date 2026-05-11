<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/BillingService.php';
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

createFinancialTables();

$billingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($billingId <= 0) {
    http_response_code(400);
    echo 'Invalid billing.';
    exit();
}

$database = new Database();
$db = $database->getConnection();
$service = new BillingService($db);
$billing = $service->getBillingDetails($billingId);

if (!$billing) {
    http_response_code(404);
    echo 'Billing not found.';
    exit();
}

function cleanPdf($value) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$value);
}

function moneyPdf($amount) {
    return 'PHP ' . number_format((float)$amount, 2);
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetTitle('Billing Statement ' . $billing['billing_no']);
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, cleanPdf($billing['organization']), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Billing Statement', 0, 1);
$pdf->Ln(8);

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(120, 8, 'BILLING', 0, 0);
$pdf->Cell(70, 8, cleanPdf($billing['billing_no']), 0, 1, 'R');
$pdf->Line(10, 35, 200, 35);
$pdf->Ln(8);

$pdf->SetFont('Arial', '', 10);
$rows = [
    ['Participant', $billing['participant_name']],
    ['Email', $billing['participant_email']],
    ['Seminar', $billing['seminar_title']],
    ['Seminar Date', date('F d, Y', strtotime($billing['seminar_date']))],
    ['Venue', $billing['venue']],
    ['Due Date', $billing['due_date'] ? date('F d, Y', strtotime($billing['due_date'])) : 'N/A'],
    ['Status', ucfirst($billing['status'])],
];
foreach ($rows as $row) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(42, 7, $row[0] . ':', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(145, 7, cleanPdf($row[1]));
}

$pdf->Ln(8);
$pdf->SetFillColor(248, 249, 250);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(130, 9, 'Description', 1, 0, 'L', true);
$pdf->Cell(55, 9, 'Amount', 1, 1, 'R', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(130, 11, cleanPdf('Seminar registration billing'), 1, 0);
$pdf->Cell(55, 11, moneyPdf($billing['amount']), 1, 1, 'R');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(130, 10, 'Total Paid', 1, 0, 'R');
$pdf->Cell(55, 10, moneyPdf($billing['amount'] - $billing['balance']), 1, 1, 'R');
$pdf->Cell(130, 10, 'Balance Due', 1, 0, 'R');
$pdf->Cell(55, 10, moneyPdf($billing['balance']), 1, 1, 'R');

if (!empty($billing['remarks'])) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'Remarks', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 7, cleanPdf($billing['remarks']));
}

$pdf->SetY(265);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s') . ' by ' . cleanPdf($_SESSION['admin_name']), 0, 1, 'C');

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '_', $billing['billing_no']) . '.pdf"');
$pdf->Output('I');
exit();
