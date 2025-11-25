<?php
/**
 * Add Course Script
 * Uses user_id directly as faculty_id (no separate faculty table)
 */

session_start();

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once 'database.php';

// Get form data
$course_code = !empty($_POST['course_code']) ? trim($_POST['course_code']) : NULL;
$course_name = !empty($_POST['course_name']) ? trim($_POST['course_name']) : NULL;
$description = !empty($_POST['description']) ? trim($_POST['description']) : NULL;
$credit_hours = !empty($_POST['credit_hours']) ? intval($_POST['credit_hours']) : NULL;

// Use the logged-in admin's user_id as faculty_id
$faculty_id = $_SESSION['user_id'];

// Validate required fields
if (empty($course_name)) {
    echo json_encode(['success' => false, 'message' => 'Course name is required']);
    exit();
}

// Validate credit hours if provided
if ($credit_hours !== NULL && $credit_hours <= 0) {
    echo json_encode(['success' => false, 'message' => 'Credit hours must be a positive number']);
    exit();
}

// Prepare SQL statement - using faculty_id column
$sql = "INSERT INTO courses (course_code, course_name, description, credit_hours, faculty_id) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare failed: ' . $conn->error]);
    exit();
}

// Bind parameters
$stmt->bind_param("sssii", $course_code, $course_name, $description, $credit_hours, $faculty_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Course added successfully',
        'course_id' => $conn->insert_id,
        'faculty_id' => $faculty_id
    ]);
} else {
    // Check for specific error codes
    if ($conn->errno === 1062) {
        echo json_encode(['success' => false, 'message' => 'Course code already exists']);
    } elseif ($conn->errno === 1452) {
        echo json_encode(['success' => false, 'message' => 'Invalid faculty ID - user not found']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add course: ' . $stmt->error]);
    }
}

$stmt->close();
$conn->close();
?>