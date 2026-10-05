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
| Validate Student ID
|--------------------------------------------------------------------------
*/
$student_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $student_id === false ||
    $student_id === null ||
    $student_id <= 0
) {
    header('Location: students.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$student = null;

$attendance_records = [];
$marks_records = [];

$total_classes = 0;
$present_count = 0;
$absent_count = 0;
$attendance_percentage = 0.0;

$total_marks = 0.0;
$marks_count = 0;
$marks_average = 0.0;

$database_error = '';

/*
|--------------------------------------------------------------------------
| Get Student Details
|--------------------------------------------------------------------------
*/
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
            $db_id,
            $db_roll_no,
            $db_name,
            $db_email,
            $db_phone,
            $db_gender,
            $db_class_name
        );

        $stmt->fetch();

        $student = [
            'id' => $db_id,
            'roll_no' => $db_roll_no,
            'name' => $db_name,
            'email' => $db_email,
            'phone' => $db_phone,
            'gender' => $db_gender,
            'class_name' => $db_class_name
        ];
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Student report - student lookup error: ' .
        $e->getMessage()
    );

    $database_error = 'Unable to load the student report right now.';
}

if ($student === null) {

    if ($database_error !== '') {

        http_response_code(500);

    } else {

        header('Location: students.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Attendance Records
|--------------------------------------------------------------------------
*/
if ($database_error === '') {

    try {

        $stmt = $conn->prepare(
            'SELECT id, attendance_date, status
             FROM attendance
             WHERE student_id = ?
             ORDER BY attendance_date DESC, id DESC'
        );

        $stmt->bind_param('i', $student_id);
        $stmt->execute();

        $stmt->bind_result(
            $attendance_id,
            $attendance_date,
            $attendance_status
        );

        while ($stmt->fetch()) {

            $attendance_records[] = [
                'id' => $attendance_id,
                'attendance_date' => $attendance_date,
                'status' => $attendance_status
            ];

            $total_classes++;

            if (
                strcasecmp(
                    trim((string) $attendance_status),
                    'Present'
                ) === 0
            ) {

                $present_count++;

            } elseif (
                strcasecmp(
                    trim((string) $attendance_status),
                    'Absent'
                ) === 0
            ) {

                $absent_count++;
            }
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        error_log(
            'Student report - attendance error: ' .
            $e->getMessage()
        );

        $database_error = 'Unable to load attendance records right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Attendance Percentage
|--------------------------------------------------------------------------
*/
if ($total_classes > 0) {

    $attendance_percentage =
        ($present_count / $total_classes) * 100;

    $attendance_percentage = min(
        100,
        max(0, $attendance_percentage)
    );
}

/*
|--------------------------------------------------------------------------
| Marks Records
|--------------------------------------------------------------------------
*/
if ($database_error === '') {

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
            $marks_count++;
        }

        $stmt->close();

    } catch (mysqli_sql_exception $e) {

        error_log(
            'Student report - marks error: ' .
            $e->getMessage()
        );

        $database_error = 'Unable to load marks records right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Marks Average
|--------------------------------------------------------------------------
*/
if ($marks_count > 0) {

    $marks_average =
        $total_marks / $marks_count;
}

/*
|--------------------------------------------------------------------------
| Student Initial
|--------------------------------------------------------------------------
*/
$student_initial = strtoupper(
    substr(
        trim((string) $student['name']),
        0,
        1
    )
);

if ($student_initial === '') {
    $student_initial = '?';
}

/*
|--------------------------------------------------------------------------
| Attendance Level
|--------------------------------------------------------------------------
*/
$attendance_level = 'No Data';

if ($total_classes > 0) {

    if ($attendance_percentage >= 75) {

        $attendance_level = 'Good';

    } elseif ($attendance_percentage >= 60) {

        $attendance_level = 'Average';

    } else {

        $attendance_level = 'Low';
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

    <title>
        Student Report -
        <?php echo e($student['name']); ?>
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

        body {
            background-color: #f5f7fb;
            color: #1f2937;
        }

        .page-title {
            font-weight: 700;
        }

        .profile-card,
        .stat-card,
        .table-card {
            background: #ffffff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .profile-header {
            background: #0d6efd;
            color: #ffffff;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }

        .profile-icon {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            background: #ffffff;
            color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .stat-card {
            padding: 20px;
            text-align: center;
            height: 100%;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
        }

        .info-label {
            color: #777777;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 17px;
            word-break: break-word;
        }

        .table-card {
            padding: 20px;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 20px;
        }

        .attendance-progress {
            height: 10px;
            border-radius: 10px;
        }

        .status-good {
            color: #198754;
            font-weight: 700;
        }

        .status-average {
            color: #fd7e14;
            font-weight: 700;
        }

        .status-low {
            color: #dc3545;
            font-weight: 700;
        }

        .status-none {
            color: #6c757d;
            font-weight: 700;
        }

        @media (max-width: 576px) {

            .page-header {
                align-items: flex-start !important;
            }

            .page-header .btn {
                margin-top: 5px;
            }

            .profile-header {
                padding: 25px 20px;
            }

            .table-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 26px;
            }

        }

        @media print {

            body {
                background: #ffffff;
            }

            .no-print {
                display: none !important;
            }

            .container {
                max-width: 100%;
                margin: 0 !important;
                padding: 0 !important;
            }

            .profile-card,
            .stat-card,
            .table-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .profile-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

    </style>

</head>

<body>

<div class="container mt-4 mb-5">

    <!-- PAGE HEADER -->

    <div class="page-header d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">

        <div>

            <h2 class="page-title mb-1">
                Student Profile & Report
            </h2>

            <p class="text-muted mb-0">
                Complete academic information
            </p>

        </div>

        <div class="d-flex gap-2 flex-wrap">

            <a
                href="students.php"
                class="btn btn-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Students
            </a>

            <a
                href="edit_student.php?id=<?php echo (int) $student['id']; ?>"
                class="btn btn-warning"
            >
                <i class="bi bi-pencil-square"></i>
                Edit Student
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary"
            >
                <i class="bi bi-printer-fill"></i>
                Print
            </button>

        </div>

    </div>

    <?php if ($database_error !== '') { ?>

        <div class="alert alert-danger no-print">

            <i class="bi bi-exclamation-triangle-fill"></i>

            <?php echo e($database_error); ?>

        </div>

    <?php } ?>

    <!-- STUDENT PROFILE -->

    <div class="card profile-card mb-4">

        <div class="profile-header">

            <div class="profile-icon">

                <?php echo e($student_initial); ?>

            </div>

            <h3 class="mb-1">

                <?php echo e($student['name']); ?>

            </h3>

            <p class="mb-0">

                Roll No:
                <?php echo e($student['roll_no']); ?>

            </p>

        </div>

        <div class="card-body p-4">

            <h4 class="section-title">
                <i class="bi bi-person-vcard-fill text-primary"></i>
                Personal Information
            </h4>

            <div class="row g-4">

                <div class="col-md-6">

                    <div class="info-label">
                        Roll Number
                    </div>

                    <div class="info-value">
                        <?php echo e($student['roll_no']); ?>
                    </div>

                </div>

                <div class="col-md-6">

                    <div class="info-label">
                        Full Name
                    </div>

                    <div class="info-value">
                        <?php echo e($student['name']); ?>
                    </div>

                </div>

                <div class="col-md-6">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?php
                        echo e(
                            $student['email'] !== ''
                                ? $student['email']
                                : 'Not provided'
                        );
                        ?>

                    </div>

                </div>

                <div class="col-md-6">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="info-value">

                        <?php
                        echo e(
                            $student['phone'] !== ''
                                ? $student['phone']
                                : 'Not provided'
                        );
                        ?>

                    </div>

                </div>

                <div class="col-md-6">

                    <div class="info-label">
                        Gender
                    </div>

                    <div class="info-value">

                        <?php
                        echo e(
                            $student['gender'] !== ''
                                ? $student['gender']
                                : 'Not provided'
                        );
                        ?>

                    </div>

                </div>

                <div class="col-md-6">

                    <div class="info-label">
                        Class
                    </div>

                    <div class="info-value">
                        <?php echo e($student['class_name']); ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- ATTENDANCE STATISTICS -->

    <div class="row g-4 mb-4">

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Total Classes
                </h6>

                <div class="stat-number">
                    <?php echo $total_classes; ?>
                </div>

            </div>

        </div>

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Present
                </h6>

                <div class="stat-number text-success">
                    <?php echo $present_count; ?>
                </div>

            </div>

        </div>

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Absent
                </h6>

                <div class="stat-number text-danger">
                    <?php echo $absent_count; ?>
                </div>

            </div>

        </div>

        <div class="col-md-3 col-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Attendance %
                </h6>

                <div class="stat-number text-primary">

                    <?php
                    echo number_format(
                        $attendance_percentage,
                        1
                    );
                    ?>%

                </div>

            </div>

        </div>

    </div>

    <!-- ATTENDANCE PROGRESS -->

    <div class="card table-card mb-4">

        <h5 class="section-title">

            <i class="bi bi-calendar-check text-primary"></i>
            Attendance Progress

        </h5>

        <div class="progress attendance-progress">

            <div
                class="progress-bar
                <?php
                if ($total_classes === 0) {
                    echo 'bg-secondary';
                } elseif ($attendance_percentage >= 75) {
                    echo 'bg-success';
                } elseif ($attendance_percentage >= 60) {
                    echo 'bg-warning';
                } else {
                    echo 'bg-danger';
                }
                ?>"
                role="progressbar"
                style="width: <?php echo e((string) $attendance_percentage); ?>%;"
                aria-valuenow="<?php echo e((string) round($attendance_percentage, 1)); ?>"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>

        </div>

        <div class="d-flex justify-content-between mt-2">

            <small class="text-muted">
                0%
            </small>

            <small>

                <?php
                $attendance_class = 'status-none';

                if ($total_classes > 0) {

                    if ($attendance_percentage >= 75) {
                        $attendance_class = 'status-good';
                    } elseif ($attendance_percentage >= 60) {
                        $attendance_class = 'status-average';
                    } else {
                        $attendance_class = 'status-low';
                    }
                }
                ?>

                <span class="<?php echo e($attendance_class); ?>">

                    <?php
                    echo number_format(
                        $attendance_percentage,
                        1
                    );
                    ?>%

                    -
                    <?php echo e($attendance_level); ?>

                </span>

            </small>

            <small class="text-muted">
                100%
            </small>

        </div>

    </div>

    <!-- MARKS STATISTICS -->

    <div class="row g-4 mb-4">

        <div class="col-md-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Total Marks
                </h6>

                <div class="stat-number text-info">

                    <?php
                    echo number_format(
                        $total_marks,
                        2
                    );
                    ?>

                </div>

                <small class="text-muted">
                    <?php echo $marks_count; ?> subject(s)
                </small>

            </div>

        </div>

        <div class="col-md-6">

            <div class="stat-card">

                <h6 class="text-muted">
                    Average Marks
                </h6>

                <div class="stat-number text-success">

                    <?php
                    echo number_format(
                        $marks_average,
                        2
                    );
                    ?>

                </div>

                <small class="text-muted">
                    Out of 100
                </small>

            </div>

        </div>

    </div>

    <!-- ATTENDANCE RECORDS -->

    <div class="table-card mb-4">

        <h4 class="section-title">

            <i class="bi bi-calendar3 text-primary"></i>
            Attendance Records

        </h4>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th>#</th>
                        <th>Date</th>
                        <th>Status</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($attendance_records) > 0) { ?>

                    <?php $attendance_sr = 1; ?>

                    <?php foreach ($attendance_records as $attendance) { ?>

                        <?php
                        $status = trim(
                            (string) $attendance['status']
                        );
                        ?>

                        <tr>

                            <td>
                                <?php echo $attendance_sr++; ?>
                            </td>

                            <td>
                                <?php
                                echo e(
                                    $attendance['attendance_date']
                                );
                                ?>
                            </td>

                            <td>

                                <?php if (
                                    strcasecmp(
                                        $status,
                                        'Present'
                                    ) === 0
                                ) { ?>

                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle-fill"></i>
                                        Present
                                    </span>

                                <?php } elseif (
                                    strcasecmp(
                                        $status,
                                        'Absent'
                                    ) === 0
                                ) { ?>

                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle-fill"></i>
                                        Absent
                                    </span>

                                <?php } else { ?>

                                    <span class="badge bg-secondary">
                                        <?php echo e($status); ?>
                                    </span>

                                <?php } ?>

                            </td>

                        </tr>

                    <?php } ?>

                <?php } else { ?>

                    <tr>

                        <td
                            colspan="3"
                            class="text-center text-muted p-4"
                        >

                            <i class="bi bi-calendar-x"></i>

                            No attendance records found.

                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- MARKS RECORDS -->

    <div class="table-card">

        <h4 class="section-title">

            <i class="bi bi-bar-chart-fill text-primary"></i>
            Marks Records

        </h4>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th>#</th>
                        <th>Subject</th>
                        <th>Marks</th>
                        <th>Performance</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($marks_records) > 0) { ?>

                    <?php $marks_sr = 1; ?>

                    <?php foreach ($marks_records as $mark) { ?>

                        <?php
                        $mark_value = (float) $mark['marks'];
                        $mark_pass = $mark_value >= 40;
                        ?>

                        <tr>

                            <td>
                                <?php echo $marks_sr++; ?>
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
                                    class="badge
                                    <?php
                                    echo $mark_pass
                                        ? 'bg-success'
                                        : 'bg-danger';
                                    ?>"
                                >

                                    <?php
                                    echo e($mark['marks']);
                                    ?>
                                    / 100

                                </span>

                            </td>

                            <td>

                                <?php if ($mark_pass) { ?>

                                    <span class="text-success fw-bold">

                                        <i class="bi bi-check-circle-fill"></i>
                                        Pass

                                    </span>

                                <?php } else { ?>

                                    <span class="text-danger fw-bold">

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
                            class="text-center text-muted p-4"
                        >

                            <i class="bi bi-journal-x"></i>

                            No marks records found.

                        </td>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

    <div class="text-center text-muted small mt-4 no-print">

        <i class="bi bi-shield-check"></i>

        Generated by Class Management System

        &middot;

        <?php echo date('Y'); ?>

    </div>

</div>

</body>

</html>