<?php
/**
 * Attendance Handler
 * Handles marking attendance and generating reports
 */

require_once __DIR__ . '/session_config.php';
require_once 'database.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    switch ($action) {
        case 'mark_by_code':
            // Student marking attendance with code
            if ($role !== 'student') {
                echo json_encode(['success' => false, 'message' => 'Only students can use attendance codes']);
                exit();
            }
            markAttendanceByCode($conn, $user_id);
            break;
        
        case 'mark_manual':
            // Faculty/FI manually marking attendance
            if (!in_array($role, ['faculty', 'fi'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            markAttendanceManual($conn, $user_id);
            break;
        
        case 'get_session_attendance':
            // Get attendance list for a session
            if (!in_array($role, ['faculty', 'fi'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            getSessionAttendance($conn, $user_id);
            break;
        
        case 'student_report':
            // Student's own attendance report
            if ($role !== 'student') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            getStudentAttendanceReport($conn, $user_id);
            break;
        
        case 'course_report':
            // Faculty/FI course attendance report
            if (!in_array($role, ['faculty', 'fi'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            getCourseAttendanceReport($conn, $user_id);
            break;
        
        case 'student_past_schedule':
            // Get past schedule for a specific student (Faculty/FI view)
            if (!in_array($role, ['faculty', 'fi'])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            getStudentPastSchedule($conn, $user_id);
            break;
        
        case 'cleanup_closed_sessions':
            // Cleanup closed sessions (automatic maintenance)
            cleanupClosedSessions($conn);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    error_log("Attendance handler error: " . $e->getMessage());
}

$conn->close();

// Student marks attendance using code
function markAttendanceByCode($conn, $student_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $attendance_code = strtoupper(trim($input['attendance_code'] ?? ''));
    
    if (empty($attendance_code)) {
        echo json_encode(['success' => false, 'message' => 'Attendance code is required']);
        return;
    }
    
    // Find active session with this code
    $sql = "SELECT s.session_id, s.course_id, s.session_title, s.location, c.course_name, s.code_expiry
            FROM sessions s
            JOIN courses c ON s.course_id = c.course_id
            WHERE s.attendance_code = ? AND s.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $attendance_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired attendance code']);
        return;
    }
    
    $session = $result->fetch_assoc();
    
    // Check if code has expired
    if (strtotime($session['code_expiry']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Attendance code has expired']);
        return;
    }
    
    // Check if student is enrolled in this course
    $enroll_check = "SELECT enrollment_id FROM enrollments 
                     WHERE course_id = ? AND student_id = ? AND status = 'approved'";
    $enroll_stmt = $conn->prepare($enroll_check);
    $enroll_stmt->bind_param("ii", $session['course_id'], $student_id);
    $enroll_stmt->execute();
    
    if ($enroll_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this course']);
        return;
    }
    
    // Mark attendance - use INSERT ... ON DUPLICATE KEY UPDATE to handle both cases
    $mark_sql = "INSERT INTO attendance_records (session_id, student_id, status, marked_at, marked_by)
                 VALUES (?, ?, 'present', NOW(), 'student')
                 ON DUPLICATE KEY UPDATE 
                 status = 'present', marked_at = NOW(), marked_by = 'student'";
    $mark_stmt = $conn->prepare($mark_sql);
    $mark_stmt->bind_param("ii", $session['session_id'], $student_id);
    
    if ($mark_stmt->execute()) {
        // Cleanup closed sessions to save memory
        cleanupClosedSessions($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked successfully',
            'session_title' => $session['session_title'],
            'course_name' => $session['course_name'],
            'location' => $session['location']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark attendance. Please try again.']);
    }
}

// Faculty/FI manually marks attendance
function markAttendanceManual($conn, $faculty_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $input['session_id'] ?? null;
    $student_id = $input['student_id'] ?? null;
    $status = $input['status'] ?? 'present';
    $notes = trim($input['notes'] ?? '');
    
    if (!$session_id || !$student_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    // Verify faculty owns this session's course
    $check_sql = "SELECT s.session_id FROM sessions s
                  JOIN courses c ON s.course_id = c.course_id
                  WHERE s.session_id = ? AND c.faculty_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $session_id, $faculty_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access to this session']);
        return;
    }
    
    // Update or insert attendance record
    $sql = "INSERT INTO attendance_records (session_id, student_id, status, marked_at, marked_by, notes)
            VALUES (?, ?, ?, NOW(), 'faculty', ?)
            ON DUPLICATE KEY UPDATE 
            status = VALUES(status), 
            marked_at = NOW(), 
            marked_by = 'faculty',
            notes = VALUES(notes)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $session_id, $student_id, $status, $notes);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
    }
}

// Get attendance list for a session
function getSessionAttendance($conn, $faculty_id) {
    $session_id = $_GET['session_id'] ?? null;
    
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    // Verify ownership
    $check_sql = "SELECT s.session_id FROM sessions s
                  JOIN courses c ON s.course_id = c.course_id
                  WHERE s.session_id = ? AND c.faculty_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $session_id, $faculty_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    // Get all enrolled students with their attendance status (if any)
    $sql = "SELECT 
            e.student_id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            u.email as student_email,
            COALESCE(ar.status, NULL) as status,
            ar.marked_at,
            ar.marked_by,
            ar.notes,
            CASE WHEN ar.attendance_id IS NULL THEN 0 ELSE 1 END as has_record
            FROM sessions s
            JOIN courses c ON s.course_id = c.course_id
            JOIN enrollments e ON c.course_id = e.course_id
            JOIN attendance_users u ON e.student_id = u.user_id
            LEFT JOIN attendance_records ar ON ar.session_id = s.session_id AND ar.student_id = e.student_id
            WHERE s.session_id = ? AND e.status = 'approved'
            ORDER BY u.last_name, u.first_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'attendance' => $records]);
}

// Student's attendance report for their courses
function getStudentAttendanceReport($conn, $student_id) {
    $course_id = $_GET['course_id'] ?? null;
    
    if ($course_id) {
        // Detailed report for one course
        $sql = "SELECT s.session_date, s.session_time, s.session_title, 
                ar.status, ar.marked_at, ar.notes,
                c.course_code, c.course_name
                FROM attendance_records ar
                JOIN sessions s ON ar.session_id = s.session_id
                JOIN courses c ON s.course_id = c.course_id
                WHERE ar.student_id = ? AND s.course_id = ?
                ORDER BY s.session_date DESC, s.session_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $course_id);
        $stmt->execute();
        $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate statistics
        $stats_sql = "SELECT 
                      COUNT(*) as total_sessions,
                      SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                      SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                      SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                      SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_count
                      FROM attendance_records ar
                      JOIN sessions s ON ar.session_id = s.session_id
                      WHERE ar.student_id = ? AND s.course_id = ?";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param("ii", $student_id, $course_id);
        $stats_stmt->execute();
        $stats = $stats_stmt->get_result()->fetch_assoc();
        
        $stats['attendance_percentage'] = $stats['total_sessions'] > 0 
            ? round(($stats['present_count'] / $stats['total_sessions']) * 100, 2) 
            : 0;
        
        echo json_encode([
            'success' => true,
            'sessions' => $sessions,
            'statistics' => $stats
        ]);
    } else {
        // Summary for all courses
        $sql = "SELECT c.course_id, c.course_code, c.course_name,
                COUNT(DISTINCT s.session_id) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                LEFT JOIN sessions s ON c.course_id = s.course_id
                LEFT JOIN attendance_records ar ON s.session_id = ar.session_id AND ar.student_id = e.student_id
                WHERE e.student_id = ? AND e.status = 'approved'
                GROUP BY c.course_id
                ORDER BY c.course_code";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate percentages
        foreach ($courses as &$course) {
            $course['attendance_percentage'] = $course['total_sessions'] > 0
                ? round(($course['present_count'] / $course['total_sessions']) * 100, 2)
                : 0;
        }
        
        echo json_encode(['success' => true, 'courses' => $courses]);
    }
}

// Course attendance report for faculty
function getCourseAttendanceReport($conn, $faculty_id) {
    $course_id = $_GET['course_id'] ?? null;
    $session_id = $_GET['session_id'] ?? null;
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    // Verify ownership
    $check_sql = "SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $course_id, $faculty_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    if ($session_id) {
        // Single session report (already handled by getSessionAttendance)
        getSessionAttendance($conn, $faculty_id);
    } else {
        // Overall course report - all students
        $sql = "SELECT 
                u.user_id, 
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.email,
                COUNT(DISTINCT s.session_id) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_count
                FROM enrollments e
                JOIN attendance_users u ON e.student_id = u.user_id
                LEFT JOIN sessions s ON e.course_id = s.course_id
                LEFT JOIN attendance_records ar ON s.session_id = ar.session_id AND ar.student_id = e.student_id
                WHERE e.course_id = ? AND e.status = 'approved'
                GROUP BY u.user_id
                ORDER BY u.last_name, u.first_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate percentages
        foreach ($students as &$student) {
            $student['attendance_percentage'] = $student['total_sessions'] > 0
                ? round(($student['present_count'] / $student['total_sessions']) * 100, 2)
                : 0;
        }
        
        echo json_encode(['success' => true, 'students' => $students]);
    }
}

// Get past schedule for a specific student (Faculty/FI view)
function getStudentPastSchedule($conn, $faculty_id) {
    $student_id = $_GET['student_id'] ?? null;
    $course_id = $_GET['course_id'] ?? null;
    
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID required']);
        return;
    }
    
    // Verify faculty/FI has access to this student's courses
    $access_check = "SELECT DISTINCT e.course_id 
                     FROM enrollments e 
                     JOIN courses c ON e.course_id = c.course_id 
                     WHERE e.student_id = ? AND c.faculty_id = ? AND e.status = 'approved'";
    $stmt = $conn->prepare($access_check);
    $stmt->bind_param("ii", $student_id, $faculty_id);
    $stmt->execute();
    $accessible_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($accessible_courses)) {
        echo json_encode(['success' => false, 'message' => 'No access to this student']);
        return;
    }
    
    // Build query based on course filter
    if ($course_id) {
        $sql = "SELECT s.session_id, s.session_date, s.session_time, s.session_title, 
                s.location, ar.status as attendance_status, ar.marked_at,
                c.course_id, c.course_code, c.course_name
                FROM sessions s
                JOIN courses c ON s.course_id = c.course_id
                JOIN attendance_records ar ON s.session_id = ar.session_id
                WHERE ar.student_id = ? AND c.course_id = ? AND c.faculty_id = ?
                AND ar.status IS NOT NULL
                ORDER BY s.session_date DESC, s.session_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $student_id, $course_id, $faculty_id);
    } else {
        $sql = "SELECT s.session_id, s.session_date, s.session_time, s.session_title, 
                s.location, ar.status as attendance_status, ar.marked_at,
                c.course_id, c.course_code, c.course_name
                FROM sessions s
                JOIN courses c ON s.course_id = c.course_id
                JOIN attendance_records ar ON s.session_id = ar.session_id
                WHERE ar.student_id = ? AND c.faculty_id = ?
                AND ar.status IS NOT NULL
                ORDER BY s.session_date DESC, s.session_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $faculty_id);
    }
    
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    $stats = [
        'total' => count($sessions),
        'present' => 0,
        'late' => 0,
        'absent' => 0,
        'excused' => 0
    ];
    
    foreach ($sessions as $session) {
        if (isset($stats[$session['attendance_status']])) {
            $stats[$session['attendance_status']]++;
        }
    }
    
    $stats['percentage'] = $stats['total'] > 0 
        ? round(($stats['present'] / $stats['total']) * 100, 2) 
        : 0;
    
    echo json_encode([
        'success' => true, 
        'sessions' => $sessions,
        'stats' => $stats
    ]);
}

// Cleanup closed sessions to manage memory
function cleanupClosedSessions($conn) {
    // Delete sessions that are closed AND all students have been marked
    // Keep sessions where at least one student hasn't been marked
    $sql = "DELETE s FROM sessions s
            WHERE s.status = 'closed'
            AND NOW() > DATE_ADD(s.code_expiry, INTERVAL 7 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM attendance_records ar 
                WHERE ar.session_id = s.session_id 
                AND ar.status = 'absent'
            )
            AND (
                SELECT COUNT(*) FROM attendance_records ar2
                WHERE ar2.session_id = s.session_id
                AND ar2.status IN ('present', 'late', 'excused')
            ) >= (
                SELECT COUNT(*) FROM enrollments e
                WHERE e.course_id = s.course_id
                AND e.status = 'approved'
            ) * 0.8";
    
    // This keeps sessions for 7 days after closing and only deletes if 80%+ attendance recorded
    $conn->query($sql);
    
    return true;
}
?>
