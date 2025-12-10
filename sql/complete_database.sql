-- phpMyAdmin SQL Dump
-- Complete Attendance Management System Database
-- Database: `attendancemanagement`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


-- Database: `attendancemanagement`
--
CREATE DATABASE IF NOT EXISTS `attendancemanagement` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `attendancemanagement`;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_users`
--

DROP TABLE IF EXISTS `attendance_users`;
CREATE TABLE `attendance_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','faculty','fi','admin') NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(20) DEFAULT NULL,
  `course_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `credit_hours` int(11) DEFAULT NULL,
  `faculty_id` int(11) NOT NULL,
  PRIMARY KEY (`course_id`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `attendance_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `description`, `credit_hours`, `faculty_id`) VALUES
(28, 'CS205', 'Introduction to C++', 'hey', 4, 1),
(29, 'CS202', 'Objected Oriented Programming', NULL, NULL, 1),
(30, 'CS202', 'Objected Oriented Programming', NULL, NULL, 1),
(31, 'CS202', 'Objected Oriented Programming', 'This course is very crucial', 3, 2),
(33, 'C57', 'Computer Science', NULL, 3, 10);


DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  KEY `idx_student_status` (`student_id`,`status`),
  KEY `idx_course_status` (`course_id`,`status`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `attendance_users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `session_title` varchar(100) NOT NULL,
  `session_description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `attendance_code` varchar(10) NOT NULL,
  `code_expiry` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('scheduled','active','closed') DEFAULT 'scheduled',
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `attendance_code` (`attendance_code`),
  KEY `created_by` (`created_by`),
  KEY `idx_course_date` (`course_id`,`session_date`),
  KEY `idx_attendance_code` (`attendance_code`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  CONSTRAINT `sessions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `attendance_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('present','absent','late','excused') DEFAULT 'absent',
  `marked_at` timestamp NULL DEFAULT NULL,
  `marked_by` enum('student','faculty','system') DEFAULT 'system',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `unique_session_student` (`session_id`,`student_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `attendance_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Optional: Legacy tables (can be removed if not needed)
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('present','absent','late') NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`attendance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `faculty`;
CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `course_student_list`;
CREATE TABLE `course_student_list` (
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  PRIMARY KEY (`course_id`,`student_id`),
  KEY `course_id` (`course_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `course_student_list_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  CONSTRAINT `course_student_list_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `attendance_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
