# Attendance Management System

A comprehensive web-based attendance tracking system built with PHP, MySQL, and JavaScript. Supports faculty, faculty interns, and students with role-based access control.

## 🎯 Features

### For Faculty & Faculty Interns (FI)
- ✅ **Course Management**: Create, edit, and delete courses
- ✅ **Student Management**: Add/remove students, approve/reject enrollment requests
- ✅ **Session Management**: Create class sessions with auto-generated attendance codes
- ✅ **Attendance Tracking**: Mark attendance manually or let students self-mark with codes
- ✅ **Reporting**: View detailed attendance statistics and reports

### For Students
- ✅ **Course Enrollment**: Browse and request to join courses
- ✅ **Attendance Marking**: Mark attendance using time-limited codes
- ✅ **Progress Tracking**: View daily and overall attendance reports
- ✅ **Course Overview**: Access enrolled courses and instructor information

## 🛠️ Technical Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Libraries**: SweetAlert2 for notifications
- **Server**: Apache (XAMPP)

## 📋 Prerequisites

- XAMPP or similar PHP development environment
- MySQL Database
- Web browser (Chrome, Firefox, Edge)
- Basic understanding of PHP and MySQL

## 🚀 Installation

### 1. Clone/Download Project
```bash
# Place in XAMPP htdocs directory
C:\xampp\htdocs\Attendance\
```

### 2. Database Setup
```sql
-- Option A: Import complete database with sample data
-- In phpMyAdmin, import: sql/complete_database.sql

-- Option B: Manual setup
CREATE DATABASE attendancemanagement;
USE attendancemanagement;
-- Then import sql/complete_database.sql
```

### 3. Configure Database Connection
Edit `env/connect.env`:
```env
servername=localhost
username=root
password=
dbname=attendancemanagement
```

### 4. Start Services
- Start Apache in XAMPP Control Panel
- Start MySQL in XAMPP Control Panel

### 5. Access Application
Open browser and navigate to:
```
http://localhost/Attendance/html/login.html
```

## 👥 User Roles & Permissions

| Role | Create Courses | Approve Students | Create Sessions | Mark Attendance | View Reports |
|------|---------------|------------------|-----------------|-----------------|--------------|
| **Faculty** | ✅ | ✅ | ✅ | ✅ (Manual) | ✅ (All students) |
| **Faculty Intern (FI)** | ✅ | ✅ | ✅ | ✅ (Manual) | ✅ (All students) |
| **Student** | ❌ | ❌ | ❌ | ✅ (Via code) | ✅ (Own only) |

## 📊 Database Schema

### Main Tables

#### `attendance_users`
```sql
user_id, first_name, last_name, email, password_hash, role
Roles: 'student', 'faculty', 'fi', 'admin'
```

#### `courses`
```sql
course_id, course_code, course_name, description, credit_hours, faculty_id
```

#### `enrollments`
```sql
enrollment_id, student_id, course_id, status, request_date, approval_date
Status: 'pending', 'approved', 'rejected'
```

#### `sessions`
```sql
session_id, course_id, session_date, session_time, session_title, 
session_description, location, attendance_code, code_expiry, created_by, status
Status: 'scheduled', 'active', 'closed'
```

#### `attendance_records`
```sql
attendance_id, session_id, student_id, status, marked_at, marked_by, notes
Status: 'present', 'absent', 'late', 'excused'
Marked by: 'student', 'faculty', 'system'
```

## 🔐 Security Features

- ✅ **Password Hashing**: Using PHP `password_hash()` and `password_verify()`
- ✅ **SQL Injection Prevention**: Prepared statements throughout
- ✅ **Session Management**: Secure session handling with `session_config.php`
- ✅ **Role-based Access Control**: Authorization checks on every endpoint
- ✅ **Concurrent User Support**: Multiple users can login simultaneously
- ✅ **XSS Prevention**: HTML escaping with `htmlspecialchars()`

## 📁 Project Structure

```
Attendance/
├── php/                          # Backend PHP files
│   ├── session_config.php        # Session management configuration
│   ├── auth_check.php            # Authentication middleware
│   ├── database.php              # Database connection
│   ├── login.php                 # User authentication
│   ├── register.php              # User registration
│   ├── logout.php                # Logout handler
│   ├── dashboardFaculty.php      # Faculty dashboard
│   ├── fiDashboard.php           # Faculty Intern dashboard
│   ├── studentDashboard.php      # Student dashboard
│   ├── add_course.php            # Course creation endpoint
│   ├── edit_course.php           # Course editing endpoint
│   ├── delete_course.php         # Course deletion endpoint
│   ├── student_course_handler.php # Student enrollment handler
│   ├── faculty_requests_handler.php # Approval/removal handler
│   ├── session_handler.php       # Session management handler
│   └── attendance_handler.php    # Attendance marking/reports
├── js/                           # Frontend JavaScript
│   ├── dashboardFaculty.js       # Faculty dashboard logic
│   ├── studentDashboard.js       # Student dashboard logic
│   └── logout.js                 # Logout with SweetAlert
├── css/                          # Stylesheets
│   ├── dashboardFI.css
│   ├── dashboardFaculty.css
│   ├── studentDashboard.css
│   └── login.css
├── html/                         # HTML pages
│   ├── login.html
│   └── register.html
├── sql/                          # Database scripts
│   └── complete_database.sql     # Complete schema with sample data
├── env/                          # Configuration
│   └── connect.env               # Database credentials
└── TESTING_GUIDE.md              # Comprehensive testing guide
```

## 🔄 Workflow Example

### Complete Attendance Cycle

1. **Faculty/FI creates course** → "CS301 - Database Systems"
2. **Student requests to join** → Enrollment status: "pending"
3. **Faculty/FI approves request** → Enrollment status: "approved"
4. **Faculty/FI creates session** → Auto-generates code: "AB3K7M"
5. **Faculty/FI activates session** → Students can now mark attendance
6. **Student enters code** → Attendance marked: "present"
7. **Faculty/FI closes session** → Code expires, final records saved
8. **Reports available** → Both faculty and student can view statistics

## 📱 API Endpoints

### Student Endpoints
```php
POST student_course_handler.php?action=search_courses    // Search courses
POST student_course_handler.php?action=request_join      // Request enrollment
POST attendance_handler.php?action=mark_by_code          // Mark attendance
GET  attendance_handler.php?action=student_report        // View reports
```

### Faculty/FI Endpoints
```php
POST add_course.php                                       // Create course
POST edit_course.php                                      // Edit course
POST delete_course.php                                    // Delete course
POST faculty_requests_handler.php?action=get_pending_requests   // View requests
POST faculty_requests_handler.php?action=approve_request        // Approve student
POST faculty_requests_handler.php?action=reject_request         // Reject student
POST student_course_handler.php?action=remove_student           // Remove student
POST session_handler.php?action=create                   // Create session
POST session_handler.php?action=update_status            // Activate/close session
POST attendance_handler.php?action=mark_manual           // Manual marking
GET  attendance_handler.php?action=course_report         // Course reports
```

## 🧪 Testing

See [TESTING_GUIDE.md](TESTING_GUIDE.md) for comprehensive testing scenarios.

### Quick Test
1. Register as Faculty: `http://localhost/Attendance/html/register.html`
2. Login and create a course
3. Register as Student (different browser/incognito)
4. Request to join the course
5. Switch to Faculty, approve request
6. Create a session and activate it
7. Switch to Student, mark attendance with code
8. View reports in both dashboards

## 🐛 Common Issues & Solutions

### Issue: Session warnings on page refresh
**Solution**: All PHP files now use `session_config.php`. Restart Apache.

### Issue: Multiple users can't login simultaneously
**Solution**: Session configuration updated. Clear browser cache and restart Apache.

### Issue: "Access denied" errors
**Solution**: 
- Verify role in database matches expected role
- Check that `auth_check.php` is included in protected pages
- Clear sessions: Delete files in `C:\xampp\tmp\`

### Issue: Attendance code not working
**Solution**:
- Ensure session is "active" (not "scheduled" or "closed")
- Check code hasn't expired
- Verify student is enrolled with "approved" status

## 📈 Features Roadmap

### Implemented ✅
- [x] User authentication and registration
- [x] Role-based access control
- [x] Course management (CRUD)
- [x] Student enrollment workflow
- [x] Session creation with attendance codes
- [x] Attendance marking (code-based and manual)
- [x] Attendance reports and statistics
- [x] Concurrent user sessions
- [x] SweetAlert notifications

### Future Enhancements 🚧
- [ ] Email notifications for approvals/rejections
- [ ] Export reports to PDF/Excel
- [ ] QR code-based attendance
- [ ] Mobile app version
- [ ] Attendance analytics dashboard
- [ ] Parent/guardian portal
- [ ] Integration with LMS systems
- [ ] Biometric attendance option

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 Sample Data

The `complete_database.sql` includes sample data:

**Faculty Users:**
- Kevin Bigirimana (kevinbigirimana3@gmail.com)
- Chadia Chacha (chadia@gmail.com)
- Nobel Bitanagimana (nobel@gmail.com)

**Faculty Intern:**
- Faculty Intern (intern@gmail.com)

**Students:**
- Student User (student@gmail.com)
- Maryam M (k@gmail.com)
- Hoo Haa (hoo@gmail.com)

**Sample Courses:**
- CS205 - Introduction to C++
- CS202 - Object Oriented Programming
- C57 - Computer Science

## 🔧 Configuration

### Session Configuration (`php/session_config.php`)
```php
ini_set('session.cookie_lifetime', 86400);     // 24 hours
ini_set('session.gc_maxlifetime', 86400);      // 24 hours
ini_set('session.use_strict_mode', 1);         // Security
ini_set('session.cookie_httponly', 1);         // Prevent XSS
ini_set('session.use_only_cookies', 1);        // Cookie-only sessions
```

### Database Connection (`php/database.php`)
```php
$conn = new mysqli(
    $env['servername'],  // localhost
    $env['username'],    // root
    $env['password'],    // (empty for XAMPP)
    $env['dbname']       // attendancemanagement
);
```

## 📞 Support

For issues, questions, or contributions:
- Create an issue in the repository
- Contact project maintainer
- Check TESTING_GUIDE.md for troubleshooting

## 📄 License

This project is developed for educational purposes.

## 🙏 Acknowledgments

- SweetAlert2 for beautiful notifications
- XAMPP for local development environment
- PHP and MySQL communities for documentation

---

**Built with ❤️ for efficient attendance management**

Last Updated: December 2025
Version: 1.0.0
