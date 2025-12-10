<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

require_once 'auth_check.php';
require_once 'database.php';

header('Content-Type: application/json');

// Get action first to determine access requirements
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_role = $_SESSION['role'] ?? '';

// Actions available to faculty/fi
$faculty_actions = ['get_enrolled_students', 'remove_student', 'add_student_directly', 'get_student_courses'];

// Check authorization based on action
if (in_array($action, $faculty_actions)) {
    // Faculty/FI actions
    if (!in_array($user_role, ['faculty', 'fi'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Faculty or FI privileges required']);
        exit();
    }
    $user_id = $_SESSION['user_id'];
} else {
    // Student actions
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $user_role !== 'student') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    $student_id = $_SESSION['user_id'];
}

try {
    switch ($action) {
        case 'search_courses':
            $search = $_POST['search'] ?? '';
            
            // Search for all courses with enrollment status for current student
            $sql = "SELECT c.course_id, c.course_code, c.course_name, c.description, c.credit_hours,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                    e.status as enrollment_status
                    FROM courses c
                    JOIN attendance_users u ON c.faculty_id = u.user_id
                    LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.student_id = ?
                    WHERE (c.course_code LIKE ? OR c.course_name LIKE ? OR ? = '')
                    ORDER BY c.course_code";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $search_term = "%$search%";
            $stmt->bind_param("isss", $student_id, $search_term, $search_term, $search);
            
            if (!$stmt->execute()) {
                throw new Exception('Query execution error: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = [
                    'course_id' => $row['course_id'],
                    'course_code' => $row['course_code'],
                    'course_name' => $row['course_name'],
                    'description' => $row['description'],
                    'credit_hours' => $row['credit_hours'],
                    'instructor_name' => $row['instructor_name'],
                    'enrollment_status' => $row['enrollment_status']
                ];
            }
            
            echo json_encode([
                'success' => true, 
                'courses' => $courses,
                'count' => count($courses)
            ]);
            
            $stmt->close();
            break;
        
        case 'request_join':
            $course_id = intval($_POST['course_id'] ?? 0);
            
            if ($course_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                break;
            }
            
            // Check if course exists
            $check_course_sql = "SELECT course_id FROM courses WHERE course_id = ?";
            $check_course_stmt = $conn->prepare($check_course_sql);
            
            if (!$check_course_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $check_course_stmt->bind_param("i", $course_id);
            $check_course_stmt->execute();
            $check_course_result = $check_course_stmt->get_result();
            
            if ($check_course_result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Course not found']);
                $check_course_stmt->close();
                break;
            }
            $check_course_stmt->close();
            
            // Check if already enrolled or has pending request
            $check_sql = "SELECT status FROM enrollments WHERE student_id = ? AND course_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            
            if (!$check_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $check_stmt->bind_param("ii", $student_id, $course_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                $status = $row['status'];
                
                if ($status === 'approved') {
                    echo json_encode(['success' => false, 'message' => 'You are already enrolled in this course']);
                } else if ($status === 'pending') {
                    echo json_encode(['success' => false, 'message' => 'You already have a pending request for this course']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Your previous request was rejected. Please contact the instructor.']);
                }
                $check_stmt->close();
                break;
            }
            $check_stmt->close();
            
            // Insert new enrollment request
            $insert_sql = "INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')";
            $insert_stmt = $conn->prepare($insert_sql);
            
            if (!$insert_stmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            
            $insert_stmt->bind_param("ii", $student_id, $course_id);
            
            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Request sent successfully! Waiting for instructor approval.']);
            } else {
                throw new Exception('Failed to insert enrollment: ' . $insert_stmt->error);
            }
            
            $insert_stmt->close();
            break;
        
        case 'get_enrolled_students':
            // Faculty/FI: Get all enrolled students for a course
            $course_id = intval($_GET['course_id'] ?? 0);
            
            if ($course_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                break;
            }
            
            // Verify course belongs to this faculty/fi
            $check_sql = "SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $course_id, $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Access denied to this course']);
                break;
            }
            
            // Get enrolled students
            $sql = "SELECT e.student_id, e.approval_date as enrollment_date,
                    CONCAT(u.first_name, ' ', u.last_name) as student_name,
                    u.email
                    FROM enrollments e
                    JOIN attendance_users u ON e.student_id = u.user_id
                    WHERE e.course_id = ? AND e.status = 'approved'
                    ORDER BY u.last_name, u.first_name";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $students = [];
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            
            echo json_encode(['success' => true, 'students' => $students]);
            break;
        
        case 'remove_student':
            // Faculty/FI: Remove a student from course
            $input = json_decode(file_get_contents('php://input'), true);
            $course_id = intval($input['course_id'] ?? 0);
            $remove_student_id = intval($input['student_id'] ?? 0);
            
            if ($course_id <= 0 || $remove_student_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                break;
            }
            
            // Verify course belongs to this faculty/fi
            $check_sql = "SELECT course_id FROM courses WHERE course_id = ? AND faculty_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $course_id, $user_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Access denied to this course']);
                break;
            }
            
            // Delete enrollment
            $delete_sql = "DELETE FROM enrollments WHERE course_id = ? AND student_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $course_id, $remove_student_id);
            
            if ($delete_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Student removed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove student']);
            }
            break;
        
        case 'get_student_courses':
            // Faculty/FI: Get all courses for a specific student
            $student_id = intval($_GET['student_id'] ?? 0);
            
            if ($student_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
                break;
            }
            
            // Get courses where student is enrolled and faculty/fi has access
            $sql = "SELECT DISTINCT c.course_id, c.course_code, c.course_name
                    FROM courses c
                    JOIN enrollments e ON c.course_id = e.course_id
                    WHERE e.student_id = ? AND c.faculty_id = ? AND e.status = 'approved'
                    ORDER BY c.course_code";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $student_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
            
            echo json_encode(['success' => true, 'courses' => $courses]);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    }
} catch (Exception $e) {
    error_log('Student Course Handler Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>