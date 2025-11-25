<?php
/**
 * Delete Course Script
 */

session_start();

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in and is faculty
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once 'database.php';

// Get course ID
$course_id = intval($_POST['course_id']);

// Validate input
if ($course_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit();
}

// Prepare SQL statement
$sql = "DELETE FROM courses WHERE course_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $course_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Course not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete course: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>