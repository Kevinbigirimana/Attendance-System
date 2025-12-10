<?php
require_once 'auth_check.php';
require_once 'database.php';

// Check if user is faculty or fi
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['faculty', 'fi'])) {
    die("Access denied. Faculty or FI privileges required.");
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Enrollment Requests</title>
  <link rel="stylesheet" href="../css/dashboardFI.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .btn {padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;}
    .btn-primary {background-color: #007bff; color: white;}
    .btn-success {background-color: #28a745; color: white;}
    .btn-danger {background-color: #dc3545; color: white;}
    .btn-secondary {background-color: #6c757d; color: white;}
    .btn-sm {padding: 6px 12px; font-size: 14px;}
    .loading {text-align: center; padding: 20px; color: #666;}
    .no-requests {text-align: center; padding: 40px; color: #999;}
    .request-card {border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 8px; background: #f9f9f9;}
    .request-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;}
    .request-info {color: #666; font-size: 14px;}
    .request-actions {display: flex; gap: 10px; margin-top: 10px;}
  </style>
</head>
<body>
  <div class="dashboard-container">
    <aside class="sidebar">
      <h2>Attendance System</h2>
      <nav>
        <ul>
          <li><a href="<?php echo ($_SESSION['role'] === 'fi') ? 'fiDashboard.php' : 'dashboardFaculty.php'; ?>">My Courses</a></li>
          <li><a href="manage_requests.php" class="active">Manage Students</a></li>
          <li><a href="#" class="logout-btn">Log Out</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <h1>Student Enrollment Requests</h1>
        <p>Welcome, <?php echo htmlspecialchars($username); ?></p>
      </header>

      <section class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2>Pending Requests</h2>
          <button class="btn btn-primary" onclick="loadRequests()">Refresh</button>
        </div>

        <div id="requests-container">
          <div class="loading">Loading requests...</div>
        </div>
      </section>
    </main>
  </div>

  <script src="../js/logout.js"></script>
  <script>
    // Load requests on page load
    document.addEventListener('DOMContentLoaded', function() {
      loadRequests();
    });

    // Load all pending requests
    async function loadRequests() {
      const container = document.getElementById('requests-container');
      container.innerHTML = '<div class="loading">Loading requests...</div>';

      try {
        const formData = new FormData();
        formData.append('action', 'get_pending_requests');
        formData.append('course_id', '0'); // 0 means all courses

        const response = await fetch('faculty_requests_handler.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          if (result.requests.length === 0) {
            container.innerHTML = '<div class="no-requests">No pending requests at this time.</div>';
          } else {
            displayRequests(result.requests);
          }
        } else {
          container.innerHTML = `<div class="no-requests" style="color: #dc3545;">Error: ${result.message}</div>`;
        }
      } catch (error) {
        console.error('Error loading requests:', error);
        container.innerHTML = '<div class="no-requests" style="color: #dc3545;">Failed to load requests. Please try again.</div>';
      }
    }

    // Display requests
    function displayRequests(requests) {
      const container = document.getElementById('requests-container');
      
      let html = '';
      requests.forEach(request => {
        html += `
          <div class="request-card">
            <div class="request-header">
              <div>
                <strong>${request.student_name}</strong>
                <div class="request-info">${request.student_email}</div>
              </div>
            </div>
            <div class="request-info">
              <strong>Course:</strong> ${request.course_code} - ${request.course_name}<br>
              <strong>Request Date:</strong> ${formatDate(request.request_date)}
            </div>
            <div class="request-actions">
              <button class="btn btn-success btn-sm" onclick="approveRequest(${request.enrollment_id}, '${request.student_name}', '${request.course_name}')">
                Approve
              </button>
              <button class="btn btn-danger btn-sm" onclick="rejectRequest(${request.enrollment_id}, '${request.student_name}', '${request.course_name}')">
                Reject
              </button>
            </div>
          </div>
        `;
      });
      
      container.innerHTML = html;
    }

    // Format date
    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    // Approve request
    async function approveRequest(enrollmentId, studentName, courseName) {
      const confirm = await Swal.fire({
        title: 'Approve Request?',
        text: `Approve ${studentName} for ${courseName}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, approve'
      });

      if (confirm.isConfirmed) {
        try {
          const formData = new FormData();
          formData.append('action', 'approve_request');
          formData.append('enrollment_id', enrollmentId);

          const response = await fetch('faculty_requests_handler.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            Swal.fire({
              icon: 'success',
              title: 'Approved!',
              text: result.message,
              timer: 2000,
              showConfirmButton: false
            });
            loadRequests(); // Reload list
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: result.message
            });
          }
        } catch (error) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to approve request'
          });
        }
      }
    }

    // Reject request
    async function rejectRequest(enrollmentId, studentName, courseName) {
      const confirm = await Swal.fire({
        title: 'Reject Request?',
        text: `Reject ${studentName} for ${courseName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, reject'
      });

      if (confirm.isConfirmed) {
        try {
          const formData = new FormData();
          formData.append('action', 'reject_request');
          formData.append('enrollment_id', enrollmentId);

          const response = await fetch('faculty_requests_handler.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            Swal.fire({
              icon: 'success',
              title: 'Rejected!',
              text: result.message,
              timer: 2000,
              showConfirmButton: false
            });
            loadRequests(); // Reload list
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: result.message
            });
          }
        } catch (error) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to reject request'
          });
        }
      }
    }
  </script>
</body>
</html>
