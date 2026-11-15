<?php
// reset_password.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config_file = __DIR__ . '/config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

$msg = "";
$token_valid = false;
$token = $_GET['token'] ?? '';

// 1. Check if token exists and is valid
if (!empty($token) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE reset_token = ? AND reset_expires >= NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token_valid = true;
        } else {
            $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Invalid or expired reset link.</small></div>";
        }
    } catch (Exception $e) {
        $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Database Error: " . $e->getMessage() . "</small></div>";
    }
}

// 2. Handle New Password Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ($new_pass === $confirm_pass) {
        if (strlen($new_pass) >= 6) { 
            try {
                $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt_up = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
                $stmt_up->execute([$new_hashed, $token]);

                $_SESSION['msg'] = "<div class='alert alert-success py-2 rounded-3 mb-3'><small><i class='bi bi-check-circle-fill me-1'></i> Password reset successfully! You can now log in.</small></div>";
                header("Location: login.php");
                exit;
            } catch (Exception $e) {
                $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Error: " . $e->getMessage() . "</small></div>";
            }
        } else {
            $msg = "<div class='alert alert-warning py-2 rounded-3 mb-3'><small>Password must be at least 6 characters long.</small></div>";
        }
    } else {
        $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Passwords do not match!</small></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center p-3" style="min-height: 100vh;">

<div class="login-card w-100" style="max-width: 420px; border-radius: 20px;">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-body m-0 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-shield-lock-fill text-primary"></i> Set New Password
        </h3>
        <p class="text-muted small mt-1">Enter your new password below</p>
    </div>

    <?= $msg ?>

    <?php if ($token_valid): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-bold small text-body">New Password</label>
                <div class="input-group">
                    <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-lock"></i></span>
                    <input type="password" name="new_password" class="form-control border-start-0 inner-box text-body" required placeholder="••••••••">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-body">Confirm New Password</label>
                <div class="input-group">
                    <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="confirm_password" class="form-control border-start-0 inner-box text-body" required placeholder="••••••••">
                </div>
            </div>

            <button type="submit" name="reset_password" class="btn btn-primary w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6366f1; border: none;">
                <i class="bi bi-check-circle me-1"></i> Update Password
            </button>
        </form>
    <?php endif; ?>

    <div class="text-center mt-4 pt-3 border-top">
        <small class="text-muted">Back to <a href="login.php" class="fw-bold text-primary text-decoration-none">Login</a></small>
    </div>
</div>

</body>
</html>