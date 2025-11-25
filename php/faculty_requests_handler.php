<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'auth_check.php';
require_once 'database.php';

// Set JSON header
header('Content-Type: application/json');

// Debug: Log session info (remove after testing)
error_log('Faculty Handler - User ID: ' . ($_SESSION['user_id'] ?? 'not set'));
error_log('Faculty Handler - Role: ' . ($_SESSION['role'] ?? 'not set'));
error_log('Faculty Handler - Logged in: ' . ($_SESSION['logged_in'] ?? 'not set'));

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log('Faculty Handler: User not logged in');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if user is faculty
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    error_log('Faculty Handler: User is not faculty. Role: ' . ($_SESSION['role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Faculty privileges required.']);
    exit();
}

$action = $_POST['action'] ?? '';
$faculty_id = $_SESSION['user_id'];

error_log('Faculty Handler - Action: ' . $action);

try {
    switch ($action) {
        case 'get_pending_requests':
            $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
            
            error_log('Getting pending requests. Course ID: ' . $course_id);
            
            // Get pending requests for this faculty's courses
            if ($course_id > 0) {
                // Get requests for specific course
                $sql = "SELECT e.enrollment_id, e.student_id, e.request_date, e.course_id,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        u.email as student_email,
                        c.course_code, c.course_name
                        FROM enrollments e
                        JOIN users u ON e.student_id = u.user_id
                        JOIN courses c ON e.course_id = c.course_id
                        WHERE c.course_id = ? AND c.faculty_id = ? AND e.status = 'pending'
                        ORDER BY e.request_date DESC";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Database prepare error: ' . $conn->error);
                }
                $stmt->bind_param("ii", $course_id, $faculty_id);
            } else {
                // Get all pending requests for this faculty
                $sql = "SELECT e.enrollment_id, e.student_id, e.request_date, e.course_id,
                        CONCAT(u.first_name, ' ', u.last_name) as student_name,
                        u.email as student_email,
                        c.course_code, c.course_name
                        FROM enrollments e
                        JOIN users u ON e.student_id = u.user_id
                        JOIN courses c ON e.course_id = c.course_id
                        WHERE c.faculty_id = ? AND e.status = 'pending'
                        ORDER BY e.request_date DESC";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception('Database prepare error: ' . $conn->error);
                }
                $stmt->bind_param("i", $faculty_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Query execution error: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            $requests = [];
            while ($row = $result->fetch_assoc()) {
                $requests[] = [
                    'enrollment_id' => $row['enrollment_id'],
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'student_email' => $row['student_email'],
                    'course_code' => $row['course_code'],
                    'course_name' => $row['course_name'],
                    'request_date' => $row['request_date']
                ];
            }
            
            error_log('Found ' . count($requests) . ' pending requests');
            
            echo json_encode([
                'success' => true, 
                'requests' => $requests,
                'count' => count($requests)
            ]);
            
            $stmt->close();
            break;
        
        case 'approve_request':
            $enrollment_id = intval($_POST['enrollment_id'] ?? 0);
            
            if ($enrollment_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid enrollment ID']);
                break;
            }
            
            error_log('Approving enrollment ID: ' . $enrollment_id);
            
            // Verify this enrollment belongs to faculty's course
            $verify_sql = "SELECT e.enrollment_id, e.student_id, e.course_id, c.course_name
                          FROM enrollments e
                          JOIN courses c ON e.course_id = c.course_id
                          WHERE e.enrollment_id = ? AND c.faculty_id = ? AND e.status = 'pending'";
            $verify_stmt = $conn->prepare($verify_sql);
            
            if (!$verify_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $verify_stmt->bind_param("ii", $enrollment_id, $faculty_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                error_log('Invalid enrollment or already processed');
                echo json_encode(['success' => false, 'message' => 'Invalid request or already processed']);
                $verify_stmt->close();
                break;
            }
            
            $enrollment_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            // Update enrollment status
            $update_sql = "UPDATE enrollments SET status = 'approved', approval_date = NOW() 
                          WHERE enrollment_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if (!$update_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $update_stmt->bind_param("i", $enrollment_id);
            
            if ($update_stmt->execute()) {
                error_log('Successfully approved enrollment ID: ' . $enrollment_id);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Request approved successfully! Student enrolled in ' . $enrollment_data['course_name']
                ]);
            } else {
                throw new Exception('Failed to update enrollment: ' . $update_stmt->error);
            }
            
            $update_stmt->close();
            break;
        
        case 'reject_request':
            $enrollment_id = intval($_POST['enrollment_id'] ?? 0);
            
            if ($enrollment_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid enrollment ID']);
                break;
            }
            
            error_log('Rejecting enrollment ID: ' . $enrollment_id);
            
            // Verify this enrollment belongs to faculty's course
            $verify_sql = "SELECT e.enrollment_id FROM enrollments e
                          JOIN courses c ON e.course_id = c.course_id
                          WHERE e.enrollment_id = ? AND c.faculty_id = ? AND e.status = 'pending'";
            $verify_stmt = $conn->prepare($verify_sql);
            
            if (!$verify_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $verify_stmt->bind_param("ii", $enrollment_id, $faculty_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 0) {
                error_log('Invalid enrollment or already processed');
                echo json_encode(['success' => false, 'message' => 'Invalid request or already processed']);
                $verify_stmt->close();
                break;
            }
            $verify_stmt->close();
            
            // Update enrollment status
            $update_sql = "UPDATE enrollments SET status = 'rejected', approval_date = NOW() 
                          WHERE enrollment_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            
            if (!$update_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $update_stmt->bind_param("i", $enrollment_id);
            
            if ($update_stmt->execute()) {
                error_log('Successfully rejected enrollment ID: ' . $enrollment_id);
                echo json_encode(['success' => true, 'message' => 'Request rejected']);
            } else {
                throw new Exception('Failed to update enrollment: ' . $update_stmt->error);
            }
            
            $update_stmt->close();
            break;
        
        case 'get_enrolled_students':
            $course_id = intval($_POST['course_id'] ?? 0);
            
            if ($course_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                break;
            }
            
            // Get enrolled students for this course
            $sql = "SELECT e.enrollment_id, e.student_id, e.approval_date,
                    CONCAT(u.first_name, ' ', u.last_name) as student_name,
                    u.email as student_email
                    FROM enrollments e
                    JOIN users u ON e.student_id = u.user_id
                    JOIN courses c ON e.course_id = c.course_id
                    WHERE c.course_id = ? AND c.faculty_id = ? AND e.status = 'approved'
                    ORDER BY u.last_name, u.first_name";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $stmt->bind_param("ii", $course_id, $faculty_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $students = [];
            while ($row = $result->fetch_assoc()) {
                $students[] = [
                    'enrollment_id' => $row['enrollment_id'],
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'student_email' => $row['student_email'],
                    'approval_date' => $row['approval_date']
                ];
            }
            
            echo json_encode(['success' => true, 'students' => $students]);
            $stmt->close();
            break;
        
        default:
            error_log('Invalid action: ' . $action);
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    }
} catch (Exception $e) {
    error_log('Faculty Handler Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>