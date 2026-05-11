<?php
/**
 * Database Configuration
 * Seminar Management System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'seminar_system';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}

// Create database if not exists
function createDatabase() {
    try {
        $conn = new PDO("mysql:host=localhost", 'root', '');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "CREATE DATABASE IF NOT EXISTS seminar_system";
        $conn->exec($sql);
        
        return $conn;
    } catch(PDOException $exception) {
        echo "Database creation error: " . $exception->getMessage();
        return false;
    }
}

// Create tables
function createTables() {
    $database = new Database();
    $db = $database->getConnection();
    
    if($db) {
        try {
            // Create admins table
            $sql_admins = "
                CREATE TABLE IF NOT EXISTS admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            // Create seminars table
            $sql_seminars = "
                CREATE TABLE IF NOT EXISTS seminars (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    date DATE NOT NULL,
                    time TIME NOT NULL,
                    venue VARCHAR(255) NOT NULL,
                    organization VARCHAR(255) NOT NULL,
                    speaker VARCHAR(255) NOT NULL,
                    max_slots INT NOT NULL DEFAULT 50,
                    unique_token VARCHAR(64) NOT NULL UNIQUE,
                    status ENUM('open', 'closed') DEFAULT 'open',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            // Create participants table
            $sql_participants = "
                CREATE TABLE IF NOT EXISTS participants (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    seminar_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (seminar_id) REFERENCES seminars(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_participant (seminar_id, email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

            $db->exec($sql_admins);
            $db->exec($sql_seminars);
            $db->exec($sql_participants);

            // Insert default admin if not exists
            $default_admin = "
                INSERT IGNORE INTO admins (name, email, password) 
                VALUES ('Admin', 'admin@seminar.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "')
            ";
            $db->exec($default_admin);

            createFinancialTables();

            return true;
        } catch(PDOException $exception) {
            echo "Table creation error: " . $exception->getMessage();
            return false;
        }
    }
    return false;
}

function createFinancialTables() {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        return false;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS financial_sequences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sequence_type VARCHAR(20) NOT NULL,
                sequence_year INT NOT NULL,
                last_number INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_sequence (sequence_type, sequence_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS billings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                billing_no VARCHAR(30) NOT NULL UNIQUE,
                registration_id INT NOT NULL,
                participant_id INT NOT NULL,
                seminar_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                balance DECIMAL(12,2) NOT NULL,
                status ENUM('pending', 'partial', 'paid', 'cancelled') NOT NULL DEFAULT 'pending',
                due_date DATE NULL,
                remarks TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_registration_billing (registration_id),
                INDEX idx_billings_status (status),
                INDEX idx_billings_participant (participant_id),
                INDEX idx_billings_seminar (seminar_id),
                CONSTRAINT fk_billings_registration FOREIGN KEY (registration_id) REFERENCES participants(id) ON DELETE RESTRICT,
                CONSTRAINT fk_billings_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE RESTRICT,
                CONSTRAINT fk_billings_seminar FOREIGN KEY (seminar_id) REFERENCES seminars(id) ON DELETE RESTRICT,
                CONSTRAINT fk_billings_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_no VARCHAR(30) NOT NULL UNIQUE,
                billing_id INT NOT NULL,
                amount_paid DECIMAL(12,2) NOT NULL,
                payment_method ENUM('cash', 'gcash', 'bank_transfer', 'online') NOT NULL,
                reference_number VARCHAR(100) NULL,
                payment_date DATETIME NOT NULL,
                received_by INT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_payments_billing (billing_id),
                INDEX idx_payments_date (payment_date),
                CONSTRAINT fk_payments_billing FOREIGN KEY (billing_id) REFERENCES billings(id) ON DELETE RESTRICT,
                CONSTRAINT fk_payments_admin FOREIGN KEY (received_by) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS receipts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                receipt_no VARCHAR(30) NOT NULL UNIQUE,
                payment_id INT NOT NULL UNIQUE,
                issued_by INT NULL,
                issued_at DATETIME NOT NULL,
                pdf_path VARCHAR(255) NULL,
                receipt_status ENUM('active', 'voided', 'replaced') NOT NULL DEFAULT 'active',
                void_reason TEXT NULL,
                voided_by INT NULL,
                voided_at DATETIME NULL,
                INDEX idx_receipts_status (receipt_status),
                CONSTRAINT fk_receipts_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
                CONSTRAINT fk_receipts_issued_by FOREIGN KEY (issued_by) REFERENCES admins(id) ON DELETE SET NULL,
                CONSTRAINT fk_receipts_voided_by FOREIGN KEY (voided_by) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                module VARCHAR(60) NOT NULL,
                record_id INT NULL,
                metadata JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_module (module),
                INDEX idx_audit_user (user_id),
                INDEX idx_audit_created (created_at),
                CONSTRAINT fk_audit_admin FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        return true;
    } catch(PDOException $exception) {
        error_log("Financial table creation error: " . $exception->getMessage());
        return false;
    }
}
?>
