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

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Allow POST Requests Only
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: teachers.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify CSRF Token
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
    $_SESSION['teacher_delete_error'] =
        'Invalid request. Please try again.';

    header('Location: teachers.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Teacher ID
|--------------------------------------------------------------------------
*/
$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/
if (
    $id === false ||
    $id === null ||
    $id <= 0
) {
    $_SESSION['teacher_delete_error'] =
        'Invalid teacher ID.';

    header('Location: teachers.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Teacher
|--------------------------------------------------------------------------
*/
try {

    $stmt = $conn->prepare(
        'DELETE FROM teachers WHERE id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | Check Whether a Record Was Deleted
    |--------------------------------------------------------------------------
    */
    if ($stmt->affected_rows === 0) {

        $_SESSION['teacher_delete_error'] =
            'Teacher record was not found.';

    } else {

        $_SESSION['teacher_delete_success'] =
            'Teacher deleted successfully.';
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    /*
    |--------------------------------------------------------------------------
    | Log Technical Error Privately
    |--------------------------------------------------------------------------
    */
    error_log(
        'Class Management System - Delete teacher error: ' .
        $e->getMessage()
    );

    /*
    |--------------------------------------------------------------------------
    | Handle Foreign-Key Constraint
    |--------------------------------------------------------------------------
    */
    if ((int) $e->getCode() === 1451) {

        $_SESSION['teacher_delete_error'] =
            'This teacher cannot be deleted because related records exist.';

    } else {

        $_SESSION['teacher_delete_error'] =
            'Unable to delete the teacher. Please try again later.';
    }
}

/*
|--------------------------------------------------------------------------
| Return to Teachers Page
|--------------------------------------------------------------------------
*/
header('Location: teachers.php');
exit;