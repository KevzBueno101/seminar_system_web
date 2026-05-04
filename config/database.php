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

            return true;
        } catch(PDOException $exception) {
            echo "Table creation error: " . $exception->getMessage();
            return false;
        }
    }
    return false;
}
?>
