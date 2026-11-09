<?php
// student/profile.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$full_name  = $_SESSION['full_name'] ?? 'Student User';
$id_number  = $_SESSION['id_number'] ?? '2026-0000';
$email      = 'student@example.com';
$created_at = date('Y-m-d H:i:s');
$profile_pic = null;
$msg = '';

$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

$user_id = $_SESSION['user_id'] ?? 0;

// Logic for Profile Picture File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && $user_id > 0) {
    $file = $_FILES['profile_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_extensions)) {
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_dir   = __DIR__ . '/../uploads/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                if (isset($pdo)) {
                    try {
                        $stmt_up = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE user_id = ?");
                        $stmt_up->execute([$new_filename, $user_id]);
                        $msg = "<div class='alert alert-success py-2 mb-3'><small><i class='bi bi-check-circle-fill me-1'></i> Profile picture updated successfully!</small></div>";
                    } catch (Exception $e) {
                        $msg = "<div class='alert alert-danger py-2 mb-3'><small>Failed to save image path to the database.</small></div>";
                    }
                }
            } else {
                $msg = "<div class='alert alert-danger py-2 mb-3'><small>Image file upload failed.</small></div>";
            }
        } else {
            $msg = "<div class='alert alert-warning py-2 mb-3'><small>Allowed file formats are JPG, JPEG, PNG, or WEBP only.</small></div>";
        }
    }
}

// Fetch latest student details
if (isset($pdo) && $user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT student_faculty_id, full_name, email, created_at, profile_pic FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $full_name   = $user_data['full_name'];
            $id_number   = $user_data['student_faculty_id'];
            $email       = $user_data['email'] ?: 'student@example.com';
            $created_at  = $user_data['created_at'];
            $profile_pic = $user_data['profile_pic'];
        }
    } catch (Exception $e) {
        // Safe fallback
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Smart Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --header-bg: rgba(255, 255, 255, 0.95);
            --card-bg: #ffffff;
            --text-main: #212529;
            --inner-bg: #f8fafc;
            --border-color: #e2e8f0;
        }

        [data-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --header-bg: rgba(30, 41, 59, 0.95);
            --card-bg: #1e293b;
            --text-main: #ffffff;
            --inner-bg: #0f172a;
            --border-color: #334155;
        }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 40px;
            color: var(--text-main);
            transition: background 0.3s ease;
        }

        /* FORCE ALL TEXT TO PURE WHITE IN DARK MODE */
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, 
        [data-theme="dark"] h4, [data-theme="dark"] h5, [data-theme="dark"] h6,
        [data-theme="dark"] p, [data-theme="dark"] span, [data-theme="dark"] small,
        [data-theme="dark"] strong, [data-theme="dark"] b, [data-theme="dark"] label,
        [data-theme="dark"] .text-dark, [data-theme="dark"] .text-muted, [data-theme="dark"] .text-secondary {
            color: #ffffff !important;
        }

        .main-header { background: var(--header-bg); border-radius: 20px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .nav-pill-custom { background: var(--header-bg); border-radius: 15px; padding: 8px; }
        .nav-link-item { border-radius: 10px; padding: 10px 20px; font-weight: 600; color: #555; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        [data-theme="dark"] .nav-link-item { color: #e2e8f0 !important; }
        .nav-link-item.active { background: #6366f1; color: white !important; }
        .content-card { background: var(--card-bg); border-radius: 20px; padding: 40px 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .inner-box { background: var(--inner-bg) !important; border: 1px solid var(--border-color) !important; }
        
        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px auto;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #6366f1;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }
        .profile-avatar-fallback {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }
        .upload-btn-badge {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #6366f1;
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: transform 0.2s;
        }
        .upload-btn-badge:hover { transform: scale(1.1); }
    </style>
</head>
<body>

<div class="container py-4">
    
    <!-- Header -->
    <div class="main-header text-center mb-4">
        <h2 class="fw-bold m-0 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-mortarboard-fill text-primary"></i> Smart Attendance System
        </h2>
        <p class="text-muted mb-0 mt-1">Student Account Profile & Details</p>
    </div>

    <!-- Navigation Bar (Added Real-Time Clock Display) -->
    <div class="nav-pill-custom d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 px-3">
        <div class="d-flex gap-2">
            <a href="index.php" class="nav-link-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="subjects.php" class="nav-link-item"><i class="bi bi-journal-bookmark"></i> Subjects</a>
            <a href="profile.php" class="nav-link-item active"><i class="bi bi-person-badge"></i> Profile</a>
        </div>

        <!-- Real-Time Clock Element -->
        <div class="text-center px-3 py-1 bg-white rounded shadow-sm border inner-box d-none d-lg-block">
            <span id="liveClock" class="fw-bold text-primary small">--:--:-- --</span>
            <span class="text-muted small mx-1">|</span>
            <span id="liveDate" class="text-muted small">Loading date...</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-sm btn-outline-primary rounded-pill fw-bold">
                <i id="themeIcon" class="bi bi-moon-stars-fill me-1"></i> <span id="themeText">Dark Mode</span>
            </button>
            <span class="fw-bold me-2 ms-2 text-secondary d-none d-md-inline"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($full_name) ?></span>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <!-- Content Card -->
    <div class="content-card">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                
                <?= $msg ?>

                <!-- Profile Picture Display & Upload Button -->
                <div class="profile-avatar-container">
                    <?php if ($profile_pic && file_exists(__DIR__ . '/../uploads/' . $profile_pic)): ?>
                        <img src="../uploads/<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-img">
                    <?php else: ?>
                        <div class="profile-avatar-fallback">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    <?php endif; ?>

                    <label for="profile_image_input" class="upload-btn-badge" title="Upload Profile Picture">
                        <i class="bi bi-camera-fill"></i>
                    </label>
                </div>

                <!-- Hidden Form for Image Upload -->
                <form id="upload_form" method="POST" enctype="multipart/form-data" class="mb-4">
                    <input type="file" name="profile_image" id="profile_image_input" class="d-none" accept="image/*" onchange="document.getElementById('upload_form').submit();">
                    <small class="text-muted d-block">Click the camera icon to upload a new profile picture</small>
                </form>

                <h3 class="fw-bold mb-1"><?= htmlspecialchars($full_name) ?></h3>
                <span class="badge bg-primary px-3 py-2 rounded-pill mb-4">
                    <i class="bi bi-mortarboard me-1"></i> Student Account
                </span>

                <!-- Info Grid -->
                <div class="row g-3 text-start">
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box">
                            <small class="text-muted d-block font-weight-bold text-uppercase">Student ID Number</small>
                            <strong class="fs-6"><i class="bi bi-card-heading text-primary me-2"></i><?= htmlspecialchars($id_number) ?></strong>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box">
                            <small class="text-muted d-block font-weight-bold text-uppercase">Email Address</small>
                            <strong class="fs-6"><i class="bi bi-envelope text-primary me-2"></i><?= htmlspecialchars($email) ?></strong>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box">
                            <small class="text-muted d-block font-weight-bold text-uppercase">User Role</small>
                            <strong class="fs-6"><i class="bi bi-shield-check text-primary me-2"></i>Student</strong>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 rounded-4 inner-box">
                            <small class="text-muted d-block font-weight-bold text-uppercase">Registration Date</small>
                            <strong class="fs-6"><i class="bi bi-calendar-check text-primary me-2"></i><?= date('F d, Y', strtotime($created_at)) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <a href="index.php" class="btn btn-primary fw-bold px-4 py-2 rounded-pill" style="background-color: #6366f1; border: none;">
                        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Tawagin ang hiwalay na clock.js file para gumana ang orasan at dark mode -->
<script src="clock.js"></script>

</body>
</html>