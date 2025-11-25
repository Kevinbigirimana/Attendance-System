<?php
/**
 * Edit Course Script
 * Updated to match database structure
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
$course_id = intval($_POST['course_id']);
$course_code = !empty($_POST['course_code']) ? trim($_POST['course_code']) : NULL;
$course_name = !empty($_POST['course_name']) ? trim($_POST['course_name']) : NULL;
$description = !empty($_POST['description']) ? trim($_POST['description']) : NULL;
$credit_hours = !empty($_POST['credit_hours']) ? intval($_POST['credit_hours']) : NULL;

// Validate input
if (empty($course_name) || $course_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Course name and valid ID are required']);
    exit();
}

// Prepare SQL statement
$sql = "UPDATE courses SET course_code = ?, course_name = ?, description = ?, credit_hours = ? WHERE course_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("sssii", $course_code, $course_name, $description, $credit_hours, $course_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
    } else {
        // Check if duplicate course code
        if ($conn->errno === 1062) {
            echo json_encode(['success' => false, 'message' => 'Course code already exists']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update course: ' . $stmt->error]);
        }
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>