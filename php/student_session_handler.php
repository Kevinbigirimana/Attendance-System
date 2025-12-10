<?php
/**
 * Student Session Handler
 * Allows students to view sessions for their enrolled courses
 */

require_once __DIR__ . '/session_config.php';
require_once 'database.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

// Check if user is a student
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? '';
$student_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'list':
            listStudentSessions($conn, $student_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function listStudentSessions($conn, $student_id) {
    // Get sessions for all courses the student is enrolled in
    $sql = "SELECT s.session_id, s.session_date, s.session_time, s.session_title, 
            s.session_description, s.location, s.code_expiry,
            c.course_id, c.course_code, c.course_name,
            ar.status as attendance_status,
            ar.marked_at,
            CASE 
                WHEN NOW() > s.code_expiry THEN 'closed'
                WHEN NOW() BETWEEN s.session_date AND s.code_expiry THEN 'active'
                ELSE 'scheduled'
            END as status
            FROM sessions s
            JOIN courses c ON s.course_id = c.course_id
            JOIN enrollments e ON c.course_id = e.course_id
            LEFT JOIN attendance_records ar ON s.session_id = ar.session_id AND ar.student_id = ?
            WHERE e.student_id = ? AND e.status = 'approved'
            ORDER BY s.session_date DESC, s.session_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}
