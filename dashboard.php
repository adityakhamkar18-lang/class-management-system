<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['admin']) || $_SESSION['admin'] === '') {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Admin Name
|--------------------------------------------------------------------------
*/
$admin_name = (string) $_SESSION['admin'];

/*
|--------------------------------------------------------------------------
| Dashboard Defaults
|--------------------------------------------------------------------------
*/
$total_students = 0;
$total_teachers = 0;
$total_subjects = 0;
$total_attendance = 0;
$total_marks = 0;

$present = 0;
$absent = 0;
$attendance_percentage = 0.0;

$subjects_labels = [];
$subjects_marks = [];

$database_error = '';

/*
|--------------------------------------------------------------------------
| Get Table Count
|--------------------------------------------------------------------------
*/
function getCount(mysqli $conn, string $table): int
{
    $allowed_tables = [
        'students',
        'teachers',
        'subjects',
        'attendance',
        'marks'
    ];

    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM `$table`";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int) $count;
}

/*
|--------------------------------------------------------------------------
| Load Dashboard Data
|--------------------------------------------------------------------------
*/
try {

    /*
    |--------------------------------------------------------------------------
    | Main Counts
    |--------------------------------------------------------------------------
    */
    $total_students = getCount($conn, 'students');
    $total_teachers = getCount($conn, 'teachers');
    $total_subjects = getCount($conn, 'subjects');
    $total_attendance = getCount($conn, 'attendance');
    $total_marks = getCount($conn, 'marks');

    /*
    |--------------------------------------------------------------------------
    | Attendance Statistics
    |--------------------------------------------------------------------------
    */
    $attendance_stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(status = 'Present'), 0) AS present,
            COALESCE(SUM(status = 'Absent'), 0) AS absent
         FROM attendance"
    );

    $attendance_stmt->execute();

    $attendance_stmt->bind_result(
        $present,
        $absent
    );

    $attendance_stmt->fetch();
    $attendance_stmt->close();

    $present = (int) $present;
    $absent = (int) $absent;

    $attendance_total = $present + $absent;

    if ($attendance_total > 0) {
        $attendance_percentage = round(
            ($present / $attendance_total) * 100,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subject-Wise Average Marks
    |--------------------------------------------------------------------------
    */
    $marks_stmt = $conn->prepare(
        "SELECT
            subject_name,
            ROUND(AVG(marks), 2) AS average_marks
         FROM marks
         GROUP BY subject_name
         ORDER BY subject_name ASC"
    );

    $marks_stmt->execute();

    $marks_stmt->bind_result(
        $subject_name,
        $average_marks
    );

    while ($marks_stmt->fetch()) {

        $subjects_labels[] = (string) $subject_name;
        $subjects_marks[] = (float) $average_marks;
    }

    $marks_stmt->close();

} catch (mysqli_sql_exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error Privately
    |--------------------------------------------------------------------------
    */
    error_log(
        'Class Management System - Dashboard database error: ' .
        $e->getMessage()
    );

    $database_error =
        'Some dashboard information could not be loaded right now.';
}

/*
|--------------------------------------------------------------------------
| JSON Data For Charts
|--------------------------------------------------------------------------
*/
$subjects_labels_json = json_encode(
    $subjects_labels,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$subjects_marks_json = json_encode(
    $subjects_marks,
    JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK
);

if ($subjects_labels_json === false) {
    $subjects_labels_json = '[]';
}

if ($subjects_marks_json === false) {
    $subjects_marks_json = '[]';
}

$current_year = date('Y');

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <meta
        name="description"
        content="Class Management System administrator dashboard"
    >

    <title>Dashboard | Class Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #111827;
            color: white;
            padding: 20px 15px;
            overflow-y: auto;
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 10px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .brand img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 10px;
            background: white;
        }

        .brand h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .brand small {
            color: #9ca3af;
            font-size: 11px;
        }

        .nav-title {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 10px 8px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 13px;
            margin-bottom: 5px;
            border-radius: 9px;
            color: #d1d5db;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a i {
            font-size: 17px;
            width: 22px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .logout-link {
            margin-top: 25px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px !important;
        }

        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* =========================
           TOPBAR
        ========================= */

        .topbar {
            height: 72px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .page-title h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .page-title p {
            margin: 3px 0 0;
            font-size: 13px;
            color: #6b7280;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2563eb;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .admin-info strong {
            display: block;
            font-size: 14px;
        }

        .admin-info small {
            color: #6b7280;
            font-size: 11px;
        }

        /* =========================
           CONTENT
        ========================= */

        .content {
            padding: 30px;
        }

        /* =========================
           DATABASE NOTICE
        ========================= */

        .database-notice {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            color: #664d03;
            border-radius: 12px;
            padding: 13px 16px;
            margin-bottom: 25px;
            font-size: 14px;
        }

        /* =========================
           BANNER
        ========================= */

        .dashboard-banner {
            position: relative;
            min-height: 220px;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 28px;

            background-image:
                linear-gradient(
                    90deg,
                    rgba(17,24,39,0.90),
                    rgba(37,99,235,0.65)
                ),
                url("images/login-bg.jpg");

            background-size: cover;
            background-position: center;

            display: flex;
            align-items: center;
        }

        .banner-content {
            padding: 35px;
            color: white;
            max-width: 650px;
        }

        .banner-content h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .banner-content p {
            margin: 0;
            color: #e5e7eb;
            font-size: 15px;
        }

        /* =========================
           STAT CARDS
        ========================= */

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 22px;
            border: 1px solid #e5e7eb;
            height: 100%;
            transition: 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.07);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }

        .stat-card h6 {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .stat-card h2 {
            font-size: 28px;
            font-weight: 800;
            margin: 0;
        }

        /* =========================
           SECTION TITLE
        ========================= */

        .section-title {
            margin: 32px 0 16px;
        }

        .section-title h4 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .section-title p {
            margin: 3px 0 0;
            color: #6b7280;
            font-size: 13px;
        }

        /* =========================
           ATTENDANCE CARDS
        ========================= */

        .attendance-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 22px;
            height: 100%;
        }

        .attendance-card h6 {
            color: #6b7280;
            font-size: 13px;
        }

        .attendance-number {
            font-size: 30px;
            font-weight: 800;
        }

        .attendance-present {
            color: #16a34a;
        }

        .attendance-absent {
            color: #dc2626;
        }

        .attendance-percent {
            color: #2563eb;
        }

        /* =========================
           CHART CARDS
        ========================= */

        .chart-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 22px;
            height: 100%;
        }

        .chart-card h5 {
            margin-bottom: 20px;
            font-weight: 700;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* =========================
           QUICK ACTIONS
        ========================= */

        .quick-action {
            display: block;
            text-decoration: none;
            color: inherit;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 20px;
            height: 100%;
            transition: 0.2s;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.07);
        }

        .quick-action i {
            font-size: 28px;
            color: #2563eb;
            margin-bottom: 10px;
        }

        .quick-action h6 {
            font-weight: 700;
            margin-bottom: 5px;
        }

        .quick-action p {
            color: #6b7280;
            font-size: 12px;
            margin: 0;
        }

        /* =========================
           REPORT CARDS
        ========================= */

        .report-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            padding: 25px;
            height: 100%;
        }

        .report-card > i {
            font-size: 30px;
            color: #2563eb;
            margin-bottom: 15px;
        }

        .report-card h5 {
            font-weight: 700;
        }

        .report-card p {
            color: #6b7280;
            font-size: 13px;
        }

        .report-card a {
            text-decoration: none;
        }

        .report-card a i {
            font-size: inherit;
            margin: 0;
        }

        /* =========================
           FOOTER
        ========================= */

        footer {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 12px;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 991px) {

            .sidebar {
                width: 220px;
            }

            .main {
                margin-left: 220px;
            }

            .content {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                height: auto;
                padding: 15px 20px;
                gap: 15px;
            }

            .admin-info {
                display: none;
            }

            .content {
                padding: 15px;
            }

            .dashboard-banner {
                min-height: 200px;
            }

            .banner-content {
                padding: 25px;
            }

            .banner-content h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<!-- =========================
     SIDEBAR
========================= -->

<div class="sidebar">

    <div class="brand">

        <img
            src="images/logo.png"
            alt="Class Management System Logo"
        >

        <div>
            <h4>Class Manager</h4>
            <small>Management System</small>
        </div>

    </div>

    <div class="nav-title">Main Menu</div>

    <a href="dashboard.php" class="active">
        <i class="bi bi-speedometer2"></i>
        Dashboard
    </a>

    <a href="students.php">
        <i class="bi bi-people"></i>
        Students
    </a>

    <a href="teachers.php">
        <i class="bi bi-person-workspace"></i>
        Teachers
    </a>

    <a href="subjects.php">
        <i class="bi bi-book"></i>
        Subjects
    </a>

    <a href="attendance.php">
        <i class="bi bi-calendar-check"></i>
        Attendance
    </a>

    <a href="marks.php">
        <i class="bi bi-bar-chart"></i>
        Marks
    </a>

    <a href="reports.php">
        <i class="bi bi-file-earmark-text"></i>
        Reports
    </a>

    <a href="logout.php" class="logout-link">
        <i class="bi bi-box-arrow-right"></i>
        Logout
    </a>

</div>


<!-- =========================
     MAIN
========================= -->

<div class="main">

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="page-title">

            <h2>Dashboard</h2>

            <p>
                Welcome back to your Class Management System
            </p>

        </div>

        <div class="admin-profile">

            <div class="admin-icon">
                <i class="bi bi-person"></i>
            </div>

            <div class="admin-info">

                <strong>
                    <?php echo e($admin_name); ?>
                </strong>

                <small>Administrator</small>

            </div>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="content">

        <?php if ($database_error !== ''): ?>

            <div class="database-notice">
                <?php echo e($database_error); ?>
            </div>

        <?php endif; ?>


        <!-- BANNER -->

        <div class="dashboard-banner">

            <div class="banner-content">

                <h1>
                    Class Management System
                </h1>

                <p>
                    Manage students, teachers, subjects, attendance
                    and academic performance from one professional dashboard.
                </p>

            </div>

        </div>


        <!-- STATISTICS -->

        <div class="row g-4">

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>

                    <h6>Total Students</h6>

                    <h2>
                        <?php echo $total_students; ?>
                    </h2>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-person-workspace"></i>
                    </div>

                    <h6>Total Teachers</h6>

                    <h2>
                        <?php echo $total_teachers; ?>
                    </h2>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-book-fill"></i>
                    </div>

                    <h6>Total Subjects</h6>

                    <h2>
                        <?php echo $total_subjects; ?>
                    </h2>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-award-fill"></i>
                    </div>

                    <h6>Marks Records</h6>

                    <h2>
                        <?php echo $total_marks; ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- ATTENDANCE -->

        <div class="section-title">

            <h4>Attendance Overview</h4>

            <p>
                Current attendance statistics
            </p>

        </div>


        <div class="row g-4">

            <div class="col-md-4">

                <div class="attendance-card">

                    <h6>Present</h6>

                    <div class="attendance-number attendance-present">
                        <?php echo $present; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="attendance-card">

                    <h6>Absent</h6>

                    <div class="attendance-number attendance-absent">
                        <?php echo $absent; ?>
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="attendance-card">

                    <h6>Attendance Percentage</h6>

                    <div class="attendance-number attendance-percent">
                        <?php echo number_format($attendance_percentage, 1); ?>%
                    </div>

                </div>

            </div>

        </div>


        <!-- CHARTS -->

        <div class="section-title">

            <h4>Analytics</h4>

            <p>
                Visual overview of academic information
            </p>

        </div>


        <div class="row g-4">

            <div class="col-lg-5">

                <div class="chart-card">

                    <h5>Attendance</h5>

                    <div class="chart-container">

                        <canvas id="attendanceChart"></canvas>

                    </div>

                </div>

            </div>


            <div class="col-lg-7">

                <div class="chart-card">

                    <h5>Subject Average Marks</h5>

                    <div class="chart-container">

                        <canvas id="marksChart"></canvas>

                    </div>

                </div>

            </div>

        </div>


        <!-- QUICK ACTIONS -->

        <div class="section-title">

            <h4>Quick Actions</h4>

            <p>
                Quickly access commonly used features
            </p>

        </div>


        <div class="row g-4">

            <div class="col-lg-3 col-md-6">

                <a href="add_student.php" class="quick-action">

                    <i class="bi bi-person-plus-fill"></i>

                    <h6>Add Student</h6>

                    <p>
                        Register a new student
                    </p>

                </a>

            </div>


            <div class="col-lg-3 col-md-6">

                <a href="add_teacher.php" class="quick-action">

                    <i class="bi bi-person-workspace"></i>

                    <h6>Add Teacher</h6>

                    <p>
                        Register a new teacher
                    </p>

                </a>

            </div>


            <div class="col-lg-3 col-md-6">

                <a href="add_subject.php" class="quick-action">

                    <i class="bi bi-book-half"></i>

                    <h6>Add Subject</h6>

                    <p>
                        Create a new subject
                    </p>

                </a>

            </div>


            <div class="col-lg-3 col-md-6">

                <a href="add_marks.php" class="quick-action">

                    <i class="bi bi-pencil-square"></i>

                    <h6>Add Marks</h6>

                    <p>
                        Enter student marks
                    </p>

                </a>

            </div>

        </div>


        <!-- REPORTS -->

        <div class="section-title">

            <h4>Reports</h4>

            <p>
                View detailed academic reports
            </p>

        </div>


        <div class="row g-4">

            <div class="col-md-6">

                <div class="report-card">

                    <i class="bi bi-file-earmark-bar-graph"></i>

                    <h5>Student Performance</h5>

                    <p>
                        View marks and academic performance
                        of students.
                    </p>

                    <a href="reports.php">
                        View Report
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>


            <div class="col-md-6">

                <div class="report-card">

                    <i class="bi bi-calendar2-check"></i>

                    <h5>Attendance Report</h5>

                    <p>
                        Check attendance records and
                        attendance percentages.
                    </p>

                    <a href="attendance.php">
                        View Attendance
                        <i class="bi bi-arrow-right"></i>
                    </a>

                </div>

            </div>

        </div>


        <!-- FOOTER -->

        <footer>

            © <?php echo e($current_year); ?>
            Class Management System.
            All Rights Reserved.

        </footer>


    </div>

</div>


<script>

    /*
    |--------------------------------------------------------------------------
    | Attendance Chart
    |--------------------------------------------------------------------------
    */

    const attendanceCanvas =
        document.getElementById('attendanceChart');

    if (attendanceCanvas) {

        new Chart(attendanceCanvas, {

            type: 'doughnut',

            data: {

                labels: [
                    'Present',
                    'Absent'
                ],

                datasets: [{

                    data: [
                        <?php echo $present; ?>,
                        <?php echo $absent; ?>
                    ]

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    legend: {
                        position: 'bottom'
                    }

                }

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | Subject Average Marks Chart
    |--------------------------------------------------------------------------
    */

    const marksCanvas =
        document.getElementById('marksChart');

    if (marksCanvas) {

        new Chart(marksCanvas, {

            type: 'bar',

            data: {

                labels:
                    <?php echo $subjects_labels_json; ?>,

                datasets: [{

                    label: 'Average Marks',

                    data:
                        <?php echo $subjects_marks_json; ?>

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                scales: {

                    y: {

                        beginAtZero: true,

                        max: 100,

                        ticks: {
                            precision: 0
                        }

                    }

                },

                plugins: {

                    legend: {
                        display: false
                    }

                }

            }

        });

    }

</script>

</body>

</html>