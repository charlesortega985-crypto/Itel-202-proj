<?php
// student/history.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Student User';
$user_id   = $_SESSION['user_id'] ?? 0;

$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

// Fetch the student's attendance history from the database
$attendance_records = [];
if (isset($pdo) && $user_id > 0) {
    try {
        $stmt_logs = $pdo->prepare("
            SELECT a.scanned_at, a.status, s.subject_code, s.subject_title 
            FROM attendance a
            JOIN subjects s ON a.subject_id = s.subject_id
            WHERE a.student_id = ? 
            ORDER BY a.scanned_at DESC
        ");
        $stmt_logs->execute([$user_id]);
        $attendance_records = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $attendance_records = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History - Smart Attendance System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS Style -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-4">
    
    <!-- Header Banner -->
    <div class="main-header text-center mb-4">
        <h2 class="fw-bold m-0 d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-clock-history text-primary"></i> Smart Attendance System
        </h2>
        <p class="text-muted mb-0 mt-1">Review all your past attendance logs</p>
    </div>

    <!-- Navigation Bar -->
    <div class="nav-pill-custom d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 px-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php" class="nav-link-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="subjects.php" class="nav-link-item"><i class="bi bi-journal-bookmark"></i> Subjects</a>
            <a href="history.php" class="nav-link-item active"><i class="bi bi-clock-history"></i> History</a>
            <a href="profile.php" class="nav-link-item"><i class="bi bi-person-badge"></i> Profile</a>
        </div>

        <!-- Real-Time Clock Display -->
        <div class="text-center px-3 py-1 rounded shadow-sm border inner-box d-none d-lg-block">
            <span id="liveClock" class="fw-bold text-primary small">--:--:-- --</span>
            <span class="text-muted small mx-1">|</span>
            <span id="liveDate" class="text-muted small">Loading date...</span>
        </div>

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
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0"><i class="bi bi-journal-text text-primary me-2"></i>Your Attendance Records</h5>
            <a href="index.php" class="btn btn-sm btn-outline-primary fw-bold rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Date & Time Scanned</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($attendance_records) > 0): ?>
                        <?php $count = 1; foreach ($attendance_records as $log): ?>
                            <tr>
                                <td><strong><?= $count++ ?></strong></td>
                                <td>
                                    <span class="badge bg-primary px-2 py-1 me-1"><?= htmlspecialchars($log['subject_code'] ?? 'General') ?></span>
                                    <small class="fw-bold text-body"><?= htmlspecialchars($log['subject_title'] ?? '') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-dark bg-opacity-75 text-light border border-secondary font-monospace px-3 py-2 fw-normal">
                                        <i class="bi bi-calendar3 me-1 text-primary"></i><?= date('M d, Y', strtotime($log['scanned_at'])) ?>
                                        <span class="text-muted mx-1">|</span>
                                        <i class="bi bi-clock me-1 text-primary"></i><?= date('h:i:s A', strtotime($log['scanned_at'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($log['status'] === 'Present'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-circle me-1"></i> Present</span>
                                    <?php elseif ($log['status'] === 'Late'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="bi bi-clock-history me-1"></i> Late</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-x-circle me-1"></i> Absent</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                <h5 class="fw-bold">No history yet</h5>
                                <p class="mb-0">You haven't scanned your attendance for any subject.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Theme JS & Clock JS -->
<script src="../assets/js/theme.js"></script>
<script src="clock.js"></script>

</body>
</html>