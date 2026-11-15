<?php
// faculty/auto_absent.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    return; // Do nothing if not logged in as faculty
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');

$config_file = __DIR__ . '/../config/db_connect.php';
if (file_exists($config_file)) {
    include_once $config_file;
}

if (isset($pdo)) {
    try {
        // 1. Find all subjects where class has already ended today
        $stmt_subjects = $pdo->prepare("
            SELECT subject_id, class_end_time 
            FROM subjects 
            WHERE faculty_id = ? AND class_end_time < ?
        ");
        $stmt_subjects->execute([$user_id, $current_time]);
        $ended_subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

        // 2. Loop through each ended subject
        foreach ($ended_subjects as $sub) {
            $subject_id = $sub['subject_id'];
            $class_end_time = $sub['class_end_time'];

            // 3. Find all students enrolled
            $stmt_students = $pdo->prepare("
                SELECT u.user_id 
                FROM student_subjects ss
                JOIN users u ON ss.student_id = u.user_id
                WHERE ss.subject_id = ?
            ");
            $stmt_students->execute([$subject_id]);
            $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

            // 4. Check if they have a record for today
            foreach ($students as $student) {
                $student_id = $student['user_id'];

                $stmt_check = $pdo->prepare("
                    SELECT attendance_id 
                    FROM attendance 
                    WHERE subject_id = ? AND student_id = ? AND DATE(scanned_at) = ?
                ");
                $stmt_check->execute([$subject_id, $student_id, $today]);
                $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

                // 5. If no record, insert "Absent"
                if (!$existing) {
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO attendance (subject_id, student_id, status, scanned_at) 
                        VALUES (?, ?, 'Absent', ?)
                    ");
                    // We set the time as the class end time to simulate when the scan should have been missed
                    $stmt_insert->execute([$subject_id, $student_id, $today . ' ' . $class_end_time]);
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail so it doesn't break the UI
    }
}
?>