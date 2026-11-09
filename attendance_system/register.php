<?php
// register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config_file = __DIR__ . '/config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name'] ?? '');
    $id_number  = trim($_POST['id_number'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role       = $_POST['role'] ?? 'student';

    if (!empty($full_name) && !empty($id_number) && !empty($password)) {
        if (isset($pdo)) {
            try {
                $stmt_check = $pdo->prepare("SELECT user_id FROM users WHERE student_faculty_id = ? OR id_number = ?");
                $stmt_check->execute([$id_number, $id_number]);
                
                if ($stmt_check->rowCount() > 0) {
                    $msg = "<div class='alert alert-danger py-2'><small><i class='bi bi-exclamation-triangle-fill me-1'></i> ID Number is already registered!</small></div>";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt_ins = $pdo->prepare("
                        INSERT INTO users (student_faculty_id, id_number, full_name, email, password, role) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_ins->execute([$id_number, $id_number, $full_name, $email, $hashed_password, $role]);
                    
                    $_SESSION['msg'] = "<div class='alert alert-success py-2'><small><i class='bi bi-check-circle-fill me-1'></i> Registration successful! You can now log in.</small></div>";
                    header("Location: login.php");
                    exit;
                }
            } catch (Exception $e) {
                $msg = "<div class='alert alert-danger py-2'><small>Error: " . $e->getMessage() . "</small></div>";
            }
        } else {
            $msg = "<div class='alert alert-danger py-2'><small>Database connection not found.</small></div>";
        }
    } else {
        $msg = "<div class='alert alert-warning py-2'><small>Please fill in all required fields.</small></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Smart Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center p-3" style="min-height: 100vh;">

<div class="login-card w-100" style="max-width: 480px; border-radius: 20px;">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge bg-primary px-3 py-2 rounded-pill small">System Register</span>
        <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-sm btn-outline-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" title="Toggle Theme">
            <i id="themeIcon" class="bi bi-moon-stars-fill"></i>
        </button>
    </div>

    <div class="text-center mb-4">
        <h3 class="fw-bold text-body m-0"><i class="bi bi-person-plus-fill text-primary me-2"></i>Create Account</h3>
        <p class="text-muted small mt-1">Smart Attendance System Registration</p>
    </div>

    <?= $msg ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold small text-body">Full Name</label>
            <div class="input-group">
                <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-person"></i></span>
                <input type="text" name="full_name" class="form-control border-start-0 inner-box text-body" required placeholder="e.g., Juan dela Cruz">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold small text-body">ID Number (Student / Faculty ID)</label>
            <div class="input-group">
                <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-card-heading"></i></span>
                <input type="text" name="id_number" class="form-control border-start-0 inner-box text-body" required placeholder="e.g., 0322-0001">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold small text-body">Email Address (Optional)</label>
            <div class="input-group">
                <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control border-start-0 inner-box text-body" placeholder="name@example.com">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold small text-body">Password</label>
            <div class="input-group">
                <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control border-start-0 inner-box text-body" required placeholder="••••••••">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold small text-body">Register As</label>
            <select name="role" class="form-select inner-box text-body">
                <option value="student">Student Account</option>
                <option value="faculty">Faculty / Instructor Account</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6366f1; border: none;">
            <i class="bi bi-check-circle me-1"></i> Sign Up Now
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top">
        <small class="text-muted">Already have an account? <a href="login.php" class="fw-bold text-primary text-decoration-none">Log in here</a></small>
    </div>
</div>

<script src="assets/js/theme.js"></script>

</body>
</html>