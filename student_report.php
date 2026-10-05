<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] === '') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Helper
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

/*
|--------------------------------------------------------------------------
| Get Student ID
|--------------------------------------------------------------------------
*/
$student_id = filter_input(
    INPUT_GET,
    'student_id',
    FILTER_VALIDATE_INT
);

if ($student_id === false || $student_id === null || $student_id <= 0) {
    $student_id = 0;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$students = [];
$student = null;
$marks_records = [];

$total_marks = 0.0;
$total_subjects = 0;
$percentage = 0.0;

$result_status = '';
$performance_label = 'No Data';

$database_error = '';

/*
|--------------------------------------------------------------------------
| Fetch Students
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'SELECT id, roll_no, name, class_name
         FROM students
         ORDER BY name ASC, id ASC'
    );

    $stmt->execute();

    $stmt->bind_result(
        $list_id,
        $list_roll_no,
        $list_name,
        $list_class_name
    );

    while ($stmt->fetch()) {

        $students[] = [
            'id' => $list_id,
            'roll_no' => $list_roll_no,
            'name' => $list_name,
            'class_name' => $list_class_name
        ];
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Student report - student list error: ' .
        $e->getMessage()
    );

    $database_error = 'Unable to load the student list right now.';
}

/*
|--------------------------------------------------------------------------
| Fetch Selected Student
|--------------------------------------------------------------------------
*/
if ($student_id > 0 && $database_error === '') {

    try {

        $stmt = $conn->prepare(
            'SELECT id, roll_no, name, email, phone, gender, class_name
             FROM students
             WHERE id = ?
             LIMIT 1'
        );

        $stmt->bind_param('i', $student_id);
        $stmt->execute();

        $stmt->store_result();

        if ($stmt->num_rows === 1) {

            $stmt->bind_result(
                $student_db_id,
                $student_roll_no,
                $student_name,
                $student_email,
                $student_phone,
                $student_gender,
                $student_class_name
            );

            $stmt->fetch();

            $student = [
                'id' => $student_db_id,
                'roll_no' => $student_roll_no,
                'name' => $student_name,
                'email' => $student_email,
                'phone' => $student_phone,
                'gender' => $student_gender,
                'class_name' => $student_class_name
            ];
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        error_log(
            'Student report - student information error: ' .
            $e->getMessage()
        );

        $database_error = 'Unable to load student information right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Marks
|--------------------------------------------------------------------------
*/
if ($student !== null && $database_error === '') {

    try {

        $stmt = $conn->prepare(
            'SELECT id, subject_name, marks
             FROM marks
             WHERE student_id = ?
             ORDER BY subject_name ASC, id ASC'
        );

        $stmt->bind_param('i', $student_id);
        $stmt->execute();

        $stmt->bind_result(
            $mark_id,
            $mark_subject_name,
            $mark_value
        );

        while ($stmt->fetch()) {

            $marks_records[] = [
                'id' => $mark_id,
                'subject_name' => $mark_subject_name,
                'marks' => $mark_value
            ];

            $total_marks += (float) $mark_value;
            $total_subjects++;
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        error_log(
            'Student report - marks error: ' .
            $e->getMessage()
        );

        $database_error = 'Unable to load marks right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Calculate Percentage
|--------------------------------------------------------------------------
*/
if ($total_subjects > 0) {

    $percentage = (
        $total_marks /
        ($total_subjects * 100)
    ) * 100;

    $percentage = min(
        100,
        max(0, $percentage)
    );
}

/*
|--------------------------------------------------------------------------
| Result
|--------------------------------------------------------------------------
*/
if ($total_subjects > 0) {

    $result_status = ($percentage >= 40)
        ? 'PASS'
        : 'FAIL';
}

/*
|--------------------------------------------------------------------------
| Performance Label
|--------------------------------------------------------------------------
*/
if ($total_subjects > 0) {

    if ($percentage >= 75) {

        $performance_label = 'Excellent';

    } elseif ($percentage >= 60) {

        $performance_label = 'Good';

    } elseif ($percentage >= 40) {

        $performance_label = 'Satisfactory';

    } else {

        $performance_label = 'Needs Improvement';
    }
}

/*
|--------------------------------------------------------------------------
| Admin Name
|--------------------------------------------------------------------------
*/
$admin_name = $_SESSION['admin'] ?? 'Administrator';

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

    <title>Student Performance Report | Class Management System</title>

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
            background: #eef2f7;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .top-bar {
            background: #111827;
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .brand {
            font-size: 19px;
            font-weight: 700;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            align-items: center;
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
            border-left: 6px solid #2563eb;
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
        }

        .view-button {
            min-height: 46px;
            font-weight: 600;
        }

        .report-sheet {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
        }

        .academic-header {
            background: linear-gradient(
                135deg,
                #1d4ed8,
                #2563eb,
                #3b82f6
            );
            color: white;
            padding: 35px;
        }

        .academic-header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .academic-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .academic-subtitle {
            opacity: 0.9;
        }

        .academic-icon {
            width: 75px;
            height: 75px;
            border-radius: 18px;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            flex-shrink: 0;
        }

        .report-content {
            padding: 30px;
        }

        .student-profile {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .profile-item {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 10px 0;
        }

        .profile-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }

        .profile-label {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 2px;
        }

        .profile-value {
            font-weight: 700;
            word-break: break-word;
        }

        .performance-overview {
            margin-bottom: 28px;
        }

        .performance-card {
            height: 100%;
            border-radius: 16px;
            padding: 22px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .performance-card.blue {
            border-top: 5px solid #2563eb;
        }

        .performance-card.green {
            border-top: 5px solid #16a34a;
        }

        .performance-card.orange {
            border-top: 5px solid #f59e0b;
        }

        .performance-card h6 {
            color: #6b7280;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .performance-number {
            font-size: 30px;
            font-weight: 800;
            margin-top: 8px;
        }

        .meter-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 28px;
        }

        .meter-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .performance-progress {
            height: 14px;
            border-radius: 20px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .performance-progress-bar {
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

        .result-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        .result-title {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .result-value {
            font-size: 25px;
            font-weight: 800;
        }

        .result-pass {
            color: #15803d;
        }

        .result-fail {
            color: #dc2626;
        }

        .marks-section {
            margin-top: 10px;
        }

        .marks-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
        }

        .marks-heading h4 {
            font-size: 19px;
            font-weight: 800;
            margin: 0;
        }

        .table-container {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
        }

        .marks-table {
            margin: 0;
        }

        .marks-table thead th {
            background: #111827;
            color: white;
            padding: 14px;
            border: none;
        }

        .marks-table tbody td {
            padding: 14px;
            vertical-align: middle;
        }

        .marks-value {
            font-weight: 800;
        }

        .marks-good {
            color: #15803d;
        }

        .marks-low {
            color: #dc2626;
        }

        .performance-badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }

        .badge-good {
            background: #dcfce7;
            color: #166534;
        }

        .badge-low {
            background: #fee2e2;
            color: #991b1b;
        }

        .report-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 13px;
        }

        @media (max-width: 768px) {

            .top-bar {
                padding: 13px 16px;
                flex-direction: column;
                align-items: flex-start;
            }

            .top-actions {
                width: 100%;
            }

            .top-actions .btn {
                flex: 1;
            }

            .page-wrapper {
                margin-top: 18px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .academic-header {
                padding: 25px;
            }

            .academic-header-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .academic-title {
                font-size: 23px;
            }

            .report-content {
                padding: 20px;
            }

            .result-section {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }

            .marks-heading {
                align-items: flex-start;
                flex-direction: column;
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

            .report-sheet {
                box-shadow: none;
                border-radius: 0;
            }

            .academic-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-content {
                padding: 20px;
            }

            .table-container {
                border: 1px solid #ccc;
            }
        }

    </style>

</head>

<body>

<div class="top-bar no-print">

    <div class="brand">
        <i class="bi bi-mortarboard-fill"></i>
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
            href="students.php"
            class="btn btn-light btn-sm"
        >
            <i class="bi bi-people"></i>
            Students
        </a>

    </div>

</div>

<div class="page-wrapper">

    <div class="page-header no-print">

        <h1>
            <i class="bi bi-mortarboard-fill text-primary"></i>
            Student Performance
        </h1>

        <p>
            Academic marks, percentage and result analysis
        </p>

    </div>

    <?php if ($database_error !== '') { ?>

        <div class="alert alert-danger no-print">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?php echo e($database_error); ?>

        </div>

    <?php } ?>

    <div class="selection-card no-print">

        <div class="selection-title">

            <i class="bi bi-person-check-fill text-primary"></i>
            Select Student

        </div>

        <form method="GET" action="student_report.php">

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

                        <?php foreach ($students as $s) { ?>

                            <option
                                value="<?php echo (int) $s['id']; ?>"
                                <?php
                                echo (
                                    $student_id === (int) $s['id']
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
                                    ' (' .
                                    $s['class_name'] .
                                    ')'
                                );
                                ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <button
                        type="submit"
                        class="btn btn-primary w-100 view-button"
                    >
                        <i class="bi bi-eye-fill"></i>
                        View Report
                    </button>

                </div>

            </div>

        </form>

    </div>

    <?php if ($student !== null) { ?>

        <div class="report-sheet">

            <div class="academic-header">

                <div class="academic-header-inner">

                    <div>

                        <div class="academic-title">
                            Student Performance Report
                        </div>

                        <div class="academic-subtitle">
                            Academic Performance Summary
                        </div>

                    </div>

                    <div class="academic-icon">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>

                </div>

            </div>

            <div class="report-content">

                <div class="student-profile">

                    <div class="section-title">

                        <i class="bi bi-person-vcard-fill text-primary"></i>
                        Student Profile

                    </div>

                    <div class="row">

                        <div class="col-md-6">

                            <div class="profile-item">

                                <div class="profile-icon">
                                    <i class="bi bi-person-fill"></i>
                                </div>

                                <div>

                                    <div class="profile-label">
                                        Student Name
                                    </div>

                                    <div class="profile-value">
                                        <?php echo e($student['name']); ?>
                                    </div>

                                </div>

                            </div>

                            <div class="profile-item">

                                <div class="profile-icon">
                                    <i class="bi bi-hash"></i>
                                </div>

                                <div>

                                    <div class="profile-label">
                                        Roll Number
                                    </div>

                                    <div class="profile-value">
                                        <?php echo e($student['roll_no']); ?>
                                    </div>

                                </div>

                            </div>

                            <div class="profile-item">

                                <div class="profile-icon">
                                    <i class="bi bi-building"></i>
                                </div>

                                <div>

                                    <div class="profile-label">
                                        Class
                                    </div>

                                    <div class="profile-value">
                                        <?php echo e($student['class_name']); ?>
                                    </div>

                                </div>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="profile-item">

                                <div class="profile-icon">
                                    <i class="bi bi-envelope-fill"></i>
                                </div>

                                <div>

                                    <div class="profile-label">
                                        Email
                                    </div>

                                    <div class="profile-value">
                                        <?php
                                        echo e(
                                            $student['email'] !== ''
                                                ? $student['email']
                                                : 'Not provided'
                                        );
                                        ?>
                                    </div>

                                </div>

                            </div>

                            <div class="profile-item">

                                <div class="profile-icon">
                                    <i class="bi bi-telephone-fill"></i>
                                </div>

                                <div>

                                    <div class="profile-label">
                                        Phone
                                    </div>

                                    <div class="profile-value">
                                        <?php
                                        echo e(
                                            $student['phone'] !== ''
                                                ? $student['phone']
                                                : 'Not provided'
                                        );
                                        ?>
                                    </div>

                                </div>

                            </div>

                            <div class="profile-item">

                                <div class="profile-icon">
                                    <i class="bi bi-gender-ambiguous"></i>
                                </div>

                                <div>

                                    <div class="profile-label">
                                        Gender
                                    </div>

                                    <div class="profile-value">
                                        <?php
                                        echo e(
                                            $student['gender'] !== ''
                                                ? $student['gender']
                                                : 'Not provided'
                                        );
                                        ?>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="performance-overview">

                    <div class="section-title">

                        <i class="bi bi-graph-up-arrow text-primary"></i>
                        Performance Overview

                    </div>

                    <div class="row g-3">

                        <div class="col-md-4">

                            <div class="performance-card blue">

                                <h6>
                                    <i class="bi bi-book-fill"></i>
                                    Subjects
                                </h6>

                                <div class="performance-number">
                                    <?php echo $total_subjects; ?>
                                </div>

                                <small class="text-muted">
                                    Subjects with marks
                                </small>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="performance-card orange">

                                <h6>
                                    <i class="bi bi-calculator-fill"></i>
                                    Total Marks
                                </h6>

                                <div class="performance-number">

                                    <?php
                                    echo number_format(
                                        $total_marks,
                                        2
                                    );
                                    ?>

                                </div>

                                <small class="text-muted">
                                    Out of
                                    <?php
                                    echo $total_subjects * 100;
                                    ?>
                                </small>

                            </div>

                        </div>

                        <div class="col-md-4">

                            <div class="performance-card green">

                                <h6>
                                    <i class="bi bi-percent"></i>
                                    Percentage
                                </h6>

                                <div class="performance-number">

                                    <?php
                                    echo number_format(
                                        $percentage,
                                        2
                                    );
                                    ?>%

                                </div>

                                <small class="text-muted">
                                    Overall academic score
                                </small>

                            </div>

                        </div>

                    </div>

                </div>

                <?php if ($total_subjects > 0) { ?>

                    <div class="meter-card">

                        <div class="meter-top">

                            <span>
                                Overall Performance
                            </span>

                            <span>
                                <?php
                                echo number_format(
                                    $percentage,
                                    2
                                );
                                ?>%
                            </span>

                        </div>

                        <div class="performance-progress">

                            <div
                                class="performance-progress-bar
                                <?php
                                if ($percentage >= 60) {
                                    echo 'progress-good';
                                } elseif ($percentage >= 40) {
                                    echo 'progress-average';
                                } else {
                                    echo 'progress-low';
                                }
                                ?>"
                                style="width: <?php echo e((string) $percentage); ?>%;"
                            ></div>

                        </div>

                        <div class="mt-2 text-muted small">

                            Performance Level:

                            <strong>
                                <?php echo e($performance_label); ?>
                            </strong>

                        </div>

                    </div>

                    <div class="result-section">

                        <div>

                            <div class="result-title">
                                Final Result
                            </div>

                            <div
                                class="result-value
                                <?php
                                echo (
                                    $result_status === 'PASS'
                                )
                                    ? 'result-pass'
                                    : 'result-fail';
                                ?>"
                            >

                                <?php echo e($result_status); ?>

                            </div>

                        </div>

                        <div class="text-muted">

                            Passing percentage:
                            <strong>40%</strong>

                        </div>

                    </div>

                <?php } else { ?>

                    <div class="alert alert-info mb-4">

                        <i class="bi bi-info-circle-fill"></i>

                        No marks have been recorded for this student yet.

                    </div>

                <?php } ?>

                <div class="marks-section">

                    <div class="marks-heading">

                        <h4>

                            <i class="bi bi-journal-text text-primary"></i>
                            Subject-wise Performance

                        </h4>

                        <span class="badge bg-primary">

                            <?php echo $total_subjects; ?>
                            Subjects

                        </span>

                    </div>

                    <div class="table-container">

                        <div class="table-responsive">

                            <table class="table marks-table">

                                <thead>

                                    <tr>

                                        <th width="80">
                                            #
                                        </th>

                                        <th>
                                            Subject
                                        </th>

                                        <th width="180">
                                            Marks
                                        </th>

                                        <th width="180">
                                            Performance
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php if (count($marks_records) > 0) { ?>

                                    <?php $sr_no = 1; ?>

                                    <?php foreach ($marks_records as $mark) { ?>

                                        <?php
                                        $mark_value = (float) $mark['marks'];
                                        $is_pass = $mark_value >= 40;
                                        ?>

                                        <tr>

                                            <td>
                                                <?php echo $sr_no++; ?>
                                            </td>

                                            <td>

                                                <strong>
                                                    <?php
                                                    echo e(
                                                        $mark['subject_name']
                                                    );
                                                    ?>
                                                </strong>

                                            </td>

                                            <td>

                                                <span
                                                    class="marks-value
                                                    <?php
                                                    echo $is_pass
                                                        ? 'marks-good'
                                                        : 'marks-low';
                                                    ?>"
                                                >

                                                    <?php
                                                    echo e(
                                                        $mark['marks']
                                                    );
                                                    ?>

                                                </span>

                                                <span class="text-muted">
                                                    / 100
                                                </span>

                                            </td>

                                            <td>

                                                <?php if ($is_pass) { ?>

                                                    <span
                                                        class="performance-badge badge-good"
                                                    >
                                                        <i class="bi bi-check-circle-fill"></i>
                                                        Pass
                                                    </span>

                                                <?php } else { ?>

                                                    <span
                                                        class="performance-badge badge-low"
                                                    >
                                                        <i class="bi bi-exclamation-circle-fill"></i>
                                                        Needs Improvement
                                                    </span>

                                                <?php } ?>

                                            </td>

                                        </tr>

                                    <?php } ?>

                                <?php } else { ?>

                                    <tr>

                                        <td
                                            colspan="4"
                                            class="text-center text-muted p-5"
                                        >

                                            <i
                                                class="bi bi-journal-x"
                                                style="font-size: 35px;"
                                            ></i>

                                            <div class="mt-2">
                                                No marks available for this student.
                                            </div>

                                        </td>

                                    </tr>

                                <?php } ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

                <div class="report-footer">

                    <div>
                        <i class="bi bi-shield-check"></i>
                        Generated by Class Management System
                    </div>

                    <div class="mt-1">
                        Student Academic Performance Report
                    </div>

                </div>

            </div>

        </div>

        <div class="text-center mt-4 no-print">

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary px-4"
            >

                <i class="bi bi-printer-fill"></i>
                Print Performance Report

            </button>

        </div>

    <?php } elseif ($student_id > 0 && $database_error === '') { ?>

        <div class="alert alert-danger no-print">

            <i class="bi bi-exclamation-triangle-fill"></i>

            Student not found.

            <a
                href="student_report.php"
                class="alert-link"
            >
                Try again
            </a>

        </div>

    <?php } elseif ($student_id === 0 && $database_error === '') { ?>

        <div class="alert alert-info no-print">

            <i class="bi bi-info-circle-fill"></i>

            Please select a student to view the performance report.

        </div>

    <?php } ?>

</div>

</body>

</html>