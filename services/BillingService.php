<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../templates/ReceiptPdf.php';

class BillingService {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function generateBilling($participantId, $amount, $dueDate, $remarks, $adminId) {
        $participantId = (int)$participantId;
        $amount = round((float)$amount, 2);

        if ($participantId <= 0) {
            throw new Exception('Invalid participant selected.');
        }
        if ($amount <= 0) {
            throw new Exception('Billing amount must be greater than zero.');
        }

        $this->db->beginTransaction();
        try {
            $participant = $this->getParticipantForUpdate($participantId);
            if (!$participant) {
                throw new Exception('Participant registration was not found.');
            }

            $existing = $this->findBillingByRegistration($participantId);
            if ($existing) {
                throw new Exception('A billing already exists for this participant registration.');
            }

            $billingNo = $this->nextNumber('BILL');
            $stmt = $this->db->prepare("
                INSERT INTO billings
                    (billing_no, registration_id, participant_id, seminar_id, amount, balance, status, due_date, remarks, created_by)
                VALUES
                    (:billing_no, :registration_id, :participant_id, :seminar_id, :amount, :balance, 'pending', :due_date, :remarks, :created_by)
            ");
            $stmt->execute([
                ':billing_no' => $billingNo,
                ':registration_id' => $participant['id'],
                ':participant_id' => $participant['id'],
                ':seminar_id' => $participant['seminar_id'],
                ':amount' => $amount,
                ':balance' => $amount,
                ':due_date' => $dueDate ?: null,
                ':remarks' => $remarks ?: null,
                ':created_by' => $adminId ?: null,
            ]);

            $billingId = (int)$this->db->lastInsertId();
            $this->audit($adminId, 'billing.generated', 'billing', $billingId, [
                'billing_no' => $billingNo,
                'participant_id' => $participant['id'],
                'seminar_id' => $participant['seminar_id'],
                'amount' => $amount,
            ]);

            $this->db->commit();
            return $this->getBilling($billingId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function recordPayment($billingId, $amountPaid, $paymentMethod, $referenceNumber, $paymentDate, $notes, $adminId) {
        $billingId = (int)$billingId;
        $amountPaid = round((float)$amountPaid, 2);
        $allowedMethods = ['cash', 'gcash', 'bank_transfer', 'online'];

        if ($billingId <= 0) {
            throw new Exception('Invalid billing selected.');
        }
        if ($amountPaid <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }
        if (!in_array($paymentMethod, $allowedMethods, true)) {
            throw new Exception('Invalid payment method.');
        }

        $this->db->beginTransaction();
        try {
            $billing = $this->getBillingForUpdate($billingId);
            if (!$billing) {
                throw new Exception('Billing record was not found.');
            }
            if ($billing['status'] === 'cancelled') {
                throw new Exception('Payments cannot be recorded against a cancelled billing.');
            }
            if ((float)$billing['balance'] <= 0) {
                throw new Exception('This billing is already fully paid.');
            }
            if ($amountPaid > (float)$billing['balance']) {
                throw new Exception('Payment exceeds the outstanding balance.');
            }

            $paymentNo = $this->nextNumber('PAY');
            $receiptNo = $this->nextNumber('OR');
            $paidAt = $paymentDate ? date('Y-m-d H:i:s', strtotime($paymentDate)) : date('Y-m-d H:i:s');

            $stmt = $this->db->prepare("
                INSERT INTO payments
                    (payment_no, billing_id, amount_paid, payment_method, reference_number, payment_date, received_by, notes)
                VALUES
                    (:payment_no, :billing_id, :amount_paid, :payment_method, :reference_number, :payment_date, :received_by, :notes)
            ");
            $stmt->execute([
                ':payment_no' => $paymentNo,
                ':billing_id' => $billingId,
                ':amount_paid' => $amountPaid,
                ':payment_method' => $paymentMethod,
                ':reference_number' => $referenceNumber ?: null,
                ':payment_date' => $paidAt,
                ':received_by' => $adminId ?: null,
                ':notes' => $notes ?: null,
            ]);
            $paymentId = (int)$this->db->lastInsertId();

            $newBalance = round((float)$billing['balance'] - $amountPaid, 2);
            if ($newBalance < 0) {
                throw new Exception('Payment would create a negative balance.');
            }
            $newStatus = $newBalance == 0.0 ? 'paid' : 'partial';

            $stmt = $this->db->prepare("UPDATE billings SET balance = :balance, status = :status WHERE id = :id");
            $stmt->execute([
                ':balance' => $newBalance,
                ':status' => $newStatus,
                ':id' => $billingId,
            ]);

            $stmt = $this->db->prepare("
                INSERT INTO receipts (receipt_no, payment_id, issued_by, issued_at, receipt_status)
                VALUES (:receipt_no, :payment_id, :issued_by, :issued_at, 'active')
            ");
            $stmt->execute([
                ':receipt_no' => $receiptNo,
                ':payment_id' => $paymentId,
                ':issued_by' => $adminId ?: null,
                ':issued_at' => date('Y-m-d H:i:s'),
            ]);
            $receiptId = (int)$this->db->lastInsertId();

            $pdfPath = ReceiptPdf::generate($this->db, $receiptId);
            $stmt = $this->db->prepare("UPDATE receipts SET pdf_path = :pdf_path WHERE id = :id");
            $stmt->execute([
                ':pdf_path' => $pdfPath,
                ':id' => $receiptId,
            ]);

            $this->audit($adminId, 'payment.recorded', 'payment', $paymentId, [
                'payment_no' => $paymentNo,
                'billing_id' => $billingId,
                'amount_paid' => $amountPaid,
                'new_balance' => $newBalance,
            ]);
            $this->audit($adminId, 'receipt.generated', 'receipt', $receiptId, [
                'receipt_no' => $receiptNo,
                'payment_id' => $paymentId,
            ]);

            $this->db->commit();
            return $this->getReceipt($receiptId);
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function voidReceipt($receiptId, $reason, $adminId) {
        $receiptId = (int)$receiptId;
        $reason = trim((string)$reason);
        if ($receiptId <= 0 || $reason === '') {
            throw new Exception('A void reason is required.');
        }

        $this->db->beginTransaction();
        try {
            $receipt = $this->getReceiptForUpdate($receiptId);
            if (!$receipt) {
                throw new Exception('Receipt was not found.');
            }
            if ($receipt['receipt_status'] !== 'active') {
                throw new Exception('Only active receipts can be voided.');
            }

            $stmt = $this->db->prepare("
                UPDATE receipts
                SET receipt_status = 'voided', void_reason = :reason, voided_by = :voided_by, voided_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':reason' => $reason,
                ':voided_by' => $adminId ?: null,
                ':id' => $receiptId,
            ]);

            $this->audit($adminId, 'receipt.voided', 'receipt', $receiptId, [
                'receipt_no' => $receipt['receipt_no'],
                'reason' => $reason,
            ]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelBilling($billingId, $adminId) {
        $billingId = (int)$billingId;
        $this->db->beginTransaction();
        try {
            $billing = $this->getBillingForUpdate($billingId);
            if (!$billing) {
                throw new Exception('Billing record was not found.');
            }
            if ((float)$billing['amount'] !== (float)$billing['balance']) {
                throw new Exception('Only unpaid billings can be cancelled.');
            }
            if ($billing['status'] === 'cancelled') {
                throw new Exception('Billing is already cancelled.');
            }

            $stmt = $this->db->prepare("UPDATE billings SET status = 'cancelled' WHERE id = :id");
            $stmt->execute([':id' => $billingId]);
            $this->audit($adminId, 'billing.cancelled', 'billing', $billingId, ['billing_no' => $billing['billing_no']]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listBillings($status = '', $seminarId = 0) {
        $sql = "
            SELECT b.*, p.name AS participant_name, p.email AS participant_email,
                   s.title AS seminar_title, s.date AS seminar_date,
                   a.name AS created_by_name,
                   COALESCE(SUM(pay.amount_paid), 0) AS total_paid
            FROM billings b
            JOIN participants p ON b.participant_id = p.id
            JOIN seminars s ON b.seminar_id = s.id
            LEFT JOIN admins a ON b.created_by = a.id
            LEFT JOIN payments pay ON pay.billing_id = b.id
            WHERE 1 = 1
        ";
        $params = [];
        if ($status !== '') {
            $sql .= " AND b.status = :status";
            $params[':status'] = $status;
        }
        if ((int)$seminarId > 0) {
            $sql .= " AND b.seminar_id = :seminar_id";
            $params[':seminar_id'] = (int)$seminarId;
        }
        $sql .= " GROUP BY b.id ORDER BY b.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listUnbilledParticipants() {
        $stmt = $this->db->prepare("
            SELECT p.*, s.title AS seminar_title, s.date AS seminar_date
            FROM participants p
            JOIN seminars s ON p.seminar_id = s.id
            LEFT JOIN billings b ON b.registration_id = p.id
            WHERE b.id IS NULL
            ORDER BY p.registered_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listSeminars() {
        $stmt = $this->db->query("SELECT id, title, date FROM seminars ORDER BY date DESC");
        return $stmt->fetchAll();
    }

    public function getBillingDetails($billingId) {
        $billing = $this->getBilling((int)$billingId);
        if (!$billing) {
            return null;
        }
        $stmt = $this->db->prepare("
            SELECT pay.*, r.id AS receipt_id, r.receipt_no, r.receipt_status, r.pdf_path,
                   a.name AS received_by_name
            FROM payments pay
            LEFT JOIN receipts r ON r.payment_id = pay.id
            LEFT JOIN admins a ON pay.received_by = a.id
            WHERE pay.billing_id = :billing_id
            ORDER BY pay.payment_date DESC, pay.id DESC
        ");
        $stmt->execute([':billing_id' => $billingId]);
        $billing['payments'] = $stmt->fetchAll();
        return $billing;
    }

    public function financialSummary() {
        $summary = [
            'total_billed' => 0,
            'total_collected' => 0,
            'outstanding' => 0,
            'today_collected' => 0,
        ];

        $stmt = $this->db->query("SELECT COALESCE(SUM(amount), 0) AS total_billed, COALESCE(SUM(balance), 0) AS outstanding FROM billings WHERE status <> 'cancelled'");
        $row = $stmt->fetch();
        $summary['total_billed'] = (float)$row['total_billed'];
        $summary['outstanding'] = (float)$row['outstanding'];

        $stmt = $this->db->query("SELECT COALESCE(SUM(amount_paid), 0) AS total_collected FROM payments");
        $summary['total_collected'] = (float)$stmt->fetch()['total_collected'];

        $stmt = $this->db->query("SELECT COALESCE(SUM(amount_paid), 0) AS today_collected FROM payments WHERE DATE(payment_date) = CURDATE()");
        $summary['today_collected'] = (float)$stmt->fetch()['today_collected'];

        return $summary;
    }

    private function nextNumber($type) {
        $year = (int)date('Y');
        $stmt = $this->db->prepare("
            INSERT INTO financial_sequences (sequence_type, sequence_year, last_number)
            VALUES (:type, :year, 0)
            ON DUPLICATE KEY UPDATE last_number = last_number
        ");
        $stmt->execute([':type' => $type, ':year' => $year]);

        $stmt = $this->db->prepare("SELECT last_number FROM financial_sequences WHERE sequence_type = :type AND sequence_year = :year FOR UPDATE");
        $stmt->execute([':type' => $type, ':year' => $year]);
        $next = ((int)$stmt->fetch()['last_number']) + 1;

        $stmt = $this->db->prepare("UPDATE financial_sequences SET last_number = :last_number WHERE sequence_type = :type AND sequence_year = :year");
        $stmt->execute([':last_number' => $next, ':type' => $type, ':year' => $year]);

        return sprintf('%s-%d-%04d', $type, $year, $next);
    }

    private function getParticipantForUpdate($participantId) {
        $stmt = $this->db->prepare("SELECT * FROM participants WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $participantId]);
        return $stmt->fetch();
    }

    private function findBillingByRegistration($registrationId) {
        $stmt = $this->db->prepare("SELECT * FROM billings WHERE registration_id = :registration_id");
        $stmt->execute([':registration_id' => $registrationId]);
        return $stmt->fetch();
    }

    private function getBillingForUpdate($billingId) {
        $stmt = $this->db->prepare("SELECT * FROM billings WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $billingId]);
        return $stmt->fetch();
    }

    private function getReceiptForUpdate($receiptId) {
        $stmt = $this->db->prepare("SELECT * FROM receipts WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $receiptId]);
        return $stmt->fetch();
    }

    private function getBilling($billingId) {
        $stmt = $this->db->prepare("
            SELECT b.*, p.name AS participant_name, p.email AS participant_email,
                   s.title AS seminar_title, s.date AS seminar_date, s.venue, s.organization,
                   a.name AS created_by_name
            FROM billings b
            JOIN participants p ON b.participant_id = p.id
            JOIN seminars s ON b.seminar_id = s.id
            LEFT JOIN admins a ON b.created_by = a.id
            WHERE b.id = :id
        ");
        $stmt->execute([':id' => $billingId]);
        return $stmt->fetch();
    }

    private function getReceipt($receiptId) {
        $stmt = $this->db->prepare("
            SELECT r.*, pay.payment_no, pay.billing_id
            FROM receipts r
            JOIN payments pay ON r.payment_id = pay.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $receiptId]);
        return $stmt->fetch();
    }

    private function audit($adminId, $action, $module, $recordId, array $metadata) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, action, module, record_id, metadata)
            VALUES (:user_id, :action, :module, :record_id, :metadata)
        ");
        $stmt->execute([
            ':user_id' => $adminId ?: null,
            ':action' => $action,
            ':module' => $module,
            ':record_id' => $recordId,
            ':metadata' => json_encode($metadata),
        ]);
    }
}
