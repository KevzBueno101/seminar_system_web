<?php

class ReceiptPdf {
    public static function generate(PDO $db, $receiptId) {
        if (!defined('FPDF_FONTPATH')) {
            $fontPath = realpath(__DIR__ . '/../vendor/fpdf/font');
            if ($fontPath) {
                define('FPDF_FONTPATH', $fontPath . DIRECTORY_SEPARATOR);
            }
        }

        if (!class_exists('FPDF')) {
            $fpdfFile = __DIR__ . '/../vendor/fpdf/fpdf.php';
            if (!file_exists($fpdfFile)) {
                throw new Exception('FPDF library not found at: ' . $fpdfFile);
            }
            require_once $fpdfFile;
        }

        self::ensureFontFiles();

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

        // Thermal/POS friendly canvas size (58mm wide). Height is automatic via content.
        // FPDF supports custom page size: array(width_mm, height_mm). We'll use a tall height to fit content.
        $pdf = new FPDF('P', 'mm', [58, 220]);
        $pdf->SetTitle('Official Receipt ' . $receipt['receipt_no']);
        $pdf->SetAuthor($receipt['organization']);

        $pdf->SetMargins(2, 2, 2);
        $pdf->SetAutoPageBreak(true, 4);
        $pdf->AddPage();


        self::header($pdf, $receipt);
        self::receiptBody($pdf, $receipt);
        self::footer($pdf, $receipt);


        $pdf->Output($path, 'F');
        return 'receipts/' . $filename;
    }

    private static function ensureFontFiles() {
        $fontDir = realpath(__DIR__ . '/../vendor/fpdf/font');
        if (!$fontDir || !is_writable($fontDir)) {
            return;
        }

        $coreFonts = [
            'helvetica.php'  => "<?php\n\$name='Helvetica';\n\$enc='';\n\$dw=278;\n\$cw=array(\n32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584\n);\n\$desc=array('Ascent'=>718,'Descent'=>-207,'CapHeight'=>718,'Flags'=>32,'FontBBox'=>'[-166 -225 1000 931]','ItalicAngle'=>0,'StemV'=>88,'MissingWidth'=>278);\n",
            'helveticab.php' => "<?php\n\$name='Helvetica-Bold';\n\$enc='';\n\$dw=278;\n\$cw=array(\n32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>238,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,64=>975,65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>556,75=>722,76=>611,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,96=>333,97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,104=>611,105=>278,106=>278,107=>556,108=>278,109=>889,110=>611,111=>611,112=>611,113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,119=>778,120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584\n);\n\$desc=array('Ascent'=>718,'Descent'=>-207,'CapHeight'=>718,'Flags'=>32,'FontBBox'=>'[-170 -228 1003 962]','ItalicAngle'=>0,'StemV'=>140,'MissingWidth'=>278);\n",
            'helveticai.php' => "<?php\n\$name='Helvetica-Oblique';\n\$enc='';\n\$dw=278;\n\$cw=array(\n32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,126=>584\n);\n\$desc=array('Ascent'=>718,'Descent'=>-207,'CapHeight'=>718,'Flags'=>96,'FontBBox'=>'[-170 -225 1116 931]','ItalicAngle'=>-12,'StemV'=>88,'MissingWidth'=>278);\n",
            'helveticabi.php' => "<?php\n\$name='Helvetica-BoldOblique';\n\$enc='';\n\$dw=278;\n\$cw=array(\n32=>278,33=>333,34=>474,35=>556,36=>556,37=>889,38=>722,39=>238,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>333,59=>333,60=>584,61=>584,62=>584,63=>611,64=>975,65=>722,66=>722,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>556,75=>722,76=>611,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>333,92=>278,93=>333,94=>584,95=>556,96=>333,97=>556,98=>611,99=>556,100=>611,101=>556,102=>333,103=>611,104=>611,105=>278,106=>278,107=>556,108=>278,109=>889,110=>611,111=>611,112=>611,113=>611,114=>389,115=>556,116=>333,117=>611,118=>556,119=>778,120=>556,121=>556,122=>500,123=>389,124=>280,125=>389,126=>584\n);\n\$desc=array('Ascent'=>718,'Descent'=>-207,'CapHeight'=>718,'Flags'=>96,'FontBBox'=>'[-174 -228 1114 962]','ItalicAngle'=>-12,'StemV'=>140,'MissingWidth'=>278);\n",
            'times.php'      => "<?php\n\$name='Times-Roman';\n\$enc='';\n\$dw=250;\n\$cw=array(\n32=>250,33=>333,34=>408,35=>500,36=>500,37=>833,38=>778,39=>180,40=>333,41=>333,42=>500,43=>564,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>278,59=>278,60=>564,61=>564,62=>564,63=>444,64=>921,65=>722,66=>667,67=>667,68=>722,69=>611,70=>556,71=>722,72=>722,73=>333,74=>389,75=>722,76=>611,77=>889,78=>722,79=>722,80=>556,81=>722,82=>667,83=>556,84=>611,85=>722,86=>722,87=>944,88=>722,89=>722,90=>611,91=>333,92=>278,93=>333,94=>469,95=>500,96=>333,97=>444,98=>500,99=>444,100=>500,101=>444,102=>278,103=>500,104=>500,105=>278,106=>278,107=>500,108=>278,109=>778,110=>500,111=>500,112=>500,113=>500,114=>333,115=>389,116=>278,117=>500,118=>500,119=>722,120=>500,121=>500,122=>444,123=>480,124=>200,125=>480,126=>541\n);\n\$desc=array('Ascent'=>683,'Descent'=>-217,'CapHeight'=>662,'Flags'=>32,'FontBBox'=>'[-168 -218 1000 898]','ItalicAngle'=>0,'StemV'=>84,'MissingWidth'=>250);\n",
            'timesb.php'     => "<?php\n\$name='Times-Bold';\n\$enc='';\n\$dw=250;\n\$cw=array(\n32=>250,33=>333,34=>555,35=>500,36=>500,37=>1000,38=>833,39=>333,40=>333,41=>333,42=>500,43=>570,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>333,59=>333,60=>570,61=>570,62=>570,63=>500,64=>930,65=>722,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>778,73=>389,74=>500,75=>778,76=>667,77=>944,78=>722,79=>778,80=>611,81=>778,82=>722,83=>556,84=>667,85=>722,86=>722,87=>1000,88=>722,89=>722,90=>667,91=>333,92=>278,93=>333,94=>581,95=>500,96=>333,97=>500,98=>556,99=>444,100=>556,101=>444,102=>333,103=>500,104=>556,105=>278,106=>333,107=>556,108=>278,109=>833,110=>556,111=>500,112=>556,113=>556,114=>444,115=>389,116=>333,117=>556,118=>500,119=>722,120=>500,121=>500,122=>444,123=>394,124=>220,125=>394,126=>520\n);\n\$desc=array('Ascent'=>683,'Descent'=>-217,'CapHeight'=>676,'Flags'=>32,'FontBBox'=>'[-168 -218 1000 935]','ItalicAngle'=>0,'StemV'=>139,'MissingWidth'=>250);\n",
            'timesi.php'     => "<?php\n\$name='Times-Italic';\n\$enc='';\n\$dw=250;\n\$cw=array(\n32=>250,33=>333,34=>420,35=>500,36=>500,37=>833,38=>778,39=>214,40=>333,41=>333,42=>500,43=>675,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>333,59=>333,60=>675,61=>675,62=>675,63=>500,64=>920,65=>611,66=>611,67=>667,68=>722,69=>611,70=>611,71=>722,72=>722,73=>333,74=>444,75=>667,76=>556,77=>833,78=>667,79=>722,80=>611,81=>722,82=>611,83=>500,84=>556,85=>722,86=>611,87=>833,88=>611,89=>556,90=>556,91=>389,92=>278,93=>389,94=>422,95=>500,96=>333,97=>500,98=>500,99=>444,100=>500,101=>444,102=>278,103=>500,104=>500,105=>278,106=>278,107=>444,108=>278,109=>722,110=>500,111=>500,112=>500,113=>500,114=>389,115=>389,116=>278,117=>500,118=>444,119=>667,120=>444,121=>444,122=>389,123=>400,124=>275,125=>400,126=>541\n);\n\$desc=array('Ascent'=>683,'Descent'=>-217,'CapHeight'=>653,'Flags'=>96,'FontBBox'=>'[-169 -217 1010 883]','ItalicAngle'=>-15,'StemV'=>76,'MissingWidth'=>250);\n",
            'timesbi.php'    => "<?php\n\$name='Times-BoldItalic';\n\$enc='';\n\$dw=250;\n\$cw=array(\n32=>250,33=>389,34=>555,35=>500,36=>500,37=>833,38=>778,39=>333,40=>333,41=>333,42=>500,43=>570,44=>250,45=>333,46=>250,47=>278,48=>500,49=>500,50=>500,51=>500,52=>500,53=>500,54=>500,55=>500,56=>500,57=>500,58=>333,59=>333,60=>570,61=>570,62=>570,63=>500,64=>832,65=>667,66=>667,67=>667,68=>722,69=>667,70=>667,71=>722,72=>778,73=>389,74=>500,75=>667,76=>611,77=>889,78=>722,79=>722,80=>611,81=>722,82=>667,83=>556,84=>611,85=>722,86=>667,87=>889,88=>667,89=>611,90=>611,91=>333,92=>278,93=>333,94=>570,95=>500,96=>333,97=>500,98=>500,99=>444,100=>500,101=>444,102=>333,103=>500,104=>556,105=>278,106=>278,107=>500,108=>278,109=>778,110=>556,111=>500,112=>500,113=>500,114=>389,115=>389,116=>278,117=>556,118=>444,119=>667,120=>500,121=>444,122=>389,123=>348,124=>220,125=>348,126=>570\n);\n\$desc=array('Ascent'=>683,'Descent'=>-217,'CapHeight'=>669,'Flags'=>96,'FontBBox'=>'[-200 -218 996 921]','ItalicAngle'=>-15,'StemV'=>121,'MissingWidth'=>250);\n",
        ];

        foreach ($coreFonts as $file => $content) {
            $filePath = $fontDir . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath)) {
                file_put_contents($filePath, $content);
            }
        }
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
        // Only show logo if the actual image file exists — no placeholder square
        $logo = __DIR__ . '/logo.png';
        $hasLogo = file_exists($logo);

        $leftX = $hasLogo ? 42 : 16;
        $orgW  = $hasLogo ? 100 : 120;

        // Logo removed as requested (font/layout only).


        // Organization name & subtitle (left side)
        // (Removed logo sizing influence by forcing smaller typography.)
        // ===== Compact thermal header (fits 58mm width) =====
        // Font sizes reduced for POS readability.
        $pdf->SetTextColor(20, 20, 20);

        // Left org block
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->SetXY(3, 6);
        $pdf->Cell(40, 5, self::clean($receipt['organization']), 0, 1, 'L');

        $pdf->SetFont('Courier', '', 8);
        $pdf->SetX(3);
        $pdf->Cell(40, 4, 'Seminar Management System', 0, 1, 'L');
        $pdf->SetX(3);
        $pdf->Cell(40, 4, 'Official payment receipt', 0, 1, 'L');

        // Right title/receipt no (right aligned within 58mm)
        $rightX = 55;
        $pdf->SetFont('Courier', 'B', 10);
        $pdf->SetXY($rightX - 23, 6);
        $pdf->Cell(23, 5, 'RECEIPT', 0, 1, 'R');

        $pdf->SetFont('Courier', 'B', 9);
        $pdf->SetTextColor(80, 0, 120);
        $pdf->SetX($rightX - 23);
        $pdf->Cell(23, 5, self::clean($receipt['receipt_no']), 0, 1, 'R');
        $pdf->SetTextColor(20, 20, 20);

        // VOID/CANCELLED (kept, but scaled down and centered)
        if (($receipt['receipt_status'] ?? '') !== 'active') {
            $pdf->SetTextColor(190, 30, 45);
            $pdf->SetFont('Courier', 'B', 18);
            $pdf->SetXY(18, 44);
            $pdf->Cell(22, 7, strtoupper($receipt['receipt_status']), 0, 1, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        // Divider line under header
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(2, 46, 56, 46);

    }

    private static function receiptBody(FPDF $pdf, array $receipt) {
        // Page margins
        $leftX  = 16;
        $labelW = 45;  // width of the label column
        $valueX = $leftX + $labelW;
        $valueW = 178 - $labelW; // remaining width

        $pdf->SetXY($leftX, 52);

        // Participant info rows
        $fields = [
            'Participant'   => $receipt['participant_name'],
            'Email'         => $receipt['participant_email'],
            'Seminar'       => $receipt['seminar_title'],
            'Seminar Date'  => date('F d, Y', strtotime($receipt['seminar_date'])),
            'Venue'         => $receipt['venue'],
        ];

        foreach ($fields as $label => $value) {
            self::labelValue($pdf, $leftX, $labelW, $valueW, $label, (string)$value);
        }

        // Table
        $pdf->Ln(8);
        $colDesc = 88;
        $colRef  = 50;
        $colAmt  = 40;

        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(20, 20, 20);

        // Table header
        $pdf->SetFont('Times', 'B', 9);

        $pdf->SetX($leftX);
        $pdf->Cell($colDesc, 9, 'Description', 1, 0, 'L', true);
        $pdf->Cell($colRef,  9, 'Reference',   1, 0, 'L', true);
        $pdf->Cell($colAmt,  9, 'Amount',       1, 1, 'R', true);

        // Table row
        $pdf->SetFont('Times', '', 9);

        $pdf->SetX($leftX);
        $pdf->Cell($colDesc, 10, 'Seminar payment',                   1, 0, 'L');
        $pdf->Cell($colRef,  10, self::clean($receipt['billing_no']), 1, 0, 'L');
        $pdf->Cell($colAmt,  10, self::money($receipt['amount_paid']),1, 1, 'R');

        // Total row
        $pdf->SetFont('Times', 'B', 9);

        $pdf->SetX($leftX);
        $pdf->Cell($colDesc + $colRef, 10, 'Total Paid', 1, 0, 'R');
        $pdf->Cell($colAmt,            10, self::money($receipt['amount_paid']), 1, 1, 'R');

        // Payment details
        $pdf->Ln(8);
        $pdf->SetTextColor(20, 20, 20);

        $details = [
            'Payment Method'   => self::methodLabel($receipt['payment_method']),
            'Reference Number' => $receipt['reference_number'] ?: 'N/A',
            'Date Paid'        => date('F d, Y h:i A', strtotime($receipt['payment_date'])),
            'Processed By'     => $receipt['received_by_name'] ?: $receipt['issued_by_name'],
            'Issued At'        => date('F d, Y h:i A', strtotime($receipt['issued_at'])),
            'Remaining Balance'=> self::money($receipt['balance']),
        ];

        foreach ($details as $label => $value) {
            self::labelValue($pdf, $leftX, $labelW, $valueW, $label, (string)$value);
        }
        // No signature block
    }

    private static function footer(FPDF $pdf, array $receipt) {
        $pdf->SetY(266);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(16, 262, 194, 262);
        $pdf->SetFont('Times', '', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s') . '  |  Receipt ID: ' . $receipt['id'], 0, 1, 'C');
        $pdf->Cell(0, 5, 'This receipt is system-generated. Keep this copy for your records.', 0, 1, 'C');
    }

    /**
     * Renders a label-value row with proper left-alignment and manual word wrap.
     */
    private static function labelValue(FPDF $pdf, $leftX, $labelW, $valueW, $label, $value) {
        $value = self::clean($value);
        $cellH = 6;

        $pdf->SetX($leftX);
        $pdf->SetFont('Times', 'B', 9);

        $pdf->Cell($labelW, $cellH, self::clean($label) . ':', 0, 0, 'L');

        $pdf->SetFont('Times', '', 9);

        $lines = self::wrapText($pdf, $value, $valueW);

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $pdf->Cell($valueW, $cellH, $line, 0, 1, 'L');
            } else {
                $pdf->SetX($leftX + $labelW);
                $pdf->Cell($valueW, $cellH, $line, 0, 1, 'L');
            }
        }

        if (empty($lines)) {
            $pdf->Ln($cellH);
        }
    }

    /**
     * Word-wraps text to fit within $maxWidth using GetStringWidth.
     */
    private static function wrapText(FPDF $pdf, $text, $maxWidth) {
        if (trim($text) === '') {
            return [''];
        }

        $words   = explode(' ', $text);
        $lines   = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if ($pdf->GetStringWidth($test) <= $maxWidth) {
                $current = $test;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    private static function money($amount) {
        return 'PHP ' . number_format((float)$amount, 2);
    }

    private static function methodLabel($method) {
        $labels = [
            'cash'          => 'Cash',
            'gcash'         => 'GCash',
            'bank_transfer' => 'Bank Transfer',
            'online'        => 'Online',
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