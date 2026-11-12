<?php
// faculty/profile.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

// 1. Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
        $fileName    = $_FILES['profile_pic']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'prof_' . $user_id . '_' . time() . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/../uploads/profile_pics/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $db_pic_path = 'uploads/profile_pics/' . $newFileName;
                
                if (isset($pdo)) {
                    try {
                        $stmt_img = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
                        $stmt_img->execute([$db_pic_path, $user_id]);
                        $_SESSION['msg'] = "<div class='alert alert-success py-2 rounded-3 mb-3'><small><i class='bi bi-check-circle-fill me-1'></i> Profile picture updated successfully!</small></div>";
                    } catch (Exception $e) {
                        $_SESSION['msg'] = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Database Error: " . $e->getMessage() . "</small></div>";
                    }
                }
            }
        } else {
            $_SESSION['msg'] = "<div class='alert alert-warning py-2 rounded-3 mb-3'><small>Invalid image format. Only JPG, PNG, GIF, and WEBP allowed.</small></div>";
        }
    }
    header("Location: profile.php");
    exit;
}

// 2. Handle Change Password Process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!empty($current_pass) && !empty($new_pass) && !empty($confirm_pass)) {
        if ($new_pass !== $confirm_pass) {
            $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small><i class='bi bi-exclamation-triangle-fill me-1'></i> New passwords do not match!</small></div>";
        } else {
            if (isset($pdo)) {
                try {
                    $stmt_pass = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                    $stmt_pass->execute([$user_id]);
                    $user_db = $stmt_pass->fetch(PDO::FETCH_ASSOC);

                    if ($user_db && password_verify($current_pass, $user_db['password'])) {
                        $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                        $stmt_up_pass = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt_up_pass->execute([$new_hashed, $user_id]);
                        
                        $msg = "<div class='alert alert-success py-2 rounded-3 mb-3'><small><i class='bi bi-check-circle-fill me-1'></i> Password updated successfully!</small></div>";
                    } else {
                        $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small><i class='bi bi-x-circle-fill me-1'></i> Incorrect current password.</small></div>";
                    }
                } catch (Exception $e) {
                    $msg = "<div class='alert alert-danger py-2 rounded-3 mb-3'><small>Error: " . $e->getMessage() . "</small></div>";
                }
            }
        }
    } else {
        $msg = "<div class='alert alert-warning py-2 rounded-3 mb-3'><small>Please fill in all password fields.</small></div>";
    }
}

// Fetch Faculty Details
$user_info = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, id_number, email, role, created_at, profile_pic FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $user_info = [];
    }
}

$full_name   = $user_info['full_name'] ?? ($_SESSION['full_name'] ?? 'Faculty User');
$id_number   = $user_info['id_number'] ?? 'N/A';
$email       = $user_info['email'] ?? 'N/A';
$created_at  = !empty($user_info['created_at']) ? date('F d, Y', strtotime($user_info['created_at'])) : 'N/A';
$profile_pic = $user_info['profile_pic'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Profile - Smart Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-4">
    
    <!-- Header Banner -->
    <div class="main-header text-center mb-4">
        <h2 class="fw-bold m-0 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-mortarboard-fill text-primary"></i> Smart Attendance System
        </h2>
        <p class="text-muted mb-0 mt-1">Faculty Account Profile & Account Settings</p>
    </div>

    <!-- Navigation Bar -->
    <div class="nav-pill-custom d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 px-3">
        <div class="d-flex gap-2">
            <a href="index.php" class="nav-link-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="subjects.php" class="nav-link-item"><i class="bi bi-journal-bookmark"></i> Manage Subjects</a>
            <a href="profile.php" class="nav-link-item active"><i class="bi bi-person-badge"></i> Profile</a>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-sm btn-outline-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i id="themeIcon" class="bi bi-moon-stars-fill"></i>
            </button>
            <span class="fw-bold me-2 ms-2 text-secondary d-none d-md-inline"><i class="bi bi-person-circle"></i> Prof. <?= htmlspecialchars($full_name) ?></span>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
            <!-- Profile Content Card -->
            <div class="content-card">
                <?= $msg ?>

                <div class="text-center mb-4">
                    <!-- Profile Image Upload Box -->
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (!empty($profile_pic) && file_exists(__DIR__ . '/../' . $profile_pic)): ?>
                            <img src="../<?= htmlspecialchars($profile_pic) ?>" class="profile-img-header" alt="Profile Picture" style="width: 110px; height: 110px;">
                        <?php else: ?>
                            <div class="profile-icon-fallback d-flex align-items-center justify-content-center bg-primary text-white rounded-circle shadow-sm" style="width: 110px; height: 110px; font-size: 3.5rem;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                        <?php endif; ?>

                        <form id="picForm" method="POST" enctype="multipart/form-data" class="position-absolute bottom-0 end-0">
                            <label for="profile_pic" class="btn btn-primary btn-sm rounded-circle p-2 shadow-sm cursor-pointer d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border: 2px solid #fff;" title="Upload Photo">
                                <i class="bi bi-camera-fill" style="font-size: 0.85rem;"></i>
                            </label>
                            <input type="file" id="profile_pic" name="profile_pic" class="d-none" accept="image/*" onchange="document.getElementById('picForm').submit();">
                        </form>
                    </div>

                    <h3 class="fw-bold text-body mb-1">Prof. <?= htmlspecialchars($full_name) ?></h3>
                    <span class="badge bg-primary px-3 py-2 rounded-pill small"><i class="bi bi-award-fill me-1"></i> Faculty Instructor</span>
                </div>

                <!-- Info Cards Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box h-100">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem;">FACULTY ID NUMBER</small>
                            <div class="fw-bold text-body mt-1 d-flex align-items-center gap-2">
                                <i class="bi bi-card-heading text-primary"></i> <?= htmlspecialchars($id_number) ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box h-100">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem;">EMAIL ADDRESS</small>
                            <div class="fw-bold text-body mt-1 d-flex align-items-center gap-2">
                                <i class="bi bi-envelope-fill text-primary"></i> <?= htmlspecialchars($email) ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box h-100">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem;">USER ROLE</small>
                            <div class="fw-bold text-body mt-1 d-flex align-items-center gap-2">
                                <i class="bi bi-person-badge-fill text-primary"></i> Faculty Account
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box h-100">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem;">ACCOUNT CREATED</small>
                            <div class="fw-bold text-body mt-1 d-flex align-items-center gap-2">
                                <i class="bi bi-calendar-check-fill text-primary"></i> <?= htmlspecialchars($created_at) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password Form Section -->
                <div class="p-4 rounded-4 inner-box border mb-4">
                    <h5 class="fw-bold text-body mb-3"><i class="bi bi-shield-lock-fill text-primary me-2"></i>Change Security Password</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-body">Current Password</label>
                            <input type="password" name="current_password" class="form-control inner-box text-body" required placeholder="••••••••">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-body">New Password</label>
                                <input type="password" name="new_password" class="form-control inner-box text-body" required placeholder="••••••••">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-body">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control inner-box text-body" required placeholder="••••••••">
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background-color: #6366f1; border: none;">
                            <i class="bi bi-key-fill me-1"></i> Update Password
                        </button>
                    </form>
                </div>

                <div class="text-center pt-2">
                    <a href="index.php" class="btn btn-outline-primary px-4 py-2 rounded-pill fw-bold">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="../assets/js/theme.js"></script>

</body>
</html>