<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['admin']) ||
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
| Delete Requests Must Use POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: students.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

$session_token = $_SESSION['csrf_token'] ?? '';
$submitted_token = (string) ($_POST['csrf_token'] ?? '');

if (
    !is_string($session_token) ||
    $session_token === '' ||
    $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)
) {
    $_SESSION['student_delete_error'] =
        'Invalid request. Please try again.';

    header('Location: students.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Student ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| Validate Student ID
|--------------------------------------------------------------------------
*/

if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['student_delete_error'] =
        'Invalid student ID.';

    header('Location: students.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Student
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare(
        'DELETE FROM students WHERE id = ?'
    );

    $stmt->bind_param('i', $id);

    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        $_SESSION['student_delete_error'] =
            'Student record was not found.';
    } else {
        $_SESSION['student_delete_success'] =
            'Student deleted successfully.';
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    error_log(
        'Class Management System - Delete student error: ' .
        $e->getMessage()
    );

    /*
    |--------------------------------------------------------------------------
    | Foreign-Key / Database Constraint Handling
    |--------------------------------------------------------------------------
    */

    if ((int) $e->getCode() === 1451) {
        $_SESSION['student_delete_error'] =
            'This student cannot be deleted because related records exist.';
    } else {
        $_SESSION['student_delete_error'] =
            'Unable to delete the student. Please try again later.';
    }
}

/*
|--------------------------------------------------------------------------
| Return to Students Page
|--------------------------------------------------------------------------
*/

header('Location: students.php');
exit;