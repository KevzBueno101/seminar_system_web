<?php
require_once __DIR__ . '/templates/ReceiptPdf.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/fpdf/fpdf.php';

// Test receipt generation
$database = new Database();
$db = $database->getConnection();

try {
    // Create a test receipt
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->AddPage();
    $pdf->Cell(0, 10, 'Test Receipt - FPDF Font Working!', 0, 1, 'C');
    
    $filename = 'test_receipt.pdf';
    $path = __DIR__ . '/' . $filename;
    $pdf->Output($path, 'F');
    
    echo "✅ Test receipt generated successfully: " . $filename . "\n";
    echo "✅ FPDF font loading is working correctly!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
