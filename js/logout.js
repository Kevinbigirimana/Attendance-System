/**
 * Logout JavaScript
 * Handles logout functionality with AJAX and SweetAlert
 */

/**
 * Logout function
 * Makes fetch call to logout.php and handles response
 */
async function logout() {
    try {
        // Show loading alert
        Swal.fire({
            title: 'Logging out...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Make fetch call to logout.php
        const response = await fetch('../php/logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        // Wait for JSON response
        const data = await response.json();

        // Check if logout was successful
        if (data.logout === true) {
            // Display success SweetAlert
            Swal.fire({
                icon: 'success',
                title: 'Logged Out!',
                text: 'You have been successfully logged out.',
                confirmButtonColor: '#4a90e2',
                confirmButtonText: 'OK'
            }).then((result) => {
                // Redirect to login page after confirmation
                window.location.href = '../html/login.html';
            });
        } else {
            // Display error SweetAlert
            Swal.fire({
                icon: 'error',
                title: 'Logout Failed',
                text: data.message || 'Unable to logout. Please try again.',
                confirmButtonColor: '#4a90e2'
            });
        }

    } catch (error) {
        // Handle network or other errors
        console.error('Logout error:', error);
        
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Unable to connect to server. Please try again.',
            confirmButtonColor: '#4a90e2'
        });
    }
}

/**
 * Attach event listeners to all logout buttons
 * This runs when the page loads
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find all elements with class 'logout-btn'
    const logoutButtons = document.querySelectorAll('.logout-btn');
    
    // Attach click event to each logout button
    logoutButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default link behavior
            logout(); // Call the logout function
        });
    });
});