<?php
session_start();
header('Content-Type: application/json');

// Database connection
require_once 'database.php';

// Get and sanitize input
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

// Server-side validation
if (empty($firstName) || empty($lastName) || empty($email) || empty($role) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate Ashesi email
//if (!str_ends_with($email, '@ashesi.edu.gh')) {
   // echo json_encode(['success' => false, 'message' => 'Please use your @ashesi.edu.gh email']);
   // exit();
//}

// Validate role
if (!in_array($role, ['student', 'faculty', 'fi'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit();
}

try {
    // Check if email already exists
    $check_sql = "SELECT user_id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit();
    }
    $check_stmt->close();

    // Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // FIXED: Use password_hash column instead of password
    $sql = "INSERT INTO users (first_name, last_name, email, role, password_hash) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $role, $hashed_password);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! Redirecting to login...',
                'user_id' => $conn->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Registration error: ' . $e->getMessage()]);
}

$conn->close();
?>