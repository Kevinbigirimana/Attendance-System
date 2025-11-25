<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

require_once 'auth_check.php';
require_once 'database.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$student_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'search_courses':
            $search = $_POST['search'] ?? '';
            
            // Search for all courses with enrollment status for current student
            $sql = "SELECT c.course_id, c.course_code, c.course_name, c.description, c.credit_hours,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                    e.status as enrollment_status
                    FROM courses c
                    JOIN users u ON c.faculty_id = u.user_id
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