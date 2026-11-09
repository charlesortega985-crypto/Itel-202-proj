<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config_file = __DIR__ . '/config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!empty($id_number) && !empty($password)) {
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE student_faculty_id = ?");
                $stmt->execute([$id_number]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['id_number'] = $user['student_faculty_id'];
                    $_SESSION['role']      = $user['role'];

                    if ($user['role'] === 'faculty') {
                        header("Location: faculty/index.php");
                    } else {
                        header("Location: student/index.php");
                    }
                    exit;
                } else {
                    $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small><i class='bi bi-exclamation-octagon-fill me-1'></i> Invalid ID Number or Password!</small></div>";
                }
            } catch (Exception $e) {
                $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Database Error: " . $e->getMessage() . "</small></div>";
            }
        } else {
            $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Database connection not available.</small></div>";
        }
    } else {
        $msg = "<div class='alert alert-warning py-2 rounded-3 mb-3'><small>Please enter both ID Number and Password.</small></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Nakakonekta sa iyong style.css -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="content-card position-relative shadow-lg border-0">
        
        <!-- Icon-only Dark Mode Toggle Button -->
        <div style="position: absolute; top: 25px; right: 25px;">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-sm btn-outline-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" title="Toggle Theme">
                <i id="themeIcon" class="bi bi-moon-stars-fill"></i>
            </button>
        </div>

        <div class="text-center mb-4 pt-2">
            <h3 class="fw-bold m-0"><i class="bi bi-mortarboard-fill text-primary"></i> Smart Attendance</h3>
            <p class="text-muted small mt-1">Sign in to your portal account</p>
        </div>

        <?= $msg ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold small">ID Number (Student / Faculty ID)</label>
                <div class="input-group">
                    <span class="input-group-text border-end-0"><i class="bi bi-card-heading"></i></span>
                    <input type="text" name="id_number" class="form-control border-start-0 inner-box" required placeholder="e.g., 2026-0001">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small">Password</label>
                <div class="input-group">
                    <span class="input-group-text border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 inner-box" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #1d4ed8; border: none;">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
            <small class="text-muted">Don't have an account yet? <a href="register.php" class="fw-bold text-primary text-decoration-none">Sign up here</a></small>
        </div>
    </div>
</div>

<!-- Theme JS para sa Dark/Light mode persistence -->
<script src="assets/js/theme.js"></script>

</body>
</html>