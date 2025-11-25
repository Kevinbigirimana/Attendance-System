<?php
/**
 * Logout Script
 * Clears session and returns JSON response
 */

// Start session
session_start();

// Set header for JSON response
header('Content-Type: application/json');

try {
    // Clear all session variables
    session_unset();
    
    // Destroy the session
    session_destroy();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Return success JSON response
    echo json_encode([
        'logout' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    // Return failure JSON response
    echo json_encode([
        'logout' => false,
        'message' => 'Logout failed'
    ]);
}
?>