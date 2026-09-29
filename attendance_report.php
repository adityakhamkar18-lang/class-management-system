<?php

session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once "config.php";

/* -------------------------------------------------------
   Helper
------------------------------------------------------- */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------------
   Initialize
------------------------------------------------------- */
$student_id = 0;
$student = null;

$total_attendance = 0;
$total_present = 0;
$total_absent = 0;
$attendance_percentage = 0;

$attendance_result = false;

/* -------------------------------------------------------
   Get Student ID
------------------------------------------------------- */
if (isset($_GET['student_id'])) {
    $student_id = filter_var(
        $_GET['student_id'],
        FILTER_VALIDATE_INT
    );

    if ($student_id === false || $student_id <= 0) {
        $student_id = 0;
    }
}

/* -------------------------------------------------------
   Fetch Students
------------------------------------------------------- */
$students = mysqli_query(
    $conn,
    "SELECT id, roll_no, name, class_name
     FROM students
     ORDER BY name ASC"
);

if (!$students) {
    die("Unable to load students.");
}

/* -------------------------------------------------------
   Fetch Selected Student
------------------------------------------------------- */
if ($student_id > 0) {

    $student_stmt = mysqli_prepare(
        $conn,
        "SELECT id, roll_no, name, email, phone, gender, class_name
         FROM students
         WHERE id = ?
         LIMIT 1"
    );

    if (!$student_stmt) {
        die("Unable to load student information.");
    }

    mysqli_stmt_bind_param(
        $student_stmt,
        "i",
        $student_id
    );

    mysqli_stmt_execute($student_stmt);

    $student_result = mysqli_stmt_get_result($student_stmt);

    if (
        $student_result &&
        mysqli_num_rows($student_result) > 0
    ) {
        $student = mysqli_fetch_assoc($student_result);
    }

    mysqli_stmt_close($student_stmt);
}

/* -------------------------------------------------------
   Attendance Information
------------------------------------------------------- */
if ($student) {

    /* Total */
    $total_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM attendance
         WHERE student_id = ?"
    );

    if ($total_stmt) {

        mysqli_stmt_bind_param(
            $total_stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($total_stmt);

        $total_result = mysqli_stmt_get_result($total_stmt);

        if ($total_result) {

            $total_data = mysqli_fetch_assoc(
                $total_result
            );

            $total_attendance =
                (int)$total_data['total'];
        }

        mysqli_stmt_close($total_stmt);
    }


    /* Present */
    $present_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM attendance
         WHERE student_id = ?
         AND status = 'Present'"
    );

    if ($present_stmt) {

        mysqli_stmt_bind_param(
            $present_stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($present_stmt);

        $present_result =
            mysqli_stmt_get_result($present_stmt);

        if ($present_result) {

            $present_data =
                mysqli_fetch_assoc($present_result);

            $total_present =
                (int)$present_data['total'];
        }

        mysqli_stmt_close($present_stmt);
    }


    /* Absent */
    $absent_stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM attendance
         WHERE student_id = ?
         AND status = 'Absent'"
    );

    if ($absent_stmt) {

        mysqli_stmt_bind_param(
            $absent_stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($absent_stmt);

        $absent_result =
            mysqli_stmt_get_result($absent_stmt);

        if ($absent_result) {

            $absent_data =
                mysqli_fetch_assoc($absent_result);

            $total_absent =
                (int)$absent_data['total'];
        }

        mysqli_stmt_close($absent_stmt);
    }


    /* Percentage */
    if ($total_attendance > 0) {

        $attendance_percentage =
            ($total_present / $total_attendance) * 100;
    }


    /* Attendance Records */
    $attendance_stmt = mysqli_prepare(
        $conn,
        "SELECT attendance_date, status
         FROM attendance
         WHERE student_id = ?
         ORDER BY attendance_date DESC"
    );

    if ($attendance_stmt) {

        mysqli_stmt_bind_param(
            $attendance_stmt,
            "i",
            $student_id
        );

        mysqli_stmt_execute($attendance_stmt);

        $attendance_result =
            mysqli_stmt_get_result(
                $attendance_stmt
            );
    }
}

/* -------------------------------------------------------
   Attendance Level
------------------------------------------------------- */
$attendance_level = "No Attendance";

if ($total_attendance > 0) {

    if ($attendance_percentage >= 75) {
        $attendance_level = "Good Attendance";
    } elseif ($attendance_percentage >= 60) {
        $attendance_level = "Average Attendance";
    } else {
        $attendance_level = "Low Attendance";
    }
}

/* -------------------------------------------------------
   Progress Class
------------------------------------------------------- */
$progress_class = "low";

if ($attendance_percentage >= 75) {
    $progress_class = "good";
} elseif ($attendance_percentage >= 60) {
    $progress_class = "average";
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

    <title>
        Attendance Report - Class Management System
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

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
            background: #f1f5f3;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        /* =================================================
           TOP BAR
        ================================================= */

        .top-bar {
            background: #064e3b;
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            font-size: 19px;
            font-weight: 700;
        }

        .top-actions {
            display: flex;
            gap: 10px;
        }

        /* =================================================
           PAGE
        ================================================= */

        .page-wrapper {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 18px 40px;
        }

        .page-header {
            background: white;
            border-radius: 18px;
            padding: 25px 28px;
            margin-bottom: 22px;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.06);
            border-left: 6px solid #16a34a;
        }

        .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
        }

        .page-header p {
            margin: 7px 0 0;
            color: #6b7280;
        }

        /* =================================================
           STUDENT SELECTOR
        ================================================= */

        .selection-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 5px 18px rgba(15, 23, 42, 0.06);
        }

        .selection-title {
            font-weight: 700;
            margin-bottom: 14px;
        }

        .form-select {
            min-height: 46px;
        }

        .view-button {
            min-height: 46px;
            font-weight: 600;
        }

        /* =================================================
           ATTENDANCE DASHBOARD
        ================================================= */

        .attendance-sheet {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
        }

        .attendance-header {
            background: linear-gradient(
                135deg,
                #047857,
                #059669,
                #10b981
            );
            color: white;
            padding: 35px;
        }

        .attendance-header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
        }

        .attendance-title {
            font-size: 29px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .attendance-subtitle {
            opacity: 0.9;
        }

        .calendar-icon {
            width: 78px;
            height: 78px;
            border-radius: 18px;
            background: rgba(255,255,255,0.18);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 40px;
        }

        .attendance-content {
            padding: 30px;
        }

        /* =================================================
           STUDENT SUMMARY
        ================================================= */

        .student-summary {
            display: flex;
            align-items: center;
            gap: 18px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 28px;
        }

        .student-avatar {
            width: 62px;
            height: 62px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #16a34a;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 27px;
            font-weight: 700;
        }

        .student-summary h3 {
            font-size: 21px;
            margin: 0 0 5px;
            font-weight: 800;
        }

        .student-summary p {
            margin: 0;
            color: #64748b;
        }

        /* =================================================
           ATTENDANCE OVERVIEW
        ================================================= */

        .overview-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 28px;
        }

        .overview-card {
            border-radius: 16px;
            padding: 22px;
            border: 1px solid #e5e7eb;
            background: white;
        }

        .overview-card.total {
            background: #f8fafc;
        }

        .overview-card.present {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .overview-card.absent {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .overview-card.percentage {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .overview-icon {
            font-size: 22px;
            margin-bottom: 12px;
        }

        .overview-card.total .overview-icon {
            color: #475569;
        }

        .overview-card.present .overview-icon {
            color: #16a34a;
        }

        .overview-card.absent .overview-icon {
            color: #dc2626;
        }

        .overview-card.percentage .overview-icon {
            color: #059669;
        }

        .overview-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
        }

        .overview-number {
            font-size: 29px;
            font-weight: 800;
            margin-top: 4px;
        }

        /* =================================================
           ATTENDANCE PERCENTAGE
        ================================================= */

        .percentage-panel {
            background: #f8fafc;
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 28px;
        }

        .percentage-layout {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .attendance-circle {
            width: 145px;
            height: 145px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: conic-gradient(
                #16a34a
                <?php echo min(100, max(0, $attendance_percentage)); ?>%,
                #dbe4df
                0
            );
        }

        .attendance-circle::before {
            content: "";
            position: absolute;
            width: 108px;
            height: 108px;
            border-radius: 50%;
            background: white;
        }

        .circle-value {
            position: relative;
            z-index: 1;
            font-size: 27px;
            font-weight: 800;
            color: #166534;
        }

        .percentage-info {
            flex: 1;
        }

        .percentage-info h4 {
            font-weight: 800;
            margin-bottom: 8px;
        }

        .percentage-info p {
            color: #64748b;
            margin-bottom: 15px;
        }

        .attendance-progress {
            height: 13px;
            border-radius: 20px;
            background: #dbe4df;
            overflow: hidden;
        }

        .attendance-progress-bar {
            height: 100%;
            border-radius: 20px;
        }

        .progress-good {
            background: #16a34a;
        }

        .progress-average {
            background: #f59e0b;
        }

        .progress-low {
            background: #dc2626;
        }

        .attendance-status {
            display: inline-block;
            margin-top: 12px;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-good {
            background: #dcfce7;
            color: #166534;
        }

        .status-average {
            background: #fef3c7;
            color: #92400e;
        }

        .status-low {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-none {
            background: #e5e7eb;
            color: #4b5563;
        }

        /* =================================================
           ATTENDANCE RECORDS
        ================================================= */

        .records-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .records-header h4 {
            margin: 0;
            font-size: 19px;
            font-weight: 800;
        }

        .records-table-container {
            border: 1px solid #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
        }

        .attendance-table {
            margin: 0;
        }

        .attendance-table thead th {
            background: #064e3b;
            color: white;
            padding: 14px;
            border: none;
        }

        .attendance-table tbody td {
            padding: 14px;
            vertical-align: middle;
        }

        .date-cell {
            font-weight: 700;
        }

        .date-icon {
            color: #16a34a;
            margin-right: 7px;
        }

        .attendance-badge {
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .present-badge {
            background: #dcfce7;
            color: #166534;
        }

        .absent-badge {
            background: #fee2e2;
            color: #991b1b;
        }

        .other-badge {
            background: #e5e7eb;
            color: #374151;
        }

        /* =================================================
           FOOTER
        ================================================= */

        .report-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1000px) {

            .overview-grid {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 768px) {

            .top-bar {
                padding: 13px 16px;
            }

            .page-wrapper {
                margin-top: 18px;
            }

            .attendance-header {
                padding: 25px;
            }

            .attendance-header-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .attendance-content {
                padding: 20px;
            }

            .percentage-layout {
                flex-direction: column;
                align-items: flex-start;
            }

        }

        @media (max-width: 576px) {

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .student-summary {
                align-items: flex-start;
            }

            .attendance-title {
                font-size: 24px;
            }

        }

        /* =================================================
           PRINT
        ================================================= */

        @media print {

            body {
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .page-wrapper {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .attendance-sheet {
                box-shadow: none;
                border-radius: 0;
            }

            .attendance-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .attendance-content {
                padding: 20px;
            }

            .attendance-circle {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

        }

    </style>

</head>

<body>


<!-- =====================================================
     TOP BAR
====================================================== -->

<div class="top-bar no-print">

    <div class="brand">

        <i class="bi bi-calendar-check-fill"></i>

        Class Management System

    </div>

    <div class="top-actions">

        <a
            href="reports.php"
            class="btn btn-outline-light btn-sm"
        >
            <i class="bi bi-bar-chart-line"></i>
            Reports
        </a>

        <a
            href="attendance.php"
            class="btn btn-light btn-sm"
        >
            <i class="bi bi-calendar-check"></i>
            Attendance
        </a>

    </div>

</div>


<div class="page-wrapper">


    <!-- =================================================
         PAGE HEADER
    ================================================== -->

    <div class="page-header no-print">

        <h1>

            <i class="bi bi-calendar2-week-fill text-success"></i>

            Attendance Tracking

        </h1>

        <p>
            Daily attendance history and attendance percentage
        </p>

    </div>


    <!-- =================================================
         STUDENT SELECTION
    ================================================== -->

    <div class="selection-card no-print">

        <div class="selection-title">

            <i class="bi bi-person-check-fill text-success"></i>

            Select Student

        </div>

        <form method="GET">

            <div class="row g-3">

                <div class="col-md-10">

                    <select
                        name="student_id"
                        class="form-select"
                        required
                    >

                        <option value="">
                            -- Select Student --
                        </option>

                        <?php

                        if (
                            $students &&
                            mysqli_num_rows($students) > 0
                        ) {

                            while (
                                $s =
                                mysqli_fetch_assoc($students)
                            ) {

                        ?>

                            <option
                                value="<?php echo (int)$s['id']; ?>"
                                <?php
                                echo (
                                    $student_id ===
                                    (int)$s['id']
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >

                                <?php

                                echo e(
                                    $s['roll_no'] .
                                    " - " .
                                    $s['name'] .
                                    " - " .
                                    $s['class_name']
                                );

                                ?>

                            </option>

                        <?php

                            }

                        } else {

                        ?>

                            <option
                                value=""
                                disabled
                            >
                                No students available
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-success w-100 view-button"
                    >

                        <i class="bi bi-calendar-check-fill"></i>

                        View Attendance

                    </button>

                </div>

            </div>

        </form>

    </div>


    <?php if ($student) { ?>


    <!-- =================================================
         ATTENDANCE SHEET
    ================================================== -->

    <div class="attendance-sheet">


        <!-- Attendance Header -->

        <div class="attendance-header">

            <div class="attendance-header-inner">

                <div>

                    <div class="attendance-title">

                        Student Attendance Report

                    </div>

                    <div class="attendance-subtitle">

                        Attendance history and participation summary

                    </div>

                </div>

                <div class="calendar-icon">

                    <i class="bi bi-calendar-check-fill"></i>

                </div>

            </div>

        </div>


        <div class="attendance-content">


            <!-- =================================================
                 STUDENT SUMMARY
            ================================================== -->

            <div class="student-summary">

                <div class="student-avatar">

                    <?php
                    echo strtoupper(
                        substr(
                            $student['name'],
                            0,
                            1
                        )
                    );
                    ?>

                </div>

                <div>

                    <h3>
                        <?php echo e($student['name']); ?>
                    </h3>

                    <p>

                        Roll No:
                        <strong>
                            <?php echo e($student['roll_no']); ?>
                        </strong>

                        &nbsp; • &nbsp;

                        Class:
                        <strong>
                            <?php echo e($student['class_name']); ?>
                        </strong>

                    </p>

                </div>

            </div>


            <!-- =================================================
                 ATTENDANCE OVERVIEW
            ================================================== -->

            <div class="overview-grid">


                <!-- Total -->

                <div class="overview-card total">

                    <div class="overview-icon">

                        <i class="bi bi-calendar3"></i>

                    </div>

                    <div class="overview-label">
                        Total Days
                    </div>

                    <div class="overview-number">

                        <?php
                        echo $total_attendance;
                        ?>

                    </div>

                </div>


                <!-- Present -->

                <div class="overview-card present">

                    <div class="overview-icon">

                        <i class="bi bi-check-circle-fill"></i>

                    </div>

                    <div class="overview-label">
                        Present
                    </div>

                    <div class="overview-number text-success">

                        <?php
                        echo $total_present;
                        ?>

                    </div>

                </div>


                <!-- Absent -->

                <div class="overview-card absent">

                    <div class="overview-icon">

                        <i class="bi bi-x-circle-fill"></i>

                    </div>

                    <div class="overview-label">
                        Absent
                    </div>

                    <div class="overview-number text-danger">

                        <?php
                        echo $total_absent;
                        ?>

                    </div>

                </div>


                <!-- Percentage -->

                <div class="overview-card percentage">

                    <div class="overview-icon">

                        <i class="bi bi-percent"></i>

                    </div>

                    <div class="overview-label">
                        Attendance
                    </div>

                    <div class="overview-number text-success">

                        <?php

                        echo number_format(
                            $attendance_percentage,
                            2
                        );

                        ?>%

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ATTENDANCE PERCENTAGE PANEL
            ================================================== -->

            <div class="percentage-panel">

                <div class="percentage-layout">


                    <!-- Circle -->

                    <div class="attendance-circle">

                        <div class="circle-value">

                            <?php

                            echo number_format(
                                $attendance_percentage,
                                0
                            );

                            ?>%

                        </div>

                    </div>


                    <!-- Information -->

                    <div class="percentage-info">

                        <h4>

                            Attendance Overview

                        </h4>

                        <p>

                            The student attended
                            <strong>
                                <?php echo $total_present; ?>
                            </strong>
                            out of
                            <strong>
                                <?php echo $total_attendance; ?>
                            </strong>
                            recorded days.

                        </p>


                        <div class="attendance-progress">

                            <div
                                class="attendance-progress-bar
                                progress-<?php echo $progress_class; ?>"
                                style="width: <?php echo min(100, max(0, $attendance_percentage)); ?>%;"
                            ></div>

                        </div>


                        <span
                            class="attendance-status
                            status-<?php echo $progress_class; ?>"
                        >

                            <?php

                            if ($progress_class === 'good') {

                                echo '<i class="bi bi-check-circle-fill"></i>';

                            } elseif ($progress_class === 'average') {

                                echo '<i class="bi bi-exclamation-circle-fill"></i>';

                            } else {

                                echo '<i class="bi bi-exclamation-triangle-fill"></i>';

                            }

                            ?>

                            <?php echo e($attendance_level); ?>

                        </span>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ATTENDANCE RECORDS
            ================================================== -->

            <div class="records-header">

                <h4>

                    <i class="bi bi-calendar3 text-success"></i>

                    Daily Attendance Records

                </h4>

                <span class="badge bg-success">

                    <?php echo $total_attendance; ?>

                    Records

                </span>

            </div>


            <div class="records-table-container">

                <div class="table-responsive">

                    <table class="table attendance-table">

                        <thead>

                            <tr>

                                <th width="80">
                                    #
                                </th>

                                <th>
                                    Attendance Date
                                </th>

                                <th width="200">
                                    Status
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php

                        if (
                            $attendance_result &&
                            mysqli_num_rows(
                                $attendance_result
                            ) > 0
                        ) {

                            $sr_no = 1;

                            while (
                                $attendance =
                                mysqli_fetch_assoc(
                                    $attendance_result
                                )
                            ) {

                        ?>

                            <tr>

                                <td>

                                    <?php
                                    echo $sr_no++;
                                    ?>

                                </td>

                                <td class="date-cell">

                                    <i
                                        class="bi bi-calendar-event date-icon"
                                    ></i>

                                    <?php

                                    echo e(
                                        $attendance[
                                            'attendance_date'
                                        ]
                                    );

                                    ?>

                                </td>

                                <td>

                                    <?php

                                    if (
                                        $attendance['status']
                                        === 'Present'
                                    ) {

                                    ?>

                                        <span
                                            class="attendance-badge present-badge"
                                        >

                                            <i class="bi bi-check-circle-fill"></i>

                                            Present

                                        </span>

                                    <?php

                                    } elseif (
                                        $attendance['status']
                                        === 'Absent'
                                    ) {

                                    ?>

                                        <span
                                            class="attendance-badge absent-badge"
                                        >

                                            <i class="bi bi-x-circle-fill"></i>

                                            Absent

                                        </span>

                                    <?php

                                    } else {

                                    ?>

                                        <span
                                            class="attendance-badge other-badge"
                                        >

                                            <?php

                                            echo e(
                                                $attendance['status']
                                            );

                                            ?>

                                        </span>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php

                            }

                        } else {

                        ?>

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center text-muted p-5"
                                >

                                    <i
                                        class="bi bi-calendar-x"
                                        style="font-size: 38px;"
                                    ></i>

                                    <div class="mt-2">

                                        No attendance records available.

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- =================================================
                 FOOTER
            ================================================== -->

            <div class="report-footer">

                <div>

                    <i class="bi bi-shield-check"></i>

                    Generated by Class Management System

                </div>

                <div class="mt-1">

                    Student Attendance Tracking Report

                </div>

            </div>


        </div>

    </div>


    <!-- =================================================
         PRINT BUTTON
    ================================================== -->

    <div class="text-center mt-4 no-print">

        <button
            type="button"
            onclick="window.print()"
            class="btn btn-success px-4"
        >

            <i class="bi bi-printer-fill"></i>

            Print Attendance Report

        </button>

    </div>


    <?php } elseif ($student_id > 0) { ?>


        <div class="alert alert-danger">

            <i class="bi bi-exclamation-triangle-fill"></i>

            Student not found.

            <a
                href="attendance_report.php"
                class="alert-link"
            >
                Try again
            </a>

        </div>


    <?php } else { ?>


        <div class="alert alert-info">

            <i class="bi bi-info-circle-fill"></i>

            Please select a student to view the attendance report.

        </div>


    <?php } ?>


</div>

</body>

</html>