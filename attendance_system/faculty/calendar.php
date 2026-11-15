<?php
// faculty/calendar.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Faculty User';
$user_id   = $_SESSION['user_id'] ?? 0;

$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

// 1. Fetch all subjects handled by this faculty
$subjects = [];
if (isset($pdo) && $user_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT subject_id, subject_code, subject_title 
            FROM subjects 
            WHERE faculty_id = ?
            ORDER BY subject_code ASC
        ");
        $stmt->execute([$user_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $subjects = [];
    }
}

// 2. Get selected subject (default to first subject if available)
$selected_subject_id = $_GET['subject_id'] ?? ($subjects[0]['subject_id'] ?? 0);

// 3. Fetch enrolled students if a subject is selected
$students = [];
if ($selected_subject_id > 0 && isset($pdo)) {
    try {
        $stmt_students = $pdo->prepare("
            SELECT u.user_id, u.full_name 
            FROM student_subjects ss
            JOIN users u ON ss.student_id = u.user_id
            WHERE ss.subject_id = ?
            ORDER BY u.full_name ASC
        ");
        $stmt_students->execute([$selected_subject_id]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $students = [];
    }
}

// 4. Get selected student (default to first student in the list)
$selected_student_id = $_GET['student_id'] ?? ($students[0]['user_id'] ?? 0);

// 5. Determine Month and Year for Calendar (default to current month)
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// 6. Fetch attendance records for selected student, subject, and month
$attendance_records = [];
$total_present = 0;
$total_late = 0;
$total_absent = 0;

if ($selected_student_id > 0 && $selected_subject_id > 0 && isset($pdo)) {
    try {
        $stmt_att = $pdo->prepare("
            SELECT DAY(scanned_at) as day, status 
            FROM attendance 
            WHERE student_id = ? AND subject_id = ? 
              AND MONTH(scanned_at) = ? AND YEAR(scanned_at) = ?
        ");
        $stmt_att->execute([$selected_student_id, $selected_subject_id, $month, $year]);
        $records = $stmt_att->fetchAll(PDO::FETCH_ASSOC);
        
        // Map records by day and count the totals
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
    <title>Student Attendance Calendar - Faculty Portal</title>
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
            <i class="bi bi-calendar3 text-primary"></i> Student Attendance Calendar
        </h2>
        <p class="text-muted mb-0 mt-1">Visualize attendance records by subject and student</p>
    </div>

    <!-- Navigation Bar -->
    <div class="nav-pill-custom d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 px-3">
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php" class="nav-link-item"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="subjects.php" class="nav-link-item"><i class="bi bi-journal-bookmark"></i> Manage Subjects</a>
            <a href="calendar.php" class="nav-link-item active"><i class="bi bi-calendar3"></i> Calendar</a>
            <a href="profile.php" class="nav-link-item"><i class="bi bi-person-badge"></i> Profile</a>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button id="themeToggleBtn" onclick="toggleTheme()" class="btn btn-sm btn-outline-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i id="themeIcon" class="bi bi-moon-stars-fill"></i>
            </button>
            <span class="fw-bold me-2 ms-2 text-secondary d-none d-md-inline"><i class="bi bi-person-circle"></i> Prof. <?= htmlspecialchars($full_name) ?></span>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    <!-- Content Panel -->
    <div class="content-card">
        
        <!-- Filters Section -->
        <div class="row g-3 mb-4 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold small text-body">Select Subject</label>
                <form method="GET" id="filterForm">
                    <select name="subject_id" class="form-select inner-box text-body" onchange="this.form.submit()">
                        <?php if (count($subjects) > 0): ?>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= $sub['subject_id'] ?>" <?= $selected_subject_id == $sub['subject_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sub['subject_code'] . ' - ' . $sub['subject_title']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0">No subjects created yet</option>
                        <?php endif; ?>
                    </select>
                    <!-- Retain month/year and student selection -->
                    <input type="hidden" name="student_id" value="<?= $selected_student_id ?>">
                    <input type="hidden" name="month" value="<?= $month ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">
                </form>
            </div>
            
            <div class="col-md-7">
                <label class="form-label fw-bold small text-body">Select Student</label>
                <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                    <form method="GET" id="studentFilterForm" class="flex-grow-1" style="max-width: 400px;">
                        <select name="student_id" class="form-select inner-box text-body" onchange="this.form.submit()">
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $stud): ?>
                                    <option value="<?= $stud['user_id'] ?>" <?= $selected_student_id == $stud['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($stud['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="0">No students enrolled</option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                        <input type="hidden" name="month" value="<?= $month ?>">
                        <input type="hidden" name="year" value="<?= $year ?>">
                    </form>

                    <!-- ATTENDANCE COUNTERS WITH LIGHTER BACKGROUNDS -->
                    <div class="d-flex gap-2 mt-2 mt-md-0">
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
            </div>
        </div>

        <!-- Calendar Header -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-5">
            <h4 class="fw-bold m-0 text-body"><?= $month_name ?></h4>
            <div class="d-flex gap-2">
                <a href="?subject_id=<?= $selected_subject_id ?>&student_id=<?= $selected_student_id ?>&month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                    <i class="bi bi-chevron-left"></i> Prev
                </a>
                <a href="?subject_id=<?= $selected_subject_id ?>&student_id=<?= $selected_student_id ?>&month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                    Today
                </a>
                <a href="?subject_id=<?= $selected_subject_id ?>&student_id=<?= $selected_student_id ?>&month=<?= $next_month ?>&year=<?= $next_year ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
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

    </div>
</div>

<script src="../assets/js/theme.js"></script>

</body>
</html>