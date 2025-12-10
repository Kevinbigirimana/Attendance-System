<?php
require_once 'auth_check.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Fetch courses for this FI
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
  <title>Faculty Intern Dashboard</title>
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

  <!-- Student Past Schedule Modal -->
  <div id="studentPastScheduleModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
      <h2 id="student-past-schedule-title">Student Past Schedule</h2>
      <div id="student-past-schedule-content"></div>
      <div class="modal-buttons">
        <button type="button" class="btn btn-secondary" onclick="closeModal('studentPastScheduleModal')">Close</button>
      </div>
    </div>
  </div>

  <script>
    // Navigation between views
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const view = this.dataset.view;
        
        // Update active state
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        // Show selected view
        document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
        document.getElementById(view + '-view').classList.remove('hidden');
        
        // Load data for the view
        if (view === 'sessions') {
          loadAllSessions();
        }
      });
    });

    // Open create session modal
    function openCreateSessionModal(courseId, courseName) {
      document.getElementById('session-course-id').value = courseId;
      document.getElementById('session-course-name').value = courseName;
      document.getElementById('session-title').value = '';
      document.getElementById('session-description').value = '';
      document.getElementById('session-date').value = new Date().toISOString().split('T')[0];
      document.getElementById('session-time').value = '';
      document.getElementById('createSessionModal').classList.add('active');
    }

    // Close modal
    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('active');
    }

    // Create session
    document.getElementById('createSessionForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const data = {
        course_id: document.getElementById('session-course-id').value,
        session_title: document.getElementById('session-title').value,
        session_date: document.getElementById('session-date').value,
        session_time: document.getElementById('session-time').value,
        session_description: document.getElementById('session-description').value,
        location: document.getElementById('session-location').value,
        code_duration: document.getElementById('code-duration').value
      };
      
      try {
        const response = await fetch('session_handler.php?action=create', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
          Swal.fire({
            icon: 'success',
            title: 'Session Created!',
            html: `Attendance Code: <div class="attendance-code">${result.attendance_code}</div>`,
            confirmButtonColor: '#007bff'
          });
          closeModal('createSessionModal');
          location.reload();
        } else {
          Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to create session'});
      }
    });

    // View course sessions
    function viewCourseSessions(courseId) {
      document.querySelector('[data-view="sessions"]').click();
      loadCourseSessions(courseId);
    }

    // Load all sessions
    async function loadAllSessions() {
      try {
        const response = await fetch('session_handler.php?action=list');
        const result = await response.json();
        
        if (result.success) {
          displaySessions(result.sessions);
        }
      } catch (error) {
        console.error('Failed to load sessions:', error);
      }
    }

    // Load course-specific sessions
    async function loadCourseSessions(courseId) {
      try {
        const response = await fetch(`session_handler.php?action=list&course_id=${courseId}`);
        const result = await response.json();
        
        if (result.success) {
          displaySessions(result.sessions);
        }
      } catch (error) {
        console.error('Failed to load sessions:', error);
      }
    }

    // Display sessions
    function displaySessions(sessions) {
      const container = document.getElementById('sessions-list');
      if (sessions.length === 0) {
        container.innerHTML = '<p>No sessions found. Create one to get started!</p>';
        return;
      }
      
      let html = '<table><thead><tr><th>Date</th><th>Time</th><th>Course</th><th>Title</th><th>Status</th><th>Attendance</th><th>Actions</th></tr></thead><tbody>';
      
      sessions.forEach(session => {
        const attendanceRate = session.total_students > 0 
          ? Math.round((session.present_count / session.total_students) * 100) 
          : 0;
        html += `<tr>
          <td>${session.session_date}</td>
          <td>${session.session_time}</td>
          <td>${session.course_code}</td>
          <td>${session.session_title}</td>
          <td><span class="status-${session.status}">${session.status}</span></td>
          <td>${session.present_count}/${session.total_students} (${attendanceRate}%)</td>
          <td>
            <button class="btn btn-sm btn-primary" onclick="viewSessionDetails(${session.session_id})">View</button>
            <button class="btn btn-sm btn-success" onclick="markAttendance(${session.session_id})">Mark</button>
            ${session.status === 'scheduled' ? `<button class="btn btn-sm btn-success" onclick="activateSession(${session.session_id})">Activate</button>` : ''}
            ${session.status === 'active' ? `<button class="btn btn-sm btn-secondary" onclick="closeSession(${session.session_id})">Close</button>` : ''}
          </td>
        </tr>`;
      });
      html += '</tbody></table>';
      container.innerHTML = html;
    }

    // View session details
    async function viewSessionDetails(sessionId) {
      try {
        const response = await fetch(`session_handler.php?action=get&session_id=${sessionId}`);
        const result = await response.json();
        
        if (result.success) {
          const s = result.session;
          document.getElementById('session-details-content').innerHTML = `
            <div><strong>Course:</strong> ${s.course_code} - ${s.course_name}</div>
            <div><strong>Title:</strong> ${s.session_title}</div>
            <div><strong>Date:</strong> ${s.session_date}</div>
            <div><strong>Time:</strong> ${s.session_time}</div>
            ${s.location ? `<div><strong>Location:</strong> ${s.location}</div>` : ''}
            <div><strong>Status:</strong> ${s.status}</div>
            <div><strong>Attendance Code:</strong> <div class="attendance-code">${s.attendance_code}</div></div>
            <div><strong>Code Expires:</strong> ${s.code_expiry}</div>
            ${s.session_description ? `<div><strong>Description:</strong> ${s.session_description}</div>` : ''}
            <div style="margin-top: 20px;">
              <button class="btn btn-primary" onclick="regenerateCode(${sessionId})">Regenerate Code</button>
            </div>
          `;
          document.getElementById('sessionDetailsModal').classList.add('active');
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to load session details'});
      }
    }

    // Activate session
    async function activateSession(sessionId) {
      try {
        const response = await fetch('session_handler.php?action=update_status', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({session_id: sessionId, status: 'active'})
        });
        const result = await response.json();
        
        if (result.success) {
          Swal.fire({icon: 'success', title: 'Session Activated!', timer: 1500, showConfirmButton: false});
          loadAllSessions();
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to activate session'});
      }
    }

    // Close session
    async function closeSession(sessionId) {
      try {
        const response = await fetch('session_handler.php?action=update_status', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({session_id: sessionId, status: 'closed'})
        });
        const result = await response.json();
        
        if (result.success) {
          Swal.fire({icon: 'success', title: 'Session Closed!', timer: 1500, showConfirmButton: false});
          loadAllSessions();
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to close session'});
      }
    }

    // Regenerate code
    async function regenerateCode(sessionId) {
      try {
        const response = await fetch('session_handler.php?action=regenerate_code', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({session_id: sessionId, code_duration: 30})
        });
        const result = await response.json();
        
        if (result.success) {
          Swal.fire({
            icon: 'success',
            title: 'New Code Generated!',
            html: `<div class="attendance-code">${result.attendance_code}</div>`,
            confirmButtonColor: '#007bff'
          });
          viewSessionDetails(sessionId);
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to regenerate code'});
      }
    }

    // Mark attendance
    async function markAttendance(sessionId) {
      try {
        const response = await fetch(`attendance_handler.php?action=get_session_attendance&session_id=${sessionId}`);
        const result = await response.json();
        
        if (result.success) {
          let html = '<table style="width:100%"><thead><tr><th>Student</th><th>Email</th><th>Status</th><th>Action</th></tr></thead><tbody>';
          
          result.attendance.forEach(record => {
            html += `<tr>
              <td>${record.student_name}</td>
              <td>${record.student_email}</td>
              <td><span class="status-${record.status}">${record.status}</span></td>
              <td>
                <select onchange="updateAttendance(${sessionId}, ${record.student_id}, this.value)">
                  <option value="">--Change--</option>
                  <option value="present">Present</option>
                  <option value="absent">Absent</option>
                  <option value="late">Late</option>
                  <option value="excused">Excused</option>
                </select>
              </td>
            </tr>`;
          });
          html += '</tbody></table><div class="modal-buttons"><button class="btn btn-secondary" onclick="closeModal(\'markAttendanceModal\')">Close</button></div>';
          
          document.getElementById('attendance-list-content').innerHTML = html;
          document.getElementById('markAttendanceModal').classList.add('active');
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to load attendance'});
      }
    }

    // Update individual attendance
    async function updateAttendance(sessionId, studentId, status) {
      if (!status) return;
      
      try {
        const response = await fetch('attendance_handler.php?action=mark_manual', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({session_id: sessionId, student_id: studentId, status: status})
        });
        const result = await response.json();
        
        if (result.success) {
          Swal.fire({icon: 'success', title: 'Updated!', timer: 1000, showConfirmButton: false});
          markAttendance(sessionId); // Reload
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to update attendance'});
      }
    }

    // Load course report
    async function loadCourseReport() {
      const courseId = document.getElementById('report-course-select').value;
      if (!courseId) {
        document.getElementById('report-content').innerHTML = '';
        return;
      }
      
      try {
        const response = await fetch(`attendance_handler.php?action=course_report&course_id=${courseId}`);
        const result = await response.json();
        
        if (result.success) {
          let html = '<h3>Overall Course Attendance</h3>';
          html += '<table><thead><tr><th>Student</th><th>Email</th><th>Total Sessions</th><th>Present</th><th>Late</th><th>Absent</th><th>Attendance %</th></tr></thead><tbody>';
          
          result.students.forEach(student => {
            html += `<tr>
              <td>${student.student_name}</td>
              <td>${student.email}</td>
              <td>${student.total_sessions}</td>
              <td>${student.present_count}</td>
              <td>${student.late_count}</td>
              <td>${student.absent_count}</td>
              <td><strong>${student.attendance_percentage}%</strong></td>
            </tr>`;
          });
          html += '</tbody></table>';
          document.getElementById('report-content').innerHTML = html;
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to load report'});
      }
    }

    // Open add course modal
    function openAddCourseModal() {
      document.getElementById('course-code').value = '';
      document.getElementById('course-name').value = '';
      document.getElementById('course-description').value = '';
      document.getElementById('credit-hours').value = '';
      document.getElementById('addCourseModal').classList.add('active');
    }

    // Add course form submission
    document.getElementById('addCourseForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('course_code', document.getElementById('course-code').value);
      formData.append('course_name', document.getElementById('course-name').value);
      formData.append('description', document.getElementById('course-description').value);
      formData.append('credit_hours', document.getElementById('credit-hours').value);
      
      try {
        const response = await fetch('add_course.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        
        if (result.success) {
          Swal.fire({
            icon: 'success',
            title: 'Course Added!',
            text: result.message,
            confirmButtonColor: '#007bff'
          }).then(() => {
            location.reload(); // Reload to show new course
          });
          closeModal('addCourseModal');
        } else {
          Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to add course'});
      }
    });

    // View course students
    async function viewCourseStudents(courseId, courseName) {
      document.getElementById('course-students-title').textContent = `Students - ${courseName}`;
      
      try {
        const response = await fetch(`student_course_handler.php?action=get_enrolled_students&course_id=${courseId}`);
        const result = await response.json();
        
        if (result.success) {
          let html = '<table><thead><tr><th>Name</th><th>Email</th><th>Enrolled Date</th><th>Actions</th></tr></thead><tbody>';
          
          if (result.students && result.students.length > 0) {
            result.students.forEach(student => {
              html += `<tr>
                <td>${student.student_name}</td>
                <td>${student.email}</td>
                <td>${student.enrollment_date}</td>
                <td>
                  <button class="btn btn-sm" style="background-color: #007bff; color: white; margin-right: 5px;" onclick="viewStudentPastSchedule(${student.student_id}, ${courseId}, '${student.student_name}', '${courseName}')">Past Schedule</button>
                  <button class="btn btn-sm" style="background-color: #dc3545; color: white;" onclick="removeStudentFromCourse(${student.student_id}, ${courseId}, '${student.student_name}', '${courseName}')">Remove</button>
                </td>
              </tr>`;
            });
          } else {
            html += '<tr><td colspan="4">No students enrolled yet</td></tr>';
          }
          
          html += '</tbody></table>';
          document.getElementById('course-students-content').innerHTML = html;
          document.getElementById('courseStudentsModal').classList.add('active');
        } else {
          Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to load students'});
      }
    }

    // Remove student from course
    async function removeStudentFromCourse(studentId, courseId, studentName, courseName) {
      const confirm = await Swal.fire({
        title: 'Remove Student?',
        text: `Remove ${studentName} from ${courseName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, remove'
      });
      
      if (confirm.isConfirmed) {
        try {
          const response = await fetch('student_course_handler.php?action=remove_student', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({student_id: studentId, course_id: courseId})
          });
          const result = await response.json();
          
          if (result.success) {
            Swal.fire({icon: 'success', title: 'Removed!', timer: 1500, showConfirmButton: false});
            viewCourseStudents(courseId, courseName); // Reload list
          } else {
            Swal.fire({icon: 'error', title: 'Error', text: result.message});
          }
        } catch (error) {
          Swal.fire({icon: 'error', title: 'Error', text: 'Failed to remove student'});
        }
      }
    }

    // View student past schedule
    async function viewStudentPastSchedule(studentId, courseId, studentName, courseName) {
      document.getElementById('student-past-schedule-title').textContent = `Past Schedule - ${studentName} (${courseName})`;
      document.getElementById('studentPastScheduleModal').classList.add('active');
      
      const content = document.getElementById('student-past-schedule-content');
      content.innerHTML = '<div style="text-align:center;padding:20px;">Loading...</div>';
      
      try {
        const url = `attendance_handler.php?action=student_past_schedule&student_id=${studentId}&course_id=${courseId}`;
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
          let html = '';
          
          // Display statistics
          html += `<div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
            <div style="background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; min-width: 120px; text-align: center;">
              <div style="font-size: 24px; font-weight: bold; color: #007bff;">${result.stats.total}</div>
              <div style="color: #666; margin-top: 5px; font-size: 14px;">Total Sessions</div>
            </div>
            <div style="background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; min-width: 120px; text-align: center;">
              <div style="font-size: 24px; font-weight: bold; color: #28a745;">${result.stats.present}</div>
              <div style="color: #666; margin-top: 5px; font-size: 14px;">Present</div>
            </div>
            <div style="background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; min-width: 120px; text-align: center;">
              <div style="font-size: 24px; font-weight: bold; color: #ffc107;">${result.stats.late}</div>
              <div style="color: #666; margin-top: 5px; font-size: 14px;">Late</div>
            </div>
            <div style="background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; min-width: 120px; text-align: center;">
              <div style="font-size: 24px; font-weight: bold; color: #dc3545;">${result.stats.absent}</div>
              <div style="color: #666; margin-top: 5px; font-size: 14px;">Absent</div>
            </div>
            <div style="background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; min-width: 120px; text-align: center;">
              <div style="font-size: 24px; font-weight: bold; color: #6c757d;">${result.stats.excused}</div>
              <div style="color: #666; margin-top: 5px; font-size: 14px;">Excused</div>
            </div>
          </div>`;
          
          if (result.sessions && result.sessions.length > 0) {
            html += '<table><thead><tr><th>Date</th><th>Time</th><th>Session</th><th>Location</th><th>Status</th><th>Marked At</th></tr></thead><tbody>';
            
            result.sessions.forEach(session => {
              const statusColor = session.attendance_status === 'present' ? '#28a745' : 
                                 session.attendance_status === 'late' ? '#ffc107' : 
                                 session.attendance_status === 'excused' ? '#6c757d' : '#dc3545';
              
              html += `<tr>
                <td>${session.session_date}</td>
                <td>${session.session_time}</td>
                <td>${session.session_title}</td>
                <td>${session.location || 'N/A'}</td>
                <td><span style="color: ${statusColor}; font-weight: bold;">${session.attendance_status.toUpperCase()}</span></td>
                <td>${session.marked_at ? new Date(session.marked_at).toLocaleString() : 'N/A'}</td>
              </tr>`;
            });
            
            html += '</tbody></table>';
          } else {
            html += '<p style="text-align:center;color:#666;">No past attendance records found.</p>';
          }
          
          content.innerHTML = html;
        } else {
          content.innerHTML = `<p style="color:#dc3545;text-align:center;">${result.message || 'Failed to load past schedule'}</p>`;
        }
      } catch (error) {
        content.innerHTML = '<p style="color:#dc3545;text-align:center;">Failed to load past schedule</p>';
      }
    }
  </script>
  <script src="../js/logout.js"></script>
</body>
</html>