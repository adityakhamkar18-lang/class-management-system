<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Class Management System - Mark Attendance
|--------------------------------------------------------------------------
| Purpose:
|   Add attendance for a student.
|
| Security:
|   - Admin authentication
|   - CSRF protection
|   - Prepared statements
|   - Strict server-side validation
|   - No database details exposed
|   - POST / Redirect / GET
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
| CSRF Token
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Form Variables
|--------------------------------------------------------------------------
*/
$error = '';

$selected_student = '';
$selected_date = '';
$selected_status = 'Present';

/*
|--------------------------------------------------------------------------
| Today's Date
|--------------------------------------------------------------------------
*/
$today = new DateTimeImmutable('today');
$today_string = $today->format('Y-m-d');

/*
|--------------------------------------------------------------------------
| Process Form
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Preserve Submitted Values
    |--------------------------------------------------------------------------
    */
    $selected_student = trim(
        (string) ($_POST['student_id'] ?? '')
    );

    $selected_date = trim(
        (string) ($_POST['attendance_date'] ?? '')
    );

    $selected_status = trim(
        (string) ($_POST['status'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    $submitted_csrf = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (
        $submitted_csrf === '' ||
        !hash_equals($csrf_token, $submitted_csrf)
    ) {
        $error =
            'Your session has expired or the request is invalid. Please refresh the page and try again.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Student ID
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        if (
            $selected_student === '' ||
            !ctype_digit($selected_student) ||
            (int) $selected_student <= 0
        ) {
            $error = 'Please select a valid student.';
        }
    }

    $student_id = (int) $selected_student;

    /*
    |--------------------------------------------------------------------------
    | Validate Date
    |--------------------------------------------------------------------------
    */
    $date_object = null;

    if ($error === '') {

        if ($selected_date === '') {

            $error = 'Please select an attendance date.';

        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {

            $error = 'Please enter a valid attendance date.';

        } else {

            $date_object = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $selected_date
            );

            $date_errors = DateTimeImmutable::getLastErrors();

            $date_has_errors =
                $date_errors !== false &&
                (
                    $date_errors['warning_count'] > 0 ||
                    $date_errors['error_count'] > 0
                );

            if (
                $date_object === false ||
                $date_has_errors ||
                $date_object->format('Y-m-d') !== $selected_date
            ) {
                $error = 'Please enter a valid attendance date.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Future Attendance
    |--------------------------------------------------------------------------
    */
    if (
        $error === '' &&
        $date_object instanceof DateTimeImmutable
    ) {

        if ($date_object > $today) {
            $error = 'Attendance date cannot be in the future.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        if (
            !in_array(
                $selected_status,
                ['Present', 'Absent'],
                true
            )
        ) {
            $error = 'Please select a valid attendance status.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Database Operations
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | Fetch Student
            |--------------------------------------------------------------------------
            */
            $student_stmt = $conn->prepare(
                'SELECT id, name
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

            if ($student_stmt->num_rows === 0) {

                $error = 'The selected student does not exist.';

                $student_stmt->close();

            } else {

                $student_stmt->bind_result(
                    $database_student_id,
                    $student_name
                );

                $student_stmt->fetch();
                $student_stmt->close();

                /*
                |--------------------------------------------------------------------------
                | Check Existing Attendance
                |--------------------------------------------------------------------------
                */
                $duplicate_stmt = $conn->prepare(
                    'SELECT id
                     FROM attendance
                     WHERE student_id = ?
                     AND attendance_date = ?
                     LIMIT 1'
                );

                $duplicate_stmt->bind_param(
                    'is',
                    $student_id,
                    $selected_date
                );

                $duplicate_stmt->execute();
                $duplicate_stmt->store_result();

                if ($duplicate_stmt->num_rows > 0) {

                    $error =
                        'Attendance for this student has already been marked for this date.';
                }

                $duplicate_stmt->close();

                /*
                |--------------------------------------------------------------------------
                | Insert Attendance
                |--------------------------------------------------------------------------
                */
                if ($error === '') {

                    $insert_stmt = $conn->prepare(
                        'INSERT INTO attendance
                        (
                            student_id,
                            student_name,
                            attendance_date,
                            status
                        )
                        VALUES (?, ?, ?, ?)'
                    );

                    $insert_stmt->bind_param(
                        'isss',
                        $student_id,
                        $student_name,
                        $selected_date,
                        $selected_status
                    );

                    $insert_stmt->execute();
                    $insert_stmt->close();

                    /*
                    |--------------------------------------------------------------------------
                    | Rotate CSRF Token
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['csrf_token'] = bin2hex(
                        random_bytes(32)
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Success Flash
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['attendance_add_success'] =
                        'Attendance marked successfully.';

                    /*
                    |--------------------------------------------------------------------------
                    | POST / REDIRECT / GET
                    |--------------------------------------------------------------------------
                    */
                    header('Location: attendance.php');
                    exit;
                }
            }

        } catch (mysqli_sql_exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Private Error Logging
            |--------------------------------------------------------------------------
            */
            error_log(
                'Class Management System - Mark attendance error: ' .
                $e->getMessage()
            );

            /*
            |--------------------------------------------------------------------------
            | Duplicate-Key Safety
            |--------------------------------------------------------------------------
            */
            if ((int) $e->getCode() === 1062) {

                $error =
                    'Attendance for this student has already been marked for this date.';

            } else {

                $error =
                    'Unable to save attendance right now. Please try again.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Students
|--------------------------------------------------------------------------
*/
$students = [];

try {

    $students_stmt = $conn->prepare(
        'SELECT id, name
         FROM students
         ORDER BY name ASC'
    );

    $students_stmt->execute();
    $students_stmt->store_result();

    $students_stmt->bind_result(
        $student_list_id,
        $student_list_name
    );

    while ($students_stmt->fetch()) {

        $students[] = [
            'id' => (int) $student_list_id,
            'name' => (string) $student_list_name
        ];
    }

    $students_stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Student list error: ' .
        $e->getMessage()
    );

    if ($error === '') {
        $error =
            'Unable to load the student list. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Escape Helper
|--------------------------------------------------------------------------
*/
function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
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
        Mark Attendance - Class Management System
    </title>

    <!-- Bootstrap 5.3.3 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>

        body {
            background: #f4f6f9;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
        }

        .page-wrapper {
            min-height: 100vh;
            padding: 40px 15px;
        }

        .attendance-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .card-header-custom {
            background: #dc3545;
            color: #ffffff;
            padding: 22px 25px;
        }

        .card-header-custom h4 {
            margin: 0;
            font-weight: 600;
        }

        .card-header-custom p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #343a40;
        }

        .form-control,
        .form-select {
            min-height: 46px;
            border-radius: 8px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
        }

        .btn {
            min-height: 44px;
            border-radius: 8px;
            font-weight: 500;
        }

        .empty-student-message {
            padding: 15px;
            border-radius: 8px;
            background: #fff3cd;
            color: #664d03;
            border: 1px solid #ffecb5;
        }

        .required-star {
            color: #dc3545;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 12px 15px;
            border-radius: 6px;
            font-size: 14px;
            color: #495057;
        }

        @media (max-width: 576px) {

            .page-wrapper {
                padding: 20px 10px;
            }

            .card-body {
                padding: 20px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-12 col-md-8 col-lg-6">

                <div class="card shadow attendance-card">

                    <!-- Header -->
                    <div class="card-header-custom">

                        <div class="d-flex align-items-center gap-3">

                            <i class="bi bi-calendar-check fs-3"></i>

                            <div>

                                <h4>
                                    Mark Attendance
                                </h4>

                                <p>
                                    Record attendance for a student
                                </p>

                            </div>

                        </div>

                    </div>

                    <!-- Body -->
                    <div class="card-body">

                        <?php if ($error !== ''): ?>

                            <div
                                class="alert alert-danger alert-dismissible fade show"
                                role="alert"
                            >

                                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                                <?php echo e($error); ?>

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Close"
                                ></button>

                            </div>

                        <?php endif; ?>

                        <?php if (empty($students)): ?>

                            <div class="empty-student-message mb-4">

                                <i class="bi bi-info-circle-fill me-2"></i>

                                No students are currently available.
                                Please add a student before marking attendance.

                            </div>

                        <?php endif; ?>

                        <div class="info-box mb-4">

                            <i class="bi bi-info-circle me-2"></i>

                            You can mark attendance only once for each
                            student on the same date.

                        </div>

                        <form
                            method="POST"
                            action="mark_attendance.php"
                            autocomplete="off"
                        >

                            <!-- CSRF -->
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo e($csrf_token); ?>"
                            >

                            <!-- Student -->
                            <div class="mb-4">

                                <label
                                    for="student_id"
                                    class="form-label"
                                >
                                    Student
                                    <span class="required-star">*</span>
                                </label>

                                <select
                                    id="student_id"
                                    name="student_id"
                                    class="form-select"
                                    required
                                    <?php echo empty($students) ? 'disabled' : ''; ?>
                                >

                                    <option value="">
                                        Select Student
                                    </option>

                                    <?php foreach ($students as $student): ?>

                                        <option
                                            value="<?php echo (int) $student['id']; ?>"
                                            <?php
                                            echo (
                                                (string) $selected_student ===
                                                (string) $student['id']
                                            )
                                                ? 'selected'
                                                : '';
                                            ?>
                                        >
                                            <?php
                                            echo e(
                                                (string) $student['name']
                                            );
                                            ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- Attendance Date -->
                            <div class="mb-4">

                                <label
                                    for="attendance_date"
                                    class="form-label"
                                >
                                    Attendance Date
                                    <span class="required-star">*</span>
                                </label>

                                <input
                                    type="date"
                                    id="attendance_date"
                                    name="attendance_date"
                                    class="form-control"
                                    value="<?php echo e($selected_date); ?>"
                                    max="<?php echo e($today_string); ?>"
                                    required
                                >

                                <div class="form-text">
                                    Attendance cannot be marked for a future date.
                                </div>

                            </div>

                            <!-- Status -->
                            <div class="mb-4">

                                <label
                                    for="status"
                                    class="form-label"
                                >
                                    Attendance Status
                                    <span class="required-star">*</span>
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                    class="form-select"
                                    required
                                >

                                    <option
                                        value="Present"
                                        <?php
                                        echo $selected_status === 'Present'
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        Present
                                    </option>

                                    <option
                                        value="Absent"
                                        <?php
                                        echo $selected_status === 'Absent'
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        Absent
                                    </option>

                                </select>

                            </div>

                            <!-- Buttons -->
                            <div class="d-flex gap-2 action-buttons">

                                <button
                                    type="submit"
                                    name="save"
                                    value="1"
                                    class="btn btn-success"
                                    <?php echo empty($students) ? 'disabled' : ''; ?>
                                >

                                    <i class="bi bi-check-circle me-1"></i>

                                    Save Attendance

                                </button>

                                <a
                                    href="attendance.php"
                                    class="btn btn-secondary"
                                >

                                    <i class="bi bi-arrow-left me-1"></i>

                                    Back to Attendance

                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- Bootstrap JS -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
