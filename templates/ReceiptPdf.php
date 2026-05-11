<?php

class ReceiptPdf {
    public static function generate(PDO $db, $receiptId) {
        require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

        $receipt = self::getReceiptData($db, $receiptId);
        if (!$receipt) {
            throw new Exception('Receipt data was not found.');
        }

        $dir = __DIR__ . '/../receipts';
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = self::safeFilename($receipt['receipt_no']) . '.pdf';
        $path = $dir . '/' . $filename;

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetTitle('Official Receipt ' . $receipt['receipt_no']);
        $pdf->SetAuthor($receipt['organization']);
        $pdf->AddPage();

        self::header($pdf, $receipt);
        self::receiptBody($pdf, $receipt);
        self::footer($pdf, $receipt);

        $pdf->Output($path, 'F');
        return 'receipts/' . $filename;
    }

    public static function getReceiptData(PDO $db, $receiptId) {
        $stmt = $db->prepare("
            SELECT r.*, pay.payment_no, pay.amount_paid, pay.payment_method, pay.reference_number,
                   pay.payment_date, pay.notes, b.billing_no, b.amount AS billing_amount, b.balance,
                   p.name AS participant_name, p.email AS participant_email,
                   s.title AS seminar_title, s.date AS seminar_date, s.venue, s.organization,
                   issuer.name AS issued_by_name, receiver.name AS received_by_name
            FROM receipts r
            JOIN payments pay ON r.payment_id = pay.id
            JOIN billings b ON pay.billing_id = b.id
            JOIN participants p ON b.participant_id = p.id
            JOIN seminars s ON b.seminar_id = s.id
            LEFT JOIN admins issuer ON r.issued_by = issuer.id
            LEFT JOIN admins receiver ON pay.received_by = receiver.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $receiptId]);
        return $stmt->fetch();
    }

    private static function header(FPDF $pdf, array $receipt) {
        $logo = __DIR__ . '/logo.png';
        if (file_exists($logo)) {
            $pdf->Image($logo, 16, 13, 20, 20);
        } else {
            $pdf->SetFillColor(102, 126, 234);
            $pdf->Rect(16, 13, 20, 20, 'F');
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetXY(16, 20);
            $pdf->Cell(20, 5, 'SMS', 0, 0, 'C');
        }

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetXY(42, 13);
        $pdf->Cell(110, 8, self::clean($receipt['organization']), 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetX(42);
        $pdf->Cell(110, 6, 'Seminar Management System', 0, 1);
        $pdf->SetX(42);
        $pdf->Cell(110, 6, 'Official payment receipt', 0, 1);

        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetXY(142, 14);
        $pdf->Cell(52, 9, 'OFFICIAL RECEIPT', 0, 1, 'R');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetX(142);
        $pdf->SetTextColor(102, 48, 150);
        $pdf->Cell(52, 8, self::clean($receipt['receipt_no']), 0, 1, 'R');
        $pdf->SetTextColor(20, 20, 20);

        if ($receipt['receipt_status'] !== 'active') {
            $pdf->SetTextColor(190, 30, 45);
            $pdf->SetFont('Arial', 'B', 36);
            $pdf->SetXY(45, 120);
            $pdf->Cell(120, 12, strtoupper($receipt['receipt_status']), 0, 1, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(16, 40, 194, 40);
    }

    private static function receiptBody(FPDF $pdf, array $receipt) {
        $pdf->SetXY(16, 50);
        $pdf->SetFont('Arial', '', 10);
        self::labelValue($pdf, 'Participant', $receipt['participant_name']);
        self::labelValue($pdf, 'Email', $receipt['participant_email']);
        self::labelValue($pdf, 'Seminar', $receipt['seminar_title']);
        self::labelValue($pdf, 'Seminar Date', date('F d, Y', strtotime($receipt['seminar_date'])));
        self::labelValue($pdf, 'Venue', $receipt['venue']);

        $pdf->Ln(8);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(225, 225, 225);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(78, 10, 'Description', 1, 0, 'L', true);
        $pdf->Cell(50, 10, 'Reference', 1, 0, 'L', true);
        $pdf->Cell(50, 10, 'Amount', 1, 1, 'R', true);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(78, 12, 'Seminar payment', 1, 0);
        $pdf->Cell(50, 12, self::clean($receipt['billing_no']), 1, 0);
        $pdf->Cell(50, 12, self::money($receipt['amount_paid']), 1, 1, 'R');

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(128, 12, 'Total Paid', 1, 0, 'R');
        $pdf->Cell(50, 12, self::money($receipt['amount_paid']), 1, 1, 'R');

        $pdf->Ln(8);
        $pdf->SetFont('Arial', '', 10);
        self::labelValue($pdf, 'Payment Method', self::methodLabel($receipt['payment_method']));
        self::labelValue($pdf, 'Reference Number', $receipt['reference_number'] ?: 'N/A');
        self::labelValue($pdf, 'Date Paid', date('F d, Y h:i A', strtotime($receipt['payment_date'])));
        self::labelValue($pdf, 'Processed By', $receipt['received_by_name'] ?: $receipt['issued_by_name']);
        self::labelValue($pdf, 'Issued At', date('F d, Y h:i A', strtotime($receipt['issued_at'])));
        self::labelValue($pdf, 'Remaining Balance', self::money($receipt['balance']));

        $pdf->Ln(16);
        $pdf->Cell(78, 8, '', 0, 0);
        $pdf->Cell(52, 8, '', 0, 0);
        $pdf->Cell(48, 8, '________________________', 0, 1, 'C');
        $pdf->Cell(78, 6, '', 0, 0);
        $pdf->Cell(52, 6, '', 0, 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(48, 6, 'Authorized Signature', 0, 1, 'C');
    }

    private static function footer(FPDF $pdf, array $receipt) {
        $pdf->SetY(266);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->Line(16, 262, 194, 262);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s') . ' | Receipt ID: ' . $receipt['id'], 0, 1, 'C');
        $pdf->Cell(0, 5, 'This receipt is system-generated. Keep this copy for your records.', 0, 1, 'C');
    }

    private static function labelValue(FPDF $pdf, $label, $value) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(42, 7, $label . ':', 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(136, 7, self::clean((string)$value), 0, 'L');
    }

    private static function money($amount) {
        return 'PHP ' . number_format((float)$amount, 2);
    }

    private static function methodLabel($method) {
        $labels = [
            'cash' => 'Cash',
            'gcash' => 'GCash',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online',
        ];
        return $labels[$method] ?? $method;
    }

    private static function safeFilename($value) {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $value);
    }

    private static function clean($value) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$value);
    }
}
