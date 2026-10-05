
CREATE DATABASE IF NOT EXISTS class_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE class_management;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS marks;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS admin;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- ADMIN
-- --------------------------------------------------------

CREATE TABLE admin (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

INSERT INTO admin (id, username, password) VALUES
(1, 'admin', '$2y$10$0zrlw7YXrJxdKI5sz5tnku7lZg1DmBAz8dADa7ydrK1YKc2h5vhLO');

-- --------------------------------------------------------
-- STUDENTS
-- --------------------------------------------------------

CREATE TABLE students (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    roll_no VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    gender VARCHAR(10) DEFAULT NULL,
    class_name VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_roll_no (roll_no)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

INSERT INTO students
    (id, roll_no, name, email, phone, gender, class_name)
VALUES
    (2, '56', 'aditya', 'xyz56@gmail.com', '6785467', 'Male', 'bscit'),
    (3, '103', 'rutik', 'rutik13@gmail.com', '9807654', 'Male', 'bscit'),
    (5, '21', 'vishal', 'vishal21@gmail.com', '2134567892', 'Male', 'bscit'),
    (6, '435', 'aditi khamkar', 'aditikhamkar007@gmail.com', '9029421404', 'Female', '12');

-- --------------------------------------------------------
-- TEACHERS
-- --------------------------------------------------------

CREATE TABLE teachers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teachers_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

INSERT INTO teachers
    (id, name, email, phone, subject)
VALUES
    (2, 'Rahul Sharma', 'rahul@gmail.com', '9876543210', 'Web Programming'),
    (3, 'smita parab', 'smitaparab@gmail.com', '9088664312', 'AWP');

-- --------------------------------------------------------
-- SUBJECTS
-- --------------------------------------------------------

CREATE TABLE subjects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    teacher_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subject_name (subject_name),
    UNIQUE KEY uq_subject_code (subject_code)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

INSERT INTO subjects
    (id, subject_name, subject_code, teacher_name)
VALUES
    (1, 'java', 'java101', 'surya'),
    (2, 'python', 'py12', 'suvarna'),
    (3, 'AWP', 'awp2', 'smita parab');

-- --------------------------------------------------------
-- ATTENDANCE
-- --------------------------------------------------------

CREATE TABLE attendance (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent') NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attendance_student_id (student_id),
    KEY idx_attendance_date (attendance_date),
    UNIQUE KEY uq_student_attendance_date (student_id, attendance_date),
    CONSTRAINT fk_attendance_student
        FOREIGN KEY (student_id)
        REFERENCES students (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

INSERT INTO attendance
    (id, student_id, student_name, attendance_date, status)
VALUES
    (1, 2, 'aditya', '2007-03-18', 'Present'),
    (3, 2, 'aditya', '2026-09-26', 'Present'),
    (5, 6, 'aditi khamkar', '2026-09-29', 'Present');

-- --------------------------------------------------------
-- MARKS
-- --------------------------------------------------------

CREATE TABLE marks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    marks INT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_marks_student_id (student_id),
    UNIQUE KEY uq_student_subject (student_id, subject_name),
    CONSTRAINT fk_marks_student
        FOREIGN KEY (student_id)
        REFERENCES students (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_marks_range
        CHECK (marks >= 0 AND marks <= 100)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

INSERT INTO marks
    (id, student_id, student_name, subject_name, marks)
VALUES
    (1, 2, 'aditya', 'java', 66),
    (3, 3, 'rutik', 'java', 90),
    (4, 5, 'vishal', 'java', 23),
    (5, 6, 'aditi khamkar', 'AWP', 80);

-- --------------------------------------------------------
-- AUTO_INCREMENT VALUES
-- --------------------------------------------------------

ALTER TABLE admin
    AUTO_INCREMENT = 2;

ALTER TABLE students
    AUTO_INCREMENT = 7;

ALTER TABLE teachers
    AUTO_INCREMENT = 4;

ALTER TABLE subjects
    AUTO_INCREMENT = 4;

ALTER TABLE attendance
    AUTO_INCREMENT = 6;

ALTER TABLE marks
    AUTO_INCREMENT = 6;