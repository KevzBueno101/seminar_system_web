<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation
    if (empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        // Database connection
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            try {
                $stmt = $db->prepare("SELECT id, name, email, password FROM admins WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $admin = $stmt->fetch();
                    
                    if (password_verify($password, $admin['password'])) {
                        // Login successful
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_email'] = $admin['email'];
                        
                        header('Location: ../admin/dashboard.php');
                        exit();
                    } else {
                        $error = 'Invalid password';
                    }
                } else {
                    $error = 'Email not found';
                }
            } catch(PDOException $exception) {
                $error = 'Database error: ' . $exception->getMessage();
            }
        } else {
            $error = 'Database connection failed';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            min-height: 500px;
        }
        .login-form {
            flex: 1;
            padding: 60px 40px;
        }
        .login-info {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            color: white;
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-control:focus {
            border-color: #0b7285;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0b7285 0%, #2a9d8f 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            .login-info {
                padding: 40px 20px;
            }
            .login-form {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">Welcome Back!</h2>
                <p class="text-muted">Sign in to manage your seminars</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email Address
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Don't have an account? <a href="register.php" class="text-primary">Register here</a>
                </small>
            </div>
        </div>

        <div class="login-info">
            <div>
                <h3 class="mb-4"><i class="fas fa-graduation-cap me-3"></i>Seminar Management System</h3>
                <p class="mb-4">Complete solution for managing seminars, training sessions, and certificate generation.</p>
                
                <div class="mb-3">
                    <h6><i class="fas fa-check-circle me-2"></i>Features:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-arrow-right me-2"></i>Create & Manage Seminars</li>
                        <li class="mb-2"><i class="fas fa-arrow-right me-2"></i>Participant Registration</li>
                        <li class="mb-2"><i class="fas fa-arrow-right me-2"></i>Certificate Generation</li>
                        <li class="mb-2"><i class="fas fa-arrow-right me-2"></i>Email Notifications</li>
                    </ul>
                </div>

                <div class="mt-4">
                    <small><i class="fas fa-info-circle me-2"></i>Default Admin: admin@seminar.com / admin123</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
