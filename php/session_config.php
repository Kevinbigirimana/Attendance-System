<?php
/**
 * Session Configuration
 * This ensures multiple users can be logged in simultaneously
 * IMPORTANT: This must be included BEFORE any session_start() calls
 */

// Only set ini values if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters to be more user-specific
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Session cookie lifetime (1 day)
    ini_set('session.cookie_lifetime', 86400);
    
    // Session garbage collection
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    
    // Start session
    session_start();
}
?>
