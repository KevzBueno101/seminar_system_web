<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Database connection
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            try {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM admins WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    $error = 'Email already exists';
                } else {
                    // Insert new admin
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO admins (name, email, password) VALUES (:name, :email, :password)");
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password', $hashed_password);

                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now login.';
                        // Clear form
                        $name = $email = '';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
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
    <title>Admin Registration - Seminar Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            display: flex;
            min-height: 600px;
        }
        .register-form {
            flex: 1;
            padding: 50px 40px;
        }
        .register-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        @media (max-width: 768px) {
            .register-container {
                flex-direction: column;
            }
            .register-info {
                padding: 40px 20px;
            }
            .register-form {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-form">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">Create Account</h2>
                <p class="text-muted">Join our seminar management platform</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">
                        <i class="fas fa-user me-2"></i>Full Name
                    </label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                           required>
                </div>

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
                    <input type="password" class="form-control" id="password" name="password" 
                           minlength="6" required>
                    <div class="password-strength" id="passwordStrength"></div>
                    <small class="text-muted">Minimum 6 characters</small>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Confirm Password
                    </label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           minlength="6" required>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the terms and conditions
                    </label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Already have an account? <a href="login.php" class="text-primary">Sign in here</a>
                </small>
            </div>
        </div>

        <div class="register-info">
            <div>
                <h3 class="mb-4"><i class="fas fa-rocket me-3"></i>Get Started Today</h3>
                <p class="mb-4">Create your admin account and start managing seminars with our comprehensive platform.</p>
                
                <div class="mb-3">
                    <h6><i class="fas fa-star me-2"></i>Benefits:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check me-2"></i>Easy seminar creation</li>
                        <li class="mb-2"><i class="fas fa-check me-2"></i>Automatic certificate generation</li>
                        <li class="mb-2"><i class="fas fa-check me-2"></i>Email notifications</li>
                        <li class="mb-2"><i class="fas fa-check me-2"></i>Participant management</li>
                        <li class="mb-2"><i class="fas fa-check me-2"></i>Slot management</li>
                    </ul>
                </div>

                <div class="mt-4">
                    <h6><i class="fas fa-shield-alt me-2"></i>Secure & Reliable</h6>
                    <p class="small">Your data is protected with industry-standard security measures.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            const colors = ['#dc3545', '#ffc107', '#28a745', '#007bff'];
            const widths = ['25%', '50%', '75%', '100%'];
            
            strengthBar.style.width = widths[strength - 1] || '0';
            strengthBar.style.backgroundColor = colors[strength - 1] || '#ddd';
        });

        // Password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
