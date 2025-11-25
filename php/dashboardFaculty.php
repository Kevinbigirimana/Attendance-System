<?php
/**
 * Faculty Dashboard - Course Management
 */

require_once 'auth_check.php';

// Check if user is logged in and is faculty 
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'faculty') {
    die("Access denied. Faculty privileges required.");
}

// Database connection
require_once 'database.php';

// Fetch courses for the current faculty only using prepared statement
$current_user_id = $_SESSION['user_id'];
$sql = "SELECT course_id, course_code, course_name, description, credit_hours, faculty_id FROM courses WHERE faculty_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error preparing query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty Dashboard</title>
  <link rel="stylesheet" href="../css/dashboardAdmin.css">
  <link rel="stylesheet" href="../css/modal_styles.css">
  <!-- SweetAlert2 CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <div class="dashboard-container">
    <aside class="sidebar">
      <h2>Attendance System</h2>
      <nav>
        <ul>
          <li><a href="dashboardFaculty.php" class="active">Course Management</a></li>
          <li><a href="#link">Session Overview</a></li>
          <li><a href="#link">Attendance Reports</a></li>
          <li><a href="#" class="logout-btn">Log Out</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
        <p>Role: Faculty</p>
      </header>

      <section class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2>Course Management</h2>
          <button class="view-requests-btn" id="viewRequestsBtn" style="padding: 10px 20px; background-color: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
            View Enrollment Requests
          </button>
        </div>
        
        <table id="coursesTable">
          <thead>
            <tr>
              <th>Course Code</th>
              <th>Course Name</th>
              <th>Description</th>
              <th>Credit Hours</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['course_code'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['description'] ?? 'No description') . "</td>";
                    echo "<td>" . htmlspecialchars($row['credit_hours'] ?? 'N/A') . "</td>";
                    echo "<td>";
                    
                    // Prepare data for JavaScript - escape quotes and handle nulls
                    $course_code = addslashes($row['course_code'] ?? '');
                    $course_name = addslashes($row['course_name']);
                    $description = addslashes($row['description'] ?? '');
                    $credit_hours = $row['credit_hours'] ?? 0;
                    
                    echo "<button class='edit-btn' onclick='editCourse(" . 
                         $row['course_id'] . ", \"" . 
                         $course_code . "\", \"" . 
                         $course_name . "\", \"" . 
                         $description . "\", " . 
                         $credit_hours . ")'>Edit</button> ";
                    echo "<button class='view-btn' onclick='viewCourseRequests(" . 
                         $row['course_id'] . ", \"" . 
                         $course_code . "\")' style='background-color: #17a2b8;'>Requests</button> ";
                    echo "<button class='students-btn' onclick='viewEnrolledStudents(" . 
                         $row['course_id'] . ", \"" . 
                         $course_code . "\")' style='background-color: #28a745;'>Students</button> ";
                    echo "<button class='delete-btn' onclick='deleteCourse(" . 
                         $row['course_id'] . ", \"" . 
                         $course_code . "\")'>Delete</button>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align: center;'>No courses found. Add your first course!</td></tr>";
            }
            
            // Close statement and connection
            if (isset($stmt)) {
                $stmt->close();
            }
            $conn->close();
            ?>
          </tbody>
        </table>
        
        <div class="addCourse-container">
          <button class="add-button" id="addCourseBtn">+ Add Course</button>
        </div>
      </section>
    </main>
  </div> 

  <!-- Add Course Modal -->
  <div id="addCourseModal" class="modal">
    <div class="modal-content">
      <h3>Add New Course</h3>
      <form id="addCourseForm">
        <div class="form-group">
          <label for="course_code">Course Code:</label>
          <input type="text" id="course_code" name="course_code" placeholder="e.g., CS101">
        </div>
        <div class="form-group">
          <label for="course_name">Course Name: <span style="color: red;">*</span></label>
          <input type="text" id="course_name" name="course_name" required placeholder="e.g., Introduction to Programming">
        </div>
        <div class="form-group">
          <label for="description">Description:</label>
          <textarea id="description" name="description" placeholder="Course description (optional)"></textarea>
        </div>
        <div class="form-group">
          <label for="credit_hours">Credit Hours:</label>
          <input type="number" id="credit_hours" name="credit_hours" min="1" max="10" placeholder="e.g., 3">
        </div>
        <div class="modal-buttons">
          <button type="button" id="cancelAddBtn">Cancel</button>
          <button type="submit">Add Course</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Course Modal -->
  <div id="editCourseModal" class="modal">
    <div class="modal-content">
      <h3>Edit Course</h3>
      <form id="editCourseForm">
        <input type="hidden" id="edit_course_id" name="course_id">
        <div class="form-group">
          <label for="edit_course_code">Course Code:</label>
          <input type="text" id="edit_course_code" name="course_code">
        </div>
        <div class="form-group">
          <label for="edit_course_name">Course Name: <span style="color: red;">*</span></label>
          <input type="text" id="edit_course_name" name="course_name" required>
        </div>
        <div class="form-group">
          <label for="edit_description">Description:</label>
          <textarea id="edit_description" name="description"></textarea>
        </div>
        <div class="form-group">
          <label for="edit_credit_hours">Credit Hours:</label>
          <input type="number" id="edit_credit_hours" name="credit_hours" min="1" max="10">
        </div>
        <div class="modal-buttons">
          <button type="button" id="cancelEditBtn">Cancel</button>
          <button type="submit">Update Course</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Enrollment Requests Modal -->
  <div id="requestsModal" class="modal">
    <div class="modal-content">
      <h3>Enrollment Requests</h3>
      <div id="requestsList">
        <!-- Requests will be loaded here via AJAX -->
      </div>
      <div class="modal-buttons">
        <button type="button" id="cancelRequestsBtn">Close</button>
      </div>
    </div>
  </div>

  <!-- View Course-Specific Requests Modal -->
  <div id="courseRequestsModal" class="modal">
    <div class="modal-content">
      <h3 id="courseRequestsTitle">Course Enrollment Requests</h3>
      <div id="courseRequestsList">
        <!-- Course-specific requests will be loaded here -->
      </div>
      <div class="modal-buttons">
        <button type="button" id="cancelCourseRequestsBtn">Close</button>
      </div>
    </div>
  </div>

  <!-- View Enrolled Students Modal -->
  <div id="enrolledStudentsModal" class="modal">
    <div class="modal-content">
      <h3 id="enrolledStudentsTitle">Enrolled Students</h3>
      <div id="enrolledStudentsList">
        <!-- Enrolled students will be loaded here -->
      </div>
      <div class="modal-buttons">
        <button type="button" id="cancelEnrolledStudentsBtn">Close</button>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../js/logout.js"></script>
  <script src="../js/dashboardFaculty.js"></script>
</body>
</html>