<?php
require_once 'auth_check.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'student') {
    die("Access denied. Student privileges required.");
}

// Database connection
require_once 'database.php';

// Fetch enrolled courses for the current student (only approved enrollments)
$current_user_id = $_SESSION['user_id'];
$sql = "SELECT c.course_id, c.course_code, c.course_name, c.description, c.credit_hours,
        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
        e.approval_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        JOIN users u ON c.faculty_id = u.user_id
        WHERE e.student_id = ? AND e.status = 'approved'
        ORDER BY c.course_code";

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
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../css/studentDashboard.css">
  <!-- SweetAlert2 CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: white;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 700px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .modal-content h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
    }

    .search-container {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .search-container input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .search-container button {
        padding: 10px 20px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .course-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .course-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        background-color: #f9f9f9;
    }

    .course-card h4 {
        margin: 0 0 10px 0;
        color: #333;
    }

    .course-card p {
        margin: 5px 0;
        color: #666;
        font-size: 14px;
    }

    .course-actions {
        margin-top: 10px;
        display: flex;
        gap: 10px;
    }

    .btn-join {
        padding: 8px 16px;
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .btn-join:hover {
        background-color: #218838;
    }

    .btn-pending {
        padding: 8px 16px;
        background-color: #ffc107;
        color: #333;
        border: none;
        border-radius: 4px;
        cursor: not-allowed;
    }

    .btn-enrolled {
        padding: 8px 16px;
        background-color: #6c757d;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: not-allowed;
    }

    .btn-rejected {
        padding: 8px 16px;
        background-color: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: not-allowed;
    }

    .modal-buttons {
        margin-top: 20px;
        display: flex;
        justify-content: flex-end;
    }

    .modal-buttons button {
        padding: 10px 20px;
        background-color: #6c757d;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .loading {
        text-align: center;
        padding: 20px;
        color: #666;
    }
  </style>
</head>
<body>
  <div class="container">

    <aside class="sidebar">
      <h2>Attendance Portal</h2>
      <nav>
        <ul>
          <li><a href="studentDashboard.php" class="active">My Courses</a></li>
          <li><a href="#">Session Schedule</a></li>
          <li><a href="#">Grades/Reports</a></li>
          <li><a href="#" class="logout-btn">Log Out</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
        <p>Role: Student</p>
      </header>
      
      <div class="button-group">
        <button class="join-course" id="joinCourseBtn">Join a Course</button>
        <button class="take-attendance">Take Attendance</button>
      </div>

      <section class="section">
        <h2>My Enrolled Courses</h2>
        <table id="coursesTable">
          <thead>
            <tr>
              <th>Course Code</th>
              <th>Course Title</th>
              <th>Instructor</th>
              <th>Credit Hours</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['course_code'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['instructor_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['credit_hours'] ?? 'N/A') . "</td>";
                    echo "<td>";
                    echo "<button class='view' onclick='viewCourse(" . $row['course_id'] . ")'>View</button> ";
                    echo "<button class='attendance' onclick='markAttendance(" . $row['course_id'] . ")'>Mark Attendance</button>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align: center;'>No enrolled courses yet. Click 'Join a Course' to get started!</td></tr>";
            }
            
            if (isset($stmt)) {
                $stmt->close();
            }
            $conn->close();
            ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>

  <!-- Join Course Modal -->
  <div id="joinCourseModal" class="modal">
    <div class="modal-content">
      <h3>Available Courses</h3>
      <div class="search-container">
        <input type="text" id="courseSearch" placeholder="Search by course code or name...">
        <button id="searchBtn">Search</button>
      </div>
      
      <div id="availableCourses" class="course-list">
        <div class="loading">Loading courses...</div>
      </div>
      
      <div class="modal-buttons">
        <button type="button" id="cancelJoinBtn">Close</button>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="../js/logout.js"></script>
  <script src="../js/studentDashboard.js"></script>
</body>
</html>