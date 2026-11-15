<?php
// forgot_password.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$config_file = __DIR__ . '/config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email) && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate a unique token
                $token = bin2hex(random_bytes(50));
                $expires = date("Y-m-d H:i:s", strtotime("+1 hour")); // Expires in 1 hour

                // Save token to database
try {
    $stmt_up = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    $stmt_up->execute([$token, $expires, $email]);
} catch (Exception $e) {
    die("<div style='padding:20px; background:#fee2e2; color:#991b1b; border:1px solid #ef4444; border-radius:10px; font-family:sans-serif;'>
            <strong>Database Error (Failed to save token):</strong><br>" . $e->getMessage() . "
         </div>");
}
                // Send Email
                $mail = new PHPMailer(true);

                try {
                    // GMAIL SMTP SETTINGS
                    // ⚠️ Replace with YOUR OWN Gmail Address and App Password
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = '0325-1213@lspu.edu.ph'; // <--- YOUR GMAIL
                    $mail->Password   = 'hvnn vglb dgui jqzg';    // <--- YOUR APP PASSWORD
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // SENDER (You)
                    $mail->setFrom('your_email@gmail.com', 'Smart Attendance System');
                    
                    // RECEIVER (The user who entered their email)
                    $mail->addAddress($email); 

                    $reset_link = "http://localhost/attendance_system/reset_password.php?token=" . $token;

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body    = "Hello,<br><br>We received a request to reset your password for the Smart Attendance System.<br><br>
                                      Click the link below to reset it:<br><br>
                                      <a href='$reset_link'>$reset_link</a><br><br>
                                      This link will expire in 1 hour.<br><br>
                                      If you did not request this, please ignore this email.";

                    $mail->send();
                    
                    // Success Message
                    $msg = "<div class='alert alert-success py-2 rounded-3 mb-3'><small><i class='bi bi-check-circle-fill me-1'></i> A password reset link has been sent to <strong>" . htmlspecialchars($email) . "</strong>.</small></div>";
                } catch (Exception $e) {
                    $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Message could not be sent. Mailer Error: {$mail->ErrorInfo}</small></div>";
                }
            } else {
                $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Email address not found in our system.</small></div>";
            }
        } catch (Exception $e) {
            $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Database Error: " . $e->getMessage() . "</small></div>";
        }
    } else {
        $msg = "<div class='alert alert-warning py-2 rounded-3 mb-3'><small>Please enter your email address.</small></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center p-3" style="min-height: 100vh;">

<div class="login-card w-100" style="max-width: 420px; border-radius: 20px;">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-body m-0 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-key-fill text-primary"></i> Forgot Password
        </h3>
        <p class="text-muted small mt-1">Enter your registered email to reset password</p>
    </div>

    <?= $msg ?>

    <form method="POST">
        <div class="mb-4">
            <label class="form-label fw-bold small text-body">Email Address</label>
            <div class="input-group">
                <span class="input-group-text inner-box border-end-0 text-body"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control border-start-0 inner-box text-body" required placeholder="name@example.com">
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-pill shadow-sm" style="background-color: #6366f1; border: none;">
            <i class="bi bi-send me-1"></i> Send Reset Link
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top">
        <small class="text-muted">Remembered it? <a href="login.php" class="fw-bold text-primary text-decoration-none">Back to Login</a></small>
    </div>
</div>

</body>
</html>