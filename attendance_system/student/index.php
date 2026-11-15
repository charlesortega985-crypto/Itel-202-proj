<?php
// student/index.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Student User';
$user_id   = $_SESSION['user_id'] ?? 0;

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

// 1. Kunin ang ID Number at Profile Pic ng Student
$id_number   = $_SESSION['id_number'] ?? '2026-0000';
$profile_pic = null;

if (isset($pdo) && $user_id > 0) {
    try {
        $stmt_user = $pdo->prepare("SELECT profile_pic, COALESCE(student_faculty_id, id_number, user_id) AS student_code FROM users WHERE user_id = ?");
        $stmt_user->execute([$user_id]);
        $user_row = $stmt_user->fetch(PDO::FETCH_ASSOC);
        if ($user_row) {
            $profile_pic = $user_row['profile_pic'] ?? null;
            $id_number   = $user_row['student_code'] ?? $id_number;
        }
    } catch (Exception $e) {
        $profile_pic = null;
    }
}

// 2. Fetch Enrolled Subjects
$enrolled_subjects = [];
if (isset($pdo) && $user_id > 0) {
    try {
        $stmt_sub = $pdo->prepare("
            SELECT s.subject_id, s.subject_code, s.subject_title, s.class_time, s.class_end_time, u.full_name AS prof_name 
            FROM student_subjects ss
            JOIN subjects s ON ss.subject_id = s.subject_id
            JOIN users u ON s.faculty_id = u.user_id
            WHERE ss.student_id = ?
            ORDER BY s.subject_code ASC
        ");
        $stmt_sub->execute([$user_id]);
        $enrolled_subjects = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $enrolled_subjects = [];
    }
}

// 3. Fetch TOTAL SCANS COUNT (Optimized for Stat Card)
$total_scans = 0;
if (isset($pdo) && $user_id > 0) {
    try {
        $stmt_logs = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
        $stmt_logs->execute([$user_id]);
        $total_scans = $stmt_logs->fetchColumn();
    } catch (Exception $e) {
        $total_scans = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Smart Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- QRCode Generator Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- Custom CSS Style -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-4">
    
    <!-- Header Banner -->
    <div class="main-header text-center mb-4">
        <h2 class="fw-bold m-0 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-mortarboard-fill text-primary"></i> Smart Attendance System
        </h2>
        <p class="text-muted mb-0 mt-1">Student Portal & QR Attendance Badge</p>
    </div>

    <!-- Navigation Bar -->
    <div class="nav-pill-custom d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 px-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php" class="nav-link-item active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="subjects.php" class="nav-link-item"><i class="bi bi-journal-bookmark"></i> Subjects</a>
            <a href="history.php" class="nav-link-item"><i class="bi bi-clock-history"></i> History</a>
            <a href="profile.php" class="nav-link-item"><i class="bi bi-person-badge"></i> Profile</a>
        </div>

        <!-- Real-Time Clock Display -->
        <div class="text-center px-3 py-1 rounded shadow-sm border inner-box d-none d-lg-block">
            <span id="liveClock" class="fw-bold text-primary small">--:--:-- --</span>
            <span class="text-muted small mx-1">|</span>
            <span id="liveDate" class="text-muted small">Loading date...</span>
        </div>

        <!-- Theme Toggle & Profile Info -->
        <div class="d-flex align-items-center gap-2">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-sm btn-outline-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;" title="Toggle Theme">
                <i id="themeIcon" class="bi bi-moon-stars-fill"></i>
            </button>
            <span class="fw-bold me-2 ms-2 text-secondary d-none d-md-inline"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($full_name) ?></span>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content Panel -->
    <div class="content-card">
        
        <?= $msg ?>

        <!-- Welcome Banner / Quick Stats -->
        <div class="p-4 rounded-4 inner-box mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($full_name) ?>!</h4>
                <p class="text-muted small mb-0">Check your subjects, review attendance logs, and keep your QR badge ready for class.</p>
            </div>
            <div>
                <span class="badge bg-primary px-4 py-2 rounded-pill fs-6">
                    <i class="bi bi-person-badge me-1"></i> ID: <?= htmlspecialchars($id_number) ?>
                </span>
            </div>
        </div>

        <!-- Stat Cards Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="fw-bold mb-0"><?= count($enrolled_subjects) ?></h2>
                        <small class="font-weight-bold">Enrolled Subjects</small>
                    </div>
                    <i class="bi bi-journal-bookmark fs-1 opacity-75"></i>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="fw-bold mb-0"><?= $total_scans ?></h2>
                        <small class="font-weight-bold">Total Scans Recorded</small>
                    </div>
                    <i class="bi bi-qr-code-scan fs-1 opacity-75"></i>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="fw-bold mb-0"><?= htmlspecialchars($id_number) ?></h2>
                        <small class="font-weight-bold">Student ID</small>
                    </div>
                    <i class="bi bi-card-heading fs-1 opacity-75"></i>
                </div>
            </div>
        </div>

        <!-- MAIN LAYOUT -->
        <div class="row g-4 mb-4">
            
            <!-- LEFT COLUMN: Profile & QR Badge Card -->
            <div class="col-lg-5">
                <div class="p-4 rounded-4 inner-box text-center h-100 d-flex flex-column justify-content-center">
                    
                    <div class="mb-3">
                        <?php if ($profile_pic && file_exists(__DIR__ . '/../uploads/' . $profile_pic)): ?>
                            <img src="../uploads/<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-img-header mb-2">
                        <?php else: ?>
                            <div class="profile-icon-fallback mb-2"><i class="bi bi-person-fill"></i></div>
                        <?php endif; ?>
                        <h5 class="fw-bold m-0"><?= htmlspecialchars($full_name) ?></h5>
                        <small class="text-muted fw-bold">ID: <?= htmlspecialchars($id_number) ?></small>
                    </div>

                    <hr class="my-2 opacity-25">

                    <h6 class="fw-bold text-secondary mb-1">Your Attendance QR Badge</h6>
                    <p class="text-muted small mb-2">Show this code to your instructor to scan attendance</p>

                    <div id="qrcode" class="my-2"></div>

                    <div class="mt-2">
                        <span class="badge bg-primary fs-6 px-4 py-2 rounded-pill">
                            <i class="bi bi-qr-code me-1"></i> Ready for Scanning
                        </span>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Enrolled Subjects Quick View -->
            <div class="col-lg-7">
                <div class="p-4 rounded-4 inner-box h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold m-0"><i class="bi bi-journal-check text-primary me-2"></i>My Enrolled Classes</h5>
                            <a href="subjects.php" class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3">
                                View All Subjects <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                        
                        <div class="d-flex flex-column gap-3" style="max-height: 280px; overflow-y: auto;">
                            <?php if (count($enrolled_subjects) > 0): ?>
                                <?php foreach ($enrolled_subjects as $sub): ?>
                                    <a href="subject_view.php?id=<?= $sub['subject_id'] ?>" class="text-decoration-none">
                                        <div class="p-3 rounded-3 border inner-box d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="badge bg-primary px-2 py-1"><?= htmlspecialchars($sub['subject_code']) ?></span>
                                                    <strong class="fs-6 text-body"><?= htmlspecialchars($sub['subject_title']) ?></strong>
                                                </div>
                                                <small class="text-muted fw-bold">
                                                    <i class="bi bi-person-video3 me-1 text-primary"></i>Prof. <?= htmlspecialchars($sub['prof_name']) ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="time-text-custom">
                                                    <i class="bi bi-clock text-primary"></i>
                                                    <?= !empty($sub['class_time']) ? date('h:i A', strtotime($sub['class_time'])) : 'TBA' ?>
                                                    <?php if(!empty($sub['class_end_time'])): ?>
                                                        - <?= date('h:i A', strtotime($sub['class_end_time'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary"></i>
                                    <small>No enrolled subjects yet.</small><br>
                                    <a href="subjects.php" class="btn btn-sm btn-primary mt-2 rounded-pill fw-bold px-3">Join a Class Now</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-top text-center">
                        <small class="text-muted">Want to join a new class? Go to the <a href="subjects.php" class="fw-bold text-primary">Subjects Page</a> to enter your join code.</small>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const studentIdVal = "<?= htmlspecialchars($id_number) ?>";
        const qrContainer = document.getElementById("qrcode");
        
        if (qrContainer && studentIdVal) {
            qrContainer.innerHTML = "";
            new QRCode(qrContainer, {
                text: studentIdVal,
                width: 170,
                height: 170,
                colorDark : "#4f46e5",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }
    });
</script>

<!-- Theme JS & Clock JS -->
<script src="../assets/js/theme.js"></script>
<script src="clock.js"></script>

</body>
</html>