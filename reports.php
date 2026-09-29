<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once "config.php";

// ----------------------------------------------------
// Admin name
// ----------------------------------------------------
$admin_name = $_SESSION['admin'] ?? 'Administrator';

// ----------------------------------------------------
// Function: Get Table Count
// ----------------------------------------------------
function getCount($conn, $table)
{
    $allowed_tables = [
        'students',
        'attendance',
        'marks'
    ];

    if (!in_array($table, $allowed_tables, true)) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total FROM $table"
    );

    if (!$stmt) {
        return 0;
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return (int)($data['total'] ?? 0);
}

// ----------------------------------------------------
// Get Statistics
// ----------------------------------------------------
$total_students = getCount($conn, 'students');
$total_attendance = getCount($conn, 'attendance');
$total_marks = getCount($conn, 'marks');

// ----------------------------------------------------
// Attendance Summary
// ----------------------------------------------------
$present_count = 0;
$absent_count = 0;

$attendance_stmt = mysqli_prepare(
    $conn,
    "SELECT status FROM attendance"
);

if ($attendance_stmt) {

    if (mysqli_stmt_execute($attendance_stmt)) {

        $attendance_result = mysqli_stmt_get_result($attendance_stmt);

        while ($attendance_row = mysqli_fetch_assoc($attendance_result)) {

            $status = strtolower(
                trim($attendance_row['status'] ?? '')
            );

            if ($status === 'present') {
                $present_count++;
            } elseif ($status === 'absent') {
                $absent_count++;
            }
        }
    }

    mysqli_stmt_close($attendance_stmt);
}

$total_attendance_days = $present_count + $absent_count;

$attendance_percentage = 0;

if ($total_attendance_days > 0) {

    $attendance_percentage = round(
        ($present_count / $total_attendance_days) * 100,
        2
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reports | Class Management System</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            font-family: Arial, Helvetica, sans-serif;
            color: #212529;
        }

        /* =========================================
           SIDEBAR
        ========================================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #111827;
            padding: 22px 15px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-brand {
            color: #ffffff;
            font-size: 21px;
            font-weight: 700;
            text-align: center;
            padding: 12px 5px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            margin-bottom: 20px;
        }

        .sidebar-brand i {
            margin-right: 8px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 7px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cbd5e1;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 15px;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: #1f2937;
            color: #ffffff;
        }

        .sidebar-menu a.active {
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }

        .sidebar-menu i {
            width: 20px;
            font-size: 17px;
        }

        /* =========================================
           MAIN CONTENT
        ========================================= */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* =========================================
           TOPBAR
        ========================================= */

        .topbar {
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }

        .topbar-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #374151;
            font-weight: 600;
        }

        .admin-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* =========================================
           CONTENT
        ========================================= */

        .content-area {
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .page-subtitle {
            margin-top: 6px;
            margin-bottom: 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* =========================================
           STAT CARDS
        ========================================= */

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 20px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(0,0,0,0.07);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .stat-value {
            color: #111827;
            font-size: 25px;
            font-weight: 700;
        }

        /* =========================================
           SECTION TITLE
        ========================================= */

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-top: 35px;
            margin-bottom: 18px;
        }

        /* =========================================
           ATTENDANCE SUMMARY
        ========================================= */

        .summary-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 22px;
            margin-top: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.05);
        }

        .summary-item {
            padding: 16px;
            border-radius: 10px;
            background: #f8fafc;
            height: 100%;
        }

        .summary-label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .summary-value {
            color: #111827;
            font-size: 22px;
            font-weight: 700;
        }

        /* =========================================
           DIFFERENT REPORT CARDS
        ========================================= */

        .performance-report-card,
        .attendance-report-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 25px;
            height: 100%;
            transition: 0.25s ease;
        }

        /* Student Performance */

        .performance-report-card {
            border-top: 4px solid #2563eb;
        }

        .performance-report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(37,99,235,0.12);
        }

        /* Attendance */

        .attendance-report-card {
            border-top: 4px solid #16a34a;
        }

        .attendance-report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(22,163,74,0.12);
        }

        /* =========================================
           REPORT HEADERS
        ========================================= */

        .performance-header,
        .attendance-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .performance-icon,
        .attendance-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .performance-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .attendance-icon {
            background: #f0fdf4;
            color: #16a34a;
        }

        /* =========================================
           REPORT CATEGORY
        ========================================= */

        .report-category {
            display: block;
            color: #2563eb;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .attendance-category {
            color: #16a34a;
        }

        .performance-report-card h4,
        .attendance-report-card h4 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        /* =========================================
           DIVIDER
        ========================================= */

        .performance-divider,
        .attendance-divider {
            height: 1px;
            margin: 20px 0;
        }

        .performance-divider {
            background: #dbeafe;
        }

        .attendance-divider {
            background: #dcfce7;
        }

        /* =========================================
           REPORT DESCRIPTION
        ========================================= */

        .report-description {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            min-height: 72px;
            margin-bottom: 0;
        }

        /* =========================================
           REPORT FEATURES
        ========================================= */

        .report-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 20px 0 22px;
        }

        .report-features span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 7px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
        }

        .report-features span i {
            font-size: 12px;
        }

        .attendance-features span {
            background: #f0fdf4;
            color: #15803d;
        }

        /* =========================================
           REPORT BUTTONS
        ========================================= */

        .performance-button,
        .attendance-button {
            display: flex;
            align-items: center;
            gap: 9px;
            width: 100%;
            padding: 12px 15px;
            border-radius: 9px;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .performance-button {
            background: #2563eb;
        }

        .performance-button:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .attendance-button {
            background: #16a34a;
        }

        .attendance-button:hover {
            background: #15803d;
            color: #ffffff;
        }

        /* =========================================
           QUICK NAVIGATION
        ========================================= */

        .quick-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            padding: 13px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            color: #374151;
            background: #ffffff;
            transition: 0.2s ease;
        }

        .quick-link:hover {
            background: #f8fafc;
            color: #2563eb;
            border-color: #bfdbfe;
        }

        /* =========================================
           FOOTER
        ========================================= */

        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 13px;
            padding: 25px 10px;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 992px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 5px;
            }

            .sidebar-menu li {
                margin-bottom: 0;
            }

            .topbar {
                padding: 0 20px;
            }

            .content-area {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .topbar {
                height: auto;
                padding: 15px;
                gap: 10px;
                flex-direction: column;
                align-items: flex-start;
            }

            .content-area {
                padding: 15px;
            }

            .page-title {
                font-size: 23px;
            }

            .performance-report-card,
            .attendance-report-card {
                padding: 20px;
            }

            .report-description {
                min-height: auto;
            }

            .performance-header,
            .attendance-header {
                align-items: flex-start;
            }
        }

    </style>

</head>

<body>

<!-- =========================================
     SIDEBAR
========================================= -->

<aside class="sidebar">

    <div class="sidebar-brand">
        <i class="bi bi-mortarboard-fill"></i>
        Class Management
    </div>

    <ul class="sidebar-menu">

        <li>
            <a href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </li>

        <li>
            <a href="students.php">
                <i class="bi bi-people-fill"></i>
                Students
            </a>
        </li>

        <li>
            <a href="teachers.php">
                <i class="bi bi-person-workspace"></i>
                Teachers
            </a>
        </li>

        <li>
            <a href="subjects.php">
                <i class="bi bi-book-fill"></i>
                Subjects
            </a>
        </li>

        <li>
            <a href="attendance.php">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
        </li>

        <li>
            <a href="marks.php">
                <i class="bi bi-bar-chart-fill"></i>
                Marks
            </a>
        </li>

        <li>
            <a href="reports.php" class="active">
                <i class="bi bi-file-earmark-text-fill"></i>
                Reports
            </a>
        </li>

        <li>
            <a href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </li>

    </ul>

</aside>


<!-- =========================================
     MAIN CONTENT
========================================= -->

<div class="main-content">

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="topbar-title">
            Reports
        </div>

        <div class="admin-profile">

            <div class="admin-icon">
                <i class="bi bi-person-fill"></i>
            </div>

            <span>
                <?php echo htmlspecialchars($admin_name); ?>
            </span>

        </div>

    </div>


    <!-- CONTENT -->

    <main class="content-area">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1 class="page-title">
                    Reports
                </h1>

                <p class="page-subtitle">
                    View student performance and attendance reports.
                </p>

            </div>

            <a
                href="dashboard.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>

        </div>


        <!-- =====================================
             STATISTICS
        ====================================== -->

        <div class="row g-3">

            <div class="col-lg-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>

                    <div class="stat-label">
                        Total Students
                    </div>

                    <div class="stat-value">
                        <?php echo $total_students; ?>
                    </div>

                </div>

            </div>


            <div class="col-lg-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>

                    <div class="stat-label">
                        Attendance Records
                    </div>

                    <div class="stat-value">
                        <?php echo $total_attendance; ?>
                    </div>

                </div>

            </div>


            <div class="col-lg-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>

                    <div class="stat-label">
                        Marks Records
                    </div>

                    <div class="stat-value">
                        <?php echo $total_marks; ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================
             ATTENDANCE SUMMARY
        ====================================== -->

        <div class="summary-card">

            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

                <div>

                    <h3 class="mb-1 fw-bold">
                        Attendance Summary
                    </h3>

                    <p class="text-muted mb-0 small">
                        Overall attendance information
                    </p>

                </div>

                <i class="bi bi-pie-chart-fill text-primary fs-3"></i>

            </div>


            <div class="row g-3">

                <div class="col-md-4">

                    <div class="summary-item">

                        <div class="summary-label">
                            Present
                        </div>

                        <div class="summary-value text-success">
                            <?php echo $present_count; ?>
                        </div>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="summary-item">

                        <div class="summary-label">
                            Absent
                        </div>

                        <div class="summary-value text-danger">
                            <?php echo $absent_count; ?>
                        </div>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="summary-item">

                        <div class="summary-label">
                            Attendance Percentage
                        </div>

                        <div class="summary-value text-primary">
                            <?php echo $attendance_percentage; ?>%
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================
             AVAILABLE REPORTS
        ====================================== -->

        <h2 class="section-title">
            Available Reports
        </h2>


        <div class="row g-4">


            <!-- =================================
                 STUDENT PERFORMANCE REPORT
            ================================== -->

            <div class="col-lg-6">

                <div class="performance-report-card">

                    <div class="performance-header">

                        <div class="performance-icon">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>

                        <div>

                            <span class="report-category">
                                ACADEMIC
                            </span>

                            <h4>
                                Student Performance
                            </h4>

                        </div>

                    </div>


                    <div class="performance-divider"></div>


                    <p class="report-description">

                        Analyze student academic performance including
                        subject-wise marks, total marks, percentage
                        and final result.

                    </p>


                    <div class="report-features">

                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            Subject Marks
                        </span>

                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            Percentage
                        </span>

                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            Result
                        </span>

                    </div>


                    <a
                        href="student_report.php"
                        class="performance-button"
                    >

                        <i class="bi bi-bar-chart-line-fill"></i>

                        View Performance

                        <i class="bi bi-arrow-right ms-auto"></i>

                    </a>

                </div>

            </div>


            <!-- =================================
                 ATTENDANCE REPORT
            ================================== -->

            <div class="col-lg-6">

                <div class="attendance-report-card">

                    <div class="attendance-header">

                        <div class="attendance-icon">
                            <i class="bi bi-calendar2-week-fill"></i>
                        </div>

                        <div>

                            <span class="report-category attendance-category">
                                ATTENDANCE
                            </span>

                            <h4>
                                Attendance Report
                            </h4>

                        </div>

                    </div>


                    <div class="attendance-divider"></div>


                    <p class="report-description">

                        Monitor student attendance including present
                        days, absent days and overall attendance
                        percentage.

                    </p>


                    <div class="report-features attendance-features">

                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            Present Days
                        </span>

                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            Absent Days
                        </span>

                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            Attendance %
                        </span>

                    </div>


                    <a
                        href="attendance_report.php"
                        class="attendance-button"
                    >

                        <i class="bi bi-calendar-check-fill"></i>

                        View Attendance

                        <i class="bi bi-arrow-right ms-auto"></i>

                    </a>

                </div>

            </div>

        </div>


        <!-- =====================================
             QUICK NAVIGATION
        ====================================== -->

        <h2 class="section-title">
            Quick Navigation
        </h2>


        <div class="row g-3">

            <div class="col-md-4">

                <a
                    href="students.php"
                    class="quick-link"
                >

                    <i class="bi bi-people-fill text-primary"></i>

                    Manage Students

                    <i class="bi bi-arrow-right ms-auto"></i>

                </a>

            </div>


            <div class="col-md-4">

                <a
                    href="attendance.php"
                    class="quick-link"
                >

                    <i class="bi bi-calendar-check-fill text-success"></i>

                    Manage Attendance

                    <i class="bi bi-arrow-right ms-auto"></i>

                </a>

            </div>


            <div class="col-md-4">

                <a
                    href="marks.php"
                    class="quick-link"
                >

                    <i class="bi bi-bar-chart-fill text-primary"></i>

                    Manage Marks

                    <i class="bi bi-arrow-right ms-auto"></i>

                </a>

            </div>

        </div>

    </main>


    <!-- FOOTER -->

    <footer class="footer">

        © 2026 Class Management System.
        All rights reserved.

    </footer>

</div>

</body>

</html>