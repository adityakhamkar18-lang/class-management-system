
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Class Management System - Attendance Report
|--------------------------------------------------------------------------
| Purpose:
|   Display a complete attendance report for a selected student.
|
| Features:
|   - Student selection
|   - Attendance summary
|   - Present / Absent count
|   - Attendance percentage
|   - Attendance level
|   - Daily attendance history
|   - Print-friendly report
|
| Security:
|   - Admin authentication
|   - Prepared statements
|   - Server-side input validation
|   - Safe HTML output
|   - No database errors exposed to users
|--------------------------------------------------------------------------
*/

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['admin']) ||
    !is_string($_SESSION['admin']) ||
    $_SESSION['admin'] === ''
) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDate(string $date): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('d M Y', $timestamp);
}

/*
|--------------------------------------------------------------------------
| Initialize Variables
|--------------------------------------------------------------------------
*/
$student_id = 0;

$student = null;
$students = [];
$attendance_records = [];

$total_attendance = 0;
$total_present = 0;
$total_absent = 0;

$attendance_percentage = 0.0;
$progress_percentage = 0.0;

$attendance_level = 'No Attendance';
$progress_class = 'none';

$database_error = '';

$generated_date = date('d M Y');

/*
|--------------------------------------------------------------------------
| Get Student ID
|--------------------------------------------------------------------------
*/
$raw_student_id = $_GET['student_id'] ?? '';

if (is_string($raw_student_id)) {

    $raw_student_id = trim($raw_student_id);

    if (
        $raw_student_id !== '' &&
        ctype_digit($raw_student_id)
    ) {
        $student_id = (int) $raw_student_id;
    }
}

if ($student_id <= 0) {
    $student_id = 0;
}

/*
|--------------------------------------------------------------------------
| Fetch Students
|--------------------------------------------------------------------------
*/
try {

    $students_stmt = $conn->prepare(
        'SELECT
            id,
            roll_no,
            name,
            class_name
         FROM students
         ORDER BY name ASC'
    );

    $students_stmt->execute();
    $students_stmt->store_result();

    $students_stmt->bind_result(
        $student_list_id,
        $student_roll_no,
        $student_name,
        $student_class_name
    );

    while ($students_stmt->fetch()) {

        $students[] = [
            'id' => (int) $student_list_id,
            'roll_no' => (string) $student_roll_no,
            'name' => (string) $student_name,
            'class_name' => (string) $student_class_name
        ];
    }

    $students_stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Attendance report student list error: ' .
        $e->getMessage()
    );

    $database_error =
        'Unable to load the student list. Please try again.';
}

/*
|--------------------------------------------------------------------------
| Fetch Selected Student
|--------------------------------------------------------------------------
*/
if (
    $student_id > 0 &&
    $database_error === ''
) {

    try {

        $student_stmt = $conn->prepare(
            'SELECT
                id,
                roll_no,
                name,
                email,
                phone,
                gender,
                class_name
             FROM students
             WHERE id = ?
             LIMIT 1'
        );

        $student_stmt->bind_param(
            'i',
            $student_id
        );

        $student_stmt->execute();
        $student_stmt->store_result();

        if ($student_stmt->num_rows > 0) {

            $student_stmt->bind_result(
                $database_student_id,
                $database_roll_no,
                $database_name,
                $database_email,
                $database_phone,
                $database_gender,
                $database_class_name
            );

            $student_stmt->fetch();

            $student = [
                'id' => (int) $database_student_id,
                'roll_no' => (string) $database_roll_no,
                'name' => (string) $database_name,
                'email' => (string) $database_email,
                'phone' => (string) $database_phone,
                'gender' => (string) $database_gender,
                'class_name' => (string) $database_class_name
            ];
        }

        $student_stmt->close();

    } catch (mysqli_sql_exception $e) {

        error_log(
            'Class Management System - Attendance report student error: ' .
            $e->getMessage()
        );

        $database_error =
            'Unable to load student information. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Attendance Data
|--------------------------------------------------------------------------
*/
if (
    $student !== null &&
    $database_error === ''
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Attendance Summary
        |--------------------------------------------------------------------------
        | Calculate totals directly in MySQL.
        |--------------------------------------------------------------------------
        */
        $summary_stmt = $conn->prepare(
            'SELECT
                COUNT(*) AS total_days,
                COALESCE(
                    SUM(
                        CASE
                            WHEN status = "Present" THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS present_days,
                COALESCE(
                    SUM(
                        CASE
                            WHEN status = "Absent" THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS absent_days
             FROM attendance
             WHERE student_id = ?'
        );

        $summary_stmt->bind_param(
            'i',
            $student_id
        );

        $summary_stmt->execute();

        $summary_stmt->bind_result(
            $database_total_days,
            $database_present_days,
            $database_absent_days
        );

        if ($summary_stmt->fetch()) {

            $total_attendance = (int) $database_total_days;
            $total_present = (int) $database_present_days;
            $total_absent = (int) $database_absent_days;
        }

        $summary_stmt->close();

        /*
        |--------------------------------------------------------------------------
        | Fetch Daily Attendance Records
        |--------------------------------------------------------------------------
        */
        $attendance_stmt = $conn->prepare(
            'SELECT
                id,
                attendance_date,
                status
             FROM attendance
             WHERE student_id = ?
             ORDER BY attendance_date DESC, id DESC'
        );

        $attendance_stmt->bind_param(
            'i',
            $student_id
        );

        $attendance_stmt->execute();
        $attendance_stmt->store_result();

        $attendance_stmt->bind_result(
            $attendance_record_id,
            $attendance_date,
            $attendance_status
        );

        while ($attendance_stmt->fetch()) {

            $attendance_records[] = [
                'id' => (int) $attendance_record_id,
                'attendance_date' => (string) $attendance_date,
                'status' => (string) $attendance_status
            ];
        }

        $attendance_stmt->close();

        /*
        |--------------------------------------------------------------------------
        | Calculate Attendance Percentage
        |--------------------------------------------------------------------------
        */
        if ($total_attendance > 0) {

            $attendance_percentage =
                ($total_present / $total_attendance) * 100;
        }

        /*
        |--------------------------------------------------------------------------
        | Determine Attendance Level
        |--------------------------------------------------------------------------
        */
        if ($total_attendance === 0) {

            $attendance_level = 'No Attendance';
            $progress_class = 'none';

        } elseif ($attendance_percentage >= 75) {

            $attendance_level = 'Good Attendance';
            $progress_class = 'good';

        } elseif ($attendance_percentage >= 60) {

            $attendance_level = 'Average Attendance';
            $progress_class = 'average';

        } else {

            $attendance_level = 'Low Attendance';
            $progress_class = 'low';
        }

    } catch (mysqli_sql_exception $e) {

        error_log(
            'Class Management System - Attendance report records error: ' .
            $e->getMessage()
        );

        $database_error =
            'Unable to load attendance records. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Safe Progress Percentage
|--------------------------------------------------------------------------
*/
$progress_percentage = min(
    100,
    max(
        0,
        $attendance_percentage
    )
);

/*
|--------------------------------------------------------------------------
| Student Initial
|--------------------------------------------------------------------------
*/
$student_initial = '?';

if (
    $student !== null &&
    $student['name'] !== ''
) {

    $student_initial = strtoupper(
        mb_substr(
            $student['name'],
            0,
            1,
            'UTF-8'
        )
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

    <meta
        name="robots"
        content="noindex, nofollow"
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
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
        }

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
            border-radius: 8px;
        }

        .form-select:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.15);
        }

        .view-button {
            min-height: 46px;
            font-weight: 600;
            border-radius: 8px;
        }

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
            background: rgba(255, 255, 255, 0.18);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 40px;
        }

        .attendance-content {
            padding: 30px;
        }

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
                <?php echo e($progress_percentage); ?>%,
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

        .progress-none {
            background: #94a3b8;
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

        .empty-report {
            background: white;
            border-radius: 18px;
            padding: 45px 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(15, 23, 42, 0.06);
        }

        .empty-report-icon {
            font-size: 50px;
            color: #94a3b8;
        }

        .empty-report h4 {
            margin-top: 15px;
            font-weight: 700;
        }

        .report-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        .print-date {
            display: none;
        }

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

            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .top-actions {
                width: 100%;
            }

            .top-actions .btn {
                flex: 1;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .student-summary {
                align-items: flex-start;
            }

            .attendance-title {
                font-size: 24px;
            }

            .student-summary p {
                line-height: 1.7;
            }

            .selection-card {
                padding: 18px;
            }
        }

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

            .print-date {
                display: block;
                margin-top: 5px;
            }

            .report-footer {
                margin-top: 20px;
            }
        }

    </style>

</head>

<body>

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


    <div class="page-header no-print">

        <h1>

            <i class="bi bi-calendar2-week-fill text-success"></i>

            Attendance Tracking

        </h1>

        <p>
            Daily attendance history and attendance percentage
        </p>

    </div>


    <?php if ($database_error !== ''): ?>

        <div
            class="alert alert-danger no-print"
            role="alert"
        >

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?php echo e($database_error); ?>

        </div>

    <?php endif; ?>


    <div class="selection-card no-print">

        <div class="selection-title">

            <i class="bi bi-person-check-fill text-success"></i>

            Select Student

        </div>

        <form
            method="GET"
            action="attendance_report.php"
        >

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

                        <?php foreach ($students as $s): ?>

                            <option
                                value="<?php echo (int) $s['id']; ?>"
                                <?php
                                echo (
                                    $student_id ===
                                    (int) $s['id']
                                )
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php
                                echo e(
                                    $s['roll_no'] .
                                    ' - ' .
                                    $s['name'] .
                                    ' - ' .
                                    $s['class_name']
                                );
                                ?>
                            </option>

                        <?php endforeach; ?>

                        <?php if (empty($students)): ?>

                            <option
                                value=""
                                disabled
                            >
                                No students available
                            </option>

                        <?php endif; ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-success w-100 view-button"
                        <?php
                        echo empty($students)
                            ? 'disabled'
                            : '';
                        ?>
                    >

                        <i class="bi bi-calendar-check-fill"></i>

                        View

                    </button>

                </div>

            </div>

        </form>

    </div>


    <?php if ($student !== null && $database_error === ''): ?>

    <div class="attendance-sheet">

        <div class="attendance-header">

            <div class="attendance-header-inner">

                <div>

                    <div class="attendance-title">
                        Student Attendance Report
                    </div>

                    <div class="attendance-subtitle">
                        Attendance history and participation summary
                    </div>

                    <div class="print-date">
                        Generated on: <?php echo e($generated_date); ?>
                    </div>

                </div>

                <div class="calendar-icon">

                    <i class="bi bi-calendar-check-fill"></i>

                </div>

            </div>

        </div>


        <div class="attendance-content">


            <div class="student-summary">

                <div class="student-avatar">

                    <?php echo e($student_initial); ?>

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


            <div class="overview-grid">


                <div class="overview-card total">

                    <div class="overview-icon">
                        <i class="bi bi-calendar3"></i>
                    </div>

                    <div class="overview-label">
                        Total Days
                    </div>

                    <div class="overview-number">
                        <?php echo $total_attendance; ?>
                    </div>

                </div>


                <div class="overview-card present">

                    <div class="overview-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>

                    <div class="overview-label">
                        Present
                    </div>

                    <div class="overview-number text-success">
                        <?php echo $total_present; ?>
                    </div>

                </div>


                <div class="overview-card absent">

                    <div class="overview-icon">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>

                    <div class="overview-label">
                        Absent
                    </div>

                    <div class="overview-number text-danger">
                        <?php echo $total_absent; ?>
                    </div>

                </div>


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


            <div class="percentage-panel">

                <div class="percentage-layout">


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


                    <div class="percentage-info">

                        <h4>
                            Attendance Overview
                        </h4>

                        <?php if ($total_attendance > 0): ?>

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

                        <?php else: ?>

                            <p>
                                No attendance has been recorded
                                for this student yet.
                            </p>

                        <?php endif; ?>


                        <div class="attendance-progress">

                            <div
                                class="attendance-progress-bar
                                progress-<?php echo e($progress_class); ?>"
                                style="width: <?php echo e($progress_percentage); ?>%;"
                            ></div>

                        </div>


                        <span
                            class="attendance-status
                            status-<?php echo e($progress_class); ?>"
                        >

                            <?php if ($progress_class === 'good'): ?>

                                <i class="bi bi-check-circle-fill"></i>

                            <?php elseif ($progress_class === 'average'): ?>

                                <i class="bi bi-exclamation-circle-fill"></i>

                            <?php elseif ($progress_class === 'low'): ?>

                                <i class="bi bi-exclamation-triangle-fill"></i>

                            <?php else: ?>

                                <i class="bi bi-dash-circle-fill"></i>

                            <?php endif; ?>

                            <?php echo e($attendance_level); ?>

                        </span>

                    </div>

                </div>

            </div>


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

                        <?php if (!empty($attendance_records)): ?>

                            <?php foreach (
                                $attendance_records
                                as $index => $attendance
                            ): ?>

                                <tr>

                                    <td>
                                        <?php echo $index + 1; ?>
                                    </td>

                                    <td class="date-cell">

                                        <i
                                            class="bi bi-calendar-event date-icon"
                                        ></i>

                                        <?php
                                        echo e(
                                            formatDate(
                                                $attendance[
                                                    'attendance_date'
                                                ]
                                            )
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        $record_status =
                                            $attendance['status'];
                                        ?>

                                        <?php if ($record_status === 'Present'): ?>

                                            <span
                                                class="attendance-badge present-badge"
                                            >

                                                <i class="bi bi-check-circle-fill"></i>

                                                Present

                                            </span>

                                        <?php elseif ($record_status === 'Absent'): ?>

                                            <span
                                                class="attendance-badge absent-badge"
                                            >

                                                <i class="bi bi-x-circle-fill"></i>

                                                Absent

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="attendance-badge other-badge"
                                            >

                                                <?php
                                                echo e(
                                                    $record_status
                                                );
                                                ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

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

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <div class="report-footer">

                <div>

                    <i class="bi bi-shield-check"></i>

                    Generated by Class Management System

                </div>

                <div class="mt-1">

                    Student Attendance Tracking Report

                </div>

                <div class="mt-1">

                    Report Date:
                    <?php echo e($generated_date); ?>

                </div>

            </div>


        </div>

    </div>


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


    <?php elseif (
        $student_id > 0 &&
        $database_error === ''
    ): ?>

        <div class="empty-report">

            <div class="empty-report-icon">

                <i class="bi bi-person-x-fill"></i>

            </div>

            <h4>
                Student Not Found
            </h4>

            <p class="text-muted">
                The selected student could not be found.
            </p>

            <a
                href="attendance_report.php"
                class="btn btn-success"
            >

                <i class="bi bi-arrow-left"></i>

                Select Another Student

            </a>

        </div>


    <?php elseif (
        $student_id === 0 &&
        $database_error === ''
    ): ?>

        <div class="empty-report">

            <div class="empty-report-icon">

                <i class="bi bi-person-check-fill"></i>

            </div>

            <h4>
                Select a Student
            </h4>

            <p class="text-muted">

                Choose a student from the list above
                to view their attendance report.

            </p>

        </div>

    <?php endif; ?>


</div>

</body>

</html>