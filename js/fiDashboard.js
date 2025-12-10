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
        confirmButtonColor: '#bc1a25'
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
        confirmButtonColor: '#bc1a25'
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
        const currentStatus = record.status || 'not-marked';
        const displayStatus = record.status || 'Not Marked';
        const statusClass = record.status ? `status-${record.status}` : 'status-scheduled';
        
        html += `<tr>
          <td>${record.student_name}</td>
          <td>${record.student_email}</td>
          <td><span class="${statusClass}">${displayStatus.toUpperCase()}</span></td>
          <td>
            <select onchange="updateAttendance(${sessionId}, ${record.student_id}, this.value)">
              <option value="">--Change Status--</option>
              <option value="present" ${currentStatus === 'present' ? 'selected' : ''}>Present</option>
              <option value="absent" ${currentStatus === 'absent' ? 'selected' : ''}>Absent</option>
              <option value="late" ${currentStatus === 'late' ? 'selected' : ''}>Late</option>
              <option value="excused" ${currentStatus === 'excused' ? 'selected' : ''}>Excused</option>
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

// View course report in modal
async function viewCourseReport(courseId, courseName) {
  try {
    const response = await fetch(`attendance_handler.php?action=course_report&course_id=${courseId}`);
    const result = await response.json();
    
    if (result.success) {
      let html = `<h2>Attendance Report - ${courseName}</h2>`;
      html += '<table style="width:100%"><thead><tr><th>Student</th><th>Email</th><th>Total Sessions</th><th>Present</th><th>Late</th><th>Absent</th><th>Attendance %</th></tr></thead><tbody>';
      
      if (result.students && result.students.length > 0) {
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
      } else {
        html += '<tr><td colspan="7">No attendance data available</td></tr>';
      }
      
      html += '</tbody></table>';
      
      Swal.fire({
        title: 'Course Report',
        html: html,
        width: '90%',
        confirmButtonColor: '#007bff',
        confirmButtonText: 'Close'
      });
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
        confirmButtonColor: '#bc1a25'
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
              <button class="btn btn-sm btn-info" onclick="viewStudentPastSchedule(${student.student_id}, '${student.student_name}', ${courseId}, '${courseName}')" style="margin-right:5px;">📅 Past Schedule</button>
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
async function viewStudentPastSchedule(studentId, studentName, courseId, courseName) {
  try {
    const response = await fetch(`attendance_handler.php?action=student_past_schedule&student_id=${studentId}&course_id=${courseId}`);
    const result = await response.json();
    
    if (result.success) {
      const stats = result.statistics;
      const sessions = result.sessions;
      
      let html = `
        <div style="margin-bottom: 20px;">
          <h3 style="color: #007bff; margin-bottom: 10px;">${studentName} - ${courseName}</h3>
          <div style="display: flex; gap: 10px; margin-bottom: 15px; font-size: 14px; flex-wrap: wrap;">
            <span><strong>Total Sessions:</strong> ${stats.total}</span>
            <span style="color: #28a745;"><strong>Present:</strong> ${stats.present}</span>
            <span style="color: #ffc107;"><strong>Late:</strong> ${stats.late}</span>
            <span style="color: #6c757d;"><strong>Excused:</strong> ${stats.excused}</span>
            <span style="color: #007bff;"><strong>Attendance:</strong> ${stats.percentage}%</span>
          </div>
        </div>`;
      
      if (sessions.length === 0) {
        html += '<p style="color: #666;">No attendance records found for this student in this course.</p>';
      } else {
        html += '<div style="background: #f8f9fa; border-radius: 5px; padding: 15px; max-height: 400px; overflow-y: auto;">';
        
        sessions.forEach(session => {
          const statusColor = session.attendance_status === 'present' ? '#28a745' : 
                             session.attendance_status === 'late' ? '#ffc107' : 
                             session.attendance_status === 'excused' ? '#6c757d' : '#dc3545';
          const date = new Date(session.session_date);
          const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
          const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
          const formattedDate = `${days[date.getDay()]} ${date.getDate().toString().padStart(2, '0')}-${months[date.getMonth()]}-${date.getFullYear()}`;
          
          html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e9ecef;">
              <div style="flex: 1;">
                <strong>${formattedDate} @ ${session.session_time}</strong>
                ${session.session_title ? '<div style="color:#666;font-size:13px;">' + session.session_title + '</div>' : ''}
                ${session.location ? '<span style="color:#666;font-size:12px;">📍 ' + session.location + '</span>' : ''}
              </div>
              <span style="background: ${statusColor}; color: white; padding: 5px 15px; border-radius: 15px; font-size: 12px; font-weight: bold; text-transform: uppercase;">
                ${session.attendance_status}
              </span>
            </div>
          `;
        });
        
        html += '</div>';
      }
      
      Swal.fire({
        title: '📅 Past Schedule',
        html: html,
        width: '700px',
        confirmButtonColor: '#007bff',
        confirmButtonText: 'Close'
      });
    } else {
      Swal.fire({icon: 'error', title: 'Error', text: result.message});
    }
  } catch (error) {
    Swal.fire({icon: 'error', title: 'Error', text: 'Failed to load past schedule'});
  }
}
