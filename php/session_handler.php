<?php
/**
 * Session Management Handler
 * Handles creation, listing, and management of class sessions
 */

require_once __DIR__ . '/session_config.php';
require_once 'database.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

// Check if user is faculty or FI
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['faculty', 'fi'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'create':
            createSession($conn, $user_id);
            break;
        
        case 'list':
            listSessions($conn, $user_id);
            break;
        
        case 'get':
            getSession($conn, $user_id);
            break;
        
        case 'update_status':
            updateSessionStatus($conn, $user_id);
            break;
        
        case 'delete':
            deleteSession($conn, $user_id);
            break;
        
        case 'regenerate_code':
            regenerateCode($conn, $user_id);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    error_log("Session handler error: " . $e->getMessage());
}

$conn->close();

// Generate random attendance code
function generateAttendanceCode($length = 6) {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Avoid confusing characters
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Create new session
function createSession($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $course_id = $input['course_id'] ?? null;
    $session_date = $input['session_date'] ?? null;
    $session_time = $input['session_time'] ?? null;
    $session_title = trim($input['session_title'] ?? '');
    $session_description = trim($input['session_description'] ?? '');
    $location = trim($input['location'] ?? '');
    $code_duration = intval($input['code_duration'] ?? 30); // minutes
    
    // Validation
    if (!$course_id || !$session_date || !$session_time || !$session_title) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Verify user has access to this course
    $check_sql = "SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $course_id, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to create sessions for this course']);
        return;
    }
    
    // Generate unique attendance code
    do {
        $attendance_code = generateAttendanceCode();
        $code_check = $conn->prepare("SELECT session_id FROM sessions WHERE attendance_code = ?");
        $code_check->bind_param("s", $attendance_code);
        $code_check->execute();
    } while ($code_check->get_result()->num_rows > 0);
    
    // Calculate code expiry
    $code_expiry = date('Y-m-d H:i:s', strtotime("$session_date $session_time +$code_duration minutes"));
    
    // Insert session
    $sql = "INSERT INTO sessions (course_id, session_date, session_time, session_title, session_description, 
            location, attendance_code, code_expiry, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssi", $course_id, $session_date, $session_time, $session_title, 
                      $session_description, $location, $attendance_code, $code_expiry, $user_id);
    
    if ($stmt->execute()) {
        $session_id = $conn->insert_id;
        
        // Don't auto-create attendance records - students will mark their own attendance
        // Records will only be created when students mark attendance or when session closes
        
        echo json_encode([
            'success' => true,
            'message' => 'Session created successfully',
            'session_id' => $session_id,
            'attendance_code' => $attendance_code
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create session']);
    }
}

// List sessions for faculty's courses
function listSessions($conn, $user_id) {
    $course_id = $_GET['course_id'] ?? null;
    
    if ($course_id) {
        // List sessions for specific course
        $sql = "SELECT s.*, c.course_code, c.course_name,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.student_id END) as present_count
                FROM sessions s
                JOIN courses c ON s.course_id = c.course_id
                LEFT JOIN attendance_records ar ON s.session_id = ar.session_id
                WHERE s.course_id = ? AND c.faculty_id = ?
                GROUP BY s.session_id
                ORDER BY s.session_date DESC, s.session_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $course_id, $user_id);
    } else {
        // List all sessions for faculty
        $sql = "SELECT s.*, c.course_code, c.course_name,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.student_id END) as present_count
                FROM sessions s
                JOIN courses c ON s.course_id = c.course_id
                LEFT JOIN attendance_records ar ON s.session_id = ar.session_id
                WHERE c.faculty_id = ?
                GROUP BY s.session_id
                ORDER BY s.session_date DESC, s.session_time DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $sessions = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

// Get single session details
function getSession($conn, $user_id) {
    $session_id = $_GET['session_id'] ?? null;
    
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    $sql = "SELECT s.*, c.course_code, c.course_name
            FROM sessions s
            JOIN courses c ON s.course_id = c.course_id
            WHERE s.session_id = ? AND c.faculty_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        echo json_encode(['success' => true, 'session' => $session]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
    }
}

// Update session status
function updateSessionStatus($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $input['session_id'] ?? null;
    $status = $input['status'] ?? null;
    
    if (!$session_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    if (!in_array($status, ['scheduled', 'active', 'closed'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    // Verify ownership
    $sql = "UPDATE sessions s
            JOIN courses c ON s.course_id = c.course_id
            SET s.status = ?
            WHERE s.session_id = ? AND c.faculty_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $status, $session_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // If closing session, mark all students who haven't marked attendance as absent
        if ($status === 'closed') {
            $absent_sql = "INSERT INTO attendance_records (session_id, student_id, status, marked_by)
                          SELECT s.session_id, e.student_id, 'absent', 'system'
                          FROM sessions s
                          JOIN courses c ON s.course_id = c.course_id
                          JOIN enrollments e ON c.course_id = e.course_id
                          WHERE s.session_id = ? 
                          AND e.status = 'approved'
                          AND NOT EXISTS (
                              SELECT 1 FROM attendance_records ar 
                              WHERE ar.session_id = s.session_id 
                              AND ar.student_id = e.student_id
                          )";
            $absent_stmt = $conn->prepare($absent_sql);
            $absent_stmt->bind_param("i", $session_id);
            $absent_stmt->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Session status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update session or unauthorized']);
    }
}

// Delete session
function deleteSession($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $input['session_id'] ?? null;
    
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    // Verify ownership and delete
    $sql = "DELETE s FROM sessions s
            JOIN courses c ON s.course_id = c.course_id
            WHERE s.session_id = ? AND c.faculty_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $session_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Session deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete session or unauthorized']);
    }
}

// Regenerate attendance code
function regenerateCode($conn, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $input['session_id'] ?? null;
    $code_duration = intval($input['code_duration'] ?? 30);
    
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'Session ID required']);
        return;
    }
    
    // Generate new unique code
    do {
        $attendance_code = generateAttendanceCode();
        $code_check = $conn->prepare("SELECT session_id FROM sessions WHERE attendance_code = ?");
        $code_check->bind_param("s", $attendance_code);
        $code_check->execute();
    } while ($code_check->get_result()->num_rows > 0);
    
    // Update code and expiry
    $code_expiry = date('Y-m-d H:i:s', strtotime("+$code_duration minutes"));
    $sql = "UPDATE sessions s
            JOIN courses c ON s.course_id = c.course_id
            SET s.attendance_code = ?, s.code_expiry = ?
            WHERE s.session_id = ? AND c.faculty_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $attendance_code, $code_expiry, $session_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Attendance code regenerated',
            'attendance_code' => $attendance_code,
            'code_expiry' => $code_expiry
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to regenerate code or unauthorized']);
    }
}
?>
