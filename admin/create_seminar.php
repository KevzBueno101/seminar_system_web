<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$seminar = null;
$error = '';
$success = '';

// =========================
// HANDLE EDIT
// =========================
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {

    $edit_id = (int) $_GET['edit'];

    try {

        $stmt = $db->prepare("SELECT * FROM seminars WHERE id = :id");
        $stmt->execute([':id' => $edit_id]);

        $seminar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$seminar) {
            $error = 'Seminar not found.';
        }

    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// =========================
// HANDLE DELETE
// =========================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $delete_id = (int) $_GET['delete'];

    try {

        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM participants
            WHERE seminar_id = :id
        ");

        $stmt->execute([':id' => $delete_id]);

        $participant_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($participant_count > 0) {

            $error = 'Cannot delete seminar with registered participants.';

        } else {

            $stmt = $db->prepare("
                DELETE FROM seminars
                WHERE id = :id
            ");

            $stmt->execute([':id' => $delete_id]);

            header("Location: create_seminar.php?deleted=1");
            exit();
        }

    } catch (PDOException $e) {

        $error = 'Database error: ' . $e->getMessage();
    }
}

// =========================
// SUCCESS MESSAGE
// =========================
if (isset($_GET['deleted'])) {
    $success = 'Seminar deleted successfully.';
}

// =========================
// HANDLE FORM SUBMISSION
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $speaker = trim($_POST['speaker'] ?? '');
    $max_slots = (int) ($_POST['max_slots'] ?? 0);
    $status = $_POST['status'] ?? 'open';

    // Validation
    if (
        empty($title) ||
        empty($date) ||
        empty($time) ||
        empty($venue) ||
        empty($organization) ||
        empty($speaker)
    ) {

        $error = 'All required fields must be filled.';

    } elseif ($max_slots < 1) {

        $error = 'Maximum slots must be at least 1.';

    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {

        $error = 'Seminar date cannot be in the past.';

    } else {

        try {

            if ($seminar) {

                // UPDATE
                $stmt = $db->prepare("
                    UPDATE seminars
                    SET
                        title = :title,
                        description = :description,
                        date = :date,
                        time = :time,
                        venue = :venue,
                        organization = :organization,
                        speaker = :speaker,
                        max_slots = :max_slots,
                        status = :status
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':date' => $date,
                    ':time' => $time,
                    ':venue' => $venue,
                    ':organization' => $organization,
                    ':speaker' => $speaker,
                    ':max_slots' => $max_slots,
                    ':status' => $status,
                    ':id' => $seminar['id']
                ]);

                header("Location: create_seminar.php?edit=" . $seminar['id'] . "&updated=1");
                exit();

            } else {

                // CREATE
                $unique_token = bin2hex(random_bytes(32));

                $stmt = $db->prepare("
                    INSERT INTO seminars (
                        title,
                        description,
                        date,
                        time,
                        venue,
                        organization,
                        speaker,
                        max_slots,
                        unique_token,
                        status,
                        created_by
                    )
                    VALUES (
                        :title,
                        :description,
                        :date,
                        :time,
                        :venue,
                        :organization,
                        :speaker,
                        :max_slots,
                        :unique_token,
                        :status,
                        :created_by
                    )
                ");

                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':date' => $date,
                    ':time' => $time,
                    ':venue' => $venue,
                    ':organization' => $organization,
                    ':speaker' => $speaker,
                    ':max_slots' => $max_slots,
                    ':unique_token' => $unique_token,
                    ':status' => $status,
                    ':created_by' => $_SESSION['admin_id']
                ]);

                $new_id = $db->lastInsertId();

                header("Location: create_seminar.php?edit=" . $new_id . "&created=1");
                exit();
            }

        } catch (PDOException $e) {

            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Success messages
if (isset($_GET['created'])) {
    $success = 'Seminar created successfully!';
}

if (isset($_GET['updated'])) {
    $success = 'Seminar updated successfully!';
}

// =========================
// FETCH ALL SEMINARS
// =========================
try {

    $stmt = $db->prepare("
        SELECT
            s.*,
            COUNT(p.id) as participant_count
        FROM seminars s
        LEFT JOIN participants p
            ON s.id = p.seminar_id
        WHERE s.created_by = :admin_id
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ");

    $stmt->execute([':admin_id' => $_SESSION['admin_id']]);

    $all_seminars = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $all_seminars = [];
    $error = 'Failed to load seminars.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    <?php echo $seminar ? 'Edit' : 'Create'; ?> Seminar
</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>

body{
    background:#f8f9fa;
}

.copy-btn{
    border:none;
    background:#667eea;
    color:#fff;
    padding:8px 16px;
    border-radius:6px;
}

</style>

</head>

<body>

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2>
                <?php echo $seminar ? 'Edit' : 'Create'; ?> Seminar
            </h2>
        </div>

        <a href="dashboard.php" class="btn btn-secondary">
            Back
        </a>

    </div>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>

    <?php endif; ?>

    <!-- FORM -->

    <div class="card mb-5">
        <div class="card-body">

            <form method="POST">

                <div class="mb-3">
                    <label>Seminar Title</label>

                    <input
                        type="text"
                        name="title"
                        class="form-control"
                        required
                        value="<?php echo htmlspecialchars($seminar['title'] ?? ''); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label>Theme / Description</label>

                    <textarea
                        name="description"
                        class="form-control"
                    ><?php echo htmlspecialchars($seminar['description'] ?? ''); ?></textarea>
                </div>

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label>Date</label>

                        <input
                            type="date"
                            name="date"
                            class="form-control"
                            required
                            value="<?php echo htmlspecialchars($seminar['date'] ?? ''); ?>"
                        >

                    </div>

                    <div class="col-md-4 mb-3">

                        <label>Time</label>

                        <input
                            type="time"
                            name="time"
                            class="form-control"
                            required
                            value="<?php echo htmlspecialchars($seminar['time'] ?? ''); ?>"
                        >

                    </div>

                    <div class="col-md-4 mb-3">

                        <label>Max Slots</label>

                        <input
                            type="number"
                            name="max_slots"
                            class="form-control"
                            min="1"
                            required
                            value="<?php echo htmlspecialchars($seminar['max_slots'] ?? 50); ?>"
                        >

                    </div>

                </div>

                <div class="mb-3">

                    <label>Venue</label>

                    <input
                        type="text"
                        name="venue"
                        class="form-control"
                        required
                        value="<?php echo htmlspecialchars($seminar['venue'] ?? ''); ?>"
                    >

                </div>

                <div class="mb-3">

                    <label>Organization</label>

                    <input
                        type="text"
                        name="organization"
                        class="form-control"
                        required
                        value="<?php echo htmlspecialchars($seminar['organization'] ?? ''); ?>"
                    >

                </div>

                <div class="mb-3">

                    <label>Speaker</label>

                    <input
                        type="text"
                        name="speaker"
                        class="form-control"
                        required
                        value="<?php echo htmlspecialchars($seminar['speaker'] ?? ''); ?>"
                    >

                </div>

                <div class="mb-3">

                    <label>Status</label>

                    <select name="status" class="form-select">

                        <option value="open"
                            <?php echo (($seminar['status'] ?? '') === 'open') ? 'selected' : ''; ?>>
                            Open
                        </option>

                        <option value="closed"
                            <?php echo (($seminar['status'] ?? '') === 'closed') ? 'selected' : ''; ?>>
                            Closed
                        </option>

                    </select>

                </div>

                <button type="submit" class="btn btn-primary">
                    <?php echo $seminar ? 'Update' : 'Create'; ?> Seminar
                </button>

            </form>

        </div>
    </div>

    <!-- REGISTRATION LINK -->

    <?php if ($seminar && !empty($seminar['unique_token'])): ?>

        <div class="card mb-5">
            <div class="card-body">

                <h5>Registration Link</h5>

                <div class="input-group">

                    <input
                        type="text"
                        readonly
                        id="regLink"
                        class="form-control"
                        value="<?php echo 'http://localhost/web-comission/public/register.php?token=' . $seminar['unique_token']; ?>"
                    >

                    <button class="copy-btn" onclick="copyLink()">
                        Copy
                    </button>

                </div>

            </div>
        </div>

    <?php endif; ?>

    <!-- TABLE -->

    <div class="card">

        <div class="card-header">
            <h5>All Seminars</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered">

                    <thead>

                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Speaker</th>
                            <th>Participants</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($all_seminars as $item): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($item['title']); ?>
                            </td>

                            <td>
                                <?php echo date('M d, Y', strtotime($item['date'])); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($item['speaker']); ?>
                            </td>

                            <td>
                                <?php echo $item['participant_count']; ?> /
                                <?php echo $item['max_slots']; ?>
                            </td>

                            <td>

                                <?php if ($item['status'] === 'open'): ?>

                                    <span class="badge bg-success">
                                        Open
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Closed
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <a
                                    href="create_seminar.php?edit=<?php echo $item['id']; ?>"
                                    class="btn btn-sm btn-primary"
                                >
                                    Edit
                                </a>

                                <button
                                    class="btn btn-sm btn-danger"
                                    onclick="confirmDelete(<?php echo $item['id']; ?>)"
                                >
                                    Delete
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<script>

function confirmDelete(id){

    if(confirm('Delete this seminar?')){

        window.location.href =
            'create_seminar.php?delete=' + id;
    }
}

async function copyLink(){

    const input = document.getElementById('regLink');

    try{

        await navigator.clipboard.writeText(input.value);

        alert('Link copied!');

    }catch(err){

        alert('Failed to copy.');
    }
}

</script>

</body>
</html>