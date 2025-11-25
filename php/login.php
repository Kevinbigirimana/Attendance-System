<?php
/**
 * Login Script - Returns JSON Response
 * Validates credentials and creates session
 */

session_start();

// Set header for JSON response
header('Content-Type: application/json');

// Database connection
require_once 'database.php';

// Validate request method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If JSON decode fails, try regular POST
if ($input === null) {
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
} else {
    $email = trim($input["email"] ?? '');
    $password = $input["password"] ?? '';
}

// Server-side validation
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit();
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

try {
    // Prepare SQL query using prepared statements (secure against SQL injection)
    $sql = "SELECT user_id, first_name, last_name, email, password_hash, role FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    // Bind parameter
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify hashed password
        if (password_verify($password, $user['password_hash'])) {
            // Password is correct - regenerate session ID for security
            session_regenerate_id(true);
            
            // Create session variables
            $_SESSION["user_id"] = $user['user_id'];
            $_SESSION["username"] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION["first_name"] = $user['first_name'];
            $_SESSION["last_name"] = $user['last_name'];
            $_SESSION["email"] = $user['email'];
            $_SESSION["role"] = $user['role'];
            $_SESSION["logged_in"] = true;
            
            // Return JSON success response
            echo json_encode([
                'success' => true,
                'username' => $user['first_name'] . ' ' . $user['last_name'],
                'user_id' => $user['user_id'],
                'role' => $user['role'],
                'message' => 'Login successful'
            ]);
        } else {
            // Password is incorrect
            echo json_encode([
                'success' => false,
                'message' => 'Incorrect password'
            ]);
        }
    } else {
        // User not found
        echo json_encode([
            'success' => false,
            'message' => 'No account found with this email address'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Login error. Please try again later.'
    ]);
    error_log("Login error: " . $e->getMessage());
}

$conn->close();
?>
