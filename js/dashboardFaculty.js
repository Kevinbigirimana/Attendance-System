// Get modal elements
const addModal = document.getElementById('addCourseModal');
const editModal = document.getElementById('editCourseModal');
const requestsModal = document.getElementById('requestsModal');
const courseRequestsModal = document.getElementById('courseRequestsModal');
const enrolledStudentsModal = document.getElementById('enrolledStudentsModal');

const addBtn = document.getElementById('addCourseBtn');
const cancelAddBtn = document.getElementById('cancelAddBtn');
const cancelEditBtn = document.getElementById('cancelEditBtn');
const viewRequestsBtn = document.getElementById('viewRequestsBtn');
const cancelRequestsBtn = document.getElementById('cancelRequestsBtn');
const cancelCourseRequestsBtn = document.getElementById('cancelCourseRequestsBtn');
const cancelEnrolledStudentsBtn = document.getElementById('cancelEnrolledStudentsBtn');

const addForm = document.getElementById('addCourseForm');
const editForm = document.getElementById('editCourseForm');

// Open add course modal
addBtn.addEventListener('click', () => {
    addModal.style.display = 'flex';
});

// Close modals
cancelAddBtn.addEventListener('click', () => {
    addModal.style.display = 'none';
    addForm.reset();
});

cancelEditBtn.addEventListener('click', () => {
    editModal.style.display = 'none';
    editForm.reset();
});

cancelRequestsBtn.addEventListener('click', () => {
    requestsModal.style.display = 'none';
});

cancelCourseRequestsBtn.addEventListener('click', () => {
    courseRequestsModal.style.display = 'none';
});

cancelEnrolledStudentsBtn.addEventListener('click', () => {
    enrolledStudentsModal.style.display = 'none';
});

// View all enrollment requests
viewRequestsBtn.addEventListener('click', () => {
    loadAllRequests();
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    if (e.target === addModal) {
        addModal.style.display = 'none';
        addForm.reset();
    }
    if (e.target === editModal) {
        editModal.style.display = 'none';
        editForm.reset();
    }
    if (e.target === requestsModal) {
        requestsModal.style.display = 'none';
    }
    if (e.target === courseRequestsModal) {
        courseRequestsModal.style.display = 'none';
    }
    if (e.target === enrolledStudentsModal) {
        enrolledStudentsModal.style.display = 'none';
    }
});

// Add course form submission
addForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = new FormData(addForm);
    
    fetch('add_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the course');
    });
});

// Edit course form submission
editForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = new FormData(editForm);
    
    fetch('edit_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the course');
    });
});

// Edit course function
function editCourse(courseId, courseCode, courseName, description, creditHours) {
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_course_code').value = courseCode;
    document.getElementById('edit_course_name').value = courseName;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_credit_hours').value = creditHours;
    
    editModal.style.display = 'flex';
}

// Delete course function
function deleteCourse(courseId, courseCode) {
    if (!confirm(`Are you sure you want to delete course ${courseCode}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('course_id', courseId);
    
    fetch('delete_course.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the course');
    });
}

// Load all enrollment requests
function loadAllRequests() {
    const formData = new FormData();
    formData.append('action', 'get_pending_requests');
    
    fetch('faculty_requests_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAllRequests(data.requests);
            requestsModal.style.display = 'flex';
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load requests');
    });
}

// Display all enrollment requests
function displayAllRequests(requests) {
    const requestsList = document.getElementById('requestsList');
    
    if (requests.length === 0) {
        requestsList.innerHTML = '<p style="text-align: center; color: #666;">No pending requests</p>';
        return;
    }
    
    let html = '<div style="max-height: 400px; overflow-y: auto;">';
    
    requests.forEach(request => {
        html += `
            <div class="request-item">
                <div class="request-info">
                    <h4>${request.student_name}</h4>
                    <p><strong>Email:</strong> ${request.student_email}</p>
                    <p><strong>Course:</strong> ${request.course_code} - ${request.course_name}</p>
                    <p><strong>Request Date:</strong> ${new Date(request.request_date).toLocaleDateString()}</p>
                </div>
                <div class="request-actions">
                    <button class="approve-btn" onclick="approveRequest(${request.enrollment_id})">Approve</button>
                    <button class="reject-btn" onclick="rejectRequest(${request.enrollment_id})">Reject</button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    requestsList.innerHTML = html;
}

// View course-specific requests
function viewCourseRequests(courseId, courseCode) {
    const formData = new FormData();
    formData.append('action', 'get_pending_requests');
    formData.append('course_id', courseId);
    
    fetch('faculty_requests_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('courseRequestsTitle').textContent = 
                `Enrollment Requests for ${courseCode}`;
            displayCourseRequests(data.requests);
            courseRequestsModal.style.display = 'flex';
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load course requests');
    });
}

// Display course-specific requests
function displayCourseRequests(requests) {
    const courseRequestsList = document.getElementById('courseRequestsList');
    
    if (requests.length === 0) {
        courseRequestsList.innerHTML = '<p style="text-align: center; color: #666;">No pending requests for this course</p>';
        return;
    }
    
    let html = '<div style="max-height: 400px; overflow-y: auto;">';
    
    requests.forEach(request => {
        html += `
            <div class="request-item">
                <div class="request-info">
                    <h4>${request.student_name}</h4>
                    <p><strong>Email:</strong> ${request.student_email}</p>
                    <p><strong>Request Date:</strong> ${new Date(request.request_date).toLocaleDateString()}</p>
                </div>
                <div class="request-actions">
                    <button class="approve-btn" onclick="approveRequest(${request.enrollment_id}, true)">Approve</button>
                    <button class="reject-btn" onclick="rejectRequest(${request.enrollment_id}, true)">Reject</button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    courseRequestsList.innerHTML = html;
}

// Approve enrollment request
function approveRequest(enrollmentId, isCourseSpecific = false) {
    if (!confirm('Are you sure you want to approve this request?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'approve_request');
    formData.append('enrollment_id', enrollmentId);
    
    fetch('faculty_requests_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            // Reload the appropriate modal
            if (isCourseSpecific) {
                // Re-fetch course-specific requests
                const courseId = document.getElementById('courseRequestsTitle').textContent.match(/\d+/);
                if (courseId) {
                    location.reload(); // Simpler approach: reload page
                }
            } else {
                loadAllRequests();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to approve request');
    });
}

// Reject enrollment request
function rejectRequest(enrollmentId, isCourseSpecific = false) {
    if (!confirm('Are you sure you want to reject this request?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reject_request');
    formData.append('enrollment_id', enrollmentId);
    
    fetch('faculty_requests_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            // Reload the appropriate modal
            if (isCourseSpecific) {
                location.reload();
            } else {
                loadAllRequests();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to reject request');
    });
}

// View enrolled students for a course
function viewEnrolledStudents(courseId, courseCode) {
    const formData = new FormData();
    formData.append('action', 'get_enrolled_students');
    formData.append('course_id', courseId);
    
    fetch('faculty_requests_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('enrolledStudentsTitle').textContent = 
                `Enrolled Students - ${courseCode}`;
            displayEnrolledStudents(data.students);
            enrolledStudentsModal.style.display = 'flex';
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to load enrolled students');
    });
}

// Display enrolled students
function displayEnrolledStudents(students) {
    const enrolledStudentsList = document.getElementById('enrolledStudentsList');
    
    if (students.length === 0) {
        enrolledStudentsList.innerHTML = '<p style="text-align: center; color: #666;">No students enrolled yet</p>';
        return;
    }
    
    let html = '<div style="max-height: 400px; overflow-y: auto;">';
    html += '<table style="width: 100%; border-collapse: collapse;">';
    html += '<thead><tr style="background-color: #f8f9fa;">';
    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Student Name</th>';
    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Email</th>';
    html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Enrolled Date</th>';
    html += '</tr></thead><tbody>';
    
    students.forEach(student => {
        html += '<tr style="border-bottom: 1px solid #dee2e6;">';
        html += '<td style="padding: 10px;">' + student.student_name + '</td>';
        html += '<td style="padding: 10px;">' + student.student_email + '</td>';
        html += '<td style="padding: 10px;">' + new Date(student.approval_date).toLocaleDateString() + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    html += '<p style="margin-top: 15px; font-weight: bold;">Total Students: ' + students.length + '</p>';
    html += '</div>';
    
    enrolledStudentsList.innerHTML = html;
}