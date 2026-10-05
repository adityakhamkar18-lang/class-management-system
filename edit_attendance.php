
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Class Management System - Edit Attendance
|--------------------------------------------------------------------------
| Purpose:
|   Edit an existing attendance record.
|
| Security:
|   - Admin authentication
|   - CSRF protection
|   - Prepared statements
|   - Strict server-side validation
|   - Duplicate attendance protection
|   - Safe database error handling
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
| Get Attendance ID
|--------------------------------------------------------------------------
*/
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    header('Location: attendance.php');
    exit;
}

$attendance_id = (int) $id;

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$student_id = 0;
$student_name = '';
$attendance_date = '';
$status = 'Present';
$error = '';

/*
|--------------------------------------------------------------------------
| Fetch Existing Attendance Record
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'SELECT
            student_id,
            student_name,
            attendance_date,
            status
         FROM attendance
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->bind_param(
        'i',
        $attendance_id
    );

    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {

        $stmt->close();

        header('Location: attendance.php');
        exit;
    }

    $stmt->bind_result(
        $database_student_id,
        $database_student_name,
        $database_attendance_date,
        $database_status
    );

    $stmt->fetch();
    $stmt->close();

    $student_id = (int) $database_student_id;
    $student_name = (string) $database_student_name;
    $attendance_date = (string) $database_attendance_date;
    $status = (string) $database_status;

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Edit attendance fetch error: ' .
        $e->getMessage()
    );

    header('Location: attendance.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Today's Date
|--------------------------------------------------------------------------
*/
$today = new DateTimeImmutable('today');
$today_string = $today->format('Y-m-d');

/*
|--------------------------------------------------------------------------
| Process Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Preserve Submitted Values
    |--------------------------------------------------------------------------
    */
    $attendance_date = trim(
        (string) ($_POST['attendance_date'] ?? '')
    );

    $status = trim(
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
    | Validate Date
    |--------------------------------------------------------------------------
    */
    $date_object = null;

    if ($error === '') {

        if ($attendance_date === '') {

            $error =
                'Please select an attendance date.';

        } elseif (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $attendance_date
            )
        ) {

            $error =
                'Please enter a valid attendance date.';

        } else {

            $date_object = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $attendance_date
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
                $date_object->format('Y-m-d') !== $attendance_date
            ) {
                $error =
                    'Please enter a valid attendance date.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Future Date
    |--------------------------------------------------------------------------
    */
    if (
        $error === '' &&
        $date_object instanceof DateTimeImmutable
    ) {

        if ($date_object > $today) {

            $error =
                'Attendance date cannot be in the future.';
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
                $status,
                ['Present', 'Absent'],
                true
            )
        ) {

            $error =
                'Please select a valid attendance status.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Attendance
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        try {

            $duplicate_stmt = $conn->prepare(
                'SELECT id
                 FROM attendance
                 WHERE student_id = ?
                 AND attendance_date = ?
                 AND id != ?
                 LIMIT 1'
            );

            $duplicate_stmt->bind_param(
                'isi',
                $student_id,
                $attendance_date,
                $attendance_id
            );

            $duplicate_stmt->execute();
            $duplicate_stmt->store_result();

            if ($duplicate_stmt->num_rows > 0) {

                $error =
                    'Another attendance record already exists for this student on this date.';
            }

            $duplicate_stmt->close();

        } catch (mysqli_sql_exception $e) {

            error_log(
                'Class Management System - Duplicate attendance check error: ' .
                $e->getMessage()
            );

            $error =
                'Unable to validate the attendance record. Please try again.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */
    if ($error === '') {

        try {

            $update_stmt = $conn->prepare(
                'UPDATE attendance
                 SET attendance_date = ?,
                     status = ?
                 WHERE id = ?'
            );

            $update_stmt->bind_param(
                'ssi',
                $attendance_date,
                $status,
                $attendance_id
            );

            $update_stmt->execute();

            /*
            |--------------------------------------------------------------------------
            | Confirm Record Still Exists
            |--------------------------------------------------------------------------
            */
            if ($update_stmt->affected_rows < 0) {

                $update_stmt->close();

                $error =
                    'Unable to update the attendance record.';
            } else {

                $update_stmt->close();

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
                $_SESSION['attendance_update_success'] =
                    'Attendance updated successfully.';

                /*
                |--------------------------------------------------------------------------
                | POST / REDIRECT / GET
                |--------------------------------------------------------------------------
                */
                header('Location: attendance.php');
                exit;
            }

        } catch (mysqli_sql_exception $e) {

            error_log(
                'Class Management System - Update attendance error: ' .
                $e->getMessage()
            );

            /*
            |--------------------------------------------------------------------------
            | Duplicate-Key Safety
            |--------------------------------------------------------------------------
            */
            if ((int) $e->getCode() === 1062) {

                $error =
                    'Another attendance record already exists for this student on this date.';

            } else {

                $error =
                    'Unable to update attendance right now. Please try again.';
            }
        }
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
        Edit Attendance - Class Management System
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
            background: #0d6efd;
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
            border-color: #0d6efd;
            box-shadow:
                0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        .btn {
            min-height: 44px;
            border-radius: 8px;
            font-weight: 500;
        }

        .student-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            min-height: 46px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-display i {
            color: #0d6efd;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 12px 15px;
            border-radius: 6px;
            font-size: 14px;
            color: #495057;
        }

        .required-star {
            color: #dc3545;
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

                            <i class="bi bi-pencil-square fs-3"></i>

                            <div>

                                <h4>
                                    Edit Attendance
                                </h4>

                                <p>
                                    Update the attendance record
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

                        <div class="info-box mb-4">

                            <i class="bi bi-info-circle me-2"></i>

                            A student can have only one attendance record
                            for a particular date.

                        </div>

                        <form
                            method="POST"
                            action="edit_attendance.php?id=<?php echo $attendance_id; ?>"
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
                                    class="form-label"
                                    for="student_display"
                                >
                                    Student
                                </label>

                                <div
                                    id="student_display"
                                    class="student-display"
                                >

                                    <i class="bi bi-person-circle"></i>

                                    <span>
                                        <?php echo e($student_name); ?>
                                    </span>

                                </div>

                                <div class="form-text">
                                    The student cannot be changed while
                                    editing this attendance record.
                                </div>

                            </div>

                            <!-- Attendance Date -->
                            <div class="mb-4">

                                <label
                                    for="attendance_date"
                                    class="form-label"
                                >

                                    Attendance Date

                                    <span class="required-star">
                                        *
                                    </span>

                                </label>

                                <input
                                    type="date"
                                    id="attendance_date"
                                    name="attendance_date"
                                    class="form-control"
                                    value="<?php echo e($attendance_date); ?>"
                                    max="<?php echo e($today_string); ?>"
                                    required
                                >

                                <div class="form-text">
                                    Future dates are not allowed.
                                </div>

                            </div>

                            <!-- Status -->
                            <div class="mb-4">

                                <label
                                    for="status"
                                    class="form-label"
                                >

                                    Attendance Status

                                    <span class="required-star">
                                        *
                                    </span>

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
                                        echo $status === 'Present'
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        Present
                                    </option>

                                    <option
                                        value="Absent"
                                        <?php
                                        echo $status === 'Absent'
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
                                    name="update"
                                    value="1"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-check-circle me-1"></i>

                                    Update Attendance

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