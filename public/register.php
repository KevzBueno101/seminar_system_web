<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$seminar = null;
$error = '';
$success = '';

// Get seminar by token
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $db->prepare("
            SELECT s.*, COUNT(p.id) as participant_count 
            FROM seminars s 
            LEFT JOIN participants p ON s.id = p.seminar_id 
            WHERE s.unique_token = :token AND s.status = 'open'
        ");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $seminar = $stmt->fetch();
        
        if (!$seminar) {
            $error = 'Invalid registration link or seminar is not available for registration.';
        }
    } catch(PDOException $exception) {
        $error = 'Database error. Please try again later.';
    }
} else {
    $error = 'Invalid registration link. Please contact the seminar organizer.';
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $seminar) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($name) || empty($email)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($seminar['participant_count'] >= $seminar['max_slots']) {
        $error = 'Registration closed - All slots are full';
    } else {
        try {
            // Check if already registered
            $stmt = $db->prepare("SELECT id FROM participants WHERE seminar_id = :seminar_id AND email = :email");
            $stmt->bindParam(':seminar_id', $seminar['id']);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'You have already registered for this seminar';
            } else {
                // Register participant
                $stmt = $db->prepare("
                    INSERT INTO participants (seminar_id, name, email) 
                    VALUES (:seminar_id, :name, :email)
                ");
                $stmt->bindParam(':seminar_id', $seminar['id']);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                
                if ($stmt->execute()) {
                    $success = 'Registration successful! You will receive a confirmation email with seminar details.';
                    
                    // Clear form
                    $name = $email = '';
                    
                    // Refresh seminar data to get updated participant count
                    $stmt = $db->prepare("
                        SELECT s.*, COUNT(p.id) as participant_count 
                        FROM seminars s 
                        LEFT JOIN participants p ON s.id = p.seminar_id 
                        WHERE s.id = :id
                    ");
                    $stmt->bindParam(':id', $seminar['id']);
                    $stmt->execute();
                    $seminar = $stmt->fetch();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch(PDOException $exception) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seminar Registration - <?php echo $seminar ? htmlspecialchars($seminar['title']) : 'Registration'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .registration-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: flex;
            min-height: 600px;
        }
        .seminar-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .registration-form {
            flex: 1;
            padding: 50px 40px;
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
        .seminar-header {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        .seminar-detail {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .seminar-detail i {
            width: 30px;
            text-align: center;
            margin-right: 15px;
        }
        .slots-info {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.3);
        }
        .progress-bar {
            background: #28a745;
            border-radius: 4px;
        }
        .badge {
            font-size: 12px;
            padding: 6px 12px;
        }
        @media (max-width: 768px) {
            .registration-container {
                flex-direction: column;
            }
            .seminar-info {
                padding: 40px 20px;
            }
            .registration-form {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <?php if (!$seminar): ?>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="text-center bg-white p-5 rounded-3 shadow">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>Registration Not Available</h4>
                        <p class="text-muted"><?php echo $error ?: 'This seminar is not available for registration.'; ?></p>
                        <a href="#" onclick="window.history.back();" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-1"></i>Go Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="registration-container">
            <div class="seminar-info">
                <div class="seminar-header">
                    <h3 class="mb-3"><i class="fas fa-graduation-cap me-2"></i>Seminar Registration</h3>
                    <h4><?php echo htmlspecialchars($seminar['title']); ?></h4>
                    <small><?php echo htmlspecialchars($seminar['organization']); ?></small>
                </div>
                
                <div class="seminar-details">
                    <div class="seminar-detail">
                        <i class="fas fa-calendar"></i>
                        <div>
                            <strong>Date</strong><br>
                            <?php echo date('F d, Y', strtotime($seminar['date'])); ?>
                        </div>
                    </div>
                    
                    <div class="seminar-detail">
                        <i class="fas fa-clock"></i>
                        <div>
                            <strong>Time</strong><br>
                            <?php echo date('h:i A', strtotime($seminar['time'])); ?>
                        </div>
                    </div>
                    
                    <div class="seminar-detail">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <strong>Venue</strong><br>
                            <?php echo htmlspecialchars($seminar['venue']); ?>
                        </div>
                    </div>
                    
                    <div class="seminar-detail">
                        <i class="fas fa-microphone"></i>
                        <div>
                            <strong>Speaker</strong><br>
                            <?php echo htmlspecialchars($seminar['speaker']); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($seminar['description'])): ?>
                    <div class="mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>About this Seminar</h6>
                        <p><?php echo nl2br(htmlspecialchars($seminar['description'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="slots-info">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><i class="fas fa-users me-2"></i>Available Slots</span>
                        <span class="badge bg-light text-dark">
                            <?php echo $seminar['participant_count']; ?> / <?php echo $seminar['max_slots']; ?>
                        </span>
                    </div>
                    <div class="progress">
                        <?php 
                            $percentage = ($seminar['participant_count'] / $seminar['max_slots']) * 100;
                            $progress_class = $percentage >= 80 ? 'bg-danger' : ($percentage >= 60 ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="progress-bar <?php echo $progress_class; ?>" 
                             style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <small class="mt-2 d-block">
                        <?php 
                            $remaining = $seminar['max_slots'] - $seminar['participant_count'];
                            if ($remaining > 0) {
                                echo $remaining . ' slots remaining';
                            } else {
                                echo 'All slots are full';
                            }
                        ?>
                    </small>
                </div>
            </div>
            
            <div class="registration-form">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Register Now</h2>
                    <p class="text-muted">Fill in your details to participate</p>
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

                <?php if ($seminar['participant_count'] < $seminar['max_slots']): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-user me-2"></i>Full Name *
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email Address *
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   required>
                            <small class="text-muted">We'll send seminar details to this email</small>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="confirm" required>
                            <label class="form-check-label" for="confirm">
                                I confirm that I will attend this seminar
                            </label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check-circle me-2"></i>Complete Registration
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-danger mb-3"></i>
                        <h4>Registration Closed</h4>
                        <p class="text-muted">All slots for this seminar have been filled.</p>
                        <p class="text-muted">Please contact the organizer for more information.</p>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Your information is secure and will only be used for seminar communication
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
