sql
-- ============================================================
-- CLASS MANAGEMENT SYSTEM
-- HOSTING-READY DATABASE
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- ADMIN TABLE
-- ============================================================

DROP TABLE IF EXISTS `admin`;

CREATE TABLE `admin` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- ============================================================
-- STUDENTS TABLE
-- ============================================================

DROP TABLE IF EXISTS `students`;

CREATE TABLE `students` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `roll_no` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(15) DEFAULT NULL,
    `gender` VARCHAR(10) DEFAULT NULL,
    `class_name` VARCHAR(50) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_roll_no` (`roll_no`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- ============================================================
-- TEACHERS TABLE
-- ============================================================

DROP TABLE IF EXISTS `teachers`;

CREATE TABLE `teachers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(15) DEFAULT NULL,
    `subject` VARCHAR(100) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- ============================================================
-- SUBJECTS TABLE
-- ============================================================

DROP TABLE IF EXISTS `subjects`;

CREATE TABLE `subjects` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `subject_name` VARCHAR(100) NOT NULL,
    `subject_code` VARCHAR(20) NOT NULL,
    `teacher_name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_subject_code` (`subject_code`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- ============================================================
-- ATTENDANCE TABLE
-- ============================================================

DROP TABLE IF EXISTS `attendance`;

CREATE TABLE `attendance` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `student_name` VARCHAR(100) NOT NULL,
    `attendance_date` DATE NOT NULL,
    `status` ENUM('Present','Absent') NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_attendance_student` (`student_id`),
    KEY `idx_attendance_date` (`attendance_date`),
    CONSTRAINT `attendance_ibfk_1`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- ============================================================
-- MARKS TABLE
-- ============================================================

DROP TABLE IF EXISTS `marks`;

CREATE TABLE `marks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `student_name` VARCHAR(100) NOT NULL,
    `subject_name` VARCHAR(100) NOT NULL,
    `marks` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_marks_student` (`student_id`),
    KEY `idx_marks_subject` (`subject_name`),
    CONSTRAINT `marks_ibfk_1`
        FOREIGN KEY (`student_id`)
        REFERENCES `students` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- ============================================================
-- SAMPLE ADMIN ACCOUNT
-- ============================================================
-- Username: admin
-- Password: admin123
--
-- IMPORTANT:
-- Change this password after deployment.
-- The password below is a bcrypt hash.
-- ============================================================

INSERT INTO `admin` (`username`, `password`)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC1dL6qfQjY7wKqX4K6'
);


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATABASE SETUP COMPLETE
-- ============================================================