<?php
/**
 * Authentication Check
 * Handles both regular page loads and AJAX requests
 */

// Include session configuration for concurrent user support
require_once __DIR__ . '/session_config.php';

// Session is now started in session_config.php
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    
    // Detect if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Also check if the request expects JSON (from fetch API)
    $expectsJson = isset($_SERVER['HTTP_ACCEPT']) && 
                   strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    
    // Check if this is a POST request (most AJAX handlers use POST)
    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
    
    // If it's an AJAX call or expects JSON, return JSON error
    if ($isAjax || $expectsJson || $isPost) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Not logged in. Please login first.',
            'redirect' => '../html/login.html'
        ]);
        exit();
    }
    
    // Otherwise, redirect to login page (for regular page loads)
    header("Location: ../html/login.html");
    exit();
}
?>