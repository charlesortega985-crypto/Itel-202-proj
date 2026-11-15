<?php
// student/subject_view.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Student User';
$user_id   = $_SESSION['user_id'] ?? 0;
$subject_id = $_GET['id'] ?? 0;

$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

// Fetch Subject Details
$subject_info = null;
if (isset($pdo) && $subject_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name AS prof_name 
        FROM subjects s 
        JOIN users u ON s.faculty_id = u.user_id 
        WHERE s.subject_id = ?
    ");
    $stmt->execute([$subject_id]);
    $subject_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$subject_info) {
    header("Location: subjects.php");
    exit;
}

// Determine Month and Year for Calendar
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Fetch attendance records for this student
$attendance_records = [];
$total_present = 0;
$total_late = 0;
$total_absent = 0;

if (isset($pdo)) {
    try {
        $stmt_att = $pdo->prepare("
            SELECT DAY(scanned_at) as day, status 
            FROM attendance 
            WHERE student_id = ? AND subject_id = ? 
              AND MONTH(scanned_at) = ? AND YEAR(scanned_at) = ?
        ");
        $stmt_att->execute([$user_id, $subject_id, $month, $year]);
        $records = $stmt_att->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($records as $rec) {
            $attendance_records[$rec['day']] = $rec['status'];
            
            if ($rec['status'] === 'Present') $total_present++;
            elseif ($rec['status'] === 'Late') $total_late++;
            elseif ($rec['status'] === 'Absent') $total_absent++;
        }
    } catch (Exception $e) {
        $attendance_records = [];
    }
}

// Calendar Setup
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_month = strtotime("$year-$month-01");
$first_day_of_week = date('w', $first_day_of_month); // 0 (Sunday) to 6 (Saturday)

$month_name = date('F Y', $first_day_of_month);
$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year  = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year  = $month == 12 ? $year + 1 : $year;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - <?= htmlspecialchars($subject_info['subject_code']) ?></title>
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
            <i class="bi bi-calendar3 text-primary"></i> <?= htmlspecialchars($subject_info['subject_code']) ?> Attendance
        </h2>
        <p class="text-muted mb-0 mt-1"><?= htmlspecialchars($subject_info['subject_title']) ?> - Prof. <?= htmlspecialchars($subject_info['prof_name']) ?></p>
    </div>

    <!-- Navigation Bar -->
    <div class="nav-pill-custom d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 px-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php" class="nav-link-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="subjects.php" class="nav-link-item active"><i class="bi bi-journal-bookmark"></i> Subjects</a>
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

    <!-- Content Panel -->
    <div class="content-card">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h5 class="fw-bold m-0"><i class="bi bi-calendar-check text-primary me-2"></i>My Attendance Records</h5>
                <p class="text-muted small mb-0 mt-1">Your personal attendance for this subject.</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge badge-present px-3 py-2 rounded-3 shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= $total_present ?> Present
                </span>
                <span class="badge badge-late px-3 py-2 rounded-3 shadow-sm">
                    <i class="bi bi-clock-history me-1"></i> <?= $total_late ?> Late
                </span>
                <span class="badge badge-absent px-3 py-2 rounded-3 shadow-sm">
                    <i class="bi bi-x-circle-fill me-1"></i> <?= $total_absent ?> Absent
                </span>
            </div>
        </div>

        <!-- Calendar Header -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h4 class="fw-bold m-0 text-body"><?= $month_name ?></h4>
            <div class="d-flex gap-2">
                <a href="?id=<?= $subject_id ?>&month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    <i class="bi bi-chevron-left"></i> Prev
                </a>
                <a href="?id=<?= $subject_id ?>&month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                    Today
                </a>
                <a href="?id=<?= $subject_id ?>&month=<?= $next_month ?>&year=<?= $next_year ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    Next <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="table-responsive rounded-3 border">
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>Sun</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php
                        // Fill empty cells before the 1st day of the month
                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            echo "<td class='calendar-empty'></td>";
                        }
                        
                        $current_day = 1;
                        $cell_count = $first_day_of_week;

                        while ($current_day <= $days_in_month) {
                            if ($cell_count == 7) {
                                echo "</tr><tr>";
                                $cell_count = 0;
                            }

                            $status = $attendance_records[$current_day] ?? null;
                            $td_class = '';
                            $badge = '';
                            
                            if ($status === 'Present') {
                                $td_class = 'bg-present';
                                $badge = "<span class='badge status-badge shadow-sm'>Present</span>";
                            } elseif ($status === 'Late') {
                                $td_class = 'bg-late';
                                $badge = "<span class='badge status-badge shadow-sm'>Late</span>";
                            } elseif ($status === 'Absent') {
                                $td_class = 'bg-absent';
                                $badge = "<span class='badge status-badge shadow-sm'>Absent</span>";
                            }

                            echo "<td class='$td_class'>";
                            echo "<div class='day-number'>$current_day</div>";
                            echo $badge;
                            echo "</td>";

                            $current_day++;
                            $cell_count++;
                        }

                        // Fill empty cells after the last day
                        while ($cell_count < 7) {
                            echo "<td class='calendar-empty'></td>";
                            $cell_count++;
                        }
                        ?>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-center">
            <a href="subjects.php" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Back to My Subjects
            </a>
        </div>

    </div>
</div>

<script src="../assets/js/theme.js"></script>
<script src="clock.js"></script>

</body>
</html>