
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Class Management System - Delete Attendance
|--------------------------------------------------------------------------
| Purpose:
|   Delete an attendance record.
|
| Security:
|   - Admin authentication
|   - POST-only deletion
|   - CSRF protection
|   - Positive integer ID validation
|   - Prepared statements
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
| Only POST Requests Are Allowed
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $_SESSION['attendance_delete_error'] =
        'Invalid request method.';

    header('Location: attendance.php');
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
| CSRF Validation
|--------------------------------------------------------------------------
*/
$session_csrf_token = $_SESSION['csrf_token'] ?? '';

$submitted_csrf_token = (string) (
    $_POST['csrf_token'] ?? ''
);

if (
    !is_string($session_csrf_token) ||
    $session_csrf_token === '' ||
    $submitted_csrf_token === '' ||
    !hash_equals(
        $session_csrf_token,
        $submitted_csrf_token
    )
) {

    $_SESSION['attendance_delete_error'] =
        'Your session has expired or the request is invalid. Please try again.';

    header('Location: attendance.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Attendance ID
|--------------------------------------------------------------------------
*/
$raw_id = trim(
    (string) ($_POST['id'] ?? '')
);

/*
|--------------------------------------------------------------------------
| Validate Attendance ID
|--------------------------------------------------------------------------
*/
if (
    $raw_id === '' ||
    !ctype_digit($raw_id) ||
    (int) $raw_id <= 0
) {

    $_SESSION['attendance_delete_error'] =
        'Invalid attendance record selected.';

    header('Location: attendance.php');
    exit;
}

$attendance_id = (int) $raw_id;

/*
|--------------------------------------------------------------------------
| Delete Attendance Record
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'DELETE FROM attendance
         WHERE id = ?'
    );

    $stmt->bind_param(
        'i',
        $attendance_id
    );

    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | Check Delete Result
    |--------------------------------------------------------------------------
    */
    if ($stmt->affected_rows > 0) {

        $_SESSION['attendance_delete_success'] =
            'Attendance record deleted successfully.';

        /*
        |--------------------------------------------------------------------------
        | Rotate CSRF Token After Successful State Change
        |--------------------------------------------------------------------------
        */
        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );

    } else {

        $_SESSION['attendance_delete_error'] =
            'Attendance record was not found or has already been deleted.';
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Private Error Logging
    |--------------------------------------------------------------------------
    */
    error_log(
        'Class Management System - Delete attendance error: ' .
        $e->getMessage()
    );

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Protection
    |--------------------------------------------------------------------------
    */
    if ((int) $e->getCode() === 1451) {

        $_SESSION['attendance_delete_error'] =
            'This attendance record cannot be deleted because it is being used elsewhere.';

    } else {

        $_SESSION['attendance_delete_error'] =
            'Unable to delete the attendance record. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Return to Attendance Page
|--------------------------------------------------------------------------
*/
header('Location: attendance.php');
exit;