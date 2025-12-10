<?php
require_once 'auth_check.php';
require_once 'database.php';

$student_id = $_SESSION['user_id'];
$username = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Fetch enrolled courses
$sql = "SELECT c.course_id, c.course_code, c.course_name, c.description, c.credit_hours,
        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
        e.approval_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.course_id
        JOIN attendance_users u ON c.faculty_id = u.user_id
        WHERE e.student_id = ? AND e.status = 'approved'
        ORDER BY c.course_code";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../css/studentDashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .nav-link {cursor: pointer; transition: background 0.3s;}
    .hidden {display: none;}
    .modal {display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; 
            background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;}
    .modal.active {display: flex;}
    .modal-content {background: white; padding: 30px; border-radius: 10px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto;}
    .form-group {margin-bottom: 15px;}
    .form-group label {display: block; margin-bottom: 5px; font-weight: bold;}
    .form-group input, .form-group textarea {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;}
    .modal-buttons {display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;}
    .btn {padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; transition: all 0.3s;}
    .btn-primary {background-color: #007bff; color: white;}
    .btn-primary:hover {background-color: #0056b3;}
    .btn-secondary {background-color: #6c757d; color: white;}
    .btn-secondary:hover {background-color: #545b62;}
    .btn-success {background-color: #28a745; color: white;}
    .btn-success:hover {background-color: #218838;}
    .session-tab {font-size: 14px; padding: 8px 16px; border: 2px solid transparent;}
    .session-tab.active {border-color: #007bff;}
    .session-tab-content {min-height: 200px;}
    .session-card {border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px; background: #f9f9f9; transition: box-shadow 0.3s;}
    .session-card:hover {box-shadow: 0 4px 8px rgba(0,0,0,0.1);}
    .session-header {display: flex; justify-content: space-between; align-items: center;}
    .status-active {color: #28a745; font-weight: bold;}
    .status-scheduled {color: #007bff; font-weight: bold;}
    .status-closed {color: #6c757d; font-weight: bold;}
    .status-present {color: #28a745; font-weight: bold;}
    .status-absent {color: #dc3545; font-weight: bold;}
    .status-late {color: #ffc107; font-weight: bold;}
    .stats {display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;}
    .stat-card {background: #f4f6f8; padding: 15px; border-radius: 8px; flex: 1; min-width: 150px; text-align: center;}
    .stat-value {font-size: 28px; font-weight: bold; color: #007bff;}
    .stat-label {color: #666; margin-top: 5px;}
  </style>
</head>
<body>
  <div class="container">
    <aside class="sidebar">
      <h2>Attendance Portal</h2>
      <nav>
        <ul>
          <li><a class="nav-link active" data-view="courses">My Courses</a></li>
          <li><a class="nav-link" data-view="sessions">Session Schedule</a></li>
          <li><a class="nav-link" data-view="reports">Grades/Reports</a></li>
          <li><a href="#" class="logout-btn">Log Out</a></li>
        </ul>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p>Role: Student</p>
      </header>

      <!-- My Courses View -->
      <section id="courses-view" class="section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h2>My Enrolled Courses</h2>
          <button class="btn btn-success" onclick="openJoinCourseModal()">Join a Course</button>
        </div>
        
        <?php if (empty($courses)): ?>
          <p>No enrolled courses yet. Click "Join a Course" to get started!</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Course Code</th>
                <th>Course Name</th>
                <th>Instructor</th>
                <th>Credit Hours</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($courses as $course): ?>
                <tr>
                  <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                  <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                  <td><?php echo htmlspecialchars($course['instructor_name']); ?></td>
                  <td><?php echo htmlspecialchars($course['credit_hours'] ?? 'N/A'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

      <!-- Session Schedule View -->
      <section id="sessions-view" class="section hidden">
        <h2>Session Schedule</h2>
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
          <button class="btn btn-primary session-tab active" data-tab="available" onclick="switchSessionTab('available')">📋 Available Sessions</button>
          <button class="btn btn-secondary session-tab" data-tab="past" onclick="switchSessionTab('past')">📅 Past Schedule</button>
        </div>
        
        <!-- Available Sessions Tab -->
        <div id="available-sessions-tab" class="session-tab-content">
          <p style="margin-bottom: 20px; color: #666;">Click on any active session to mark your attendance</p>
          <div id="sessions-list"></div>
        </div>
        
        <!-- Past Schedule Tab -->
        <div id="past-sessions-tab" class="session-tab-content hidden">
          <p style="margin-bottom: 20px; color: #666;">View your past attendance records</p>
          <div id="past-sessions-list"></div>
        </div>
      </section>

      <!-- Reports View -->
      <section id="reports-view" class="section hidden">
        <h2>My Attendance Reports</h2>
        <div class="form-group">
          <label>Select Course:</label>
          <select id="report-course-select" onchange="loadStudentReport()">
            <option value="">-- All Courses Overview --</option>
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

  <!-- Join Course Modal -->
  <div id="joinCourseModal" class="modal">
    <div class="modal-content">
      <h3>Join a Course</h3>
      <div class="search-container" style="display: flex; gap: 10px; margin-bottom: 20px;">
        <input type="text" id="courseSearch" placeholder="Search by course code or name..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        <button class="btn btn-primary" onclick="searchCourses()">Search</button>
      </div>
      <div id="availableCourses"></div>
      <div class="modal-buttons">
        <button class="btn btn-secondary" onclick="closeModal('joinCourseModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Mark Attendance Modal -->
  <div id="markAttendanceModal" class="modal">
    <div class="modal-content">
      <h3>Mark Attendance</h3>
      <p id="session-info" style="margin-bottom: 15px;"></p>
      <form id="attendanceForm" onsubmit="submitAttendance(event)">
        <div class="form-group">
          <label>Enter Attendance Code:</label>
          <input type="text" id="attendanceCode" required placeholder="Enter 6-digit code" style="text-transform: uppercase;">
        </div>
        <input type="hidden" id="selected-session-id">
        <div class="modal-buttons">
          <button type="button" class="btn btn-secondary" onclick="closeModal('markAttendanceModal')">Cancel</button>
          <button type="submit" class="btn btn-success">Submit</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../js/logout.js"></script>
  <script>
    // Navigation
    document.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const view = this.dataset.view;
        
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
        document.getElementById(view + '-view').classList.remove('hidden');
        
        if (view === 'sessions') loadSessions();
        if (view === 'reports') loadStudentReport();
      });
    });

    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('active');
    }

    // Join Course Modal
    function openJoinCourseModal() {
      document.getElementById('joinCourseModal').classList.add('active');
      searchCourses();
    }

    async function searchCourses() {
      const search = document.getElementById('courseSearch').value;
      const container = document.getElementById('availableCourses');
      container.innerHTML = '<div style="text-align:center;padding:20px;">Loading...</div>';

      try {
        const formData = new FormData();
        formData.append('action', 'search_courses');
        formData.append('search', search);

        const response = await fetch('student_course_handler.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();

        if (result.success) {
          displayCourses(result.courses);
        }
      } catch (error) {
        container.innerHTML = '<p style="color:#dc3545;">Failed to load courses</p>';
      }
    }

    function displayCourses(courses) {
      const container = document.getElementById('availableCourses');
      if (courses.length === 0) {
        container.innerHTML = '<p>No courses found</p>';
        return;
      }

      let html = '';
      courses.forEach(course => {
        const status = course.enrollment_status;
        let buttonHtml = '';
        
        if (status === 'approved') {
          buttonHtml = '<button class="btn btn-secondary" disabled>Enrolled</button>';
        } else if (status === 'pending') {
          buttonHtml = '<button class="btn" style="background:#ffc107;color:#333;" disabled>Pending Approval</button>';
        } else {
          buttonHtml = `<button class="btn btn-success" onclick="requestJoin(${course.course_id})">Request to Join</button>`;
        }
        
        html += `
          <div class="session-card">
            <div class="session-header">
              <div>
                <strong>${course.course_code} - ${course.course_name}</strong>
                <div style="color:#666;font-size:14px;">Instructor: ${course.instructor_name}</div>
              </div>
              ${buttonHtml}
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    }

    async function requestJoin(courseId) {
      try {
        const formData = new FormData();
        formData.append('action', 'request_join');
        formData.append('course_id', courseId);

        const response = await fetch('student_course_handler.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();

        if (result.success) {
          Swal.fire({icon: 'success', title: 'Request Sent!', text: result.message});
          searchCourses();
        } else {
          Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to send request'});
      }
    }

    // Load Sessions
    let allSessions = [];

    async function loadSessions() {
      const container = document.getElementById('sessions-list');
      container.innerHTML = '<div style="text-align:center;padding:20px;">Loading sessions...</div>';

      try {
        const response = await fetch('student_session_handler.php?action=list');
        const result = await response.json();

        if (result.success && result.sessions) {
          allSessions = result.sessions;
          displayAvailableSessions();
          displayPastSessions();
        } else {
          container.innerHTML = '<p>No sessions available</p>';
        }
      } catch (error) {
        container.innerHTML = '<p style="color:#dc3545;">Failed to load sessions</p>';
      }
    }

    function switchSessionTab(tab) {
      // Update button styles
      document.querySelectorAll('.session-tab').forEach(btn => {
        if (btn.dataset.tab === tab) {
          btn.classList.remove('btn-secondary');
          btn.classList.add('btn-primary', 'active');
        } else {
          btn.classList.remove('btn-primary', 'active');
          btn.classList.add('btn-secondary');
        }
      });

      // Show/hide tab content
      if (tab === 'available') {
        document.getElementById('available-sessions-tab').classList.remove('hidden');
        document.getElementById('past-sessions-tab').classList.add('hidden');
      } else {
        document.getElementById('available-sessions-tab').classList.add('hidden');
        document.getElementById('past-sessions-tab').classList.remove('hidden');
      }
    }

    function displayAvailableSessions() {
      const container = document.getElementById('sessions-list');
      // Filter: scheduled and active sessions, OR closed sessions where student hasn't marked attendance
      const availableSessions = allSessions.filter(s => 
        s.status === 'scheduled' || 
        s.status === 'active' || 
        (s.status === 'closed' && !s.attendance_status)
      );

      if (availableSessions.length === 0) {
        container.innerHTML = '<p>No available sessions at the moment</p>';
        return;
      }

      // Group sessions by course
      const groupedByCourse = {};
      availableSessions.forEach(session => {
        const key = `${session.course_code} - ${session.course_name}`;
        if (!groupedByCourse[key]) {
          groupedByCourse[key] = {
            course_id: session.course_id,
            course_code: session.course_code,
            course_name: session.course_name,
            sessions: []
          };
        }
        groupedByCourse[key].sessions.push(session);
      });

      let html = '';
      Object.keys(groupedByCourse).forEach(courseName => {
        const courseData = groupedByCourse[courseName];
        const courseId = `course-${courseData.course_id}`;
        const sessionCount = courseData.sessions.length;
        const activeCount = courseData.sessions.filter(s => s.status === 'active').length;
        
        html += `
          <div style="border: 2px solid #007bff; border-radius: 10px; margin-bottom: 15px; overflow: hidden;">
            <div style="background: #007bff; color: white; padding: 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;" onclick="toggleCourse('${courseId}')">
              <div>
                <h3 style="margin: 0; font-size: 18px;">${courseName}</h3>
                <div style="font-size: 14px; margin-top: 5px; opacity: 0.9;">
                  ${sessionCount} session${sessionCount !== 1 ? 's' : ''} available
                  ${activeCount > 0 ? ` | <strong>${activeCount} ACTIVE</strong>` : ''}
                </div>
              </div>
              <div style="font-size: 24px; transition: transform 0.3s;" id="${courseId}-icon">▼</div>
            </div>
            <div id="${courseId}" class="course-sessions" style="display: none; padding: 15px; background: #f8f9fa;">`;
        
        courseData.sessions.forEach(session => {
          html += `
            <div class="session-card" style="background: white; margin-bottom: 10px;">
              <div class="session-header">
                <div>
                  <strong>${session.session_title}</strong>
                  <div style="color:#666;font-size:14px;">
                    ${formatDate(session.session_date)} at ${session.session_time}
                    ${session.location ? ' | 📍 ' + session.location : ''}
                  </div>
                </div>
                <div>
                  <span class="status-${session.status}">${session.status.toUpperCase()}</span>
                </div>
              </div>
              ${session.status === 'active' && !session.attendance_status ? `
                <button class="btn btn-success" style="margin-top:10px; font-size: 16px; padding: 12px 24px;" onclick="openAttendanceModal(${session.session_id}, '${escapeQuotes(session.session_title)}', '${escapeQuotes(session.course_name)}')">
                  ✓ Mark Attendance Now
                </button>
              ` : ''}
              ${session.status === 'scheduled' ? `
                <div style="margin-top:10px;color:#007bff;font-size:14px;">📅 Scheduled - Not yet open for attendance</div>
              ` : ''}
              ${session.status === 'closed' && !session.attendance_status ? `
                <div style="margin-top:10px;color:#dc3545;font-size:14px;">⚠️ Session closed - You missed this session</div>
              ` : ''}
              ${session.attendance_status ? `
                <div style="margin-top:10px;color:#28a745;font-size:14px;">✓ Already marked as ${session.attendance_status}</div>
              ` : ''}
            </div>
          `;
        });
        
        html += `</div></div>`;
      });
      
      container.innerHTML = html;
    }

    function escapeQuotes(str) {
      return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function toggleCourse(courseId) {
      const courseDiv = document.getElementById(courseId);
      const icon = document.getElementById(courseId + '-icon');
      
      if (courseDiv.style.display === 'none') {
        courseDiv.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
      } else {
        courseDiv.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
      }
    }

    function displayPastSessions() {
      const container = document.getElementById('past-sessions-list');
      // Filter: sessions where student has marked attendance
      const pastSessions = allSessions.filter(s => s.attendance_status);

      if (pastSessions.length === 0) {
        container.innerHTML = '<p>No past attendance records yet</p>';
        return;
      }

      // Group by course
      const groupedByCourse = {};
      pastSessions.forEach(session => {
        const key = `${session.course_code} - ${session.course_name}`;
        if (!groupedByCourse[key]) {
          groupedByCourse[key] = {
            course_id: session.course_id,
            sessions: [],
            stats: { present: 0, absent: 0, late: 0, total: 0 }
          };
        }
        groupedByCourse[key].sessions.push(session);
        groupedByCourse[key].stats.total++;
        if (session.attendance_status === 'present') groupedByCourse[key].stats.present++;
        if (session.attendance_status === 'absent') groupedByCourse[key].stats.absent++;
        if (session.attendance_status === 'late') groupedByCourse[key].stats.late++;
      });

      let html = '';
      Object.keys(groupedByCourse).forEach(courseName => {
        const courseData = groupedByCourse[courseName];
        const percentage = Math.round((courseData.stats.present / courseData.stats.total) * 100);
        
        html += `
          <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="color: #007bff; margin-bottom: 15px;">${courseName}</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 15px; font-size: 14px;">
              <span><strong>Total Sessions:</strong> ${courseData.stats.total}</span>
              <span style="color: #28a745;"><strong>Present:</strong> ${courseData.stats.present}</span>
              <span style="color: #dc3545;"><strong>Absent:</strong> ${courseData.stats.absent}</span>
              <span style="color: #ffc107;"><strong>Late:</strong> ${courseData.stats.late}</span>
              <span style="color: #007bff;"><strong>Attendance:</strong> ${percentage}%</span>
            </div>
            <div style="background: #f8f9fa; border-radius: 5px; padding: 10px;">`;
        
        courseData.sessions.forEach(session => {
          const statusColor = session.attendance_status === 'present' ? '#28a745' : 
                             session.attendance_status === 'late' ? '#ffc107' : '#dc3545';
          html += `
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e9ecef;">
              <div>
                <strong>${formatDate(session.session_date)} @ ${session.session_time}</strong>
                ${session.location ? '<span style="color:#666;font-size:12px;"> | ' + session.location + '</span>' : ''}
              </div>
              <span style="background: ${statusColor}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                ${session.attendance_status}
              </span>
            </div>
          `;
        });
        
        html += `</div></div>`;
      });
      
      container.innerHTML = html;
    }

    function formatDate(dateStr) {
      const date = new Date(dateStr);
      const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      return `${days[date.getDay()]} ${date.getDate().toString().padStart(2, '0')}-${months[date.getMonth()]}-${date.getFullYear()}`;
    }



    function openAttendanceModal(sessionId, sessionTitle, courseName) {
      document.getElementById('session-info').textContent = `${courseName} - ${sessionTitle}`;
      document.getElementById('selected-session-id').value = sessionId;
      document.getElementById('attendanceCode').value = '';
      document.getElementById('markAttendanceModal').classList.add('active');
    }

    async function submitAttendance(e) {
      e.preventDefault();
      const code = document.getElementById('attendanceCode').value.trim().toUpperCase();

      try {
        const response = await fetch('attendance_handler.php?action=mark_by_code', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({attendance_code: code})
        });
        const result = await response.json();

        if (result.success) {
          Swal.fire({
            icon: 'success',
            title: 'Attendance Marked!',
            html: `You have been marked present for:<br><strong>${result.session_title}</strong><br>${result.course_name}`,
            confirmButtonColor: '#007bff'
          });
          closeModal('markAttendanceModal');
          loadSessions();
        } else {
          Swal.fire({icon: 'error', title: 'Error', text: result.message});
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to mark attendance'});
      }
    }

    // Load Reports
    async function loadStudentReport() {
      const courseId = document.getElementById('report-course-select').value;
      const url = courseId 
        ? `attendance_handler.php?action=student_report&course_id=${courseId}`
        : `attendance_handler.php?action=student_report`;

      try {
        const response = await fetch(url);
        const result = await response.json();

        if (result.success) {
          let html = '';
          
          if (courseId) {
            const stats = result.statistics;
            html += `<div class="stats">
              <div class="stat-card"><div class="stat-value">${stats.total_sessions}</div><div class="stat-label">Total Sessions</div></div>
              <div class="stat-card"><div class="stat-value">${stats.present_count}</div><div class="stat-label">Present</div></div>
              <div class="stat-card"><div class="stat-value">${stats.late_count}</div><div class="stat-label">Late</div></div>
              <div class="stat-card"><div class="stat-value">${stats.absent_count}</div><div class="stat-label">Absent</div></div>
              <div class="stat-card"><div class="stat-value">${stats.attendance_percentage}%</div><div class="stat-label">Attendance Rate</div></div>
            </div>`;
            
            html += '<h3>Session History</h3><table><thead><tr><th>Date</th><th>Time</th><th>Session</th><th>Status</th></tr></thead><tbody>';
            result.sessions.forEach(s => {
              html += `<tr><td>${s.session_date}</td><td>${s.session_time}</td><td>${s.session_title}</td><td><span class="status-${s.status}">${s.status}</span></td></tr>`;
            });
            html += '</tbody></table>';
          } else {
            html += '<h3>All Courses Overview</h3><table><thead><tr><th>Course</th><th>Total Sessions</th><th>Present</th><th>Late</th><th>Absent</th><th>Attendance %</th></tr></thead><tbody>';
            result.courses.forEach(c => {
              html += `<tr><td>${c.course_code} - ${c.course_name}</td><td>${c.total_sessions}</td><td>${c.present_count}</td><td>${c.late_count}</td><td>${c.absent_count}</td><td><strong>${c.attendance_percentage}%</strong></td></tr>`;
            });
            html += '</tbody></table>';
          }
          
          document.getElementById('report-content').innerHTML = html;
        }
      } catch (error) {
        Swal.fire({icon: 'error', title: 'Error', text: 'Failed to load report'});
      }
    }
  </script>
</body>
</html>