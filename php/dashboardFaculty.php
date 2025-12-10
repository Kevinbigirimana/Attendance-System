<?php
require_once 'auth_check.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Fetch courses for this Faculty
$sql = "SELECT c.course_id, c.course_code, c.course_name, c.description,
        COUNT(DISTINCT e.student_id) as student_count,
        COUNT(DISTINCT s.session_id) as session_count
        FROM courses c
        LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.status = 'approved'
        LEFT JOIN sessions s ON c.course_id = s.course_id
        WHERE c.faculty_id = ?
        GROUP BY c.course_id
        ORDER BY c.course_code";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"="width=device-width, initial-scale=1.0">
  <title>Faculty Dashboard</title>
  <link rel="stylesheet" href="../css/dashboardFI.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .nav-link {cursor: pointer; transition: background 0.3s;}
    .hidden {display: none;}
    .modal {display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;}
    .modal.active {display: flex;}
    .modal-content {background: white; padding: 30px; border-radius: 10px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;}
    .form-group {margin-bottom: 15px;}
    .form-group label {display: block; margin-bottom: 5px; font-weight: bold;}
    .form-group input, .form-group textarea, .form-group select {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;}
    .modal-buttons {display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;}
    .btn {padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;}
    .btn-primary {background-color: #007bff; color: white;}
    .btn-secondary {background-color: #6c757d; color: white;}
    .btn-success {background-color: #28a745; color: white;}
    .btn-warning {background-color: #ffc107; color: #000;}
    .btn-info {background-color: #17a2b8; color: white;}
    .btn-sm {padding: 6px 12px; font-size: 14px;}
    .attendance-code {font-size: 24px; font-weight: bold; letter-spacing: 3px; color: #007bff; padding: 15px; background: #f4f6f8; border-radius: 5px; text-align: center; margin: 15px 0;}
    .stats {display: flex; gap: 20px; margin: 20px 0;}
    .stat-card {background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; text-align: center;}
    .stat-value {font-size: 32px; font-weight: bold; color: #007bff;}
    .stat-label {color: #666; margin-top: 5px;}
  </style>
</head>
<body>
  <div class="dashboard-container">
    <aside class="sidebar">
      <h2>Attendance System</h2>
      <nav>
        <ul>
          <li><a class="nav-link" data-view="courses">My Courses</a></li>
          <li><a class="nav-link" data-view="sessions">Sessions</a></li>
          <li><a class="nav-link" data-view="reports">Reports</a></li>
          <li><a href="manage_requests.php">Manage Students</a></li>
          <li><a href="#" class="logout-btn">Log Out</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
      </header>

      <!-- Courses View -->
      <section id="courses-view" class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2>My Courses</h2>
          <div style="display: flex; gap: 10px;">
            <button class="btn btn-success" onclick="openAddCourseModal()">Add Course</button>
            <button class="btn btn-primary" onclick="window.location.href='manage_requests.php'">View Enrollment Requests</button>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Course Code</th>
              <th>Course Title</th>
              <th>Students</th>
              <th>Sessions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($courses)): ?>
              <tr><td colspan="5">No courses assigned yet. Click "Add Course" to create one.</td></tr>
            <?php else: ?>
              <?php foreach ($courses as $course): ?>
                <tr>
                  <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                  <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                  <td><?php echo $course['student_count']; ?></td>
                  <td><?php echo $course['session_count']; ?></td>
                  <td>
                    <button class="btn btn-primary btn-sm" onclick="viewCourseSessions(<?php echo $course['course_id']; ?>)">View Sessions</button>
                    <button class="btn btn-success btn-sm" onclick="openCreateSessionModal(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">Create Session</button>
                    <button class="btn btn-sm" style="background-color: #17a2b8; color: white;" onclick="viewCourseStudents(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">Students</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <!-- Sessions View -->
      <section id="sessions-view" class="section hidden">
        <h2>Class Sessions</h2>
        <div id="sessions-list"></div>
      </section>

      <!-- Reports View -->
      <section id="reports-view" class="section hidden">
        <h2>Attendance Reports</h2>
        <div class="form-group">
          <label>Select Course:</label>
          <select id="report-course-select" onchange="loadCourseReport()">
            <option value="">-- Select a course --</option>
            <?php foreach ($courses as $course): ?>
              <option value="<?php echo $course['course_id']; ?>">
                <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="report-content"></div>
      </section>
    </main>

  </div>

  <!-- Create Session Modal -->
  <div id="createSessionModal" class="modal">
    <div class="modal-content">
      <h2>Create Class Session</h2>
      <form id="createSessionForm">
        <input type="hidden" id="session-course-id">
        <div class="form-group">
          <label>Course:</label>
          <input type="text" id="session-course-name" readonly>
        </div>
        <div class="form-group">
          <label>Session Title:</label>
          <input type="text" id="session-title" required placeholder="e.g., Week 5 Lecture">
        </div>
        <div class="form-group">
          <label>Session Date:</label>
          <input type="date" id="session-date" required>
        </div>
        <div class="form-group">
          <label>Session Time:</label>
          <input type="time" id="session-time" required>
        </div>
        <div class="form-group">
          <label>Description (Optional):</label>
          <textarea id="session-description" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label>Location (Optional):</label>
          <input type="text" id="session-location" placeholder="e.g., Room 101, Zoom Link">
        </div>
        <div class="form-group">
          <label>Code Valid For (minutes):</label>
          <input type="number" id="code-duration" value="30" min="5" max="180">
        </div>
        <div class="modal-buttons">
          <button type="button" class="btn btn-secondary" onclick="closeModal('createSessionModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Session</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Session Details Modal -->
  <div id="sessionDetailsModal" class="modal">
    <div class="modal-content">
      <h2>Session Details</h2>
      <div id="session-details-content"></div>
      <div class="modal-buttons">
        <button type="button" class="btn btn-secondary" onclick="closeModal('sessionDetailsModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Mark Attendance Modal -->
  <div id="markAttendanceModal" class="modal">
    <div class="modal-content">
      <h2>Mark Attendance</h2>
      <div id="attendance-list-content"></div>
    </div>
  </div>

  <!-- Add Course Modal -->
  <div id="addCourseModal" class="modal">
    <div class="modal-content">
      <h2>Add New Course</h2>
      <form id="addCourseForm">
        <div class="form-group">
          <label>Course Code:</label>
          <input type="text" id="course-code" placeholder="e.g., CS205">
        </div>
        <div class="form-group">
          <label>Course Name: *</label>
          <input type="text" id="course-name" required placeholder="e.g., Data Structures">
        </div>
        <div class="form-group">
          <label>Description:</label>
          <textarea id="course-description" rows="3" placeholder="Course description (optional)"></textarea>
        </div>
        <div class="form-group">
          <label>Credit Hours:</label>
          <input type="number" id="credit-hours" min="1" max="10" placeholder="e.g., 3">
        </div>
        <div class="modal-buttons">
          <button type="button" class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Course</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Course Students Modal -->
  <div id="courseStudentsModal" class="modal">
    <div class="modal-content">
      <h2 id="course-students-title">Course Students</h2>
      <div id="course-students-content"></div>
      <div class="modal-buttons">
        <button type="button" class="btn btn-secondary" onclick="closeModal('courseStudentsModal')">Close</button>
      </div>
    </div>
  </div>

  <script src="../js/fiDashboard.js"></script>
  <script src="../js/logout.js"></script>
</body>
</html>