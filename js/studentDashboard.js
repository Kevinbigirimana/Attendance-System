// Get modal elements
const joinModal = document.getElementById('joinCourseModal');
const joinBtn = document.getElementById('joinCourseBtn');
const cancelJoinBtn = document.getElementById('cancelJoinBtn');
const searchBtn = document.getElementById('searchBtn');
const courseSearch = document.getElementById('courseSearch');
const availableCourses = document.getElementById('availableCourses');

// Open join course modal and load all courses
joinBtn.addEventListener('click', () => {
    joinModal.style.display = 'flex';
    loadAllCourses(''); // Load all courses when modal opens
});

// Close modal
cancelJoinBtn.addEventListener('click', () => {
    joinModal.style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    if (e.target === joinModal) {
        joinModal.style.display = 'none';
    }
});

// Search courses button
searchBtn.addEventListener('click', () => {
    const searchTerm = courseSearch.value.trim();
    loadAllCourses(searchTerm);
});

// Search on Enter key
courseSearch.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        const searchTerm = courseSearch.value.trim();
        loadAllCourses(searchTerm);
    }
});

// Function to load all available courses
function loadAllCourses(searchTerm) {
    availableCourses.innerHTML = '<div class="loading">Loading courses...</div>';
    
    const formData = new FormData();
    formData.append('action', 'search_courses');
    formData.append('search', searchTerm);
    
    fetch('student_course_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Received data:', data); // Debug log
        if (data.success) {
            displayCourses(data.courses);
        } else {
            availableCourses.innerHTML = '<p style="text-align: center; color: #dc3545;">Error: ' + data.message + '</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        availableCourses.innerHTML = '<p style="text-align: center; color: #dc3545;">Failed to load courses. Please try again.</p>';
    });
}

// Function to display courses
function displayCourses(courses) {
    if (!courses || courses.length === 0) {
        availableCourses.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No courses available at this time.</p>';
        return;
    }
    
    let html = '';
    
    courses.forEach(course => {
        const status = course.enrollment_status;
        let buttonHtml = '';
        let statusText = '';
        
        if (status === 'approved') {
            buttonHtml = '<button class="btn-enrolled" disabled>Already Enrolled</button>';
            statusText = '<span style="color: #28a745; font-weight: bold;">✓ Enrolled</span>';
        } else if (status === 'pending') {
            buttonHtml = '<button class="btn-pending" disabled>Request Pending</button>';
            statusText = '<span style="color: #ffc107; font-weight: bold;">⏳ Pending Approval</span>';
        } else if (status === 'rejected') {
            buttonHtml = '<button class="btn-rejected" disabled>Request Rejected</button>';
            statusText = '<span style="color: #dc3545; font-weight: bold;">✗ Rejected</span>';
        } else {
            buttonHtml = '<button class="btn-join" onclick="requestJoinCourse(' + course.course_id + ', \'' + course.course_code.replace(/'/g, "\\'") + '\')">Request to Join</button>';
            statusText = '';
        }
        
        html += `
            <div class="course-card">
                <h4>${course.course_code} - ${course.course_name}</h4>
                <p><strong>Instructor:</strong> ${course.instructor_name}</p>
                <p><strong>Credit Hours:</strong> ${course.credit_hours || 'N/A'}</p>
                <p style="color: #888; font-style: italic;">${course.description || 'No description available'}</p>
                ${statusText ? '<p>' + statusText + '</p>' : ''}
                <div class="course-actions">
                    ${buttonHtml}
                </div>
            </div>
        `;
    });
    
    availableCourses.innerHTML = html;
}

// Function to request join course
function requestJoinCourse(courseId, courseCode) {
    if (!confirm('Do you want to request to join ' + courseCode + '?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'request_join');
    formData.append('course_id', courseId);
    
    fetch('student_course_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            // Refresh the course list to show updated status
            const searchTerm = courseSearch.value.trim();
            loadAllCourses(searchTerm);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send request. Please try again.');
    });
}

// Placeholder functions for other buttons
function viewCourse(courseId) {
    alert('View course details for course ID: ' + courseId);
    // TODO: Implement course details view
}

function markAttendance(courseId) {
    alert('Mark attendance for course ID: ' + courseId);
    // TODO: Implement attendance marking
}