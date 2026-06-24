<?php

class ReceiptPdf {
    // --- Page geometry constants (58mm wide thermal) ---
    // Usable width: 58mm - 2mm left margin - 2mm right margin = 54mm
    const PAGE_W    = 58;
    const MARGIN    = 2;
    const USABLE_W  = 54;   // PAGE_W - 2*MARGIN
    const LEFT_X    = 2;    // left margin
    const RIGHT_X   = 56;   // PAGE_W - MARGIN

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

        // 58mm wide, 220mm tall thermal/POS page
        $pdf = new FPDF('P', 'mm', [self::PAGE_W, 220]);
        $pdf->SetTitle('Official Receipt ' . $receipt['receipt_no']);
        $pdf->SetAuthor($receipt['organization']);

        $pdf->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
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
        $L = self::LEFT_X;
        $W = self::USABLE_W; // 54mm

        $pdf->SetTextColor(20, 20, 20);

        // --- Organization name ---
        $pdf->SetFont('Courier', 'B', 7);
        $pdf->SetXY($L, 3);
        $pdf->Cell($W, 4, self::clean($receipt['organization']), 0, 1, 'C');

        // --- Subtitle lines ---
        $pdf->SetFont('Courier', '', 5);
        $pdf->SetX($L);
        $pdf->Cell($W, 3, 'Seminar Management System', 0, 1, 'C');
        $pdf->SetX($L);
        $pdf->Cell($W, 3, 'Official Payment Receipt', 0, 1, 'C');

        // --- Divider ---
        $pdf->SetDrawColor(160, 160, 160);
        $lineY = $pdf->GetY() + 1;
        $pdf->Line($L, $lineY, self::RIGHT_X, $lineY);
        $pdf->Ln(3);

        // --- OFFICIAL RECEIPT label ---
        $pdf->SetFont('Courier', 'B', 6);
        $pdf->SetX($L);
        $pdf->Cell($W, 3, 'OFFICIAL RECEIPT', 0, 1, 'C');

        // --- Receipt No ---
        $pdf->SetFont('Courier', 'B', 7);
        $pdf->SetTextColor(80, 0, 120);
        $pdf->SetX($L);
        $pdf->Cell($W, 4, self::clean($receipt['receipt_no']), 0, 1, 'C');
        $pdf->SetTextColor(20, 20, 20);

        // --- VOID / CANCELLED stamp ---
        if (($receipt['receipt_status'] ?? '') !== 'active') {
            $pdf->SetTextColor(190, 30, 45);
            $pdf->SetFont('Courier', 'B', 12);
            $pdf->SetX($L);
            $pdf->Cell($W, 5, strtoupper($receipt['receipt_status']), 0, 1, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        // --- Bottom divider ---
        $pdf->SetDrawColor(160, 160, 160);
        $lineY2 = $pdf->GetY() + 1;
        $pdf->Line($L, $lineY2, self::RIGHT_X, $lineY2);
        $pdf->Ln(3);
    }

    private static function receiptBody(FPDF $pdf, array $receipt) {
        $L  = self::LEFT_X;
        $W  = self::USABLE_W; // 54mm

        // Label ~41%, value ~59%
        $labelW = 22;
        $valueW = $W - $labelW; // 32mm

        // --- Participant / seminar info ---
        $fields = [
            'Participant'  => $receipt['participant_name'],
            'Email'        => $receipt['participant_email'],
            'Seminar'      => $receipt['seminar_title'],
            'Seminar Date' => date('M d, Y', strtotime($receipt['seminar_date'])),
            'Venue'        => $receipt['venue'],
        ];

        foreach ($fields as $label => $value) {
            self::labelValue($pdf, $L, $labelW, $valueW, $label, (string)$value);
        }

        $pdf->Ln(2);

        // --- Payment table ---
        // 54mm usable: Desc 22 | Ref 18 | Amt 14 = 54mm
        $colDesc = 22;
        $colRef  = 18;
        $colAmt  = 14;

        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetDrawColor(160, 160, 160);
        $pdf->SetTextColor(20, 20, 20);

        // Table header
        $pdf->SetFont('Courier', 'B', 5);
        $pdf->SetX($L);
        $pdf->Cell($colDesc, 5, 'Description', 1, 0, 'L', true);
        $pdf->Cell($colRef,  5, 'Reference',   1, 0, 'L', true);
        $pdf->Cell($colAmt,  5, 'Amount',       1, 1, 'R', true);

        // Table row
        $pdf->SetFont('Courier', '', 5);
        $pdf->SetX($L);
        $pdf->Cell($colDesc, 5, 'Seminar payment',                    1, 0, 'L');
        $pdf->Cell($colRef,  5, self::clean($receipt['billing_no']),  1, 0, 'L');
        $pdf->Cell($colAmt,  5, self::money($receipt['amount_paid']), 1, 1, 'R');

        // Total row
        $pdf->SetFont('Courier', 'B', 5);
        $pdf->SetX($L);
        $pdf->Cell($colDesc + $colRef, 5, 'Total Paid', 1, 0, 'R');
        $pdf->Cell($colAmt,            5, self::money($receipt['amount_paid']), 1, 1, 'R');

        $pdf->Ln(2);

        // --- Payment details ---
        $details = [
            'Payment Method'    => self::methodLabel($receipt['payment_method']),
            'Reference Number'  => $receipt['reference_number'] ?: 'N/A',
            'Date Paid'         => date('M d, Y g:i A', strtotime($receipt['payment_date'])),
            'Processed By'      => $receipt['received_by_name'] ?: $receipt['issued_by_name'],
            'Issued At'         => date('M d, Y g:i A', strtotime($receipt['issued_at'])),
            'Balance'           => self::money($receipt['balance']),
        ];

        foreach ($details as $label => $value) {
            self::labelValue($pdf, $L, $labelW, $valueW, $label, (string)$value);
        }
    }

    private static function footer(FPDF $pdf, array $receipt) {
        $L = self::LEFT_X;
        $W = self::USABLE_W;

        $pdf->Ln(4);

        // Divider
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line($L, $pdf->GetY(), self::RIGHT_X, $pdf->GetY());
        $pdf->Ln(2);

        $pdf->SetFont('Courier', '', 5);
        $pdf->SetTextColor(120, 120, 120);

        $pdf->SetX($L);
        $pdf->Cell($W, 3, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->SetX($L);
        $pdf->Cell($W, 3, 'Receipt ID: ' . $receipt['id'], 0, 1, 'C');
        $pdf->SetX($L);
        $pdf->Cell($W, 3, 'Keep this copy for your records.', 0, 1, 'C');
    }

    /**
     * Renders a label : value row, with word-wrap on the value side.
     */
    private static function labelValue(FPDF $pdf, $leftX, $labelW, $valueW, $label, $value) {
        $value  = self::clean($value);
        $cellH  = 4;

        $pdf->SetX($leftX);
        $pdf->SetFont('Courier', 'B', 6);
        $pdf->Cell($labelW, $cellH, self::clean($label) . ':', 0, 0, 'L');

        $pdf->SetFont('Courier', '', 6);
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